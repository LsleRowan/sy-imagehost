<?php
/**
 * ImageHost 数据库连接
 * PDO 封装，全局单例
 */

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('数据库连接失败');
        }
    }

    return $pdo;
}

// 从数据库加载站点配置
try {
    $settings = getDB()->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $settings = [];
}
define('BASE_URL', $settings['base_url'] ?? '');
define('CORS_ORIGIN', $settings['cors_origin'] ?? '');
define('API_TOKEN', $settings['api_token'] ?? '');
define('MAX_FILE_SIZE', (int)($settings['max_file_size'] ?? 0));
