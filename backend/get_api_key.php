<?php
header('Content-Type: application/json');

// Load .env file
$envPath = __DIR__ . '/.env';
error_log("Attempting to load .env from: " . $envPath);

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            error_log("Loaded ENV var: " . $key);
        }
    }
} else {
    error_log(".env file not found at: " . $envPath);
}

$apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? null;
error_log("API Key retrieved: " . ($apiKey ? "(exists)" : "(not found)"));

if ($apiKey) {
    echo json_encode(['success' => true, 'apiKey' => $apiKey]);
} else {
    echo json_encode(['success' => false, 'message' => 'API Key not found or empty.']);
}
?>