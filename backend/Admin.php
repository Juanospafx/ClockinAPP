<?php
error_log("TEST: Admin.php started execution in new log directory.");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/Registros.php';

$pdo = getPDO();

header('Content-Type: application/json');

// In a real application, you would have robust authentication and authorization here.
// For example, checking a JWT token and ensuring the user has the 'admin' role.

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

// Read data from $_POST
$data = $_POST;
error_log("Admin.php received POST data: " . print_r($data, true));

$action = $data['action'] ?? null;

switch ($action) {
    case 'adjust_time':
        $recordId       = $data['record_id'] ?? null;
        $newRoundedTime = $data['new_rounded_time'] ?? null;
        $newDuration    = $data['new_duration'] ?? null;

        if (!$recordId || !$newRoundedTime) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta record_id o new_rounded_time.'
            ]);
            exit();
        }

        try {
            $updates = [];
            $params  = [];

            if ($newRoundedTime) {
                $updates[] = 'rounded_time = ?';
                $params[]  = $newRoundedTime;
            }
            if ($newDuration !== null && is_numeric($newDuration)) {
                $updates[] = 'total_duration = ?';
                $params[]  = $newDuration;
            }

            if (empty($updates)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay campos válidos para actualizar.'
                ]);
                exit();
            }

            $params[] = $recordId;
            $sql      = 'UPDATE attendance_records SET ' . implode(', ', $updates) . ' WHERE id = ?';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro actualizado exitosamente.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado o sin cambios.'
                ]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de BD: ' . $e->getMessage()
            ]);
        }
        break;

    case 'delete_record':
        $recordId = $data['record_id'] ?? null;

        if (!$recordId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta record_id.'
            ]);
            exit();
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = ?');
            $stmt->execute([$recordId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro eliminado exitosamente.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado.'
                ]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de BD: ' . $e->getMessage()
            ]);
        }
        break;

    case 'recalculate_daily_duration':
        $userId = $data['user_id'] ?? null;
        $date   = $data['date'] ?? null;

        if (!$userId || !$date) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Falta user_id o date.'
            ]);
            exit();
        }

        try {
            $totalDuration = calculate_daily_work_duration($pdo, $userId, $date);

            $stmt = $pdo->prepare(
                "UPDATE attendance_records
                 SET total_duration = ?
                 WHERE user_id = ? AND DATE(original_time) = ? AND type = 'exit'"
            );
            $stmt->execute([$totalDuration, $userId, $date]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success'        => true,
                    'message'        => 'Duración diaria recalculada y actualizada.',
                    'total_duration'=> $totalDuration
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró registro de salida para actualizar.'
                ]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de BD: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Acción inválida.'
        ]);
        break;
}
