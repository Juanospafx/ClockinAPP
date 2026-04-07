<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class AuthService {
    public static function normalizeRole(?string $rawRole): ?string {
        if (!$rawRole) {
            return null;
        }
        $normalized = strtolower(trim($rawRole));
        if ($normalized === 'special user' || $normalized === 'special-user') {
            return 'special';
        }
        return $normalized;
    }

    public static function getCurrentUserId(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function isUserActive(int $userId): bool {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function getCurrentUserRole(): ?string {
        static $cachedRole = null;
        if ($cachedRole !== null) {
            return $cachedRole;
        }

        $userId = self::getCurrentUserId();
        if (!$userId) {
            return null;
        }

        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        $cachedRole = self::normalizeRole($role ?: null);
        return $cachedRole;
    }

    public static function login(string $username, string $password): ?array {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.password, r.name AS role, u.profile_pic_url
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.username = ? AND u.deleted_at IS NULL'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!password_verify($password, $row['password'])) {
            return null;
        }
        $_SESSION['user_id'] = (int)$row['id'];
        return [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'role' => self::normalizeRole($row['role']),
            'profile_pic_url' => $row['profile_pic_url'] ?? '',
        ];
    }
}
