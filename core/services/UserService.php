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
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role = $data['role'] ?? null;
        $roleId = $data['role_id'] ?? null;

        if ($username === '' || $password === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'Username and password are required.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // En soft-delete solo validamos colisión contra usuarios activos.
        if (self::activeUsernameExists($pdo, $username)) {
            return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
        }

        if ($roleId === null && $role !== null) {
            $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $stmtRole->execute([$role]);
            $roleId = $stmtRole->fetchColumn();
        }
        if ($roleId === null) {
            $roleId = 2;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hashedPassword, $roleId]);
        } catch (PDOException $e) {
            if (self::isDuplicateKeyException($e)) {
                return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
            }
            throw $e;
        }

        return ['data' => ['message' => 'User created successfully.']];
    }

    public static function updateUser(int $id, array $data): array {
        $username = array_key_exists('username', $data) ? trim((string)$data['username']) : null;
        $password = array_key_exists('password', $data) ? (string)$data['password'] : null;
        $role = $data['role'] ?? null;
        $roleId = $data['role_id'] ?? null;

        if (!$id || ($username === null && $password === null && !$role && !$roleId)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing ID or data to update.'], 'status' => 400];
        }

        $pdo = get_pdo();

        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $existsStmt->execute([$id]);
        if (!$existsStmt->fetchColumn()) {
            return ['error' => ['code' => 'not_found', 'message' => 'User not found or deleted.'], 'status' => 404];
        }

        $updates = [];
        $params = [];

        if ($username !== null) {
            if ($username === '') {
                return ['error' => ['code' => 'validation_error', 'message' => 'Username cannot be empty.'], 'status' => 400];
            }

            // Evita choque consigo mismo y con otros activos.
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL AND id <> ? LIMIT 1');
            $checkStmt->execute([$username, $id]);
            if ($checkStmt->fetchColumn()) {
                return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
            }

            $updates[] = 'username = ?';
            $params[] = $username;
        }

        if ($password !== null) {
            if ($password === '') {
                return ['error' => ['code' => 'validation_error', 'message' => 'Password cannot be empty.'], 'status' => 400];
            }
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

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ? AND deleted_at IS NULL';
        $params[] = $id;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (self::isDuplicateKeyException($e)) {
                return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
            }
            throw $e;
        }

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

        // Soft delete (se mantiene username intacto; la unicidad la controla username_active en DB)
        $stmt = $pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            return ['error' => ['code' => 'not_found', 'message' => 'User not found or already deleted.'], 'status' => 404];
        }

        return ['data' => ['message' => "User deactivated. {$recordCount} attendance records preserved."]];
    }

    private static function activeUsernameExists(PDO $pdo, string $username): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$username]);
        return (bool)$stmt->fetchColumn();
    }

    private static function isDuplicateKeyException(PDOException $e): bool {
        $sqlState = (string)($e->errorInfo[0] ?? '');
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }
}
