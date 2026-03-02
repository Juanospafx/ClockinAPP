<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../funciones/time.php';
require_once __DIR__ . '/GeofenceService.php';
require_once __DIR__ . '/ProjectService.php';

class AttendanceService {
    public static function fetchRecords(?int $userId, ?int $limit): array {
        $pdo = get_pdo();

        $hasEntrySource = self::columnExists($pdo, 'attendance_records', 'entry_source');
        $hasManualReason = self::columnExists($pdo, 'attendance_records', 'manual_reason');
        $hasCreatedBy = self::columnExists($pdo, 'attendance_records', 'created_by');

        $hasLateReason = self::columnExists($pdo, 'attendance_records', 'late_reason');

        $selectEntrySource = $hasEntrySource ? 'ar.entry_source' : "NULL AS entry_source";
        $selectManualReason = $hasManualReason ? 'ar.manual_reason' : "NULL AS manual_reason";
        $selectCreatedBy = $hasCreatedBy ? 'ar.created_by' : "NULL AS created_by";
        $selectCreatedByUsername = $hasCreatedBy ? 'admin_u.username AS created_by_username' : "NULL AS created_by_username";
        $selectLateReason = $hasLateReason ? 'ar.late_reason AS late_reason' : "NULL AS late_reason";

        $sql = "SELECT ar.id, ar.user_id, u.username, ar.location, ar.type, ar.original_time, ar.rounded_time,
                       ar.total_duration, ar.lunch_duration, ar.created_at, ar.project_qr_id, pq.project_id, p.name AS project_name,
                       {$selectEntrySource}, {$selectManualReason}, {$selectCreatedBy},
                       {$selectCreatedByUsername}, {$selectLateReason}
                FROM attendance_records ar
                JOIN users u ON ar.user_id = u.id
                LEFT JOIN project_qrs pq ON ar.project_qr_id = pq.id
                LEFT JOIN projects p ON pq.project_id = p.id";

        if ($hasCreatedBy) {
            $sql .= ' LEFT JOIN users admin_u ON ar.created_by = admin_u.id';
        }

        $params = [];

        if ($userId !== null) {
            $sql .= ' WHERE ar.user_id = ?';
            $params[] = $userId;
        }

        $sql .= ' ORDER BY ar.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSummary(?int $userId): array {
        $pdo = get_pdo();
        $subQuery = "SELECT user_id, DATE(original_time) as work_date, MAX(total_duration) as max_duration 
                     FROM attendance_records 
                     WHERE total_duration IS NOT NULL 
                     GROUP BY user_id, DATE(original_time)";

        $sql = "SELECT DATE_FORMAT(work_date, '%Y-%m') as work_month, SUM(max_duration) as total_minutes 
                FROM ({$subQuery}) as daily_maxes";
        $params = [];
        if ($userId) {
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
        }
        $sql .= " GROUP BY work_month ORDER BY work_month ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = array_column($results, 'work_month');
        $data = array_map(function ($minutes) {
            return round(((int)$minutes) / 60, 2);
        }, array_column($results, 'total_minutes'));

        return ['labels' => $labels, 'data' => $data];
    }

    public static function getDashboardMetrics(): array {
        $pdo = get_pdo();

        $activeEmployeesToday = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance_records WHERE type = 'entry' AND DATE(original_time) = CURRENT_DATE()")->fetchColumn();
        $clockInsToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance_records WHERE type = 'entry' AND DATE(original_time) = CURRENT_DATE()")->fetchColumn();
        $absencesToday = (int)$pdo->query("SELECT COUNT(*) FROM absences WHERE CURRENT_DATE() BETWEEN date_start AND date_end")->fetchColumn();

        $weeklyAvgStmt = $pdo->query("SELECT AVG(daily_hours) FROM (
            SELECT user_id, DATE(original_time) AS d, MAX(COALESCE(total_duration, 0))/60.0 AS daily_hours
            FROM attendance_records
            WHERE type = 'exit' AND YEARWEEK(original_time, 1) = YEARWEEK(CURRENT_DATE(), 1)
            GROUP BY user_id, DATE(original_time)
        ) t");
        $avgHoursWeek = round((float)($weeklyAvgStmt->fetchColumn() ?: 0), 2);

        return [
            'active_employees_today' => $activeEmployeesToday,
            'clockins_today' => $clockInsToday,
            'absences_today' => $absencesToday,
            'avg_hours_week' => $avgHoursWeek,
        ];
    }

    public static function createRecord(int $currentUserId, string $currentUserRole, array $data): array {
        $pdo = get_pdo();
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : $currentUserId;
        $location = trim((string)($data['location'] ?? ''));
        $type = (string)($data['type'] ?? '');
        $projectQrId = isset($data['project_qr_id']) ? (int)$data['project_qr_id'] : null;
        $projectIdFromClient = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $clientTimeIso = $data['client_time_iso'] ?? null;
        $clientTimeLocal = isset($data['client_time_local']) ? trim((string)$data['client_time_local']) : '';
        $lateReason = isset($data['late_reason']) ? trim((string)$data['late_reason']) : '';

        if (!$userId || !$location || !$type) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing required fields.'], 'status' => 400];
        }
        if (!$clientTimeIso) {
            return ['error' => ['code' => 'validation_error', 'message' => 'client_time_iso is required.'], 'status' => 400];
        }
        if ($currentUserRole !== 'admin' && $userId !== $currentUserId) {
            return ['error' => ['code' => 'forbidden', 'message' => 'You cannot create records for another user.'], 'status' => 403];
        }

        try {
            $originalTime = new DateTime($clientTimeIso);
            $originalTime->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid client_time_iso format.'], 'status' => 400];
        }
        $roundedTime = round_time_to_next_quarter(clone $originalTime);

        if ($projectQrId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM project_qrs WHERE id = ?');
            $stmt->execute([$projectQrId]);
            if (!$stmt->fetch()) {
                $projectQrId = null;
            }
        }
        if ($projectQrId === null && $projectIdFromClient) {
            $stmt = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$projectIdFromClient]);
            $foundQrId = $stmt->fetchColumn();
            if ($foundQrId) {
                $projectQrId = (int)$foundQrId;
            }
        }

        // --- Late check based on QR schedule (entry + 10 min tolerance) ---
        if ($type === 'entry' && $projectQrId !== null && self::columnExists($pdo, 'project_qrs', 'entry_time_required')) {
            $qrStmt = $pdo->prepare('SELECT entry_time_required FROM project_qrs WHERE id = ? LIMIT 1');
            $qrStmt->execute([$projectQrId]);
            $entryTimeRequired = (string)($qrStmt->fetchColumn() ?: '');

            if ($entryTimeRequired !== '' && preg_match('/^\d{2}:\d{2}/', $entryTimeRequired)) {
                $referenceLocal = null;
                if ($clientTimeLocal !== '') {
                    $referenceLocal = new DateTime($clientTimeLocal);
                } else {
                    $referenceLocal = clone $originalTime;
                }

                $day = $referenceLocal->format('Y-m-d');
                $deadline = new DateTime($day . ' ' . substr($entryTimeRequired, 0, 5) . ':00');
                $deadline->modify('+10 minutes');

                if ($referenceLocal > $deadline && $lateReason === '') {
                    $lateMinutes = (int)floor(($referenceLocal->getTimestamp() - $deadline->getTimestamp()) / 60);
                    return [
                        'error' => [
                            'code' => 'late_reason_required',
                            'message' => 'Llegaste tarde. Debes escribir una justificación.',
                            'details' => ['late_minutes' => max(1, $lateMinutes)]
                        ],
                        'status' => 422
                    ];
                }
            }
        }

        // --- Geofence validation ---
        $resolvedProjectId = $projectIdFromClient;
        if (!$resolvedProjectId && $projectQrId !== null) {
            $pqStmt = $pdo->prepare('SELECT project_id FROM project_qrs WHERE id = ?');
            $pqStmt->execute([$projectQrId]);
            $resolvedProjectId = (int)($pqStmt->fetchColumn() ?: 0);
        }

        if ($resolvedProjectId) {
            $project = ProjectService::getProject($resolvedProjectId);
            if ($project && $project['latitude'] !== null && $project['longitude'] !== null) {
                $userCoords = GeofenceService::parseLocation($location);
                if ($userCoords === null) {
                    return [
                        'error' => [
                            'code' => 'geofence_error',
                            'message' => 'No se pudo determinar tu ubicación. La geolocalización es obligatoria para registrar asistencia en este proyecto.'
                        ],
                        'status' => 400
                    ];
                }

                $geoCheck = GeofenceService::checkPosition(
                    $userCoords[0],
                    $userCoords[1],
                    (float)$project['latitude'],
                    (float)$project['longitude'],
                    (int)$project['geofence_radius']
                );

                if (!$geoCheck['inside']) {
                    return [
                        'error' => [
                            'code' => 'outside_geofence',
                            'message' => 'No estás dentro del área permitida para este proyecto. Acércate a la ubicación asignada para poder registrar tu entrada/salida.',
                            'details' => [
                                'distance_meters' => $geoCheck['distance'],
                                'allowed_radius'  => $geoCheck['radius'],
                            ]
                        ],
                        'status' => 403
                    ];
                }
            }
        }
        // --- End geofence validation ---

        $pdo->beginTransaction();
        try {
            if ($type === 'entry') {
                $openTimer = self::findOpenTimerEntry($pdo, $userId, false);
                if ($openTimer) {
                    $pdo->rollBack();
                    return ['error' => ['code' => 'conflict', 'message' => 'Ya existe un temporizador activo.'], 'status' => 409];
                }
                if (self::columnExists($pdo, 'attendance_records', 'late_reason')) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, status, late_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, 'entry', $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $projectQrId, 1, ($lateReason !== '' ? $lateReason : null)]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, 'entry', $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $projectQrId, 1]);
                }
            } else {
                $openTimer = self::findOpenTimerEntry($pdo, $userId, true);
                if (!$openTimer) {
                    $pdo->rollBack();
                    return ['error' => ['code' => 'conflict', 'message' => 'No se encontró un temporizador activo para actualizar.'], 'status' => 409];
                }

                $currentProjectQrId = $projectQrId ?? $openTimer['project_qr_id'];
                $newStatus = self::mapTimerStatusFromType($type);

                if ($type === 'exit') {
                    $metrics = self::calculateTimerMetrics($pdo, $openTimer, $originalTime);
                    $sessionDurationMinutes = (int)round($metrics['duration_seconds'] / 60);

                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId, $sessionDurationMinutes]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ?, total_duration = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $sessionDurationMinutes, $openTimer['id']]);
                } elseif ($type === 'end_lunch') {
                    $lunchDurationMinutes = null;
                    $startLunchStmt = $pdo->prepare("SELECT original_time FROM attendance_records WHERE user_id = ? AND type = 'start_lunch' AND original_time >= ? ORDER BY original_time DESC LIMIT 1");
                    $startLunchStmt->execute([$userId, $openTimer['original_time']]);
                    if ($startLunchTimeStr = $startLunchStmt->fetchColumn()) {
                        $startLunchTime = new DateTime($startLunchTimeStr, new DateTimeZone('UTC'));
                        $lunchDurationSeconds = $originalTime->getTimestamp() - $startLunchTime->getTimestamp();
                        $lunchDurationMinutes = (int)round($lunchDurationSeconds / 60);
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, lunch_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId, $lunchDurationMinutes]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $openTimer['id']]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $openTimer['id']]);
                }
            }

            $pdo->commit();
            return ['data' => ['message' => 'Record ' . $type . ' processed successfully.']];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => ['code' => 'server_error', 'message' => 'Server error: ' . $e->getMessage()], 'status' => 500];
        }
    }

    public static function updateRecord(string $currentUserRole, array $data): array {
        if ($currentUserRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can update records.'], 'status' => 403];
        }

        $recordId = isset($data['id']) ? (int)$data['id'] : null;
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $location = trim((string)($data['location'] ?? ''));
        $type = (string)($data['type'] ?? '');
        $originalTimeStr = $data['original_time'] ?? null;
        $roundedTimeStr = $data['rounded_time'] ?? null;
        $totalDuration = isset($data['total_duration']) ? (int)$data['total_duration'] : null;
        $lunchDuration = isset($data['lunch_duration']) ? (int)$data['lunch_duration'] : null;
        $projectQrId = isset($data['project_qr_id']) && $data['project_qr_id'] !== null ? (int)$data['project_qr_id'] : null;
        $projectId = isset($data['project_id']) && $data['project_id'] !== null ? (int)$data['project_id'] : null;

        if (!$recordId || !$userId || !$location || !$type || !$originalTimeStr || !$roundedTimeStr) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing required fields for update.'], 'status' => 400];
        }

        $pdo = get_pdo();

        if ($projectId) {
            $stmtProjectQr = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $stmtProjectQr->execute([$projectId]);
            $resolvedProjectQrId = $stmtProjectQr->fetchColumn();
            if ($resolvedProjectQrId) {
                $projectQrId = (int)$resolvedProjectQrId;
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE attendance_records
             SET user_id = ?, location = ?, type = ?, original_time = ?, rounded_time = ?, total_duration = ?, lunch_duration = ?, project_qr_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $userId,
            $location,
            $type,
            $originalTimeStr,
            $roundedTimeStr,
            $totalDuration,
            $lunchDuration,
            $projectQrId,
            $recordId
        ]);

        return ['data' => ['message' => 'Record updated successfully.']];
    }

    public static function deleteRecord(string $currentUserRole, array $data): array {
        if ($currentUserRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can delete records.'], 'status' => 403];
        }
        $recordId = isset($data['id']) ? (int)$data['id'] : null;
        if (!$recordId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing record ID for deletion.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = ?');
        $stmt->execute([$recordId]);
        return ['data' => ['message' => 'Record deleted successfully.']];
    }

    public static function recalcDailyDuration(int $userId, string $date): int {
        $pdo = get_pdo();
        $totalDuration = self::calculateDailyWorkDuration($pdo, $userId, $date);
        $stmt = $pdo->prepare(
            "UPDATE attendance_records
             SET total_duration = ?
             WHERE user_id = ? AND DATE(original_time) = ? AND type = 'exit'"
        );
        $stmt->execute([$totalDuration, $userId, $date]);
        return $totalDuration;
    }

    public static function calculateDailyWorkDuration(PDO $pdo, int $userId, string $date, ?array $pendingEvent = null): int {
        $utc = new DateTimeZone('UTC');
        $stmt = $pdo->prepare(
            "SELECT type, original_time FROM attendance_records
             WHERE user_id = ? AND DATE(original_time) = ?
             ORDER BY original_time ASC, id ASC"
        );
        $stmt->execute([$userId, $date]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($pendingEvent) {
            $events[] = $pendingEvent;
            usort($events, function ($a, $b) {
                return strtotime($a['original_time']) - strtotime($b['original_time']);
            });
        }

        if (empty($events)) {
            return 0;
        }

        $elapsedSeconds = 0;
        $segmentStart = null;

        foreach ($events as $event) {
            $eventTime = new DateTime($event['original_time'], $utc);

            switch ($event['type']) {
                case 'entry':
                case 'resume':
                case 'end_lunch':
                    if ($segmentStart === null) {
                        $segmentStart = $eventTime;
                    }
                    break;
                case 'exit':
                case 'pause':
                case 'start_lunch':
                    if ($segmentStart !== null) {
                        $elapsedSeconds += $eventTime->getTimestamp() - $segmentStart->getTimestamp();
                        $segmentStart = null;
                    }
                    break;
            }
        }

        return (int)round($elapsedSeconds / 60);
    }

    public static function mapTimerStatusFromType(string $type): ?int {
        switch ($type) {
            case 'entry':
            case 'resume':
            case 'end_lunch':
                return 1;
            case 'pause':
            case 'start_lunch':
                return 2;
            case 'exit':
                return 3;
            default:
                return null;
        }
    }

    public static function findOpenTimerEntry(PDO $pdo, int $userId, bool $forUpdate = false): ?array {
        $sql = "SELECT * FROM attendance_records WHERE user_id = :user_id AND type = 'entry' AND status IN (1, 2) ORDER BY original_time DESC, id DESC LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getTimerEntryById(PDO $pdo, int $timerId, bool $forUpdate = false): ?array {
        $sql = "SELECT * FROM attendance_records WHERE id = :id AND type = 'entry' LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $timerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

    public static function calculateTimerMetrics(PDO $pdo, array $entryRow, ?DateTime $referenceTime = null): array {
        $utc = new DateTimeZone('UTC');
        $reference = $referenceTime ? clone $referenceTime : new DateTime('now', $utc);
        if ($reference->getTimezone()->getName() !== 'UTC') {
            $reference->setTimezone($utc);
        }

        $stmt = $pdo->prepare(
            "SELECT id, type, original_time FROM attendance_records
             WHERE user_id = :user_id AND original_time >= :start_time
             ORDER BY original_time ASC, id ASC"
        );
        $stmt->execute([
            'user_id' => $entryRow['user_id'],
            'start_time' => $entryRow['original_time']
        ]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $elapsedSeconds = 0;
        $segmentStart = null;

        foreach ($events as $event) {
            $eventTime = new DateTime($event['original_time'], $utc);
            if ((int)$event['id'] !== (int)$entryRow['id'] && $event['type'] === 'entry') {
                break;
            }

            switch ($event['type']) {
                case 'entry':
                case 'resume':
                case 'end_lunch':
                    if ($segmentStart === null) {
                        $segmentStart = $eventTime;
                    }
                    break;
                case 'pause':
                case 'start_lunch':
                case 'exit':
                    if ($segmentStart !== null) {
                        $elapsedSeconds += max(0, $eventTime->getTimestamp() - $segmentStart->getTimestamp());
                        $segmentStart = null;
                    }
                    if ($event['type'] === 'exit') {
                        break 2;
                    }
                    break;
            }
        }

        $runningSince = null;
        if ($segmentStart !== null) {
            $elapsedSeconds += max(0, $reference->getTimestamp() - $segmentStart->getTimestamp());
            $runningSince = clone $segmentStart;
        }

        return [
            'duration_seconds' => (int)$elapsedSeconds,
            'running_since' => $runningSince,
        ];
    }
}
