<?php
/**
 * ImageHost API - 删除图片
 * POST /api/delete.php
 * DELETE /api/delete.php
 * Authorization: Bearer YOUR_TOKEN
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';

setCorsHeaders();
setSecurityHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Method not allowed', 405);
}

if (!verifyApiToken()) {
    jsonError('Unauthorized', 401);
}

// 获取参数
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $params);
} else {
    $params = $_POST;
}

// 支持两种方式删除：通过 image_id 或 folder+filename
$imageId = (int)($params['image_id'] ?? 0);
$folder = trim($params['folder'] ?? '');
$filename = trim($params['filename'] ?? '');

if ($imageId > 0) {
    // 通过 ID 删除
    if (deleteImage($imageId)) {
        jsonSuccess(['deleted' => true]);
    } else {
        jsonError('Delete failed. Image may not exist.', 404);
    }
} elseif (!empty($folder) && !empty($filename)) {
    // 通过文件夹+文件名删除（兼容旧版）
    if (deleteImageByPath($folder, $filename)) {
        jsonSuccess(['deleted' => true]);
    } else {
        jsonError('Delete failed. Image may not exist.', 404);
    }
} else {
    jsonError('image_id or folder+filename is required');
}
