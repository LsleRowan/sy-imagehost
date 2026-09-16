<?php
$pageTitle = '设置';
require_once '../includes/header.php';
require_once '../includes/settings.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF 验证失败';
        $messageType = 'error';
    } else {

        if ($_POST['action'] === 'save_site_settings') {
            $baseUrl = trim($_POST['base_url'] ?? '');
            $corsOrigin = trim($_POST['cors_origin'] ?? '');
            $apiToken = trim($_POST['api_token'] ?? '');
            $maxFileSize = (int)($_POST['max_file_size'] ?? 10);

            if (empty($baseUrl)) {
                $message = 'Base URL 不能为空';
                $messageType = 'error';
            } elseif (empty($apiToken)) {
                $message = 'API Token 不能为空';
                $messageType = 'error';
            } elseif ($maxFileSize < 1 || $maxFileSize > 100) {
                $message = '上传大小限制范围为 1-100 MB';
                $messageType = 'error';
            } else {
                $bytes = $maxFileSize * 1024 * 1024;
                $ok = saveSettings([
                    'base_url' => rtrim($baseUrl, '/'),
                    'cors_origin' => $corsOrigin,
                    'api_token' => $apiToken,
                    'max_file_size' => (string)$bytes,
                ]);
                if ($ok) {
                    $message = '站点配置保存成功';
                    $messageType = 'success';
                } else {
                    $message = '保存失败，请重试';
                    $messageType = 'error';
                }
            }
        }

        if ($_POST['action'] === 'generate_token') {
            $newToken = strtoupper(bin2hex(random_bytes(32)));
            $ok = saveSetting('api_token', $newToken);
            if ($ok) {
                $message = 'API Token 已重新生成';
                $messageType = 'success';
            } else {
                $message = '生成失败，请重试';
                $messageType = 'error';
            }
        }

        if ($_POST['action'] === 'rebuild_folder') {
            $folderName = trim($_POST['folder_name'] ?? '');
            if (!validateFolderName($folderName)) {
                $message = '无效的文件夹名称';
                $messageType = 'error';
            } else {
                $path = UPLOAD_DIR . $folderName;
                if (is_dir($path)) {
                    $message = '目录已存在，无需重建';
                    $messageType = 'success';
                } elseif (mkdir($path, 0755)) {
                    $message = '目录 ' . htmlspecialchars($folderName) . ' 重建成功';
                    $messageType = 'success';
                } else {
                    $message = '目录创建失败，请检查服务器权限';
                    $messageType = 'error';
                }
            }
        }

        if ($_POST['action'] === 'fix_url') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            if ($imageId <= 0) {
                $message = '无效的图片 ID';
                $messageType = 'error';
            } else {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, url FROM images WHERE id = ? LIMIT 1");
                $stmt->execute([$imageId]);
                $img = $stmt->fetch();
                if (!$img) {
                    $message = '图片不存在';
                    $messageType = 'error';
                } else {
                    $oldUrl = $img['url'];
                    $pathPart = preg_replace('#^.*/uploads/#', '/uploads/', $oldUrl);
                    $newUrl = rtrim(BASE_URL, '/') . $pathPart;
                    $update = $pdo->prepare("UPDATE images SET url = ? WHERE id = ?");
                    if ($update->execute([$newUrl, $imageId])) {
                        $message = "已修复：{$oldUrl} → {$newUrl}";
                        $messageType = 'success';
                    } else {
                        $message = '修复失败，请重试';
                        $messageType = 'error';
                    }
                }
            }
        }

        if ($_POST['action'] === 'fix_all_urls') {
            $pdo = getDB();
            $baseUrl = rtrim(BASE_URL, '/');
            $stmt = $pdo->query("SELECT id, url FROM images");
            $all = $stmt->fetchAll();
            $fixed = 0;
            foreach ($all as $row) {
                if (strpos($row['url'], $baseUrl) !== 0) {
                    $pathPart = preg_replace('#^.*/uploads/#', '/uploads/', $row['url']);
                    $newUrl = $baseUrl . $pathPart;
                    $pdo->prepare("UPDATE images SET url = ? WHERE id = ?")->execute([$newUrl, $row['id']]);
                    $fixed++;
                }
            }
            $message = "批量修复完成，共修复 {$fixed} 条 URL";
            $messageType = 'success';
        }

        if ($_POST['action'] === 'change_username') {
            $newUsername = trim($_POST['new_username'] ?? '');
            if (empty($newUsername)) {
                $message = '请输入新用户名';
                $messageType = 'error';
            } elseif (strlen($newUsername) > 50) {
                $message = '用户名最长50个字符';
                $messageType = 'error';
            } elseif (changeUsername($newUsername)) {
                $message = '用户名修改成功';
                $messageType = 'success';
            } else {
                $message = '修改失败，用户名可能已存在';
                $messageType = 'error';
            }
        }

        if ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $message = '请填写所有字段';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = '两次输入的新密码不一致';
                $messageType = 'error';
            } elseif (strlen($newPassword) < 6) {
                $message = '新密码至少6个字符';
                $messageType = 'error';
            } else {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch();

                if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
                    $message = '当前密码错误';
                    $messageType = 'error';
                } elseif (changePassword($newPassword)) {
                    $message = '密码修改成功';
                    $messageType = 'success';
                } else {
                    $message = '修改失败，请重试';
                    $messageType = 'error';
                }
            }
        }

    } // end CSRF check
}

$folders = getFolders();
$pdo = getDB();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$currentSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$currentMaxMb = round(((int)($currentSettings['max_file_size'] ?? 10485760)) / 1024 / 1024);
?>

    <div class="container">
        <h2 style="margin-bottom:20px">设置</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-columns" style="display:flex; gap:24px; margin-bottom:24px; flex-wrap:wrap;">

            <!-- 站点配置 -->
            <div style="flex:1; min-width:320px; background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
                <h3 style="margin-bottom:16px">站点配置</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_site_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="base_url">Base URL</label>
                        <input type="url" id="base_url" name="base_url" required
                               placeholder="https://img.example.com"
                               value="<?php echo htmlspecialchars($currentSettings['base_url'] ?? ''); ?>">
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">图片访问的根地址，末尾不带 /</div>
                    </div>
                    <div class="form-group">
                        <label for="cors_origin">CORS Origin</label>
                        <input type="text" id="cors_origin" name="cors_origin"
                               placeholder="https://gallery.example.com"
                               value="<?php echo htmlspecialchars($currentSettings['cors_origin'] ?? ''); ?>">
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">允许跨域访问的域名，多个用逗号分隔</div>
                    </div>
                    <div class="form-group">
                        <label for="api_token">API Token</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="api_token" name="api_token" required style="flex:1"
                                   value="<?php echo htmlspecialchars($currentSettings['api_token'] ?? ''); ?>">
                            <button type="button" class="btn btn-sm"
                                    style="white-space:nowrap;" onclick="regenerateToken()">重新生成</button>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">API 上传/删除接口的鉴权 Token</div>
                    </div>
                    <div class="form-group">
                        <label for="max_file_size">最大上传大小 (MB)</label>
                        <input type="number" id="max_file_size" name="max_file_size" required min="1" max="100"
                               value="<?php echo htmlspecialchars((string)$currentMaxMb); ?>">
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">范围 1-100 MB</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">保存配置</button>
                </form>
            </div>

            <!-- 账号管理 -->
            <div style="min-width:300px; max-width:400px; background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
                <h3 style="margin-bottom:16px">账号管理</h3>

                <!-- 修改用户名 -->
                <form method="POST" style="margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--border);">
                    <input type="hidden" name="action" value="change_username">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="new_username">新用户名</label>
                        <input type="text" id="new_username" name="new_username" required maxlength="50"
                               value="<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">保存</button>
                </form>

                <!-- 修改密码 -->
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="current_password">当前密码</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">新密码</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认新密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">修改密码</button>
                </form>
            </div>

        </div>

        <!-- 文件夹一致性检查 -->
        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:24px;">
            <h3 style="margin-bottom:16px">文件夹一致性检查</h3>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
                检查 MySQL 记录与服务器目录是否一致。如果目录缺失可点击「重建」。
            </div>

            <?php if (empty($folders)): ?>
                <div class="alert alert-error">暂无文件夹</div>
            <?php else: ?>
                <?php foreach ($folders as $f): ?>
                    <div class="folder-check-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border:1px solid var(--border); border-radius:var(--radius); margin-bottom:8px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-weight:500;"><?php echo htmlspecialchars($f['name']); ?></span>
                            <span style="font-size:13px; color:var(--text-muted);"><?php echo $f['count']; ?> 张图片</span>
                            <?php if ($f['dir_exists']): ?>
                                <span style="font-size:12px; color:var(--success);">✅ 正常</span>
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--danger);">⚠️ 目录不存在</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$f['dir_exists']): ?>
                            <form method="POST" style="display:inline">
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

        <!-- URL 一致性检查 -->
        <?php $allImages = getImagesWithUrlStatus(); ?>
        <?php $invalidImages = array_values(array_filter($allImages, fn($img) => !$img['url_valid'])); ?>

        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:24px;">
            <h3 style="margin-bottom:16px">URL 一致性检查</h3>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
                检查图片 URL 是否与当前 Base URL 一致。修改 Base URL 后，旧图片的 URL 不会自动更新，可在此修复。
            </div>

            <?php if (empty($invalidImages)): ?>
                <div class="alert alert-success">所有图片 URL 均与当前 Base URL 一致</div>
            <?php else: ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <span style="font-size:13px; color:var(--danger);">
                        发现 <?php echo count($invalidImages); ?> 条不一致的 URL
                    </span>
                    <form method="POST" style="display:inline" onsubmit="return confirm('确定要批量修复所有不一致的 URL 吗？')">
                        <input type="hidden" name="action" value="fix_all_urls">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" class="btn btn-sm btn-primary">一键修复全部</button>
                    </form>
                </div>

                <?php foreach ($invalidImages as $img): ?>
                    <div class="url-check-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border:1px solid var(--border); border-radius:var(--radius); margin-bottom:8px;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; color:var(--danger); margin-bottom:4px;">
                                ⚠️ <?php echo htmlspecialchars($img['folder_name'] . '/' . $img['filename']); ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-muted); word-break:break-all;">
                                当前：<?php echo htmlspecialchars($img['url']); ?>
                            </div>
                        </div>
                        <form method="POST" style="display:inline; margin-left:12px; flex-shrink:0;">
                            <input type="hidden" name="action" value="fix_url">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-primary">修复</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function regenerateToken() {
        if (!confirm('确定重新生成 API Token？')) return;
        var csrf = document.querySelector('input[name="csrf_token"]').value;
        fetch('settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate_token&csrf_token=' + encodeURIComponent(csrf)
        }).then(function() { location.reload(); });
    }
    </script>

<?php require_once '../includes/footer.php'; ?>
