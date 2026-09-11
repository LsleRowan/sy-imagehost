<?php
/**
 * ImageHost 认证工具
 * MySQL 持久化登录锁定（按 IP）
 */

/**
 * 初始化 Session
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * 检查管理员是否已登录
 */
function isAdminLoggedIn(): bool
{
    initSession();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * 检查 IP 是否被锁定
 */
function isIpLocked(string $ip): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT failed_attempts, lockout_until FROM login_lockout WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $lockout = $stmt->fetch();

    if (!$lockout) {
        return null;
    }

    if ($lockout['lockout_until'] && strtotime($lockout['lockout_until']) > time()) {
        return $lockout;
    }

    // 锁定已过期，清除记录
    if ($lockout['lockout_until'] && strtotime($lockout['lockout_until']) <= time()) {
        $pdo->prepare("DELETE FROM login_lockout WHERE ip_address = ?")->execute([$ip]);
        return null;
    }

    return null;
}

/**
 * 记录登录失败
 */
function recordLoginFailure(string $ip): void
{
    $pdo = getDB();

    // 获取当前记录
    $stmt = $pdo->prepare("SELECT failed_attempts FROM login_lockout WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    $attempts = $row ? $row['failed_attempts'] + 1 : 1;

    $lockoutUntil = null;
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockoutUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
    }

    $sql = "INSERT INTO login_lockout (ip_address, failed_attempts, last_failed_at, lockout_until)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                failed_attempts = failed_attempts + 1,
                last_failed_at = NOW(),
                lockout_until = IF(failed_attempts + 1 >= " . MAX_LOGIN_ATTEMPTS . ", IFNULL(lockout_until, NOW() + INTERVAL " . LOGIN_LOCKOUT_TIME . " SECOND), lockout_until)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ip, $attempts, $lockoutUntil]);
}

/**
 * 重置登录失败记录
 */
function resetLoginFailure(string $ip): void
{
    $pdo = getDB();
    $pdo->prepare("DELETE FROM login_lockout WHERE ip_address = ?")->execute([$ip]);
}

/**
 * 管理员登录
 */
function adminLogin(string $username, string $password): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // 检查 IP 锁定
    $lockout = isIpLocked($ip);
    if ($lockout) {
        return false;
    }

    // 从数据库查询管理员
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin) {
        recordLoginFailure($ip);
        return false;
    }

    // 验证密码
    try {
        $valid = password_verify($password, $admin['password_hash']);
    } catch (\Throwable $e) {
        $valid = false;
    }

    if (!$valid) {
        recordLoginFailure($ip);
        return false;
    }

    // 登录成功，重置失败记录
    resetLoginFailure($ip);

    // 设置 Session
    initSession();
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    session_regenerate_id(true);

    return true;
}

/**
 * 管理员退出
 */
function adminLogout(): void
{
    initSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * 检查 API Token
 */
function verifyApiToken(): bool
{
    if (empty(API_TOKEN)) {
        return false;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader)) {
        return false;
    }

    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        $token = $authHeader;
    }

    return hash_equals(API_TOKEN, $token);
}

/**
 * 生成 CSRF 令牌
 */
function generateCsrfToken(): string
{
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF 令牌
 */
function verifyCsrfToken(string $token): bool
{
    initSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 要求管理员登录（否则跳转到登录页）
 */
function requireLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 修改管理员密码
 */
function changePassword(string $newPassword): bool
{
    if (strlen($newPassword) < 6) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE admin SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $_SESSION['admin_id'] ?? 0]);
    return $stmt->rowCount() > 0;
}

/**
 * 修改管理员用户名
 */
function changeUsername(string $newUsername): bool
{
    $newUsername = trim($newUsername);
    if (empty($newUsername) || strlen($newUsername) > 50) {
        return false;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE admin SET username = ? WHERE id = ?");
    try {
        $stmt->execute([$newUsername, $_SESSION['admin_id'] ?? 0]);
    } catch (PDOException $e) {
        return false;
    }

    if ($stmt->rowCount() > 0) {
        $_SESSION['admin_username'] = $newUsername;
        return true;
    }
    return false;
}
