<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/UserService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_users_list(): void {
    require_role(['admin']);
    $users = UserService::listUsers();
    json_ok(['users' => $users]);
}

function handle_users_create(): void {
    require_role(['admin']);
    $data = read_json_body();
    $result = UserService::createUser($data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_users_update(int $userId): void {
    require_role(['admin']);
    $data = read_json_body();
    $result = UserService::updateUser($userId, $data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_users_delete(int $userId): void {
    require_role(['admin']);
    $result = UserService::deleteUser($userId);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
