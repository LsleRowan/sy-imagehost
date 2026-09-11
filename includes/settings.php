<?php
/**
 * ImageHost 站点配置工具
 * 从数据库读取/写入站点配置
 */

/**
 * 获取单个配置值
 */
function getSetting(string $key, mixed $default = null): mixed
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

/**
 * 保存单个配置值
 */
function saveSetting(string $key, string $value): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    return $stmt->execute([$key, $value]);
}

/**
 * 批量保存配置值
 */
function saveSettings(array $settings): bool
{
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}
