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
        $entryTimeRequired = isset($data['entry_time_required']) ? trim((string)$data['entry_time_required']) : '';
        $exitTimeOptional = isset($data['exit_time_optional']) ? trim((string)$data['exit_time_optional']) : '';

        if ($entryTimeRequired === '' || !preg_match('/^\d{2}:\d{2}$/', $entryTimeRequired)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Entry time is required (HH:MM).'], 'status' => 400];
        }

        if ($exitTimeOptional !== '' && !preg_match('/^\d{2}:\d{2}$/', $exitTimeOptional)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Exit time must be HH:MM.'], 'status' => 400];
        }

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

            if (self::columnExists($pdo, 'project_qrs', 'entry_time_required')) {
                $columns[] = 'entry_time_required';
                $values[] = $entryTimeRequired;
            }
            if (self::columnExists($pdo, 'project_qrs', 'exit_time_optional')) {
                $columns[] = 'exit_time_optional';
                $values[] = ($exitTimeOptional !== '' ? $exitTimeOptional : null);
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

    private static function columnExists(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$key];
    }

    public static function deleteQr(int $qrId): array {
        if (!$qrId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'QR ID is required.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // Check for dependent attendance records
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE project_qr_id = ?');
        $countStmt->execute([$qrId]);
        $dependentCount = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM project_qrs WHERE id = :id');
        $stmt->bindParam(':id', $qrId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $msg = 'QR deleted successfully.';
            if ($dependentCount > 0) {
                $msg .= " Note: {$dependentCount} attendance record(s) lost their project association.";
            }
            return ['data' => ['message' => $msg, 'affected_records' => $dependentCount]];
        }

        return ['error' => ['code' => 'not_found', 'message' => 'QR not found or already deleted.'], 'status' => 404];
    }
}
