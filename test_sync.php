<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/dashboard/includes/hub_client.php';
require_once __DIR__ . '/hub/utils/token_helper.php';

$client_id = 1;
$pdo_hub = require __DIR__ . '/hub/db/connection.php';

echo "Testing get_valid_platform_token for client 1, youtube...\n";
try {
    $token = get_valid_platform_token($pdo_hub, $client_id, 'youtube');
    echo "Result token: " . ($token ? substr($token, 0, 15) . "..." : "NULL/Empty") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTesting loadPlatformPosts with forceSync = true...\n";
try {
    $res = loadPlatformPosts($client_id, true);
    echo "Count of posts returned: " . count($res) . "\n";
    if (count($res) > 0) {
        echo "First post platform: " . $res[0]['platform'] . "\n";
        echo "First post content: " . substr($res[0]['content'], 0, 50) . "\n";
        echo "First post views/likes: " . ($res[0]['views_count'] ?? 0) . " / " . ($res[0]['likes_count'] ?? 0) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
