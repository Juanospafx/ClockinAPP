<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/session.php');
require_once(__DIR__ . '/includes/db.php');

// Ensure the user is logged in
if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in.']);
    exit;
}

header('Content-Type: application/json');

$user_id = current_user()['id'];

$sql = "SELECT 
            ar.id, 
            DATE(ar.timestamp) as date,
            ar.location, 
            ar.type, 
            TIME_FORMAT(ar.timestamp, '%H:%i:%s') as original_time, 
            TIME_FORMAT(ar.rounded_timestamp, '%H:%i:%s') as rounded_time,
            ar.total_duration / 60 as duration, -- Convert minutes to hours for display
            p.name as project_name
        FROM 
            attendance_records ar
        LEFT JOIN 
            projects p ON ar.project_id = p.id
        WHERE 
            ar.user_id = ?
        ORDER BY 
            ar.timestamp DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'records' => $records]);

} catch (Exception $e) {
    // Log error and send a generic message
    error_log('Error fetching user records: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching records.']);
}

$db->close();
