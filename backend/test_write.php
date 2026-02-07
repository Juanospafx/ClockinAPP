<?php
$log_file = __DIR__ . '/../../uploads/test.log';
$message = "Test write at " . date('Y-m-d H:i:s') . "\n";

if (file_put_contents($log_file, $message, FILE_APPEND) !== false) {
    echo "Successfully attempted to write to $log_file";
} else {
    echo "Failed to write to $log_file. Check directory permissions.";
}
?>