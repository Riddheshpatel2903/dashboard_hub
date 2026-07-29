<?php
/**
 * AJAX stats endpoint for Dashboard Home.
 * Supports platform and date range filtering.
 * Returns connections and post counts as JSON.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

if ($client_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No client context selected.']);
    exit();
}

// Check and run synchronization if needed - sync_analytics is removed, so we don't call it.

$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// 1. Fetch connection status from the Hub
$connCount = 0;
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected' && $conn['platform'] !== 'whatsapp') {
            if (empty($platform) || $conn['platform'] === $platform) {
                $connCount++;
            }
        }
    }
}

// 2. Load posts dynamically from the dedicated posts endpoint
$forceSync = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);
$allPosts = loadPlatformPosts($client_id, $forceSync);

// 3. Apply platform and date filters in PHP
$totalCount = 0;
$publishedCount = 0;
$scheduledCount = 0;

foreach ($allPosts as $post) {
    if (!empty($platform) && $post['platform'] !== $platform) {
        continue;
    }
    if (!empty($startDate) && !empty($endDate)) {
        $postDate = $post['published_at'] ?: ($post['scheduled_at'] ?: $post['created_at']);
        $postDay = date('Y-m-d', strtotime($postDate));
        if ($postDay < $startDate || $postDay > $endDate) {
            continue;
        }
    }
    $totalCount++;
    if ($post['status'] === 'published') {
        $publishedCount++;
    } elseif ($post['status'] === 'scheduled') {
        $scheduledCount++;
    }
}

echo json_encode([
    'success'           => true,
    'connections_count' => $connCount,
    'total_posts'       => $totalCount,
    'published_posts'   => $publishedCount,
    'scheduled_posts'   => $scheduledCount
]);
