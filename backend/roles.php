<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    // Normalise legacy role naming.
    $findSpecialUser = $pdo->prepare("SELECT id FROM roles WHERE name = 'special user' LIMIT 1");
    $findSpecialUser->execute();
    $specialUserId = $findSpecialUser->fetchColumn();

    $findSpecial = $pdo->prepare("SELECT id FROM roles WHERE name = 'special' LIMIT 1");
    $findSpecial->execute();
    $specialId = $findSpecial->fetchColumn();

    if ($specialUserId) {
        if ($specialId) {
            // Reassign existing users from legacy role to the canonical role id and drop the duplicate.
            $updateUsers = $pdo->prepare('UPDATE users SET role_id = ? WHERE role_id = ?');
            $updateUsers->execute([$specialId, $specialUserId]);
            $deleteRole = $pdo->prepare('DELETE FROM roles WHERE id = ?');
            $deleteRole->execute([$specialUserId]);
        } else {
            // Rename the legacy role so existing users keep the same id but new name.
            $renameRole = $pdo->prepare("UPDATE roles SET name = 'special' WHERE id = ?");
            $renameRole->execute([$specialUserId]);
            $specialId = $specialUserId;
        }
    }

    $requiredRoles = ['admin', 'user', 'special'];
    $checkStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
    $insertStmt = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');

    foreach ($requiredRoles as $roleName) {
        $checkStmt->execute([$roleName]);
        if (!$checkStmt->fetchColumn()) {
            $insertStmt->execute([$roleName]);
        }
    }

    $pdo->commit();

    $stmt = $pdo->query('SELECT id, name FROM roles ORDER BY id');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'roles' => $roles]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
