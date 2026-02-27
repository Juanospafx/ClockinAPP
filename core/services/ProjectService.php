<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ProjectService {
    public static function listProjects(): array {
        $pdo = get_pdo();
        $stmt = $pdo->query('SELECT id, name, latitude, longitude, geofence_radius, created_at FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getProject(int $id): ?array {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, name, latitude, longitude, geofence_radius, created_at FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get the project associated with a project_qr_id, including geofence info.
     */
    public static function getProjectByQrId(int $projectQrId): ?array {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.latitude, p.longitude, p.geofence_radius
             FROM projects p
             JOIN project_qrs pq ON pq.project_id = p.id
             WHERE pq.id = ?'
        );
        $stmt->execute([$projectQrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function createProject(array $data): array {
        $name = $data['name'] ?? null;
        if (!$name) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Project name is required.'], 'status' => 400];
        }

        $latitude  = isset($data['latitude'])  ? (float)$data['latitude']  : null;
        $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $radius    = isset($data['geofence_radius']) ? max(10, (int)$data['geofence_radius']) : 100;

        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO projects (name, latitude, longitude, geofence_radius) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $latitude, $longitude, $radius]);
        return ['data' => ['message' => 'Project created successfully.', 'id' => (int)$pdo->lastInsertId()]];
    }

    public static function updateProject(int $id, array $data): array {
        if (!$id) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Project ID is required.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // Check project exists
        $check = $pdo->prepare('SELECT id FROM projects WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            return ['error' => ['code' => 'not_found', 'message' => 'Project not found.'], 'status' => 404];
        }

        $fields = [];
        $values = [];

        if (isset($data['name']) && trim($data['name']) !== '') {
            $fields[] = 'name = ?';
            $values[] = trim($data['name']);
        }
        if (array_key_exists('latitude', $data)) {
            $fields[] = 'latitude = ?';
            $values[] = $data['latitude'] !== null && $data['latitude'] !== '' ? (float)$data['latitude'] : null;
        }
        if (array_key_exists('longitude', $data)) {
            $fields[] = 'longitude = ?';
            $values[] = $data['longitude'] !== null && $data['longitude'] !== '' ? (float)$data['longitude'] : null;
        }
        if (isset($data['geofence_radius'])) {
            $fields[] = 'geofence_radius = ?';
            $values[] = max(10, (int)$data['geofence_radius']);
        }

        if (empty($fields)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'No fields to update.'], 'status' => 400];
        }

        $values[] = $id;
        $sql = 'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        return ['data' => ['message' => 'Project updated successfully.']];
    }

    public static function deleteProject(int $id): array {
        if (!$id) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Project ID is required.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return ['data' => ['message' => 'Project deleted successfully.']];
    }
}
