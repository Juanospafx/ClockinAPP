<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';
require_once __DIR__ . '/../../../core/response.php';

function handle_auth_login(): void {
    $data = read_json_body();
    $username = trim((string)($data['user'] ?? $data['username'] ?? ''));
    $password = (string)($data['pass'] ?? $data['password'] ?? '');

    if ($username === '' || $password === '') {
        json_error('validation_error', 'Username and password are required.', 400);
        return;
    }

    $user = AuthService::login($username, $password);
    if (!$user) {
        json_error('invalid_credentials', 'Incorrect username or password.', 401);
        return;
    }

    json_ok(['user' => $user]);
}
