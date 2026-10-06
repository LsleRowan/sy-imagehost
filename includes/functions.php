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
 * 获取所有图片及其 URL 一致性状态
 */
function getImagesWithUrlStatus(): array
{
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT i.id, i.url, i.filename, f.name AS folder_name
        FROM images i
        JOIN folders f ON i.folder_id = f.id
        ORDER BY i.uploaded_at DESC
    ");
    $rows = $stmt->fetchAll();
    $baseUrl = rtrim(BASE_URL, '/');

    $images = [];
    foreach ($rows as $row) {
        $url = $row['url'];
        $images[] = [
            'id' => (int)$row['id'],
            'url' => $url,
            'filename' => $row['filename'],
            'folder_name' => $row['folder_name'],
            'url_valid' => strpos($url, $baseUrl) === 0,
        ];
    }
    return $images;
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
 * 获取最近上传的图片（按上传时间倒序）
 */
function getRecentImages(int $limit = 10): array
{
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT i.id, i.folder_id, i.filename, i.original_name, i.mime_type,
               i.file_size, i.width, i.height, i.url, i.uploaded_at,
               f.name AS folder_name
        FROM images i
        JOIN folders f ON i.folder_id = f.id
        ORDER BY i.uploaded_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
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
 * 重命名文件夹（数据库 + 物理目录）
 */
function renameFolder(string $oldName, string $newName): bool
{
    if (!validateFolderName($oldName) || !validateFolderName($newName)) {
        return false;
    }

    if ($oldName === $newName) {
        return true;
    }

    $folderId = getFolderId($oldName);
    if ($folderId === null) {
        return false;
    }

    if (getFolderId($newName) !== null || is_dir(UPLOAD_DIR . $newName)) {
        return false;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE folders SET name = ? WHERE id = ?");
    try {
        if (!$stmt->execute([$newName, $folderId])) {
            return false;
        }
    } catch (PDOException $e) {
        return false;
    }

    $oldPath = UPLOAD_DIR . $oldName;
    $newPath = UPLOAD_DIR . $newName;

    if (is_dir($oldPath) && !rename($oldPath, $newPath)) {
        // 目录改名失败则回滚数据库
        $pdo->prepare("UPDATE folders SET name = ? WHERE id = ?")->execute([$oldName, $folderId]);
        return false;
    }

    if (!is_dir($newPath)) {
        @mkdir($newPath, 0755);
    }

    return true;
}

/**
 * 文件类型分布统计
 * 返回：[['label' => 'JPG', 'count' => 12, 'bytes' => 123456], ...]
 */
function getFileTypeStats(): array
{
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT mime_type, MAX(filename) AS sample, COUNT(*) AS cnt, IFNULL(SUM(file_size), 0) AS bytes
        FROM images
        GROUP BY mime_type
        ORDER BY cnt DESC
    ");

    $stats = [];
    foreach ($stmt->fetchAll() as $row) {
        $parts = explode('/', (string)$row['mime_type']);
        $label = strtoupper(end($parts));
        if ($label === '') {
            // mime_type 为空时退回用文件扩展名兜底，避免显示"未知"
            $label = strtoupper(pathinfo((string)$row['sample'], PATHINFO_EXTENSION));
        }
        if ($label === 'JPEG') {
            $label = 'JPG';
        }
        $stats[] = [
            'label' => $label !== '' ? $label : '未知',
            'count' => (int)$row['cnt'],
            'bytes' => (int)$row['bytes'],
        ];
    }

    return $stats;
}

/**
 * 近 7 天新增图片数量
 */
function getRecentWeeklyCount(): int
{
    $pdo = getDB();
    return (int)$pdo->query(
        "SELECT COUNT(*) FROM images WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();
}

/**
 * 服务器是否支持 WebP 转换（需要 GD 的 imagewebp）
 */
function supportsWebpConversion(): bool
{
    return function_exists('imagewebp');
}

/**
 * 判断 GIF 是否为动画（含 NETSCAPE2.0 循环块）
 */
function isAnimatedGif(string $path): bool
{
    $data = @file_get_contents($path);
    if ($data === false) {
        return false;
    }
    return strpos($data, 'NETSCAPE2.0') !== false;
}

/**
 * 将图片转换为 WebP 写入 $destPath，返回是否成功
 */
function convertImageToWebp(string $srcPath, string $destPath, int $quality = 85): bool
{
    if (!supportsWebpConversion()) {
        return false;
    }

    $info = @getimagesize($srcPath);
    if ($info === false) {
        return false;
    }

    $img = false;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($srcPath);
    } elseif ($info[2] === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        $img = @imagecreatefrompng($srcPath);
    } elseif ($info[2] === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
        $img = @imagecreatefromgif($srcPath);
    } elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($srcPath);
    } elseif (defined('IMAGETYPE_AVIF') && $info[2] === IMAGETYPE_AVIF && function_exists('imagecreatefromavif')) {
        $img = @imagecreatefromavif($srcPath);
    }

    if ($img === false) {
        return false;
    }

    if ($info[2] === IMAGETYPE_GIF) {
        @imagepalettetotruecolor($img);
    }

    // EXIF 方向修正（GD 输出不保留 EXIF 元数据）
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data($srcPath);
        $orientation = (int)($exif['Orientation'] ?? 1);
        if ($orientation >= 2 && $orientation <= 8) {
            $rotate = function ($image, $angle) {
                $rotated = imagerotate($image, $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($image);
                    return $rotated;
                }
                return $image;
            };
            switch ($orientation) {
                case 2:
                    imageflip($img, IMG_FLIP_HORIZONTAL);
                    break;
                case 3:
                    $img = $rotate($img, 180);
                    break;
                case 4:
                    imageflip($img, IMG_FLIP_VERTICAL);
                    break;
                case 5:
                    $img = $rotate($img, -90);
                    imageflip($img, IMG_FLIP_HORIZONTAL);
                    break;
                case 6:
                    $img = $rotate($img, -90);
                    break;
                case 7:
                    $img = $rotate($img, 90);
                    imageflip($img, IMG_FLIP_HORIZONTAL);
                    break;
                case 8:
                    $img = $rotate($img, 90);
                    break;
            }
        }
    }

    // 保留透明通道
    $hasAlpha = in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
        || (defined('IMAGETYPE_AVIF') && $info[2] === IMAGETYPE_AVIF);
    if ($hasAlpha) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    $ok = @imagewebp($img, $destPath, $quality);
    imagedestroy($img);
    return $ok;
}

/**
 * 保存上传的图片（文件 + MySQL）
 */
function saveUploadedFile(array $file, string $folder, bool $convertWebp = false): ?array
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

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return null;
    }

    // finfo 不可用时用 getimagesize 的真实类型兜底
    if ($mimeType === '' || $mimeType === false) {
        $mimeType = $imageInfo['mime'] ?? '';
    }
    if (!in_array($mimeType, ALLOWED_MIMES)) {
        return null;
    }

    // WebP 转换（勾选且非 webp 源、非动画 GIF）
    $toWebp = $convertWebp
        && supportsWebpConversion()
        && $imageInfo[2] !== IMAGETYPE_WEBP
        && !($imageInfo[2] === IMAGETYPE_GIF && isAnimatedGif($file['tmp_name']));

    $newFilename = generateFilename($originalName);
    $destPath = $folderPath . '/' . $newFilename;

    // 转换为 WebP；失败则回退按原图保存
    $converted = false;
    if ($toWebp) {
        $webpName = preg_replace('/\.[^.]+$/', '.webp', $newFilename);
        $webpPath = $folderPath . '/' . $webpName;
        if (convertImageToWebp($file['tmp_name'], $webpPath)) {
            $newFilename = $webpName;
            $destPath = $webpPath;
            $converted = true;
        }
    }

    if (!$converted && !move_uploaded_file($file['tmp_name'], $destPath)) {
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
    $finalMime = ($converted || $imageInfo[2] === IMAGETYPE_WEBP) ? 'image/webp' : $mimeType;
    $finalInfo = $converted ? (@getimagesize($destPath) ?: $imageInfo) : $imageInfo;

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
        $finalMime,
        $size,
        $finalInfo[0],
        $finalInfo[1],
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
        'width' => $finalInfo[0],
        'height' => $finalInfo[1],
        'converted' => $converted,
        'ext' => strtolower(pathinfo($newFilename, PATHINFO_EXTENSION)),
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
function saveUploadedFiles(array $files, string $folder, bool $convertWebp = false): array
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

        // 检查图片真实性
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '不是有效的图片文件'];
            continue;
        }

        // finfo 不可用时用 getimagesize 的真实类型兜底
        if ($mimeType === '' || $mimeType === false) {
            $mimeType = $imageInfo['mime'] ?? '';
        }
        if (!in_array($mimeType, ALLOWED_MIMES)) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '文件类型不允许 (' . $mimeType . ')'];
            continue;
        }

        // WebP 转换（勾选且非 webp 源、非动画 GIF）
        $toWebp = $convertWebp
            && supportsWebpConversion()
            && $imageInfo[2] !== IMAGETYPE_WEBP
            && !($imageInfo[2] === IMAGETYPE_GIF && isAnimatedGif($tmpName));

        // 生成随机文件名
        $newFilename = generateFilename($originalName);
        $destPath = $folderPath . '/' . $newFilename;

        // 转换为 WebP；失败则回退按原图保存
        $converted = false;
        if ($toWebp) {
            $webpName = preg_replace('/\.[^.]+$/', '.webp', $newFilename);
            $webpPath = $folderPath . '/' . $webpName;
            if (convertImageToWebp($tmpName, $webpPath)) {
                $newFilename = $webpName;
                $destPath = $webpPath;
                $converted = true;
            }
        }

        // 保存文件
        if (!$converted && !move_uploaded_file($tmpName, $destPath)) {
            $result['failed'][] = ['name' => $originalName, 'reason' => '保存文件失败'];
            continue;
        }

        $size = filesize($destPath);
        $url = BASE_URL . '/uploads/' . $folder . '/' . $newFilename;
        $finalMime = ($converted || $imageInfo[2] === IMAGETYPE_WEBP) ? 'image/webp' : $mimeType;
        $finalInfo = $converted ? (@getimagesize($destPath) ?: $imageInfo) : $imageInfo;

        // 写入 MySQL
        $stmt = $pdo->prepare("
            INSERT INTO images (folder_id, filename, original_name, mime_type, file_size, width, height, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $folderId,
            $newFilename,
            $originalName,
            $finalMime,
            $size,
            $finalInfo[0],
            $finalInfo[1],
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
            'width' => $finalInfo[0],
            'height' => $finalInfo[1],
            'converted' => $converted,
            'ext' => strtolower(pathinfo($newFilename, PATHINFO_EXTENSION)),
        ];
    }

    return $result;
}
