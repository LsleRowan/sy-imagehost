<?php
/**
 * ImageHost 后台公共头部
 */
$pageTitle = $pageTitle ?? 'ImageHost';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (empty(API_TOKEN)) {
    header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/../install.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';
setSecurityHeaders();
requireLogin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - ImageHost</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>ImageHost</h1>
            <nav>
                <a href="index.php">首页</a>
                <a href="upload.php">上传</a>
                <a href="images.php">图片</a>
                <a href="folders.php">文件夹</a>
                <a href="api_info.php">API</a>
                <a href="settings.php">设置</a>
                <a href="logout.php">退出</a>
            </nav>
        </div>
    </div>
