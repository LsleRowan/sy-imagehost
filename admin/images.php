<?php
/**
 * ImageHost 后台 - 图片管理
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';

initSession();
setSecurityHeaders();
requireLogin();

/* ---------- POST / AJAX 处理（在输出 HTML 之前） ---------- */
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            jsonError('CSRF 验证失败', 403);
        }
        header('Location: images.php?msg=csrf_failed');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $deleteId = (int)($_POST['image_id'] ?? 0);
        $ok = $deleteId > 0 && deleteImage($deleteId);
        if ($isAjax) {
            $ok ? jsonSuccess(['deleted' => 1]) : jsonError('删除失败，图片可能不存在', 404);
        }
        header('Location: images.php?msg=' . ($ok ? 'deleted' : 'delete_failed'));
        exit;
    }

    if ($_POST['action'] === 'delete_batch') {
        $ids = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
        $deleted = 0;
        foreach ($ids as $id) {
            if ($id > 0 && deleteImage($id)) {
                $deleted++;
            }
        }
        if ($isAjax) {
            jsonSuccess(['deleted' => $deleted, 'requested' => count($ids)]);
        }
        header('Location: images.php?msg=' . ($deleted > 0 ? 'deleted' : 'delete_failed'));
        exit;
    }
}

/* ---------- 页面数据 ---------- */
$folder        = isset($_GET['folder']) ? trim((string)$_GET['folder']) : '';
$query         = trim((string)($_GET['q'] ?? ''));
$images        = getImages();   // 全量加载，文件夹/类型/关键词由前端筛选
$folders       = getFolders();
$totalImages   = getTotalImageCount();
$totalStorage  = getTotalStorageUsed();
$totalFolders  = getTotalFolderCount();
$typeStats     = getFileTypeStats();
$weeklyNew     = getRecentWeeklyCount();
$recentImages  = array_slice($images, 0, 5);
$msg           = (string)($_GET['msg'] ?? '');

$extBadges = [
    'jpg' => 'badge-blue', 'jpeg' => 'badge-blue',
    'png' => 'badge-green', 'webp' => 'badge-blue',
    'gif' => 'badge-orange', 'avif' => 'badge-green',
];

$pageTitle    = '图片管理';
$pageSubtitle = '管理、筛选与预览你的图片资源';
$activeNav    = 'images';
require_once '../includes/header.php';
?>

<?php if ($msg === 'deleted'): ?>
    <div class="alert alert-success"><?php echo icon('check-circle', 16); ?> 图片已删除</div>
<?php elseif ($msg === 'delete_failed'): ?>
    <div class="alert alert-error"><?php echo icon('alert', 16); ?> 删除失败，请重试</div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h2>图片管理</h2>
        <div class="page-meta">
            共 <span class="num"><?php echo $totalImages; ?></span> 张图片
            <span class="dot">·</span>
            <?php echo formatFileSize($totalStorage); ?>
            <?php if ($folder !== ''): ?>
                <span class="dot">·</span>
                文件夹 <?php echo htmlspecialchars($folder); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" type="button" data-upload-open>
            <?php echo icon('upload', 16); ?> 上传图片
        </button>
    </div>
</div>

<div class="images-layout">
    <div class="images-main">

        <div class="toolbar">
            <div class="search-box">
                <?php echo icon('search', 15); ?>
                <input type="search" id="img-search" placeholder="搜索文件名或文件夹…"
                       value="<?php echo htmlspecialchars($query); ?>" aria-label="搜索图片">
            </div>

            <select id="filter-folder" aria-label="按文件夹筛选">
                <option value="">全部文件夹</option>
                <?php foreach ($folders as $f): ?>
                    <option value="<?php echo htmlspecialchars($f['name']); ?>"
                        <?php echo $folder === $f['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['count']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="filter-type" aria-label="按文件类型筛选">
                <option value="">全部类型</option>
                <?php foreach (ALLOWED_EXTENSIONS as $ext): ?>
                    <option value="<?php echo $ext; ?>"><?php echo strtoupper($ext); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="sort-by" aria-label="排序方式">
                <option value="newest">最新上传</option>
                <option value="oldest">最早上传</option>
                <option value="name">文件名</option>
                <option value="size">文件大小</option>
            </select>

            <span class="toolbar__count" id="filter-count"></span>
        </div>

        <div class="batch-bar" id="batch-bar">
            <span>已选 <strong id="sel-count">0</strong> 张</span>
            <button class="btn btn-sm btn-ghost" type="button" id="select-all">全选</button>
            <span class="spacer"></span>
            <button class="btn btn-sm btn-ghost" type="button" id="select-none">取消选择</button>
            <button class="btn btn-sm btn-danger-soft" type="button" id="batch-delete">
                <?php echo icon('trash', 14); ?> 删除选中
            </button>
        </div>

        <?php if (empty($images)): ?>
            <div class="empty" id="empty-all">
                <div class="empty__icon"><?php echo icon('image', 26); ?></div>
                <h4>还没有图片</h4>
                <p>上传第一批图片，它们会立刻出现在这里，方便你管理与引用。</p>
                <button class="btn btn-primary" type="button" data-upload-open>
                    <?php echo icon('upload', 16); ?> 上传图片
                </button>
            </div>
        <?php else: ?>
            <div class="image-grid" id="image-grid">
                <?php foreach ($images as $img):
                    $ext  = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                    $date = date('Y-m-d', strtotime($img['uploaded_at']));
                ?>
                    <article class="img-card"
                        data-id="<?php echo $img['id']; ?>"
                        data-name="<?php echo htmlspecialchars($img['original_name'], ENT_QUOTES); ?>"
                        data-file="<?php echo htmlspecialchars($img['name'], ENT_QUOTES); ?>"
                        data-folder="<?php echo htmlspecialchars($img['folder'], ENT_QUOTES); ?>"
                        data-ext="<?php echo htmlspecialchars($ext, ENT_QUOTES); ?>"
                        data-size="<?php echo $img['size']; ?>"
                        data-time="<?php echo htmlspecialchars($img['uploaded_at'], ENT_QUOTES); ?>"
                        data-url="<?php echo htmlspecialchars($img['url'], ENT_QUOTES); ?>"
                        data-mime="<?php echo htmlspecialchars($img['mime_type'], ENT_QUOTES); ?>"
                        data-w="<?php echo $img['width']; ?>"
                        data-h="<?php echo $img['height']; ?>">

                        <div class="img-card__thumb">
                            <span class="img-check">
                                <input type="checkbox" class="js-select" value="<?php echo $img['id']; ?>"
                                       aria-label="选择 <?php echo htmlspecialchars($img['original_name']); ?>">
                            </span>

                            <button class="img-card__more" type="button" data-menu-btn
                                    aria-label="更多操作" aria-haspopup="true">
                                <?php echo icon('more', 15); ?>
                            </button>

                            <div class="menu img-card__menu">
                                <button type="button" data-act="preview"><?php echo icon('eye', 15); ?> 预览</button>
                                <button type="button" data-act="copy"><?php echo icon('copy', 15); ?> 复制链接</button>
                                <button type="button" data-act="download"><?php echo icon('download', 15); ?> 下载</button>
                                <div class="menu-sep"></div>
                                <button type="button" data-act="delete" class="menu-danger"><?php echo icon('trash', 15); ?> 删除</button>
                            </div>

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
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="empty" id="empty-filter" style="display:none">
                <div class="empty__icon"><?php echo icon('search', 26); ?></div>
                <h4>没有匹配的图片</h4>
                <p>换个关键词或调整筛选条件试试。</p>
                <button class="btn btn-outline" type="button" id="clear-filters">清除筛选</button>
            </div>
        <?php endif; ?>
    </div>

    <aside class="rail">
        <section class="card">
            <div class="card-head">
                <h3>图片统计</h3>
                <span class="badge badge-blue num"><?php echo $totalImages; ?></span>
            </div>
            <div class="rail-rows">
                <div class="rail-row"><span>图片数量</span><strong><?php echo $totalImages; ?> 张</strong></div>
                <div class="rail-row"><span>存储空间</span><strong><?php echo formatFileSize($totalStorage); ?></strong></div>
                <div class="rail-row"><span>文件夹</span><strong><?php echo $totalFolders; ?> 个</strong></div>
                <div class="rail-row"><span>近 7 日新增</span><strong class="num">+<?php echo $weeklyNew; ?></strong></div>
            </div>
            <?php if (!empty($typeStats)): ?>
                <div class="type-bars">
                    <?php $maxCount = max(array_column($typeStats, 'count')); ?>
                    <?php foreach (array_slice($typeStats, 0, 4) as $t): ?>
                        <div class="type-row">
                            <span class="tr-name"><?php echo htmlspecialchars($t['label']); ?></span>
                            <span class="progress"><span style="width: <?php echo $maxCount > 0 ? round($t['count'] / $maxCount * 100) : 0; ?>%"></span></span>
                            <span class="tr-count"><?php echo $t['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h3>快捷操作</h3></div>
            <div class="quick-actions">
                <button class="btn btn-outline" type="button" data-upload-open>
                    <?php echo icon('upload', 16); ?> 上传图片
                </button>
                <a class="btn btn-outline" href="folders.php?new=1">
                    <?php echo icon('folder-plus', 16); ?> 新建文件夹
                </a>
                <a class="btn btn-outline" href="api_info.php">
                    <?php echo icon('code', 16); ?> API 文档 <span class="chev"><?php echo icon('chevron-right', 15); ?></span>
                </a>
            </div>
        </section>

        <?php if (!empty($recentImages)): ?>
            <section class="card">
                <div class="card-head">
                    <h3>最近上传</h3>
                    <a class="card-link" href="images.php">查看更多</a>
                </div>
                <div class="recent-list">
                    <?php foreach ($recentImages as $img): ?>
                        <div class="recent-item" data-goto="<?php echo $img['id']; ?>"
                             title="<?php echo htmlspecialchars($img['original_name']); ?>">
                            <img class="ri-thumb" src="<?php echo htmlspecialchars($img['url']); ?>" alt="" loading="lazy">
                            <div class="ri-info">
                                <div class="ri-name"><?php echo htmlspecialchars($img['original_name']); ?></div>
                                <div class="ri-meta"><?php echo $img['sizeFormatted']; ?> · <?php echo date('m-d H:i', strtotime($img['uploaded_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php require_once '../includes/footer.php'; ?>
