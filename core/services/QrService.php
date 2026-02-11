<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class QrService {
    public static function listQrs(): array {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT pq.id, pq.action_type, pq.location, pq.qr_content, pq.created_at, p.name as project_name 
             FROM project_qrs pq 
             JOIN projects p ON pq.project_id = p.id 
             ORDER BY pq.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createQr(array $data): array {
        $projectId = $data['project_id'] ?? null;
        if (!$projectId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Project ID is required.'], 'status' => 400];
        }

        $actionType = $data['action_type'] ?? null;
        $location = $data['location'] ?? null;

        $pdo = get_pdo();
        $initialPayload = json_encode(['project_id' => (int)$projectId]);

        $pdo->beginTransaction();
        try {
            $columns = ['project_id', 'qr_content'];
            $values = [$projectId, $initialPayload];
            if ($actionType !== null) {
                $columns[] = 'action_type';
                $values[] = $actionType;
            }
            if ($location !== null) {
                $columns[] = 'location';
                $values[] = $location;
            }

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare('INSERT INTO project_qrs (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
            $stmt->execute($values);
            $projectQrId = (int)$pdo->lastInsertId();

            $qrPayload = [
                'project_id' => (int)$projectId,
                'project_qr_id' => $projectQrId
            ];
            $qrContent = json_encode($qrPayload);

            $update = $pdo->prepare('UPDATE project_qrs SET qr_content = ? WHERE id = ?');
            $update->execute([$qrContent, $projectQrId]);

            $pdo->commit();

            return ['data' => ['qr_content' => $qrContent, 'project_qr_id' => $projectQrId]];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => ['code' => 'server_error', 'message' => 'Database error: ' . $e->getMessage()], 'status' => 500];
        }
    }

    public static function deleteQr(int $qrId): array {
        if (!$qrId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'QR ID is required.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare('DELETE FROM project_qrs WHERE id = :id');
        $stmt->bindParam(':id', $qrId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return ['data' => ['message' => 'QR deleted successfully.']];
        }
        return ['error' => ['code' => 'not_found', 'message' => 'QR not found or already deleted.'], 'status' => 404];
    }
}
