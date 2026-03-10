<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class NotificationService
{
    public static function create(int $userId, string $type, string $message, ?int $relatedId = null): void
    {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $type, $message, $relatedId]);
        } catch (\Throwable $e) {
            error_log('NotificationService error: ' . $e->getMessage());
        }
    }

    public static function notifyAdmins(string $type, string $message, ?int $relatedId = null): void
    {
        try {
            $pdo = get_pdo();
            $admins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
            if (!$admins) return;

            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, ?, ?, ?)');
            foreach ($admins as $adminId) {
                $stmt->execute([(int)$adminId, $type, $message, $relatedId]);
            }
        } catch (\Throwable $e) {
            error_log('NotificationService error: ' . $e->getMessage());
        }
    }

    public static function listByUser(int $userId, int $limit = 20): array
    {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT id, type, message, related_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function markRead(int $notificationId, int $userId): bool
    {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
