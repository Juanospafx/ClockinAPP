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
        return self::createManualInternal($adminId, $adminRole, $data, false);
    }

    /**
     * Create manual attendance records for multiple users in a single request.
     */
    public static function createManualBulk(int $adminId, string $adminRole, array $data): array
    {
        if ($adminRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can create manual records.'], 'status' => 403];
        }

        $userIds = array_values(array_unique(array_map('intval', (array)($data['user_ids'] ?? []))));
        $userIds = array_values(array_filter($userIds, static fn(int $id): bool => $id > 0));

        if (empty($userIds)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'At least one employee is required.'], 'status' => 400];
        }

        $force = !empty($data['force']);

        // Pre-check warnings for bulk Clock Out without force (to avoid partial inserts)
        if (!$force && (($data['type'] ?? '') === 'exit')) {
            $warningUsers = [];
            foreach ($userIds as $uid) {
                $checkData = $data;
                $checkData['user_id'] = $uid;
                $preview = self::createManualInternal($adminId, $adminRole, $checkData, true);
                if (isset($preview['warning']) && $preview['warning']) {
                    $warningUsers[] = $uid;
                }
            }

            if (!empty($warningUsers)) {
                return [
                    'warning' => true,
                    'error' => [
                        'code' => 'no_entry_found',
                        'message' => 'Some selected employees do not have a Clock In for that date/project. Continue anyway?',
                        'details' => ['user_ids' => $warningUsers],
                    ],
                    'status' => 200,
                ];
            }
        }

        $pdo = get_pdo();
        $created = [];

        try {
            $pdo->beginTransaction();
            foreach ($userIds as $uid) {
                $itemData = $data;
                $itemData['user_id'] = $uid;
                $result = self::createManualInternal($adminId, $adminRole, $itemData, false);

                if (isset($result['error'])) {
                    throw new RuntimeException($result['error']['message']);
                }

                $created[] = [
                    'user_id' => $uid,
                    'id' => (int)($result['data']['id'] ?? 0),
                ];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'error' => [
                    'code' => 'bulk_insert_failed',
                    'message' => 'Could not create manual entries for all selected users. Nothing was saved. ' . $e->getMessage(),
                ],
                'status' => 400,
            ];
        }

        return [
            'data' => [
                'message' => 'Manual records created successfully.',
                'count' => count($created),
                'records' => $created,
            ]
        ];
    }

    private static function createManualInternal(int $adminId, string $adminRole, array $data, bool $dryRun): array
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
        $force     = !empty($data['force']);

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

        $datetimeStr = $date . ' ' . $time . ':00';
        try {
            new DateTime($datetimeStr);
        } catch (\Exception $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid date/time format.'], 'status' => 400];
        }

        $pdo = get_pdo();

        $dupStmt = $pdo->prepare(
            "SELECT id FROM attendance_records WHERE user_id = ? AND type = ? AND original_time = ? LIMIT 1"
        );
        $dupStmt->execute([$userId, $type, $datetimeStr]);
        if ($dupStmt->fetch()) {
            return ['error' => ['code' => 'duplicate', 'message' => 'A record already exists for this employee with the same type, date and time.'], 'status' => 409];
        }

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

        if ($dryRun) {
            return ['data' => ['message' => 'Validation OK (dry run).']];
        }

        $projectQrId = null;
        if ($projectId) {
            // Reuse latest QR for the project; if none exists, create a minimal manual linkage QR row.
            $qrStmt = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $qrStmt->execute([$projectId]);
            $projectQrId = $qrStmt->fetchColumn() ?: null;

            if (!$projectQrId) {
                $projectExistsStmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
                $projectExistsStmt->execute([$projectId]);
                if (!$projectExistsStmt->fetchColumn()) {
                    return ['error' => ['code' => 'validation_error', 'message' => 'Selected project does not exist.'], 'status' => 400];
                }

                $manualQrPayload = json_encode(['project_id' => (int)$projectId, 'source' => 'manual_attendance']);
                $createQrStmt = $pdo->prepare(
                    "INSERT INTO project_qrs (project_id, action_type, location, qr_content) VALUES (?, ?, ?, ?)"
                );
                $createQrStmt->execute([$projectId, 'manual', 'Manual Entry', $manualQrPayload]);
                $projectQrId = (int)$pdo->lastInsertId();
            }
        }

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
            $datetimeStr,
            $projectQrId,
            $reason,
            $adminId,
        ]);

        $newId = (int)$pdo->lastInsertId();

        return ['data' => ['message' => 'Manual record created successfully.', 'id' => $newId]];
    }
}
