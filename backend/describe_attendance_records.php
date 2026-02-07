<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

try {
    $stmt = $pdo->query("DESCRIBE `attendance_records`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>Schema for attendance_records table:\n";
    foreach ($columns as $col) {
        echo "Field: " . $col['Field'] . ", Type: " . $col['Type'] . ", Null: " . $col['Null'] . ", Key: " . $col['Key'] . ", Default: " . ($col['Default'] ?? 'NULL') . ", Extra: " . $col['Extra'] . "\n";
    }
    echo "</pre>";

} catch (PDOException $e) {
    die("Error describing table: " . $e->getMessage());
}
?>