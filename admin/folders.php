<?php
$pageTitle = '文件夹管理';
require_once '../includes/header.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF 验证失败';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'create') {
        $newName = trim($_POST['name'] ?? '');
        if (empty($newName)) {
            $message = '请输入文件夹名称';
            $messageType = 'error';
        } elseif (!validateFolderName($newName)) {
            $message = '文件夹名称只允许字母、数字、下划线和短横线，最长50字符';
            $messageType = 'error';
        } elseif (createFolder($newName)) {
            $message = '文件夹创建成功';
            $messageType = 'success';
        } else {
            $message = '创建失败，文件夹可能已存在';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete') {
        $delName = trim($_POST['name'] ?? '');
        if (deleteFolder($delName)) {
            $message = '文件夹已删除';
            $messageType = 'success';
        } else {
            $message = '删除失败，文件夹不存在或不为空';
            $messageType = 'error';
        }
    }
}

$folders = getFolders();
?>

    <div class="container">
        <h2 style="margin-bottom:20px">文件夹管理</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:24px;">
            <h3 style="margin-bottom:12px">创建新文件夹</h3>
            <form method="POST" style="display:flex; gap:8px;">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="text" name="name" placeholder="文件夹名称" required
                       pattern="[a-zA-Z0-9_-]+" maxlength="50"
                       style="flex:1; padding:10px 12px; border:1px solid var(--border); border-radius:var(--radius); font-size:14px;">
                <button type="submit" class="btn btn-primary">创建</button>
            </form>
            <div style="font-size:12px; color:var(--text-muted); margin-top:8px">
                只允许字母、数字、下划线和短横线
            </div>
        </div>

        <?php if (empty($folders)): ?>
            <div class="alert alert-error">暂无文件夹</div>
        <?php else: ?>
            <div class="folder-list">
                <?php foreach ($folders as $f): ?>
                    <div class="folder-item">
                        <div>
                            <div class="name"><?php echo htmlspecialchars($f['name']); ?></div>
                            <div class="count"><?php echo $f['count']; ?> 张图片</div>
                        </div>
                        <div>
                            <?php if ($f['count'] === 0): ?>
                                <form method="POST" style="display:inline"
                                      data-folder="<?php echo htmlspecialchars($f['name']); ?>"
                                      onsubmit="return confirm('确定要删除文件夹 ' + this.dataset.folder + ' 吗？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($f['name']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--text-muted)">非空</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once '../includes/footer.php'; ?>
