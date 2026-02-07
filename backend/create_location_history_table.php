<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

try {
    $sql = "CREATE TABLE IF NOT EXISTS location_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        timestamp DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "Table 'location_history' created successfully.";
} catch (PDOException $e) {
    die("Could not create table: " . $e->getMessage());
}
?>