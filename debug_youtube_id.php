<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/hub/utils/token_helper.php';
require_once __DIR__ . '/hub/platforms/YouTubeHandler.php';

$client_id = 1;
$pdo_hub = require __DIR__ . '/hub/db/connection.php';
$token = get_valid_platform_token($pdo_hub, $client_id, 'youtube');

$channelId = 'UCQfkt17JKe9XS-MjVGim0nA';

echo "Testing getChannelStats with ID...\n";
try {
    $res1 = YouTubeHandler::getChannelStats($token, $channelId);
    echo "ID Query Items Count: " . count($res1['items'] ?? []) . "\n";
    if (empty($res1['items'])) {
        echo "Response: " . json_encode($res1) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTesting getChannelStats with 'mine'...\n";
try {
    $res2 = YouTubeHandler::getChannelStats($token, 'mine');
    echo "Mine Query Items Count: " . count($res2['items'] ?? []) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
