<?php
/**
 * ImageHost 安装向导
 * 自动建表 + 站点配置初始化
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/response.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';
initSession();
setSecurityHeaders();

$siteName = 'ImageHost';
try {
    $installedName = trim((string)(getSetting('site_name') ?: ''));
    if ($installedName !== '') {
        $siteName = $installedName;
    }
} catch (\Throwable $e) {
    // settings 表尚未创建
}

// 已安装则跳转后台
try {
    $pdo = getDB();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('settings', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        if ($count > 0) {
            header('Location: admin/');
            exit;
        }
    }
} catch (PDOException $e) {
    // 数据库连接失败，继续安装流程
}

$error = '';
$success = '';
$needInstall = false;

try {
    $pdo = getDB();

    // 检查 admin 表是否存在，判断是否需要建表
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $needInstall = !in_array('admin', $tables);
} catch (PDOException $e) {
    $error = '数据库连接失败，请检查配置';
}

// POST 处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF 验证失败，请刷新页面重试';
    } else {
    $action = $_POST['action'] ?? '';

    // 自动建表
    if ($action === 'create_tables') {
        $adminUser = trim($_POST['admin_username'] ?? '');
        $adminPass = $_POST['admin_password'] ?? '';
        $adminPassConfirm = $_POST['admin_password_confirm'] ?? '';

        if (empty($adminUser)) {
            $error = '管理员用户名不能为空';
        } elseif (strlen($adminUser) > 50) {
            $error = '管理员用户名最长50个字符';
        } elseif (empty($adminPass)) {
            $error = '管理员密码不能为空';
        } elseif (strlen($adminPass) < 6) {
            $error = '管理员密码至少6个字符';
        } elseif ($adminPass !== $adminPassConfirm) {
            $error = '两次输入的密码不一致';
        } else {
            try {
                $pdo->exec("SET NAMES utf8mb4");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `admin` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `username` VARCHAR(50) NOT NULL,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `login_lockout` (
                    `ip_address` VARCHAR(45) NOT NULL,
                    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                    `last_failed_at` TIMESTAMP NULL DEFAULT NULL,
                    `lockout_until` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`ip_address`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `folders` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(50) NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `images` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `folder_id` INT UNSIGNED NOT NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `mime_type` VARCHAR(100) NOT NULL,
                    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
                    `width` INT UNSIGNED NOT NULL DEFAULT 0,
                    `height` INT UNSIGNED NOT NULL DEFAULT 0,
                    `url` VARCHAR(500) NOT NULL,
                    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_folder_id` (`folder_id`),
                    KEY `idx_uploaded_at` (`uploaded_at`),
                    CONSTRAINT `fk_images_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                    `setting_key` VARCHAR(50) NOT NULL,
                    `setting_value` TEXT NOT NULL,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                // 插入管理员
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT IGNORE INTO `admin` (`username`, `password_hash`) VALUES (?, ?)");
                $stmt->execute([$adminUser, $hash]);

                // 创建 uploads 目录
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755);
                }

                $needInstall = false;
                $success = '数据库表创建成功，请填写站点配置';
            } catch (PDOException $e) {
                $error = '建表失败，请检查数据库权限';
            }
        }
    }

    // 保存站点配置
    if ($action === 'save_settings') {
        $baseUrl = trim($_POST['base_url'] ?? '');
        $corsOrigin = trim($_POST['cors_origin'] ?? '');
        $apiToken = trim($_POST['api_token'] ?? '');
        $maxFileSize = (int)($_POST['max_file_size'] ?? 10);

        if (empty($baseUrl)) {
            $error = 'Base URL 不能为空';
        } elseif (!preg_match('#^https?://#', $baseUrl)) {
            $error = 'Base URL 格式不正确，需以 http:// 或 https:// 开头';
        } elseif (empty($apiToken)) {
            $error = 'API Token 不能为空';
        } elseif ($maxFileSize < 1 || $maxFileSize > 100) {
            $error = '上传大小限制范围为 1-100 MB';
        } else {
            $bytes = $maxFileSize * 1024 * 1024;
            $ok = saveSettings([
                'base_url' => rtrim($baseUrl, '/'),
                'cors_origin' => $corsOrigin,
                'api_token' => $apiToken,
                'max_file_size' => (string)$bytes,
            ]);

            if ($ok) {
                header('Location: admin/login.php');
                exit;
            } else {
                $error = '保存配置失败，请重试';
            }
        }
    }
    } // end CSRF check
}

$defaultToken = strtoupper(bin2hex(random_bytes(32)));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
    (function () {
        try {
            if (localStorage.getItem('ih-theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-brand">
                <h1><?php echo htmlspecialchars($siteName); ?></h1>
                <p>安装向导 · 初始化数据库与站点配置</p>
            </div>

            <div class="steps" aria-label="安装步骤">
                <div class="step<?php echo $needInstall ? ' active' : ' done'; ?>">
                    <span class="step-dot"><?php echo $needInstall ? '1' : icon('check', 12); ?></span>
                    <span class="step-label">数据库初始化</span>
                </div>
                <div class="step-line"></div>
                <div class="step<?php echo $needInstall ? '' : ' active'; ?>">
                    <span class="step-dot">2</span>
                    <span class="step-label">站点配置</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo icon('alert', 16); ?> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$needInstall && $success): ?>
                <div class="alert alert-success"><?php echo icon('check', 16); ?> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($needInstall): ?>
                <!-- 第一步：建表 + 管理员 -->
                <p class="wizard-intro">检测到数据库尚未初始化，请设置管理员账号并完成初始化。</p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_tables">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="admin_username">管理员用户名 <span class="req">*</span></label>
                        <input type="text" id="admin_username" name="admin_username" required maxlength="50"
                               value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="admin_password">管理员密码 <span class="req">*</span></label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="6"
                               placeholder="至少 6 个字符" autocomplete="new-password">
                        <div class="form-hint">用于登录管理后台，请妥善保管</div>
                    </div>
                    <div class="form-group">
                        <label for="admin_password_confirm">确认密码 <span class="req">*</span></label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required
                               minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">初始化数据库</button>
                </form>

            <?php else: ?>
                <!-- 第二步：站点配置 -->
                <p class="wizard-intro"><?php echo $success ? '数据库已就绪，继续完成站点配置。' : '请完成以下站点配置。'; ?></p>
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="base_url">Base URL <span class="req">*</span></label>
                        <input type="url" id="base_url" name="base_url" required
                               placeholder="https://img.example.com"
                               value="<?php echo htmlspecialchars($_POST['base_url'] ?? ''); ?>">
                        <div class="form-hint">图片访问的根地址，末尾不带 /</div>
                    </div>

                    <div class="form-group">
                        <label for="cors_origin">CORS Origin</label>
                        <input type="text" id="cors_origin" name="cors_origin"
                               placeholder="https://gallery.example.com"
                               value="<?php echo htmlspecialchars($_POST['cors_origin'] ?? ''); ?>">
                        <div class="form-hint">允许跨域访问的域名，多个用逗号分隔，可留空</div>
                    </div>

                    <div class="form-group">
                        <label for="api_token">API Token <span class="req">*</span></label>
                        <input type="text" id="api_token" name="api_token" required
                               value="<?php echo htmlspecialchars($_POST['api_token'] ?? $defaultToken); ?>">
                        <div class="form-hint">API 上传 / 删除接口的鉴权 Token</div>
                    </div>

                    <div class="form-group">
                        <label for="max_file_size">最大上传大小 (MB) <span class="req">*</span></label>
                        <input type="number" id="max_file_size" name="max_file_size" required min="1" max="100"
                               value="<?php echo htmlspecialchars($_POST['max_file_size'] ?? '10'); ?>">
                        <div class="form-hint">范围 1 - 100 MB</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">完成安装</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
