<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/RoleService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_roles_list(): void {
    require_role(['admin']);
    $result = RoleService::ensureRoles();
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 500);
        return;
    }
    json_ok($result['data']);
}
