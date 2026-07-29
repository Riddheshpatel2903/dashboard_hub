<?php
/**
 * Hub Client Sync Endpoint.
 * Securely registers or updates a client + API key in the Hub DB.
 * Called once from the local dashboard sync tool to bridge local ↔ production.
 *
 * POST /hub/api/sync_client.php
 * Headers: X-Admin-Key: <HUB_ADMIN_MASTER_KEY>
 * Body JSON: { "client_id": 1, "client_name": "...", "website_url": "...", "api_key": "..." }
 */

require_once __DIR__ . '/../config/config.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

header('Content-Type: application/json');

// Verify admin key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$adminKey = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';

if (empty($adminKey) || $adminKey !== HUB_ADMIN_MASTER_KEY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: invalid admin key']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$clientId  = (int)($body['client_id'] ?? 0);
$clientName = trim($body['client_name'] ?? '');
$websiteUrl = trim($body['website_url'] ?? '');
$apiKey     = trim($body['api_key'] ?? '');

if ($clientId <= 0 || empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing client_id or api_key']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Upsert into clients table (use provided name or fallback)
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $clientId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // Update name/url if provided
        if (!empty($clientName)) {
            $pdo->prepare("UPDATE clients SET name = :name, website_url = :url WHERE id = :id")
                ->execute(['name' => $clientName, 'url' => $websiteUrl ?: '', 'id' => $clientId]);
        }
    } else {
        // Need to force insert with specific ID — disable auto-increment temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->prepare("INSERT INTO clients (id, name, website_url) VALUES (:id, :name, :url)")
            ->execute(['id' => $clientId, 'name' => $clientName ?: 'Synced Client', 'url' => $websiteUrl ?: '']);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    // 2. Upsert API key
    $pdo->prepare("
        INSERT INTO client_api_keys (client_id, api_key)
        VALUES (:client_id, :api_key)
        ON DUPLICATE KEY UPDATE api_key = VALUES(api_key)
    ")->execute(['client_id' => $clientId, 'api_key' => $apiKey]);

    $pdo->commit();

    log_message('info', 'Client synced via sync_client endpoint', ['client_id' => $clientId]);
    echo json_encode(['success' => true, 'message' => "Client $clientId synced to hub DB"]);

} catch (Throwable $e) {
    $pdo->rollBack();
    log_message('error', 'sync_client failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
