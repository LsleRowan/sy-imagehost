<?php
/**
 * ImageHost 工具函数
 * MySQL 版本
 */

/**
 * 生成随机安全文件名
 */
function generateFilename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $name = bin2hex(random_bytes(8));
    return $ext !== '' ? $name . '.' . $ext : $name;
}

/**
 * 验证文件夹名称（只允许安全字符）
 */
function validateFolderName(string $name): bool
{
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name) && strlen($name) <= 50;
}

/**
 * 获取文件可读大小
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * 获取所有文件夹及图片数量（MySQL）
 */
function getFolders(): array
{
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT f.id, f.name, f.created_at,
               COUNT(i.id) AS image_count
        FROM folders f
        LEFT JOIN images i ON i.folder_id = f.id
        GROUP BY f.id, f.name, f.created_at
        ORDER BY f.name ASC
    ");
    $rows = $stmt->fetchAll();

    $folders = [];
    foreach ($rows as $row) {
        $folders[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'count' => (int)$row['image_count'],
            'created_at' => $row['created_at'],
            'dir_exists' => is_dir(UPLOAD_DIR . $row['name']),
        ];
    }

    return $folders;
}

/**
 * 获取文件夹 ID
 */
function getFolderId(string $name): ?int
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM folders WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * 获取文件夹名称
 */
function getFolderName(int $id): ?string
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name FROM folders WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : null;
}

/**
 * 创建文件夹
 */
function createFolder(string $name): bool
{
    if (!validateFolderName($name)) {
        return false;
    }

    $path = UPLOAD_DIR . $name;
    if (is_dir($path)) {
        return false;
    }

    if (!mkdir($path, 0755)) {
        return false;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO folders (name) VALUES (?)");
    try {
        return $stmt->execute([$name]);
    } catch (PDOException $e) {
        rmdir($path);
        return false;
    }
}

/**
 * 删除文件夹（必须为空）
 */
function deleteFolder(string $name): bool
{
    if (!validateFolderName($name)) {
        return false;
    }

    $path = UPLOAD_DIR . $name;
    if (!is_dir($path)) {
        return false;
    }

    // 检查数据库中是否有图片
    $folderId = getFolderId($name);
    if ($folderId !== null) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM images WHERE folder_id = ?");
        $stmt->execute([$folderId]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }
    }

    // 检查目录是否为空
    $files = scandir($path);
    $files = array_filter($files, function ($f) {
        return $f !== '.' && $f !== '..';
    });
    if (count($files) > 0) {
        return false;
    }

    // 删除数据库记录
    if ($folderId !== null) {
        $pdo->prepare("DELETE FROM folders WHERE id = ?")->execute([$folderId]);
    }

    return rmdir($path);
}

/**
 * 获取所有图片列表（MySQL）
 */
function getImages(string $folder = null): array
{
    $pdo = getDB();

    $sql = "
        SELECT i.id, i.folder_id, i.filename, i.original_name, i.mime_type,
               i.file_size, i.width, i.height, i.url, i.uploaded_at,
               f.name AS folder_name
        FROM images i
        JOIN folders f ON i.folder_id = f.id
    ";
    $params = [];

    if ($folder) {
        $sql .= " WHERE f.name = ?";
        $params[] = $folder;
    }

    $sql .= " ORDER BY i.uploaded_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $images = [];
    foreach ($rows as $row) {
        $images[] = [
            'id' => (int)$row['id'],
            'name' => $row['filename'],
            'original_name' => $row['original_name'],
            'folder' => $row['folder_name'],
            'folder_id' => (int)$row['folder_id'],
            'mime_type' => $row['mime_type'],
            'url' => $row['url'],
            'size' => (int)$row['file_size'],
            'sizeFormatted' => formatFileSize((int)$row['file_size']),
            'width' => (int)$row['width'],
            'height' => (int)$row['height'],
            'uploaded_at' => $row['uploaded_at'],
        ];
    }

    return $images;
}

/**
 * 获取单张图片信息（MySQL）
 */
function getImageById(int $id): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT i.id, i.folder_id, i.filename, i.original_name, i.mime_type,
               i.file_size, i.width, i.height, i.url, i.uploaded_at,
               f.name AS folder_name
        FROM images i
        JOIN folders f ON i.folder_id = f.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'name' => $row['filename'],
        'original_name' => $row['original_name'],
        'folder' => $row['folder_name'],
        'folder_id' => (int)$row['folder_id'],
        'mime_type' => $row['mime_type'],
        'url' => $row['url'],
        'size' => (int)$row['file_size'],
        'sizeFormatted' => formatFileSize((int)$row['file_size']),
        'width' => (int)$row['width'],
        'height' => (int)$row['height'],
        'uploaded_at' => $row['uploaded_at'],
    ];
}

/**
 * 获取总图片数量（MySQL）
 */
function getTotalImageCount(): int
{
    $pdo = getDB();
    return (int)$pdo->query("SELECT COUNT(*) FROM images")->fetchColumn();
}

/**
 * 获取总文件夹数量（MySQL）
 */
function getTotalFolderCount(): int
{
    $pdo = getDB();
    return (int)$pdo->query("SELECT COUNT(*) FROM folders")->fetchColumn();
}

/**
 * 获取总存储空间（MySQL）
 */
function getTotalStorageUsed(): int
{
    $pdo = getDB();
    $result = $pdo->query("SELECT IFNULL(SUM(file_size), 0) FROM images")->fetchColumn();
    return (int)$result;
}

/**
 * 保存上传的图片（文件 + MySQL）
 */
function saveUploadedFile(array $file, string $folder): ?array
{
    if (!validateFolderName($folder)) {
        return null;
    }

    $folderPath = UPLOAD_DIR . $folder;
    if (!is_dir($folderPath)) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return null;
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return null;
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    } else {
        $mimeType = '';
    }
    if ($mimeType !== '' && !in_array($mimeType, ALLOWED_MIMES)) {
        return null;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return null;
    }

    $newFilename = generateFilename($originalName);
    $destPath = $folderPath . '/' . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return null;
    }

    // 获取文件夹 ID
    $folderId = getFolderId($folder);
    if ($folderId === null) {
        unlink($destPath);
        return null;
    }

    $size = filesize($destPath);
    $url = BASE_URL . '/uploads/' . $folder . '/' . $newFilename;

    // 写入 MySQL
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO images (folder_id, filename, original_name, mime_type, file_size, width, height, url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $folderId,
        $newFilename,
        $originalName,
        $mimeType,
        $size,
        $imageInfo[0],
        $imageInfo[1],
        $url,
    ]);

    $imageId = (int)$pdo->lastInsertId();

    return [
        'id' => $imageId,
        'name' => $newFilename,
        'original_name' => $originalName,
        'folder' => $folder,
        'url' => $url,
        'size' => $size,
        'sizeFormatted' => formatFileSize($size),
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'uploaded_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * 删除图片（文件 + MySQL）
 */
function deleteImage(int $imageId): bool
{
    $image = getImageById($imageId);
    if (!$image) {
        return false;
    }

    // 删除实际文件
    $filePath = UPLOAD_DIR . $image['folder'] . '/' . $image['name'];
    $fileDeleted = false;
    if (file_exists($filePath)) {
        $fileDeleted = unlink($filePath);
    } else {
        // 文件不存在也算删除成功（可能已被手动删除）
        $fileDeleted = true;
    }

    if (!$fileDeleted) {
        return false;
    }

    // 删除 MySQL 记录
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM images WHERE id = ?");
    return $stmt->execute([$imageId]);
}

/**
 * 删除图片（通过文件夹名和文件名，兼容旧 API）
 */
function deleteImageByPath(string $folder, string $filename): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT i.id FROM images i
        JOIN folders f ON i.folder_id = f.id
        WHERE f.name = ? AND i.filename = ?
        LIMIT 1
    ");
    $stmt->execute([$folder, $filename]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return deleteImage((int)$row['id']);
}

/**
 * 批量上传图片（文件 + MySQL）
 */
function saveUploadedFiles(array $files, string $folder): array
{
    $result = ['success' => [], 'failed' => []];

    if (!validateFolderName($folder)) {
        foreach ($files['name'] as $i => $name) {
            $result['failed'][] = ['name' => $name, 'reason' => '无效的文件夹名称'];
        }
        return $result;
    }

    $folderPath = UPLOAD_DIR . $folder;
    if (!is_dir($folderPath)) {
        foreach ($files['name'] as $i => $name) {
            $result['failed'][] = ['name' => $name, 'reason' => '文件夹不存在'];
        }
        return $result;
    }

    $folderId = getFolderId($folder);
    if ($folderId === null) {
        foreach ($files['name'] as $i => $name) {
            $result['failed'][] = ['name' => $name, 'reason' => '文件夹不存在'];
        }
        return $result;
    }

    $pdo = getDB();
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $originalName = $files['name'][$i];
        $tmpName = $files['tmp_name'][$i];
        $error = $files['error'][$i];
        $fileSize = $files['size'][$i];

        // 检查上传错误
        if ($error !== UPLOAD_ERR_OK) {
            $reason = match ($error) {
                UPLOAD_ERR_INI_SIZE => '文件超过服务器限制',
                UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
                UPLOAD_ERR_NO_FILE => '没有选择文件',
                UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失',
                UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
                default => '未知上传错误',
            };
            $result['failed'][] = ['name' => $originalName, 'reason' => $reason];
            continue;
        }

        // 检查文件大小
        if ($fileSize > MAX_FILE_SIZE) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '文件超过最大限制 (' . formatFileSize(MAX_FILE_SIZE) . ')'];
            continue;
        }

        // 检查扩展名
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '不支持的文件格式'];
            continue;
        }

        // 检查 MIME 类型
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($tmpName);
        } else {
            $mimeType = '';
        }
        if ($mimeType !== '' && !in_array($mimeType, ALLOWED_MIMES)) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '文件类型不允许 (' . $mimeType . ')'];
            continue;
        }

        // 检查图片真实性
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '不是有效的图片文件'];
            continue;
        }

        // 生成随机文件名
        $newFilename = generateFilename($originalName);
        $destPath = $folderPath . '/' . $newFilename;

        // 保存文件
        if (!move_uploaded_file($tmpName, $destPath)) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '保存文件失败'];
            continue;
        }

        $size = filesize($destPath);
        $url = BASE_URL . '/uploads/' . $folder . '/' . $newFilename;

        // 写入 MySQL
        $stmt = $pdo->prepare("
            INSERT INTO images (folder_id, filename, original_name, mime_type, file_size, width, height, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $folderId,
            $newFilename,
            $originalName,
            $mimeType,
            $size,
            $imageInfo[0],
            $imageInfo[1],
            $url,
        ]);

        $imageId = (int)$pdo->lastInsertId();

        $result['success'][] = [
            'id' => $imageId,
            'name' => $newFilename,
            'original_name' => $originalName,
            'folder' => $folder,
            'url' => $url,
            'size' => $size,
            'sizeFormatted' => formatFileSize($size),
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    return $result;
}
