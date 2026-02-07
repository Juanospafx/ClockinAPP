<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/attendance.php';

header('Content-Type: application/json');

$role = require_authenticated_role(['user', 'special']);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'timer' => null, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getPDO();

try {
    $entry = find_open_timer_entry($pdo, $userId);

    if (!$entry) {
        echo json_encode(['success' => true, 'timer' => null]);
        exit;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $metrics = calculate_timer_metrics($pdo, $entry, clone $now);
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

    echo json_encode(['success' => true, 'timer' => $timer]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'timer' => null, 'message' => 'Database error: ' . $e->getMessage()]);
}
