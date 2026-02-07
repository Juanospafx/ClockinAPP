<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getPDO();
    $sql = "ALTER TABLE attendance_records ADD COLUMN status TINYINT(1) DEFAULT 1 COMMENT '1=active, 2=paused, 3=stopped' AFTER project_qr_id";
    $pdo->exec($sql);
    echo "Table 'attendance_records' altered successfully to add 'status' column.";
} catch (PDOException $e) {
    die("Could not alter the table: " . $e->getMessage());
}
