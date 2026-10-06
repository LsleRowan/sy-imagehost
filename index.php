<?php
/**
 * ImageHost 首页 - 私人图片托管服务入口
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/response.php';
setSecurityHeaders();

$siteName = 'ImageHost';
$totalImages = 0;
$totalStorage = 0;
$typeStats = [];

try {
    $siteNameSetting = trim((string)(getSetting('site_name') ?: ''));
    if ($siteNameSetting !== '') {
        $siteName = $siteNameSetting;
    }
} catch (\Throwable $e) {
    // 未安装
}

try {
    $pdo = getDB();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('settings', $tables) && in_array('images', $tables)) {
        $totalImages = (int)$pdo->query("SELECT COUNT(*) FROM images")->fetchColumn();
        $totalStorage = (int)$pdo->query("SELECT IFNULL(SUM(file_size), 0) FROM images")->fetchColumn();
        $typeStats = getFileTypeStats();
    }
} catch (\Throwable $e) {
    // 未安装或数据库异常，显示默认值
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - 私人图片托管服务</title>
    <meta name="description" content="私人图片托管服务，集中管理图片资源并提供稳定外链。">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="home-body">

    <main class="home-wrap">
        <section class="home-hero">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
            <p class="home-sub">私人图片托管服务</p>
            <p class="home-desc">集中管理你的图片资源，为个人网站和项目提供稳定、可引用的图片链接。</p>

            <div class="home-actions">
                <a class="btn btn-primary btn-lg" href="admin/">
                    <?php echo icon('image', 17); ?> 进入管理后台
                </a>
                <a class="btn btn-outline btn-lg" href="api/folders.php" target="_blank" rel="noopener">
                    <?php echo icon('code', 17); ?> API
                </a>
            </div>
        </section>

        <section class="home-stats">
            <div class="stat-card">
                <div class="stat-label"><?php echo icon('image', 15); ?> 图片数量</div>
                <div class="stat-value"><?php echo number_format($totalImages); ?></div>
                <div class="stat-foot">张图片</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?php echo icon('drive', 15); ?> 存储空间</div>
                <div class="stat-value"><?php echo formatFileSize($totalStorage); ?></div>
                <div class="stat-foot">已占用空间</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?php echo icon('layers', 15); ?> 文件类型</div>
                <div class="stat-value home-types">
                    <?php if (!empty($typeStats)): ?>
                        <?php foreach (array_slice($typeStats, 0, 3) as $t): ?>
                            <span class="badge badge-blue"><?php echo htmlspecialchars($t['label']); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge">—</span>
                    <?php endif; ?>
                </div>
                <div class="stat-foot"><?php echo count($typeStats); ?> 种格式</div>
            </div>
        </section>
    </main>

    <footer class="home-footer">
        <p>Copyright &copy; 2026 <?php echo htmlspecialchars($siteName); ?>
            · <a href="https://github.com/LsleRowan" target="_blank" rel="noopener noreferrer">LsleRowan</a>
        </p>
    </footer>

</body>
</html>
