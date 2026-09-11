<?php
/**
 * ImageHost Configuration
 * 所有敏感配置集中于此文件
 */

// ========================
// 数据库配置（部署时填写）
// ========================
define('DB_HOST', 'localhost');
define('DB_NAME', 'imagehost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ========================
// 上传配置
// ========================
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

// 允许的图片扩展名（小写）
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']);

// 允许的 MIME 类型
define('ALLOWED_MIMES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/avif',
]);

// ========================
// 安全配置
// ========================
// 登录失败锁定
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15分钟（秒）

// Session 配置
define('SESSION_LIFETIME', 3600); // 1小时（秒）
