<?php
/**
 * AJAX endpoint to unlink/disconnect a platform connection.
 * Endpoint: POST /pages/unlink_connection.php
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$platform = $input['platform'] ?? '';

if (empty($platform)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing platform parameter.']);
    exit();
}

if ($client_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active client selected.']);
    exit();
}

// Call Hub connection disconnect API
$res = hubDisconnectConnection($client_id, $platform);

if (!empty($res['success'])) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($platform) . " unlinked and credentials deleted successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $res['error'] ?? 'Hub proxy failed to disconnect connection.'
    ]);
}
