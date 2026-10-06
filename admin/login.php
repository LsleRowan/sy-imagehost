<?php
/**
 * ImageHost 管理后台 - 登录
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/icons.php';

initSession();
setSecurityHeaders();

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

try {
    $siteName = trim((string)(getSetting('site_name') ?: ''));
} catch (\Throwable $e) {
    $siteName = '';
}
if ($siteName === '') {
    $siteName = 'ImageHost';
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF 验证失败，请刷新页面重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } elseif (adminLogin($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $lockout = isIpLocked($ip);
            if ($lockout) {
                $remaining = strtotime($lockout['lockout_until']) - time();
                $error = '登录尝试次数过多，请 ' . ceil($remaining / 60) . ' 分钟后重试';
            } else {
                $error = '用户名或密码错误';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <p>私人图片托管服务 · 管理后台</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo icon('alert', 16); ?> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required autofocus
                           autocomplete="username"
                           value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">登录</button>
            </form>
        </div>
    </div>
</body>
</html>
