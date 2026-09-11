<?php
/**
 * ImageHost API - 获取图片列表
 * GET /api/images.php
 * GET /api/images.php?folder=wallpaper
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';

setCorsHeaders();
setSecurityHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$folder = $_GET['folder'] ?? null;

if ($folder !== null && !validateFolderName($folder)) {
    jsonError('Invalid folder name');
}

$images = getImages($folder);

// 返回精简数据
$compactImages = array_map(function ($img) {
    return [
        'id' => $img['id'],
        'name' => $img['name'],
        'folder' => $img['folder'],
        'url' => $img['url'],
        'width' => $img['width'],
        'height' => $img['height'],
    ];
}, $images);

jsonSuccess([
    'count' => count($compactImages),
    'images' => $compactImages,
]);
