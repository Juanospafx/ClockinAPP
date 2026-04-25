<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'php_errors.log');
error_reporting(E_ALL);

ini_set('session.cookie_httponly', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Asegurar que la cookie de sesión funcione en toda la app desde cualquier path
    ini_set('session.cookie_path', '/');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/cors.php';
