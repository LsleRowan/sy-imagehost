<?php
$pageTitle = '管理后台';
require_once '../includes/header.php';

$totalImages = getTotalImageCount();
$totalFolders = getTotalFolderCount();
$totalStorage = formatFileSize(getTotalStorageUsed());
?>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="number"><?php echo $totalImages; ?></div>
                <div class="label">图片数量</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $totalFolders; ?></div>
                <div class="label">文件夹数量</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $totalStorage; ?></div>
                <div class="label">存储空间</div>
            </div>
        </div>

        <div class="actions">
            <a href="upload.php" class="btn btn-primary">上传图片</a>
            <a href="images.php" class="btn btn-outline">图片管理</a>
            <a href="folders.php" class="btn btn-outline">文件夹管理</a>
            <a href="settings.php" class="btn btn-outline">设置</a>
            <a href="api_info.php" class="btn btn-outline">API 信息</a>
        </div>
    </div>

<?php require_once '../includes/footer.php'; ?>
