<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class RateLimitService {
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function ensureTable(): void {
        $pdo = get_pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip_address, attempted_at),
            INDEX idx_user_time (username, attempted_at)
        ) ENGINE=InnoDB");
    }

    public static function isBlocked(string $ip, string $username): bool {
        $pdo = get_pdo();
        $cutoff = date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60));
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempted_at > ?'
        );
        $stmt->execute([$ip, $username, $cutoff]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function recordAttempt(string $ip, string $username): void {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)');
        $stmt->execute([$ip, $username]);
    }

    public static function clearOldAttempts(): void {
        $pdo = get_pdo();
        $cutoff = date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60));
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
    }
}
