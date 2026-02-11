<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

try {
    // Check if the column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'profile_pic_url'");
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        echo "Column 'profile_pic_url' already exists in 'users' table.";
    } else {
        $sql = "ALTER TABLE `users` ADD `profile_pic_url` VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "Table 'users' updated successfully. Column 'profile_pic_url' was added.";
    }
} catch (PDOException $e) {
    die("Could not update table: " . $e->getMessage());
}
?>
