<?php
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    $pdo = get_pdo();
    $sql = "ALTER TABLE attendance_records ADD COLUMN lunch_duration INT NULL DEFAULT NULL AFTER total_duration";
    $pdo->exec($sql);
    echo "Table 'attendance_records' altered successfully to add 'lunch_duration' column.";
} catch (PDOException $e) {
    die("Could not alter table: " . $e->getMessage());
}
