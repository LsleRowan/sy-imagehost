<?php
/**
 * ImageHost 后台 - API 文档
 */
$pageTitle    = 'API';
$pageSubtitle = '接口说明与调用示例';
$activeNav    = 'api';
require_once '../includes/header.php';

$base = rtrim(BASE_URL, '/');
?>

<div class="page-head">
    <div>
        <h2>API 文档</h2>
        <div class="page-meta">REST 接口 · Bearer Token 鉴权 · JSON 响应</div>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h3>Base URL</h3></div>
    <div class="card-body">
        <div class="api-base">
            <?php echo icon('link', 15); ?>
            <span style="flex:1;min-width:0"><?php echo htmlspecialchars($base); ?></span>
            <button class="btn btn-sm btn-outline" type="button" data-copy="<?php echo htmlspecialchars($base, ENT_QUOTES); ?>">复制</button>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h3>公共接口</h3><span class="badge">无需认证</span></div>
    <div class="card-body">
        <div class="endpoint">
            <div class="endpoint__top">
                <span class="method method-get">GET</span>
                <span class="path">/api/folders.php</span>
            </div>
            <div class="endpoint__desc">获取所有文件夹及图片数量</div>
        </div>

        <div class="endpoint">
            <div class="endpoint__top">
                <span class="method method-get">GET</span>
                <span class="path">/api/images.php</span>
            </div>
            <div class="endpoint__desc">获取全部图片列表</div>
        </div>

        <div class="endpoint">
            <div class="endpoint__top">
                <span class="method method-get">GET</span>
                <span class="path">/api/images.php?folder=wallpaper</span>
            </div>
            <div class="endpoint__desc">获取指定文件夹的图片列表</div>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h3>写入接口</h3><span class="badge badge-orange">Bearer Token</span></div>
    <div class="card-body">
        <div class="endpoint">
            <div class="endpoint__top">
                <span class="method method-post">POST</span>
                <span class="path">/api/upload.php</span>
            </div>
            <div class="endpoint__desc">上传图片 · 表单字段：folder（必填）、image（必填）、webp（可选，传 1 转换为 WebP）</div>
        </div>

        <div class="endpoint">
            <div class="endpoint__top">
                <span class="method method-delete">DELETE</span>
                <span class="path">/api/delete.php</span>
            </div>
            <div class="endpoint__desc">删除图片 · 参数：image_id 或 folder + filename</div>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h3>认证方式</h3></div>
    <div class="card-body">
        <div class="code-block">Authorization: Bearer YOUR_TOKEN<button class="btn btn-sm btn-outline cb-copy" type="button" data-copy="Authorization: Bearer YOUR_TOKEN">复制</button></div>

        <div class="api-group-title"><?php echo icon('upload', 15); ?> 上传示例</div>
        <div class="code-block">curl -X POST <?php echo htmlspecialchars($base); ?>/api/upload.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "folder=default" \
  -F "image=@photo.jpg"<button class="btn btn-sm btn-outline cb-copy" type="button" data-copy="curl -X POST <?php echo htmlspecialchars($base); ?>/api/upload.php -H &quot;Authorization: Bearer YOUR_TOKEN&quot; -F &quot;folder=default&quot; -F &quot;image=@photo.jpg&quot;">复制</button></div>
    </div>
</section>

<section class="card">
    <div class="card-head"><h3>CORS 配置</h3></div>
    <div class="card-body">
        <div class="rail-row" style="padding:0 0 10px"><span>当前允许的来源</span></div>
        <div class="api-base">
            <span style="flex:1;min-width:0"><?php echo htmlspecialchars(CORS_ORIGIN !== '' ? CORS_ORIGIN : '未配置'); ?></span>
            <a class="btn btn-sm btn-outline" href="settings.php">修改</a>
        </div>
        <div class="form-hint">可在「设置」页面调整 CORS Origin 与 API Token</div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
