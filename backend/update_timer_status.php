<?php
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/attendance.php';

header('Content-Type: application/json');

$role = require_authenticated_role(['admin', 'special']);
$pdo = getPDO();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$timerId = isset($data['id']) ? (int)$data['id'] : null;
$requestedStatus = $data['status'] ?? '';
$location = trim($data['location'] ?? 'Manual Action');
$clientTimeIso = $data['client_time_iso'] ?? null;

if (!$timerId || !$requestedStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing timer ID or status.']);
    exit;
}

if (!$clientTimeIso) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'client_time_iso is required.']);
    exit;
}

$statusMap = [
    'paused'  => ['status' => 2, 'type' => 'pause'],
    'resumed' => ['status' => 1, 'type' => 'resume'],
    'stopped' => ['status' => 3, 'type' => 'exit'],
];

if (!isset($statusMap[$requestedStatus])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

$targetStatus = $statusMap[$requestedStatus]['status'];
$eventType = $statusMap[$requestedStatus]['type'];

$pdo->beginTransaction();
try {
    $timerEntry = get_timer_entry_by_id($pdo, $timerId, false);

    if (!$timerEntry) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Timer not found.']);
        $pdo->rollBack();
        return;
    }

    if ((int)$timerEntry['status'] === 3) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Timer is already stopped.']);
        $pdo->rollBack();
        return;
    }

    try {
        $eventTime = new DateTime($clientTimeIso);
        $eventTime->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid client_time_iso format.']);
        $pdo->rollBack();
        return;
    }
    
    $roundedTime = round_time_to_next_quarter(clone $eventTime);
    $userId = (int)$timerEntry['user_id'];

    if ($eventType === 'exit') {
        $metrics = calculate_timer_metrics($pdo, $timerEntry, $eventTime);
        $totalDurationMinutes = (int)round($metrics['duration_seconds'] / 60);

        // Insert the exit event with the duration
        $stmt = $pdo->prepare(
            'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $location, $eventType, $eventTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $timerEntry['project_qr_id'], $totalDurationMinutes]);

        // Update the entry record with status and duration
        $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ?, total_duration = ? WHERE id = ?');
        $updateStmt->execute([$targetStatus, $totalDurationMinutes, $timerId]);

    } else {
        // Insert other events (pause, resume) without duration
        $stmt = $pdo->prepare(
            'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $location, $eventType, $eventTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $timerEntry['project_qr_id']]);

        // Update the entry record with status only
        $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
        $updateStmt->execute([$targetStatus, $timerId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Timer status updated successfully.']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error in update_timer_status.php: ' . $e->getMessage() . ' on line ' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
