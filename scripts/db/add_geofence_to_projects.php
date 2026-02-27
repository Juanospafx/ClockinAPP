<?php
/**
 * Migration: Add geofence columns to projects table.
 *
 * Adds latitude, longitude, and geofence_radius to the projects table
 * to support location-based clock in/out validation.
 *
 * Run: php scripts/db/add_geofence_to_projects.php
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

$migrations = [
    "ALTER TABLE `projects` ADD COLUMN `latitude` DECIMAL(10, 8) NULL DEFAULT NULL AFTER `name`",
    "ALTER TABLE `projects` ADD COLUMN `longitude` DECIMAL(11, 8) NULL DEFAULT NULL AFTER `latitude`",
    "ALTER TABLE `projects` ADD COLUMN `geofence_radius` INT UNSIGNED NOT NULL DEFAULT 100 AFTER `longitude`",
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration complete.\n";
