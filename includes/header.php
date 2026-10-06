<?php
/**
 * ImageHost 后台公共头部（侧边栏 + 顶栏 + 主内容区）
 *
 * 可选变量：
 *   $pageTitle    页面标题（默认 ImageHost）
 *   $pageSubtitle 顶栏副标题
 *   $activeNav    当前导航项：home / images / folders / api / settings
 */
$pageTitle    = $pageTitle ?? 'ImageHost';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav ?? '';

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (empty(API_TOKEN)) {
    header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/../install.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/icons.php';
setSecurityHeaders();
requireLogin();

try {
    $siteName = trim((string)(getSetting('site_name') ?: ''));
} catch (\Throwable $e) {
    $siteName = '';
}
if ($siteName === '') {
    $siteName = 'ImageHost';
}

try {
    $storageQuota = (int)(getSetting('storage_quota') ?: 0);
} catch (\Throwable $e) {
    $storageQuota = 0;
}
if ($storageQuota <= 0) {
    $storageQuota = 10 * 1024 * 1024 * 1024; // 默认 10 GB
}

$storageUsed = getTotalStorageUsed();
$storagePct  = $storageQuota > 0 ? min(100, round($storageUsed / $storageQuota * 100)) : 0;

$navItems = [
    ['key' => 'home',     'label' => '首页',     'icon' => 'home',     'url' => 'index.php'],
    ['key' => 'images',   'label' => '图片',     'icon' => 'image',    'url' => 'images.php'],
    ['key' => 'folders',  'label' => '文件夹',   'icon' => 'folder',   'url' => 'folders.php'],
    ['key' => 'api',      'label' => 'API',      'icon' => 'code',     'url' => 'api_info.php'],
    ['key' => 'settings', 'label' => '设置',     'icon' => 'settings', 'url' => 'settings.php'],
];

$adminUsername = (string)($_SESSION['admin_username'] ?? 'admin');
$adminInitial  = mb_strtoupper(mb_substr($adminUsername, 0, 1));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__brand">
                <span class="logo-mark"><?php echo icon('image', 19); ?></span>
                <span class="brand-text">
                    <span class="brand-name"><?php echo htmlspecialchars($siteName); ?></span>
                    <span class="brand-sub">私人图片托管服务</span>
                </span>
            </div>

            <nav class="sidebar__nav" aria-label="主导航">
                <?php foreach ($navItems as $item): ?>
                    <a class="nav-item<?php echo $activeNav === $item['key'] ? ' active' : ''; ?>"
                       href="<?php echo $item['url']; ?>"
                      <?php echo $activeNav === $item['key'] ? ' aria-current="page"' : ''; ?>>
                        <?php echo icon($item['icon'], 17); ?>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__spacer"></div>

            <div class="sidebar__storage">
                <div class="st-top">
                    <span class="st-label"><?php echo icon('drive', 14); ?> 存储空间</span>
                    <strong><?php echo formatFileSize($storageUsed); ?></strong>
                </div>
                <div class="progress"><span style="width: <?php echo $storagePct; ?>%"></span></div>
                <div class="st-foot">已使用 <?php echo $storagePct; ?>% · 上限 <?php echo formatFileSize($storageQuota); ?></div>
            </div>

            <div class="sidebar__bottom">
                <a class="nav-item" href="logout.php">
                    <?php echo icon('logout', 17); ?>
                    <span>退出登录</span>
                </a>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <button class="btn btn-icon hamburger" id="sidebar-toggle" type="button"
                        aria-label="打开菜单" aria-controls="sidebar" aria-expanded="false">
                    <?php echo icon('menu', 19); ?>
                </button>

                <div class="topbar__title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p><?php echo htmlspecialchars($pageSubtitle); ?></p>
                    <?php endif; ?>
                </div>

                <div class="topbar__spacer"></div>

                <div class="topbar__actions">
                    <button class="btn btn-icon theme-toggle" id="theme-toggle" type="button"
                            aria-label="切换主题" title="切换主题">
                        <span class="icon-sun"><?php echo icon('sun', 17); ?></span>
                        <span class="icon-moon"><?php echo icon('moon', 17); ?></span>
                    </button>

                    <div class="account">
                        <button class="account__btn" id="account-btn" type="button"
                                aria-haspopup="true" aria-expanded="false">
                            <span class="avatar"><?php echo htmlspecialchars($adminInitial); ?></span>
                            <span class="acct-name"><?php echo htmlspecialchars($adminUsername); ?></span>
                            <?php echo icon('chevron-down', 14); ?>
                        </button>
                        <div class="menu account__menu" id="account-menu" role="menu">
                            <div class="acct-head">
                                <div class="nm"><?php echo htmlspecialchars($adminUsername); ?></div>
                                <div class="rl">管理员账户</div>
                            </div>
                            <a href="settings.php" role="menuitem"><?php echo icon('settings', 15); ?> 账户设置</a>
                            <a href="api_info.php" role="menuitem"><?php echo icon('code', 15); ?> API 文档</a>
                            <div class="menu-sep"></div>
                            <a href="logout.php" class="menu-danger" role="menuitem"><?php echo icon('logout', 15); ?> 退出登录</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
