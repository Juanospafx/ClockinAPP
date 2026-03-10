<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_absences_user_id ON absences (user_id)",
    "CREATE INDEX IF NOT EXISTS idx_absences_dates ON absences (date_start, date_end)",
    "CREATE INDEX IF NOT EXISTS idx_absences_status ON absences (status)",
    "CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications (user_id, is_read)",
];

foreach ($indexes as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: {$sql}\n";
    } catch (PDOException $e) {
        echo "SKIP: {$e->getMessage()}\n";
    }
}

echo "Done.\n";
