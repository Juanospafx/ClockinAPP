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

define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'UTC');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');

if (DB_USER === '' || DB_NAME === '') {
    error_log('FATAL: Missing required database settings in .env file (DB_USER/DB_NAME)');
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => ['code' => 'config_error', 'message' => 'Server configuration error.']]);
        exit;
    }
}

define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
