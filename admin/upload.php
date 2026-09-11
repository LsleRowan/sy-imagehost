<?php
$pageTitle = '上传图片';

// AJAX 上传请求：在 header 输出之前返回 JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once dirname(__DIR__) . '/includes/db.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/auth.php';
    require_once dirname(__DIR__) . '/includes/response.php';
    requireLogin();
    setSecurityHeaders();

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'CSRF 验证失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $folder = trim($_POST['folder'] ?? '');
    if (empty($folder)) {
        $result = ['error' => '请选择文件夹'];
    } elseif (!validateFolderName($folder)) {
        $result = ['error' => '无效的文件夹名称'];
    } elseif (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $result = ['error' => '请选择要上传的图片'];
    } else {
        $result = saveUploadedFiles($_FILES['images'], $folder);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once '../includes/header.php';

$folders = getFolders();
?>

    <div class="container">
        <h2 style="margin-bottom:20px">上传图片</h2>

        <div id="upload-result"></div>

        <form method="POST" enctype="multipart/form-data" id="upload-form">
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="upload-area" id="upload-area">
                <input type="file" name="images[]" id="file-input" multiple
                       accept="image/jpeg,image/png,image/gif,image/webp,image/avif">
                <div class="icon">+</div>
                <div class="text">点击或拖拽选择图片</div>
                <div class="text" style="font-size:12px; margin-top:4px">支持多选，单个最大 <?php echo round(MAX_FILE_SIZE / 1048576); ?>MB</div>
            </div>

            <div id="file-list" style="display:none; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong id="file-count">已选择 0 个文件</strong>
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearFiles()">清空</button>
                </div>
                <div id="file-items" style="max-height:200px; overflow-y:auto;"></div>
            </div>

            <div class="form-group">
                <label for="folder">选择文件夹</label>
                <select name="folder" id="folder" required>
                    <option value="">-- 选择文件夹 --</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?php echo htmlspecialchars($f['name']); ?>">
                            <?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['count']; ?> 张)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" id="upload-btn" disabled>选择文件后上传</button>
        </form>
    </div>

    <script>
    const BATCH_SIZE = 20;
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const fileList = document.getElementById('file-list');
    const fileItems = document.getElementById('file-items');
    const fileCount = document.getElementById('file-count');
    const uploadBtn = document.getElementById('upload-btn');
    let selectedFiles = [];

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        addFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
        addFiles(e.target.files);
    });

    function addFiles(fileArray) {
        for (let i = 0; i < fileArray.length; i++) {
            const file = fileArray[i];
            if (!file.type.startsWith('image/')) continue;

            let exists = false;
            for (let j = 0; j < selectedFiles.length; j++) {
                if (selectedFiles[j].name === file.name && selectedFiles[j].size === file.size) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                selectedFiles.push(file);
            }
        }
        updateFileList();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFileList();
    }

    function clearFiles() {
        selectedFiles = [];
        fileInput.value = '';
        updateFileList();
    }

    function updateFileList() {
        if (selectedFiles.length === 0) {
            fileList.style.display = 'none';
            uploadBtn.disabled = true;
            uploadBtn.textContent = '选择文件后上传';
            return;
        }

        fileList.style.display = 'block';
        uploadBtn.disabled = false;
        uploadBtn.textContent = '上传 ' + selectedFiles.length + ' 张图片';

        fileCount.textContent = '已选择 ' + selectedFiles.length + ' 个文件';
        let html = '';
        let totalSize = 0;

        for (let i = 0; i < selectedFiles.length; i++) {
            const file = selectedFiles[i];
            totalSize += file.size;
            const size = formatSize(file.size);
            html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius); margin-bottom:6px; font-size:13px;">'
                + '<span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + escapeHtml(file.name) + '</span>'
                + '<span style="margin:0 12px; color:var(--text-muted); white-space:nowrap;">' + size + '</span>'
                + '<button type="button" class="btn btn-sm btn-danger" onclick="removeFile(' + i + ')">移除</button>'
                + '</div>';
        }

        html = '<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;">总计: ' + formatSize(totalSize) + '</div>' + html;
        fileItems.innerHTML = html;
    }

    function formatSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function copyUrl(btn) {
        const input = btn.previousElementSibling;
        input.select();
        document.execCommand('copy');
        const originalText = btn.textContent;
        btn.textContent = '已复制';
        setTimeout(() => btn.textContent = originalText, 1500);
    }

    document.getElementById('upload-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const folder = document.getElementById('folder').value;
        if (!folder) {
            alert('请选择文件夹');
            return;
        }
        if (selectedFiles.length === 0) {
            alert('请选择要上传的图片');
            return;
        }

        const totalFiles = selectedFiles.length;
        const batches = [];
        for (let i = 0; i < totalFiles; i += BATCH_SIZE) {
            batches.push(selectedFiles.slice(i, i + BATCH_SIZE));
        }

        uploadBtn.disabled = true;
        uploadArea.style.pointerEvents = 'none';
        uploadArea.style.opacity = '0.5';

        let allSuccess = [];
        let allFailed = [];
        let currentBatch = 0;

        function uploadBatch() {
            if (currentBatch >= batches.length) {
                uploadBtn.disabled = false;
                uploadArea.style.pointerEvents = '';
                uploadArea.style.opacity = '';
                showResult(allSuccess, allFailed, totalFiles);
                clearFiles();
                return;
            }

            const batch = batches[currentBatch];
            const uploaded = currentBatch * BATCH_SIZE;
            uploadBtn.textContent = '上传中... (' + Math.min(uploaded + batch.length, totalFiles) + '/' + totalFiles + ')';

            const formData = new FormData();
            formData.append('folder', folder);
            formData.append('csrf_token', document.getElementById('csrf_token').value);
            for (let i = 0; i < batch.length; i++) {
                formData.append('images[]', batch[i]);
            }

            fetch('upload.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) allSuccess = allSuccess.concat(data.success);
                if (data.failed) allFailed = allFailed.concat(data.failed);
                currentBatch++;
                uploadBatch();
            })
            .catch(() => {
                for (let i = 0; i < batch.length; i++) {
                    allFailed.push({ name: batch[i].name, reason: '请求失败' });
                }
                currentBatch++;
                uploadBatch();
            });
        }

        uploadBatch();
    });

    function showResult(success, failed, total) {
        let html = '';
        const successCount = success.length;
        const failedCount = failed.length;

        if (successCount > 0 || failedCount > 0) {
            html += '<div class="alert alert-' + (failedCount > 0 ? 'error' : 'success') + '">'
                + '上传完成：' + successCount + ' 个成功' + (failedCount > 0 ? '，' + failedCount + ' 个失败' : '')
                + '</div>';
        }

        if (successCount > 0) {
            html += '<div class="table-wrapper" style="padding:16px; margin-bottom:16px;"><table class="table"><thead><tr>'
                + '<th>文件名</th><th>文件夹</th><th>尺寸</th><th>大小</th><th>URL</th>'
                + '</tr></thead><tbody>';
            for (let i = 0; i < success.length; i++) {
                const img = success[i];
                html += '<tr>'
                    + '<td style="font-size:12px">' + escapeHtml(img.original_name) + '</td>'
                    + '<td>' + escapeHtml(img.folder) + '</td>'
                    + '<td>' + img.width + ' × ' + img.height + '</td>'
                    + '<td>' + escapeHtml(img.sizeFormatted) + '</td>'
                    + '<td><div class="url-box"><input type="text" value="' + escapeHtml(img.url) + '" readonly style="font-size:11px">'
                    + '<button class="btn btn-sm btn-primary" onclick="copyUrl(this)">复制</button></div></td>'
                    + '</tr>';
            }
            html += '</tbody></table></div>';
        }

        if (failedCount > 0) {
            html += '<div class="table-wrapper" style="padding:16px; margin-bottom:16px;"><table class="table"><thead><tr>'
                + '<th>文件名</th><th>失败原因</th>'
                + '</tr></thead><tbody>';
            for (let i = 0; i < failed.length; i++) {
                const fail = failed[i];
                html += '<tr>'
                    + '<td>' + escapeHtml(fail.name) + '</td>'
                    + '<td style="color:var(--danger)">' + escapeHtml(fail.reason) + '</td>'
                    + '</tr>';
            }
            html += '</tbody></table></div>';
        }

        let resultArea = document.getElementById('upload-result');
        if (!resultArea) {
            resultArea = document.createElement('div');
            resultArea.id = 'upload-result';
            const container = document.querySelector('.container');
            const h2 = container.querySelector('h2');
            h2.insertAdjacentElement('afterend', resultArea);
        }
        resultArea.innerHTML = html;
    }
    </script>

<?php require_once '../includes/footer.php'; ?>
