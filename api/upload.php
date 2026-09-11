<?php
/**
 * ImageHost API - 上传图片
 * POST /api/upload.php
 * Authorization: Bearer YOUR_TOKEN
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';

setCorsHeaders();
setSecurityHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

if (!verifyApiToken()) {
    jsonError('Unauthorized', 401);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonError('No file uploaded');
}

$folder = trim($_POST['folder'] ?? '');

if (empty($folder)) {
    jsonError('Folder is required');
}

if (!validateFolderName($folder)) {
    jsonError('Invalid folder name');
}

$result = saveUploadedFile($_FILES['image'], $folder);

if ($result) {
    jsonSuccess($result);
} else {
    jsonError('Upload failed. Check file format and size.', 400);
}
