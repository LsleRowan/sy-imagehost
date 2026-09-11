<?php
$pageTitle = 'API 信息';
require_once '../includes/header.php';
?>

    <div class="container">
        <h2 style="margin-bottom:20px">API 信息</h2>

        <div class="api-info">
            <h3>Base URL</h3>
            <div class="api-endpoint">
                <span class="path"><?php echo htmlspecialchars(BASE_URL); ?></span>
            </div>

            <h3 style="margin-top:24px">公共接口（无需认证）</h3>

            <div class="api-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/folders.php</span>
                <div class="api-desc">获取所有文件夹及图片数量</div>
            </div>

            <div class="api-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/images.php</span>
                <div class="api-desc">获取全部图片列表</div>
            </div>

            <div class="api-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/images.php?folder=wallpaper</span>
                <div class="api-desc">获取指定文件夹的图片列表</div>
            </div>

            <h3 style="margin-top:24px">需要认证的接口</h3>

            <div class="api-endpoint">
                <span class="method method-post">POST</span>
                <span class="path">/api/upload.php</span>
                <div class="api-desc">上传图片（需要 Bearer Token）</div>
            </div>

            <div class="api-endpoint">
                <span class="method method-delete">DELETE</span>
                <span class="path">/api/delete.php</span>
                <div class="api-desc">删除图片（需要 Bearer Token）</div>
            </div>

            <h3 style="margin-top:24px">认证方式</h3>
            <div class="api-endpoint">
                <span class="path">Authorization: Bearer YOUR_TOKEN</span>
            </div>

            <h3 style="margin-top:24px">CORS 配置</h3>
            <div class="api-endpoint">
                <span class="path">当前允许的来源：<?php echo htmlspecialchars(CORS_ORIGIN); ?></span>
                <div class="api-desc">可在「设置」页面修改 CORS 配置</div>
            </div>
        </div>
    </div>

<?php require_once '../includes/footer.php'; ?>
