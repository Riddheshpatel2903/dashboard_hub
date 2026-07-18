<?php
/**
 * API Authentication Middleware.
 * Included at the top of every API endpoint.
 * Validates X-API-Key and exposes $client_id.
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

// Retrieve headers in a cross-compatible way
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
}

// Find key in headers case-insensitively
$apiKey = null;
foreach (['X-API-Key', 'x-api-key', 'X-Api-Key'] as $keyName) {
    if (!empty($headers[$keyName])) {
        $apiKey = $headers[$keyName];
        break;
    }
}

// Fallback to query string or HTTP_X_API_KEY directly
if (!$apiKey && isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
}

if (empty($apiKey)) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized: Missing API Key'
    ]);
    log_message('warning', 'API request rejected: missing X-API-Key', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit();
}

// Look up key in client_api_keys
$stmt = $pdo->prepare("
    SELECT client_id FROM client_api_keys 
    WHERE api_key = :api_key 
    LIMIT 1
");
$stmt->execute(['api_key' => $apiKey]);
$client_id = $stmt->fetchColumn();

if (!$client_id) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized: Invalid API Key'
    ]);
    log_message('warning', 'API request rejected: invalid X-API-Key', ['key' => substr($apiKey, 0, 6) . '...', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit();
}

// $client_id is now defined and available to the script including this file.
