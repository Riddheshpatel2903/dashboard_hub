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

// Check and run synchronization if needed (5-minute throttle)
$forceSync = (isset($_GET['force_sync']) && $_GET['force_sync'] == 1);
$stmtLastSync = $pdo->prepare("SELECT MAX(updated_at) FROM analytics_cache WHERE client_id = :client_id");
$stmtLastSync->execute(['client_id' => $client_id]);
$lastSync = $stmtLastSync->fetchColumn();
if ($forceSync || !$lastSync || (time() - strtotime($lastSync)) > 300) {
    require_once __DIR__ . '/../includes/sync_analytics.php';
    syncClientAnalytics($client_id, $pdo);
}

$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// 1. Fetch connection status from the Hub
$connCount = 0;
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            if (empty($platform) || $conn['platform'] === $platform) {
                $connCount++;
            }
        }
    }
}

// 2. Fetch post stats from local cache database with filters
$sql = "WHERE client_id = :client_id";
$params = ['client_id' => $client_id];

if (!empty($platform)) {
    $sql .= " AND platform = :platform";
    $params['platform'] = $platform;
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND (
        (status = 'published' AND DATE(published_at) BETWEEN :start_pub AND :end_pub)
        OR (status = 'scheduled' AND DATE(scheduled_at) BETWEEN :start_sched AND :end_sched)
        OR (status = 'failed' AND DATE(created_at) BETWEEN :start_fail AND :end_fail)
    )";
    $params['start_pub'] = $startDate;
    $params['end_pub'] = $endDate;
    $params['start_sched'] = $startDate;
    $params['end_sched'] = $endDate;
    $params['start_fail'] = $startDate;
    $params['end_fail'] = $endDate;
}

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
    FROM posts_cache 
    {$sql}
");
$stmt->execute($params);
$postStats = $stmt->fetch() ?: ['total' => 0, 'published' => 0, 'scheduled' => 0];

echo json_encode([
    'success'           => true,
    'connections_count' => $connCount,
    'total_posts'       => (int)$postStats['total'],
    'published_posts'   => (int)$postStats['published'],
    'scheduled_posts'   => (int)$postStats['scheduled']
]);
