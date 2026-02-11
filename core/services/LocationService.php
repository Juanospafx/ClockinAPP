<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class LocationService {
    public static function logLocation(int $userId, $latitude, $longitude): array {
        if (!$userId || $latitude === null || $longitude === null) {
            return ['error' => ['code' => 'validation_error', 'message' => 'User ID, latitude, and longitude are required.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO location_history (user_id, latitude, longitude, timestamp) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $latitude, $longitude]);
        return ['data' => ['message' => 'Location logged successfully.']];
    }

    public static function getHistory(int $userId, string $startDate, string $endDate): array {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT latitude, longitude, timestamp FROM location_history WHERE user_id = ? AND timestamp BETWEEN ? AND ? ORDER BY timestamp ASC'
        );
        $stmt->execute([$userId, $startDate, $endDate]);
        return ['data' => ['history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]];
    }

    public static function getLatestForUsers(): array {
        $pdo = get_pdo();
        $subQuery = 'SELECT MAX(id) as max_id FROM location_history GROUP BY user_id';
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, lh.latitude, lh.longitude, lh.timestamp
             FROM location_history lh
             JOIN users u ON lh.user_id = u.id
             WHERE lh.id IN (' . $subQuery . ')'
        );
        $stmt->execute();
        return ['data' => ['locations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]];
    }
}
