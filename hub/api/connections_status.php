<?php
/**
 * Platform Connections Status Endpoint.
 * Endpoint: GET /api/connections_status.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

try {
    // Try to query with last_synced_at; fall back gracefully if the column doesn't exist yet
    $connections = [];
    try {
        $stmt = $pdo->prepare("
            SELECT pc.id, pc.platform, pc.external_account_id, pc.status, pc.connected_at, pc.last_synced_at, pt.expires_at
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id
        ");
        $stmt->execute(['client_id' => $client_id]);
        $connections = $stmt->fetchAll();
    } catch (Exception $colEx) {
        // last_synced_at column may not exist yet — run without it
        $stmt = $pdo->prepare("
            SELECT pc.id, pc.platform, pc.external_account_id, pc.status, pc.connected_at, NULL as last_synced_at, pt.expires_at
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id
        ");
        $stmt->execute(['client_id' => $client_id]);
        $connections = $stmt->fetchAll();

        // Attempt to add the missing column automatically
        try {
            $pdo->exec('ALTER TABLE `platform_connections` ADD COLUMN IF NOT EXISTS `last_synced_at` TIMESTAMP NULL DEFAULT NULL');
        } catch (Exception $alterEx) {
            // Non-fatal
        }
    }

    $responseList = [];
    $now = time();

    foreach ($connections as $conn) {
        $expiresAt = $conn['expires_at'];
        $expiresSoon = false;
        
        if ($expiresAt) {
            $expiresTime = strtotime($expiresAt);
            if ($expiresTime > $now && ($expiresTime - $now) <= (7 * 24 * 3600)) {
                $expiresSoon = true;
            }
        }

        $responseList[] = [
            'id'                  => (int)$conn['id'],
            'platform'            => $conn['platform'],
            'external_account_id' => $conn['external_account_id'],
            'status'              => $conn['status'],
            'connected_at'        => $conn['connected_at'],
            'last_synced_at'      => $conn['last_synced_at'] ?? null,
            'expires_at'          => $expiresAt,
            'expires_soon'        => $expiresSoon
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'     => true,
        'connections' => $responseList
    ]);

} catch (Exception $e) {
    log_message('error', "Connections status fetch failed", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to retrieve connection statuses'
    ]);
}
