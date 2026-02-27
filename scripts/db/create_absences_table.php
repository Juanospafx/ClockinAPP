<?php
/**
 * Migration: Create absences table.
 *
 * Stores absence reports submitted by users.
 *
 * Run: php scripts/db/create_absences_table.php
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

$sql = "
CREATE TABLE IF NOT EXISTS `absences` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `project_id` INT(11) NULL DEFAULT NULL,
  `date_start` DATE NOT NULL,
  `date_end` DATE NOT NULL,
  `reason` ENUM('familiar','enfermedad','vacaciones','sin_justificacion') NOT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `evidence_path` VARCHAR(500) NULL DEFAULT NULL,
  `status` ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `reviewed_by` INT(11) NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_absences_user` (`user_id`),
  KEY `idx_absences_project` (`project_id`),
  KEY `idx_absences_status` (`status`),
  KEY `idx_absences_dates` (`date_start`, `date_end`),
  CONSTRAINT `fk_absences_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_absences_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_absences_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "Table 'absences' created successfully (or already exists).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
