<?php
/**
 * ImageHost API - 获取文件夹列表
 * GET /api/folders.php
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

$folders = getFolders();

// 返回精简数据
$compact = array_map(function ($f) {
    return [
        'name' => $f['name'],
        'count' => $f['count'],
    ];
}, $folders);

jsonSuccess($compact);
