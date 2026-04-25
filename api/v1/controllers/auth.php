<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';
require_once __DIR__ . '/../../../core/services/CsrfService.php';
require_once __DIR__ . '/../../../core/services/RateLimitService.php';
require_once __DIR__ . '/../../../core/response.php';

// --- CONFIGURACIÓN LOCAL XAMPP ---
// Descomenta este bloque para ver los errores de PHP reales en tu pantalla 
// y para evitar bloqueos de CORS si abres el frontend directamente.

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");


function handle_auth_login(): void {
    $data = read_json_body();
    $username = trim((string)($data['user'] ?? $data['username'] ?? ''));
    $password = (string)($data['pass'] ?? $data['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($username === '' || $password === '') {
        json_error('validation_error', 'Username and password are required.', 400);
        return;
    }

    RateLimitService::ensureTable();
    RateLimitService::clearOldAttempts();

    if (RateLimitService::isBlocked($ip, $username)) {
        json_error('rate_limited', 'Too many login attempts. Please wait 15 minutes.', 429);
        return;
    }

    $user = AuthService::login($username, $password);
    if (!$user) {
        RateLimitService::recordAttempt($ip, $username);
        json_error('invalid_credentials', 'Incorrect username or password.', 401);
        return;
    }

    $csrfToken = CsrfService::generateToken();
    json_ok(['user' => $user, 'csrf_token' => $csrfToken]);
}

function handle_auth_logout(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    json_ok(['message' => 'Logged out successfully.']);
}
