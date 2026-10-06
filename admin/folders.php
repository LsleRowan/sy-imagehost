<?php
/**
 * ImageHost 后台 - 文件夹
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';

initSession();
setSecurityHeaders();
requireLogin();

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            jsonError('CSRF 验证失败', 403);
        }
        header('Location: folders.php?msg=csrf_failed');
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            if ($isAjax) jsonError('请输入文件夹名称');
            header('Location: folders.php?msg=create_failed');
            exit;
        }
        if (!validateFolderName($name)) {
            if ($isAjax) jsonError('文件夹名称只允许字母、数字、下划线和短横线，最长 50 字符');
            header('Location: folders.php?msg=create_failed');
            exit;
        }
        $ok = createFolder($name);
        if ($isAjax) {
            $ok ? jsonSuccess(['name' => $name]) : jsonError('创建失败，文件夹可能已存在');
        }
        header('Location: folders.php?msg=' . ($ok ? 'created' : 'create_failed'));
        exit;
    }

    if ($action === 'rename') {
        $old = trim((string)($_POST['old_name'] ?? ''));
        $new = trim((string)($_POST['name'] ?? ''));
        if (!validateFolderName($new)) {
            if ($isAjax) jsonError('文件夹名称只允许字母、数字、下划线和短横线，最长 50 字符');
            header('Location: folders.php?msg=rename_failed');
            exit;
        }
        $ok = renameFolder($old, $new);
        if ($isAjax) {
            $ok ? jsonSuccess(['name' => $new]) : jsonError('重命名失败，新名称可能已存在');
        }
        header('Location: folders.php?msg=' . ($ok ? 'renamed' : 'rename_failed'));
        exit;
    }

    if ($action === 'delete') {
        $name = trim((string)($_POST['name'] ?? ''));
        $ok = deleteFolder($name);
        if ($isAjax) {
            $ok ? jsonSuccess(['deleted' => 1]) : jsonError('删除失败，文件夹不为空或不存在');
        }
        header('Location: folders.php?msg=' . ($ok ? 'deleted' : 'delete_failed'));
        exit;
    }
}

$folders      = getFolders();
$totalFolders = count($folders);
$totalImages  = array_sum(array_column($folders, 'count'));
$msg          = (string)($_GET['msg'] ?? '');

$msgMap = [
    'created'       => ['success', '文件夹已创建'],
    'renamed'       => ['success', '文件夹已重命名'],
    'deleted'       => ['success', '文件夹已删除'],
    'create_failed' => ['error',   '创建失败，名称可能已存在或不合法'],
    'rename_failed' => ['error',   '重命名失败，新名称可能已存在'],
    'delete_failed' => ['error',   '删除失败，文件夹不为空或不存在'],
    'csrf_failed'   => ['error',   'CSRF 验证失败，请刷新页面重试'],
];
$pageMsg = $msgMap[$msg] ?? null;

$pageTitle    = '文件夹';
$pageSubtitle = '按文件夹组织你的图片资源';
$activeNav    = 'folders';
require_once '../includes/header.php';
?>

<?php if ($pageMsg): ?>
    <div id="page-msg" hidden data-type="<?php echo $pageMsg[0]; ?>" data-text="<?php echo htmlspecialchars($pageMsg[1]); ?>"></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h2>文件夹</h2>
        <div class="page-meta">
            共 <span class="num"><?php echo $totalFolders; ?></span> 个文件夹
            <span class="dot">·</span>
            <span class="num"><?php echo $totalImages; ?></span> 张图片
        </div>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" type="button" data-folder-create>
            <?php echo icon('folder-plus', 16); ?> 新建文件夹
        </button>
    </div>
</div>

<?php if (empty($folders)): ?>
    <div class="empty">
        <div class="empty__icon"><?php echo icon('folder', 26); ?></div>
        <h4>还没有文件夹</h4>
        <p>创建第一个文件夹，用它来归类上传的图片。</p>
        <button class="btn btn-primary" type="button" data-folder-create>
            <?php echo icon('folder-plus', 16); ?> 新建文件夹
        </button>
    </div>
<?php else: ?>
    <div class="folder-grid">
        <?php foreach ($folders as $f): ?>
            <div class="folder-card" data-name="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>">
                <div class="folder-card__icon" data-fact="open" data-folder="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>" role="button" tabindex="0" aria-label="进入 <?php echo htmlspecialchars($f['name']); ?>">
                    <?php echo icon('folder', 19); ?>
                </div>

                <div class="folder-card__info" data-fact="open" data-folder="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>" role="button" tabindex="0">
                    <div class="folder-card__name" title="<?php echo htmlspecialchars($f['name']); ?>">
                        <?php echo htmlspecialchars($f['name']); ?>
                    </div>
                    <div class="folder-card__meta">
                        <?php echo $f['count']; ?> 张图片
                        <?php if (!empty($f['created_at'])): ?>
                            <span class="dot">·</span> <?php echo date('Y-m-d', strtotime($f['created_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="folder-card__more" type="button" data-menu-btn
                        aria-label="更多操作" aria-haspopup="true">
                    <?php echo icon('more', 15); ?>
                </button>

                <div class="menu folder-card__menu">
                    <button type="button" data-fact="open" data-folder="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>">
                        <?php echo icon('image', 15); ?> 进入查看
                    </button>
                    <button type="button" data-fact="rename" data-folder="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>">
                        <?php echo icon('pencil', 15); ?> 重命名
                    </button>
                    <?php if ($f['count'] === 0): ?>
                        <div class="menu-sep"></div>
                        <button type="button" data-fact="delete" data-folder="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>" class="menu-danger">
                            <?php echo icon('trash', 15); ?> 删除
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
