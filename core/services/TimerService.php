<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/AttendanceService.php';
require_once __DIR__ . '/../../funciones/time.php';

class TimerService {
    public static function getActiveTimers(string $role, int $userId): array {
        $pdo = get_pdo();
        $sql = "SELECT ar.id, ar.user_id, ar.original_time, ar.status, ar.project_qr_id, u.username, r.name AS role_name, p.name AS project_name
                FROM attendance_records ar
                JOIN users u ON u.id = ar.user_id
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN project_qrs pq ON pq.id = ar.project_qr_id
                LEFT JOIN projects p ON p.id = pq.project_id
                WHERE ar.type = 'entry' AND ar.status IN (1, 2)";

        if ($role === 'user') {
            $sql .= " AND ar.user_id = :user_id";
        } else {
            $sql .= " AND r.name = 'user'";
        }
        $sql .= " ORDER BY ar.user_id, ar.original_time DESC";

        $stmt = $pdo->prepare($sql);
        if ($role === 'user') {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $timers = [];
        $processedUsers = [];

        foreach ($entries as $entry) {
            $currentUserId = (int)$entry['user_id'];
            if (in_array($currentUserId, $processedUsers, true)) {
                continue;
            }
            $metrics = AttendanceService::calculateTimerMetrics($pdo, $entry, clone $now);
            $durationSeconds = $metrics['duration_seconds'];
            $runningSince = $metrics['running_since'] instanceof DateTime ? $metrics['running_since']->format(DATE_ATOM) : null;
            $startTime = new DateTime($entry['original_time']);

            $timers[] = [
                'id' => (int)$entry['id'],
                'user_id' => $currentUserId,
                'username' => $entry['username'],
                'status' => isset($entry['status']) ? (int)$entry['status'] : null,
                'duration_seconds' => $durationSeconds,
                'duration_display' => format_timer_duration_display($durationSeconds),
                'start_time_display' => $startTime->format('Y-m-d H:i:s'),
                'entry_time_iso' => $startTime->format(DATE_ATOM),
                'running_since' => $runningSince,
                'project_name' => $entry['project_name'] ?? null,
                'project_qr_id' => $entry['project_qr_id'] ?? null
            ];

            $processedUsers[] = $currentUserId;
        }

        return [
            'server_time' => $now->format(DATE_ATOM),
            'timers' => $timers,
        ];
    }

    public static function getMyTimer(int $userId): array {
        $pdo = get_pdo();
        $entry = AttendanceService::findOpenTimerEntry($pdo, $userId);
        if (!$entry) {
            return ['timer' => null];
        }
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $metrics = AttendanceService::calculateTimerMetrics($pdo, $entry, clone $now);
        $durationSeconds = $metrics['duration_seconds'];
        $runningSince = $metrics['running_since'] instanceof DateTime ? $metrics['running_since']->format(DATE_ATOM) : null;
        $startTime = new DateTime($entry['original_time']);

        $username = $entry['username'] ?? null;
        if ($username === null) {
            $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $userStmt->execute([$entry['user_id']]);
            $username = $userStmt->fetchColumn() ?: null;
        }

        $projectName = $entry['project_name'] ?? null;
        if ($projectName === null && !empty($entry['project_qr_id'])) {
            $projectStmt = $pdo->prepare('SELECT p.name FROM project_qrs pq LEFT JOIN projects p ON p.id = pq.project_id WHERE pq.id = ? LIMIT 1');
            $projectStmt->execute([$entry['project_qr_id']]);
            $projectName = $projectStmt->fetchColumn() ?: null;
        }

        $timer = [
            'id' => (int)$entry['id'],
            'user_id' => (int)$entry['user_id'],
            'username' => $username,
            'status' => isset($entry['status']) ? (int)$entry['status'] : null,
            'duration_seconds' => $durationSeconds,
            'duration_display' => format_timer_duration_display($durationSeconds),
            'start_time_display' => $startTime->format('Y-m-d H:i:s'),
            'entry_time_iso' => $startTime->format(DATE_ATOM),
            'running_since' => $runningSince,
            'project_qr_id' => $entry['project_qr_id'] ?? null,
            'project_name' => $projectName,
        ];

        return ['timer' => $timer];
    }

    public static function updateTimerStatus(int $timerId, string $requestedStatus, string $location, string $clientTimeIso): array {
        $statusMap = [
            'paused' => ['status' => 2, 'type' => 'pause'],
            'resumed' => ['status' => 1, 'type' => 'resume'],
            'stopped' => ['status' => 3, 'type' => 'exit'],
        ];

        if (!isset($statusMap[$requestedStatus])) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid status value.'], 'status' => 400];
        }

        $pdo = get_pdo();
        $pdo->beginTransaction();
        try {
            $timerEntry = AttendanceService::getTimerEntryById($pdo, $timerId, false);
            if (!$timerEntry) {
                $pdo->rollBack();
                return ['error' => ['code' => 'not_found', 'message' => 'Timer not found.'], 'status' => 404];
            }
            if ((int)$timerEntry['status'] === 3) {
                $pdo->rollBack();
                return ['error' => ['code' => 'conflict', 'message' => 'Timer is already stopped.'], 'status' => 409];
            }

            try {
                $eventTime = new DateTime($clientTimeIso);
                $eventTime->setTimezone(new DateTimeZone('UTC'));
            } catch (Exception $e) {
                $pdo->rollBack();
                return ['error' => ['code' => 'validation_error', 'message' => 'Invalid client_time_iso format.'], 'status' => 400];
            }

            $roundedTime = round_time_to_next_quarter(clone $eventTime);
            $userId = (int)$timerEntry['user_id'];
            $targetStatus = $statusMap[$requestedStatus]['status'];
            $eventType = $statusMap[$requestedStatus]['type'];

            if ($eventType === 'exit') {
                $metrics = AttendanceService::calculateTimerMetrics($pdo, $timerEntry, $eventTime);
                $totalDurationMinutes = (int)round($metrics['duration_seconds'] / 60);

                $stmt = $pdo->prepare(
                    'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $location, $eventType, $eventTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $timerEntry['project_qr_id'], $totalDurationMinutes]);

                $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ?, total_duration = ? WHERE id = ?');
                $updateStmt->execute([$targetStatus, $totalDurationMinutes, $timerId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $location, $eventType, $eventTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $timerEntry['project_qr_id']]);

                $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                $updateStmt->execute([$targetStatus, $timerId]);
            }

            $pdo->commit();
            return ['data' => ['message' => 'Timer status updated successfully.']];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => ['code' => 'server_error', 'message' => 'Server error: ' . $e->getMessage()], 'status' => 500];
        }
    }
}
