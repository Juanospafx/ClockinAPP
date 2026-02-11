<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ProjectService {
    public static function listProjects(): array {
        $pdo = get_pdo();
        $stmt = $pdo->query('SELECT id, name, created_at FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createProject(array $data): array {
        $name = $data['name'] ?? null;
        if (!$name) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Project name is required.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO projects (name) VALUES (?)');
        $stmt->execute([$name]);
        return ['data' => ['message' => 'Project created successfully.', 'id' => (int)$pdo->lastInsertId()]];
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
