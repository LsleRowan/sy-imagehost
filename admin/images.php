<?php
$pageTitle = '图片管理';
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: images.php?msg=csrf_failed');
        exit;
    }
    $deleteId = (int)($_POST['image_id'] ?? 0);
    if ($deleteId > 0) {
        if (deleteImage($deleteId)) {
            header('Location: images.php?msg=deleted');
            exit;
        }
    }
    header('Location: images.php?msg=delete_failed');
    exit;
}

$folder = $_GET['folder'] ?? null;
$images = getImages($folder);
$folders = getFolders();
$msg = $_GET['msg'] ?? '';
?>

    <div class="container">
        <h2 style="margin-bottom:20px">图片管理</h2>

        <?php if ($msg === 'deleted'): ?>
            <div class="alert alert-success">图片已删除</div>
        <?php elseif ($msg === 'delete_failed'): ?>
            <div class="alert alert-error">删除失败</div>
        <?php endif; ?>

        <div class="filter-bar">
            <a href="images.php" class="btn btn-sm <?php echo !$folder ? 'btn-primary' : 'btn-outline'; ?>">全部</a>
            <?php foreach ($folders as $f): ?>
                <a href="images.php?folder=<?php echo urlencode($f['name']); ?>"
                   class="btn btn-sm <?php echo $folder === $f['name'] ? 'btn-primary' : 'btn-outline'; ?>">
                    <?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['count']; ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($images)): ?>
            <div class="alert alert-error">暂无图片</div>
        <?php else: ?>
            <div class="image-grid">
                <?php foreach ($images as $img): ?>
                    <div class="image-card">
                        <a href="<?php echo htmlspecialchars($img['url']); ?>" target="_blank">
                            <img class="thumb" src="<?php echo htmlspecialchars($img['url']); ?>"
                                 alt="<?php echo htmlspecialchars($img['name']); ?>" loading="lazy">
                        </a>
                        <div class="info">
                            <div class="name" title="<?php echo htmlspecialchars($img['original_name']); ?>">
                                <?php echo htmlspecialchars($img['original_name']); ?>
                            </div>
                            <div class="meta">
                                <?php echo $img['width'] . ' × ' . $img['height']; ?>
                                &middot;
                                <?php echo $img['sizeFormatted']; ?>
                                &middot;
                                <?php echo $img['uploaded_at']; ?>
                            </div>
                            <div class="card-actions">
                                <button class="btn btn-sm btn-outline"
                                        onclick="copyUrl('<?php echo htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8'); ?>')">复制</button>
                                <a href="<?php echo htmlspecialchars($img['url']); ?>" target="_blank"
                                   class="btn btn-sm btn-outline">查看</a>
                                <button class="btn btn-sm btn-danger"
                                        onclick="confirmDelete(<?php echo $img['id']; ?>)">删除</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="delete-modal">
        <div class="modal">
            <h3>确认删除</h3>
            <p>确定要删除这张图片吗？此操作不可恢复。</p>
            <form method="POST" id="delete-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="image_id" id="delete-image-id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-danger">删除</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function copyUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('已复制到剪贴板');
        });
    }

    function confirmDelete(id) {
        document.getElementById('delete-image-id').value = id;
        document.getElementById('delete-modal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('delete-modal').classList.remove('active');
    }

    document.getElementById('delete-modal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeModal();
    });
    </script>

<?php require_once '../includes/footer.php'; ?>
