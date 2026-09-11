<?php
/**
 * ImageHost 首页
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/response.php';
setSecurityHeaders();

// 统计数据
$totalImages = 0;
$totalStorage = 0;
$fileTypes = [];

try {
    $pdo = getDB();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('settings', $tables) && $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn() > 0) {
        if (in_array('images', $tables)) {
            $totalImages = (int)$pdo->query("SELECT COUNT(*) FROM images")->fetchColumn();
            $totalStorage = (int)$pdo->query("SELECT IFNULL(SUM(file_size), 0) FROM images")->fetchColumn();
        }
        if (in_array('images', $tables)) {
            $rows = $pdo->query("SELECT DISTINCT mime_type FROM images")->fetchAll(PDO::FETCH_COLUMN);
            $typeMap = [];
            foreach ($rows as $mime) {
                $ext = explode('/', $mime);
                $label = strtoupper(end($ext));
                if ($label === 'JPEG') $label = 'JPG';
                $typeMap[$label] = true;
            }
            $fileTypes = array_keys($typeMap);
        }
    }
} catch (PDOException $e) {
    // 未安装或数据库异常，显示默认值
}

function formatStorage(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImageHost - 私人云端图片托管</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero {
            text-align: center;
            padding: 80px 20px 60px;
        }
        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero .subtitle {
            font-size: 20px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .hero .desc {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 32px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: var(--primary);
            color: #fff;
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .hero .btn-admin:hover {
            background: var(--primary-hover);
            text-decoration: none;
        }
        .hero .btn-api {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 500;
            transition: border-color 0.2s;
        }
        .hero .btn-api:hover {
            border-color: var(--primary);
            text-decoration: none;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 48px;
        }
        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px 24px;
            text-align: center;
        }
        .feature-card .icon {
            margin-bottom: 12px;
            color: var(--primary);
            line-height: 1;
        }
        .feature-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .feature-card p {
            font-size: 14px;
            color: var(--text-muted);
        }
        .home-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 48px;
        }
        .home-stats .stat-card .number {
            font-size: 32px;
        }
        .home-footer {
            text-align: center;
            padding: 24px 20px;
            border-top: 1px solid var(--border);
            margin-top: 20px;
        }
        .home-footer p {
            font-size: 13px;
            color: var(--text-muted);
        }
        .home-footer a {
            color: var(--text-muted);
        }
        .home-footer a:hover {
            color: var(--primary);
        }
        @media (max-width: 640px) {
            .hero {
                padding: 48px 16px 40px;
            }
            .hero h1 {
                font-size: 32px;
            }
            .hero .subtitle {
                font-size: 17px;
            }
            .hero .desc {
                font-size: 14px;
            }
            .hero .btn-group {
                flex-direction: column;
                align-items: center;
            }
            .hero .btn-admin, .hero .btn-api {
                width: 100%;
                justify-content: center;
                max-width: 280px;
            }
            .features {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 32px;
            }
            .feature-card {
                padding: 24px 16px;
            }
            .home-stats {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 32px;
            }
            .home-stats .stat-card .number {
                font-size: 20px;
                word-break: break-all;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1>ImageHost</h1>
        <p class="subtitle">私人云端图片托管服务</p>
        <p class="desc">集中管理图片资源，为个人网站和项目提供稳定图片链接</p>
        <div class="btn-group">
            <a href="admin/" class="btn-admin">进入管理后台</a>
            <a href="api/folders.php" class="btn-api" target="_blank">API 文档</a>
        </div>
    </div>

    <div class="container">
        <div class="features">
            <div class="feature-card">
                <div class="icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                <h3>图片管理</h3>
                <p>管理上传的图片资源，支持文件夹分类、批量上传</p>
            </div>
            <div class="feature-card">
                <div class="icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M5.034 11.117A4.002 4.002 0 0 0 6 19h11a5 5 0 1 0-1.17-9.862L14.5 9.5"/><path d="M15.83 9.138a5.5 5.5 0 0 0-10.796 1.98S5.187 12 5.5 12.5"/></svg></div>
                <h3>云端存储</h3>
                <p>统一存储和访问图片，随时随地管理你的图片资产</p>
            </div>
            <div class="feature-card">
                <div class="icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                <h3>快速引用</h3>
                <p>生成稳定图片 URL，直接用于网站和项目中</p>
            </div>
        </div>

        <div class="home-stats">
            <div class="stat-card">
                <div class="number"><?php echo number_format($totalImages); ?></div>
                <div class="label">图片数量</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo formatStorage($totalStorage); ?></div>
                <div class="label">存储空间</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo !empty($fileTypes) ? htmlspecialchars(implode(' ', $fileTypes)) : '-'; ?></div>
                <div class="label">文件类型</div>
            </div>
        </div>
    </div>

    <div class="home-footer">
        <p>Copyright &copy; 2026 by <a href="https://github.com/LsleRowan" target="_blank" rel="noopener noreferrer">LsleRowan</a></p>
    </div>
</body>
</html>
