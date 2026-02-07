<?php
date_default_timezone_set('America/Mexico_City');
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/attendance.php';

header('Content-Type: application/json');

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = get_current_user_id();
$currentUserRole = get_current_user_role();

try {
    switch ($method) {
        case 'GET':
            handle_get_records($pdo, $currentUserId, $currentUserRole);
            break;
        case 'POST':
            handle_create_record($pdo, $currentUserId, $currentUserRole);
            break;
        case 'PUT':
            handle_update_record($pdo, $currentUserId, $currentUserRole);
            break;
        case 'DELETE':
            handle_delete_record($pdo, $currentUserId, $currentUserRole);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            break;
    }
} catch (Throwable $e) {
    error_log('Uncaught exception in Registros.php: ' . $e->getMessage() . ' on line ' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

ob_end_flush();

function ensure_authenticated(?int $userId): void {
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

function handle_get_records(PDO $pdo, ?int $currentUserId, ?string $currentUserRole): void {
    ensure_authenticated($currentUserId);

    if (isset($_GET['summary'])) {
        respond_with_summary($pdo, $currentUserRole, $currentUserId);
        return;
    }

    $queryUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $limitParam = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;
    $records = [];

    if ($currentUserRole === 'admin') {
        if ($queryUserId) {
            $records = fetch_records($pdo, $queryUserId, $limitParam);
        } elseif (isset($_GET['all'])) {
            $records = fetch_records($pdo, null, $limitParam);
        } else {
            $records = fetch_records($pdo, $currentUserId, $limitParam);
        }
    } elseif ($currentUserRole === 'special') {
        $targetUserId = $queryUserId ?: $currentUserId;
        $limit = $limitParam;
        if ($targetUserId !== $currentUserId) {
            $limit = $limit ? min($limit, 15) : 15;
        }
        $records = fetch_records($pdo, $targetUserId, $limit);
    } else {
        $records = fetch_records($pdo, $currentUserId, null);
    }

    echo json_encode(['success' => true, 'records' => $records]);
}

function respond_with_summary(PDO $pdo, ?string $currentUserRole, ?int $currentUserId): void {
    $userId = null;
    if ($currentUserRole !== 'admin') {
        $userId = $currentUserId;
    } elseif (isset($_GET['user_id'])) {
        $userId = (int)$_GET['user_id'];
    }

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

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $labels = array_column($results, 'work_month');
        $data = array_map(function ($minutes) {
            return round(((int)$minutes) / 60, 2);
        }, array_column($results, 'total_minutes'));

        echo json_encode(['success' => true, 'summary' => ['labels' => $labels, 'data' => $data]]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error for summary: ' . $e->getMessage()]);
    }
}

function fetch_records(PDO $pdo, ?int $userId, ?int $limit): array {
    $sql = 'SELECT ar.id, ar.user_id, u.username, ar.location, ar.type, ar.original_time, ar.rounded_time, 
                   ar.total_duration, ar.lunch_duration, ar.created_at, p.name AS project_name
            FROM attendance_records ar
            JOIN users u ON ar.user_id = u.id
            LEFT JOIN project_qrs pq ON ar.project_qr_id = pq.id
            LEFT JOIN projects p ON pq.project_id = p.id';
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


function handle_create_record(PDO $pdo, ?int $currentUserId, ?string $currentUserRole): void {
    ensure_authenticated($currentUserId);

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : $currentUserId;
    $location = trim($data['location'] ?? '');
    $type = $data['type'] ?? '';
    $projectQrId = isset($data['project_qr_id']) ? (int)$data['project_qr_id'] : null;
    $projectIdFromClient = isset($data['project_id']) ? (int)$data['project_id'] : null;
    $clientTimeIso = $data['client_time_iso'] ?? null;

    if (!$userId || !$location || !$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        return;
    }
    if (!$clientTimeIso) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'client_time_iso is required.']);
        return;
    }
    if ($currentUserRole !== 'admin' && $userId !== $currentUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot create records for another user.']);
        return;
    }

    try {
        $originalTime = new DateTime($clientTimeIso);
        $originalTime->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid client_time_iso format.']);
        return;
    }
    $roundedTime = round_time_to_next_quarter(clone $originalTime);

    // --- Project ID Resolution ---
    if ($projectQrId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM project_qrs WHERE id = ?');
        $stmt->execute([$projectQrId]);
        if (!$stmt->fetch()) {
            $projectQrId = null; // Invalidate if not found
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

    $pdo->beginTransaction();
    try {
                    if ($type === 'entry') {
                        $openTimer = find_open_timer_entry($pdo, $userId, false);            if ($openTimer) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Ya existe un temporizador activo.']);
                $pdo->rollBack();
                return;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $location, 'entry', $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $projectQrId, 1]);
        } else { // pause, resume, exit, etc.
            $openTimer = find_open_timer_entry($pdo, $userId, true);
            if (!$openTimer) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'No se encontró un temporizador activo para actualizar.']);
                $pdo->rollBack();
                return;
            }

            $currentProjectQrId = $projectQrId ?? $openTimer['project_qr_id'];
            $newStatus = map_timer_status_from_type($type);

            if ($type === 'exit') {
                $metrics = calculate_timer_metrics($pdo, $openTimer, $originalTime);
                $sessionDurationMinutes = (int)round($metrics['duration_seconds'] / 60);

                $stmt = $pdo->prepare(
                    'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId, $sessionDurationMinutes]);

                $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ?, total_duration = ? WHERE id = ?');
                $updateStmt->execute([$newStatus, $sessionDurationMinutes, $openTimer['id']]);

            } else if ($type === 'end_lunch') {
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

            } else { // For pause, resume, start_lunch
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId]);

                $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                $updateStmt->execute([$newStatus, $openTimer['id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Record ' . $type . ' processed successfully.']);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error in handle_create_record: ' . $e->getMessage() . ' on line ' . $e->getLine());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}


function handle_update_record(PDO $pdo, ?int $currentUserId, ?string $currentUserRole): void {
    ensure_authenticated($currentUserId);

    if ($currentUserRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only administrators can update records.']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $recordId = isset($data['id']) ? (int)$data['id'] : null;
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    $location = trim($data['location'] ?? '');
    $type = $data['type'] ?? '';
    $originalTimeStr = $data['original_time'] ?? null;
    $roundedTimeStr = $data['rounded_time'] ?? null;
    $totalDuration = isset($data['total_duration']) ? (int)$data['total_duration'] : null;
    $projectQrId = isset($data['project_qr_id']) ? (int)$data['project_qr_id'] : null;

    if (!$recordId || !$userId || !$location || !$type || !$originalTimeStr || !$roundedTimeStr) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields for update.']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE attendance_records
             SET user_id = ?, location = ?, type = ?, original_time = ?, rounded_time = ?, total_duration = ?, project_qr_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $userId,
            $location,
            $type,
            $originalTimeStr,
            $roundedTimeStr,
            $totalDuration,
            $projectQrId,
            $recordId
        ]);

        echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handle_delete_record(PDO $pdo, ?int $currentUserId, ?string $currentUserRole): void {
    ensure_authenticated($currentUserId);

    if ($currentUserRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only administrators can delete records.']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $recordId = isset($data['id']) ? (int)$data['id'] : null;

    if (!$recordId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing record ID for deletion.']);
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = ?');
        $stmt->execute([$recordId]);
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
