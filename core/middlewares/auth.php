<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../response.php';

function require_login(): int {
    $userId = AuthService::getCurrentUserId();
    if (!$userId || !AuthService::isUserActive($userId)) {
        // Si el usuario fue soft-deleted, se invalida la sesión inmediatamente.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        json_error('unauthorized', 'Unauthorized', 401);
        exit;
    }
    return $userId;
}

function require_role(array $allowedRoles): string {
    $role = AuthService::getCurrentUserRole();
    if (!$role || !in_array($role, $allowedRoles, true)) {
        json_error('forbidden', 'Access denied.', 403);
        exit;
    }
    return $role;
}
