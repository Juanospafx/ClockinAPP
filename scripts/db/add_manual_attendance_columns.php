<?php
/**
 * Migration: Add columns to attendance_records for manual entry support.
 *
 * New columns:
 *   - entry_source  ENUM('qr','manual') DEFAULT 'qr'
 *   - manual_reason TEXT NULL
 *   - created_by    INT NULL  (admin user_id who created a manual entry)
 *
 * Safe to re-run (checks if columns exist before ALTER).
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

$columns = [
    'entry_source' => "ALTER TABLE `attendance_records` ADD COLUMN `entry_source` ENUM('qr','manual') NOT NULL DEFAULT 'qr' AFTER `status`",
    'manual_reason' => "ALTER TABLE `attendance_records` ADD COLUMN `manual_reason` TEXT NULL AFTER `entry_source`",
    'created_by' => "ALTER TABLE `attendance_records` ADD COLUMN `created_by` INT(11) NULL AFTER `manual_reason`",
];

foreach ($columns as $col => $sql) {
    $check = $pdo->query("SHOW COLUMNS FROM `attendance_records` LIKE '{$col}'")->fetch();
    if ($check) {
        echo "Column '{$col}' already exists — skipped.\n";
        continue;
    }
    $pdo->exec($sql);
    echo "Column '{$col}' added successfully.\n";
}

// Add unique index to prevent exact duplicates for manual entries
try {
    $pdo->exec("CREATE INDEX idx_manual_dedup ON attendance_records (user_id, type, original_time, entry_source)");
    echo "Index idx_manual_dedup created.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "Index idx_manual_dedup already exists — skipped.\n";
    } else {
        throw $e;
    }
}

echo "\nDone.\n";
