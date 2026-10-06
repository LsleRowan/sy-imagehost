<?php
/**
 * ImageHost 后台 - 设置
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/settings.php';

initSession();
setSecurityHeaders();
requireLogin();

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $message = '';
    $messageType = 'success';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF 验证失败，请刷新页面重试';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];

        if ($action === 'save_site_settings') {
            $siteName  = trim($_POST['site_name'] ?? '');
            $baseUrl   = trim($_POST['base_url'] ?? '');
            $corsOrigin = trim($_POST['cors_origin'] ?? '');
            $maxFileSize = (int)($_POST['max_file_size'] ?? 10);
            $quotaGb = (float)($_POST['storage_quota'] ?? 0);

            if (empty($baseUrl)) {
                $message = 'API 地址（Base URL）不能为空';
                $messageType = 'error';
            } elseif ($maxFileSize < 1 || $maxFileSize > 100) {
                $message = '上传大小限制范围为 1-100 MB';
                $messageType = 'error';
            } elseif ($quotaGb < 0.1 || $quotaGb > 10240) {
                $message = '存储上限范围为 0.1 - 10240 GB';
                $messageType = 'error';
            } else {
                $ok = saveSettings([
                    'site_name'     => $siteName !== '' ? $siteName : 'ImageHost',
                    'base_url'      => rtrim($baseUrl, '/'),
                    'cors_origin'   => $corsOrigin,
                    'max_file_size' => (string)($maxFileSize * 1024 * 1024),
                    'storage_quota' => (string)(int)($quotaGb * 1024 * 1024 * 1024),
                ]);
                $message = $ok ? '站点设置已保存' : '保存失败，请重试';
                $messageType = $ok ? 'success' : 'error';
            }
        }

        if ($action === 'save_security_settings') {
            $sessionDays   = (float)($_POST['session_days'] ?? 0);
            $maxAttempts   = (int)($_POST['max_login_attempts'] ?? 0);
            $lockoutMins   = (int)($_POST['lockout_minutes'] ?? 0);

            if ($sessionDays < 1 || $sessionDays > 90) {
                $message = '会话有效期范围为 1 - 90 天';
                $messageType = 'error';
            } elseif ($maxAttempts < 3 || $maxAttempts > 20) {
                $message = '连续失败次数范围为 3 - 20 次';
                $messageType = 'error';
            } elseif ($lockoutMins < 1 || $lockoutMins > 1440) {
                $message = '锁定时长范围为 1 - 1440 分钟';
                $messageType = 'error';
            } else {
                $ok = saveSettings([
                    'session_lifetime'   => (string)(int)($sessionDays * 86400),
                    'max_login_attempts' => (string)$maxAttempts,
                    'login_lockout_time' => (string)($lockoutMins * 60),
                ]);
                $message = $ok ? '登录安全设置已保存' : '保存失败，请重试';
                $messageType = $ok ? 'success' : 'error';
            }
        }

        if ($action === 'generate_token') {
            $newToken = strtoupper(bin2hex(random_bytes(32)));
            $ok = saveSetting('api_token', $newToken);
            if ($isAjax) {
                $ok ? jsonSuccess(['token' => $newToken]) : jsonError('生成失败，请重试');
            }
            $message = $ok ? 'API Token 已重新生成' : '生成失败，请重试';
            $messageType = $ok ? 'success' : 'error';
        }

        if ($action === 'rebuild_folder') {
            $folderName = trim($_POST['folder_name'] ?? '');
            if (!validateFolderName($folderName)) {
                $message = '无效的文件夹名称';
                $messageType = 'error';
            } else {
                $path = UPLOAD_DIR . $folderName;
                if (is_dir($path)) {
                    $message = '目录已存在，无需重建';
                } elseif (mkdir($path, 0755)) {
                    $message = '目录 ' . $folderName . ' 重建成功';
                } else {
                    $message = '目录创建失败，请检查服务器权限';
                    $messageType = 'error';
                }
            }
        }

        if ($action === 'fix_url') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            $message = '无效的图片 ID';
            $messageType = 'error';
            if ($imageId > 0) {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, url FROM images WHERE id = ? LIMIT 1");
                $stmt->execute([$imageId]);
                $img = $stmt->fetch();
                if (!$img) {
                    $message = '图片不存在';
                    $messageType = 'error';
                } else {
                    $pathPart = preg_replace('#^.*/uploads/#', '/uploads/', $img['url']);
                    $newUrl = rtrim(BASE_URL, '/') . $pathPart;
                    $update = $pdo->prepare("UPDATE images SET url = ? WHERE id = ?");
                    if ($update->execute([$newUrl, $imageId])) {
                        $message = '已修复：' . $img['url'] . ' → ' . $newUrl;
                    } else {
                        $message = '修复失败，请重试';
                        $messageType = 'error';
                    }
                }
            }
        }

        if ($action === 'fix_all_urls') {
            $pdo = getDB();
            $baseUrl = rtrim(BASE_URL, '/');
            $all = $pdo->query("SELECT id, url FROM images")->fetchAll();
            $fixed = 0;
            foreach ($all as $row) {
                if (strpos($row['url'], $baseUrl) !== 0) {
                    $pathPart = preg_replace('#^.*/uploads/#', '/uploads/', $row['url']);
                    $pdo->prepare("UPDATE images SET url = ? WHERE id = ?")
                        ->execute([$baseUrl . $pathPart, $row['id']]);
                    $fixed++;
                }
            }
            $message = '批量修复完成，共修复 ' . $fixed . ' 条 URL';
        }

        if ($action === 'change_username') {
            $newUsername = trim($_POST['new_username'] ?? '');
            if (empty($newUsername)) {
                $message = '请输入新用户名';
                $messageType = 'error';
            } elseif (strlen($newUsername) > 50) {
                $message = '用户名最长 50 个字符';
                $messageType = 'error';
            } elseif (changeUsername($newUsername)) {
                $message = '用户名修改成功';
            } else {
                $message = '修改失败，用户名可能已存在';
                $messageType = 'error';
            }
        }

        if ($action === 'change_password') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newPassword) || empty($confirmPassword)) {
                $message = '请填写所有字段';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = '两次输入的新密码不一致';
                $messageType = 'error';
            } elseif (strlen($newPassword) < 6) {
                $message = '新密码至少 6 个字符';
                $messageType = 'error';
            } elseif (changePassword($newPassword)) {
                $message = '密码修改成功';
            } else {
                $message = '修改失败，请重试';
                $messageType = 'error';
            }
        }
    }

    if ($isAjax) {
        $messageType === 'success' ? jsonSuccess([]) : jsonError($message);
    }

    $_SESSION['flash'] = ['type' => $messageType, 'text' => $message];
    header('Location: settings.php');
    exit;
}

$pageMsg = null;
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $pageMsg = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

/* ---------- 页面数据 ---------- */
$pdo = getDB();
$currentSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$currentMaxMb = round(((int)($currentSettings['max_file_size'] ?? 10485760)) / 1024 / 1024);
$currentQuotaGb = round(((int)($currentSettings['storage_quota'] ?? 10737418240)) / 1073741824, 1);
$currentSiteName = trim((string)($currentSettings['site_name'] ?? ''));
if ($currentSiteName === '') {
    $currentSiteName = 'ImageHost';
}
$currentSessionDays = round(((int)($currentSettings['session_lifetime'] ?? DEFAULT_SESSION_LIFETIME)) / 86400, 1);
$currentAttempts    = (int)($currentSettings['max_login_attempts'] ?? DEFAULT_MAX_LOGIN_ATTEMPTS);
$currentLockoutMins = (int)round(((int)($currentSettings['login_lockout_time'] ?? DEFAULT_LOGIN_LOCKOUT_TIME)) / 60);

$folders      = getFolders();
$allImages = getImagesWithUrlStatus();
$invalidImages = array_values(array_filter($allImages, fn($img) => !$img['url_valid']));

$pageTitle    = '设置';
$pageSubtitle = '站点、存储、API 与账户';
$activeNav    = 'settings';
require_once '../includes/header.php';
?>

<?php if ($pageMsg): ?>
    <div id="page-msg" hidden
         data-type="<?php echo htmlspecialchars($pageMsg['type']); ?>"
         data-text="<?php echo htmlspecialchars($pageMsg['text']); ?>"></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h2>设置</h2>
        <div class="page-meta">站点配置、API 与账户安全</div>
    </div>
</div>

<div class="settings-grid">

    <!-- 站点设置 -->
    <section class="card">
        <div class="card-head"><h3>站点设置</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_site_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="form-group">
                    <label for="site_name">站点名称</label>
                    <input type="text" id="site_name" name="site_name" maxlength="50"
                           placeholder="ImageHost" value="<?php echo htmlspecialchars($currentSiteName); ?>">
                    <div class="form-hint">显示在侧边栏、登录页与首页</div>
                </div>

                <div class="form-group">
                    <label for="base_url">API 地址（Base URL）</label>
                    <input type="url" id="base_url" name="base_url" required
                           placeholder="https://img.example.com"
                           value="<?php echo htmlspecialchars($currentSettings['base_url'] ?? ''); ?>">
                    <div class="form-hint">图片访问的根地址，末尾不带 /</div>
                </div>

                <div class="form-group">
                    <label for="cors_origin">CORS Origin</label>
                    <input type="text" id="cors_origin" name="cors_origin"
                           placeholder="https://gallery.example.com"
                           value="<?php echo htmlspecialchars($currentSettings['cors_origin'] ?? ''); ?>">
                    <div class="form-hint">允许跨域访问的域名，多个用逗号分隔</div>
                </div>

                <div class="form-group">
                    <label for="max_file_size">最大上传大小（MB）</label>
                    <input type="number" id="max_file_size" name="max_file_size" required min="1" max="100"
                           value="<?php echo htmlspecialchars((string)$currentMaxMb); ?>">
                    <div class="form-hint">范围 1 - 100 MB</div>
                </div>

                <div class="form-group">
                    <label for="storage_quota">存储上限（GB）</label>
                    <input type="number" id="storage_quota" name="storage_quota" required min="0.1" max="10240" step="0.1"
                           value="<?php echo htmlspecialchars((string)$currentQuotaGb); ?>">
                    <div class="form-hint">仅用于侧边栏与首页的用量显示</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">保存设置</button>
            </form>
        </div>
    </section>

    <!-- 登录安全 -->
    <section class="card">
        <div class="card-head"><h3>登录安全</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_security_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="form-group">
                    <label for="session_days">会话有效期（天）</label>
                    <input type="number" id="session_days" name="session_days" required min="1" max="90" step="0.5"
                           value="<?php echo htmlspecialchars((string)$currentSessionDays); ?>">
                    <div class="form-hint">登录 Cookie 的保持时间，范围 1 - 90 天</div>
                </div>

                <div class="form-group">
                    <label for="max_login_attempts">连续失败次数</label>
                    <input type="number" id="max_login_attempts" name="max_login_attempts" required min="3" max="20"
                           value="<?php echo $currentAttempts; ?>">
                    <div class="form-hint">同一 IP 连续登录失败达到此次数后锁定，范围 3 - 20 次</div>
                </div>

                <div class="form-group">
                    <label for="lockout_minutes">锁定时长（分钟）</label>
                    <input type="number" id="lockout_minutes" name="lockout_minutes" required min="1" max="1440"
                           value="<?php echo $currentLockoutMins; ?>">
                    <div class="form-hint">触发后该 IP 的锁定时间，登录成功会立即解锁，范围 1 - 1440 分钟</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">保存设置</button>
            </form>
        </div>
    </section>

    <!-- 账户设置 -->
    <section class="card">
        <div class="card-head"><h3>账户设置</h3></div>
        <div class="card-body">
            <form method="POST" style="margin-bottom:20px">
                <input type="hidden" name="action" value="change_username">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-group">
                    <label for="new_username">用户名</label>
                    <input type="text" id="new_username" name="new_username" required maxlength="50"
                           value="<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">保存用户名</button>
            </form>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                    <div class="form-hint">至少 6 个字符，修改后下次登录生效</div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">修改密码</button>
            </form>
        </div>
    </section>

    <!-- API 设置 -->
    <section class="card">
        <div class="card-head">
            <h3>API 设置</h3>
            <a class="card-link" href="api_info.php">API 文档</a>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="api_token">API Token</label>
                <div class="token-row">
                    <input type="text" id="api_token" readonly
                           value="<?php echo htmlspecialchars($currentSettings['api_token'] ?? ''); ?>">
                    <button class="btn btn-outline" type="button"
                            data-copy-target="#api_token" data-copy="">复制</button>
                </div>
                <div class="form-hint">用于 /api/upload.php 与 /api/delete.php 的 Bearer 鉴权</div>
            </div>

            <button class="btn btn-outline btn-block" type="button" data-regen-token>
                <?php echo icon('refresh', 15); ?> 重新生成 Token
            </button>

            <div class="form-group" style="margin-top:16px;margin-bottom:0">
                <label>调用示例</label>
                <div class="code-block">curl -X POST <?php echo htmlspecialchars(rtrim(BASE_URL, '/')); ?>/api/upload.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "folder=default" \
  -F "image=@photo.jpg"</div>
            </div>
        </div>
    </section>

    <!-- 维护 -->
    <section class="card settings-full">
        <div class="card-head"><h3>维护工具</h3></div>
        <div class="card-body">
            <div class="hint-inline">
                检查数据库记录与服务器目录 / URL 是否一致，异常时可在此修复。
            </div>

            <div class="form-group" style="margin-bottom:8px">
                <label>文件夹一致性检查</label>
                <?php if (empty($folders)): ?>
                    <div class="form-hint">暂无文件夹</div>
                <?php else: ?>
                    <?php foreach ($folders as $f): ?>
                        <div class="check-item">
                            <div class="ci-main">
                                <span class="ci-name"><?php echo htmlspecialchars($f['name']); ?></span>
                                <span class="ci-sub"><?php echo $f['count']; ?> 张图片</span>
                                <?php if ($f['dir_exists']): ?>
                                    <span class="ci-ok"><?php echo icon('check', 13); ?> 正常</span>
                                <?php else: ?>
                                    <span class="ci-bad"><?php echo icon('alert-triangle', 13); ?> 目录不存在</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$f['dir_exists']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="rebuild_folder">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="folder_name" value="<?php echo htmlspecialchars($f['name']); ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">重建目录</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom:0;margin-top:18px">
                <label>URL 一致性检查</label>

                <?php if (empty($invalidImages)): ?>
                    <div class="check-item">
                        <div class="ci-main">
                            <span class="ci-ok"><?php echo icon('check', 13); ?> 所有图片 URL 均与当前 API 地址一致</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="check-item" style="margin-bottom:8px;background:var(--danger-soft);border-color:transparent">
                        <div class="ci-main">
                            <span class="ci-bad"><?php echo icon('alert-triangle', 13); ?> 发现 <?php echo count($invalidImages); ?> 条不一致的 URL</span>
                        </div>
                        <form method="POST" onsubmit="return confirm('确定要批量修复所有不一致的 URL 吗？')">
                            <input type="hidden" name="action" value="fix_all_urls">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button type="submit" class="btn btn-sm btn-primary">一键修复全部</button>
                        </form>
                    </div>

                    <?php foreach ($invalidImages as $img): ?>
                        <div class="check-item">
                            <div class="ci-main" style="min-width:0">
                                <span class="ci-name"><?php echo htmlspecialchars($img['folder_name'] . '/' . $img['filename']); ?></span>
                                <span class="ci-url">当前：<?php echo htmlspecialchars($img['url']); ?></span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="fix_url">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline">修复</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>
