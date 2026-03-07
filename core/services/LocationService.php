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

    public static function getHistory(?int $userId = null, ?string $startDate = null, ?string $endDate = null): array {
        $pdo = get_pdo();

        $sql = 'SELECT lh.latitude, lh.longitude, lh.timestamp, lh.user_id, u.username
                FROM location_history lh
                JOIN users u ON u.id = lh.user_id';
        $conditions = [];
        $params = [];

        if ($userId !== null && $userId > 0) {
            $conditions[] = 'lh.user_id = ?';
            $params[] = $userId;
        }

        if (!empty($startDate)) {
            $conditions[] = 'lh.timestamp >= ?';
            $params[] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'lh.timestamp <= ?';
            $params[] = $endDate . ' 23:59:59';
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY lh.timestamp ASC LIMIT 5000';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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
