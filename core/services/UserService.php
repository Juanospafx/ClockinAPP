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
            $pdo->beginTransaction();

            // Busca por username incluyendo soft-deleted y bloquea fila(s) para evitar carreras.
            $existingStmt = $pdo->prepare(
                'SELECT id, deleted_at
                 FROM users
                 WHERE username = ?
                 ORDER BY (deleted_at IS NULL) DESC, deleted_at DESC, id DESC
                 FOR UPDATE'
            );
            $existingStmt->execute([$username]);
            $matches = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($matches as $row) {
                if ($row['deleted_at'] === null) {
                    $pdo->rollBack();
                    return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
                }
            }

            // Restore on recreate: reutiliza ID si existe al menos un usuario eliminado con ese username.
            if (!empty($matches)) {
                $target = $matches[0]; // más reciente según ORDER BY
                $restoreId = (int)$target['id'];

                $updates = [
                    'deleted_at = NULL',
                    'password = ?',
                    'role_id = ?',
                ];
                $params = [$hashedPassword, $roleId];

                // Campos opcionales comunes del formulario de creación.
                if (array_key_exists('profile_pic_url', $data)) {
                    $updates[] = 'profile_pic_url = ?';
                    $params[] = $data['profile_pic_url'];
                }

                // Cualquier otro campo editable enviado y existente en tabla users.
                $allowedColumns = self::getEditableCreateColumns($pdo);
                foreach ($allowedColumns as $column) {
                    if (!array_key_exists($column, $data)) {
                        continue;
                    }
                    $updates[] = "{$column} = ?";
                    $params[] = $data[$column];
                }

                $params[] = $restoreId;
                $restoreSql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $restoreStmt = $pdo->prepare($restoreSql);
                $restoreStmt->execute($params);

                $pdo->commit();
                return ['data' => ['message' => 'User restored successfully.', 'action' => 'restored', 'id' => $restoreId]];
            }

            $insertColumns = ['username', 'password', 'role_id'];
            $insertValues = [$username, $hashedPassword, $roleId];

            if (array_key_exists('profile_pic_url', $data)) {
                $insertColumns[] = 'profile_pic_url';
                $insertValues[] = $data['profile_pic_url'];
            }

            $editableColumns = self::getEditableCreateColumns($pdo);
            foreach ($editableColumns as $column) {
                if (!array_key_exists($column, $data)) {
                    continue;
                }
                $insertColumns[] = $column;
                $insertValues[] = $data[$column];
            }

            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            $sql = 'INSERT INTO users (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertValues);

            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            return ['data' => ['message' => 'User created successfully.', 'action' => 'created', 'id' => $newId]];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (self::isDuplicateKeyException($e)) {
                return ['error' => ['code' => 'conflict', 'message' => 'Username already exists for an active user.'], 'status' => 409];
            }
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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

    private static function getEditableCreateColumns(PDO $pdo): array {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $stmt = $pdo->query('SHOW COLUMNS FROM users');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $protected = [
            'id',
            'username',
            'password',
            'role_id',
            'deleted_at',
            'username_active',
            'created_at',
            'updated_at',
            'profile_pic_url',
        ];

        $cache = [];
        foreach ($columns as $column) {
            $name = (string)($column['Field'] ?? '');
            if ($name === '' || in_array($name, $protected, true)) {
                continue;
            }
            $cache[] = $name;
        }

        return $cache;
    }

    private static function isDuplicateKeyException(PDOException $e): bool {
        $sqlState = (string)($e->errorInfo[0] ?? '');
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }
}
