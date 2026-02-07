<?php
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/attendance.php';

header('Content-Type: application/json');

$role = require_authenticated_role(['admin', 'special', 'user']);
$pdo = getPDO();

try {
    $userId = $_SESSION['user_id'] ?? null;

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
        // For admins/special, get timers for all 'user' role members
        $sql .= " AND r.name = 'user'";
    }

    // Get the latest entry for each user first
    $sql .= " ORDER BY ar.user_id, ar.original_time DESC";

    $stmt = $pdo->prepare($sql);

    if ($role === 'user') {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
    $timers = [];
    $processed_users = [];

    foreach ($entries as $entry) {
        $current_user_id = (int)$entry['user_id'];
        if (in_array($current_user_id, $processed_users)) {
            continue; // We only want the most recent entry per user
        }

        $metrics = calculate_timer_metrics($pdo, $entry, clone $now);
        $durationSeconds = $metrics['duration_seconds'];
        $runningSince = $metrics['running_since'] instanceof DateTime ? $metrics['running_since']->format(DATE_ATOM) : null;
        $startTime = new DateTime($entry['original_time']);

        $timers[] = [
            'id' => (int)$entry['id'],
            'user_id' => $current_user_id,
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
        
        $processed_users[] = $current_user_id;
    }

    echo json_encode([
        'success' => true,
        'server_time' => $now->format(DATE_ATOM),
        'timers' => $timers
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error in get_active_timers.php: ' . $e->getMessage() . ' on line ' . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

