<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class RoleService {
    public static function ensureRoles(): array {
        $pdo = get_pdo();
        $pdo->beginTransaction();
        try {
            $findSpecialUser = $pdo->prepare("SELECT id FROM roles WHERE name = 'special user' LIMIT 1");
            $findSpecialUser->execute();
            $specialUserId = $findSpecialUser->fetchColumn();

            $findSpecial = $pdo->prepare("SELECT id FROM roles WHERE name = 'special' LIMIT 1");
            $findSpecial->execute();
            $specialId = $findSpecial->fetchColumn();

            if ($specialUserId) {
                if ($specialId) {
                    $updateUsers = $pdo->prepare('UPDATE users SET role_id = ? WHERE role_id = ?');
                    $updateUsers->execute([$specialId, $specialUserId]);
                    $deleteRole = $pdo->prepare('DELETE FROM roles WHERE id = ?');
                    $deleteRole->execute([$specialUserId]);
                } else {
                    $renameRole = $pdo->prepare("UPDATE roles SET name = 'special' WHERE id = ?");
                    $renameRole->execute([$specialUserId]);
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
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => ['code' => 'server_error', 'message' => 'Database error: ' . $e->getMessage()], 'status' => 500];
        }

        $stmt = $pdo->query('SELECT id, name FROM roles ORDER BY id');
        return ['data' => ['roles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]];
    }
}
