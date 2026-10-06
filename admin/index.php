<?php
/**
 * ImageHost 后台 - 首页概览
 */
$pageTitle    = '首页';
$pageSubtitle = '图库概览与快捷入口';
$activeNav    = 'home';
require_once '../includes/header.php';

$totalImages  = getTotalImageCount();
$totalFolders = getTotalFolderCount();
$totalStorage = getTotalStorageUsed();
$weeklyNew    = getRecentWeeklyCount();
$typeStats    = getFileTypeStats();
$recentImages = getRecentImages(10);
$extBadges = [
    'jpg' => 'badge-blue', 'jpeg' => 'badge-blue',
    'png' => 'badge-green', 'webp' => 'badge-blue',
    'gif' => 'badge-orange', 'avif' => 'badge-green',
];

try {
    $storageQuota = (int)(getSetting('storage_quota') ?: 0);
} catch (\Throwable $e) {
    $storageQuota = 0;
}
if ($storageQuota <= 0) {
    $storageQuota = 10 * 1024 * 1024 * 1024;
}
$storagePct = min(100, round($totalStorage / $storageQuota * 100));
$typeLabels = array_slice(array_column($typeStats, 'label'), 0, 4);
?>

<div class="welcome">
    <div>
        <h2>你好，<?php echo htmlspecialchars($adminUsername); ?></h2>
        <p>这是你的图片库现状，随时上传、整理和引用。</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="images.php"><?php echo icon('image', 16); ?> 图片管理</a>
        <button class="btn btn-primary" type="button" data-upload-open>
            <?php echo icon('upload', 16); ?> 上传图片
        </button>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label"><?php echo icon('image', 15); ?> 图片数量</div>
        <div class="stat-value"><?php echo $totalImages; ?></div>
        <div class="stat-foot">近 7 日新增 <span class="up">+<?php echo $weeklyNew; ?></span></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?php echo icon('folder', 15); ?> 文件夹</div>
        <div class="stat-value"><?php echo $totalFolders; ?></div>
        <div class="stat-foot">分类归档你的图片</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?php echo icon('drive', 15); ?> 存储空间</div>
        <div class="stat-value"><?php echo formatFileSize($totalStorage); ?></div>
        <div class="stat-foot">占上限 <?php echo $storagePct; ?>% · 上限 <?php echo formatFileSize($storageQuota); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?php echo icon('layers', 15); ?> 图片类型</div>
        <div class="stat-value"><?php echo count($typeStats); ?></div>
        <div class="stat-foot"><?php echo $typeLabels ? htmlspecialchars(implode(' · ', $typeLabels)) : '暂无数据'; ?></div>
    </div>
</div>

<div class="section-title"><h3>快捷操作</h3></div>
<div class="quick-grid">
    <button class="quick-tile" type="button" data-upload-open>
        <span class="qt-icon"><?php echo icon('upload', 17); ?></span>
        <span>上传图片<small>拖拽或选择本地文件</small></span>
    </button>
    <button class="quick-tile" type="button" onclick="location.href='folders.php?new=1'">
        <span class="qt-icon"><?php echo icon('folder-plus', 17); ?></span>
        <span>新建文件夹<small>创建新的分类目录</small></span>
    </button>
    <a class="quick-tile" href="api_info.php">
        <span class="qt-icon"><?php echo icon('code', 17); ?></span>
        <span>API 文档<small>接口说明与调用示例</small></span>
    </a>
</div>

<?php if (!empty($recentImages)): ?>
    <div class="section-title">
        <h3>最近上传</h3>
        <a class="card-link" href="images.php">查看更多</a>
    </div>
    <div class="image-grid no-tools">
        <?php foreach ($recentImages as $img):
            $ext  = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            $date = date('Y-m-d', strtotime($img['uploaded_at']));
        ?>
            <a class="img-card" href="<?php echo htmlspecialchars($img['url']); ?>"
               target="_blank" rel="noopener"
               title="<?php echo htmlspecialchars($img['original_name']); ?>">
                <div class="img-card__thumb">
                    <img src="<?php echo htmlspecialchars($img['url']); ?>"
                         alt="<?php echo htmlspecialchars($img['original_name']); ?>"
                         loading="lazy"
                         onerror="this.closest('.img-card__thumb').classList.add('img-error')">
                    <div class="thumb-error">
                        <?php echo icon('image-off', 22); ?>
                        <span>图片加载失败</span>
                    </div>
                </div>
                <div class="img-card__body">
                    <div class="img-card__name" title="<?php echo htmlspecialchars($img['original_name']); ?>">
                        <?php echo htmlspecialchars($img['original_name']); ?>
                    </div>
                    <div class="img-card__meta">
                        <span><?php echo $img['sizeFormatted']; ?></span>
                        <span class="sep">·</span>
                        <span><?php echo $date; ?></span>
                        <span class="badge <?php echo $extBadges[$ext] ?? ''; ?>"><?php echo strtoupper($ext); ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
