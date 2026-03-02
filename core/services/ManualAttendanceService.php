<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ManualAttendanceService
{
    /**
     * Create a manual attendance record (admin only).
     */
    public static function createManual(int $adminId, string $adminRole, array $data): array
    {
        if ($adminRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can create manual records.'], 'status' => 403];
        }

        $userId    = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $type      = trim((string)($data['type'] ?? ''));
        $date      = trim((string)($data['date'] ?? ''));
        $time      = trim((string)($data['time'] ?? ''));
        $reason    = trim((string)($data['reason'] ?? ''));
        $force     = !empty($data['force']); // skip clock-in check warning

        // --- Validations ---
        if (!$userId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Employee is required.'], 'status' => 400];
        }
        if (!in_array($type, ['entry', 'exit'], true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Type must be entry or exit.'], 'status' => 400];
        }
        if (!$date || !$time) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Date and time are required.'], 'status' => 400];
        }
        if ($reason === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'A reason for the manual entry is required.'], 'status' => 400];
        }

        // Build datetime (input comes as local, store as-is like normal entries)
        $datetimeStr = $date . ' ' . $time . ':00';
        try {
            $dt = new DateTime($datetimeStr);
        } catch (\Exception $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid date/time format.'], 'status' => 400];
        }

        // No future dates for Clock In; Clock Out manual entries can be future time if needed.
        $now = new DateTime('now');
        if ($type === 'entry' && $dt > $now) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Future dates are not allowed for Clock In.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // Check for exact duplicate
        $dupStmt = $pdo->prepare(
            "SELECT id FROM attendance_records WHERE user_id = ? AND type = ? AND original_time = ? LIMIT 1"
        );
        $dupStmt->execute([$userId, $type, $datetimeStr]);
        if ($dupStmt->fetch()) {
            return ['error' => ['code' => 'duplicate', 'message' => 'A record already exists for this employee with the same type, date and time.'], 'status' => 409];
        }

        // If exit, warn if no prior entry on same date+project
        if ($type === 'exit' && !$force) {
            $entrySql = "SELECT id FROM attendance_records WHERE user_id = ? AND type = 'entry' AND DATE(original_time) = ?";
            $params = [$userId, $date];
            if ($projectId) {
                $entrySql .= " AND project_qr_id IN (SELECT id FROM project_qrs WHERE project_id = ?)";
                $params[] = $projectId;
            }
            $entrySql .= " LIMIT 1";
            $entryStmt = $pdo->prepare($entrySql);
            $entryStmt->execute($params);
            if (!$entryStmt->fetch()) {
                return [
                    'warning' => true,
                    'error' => [
                        'code' => 'no_entry_found',
                        'message' => 'No Clock In found for this employee on this date/project. Do you want to continue anyway?'
                    ],
                    'status' => 200
                ];
            }
        }

        // Resolve project_qr_id from project_id
        $projectQrId = null;
        if ($projectId) {
            $qrStmt = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $qrStmt->execute([$projectId]);
            $projectQrId = $qrStmt->fetchColumn() ?: null;
        }

        // Insert
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_records
                (user_id, location, type, original_time, rounded_time, project_qr_id, entry_source, manual_reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'manual', ?, ?)"
        );
        $stmt->execute([
            $userId,
            'Manual Entry',
            $type,
            $datetimeStr,
            $datetimeStr, // no rounding for manual
            $projectQrId,
            $reason,
            $adminId,
        ]);

        $newId = (int)$pdo->lastInsertId();

        return ['data' => ['message' => 'Manual record created successfully.', 'id' => $newId]];
    }
}
