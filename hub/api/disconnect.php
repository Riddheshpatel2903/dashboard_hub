<?php
/**
 * Disconnect Platform Connection Endpoint.
 * Endpoint: POST /api/disconnect.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$platform = $input['platform'] ?? '';

if (empty($platform)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing platform parameter']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Find the connection ID for this client + platform
    $stmt = $pdo->prepare("
        SELECT id FROM platform_connections 
        WHERE client_id = :client_id AND platform = :platform
        LIMIT 1
    ");
    $stmt->execute([
        'client_id' => $client_id,
        'platform'  => $platform
    ]);
    $connectionId = $stmt->fetchColumn();

    if ($connectionId) {
        // 2. Delete associated tokens
        $stmtTokens = $pdo->prepare("
            DELETE FROM platform_tokens 
            WHERE platform_connection_id = :connection_id
        ");
        $stmtTokens->execute(['connection_id' => $connectionId]);

        // 3. Delete the connection itself
        $stmtConn = $pdo->prepare("
            DELETE FROM platform_connections 
            WHERE id = :id
        ");
        $stmtConn->execute(['id' => $connectionId]);

        $pdo->commit();
        log_message('info', "Platform {$platform} disconnected for client {$client_id}", ['connection_id' => $connectionId]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Disconnected " . ucfirst($platform) . " successfully."
        ]);
    } else {
        $pdo->rollBack();
        header('Content-Type: application/json', true, 404);
        echo json_encode([
            'success' => false,
            'error'   => "No active connection found for platform: {$platform}"
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_message('error', "Failed to disconnect platform {$platform} for client {$client_id}", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
