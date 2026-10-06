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
    $convertWebp = (($_POST['webp'] ?? '') === '1');
    if ($convertWebp && !supportsWebpConversion()) {
        $result = ['error' => '服务器未启用 GD WebP 转换'];
    } elseif (empty($folder)) {
        $result = ['error' => '请选择文件夹'];
    } elseif (!validateFolderName($folder)) {
        $result = ['error' => '无效的文件夹名称'];
    } elseif (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $result = ['error' => '请选择要上传的图片'];
    } else {
        $result = saveUploadedFiles($_FILES['images'], $folder, $convertWebp);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 上传已改为图片页内的 Modal，旧地址在输出 HTML 之前直接跳转
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';
initSession();
setSecurityHeaders();
requireLogin();
header('Location: images.php');
exit;
