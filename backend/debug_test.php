<?php
// Enable error reporting to see the output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Server Debug Test</h1>";

// --- Test 1: File Permissions ---
$log_dir = __DIR__ . '/../uploads';
$log_file = $log_dir . '/php_errors.log';
echo "<h2>Test 1: File Permissions</h2>";
echo "<p>Checking directory: <code>" . realpath($log_dir) . "</code></p>";

if (is_writable($log_dir)) {
    echo "<p style='color:green; font-weight:bold;'>SUCCESS: The directory 'uploads' is writable.</p>";
    
    // Try writing to the log file
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] Debug test: PHP can write to this file.\n";
    if (file_put_contents($log_file, $log_message, FILE_APPEND)) {
        echo "<p style='color:green; font-weight:bold;'>SUCCESS: A test message was written to the error log.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>FAILURE: The 'uploads' directory is writable, but writing to the log file failed. Check file permissions or ownership.</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>FAILURE: The directory 'uploads' is NOT writable. Please set its permissions to 775.</p>";
}

echo "<hr>";

// --- Test 2: Database Connection ---
echo "<h2>Test 2: Database Connection</h2>";
echo "<p>Attempting to connect to the database...</p>";

try {
    $host = 'localhost';
    $db   = 'brightro_qrapp_inv';
    $user = 'brightro_qrapp_inv';
    $pass = 'cV8JFL76kmqttUXLUguQ';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p style='color:green; font-weight:bold;'>SUCCESS: Database connection was successful.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>FAILURE: Database connection failed.</p>";
    echo "<p><b>Error Message:</b></p>";
    echo "<pre style='background-color:#eee; padding:10px; border:1px solid red;'>" . $e->getMessage() . "</pre>";
    echo "<p><b>Action:</b> Double-check that the database host, name, username, and password are correct. Also ensure the user has been granted privileges to the database.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red; font-weight:bold;'>FAILURE: A non-database error occurred.</p>";
    echo "<p><b>Error Message:</b></p>";
    echo "<pre style='background-color:#eee; padding:10px; border:1px solid red;'>" . $e->getMessage() . "</pre>";
}

?>