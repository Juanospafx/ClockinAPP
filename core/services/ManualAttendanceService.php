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
        // Skip warning when full manual range mode is used (entry_time + exit_time).
        $isRangeMode = !empty($data['entry_time']) || !empty($data['exit_time']);
        if (!$force && !$isRangeMode && (($data['type'] ?? '') === 'exit')) {
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
                        'message' => 'Some selected employees do not have a Clock In for that date/project and no project schedule was found. Continue anyway?',
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

        $userId      = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $projectId   = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $type        = trim((string)($data['type'] ?? ''));
        $date        = trim((string)($data['date'] ?? ''));
        $time        = trim((string)($data['time'] ?? ''));
        $entryTime   = trim((string)($data['entry_time'] ?? ''));
        $exitTime    = trim((string)($data['exit_time'] ?? ''));
        $reason      = trim((string)($data['reason'] ?? ''));
        $lunchStart  = trim((string)($data['lunch_start'] ?? ''));
        $lunchEnd    = trim((string)($data['lunch_end'] ?? ''));
        $force       = !empty($data['force']);

        $isRangeMode = ($entryTime !== '' || $exitTime !== '');

        if (!$userId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Employee is required.'], 'status' => 400];
        }
        if ($isRangeMode) {
            $type = 'exit';
            if (!$date || !$entryTime || !$exitTime) {
                return ['error' => ['code' => 'validation_error', 'message' => 'Date, entry time and exit time are required.'], 'status' => 400];
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $entryTime) || !preg_match('/^\d{2}:\d{2}$/', $exitTime)) {
                return ['error' => ['code' => 'validation_error', 'message' => 'Entry/exit time must be HH:MM format.'], 'status' => 400];
            }
            $time = $exitTime;
        } else {
            if (!in_array($type, ['entry', 'exit'], true)) {
                return ['error' => ['code' => 'validation_error', 'message' => 'Type must be entry or exit.'], 'status' => 400];
            }
            if (!$date || !$time) {
                return ['error' => ['code' => 'validation_error', 'message' => 'Date and time are required.'], 'status' => 400];
            }
        }
        if ($reason === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'A reason for the manual entry is required.'], 'status' => 400];
        }

        if (($lunchStart !== '' && !preg_match('/^\d{2}:\d{2}$/', $lunchStart)) || ($lunchEnd !== '' && !preg_match('/^\d{2}:\d{2}$/', $lunchEnd))) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Lunch start/end must be HH:MM format.'], 'status' => 400];
        }

        $datetimeStr = $date . ' ' . $time . ':00';
        $entryDateTimeStr = $isRangeMode ? ($date . ' ' . $entryTime . ':00') : null;

        // No permitir fechas futuras
        $today = date('Y-m-d');
        if ($date > $today) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Cannot create records for future dates.'], 'status' => 400];
        }

        try {
            new DateTime($datetimeStr);
            if ($entryDateTimeStr !== null) {
                new DateTime($entryDateTimeStr);
                if (strtotime($datetimeStr) <= strtotime($entryDateTimeStr)) {
                    return ['error' => ['code' => 'validation_error', 'message' => 'Exit time must be greater than entry time.'], 'status' => 400];
                }
            }
        } catch (\Exception $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid date/time format.'], 'status' => 400];
        }

        $pdo = get_pdo();

        if ($isRangeMode) {
            $dupStmt = $pdo->prepare(
                "SELECT id FROM attendance_records WHERE user_id = ? AND type = 'exit' AND rounded_time = ? LIMIT 1"
            );
            $dupStmt->execute([$userId, $datetimeStr]);
        } else {
            $dupStmt = $pdo->prepare(
                "SELECT id FROM attendance_records WHERE user_id = ? AND type = ? AND original_time = ? LIMIT 1"
            );
            $dupStmt->execute([$userId, $type, $datetimeStr]);
        }

        if ($dupStmt->fetch()) {
            return ['error' => ['code' => 'duplicate', 'message' => 'A record already exists for this employee with the same type, date and time.'], 'status' => 409];
        }

        $projectQrId = self::resolveProjectQrId($pdo, $projectId);
        if ($projectId && !$projectQrId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Selected project does not exist.'], 'status' => 400];
        }

        $schedule = self::getProjectSchedule($pdo, $projectQrId);

        if ($type === 'exit' && !$force && !$isRangeMode) {
            $entry = self::findEntryForExit($pdo, $userId, $date, $datetimeStr, $projectId);
            $hasScheduleStart = !empty($schedule['entry_time_required']);
            if (!$entry && !$hasScheduleStart) {
                return [
                    'warning' => true,
                    'error' => [
                        'code' => 'no_entry_found',
                        'message' => 'No Clock In found for this employee on this date/project and no project start schedule exists. Do you want to continue anyway?'
                    ],
                    'status' => 200
                ];
            }
        }

        if ($dryRun) {
            return ['data' => ['message' => 'Validation OK (dry run).']];
        }

        $totalDuration = null;
        $lunchDuration = null;

        if ($type === 'exit') {
            $metrics = self::calculateManualExitDurations(
                $pdo,
                $userId,
                $date,
                $datetimeStr,
                $projectId,
                $schedule,
                $lunchStart,
                $lunchEnd,
                $entryDateTimeStr
            );
            $totalDuration = $metrics['total_duration_minutes'];
            $lunchDuration = $metrics['lunch_duration_minutes'];
        }

        $storeOriginal = $isRangeMode && $entryDateTimeStr ? $entryDateTimeStr : $datetimeStr;
        $storeRounded = $datetimeStr;

        $stmt = $pdo->prepare(
            "INSERT INTO attendance_records
                (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration, lunch_duration, entry_source, manual_reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?)"
        );
        $stmt->execute([
            $userId,
            'Manual Entry',
            $type,
            $storeOriginal,
            $storeRounded,
            $projectQrId,
            $totalDuration,
            $lunchDuration,
            $reason,
            $adminId,
        ]);

        $newId = (int)$pdo->lastInsertId();

        return ['data' => ['message' => 'Manual record created successfully.', 'id' => $newId]];
    }

    private static function resolveProjectQrId(PDO $pdo, ?int $projectId): ?int
    {
        if (!$projectId) {
            return null;
        }

        $qrStmt = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
        $qrStmt->execute([$projectId]);
        $projectQrId = $qrStmt->fetchColumn() ?: null;

        if ($projectQrId) {
            return (int)$projectQrId;
        }

        $projectExistsStmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
        $projectExistsStmt->execute([$projectId]);
        if (!$projectExistsStmt->fetchColumn()) {
            return null;
        }

        $manualQrPayload = json_encode(['project_id' => (int)$projectId, 'source' => 'manual_attendance']);
        $createQrStmt = $pdo->prepare(
            "INSERT INTO project_qrs (project_id, action_type, location, qr_content) VALUES (?, ?, ?, ?)"
        );
        $createQrStmt->execute([$projectId, 'manual', 'Manual Entry', $manualQrPayload]);
        return (int)$pdo->lastInsertId();
    }

    private static function getProjectSchedule(PDO $pdo, ?int $projectQrId): array
    {
        if (!$projectQrId) {
            return ['entry_time_required' => null, 'exit_time_optional' => null];
        }

        $hasEntry = self::columnExists($pdo, 'project_qrs', 'entry_time_required');
        $hasExit = self::columnExists($pdo, 'project_qrs', 'exit_time_optional');
        if (!$hasEntry && !$hasExit) {
            return ['entry_time_required' => null, 'exit_time_optional' => null];
        }

        $cols = [];
        if ($hasEntry) $cols[] = 'entry_time_required';
        if ($hasExit) $cols[] = 'exit_time_optional';

        $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM project_qrs WHERE id = ? LIMIT 1');
        $stmt->execute([$projectQrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'entry_time_required' => isset($row['entry_time_required']) ? (string)$row['entry_time_required'] : null,
            'exit_time_optional' => isset($row['exit_time_optional']) ? (string)$row['exit_time_optional'] : null,
        ];
    }

    private static function findEntryForExit(PDO $pdo, int $userId, string $date, string $exitDateTime, ?int $projectId): ?array
    {
        $sql = "SELECT id, original_time
                FROM attendance_records
                WHERE user_id = ? AND type = 'entry' AND DATE(original_time) = ? AND original_time <= ?";
        $params = [$userId, $date, $exitDateTime];

        if ($projectId) {
            $sql .= " AND project_qr_id IN (SELECT id FROM project_qrs WHERE project_id = ?)";
            $params[] = $projectId;
        }

        $sql .= " ORDER BY original_time DESC, id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function calculateManualExitDurations(PDO $pdo, int $userId, string $date, string $exitDateTime, ?int $projectId, array $schedule, string $lunchStart, string $lunchEnd, ?string $forcedStartDateTime = null): array
    {
        $entry = self::findEntryForExit($pdo, $userId, $date, $exitDateTime, $projectId);

        $startDateTime = $forcedStartDateTime ?: ($entry['original_time'] ?? null);
        if (!$startDateTime && !empty($schedule['entry_time_required']) && preg_match('/^\d{2}:\d{2}/', (string)$schedule['entry_time_required'])) {
            $startDateTime = $date . ' ' . substr((string)$schedule['entry_time_required'], 0, 5) . ':00';
        }

        if (!$startDateTime) {
            return ['total_duration_minutes' => null, 'lunch_duration_minutes' => null];
        }

        $startTs = strtotime($startDateTime);
        $endTs = strtotime($exitDateTime);
        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            return ['total_duration_minutes' => null, 'lunch_duration_minutes' => null];
        }

        $grossMinutes = (int)round(($endTs - $startTs) / 60);

        // Default rule: if admin does not provide lunch, assume 12:00–13:00.
        // The overlap calculation naturally avoids discounting lunch for shifts that do not cross lunch time.
        $lunchStartTime = $lunchStart !== '' ? $lunchStart : '12:00';
        $lunchEndTime = $lunchEnd !== '' ? $lunchEnd : '13:00';
        $lunchStartDateTime = $date . ' ' . $lunchStartTime . ':00';
        $lunchEndDateTime = $date . ' ' . $lunchEndTime . ':00';
        $lunchMinutes = self::overlapMinutes($startDateTime, $exitDateTime, $lunchStartDateTime, $lunchEndDateTime);

        $workedMinutes = max(0, $grossMinutes - $lunchMinutes);

        return [
            'total_duration_minutes' => $workedMinutes,
            'lunch_duration_minutes' => $lunchMinutes,
        ];
    }

    private static function overlapMinutes(string $aStart, string $aEnd, string $bStart, string $bEnd): int
    {
        $aStartTs = strtotime($aStart);
        $aEndTs = strtotime($aEnd);
        $bStartTs = strtotime($bStart);
        $bEndTs = strtotime($bEnd);

        if ($aStartTs === false || $aEndTs === false || $bStartTs === false || $bEndTs === false) {
            return 0;
        }

        if ($bEndTs <= $bStartTs) {
            return 0;
        }

        $start = max($aStartTs, $bStartTs);
        $end = min($aEndTs, $bEndTs);

        if ($end <= $start) {
            return 0;
        }

        return (int)round(($end - $start) / 60);
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
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
}
