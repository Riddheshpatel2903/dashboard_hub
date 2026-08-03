<?php
header('Content-Type: text/plain');

echo "=== DIAGNOSTIC REPORT ===\n\n";

try {
    $dashPdo = require __DIR__ . '/../../../dashboard/db/connection.php';
    echo "1. Connected to Dashboard Database successfully.\n";
    
    // Check clients
    $stmt = $dashPdo->query("SELECT * FROM clients");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Clients count: " . count($clients) . "\n";
    foreach ($clients as $c) {
        echo "   - ID: {$c['id']}, Name: {$c['name']}\n";
    }

    // Check client_hub_keys
    $stmt = $dashPdo->query("SELECT * FROM client_hub_keys");
    $hubKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   client_hub_keys count: " . count($hubKeys) . "\n";
    foreach ($hubKeys as $hk) {
        echo "   - Client ID: {$hk['client_id']}, Hub API Key: " . substr($hk['hub_api_key'], 0, 10) . "...\n";
    }

    // Check posts_cache
    $stmt = $dashPdo->query("SELECT platform, COUNT(*) as c FROM posts_cache GROUP BY platform");
    echo "   posts_cache summary:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - Platform: " . ($row['platform'] ?: 'local') . ": {$row['c']}\n";
    }

} catch (Exception $e) {
    echo "ERROR connecting to Dashboard DB: " . $e->getMessage() . "\n";
}

echo "\n";

try {
    $hubPdo = require __DIR__ . '/../../../hub/db/connection.php';
    echo "2. Connected to Hub Database successfully.\n";

    // Check client_api_keys
    $stmt = $hubPdo->query("SELECT * FROM client_api_keys");
    $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   client_api_keys count: " . count($apiKeys) . "\n";
    foreach ($apiKeys as $ak) {
        echo "   - Client ID: {$ak['client_id']}, Key: " . substr($ak['api_key'], 0, 10) . "...\n";
    }

    // Check platform_connections
    $stmt = $hubPdo->query("SELECT * FROM platform_connections");
    $conns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   platform_connections count: " . count($conns) . "\n";
    foreach ($conns as $conn) {
        echo "   - ID: {$conn['id']}, Client ID: {$conn['client_id']}, Platform: {$conn['platform']}, Ext ID: {$conn['external_account_id']}, Status: {$conn['status']}, Connected At: {$conn['connected_at']}\n";
    }

    // Check platform_tokens
    $stmt = $hubPdo->query("SELECT platform_connection_id, LENGTH(access_token_encrypted) as len, expires_at FROM platform_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   platform_tokens count: " . count($tokens) . "\n";
    foreach ($tokens as $t) {
        echo "   - Conn ID: {$t['platform_connection_id']}, Token Len: {$t['len']}, Expires At: {$t['expires_at']}\n";
    }

} catch (Exception $e) {
    echo "ERROR connecting to Hub DB: " . $e->getMessage() . "\n";
}
