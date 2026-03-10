<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class UserService {
    public static function listUsers(): array {
        $pdo = get_pdo();
        $stmt = $pdo->query('
            SELECT u.id, u.username, u.role_id, r.name AS role, u.profile_pic_url
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.deleted_at IS NULL
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createUser(array $data): array {
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $role = $data['role'] ?? null;
        $roleId = $data['role_id'] ?? null;

        if (!$username || !$password) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Username and password are required.'], 'status' => 400];
        }

        $pdo = get_pdo();
        if ($roleId === null && $role !== null) {
            $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $stmtRole->execute([$role]);
            $roleId = $stmtRole->fetchColumn();
        }
        if ($roleId === null) {
            $roleId = 2;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hashedPassword, $roleId]);
        return ['data' => ['message' => 'User created successfully.']];
    }

    public static function updateUser(int $id, array $data): array {
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $role = $data['role'] ?? null;
        $roleId = $data['role_id'] ?? null;

        if (!$id || (!$username && !$password && !$role && !$roleId)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing ID or data to update.'], 'status' => 400];
        }

        $pdo = get_pdo();
        $updates = [];
        $params = [];

        if ($username) {
            $updates[] = 'username = ?';
            $params[] = $username;
        }
        if ($password) {
            $updates[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($roleId !== null) {
            $updates[] = 'role_id = ?';
            $params[] = $roleId;
        } elseif ($role) {
            $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $stmtRole->execute([$role]);
            $foundRoleId = $stmtRole->fetchColumn();
            if ($foundRoleId) {
                $updates[] = 'role_id = ?';
                $params[] = $foundRoleId;
            }
        }

        if (!$updates) {
            return ['data' => ['message' => 'No changes to apply.']];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['data' => ['message' => 'User updated successfully.']];
    }

    public static function deleteUser(int $id): array {
        if (!$id) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing user ID.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // Check for dependent records
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE user_id = ?');
        $countStmt->execute([$id]);
        $recordCount = (int)$countStmt->fetchColumn();

        // Soft delete
        $stmt = $pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            return ['error' => ['code' => 'not_found', 'message' => 'User not found or already deleted.'], 'status' => 404];
        }

        return ['data' => ['message' => "User deactivated. {$recordCount} attendance records preserved."]];
    }
}
