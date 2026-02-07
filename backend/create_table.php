<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

$sql = "
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `original_time` datetime NOT NULL,
  `rounded_time` datetime DEFAULT NULL,
  `total_duration` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
";

try {
    $pdo->exec($sql);
    echo "Table 'attendance_records' created successfully.";
} catch (PDOException $e) {
    die("Could not create table: " . $e->getMessage());
}
?>