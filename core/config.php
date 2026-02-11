<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

function load_env_file(string $path): void {
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || strpos($trimmed, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_env_file(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'America/Mexico_City');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_USER', $_ENV['DB_USER'] ?? 'brightro_qrapp_inv');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'rootadmin#');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'brightro_qrapp_inv');

define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
