<?php
/**
 * ImageHost 管理后台 - 退出登录
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

adminLogout();
header('Location: login.php');
exit;
