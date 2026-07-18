<?php
/**
 * Hub System Health Check API.
 * Endpoint: GET /api/system_health.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

// Verify Admin Master Key
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
}

$adminKey = $headers['X-Hub-Admin-Key'] ?? $_SERVER['HTTP_X_HUB_ADMIN_KEY'] ?? null;

if (empty($adminKey) || $adminKey !== HUB_ADMIN_MASTER_KEY) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized: Invalid Admin Master Key'
    ]);
    log_message('warning', 'Admin System Health API request rejected: invalid master key');
    exit();
}

try {
    // 1. Fetch expiring or expired platform tokens (expiring in next 7 days)
    $stmtExp = $pdo->prepare("
        SELECT pc.client_id, c.name as client_name, pc.platform, pc.status, pt.expires_at 
        FROM platform_connections pc
        JOIN clients c ON pc.client_id = c.id
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.status IN ('expired', 'expiring')
           OR (pt.expires_at IS NOT NULL AND pt.expires_at <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 DAY))
        ORDER BY pt.expires_at ASC
    ");
    $stmtExp->execute();
    $expiringTokens = $stmtExp->fetchAll() ?: [];

    // 2. Fetch last 10 failed posts across all clients
    $stmtFail = $pdo->prepare("
        SELECT p.id as post_id, c.name as client_name, pc.platform, p.content, p.created_at, pl.response_body
        FROM posts p
        JOIN clients c ON p.client_id = c.id
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        LEFT JOIN post_logs pl ON pl.post_id = p.id AND pl.success = 0
        WHERE p.status = 'failed'
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmtFail->execute();
    $failedPosts = $stmtFail->fetchAll() ?: [];

    // 3. Calculate estimated YouTube API quota units consumed today (Uploads = 1600 units each)
    $stmtQuota = $pdo->prepare("
        SELECT COUNT(*) as uploads_today
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE pc.platform = 'youtube' 
          AND p.status = 'published' 
          AND p.published_at >= DATE(CURRENT_TIMESTAMP)
    ");
    $stmtQuota->execute();
    $uploadsCount = $stmtQuota->fetchColumn();
    $quotaConsumed = $uploadsCount * 1600;

    header('Content-Type: application/json', true, 200);
    echo json_encode([
        'success'               => true,
        'expiring_tokens'       => $expiringTokens,
        'failed_posts'          => $failedPosts,
        'youtube_quota_consumed'=> (int)$quotaConsumed,
        'youtube_quota_limit'   => 10000 // Standard daily project limit
    ]);

} catch (Exception $e) {
    log_message('error', "System health API check failed", ['exception' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database query error: ' . $e->getMessage()
    ]);
}
