<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function normalize_role_name(?string $rawRole): ?string {
    if (!$rawRole) {
        return null;
    }
    $normalized = strtolower(trim($rawRole));
    if ($normalized === 'special user' || $normalized === 'special-user') {
        return 'special';
    }
    return $normalized;
}

function get_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function get_current_user_role(): ?string {
    static $cachedRole = null;

    if ($cachedRole !== null) {
        return $cachedRole;
    }

    $userId = get_current_user_id();
    if (!$userId) {
        return null;
    }

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        $cachedRole = normalize_role_name($role);
        return $cachedRole;
    } catch (PDOException $e) {
        error_log('Failed to resolve current user role: ' . $e->getMessage());
        return null;
    }
}

function require_authenticated_role(array $allowedRoles): string {
    $role = get_current_user_role();
    if (!$role || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
    return $role;
}
?>
