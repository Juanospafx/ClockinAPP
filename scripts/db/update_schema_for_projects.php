<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

try {
    // Create projects table
    $sql_projects = "
    CREATE TABLE IF NOT EXISTS `projects` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL UNIQUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_projects);
    echo "Table 'projects' created or already exists.\n";

    // Create project_qrs table
    $sql_project_qrs = "
    CREATE TABLE IF NOT EXISTS `project_qrs` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `project_id` INT(11) NOT NULL,
      `action_type` VARCHAR(50) NOT NULL,
      `location` VARCHAR(255) NOT NULL,
      `qr_content` TEXT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_project_qrs);
    echo "Table 'project_qrs' created or already exists.\n";

    // Add project_qr_id to attendance_records
    $sql_alter_attendance = "ALTER TABLE `attendance_records` ADD `project_qr_id` INT(11) NULL AFTER `total_duration`";
    $pdo->exec($sql_alter_attendance);
    echo "Column 'project_qr_id' added to 'attendance_records' or already exists.\n";

    // Add foreign key constraint (optional, but good practice)
    $sql_add_fk = "ALTER TABLE `attendance_records` ADD CONSTRAINT `fk_project_qr` FOREIGN KEY (`project_qr_id`) REFERENCES `project_qrs`(`id`) ON DELETE SET NULL";
    $pdo->exec($sql_add_fk);
    echo "Foreign key 'fk_project_qr' added to 'attendance_records' or already exists.\n";

} catch (PDOException $e) {
    // Handle specific error for column already existing
    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'Cannot add foreign key constraint') !== false) {
        echo "Database schema already up to date (some elements might exist).\n";
    } else {
        die("Database error: " . $e->getMessage());
    }
}
?>
