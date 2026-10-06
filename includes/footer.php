            </main>
        </div>
    </div>

    <div class="toast-wrap" id="toast-wrap" aria-live="polite"></div>

    <div class="upload-pill" id="upload-pill" role="button" tabindex="0" aria-label="查看上传进度">
        <span class="up-icon" id="pill-icon"></span>
        <span>正在上传 <span class="up-count" id="pill-count">0 / 0</span></span>
    </div>

    <?php
    $ihFolders = [];
    try {
        foreach (getFolders() as $f) {
            $ihFolders[] = ['name' => $f['name'], 'count' => $f['count']];
        }
    } catch (\Throwable $e) {
        $ihFolders = [];
    }

    $ihConfig = [
        'folders' => $ihFolders,
        'csrf'    => generateCsrfToken(),
        'maxMb'   => (int)round(MAX_FILE_SIZE / 1048576) ?: 10,
        'endpoint' => 'upload.php',
    ];
    ?>
    <script>window.IH = <?php echo json_encode($ihConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
