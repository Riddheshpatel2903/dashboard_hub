<?php
/**
 * API Key sync diagnostic - deploy to rbfitness.in/new-site/hub/auth/check_apikey.php
 * Pass the API key as ?key=YOUR_KEY to test if it is recognized by the hub.
 * DELETE after use.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/connection.php';

echo "<h2>API Key Diagnostic</h2><pre>";

echo "HUB_BASE_URL: " . HUB_BASE_URL . "\n\n";

// 1. List all API keys in hub DB
echo "=== client_api_keys in Hub DB ===\n";
try {
    $rows = $pdo->query("SELECT id, client_id, api_key, created_at FROM client_api_keys ORDER BY client_id")->fetchAll();
    if (empty($rows)) {
        echo "NO KEYS FOUND — table is empty!\n";
        echo "This is why all API requests return 500/401.\n";
        echo "The hub DB does not have any API key matching your local dashboard DB.\n";
    } else {
        foreach ($rows as $r) {
            echo "client_id={$r['client_id']}  key_prefix=" . substr($r['api_key'], 0, 12) . "...  created={$r['created_at']}\n";
        }
    }
} catch (Throwable $e) {
    echo "ERROR querying client_api_keys: " . $e->getMessage() . "\n";
    echo "Table may not exist on production hub DB.\n";
}

echo "\n=== platform_connections in Hub DB ===\n";
try {
    $rows = $pdo->query("SELECT id, client_id, platform, status, external_account_id FROM platform_connections")->fetchAll();
    if (empty($rows)) {
        echo "NO CONNECTIONS — table is empty.\n";
    } else {
        foreach ($rows as $r) {
            echo "id={$r['id']}  client_id={$r['client_id']}  platform={$r['platform']}  status={$r['status']}  ext_id={$r['external_account_id']}\n";
        }
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 2. Test a specific key if passed
if (!empty($_GET['key'])) {
    $testKey = trim($_GET['key']);
    echo "\n=== Testing API Key: " . substr($testKey, 0, 12) . "... ===\n";
    $stmt = $pdo->prepare("SELECT client_id FROM client_api_keys WHERE api_key = :key LIMIT 1");
    $stmt->execute(['key' => $testKey]);
    $cid = $stmt->fetchColumn();
    echo "Result: " . ($cid ? "FOUND — client_id=$cid" : "NOT FOUND — key missing from hub DB!") . "\n";
}

echo "</pre>";
?>
