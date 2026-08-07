<?php
/**
 * SEO Analytics API Endpoint (Search Console & PageSpeed).
 * Endpoint: GET /api/seo.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/platforms.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/token_helper.php';
require_once __DIR__ . '/../platforms/SearchConsoleHandler.php';
require_once __DIR__ . '/../platforms/PageSpeedHandler.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'search_analytics';

if ($action === 'search_analytics') {
    $token = get_valid_platform_token($pdo, $client_id, 'search_console');
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Search Console not connected for this client.']);
        exit();
    }
    $connStmt = $pdo->prepare("SELECT external_account_id FROM platform_connections WHERE client_id = :client_id AND platform = 'search_console' LIMIT 1");
    $connStmt->execute(['client_id' => $client_id]);
    $siteUrl = $connStmt->fetchColumn();

    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-28 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $dimensions = isset($_GET['dimensions']) ? explode(',', $_GET['dimensions']) : ['date'];

    try {
        $rows = SearchConsoleHandler::getSearchAnalytics($token, $siteUrl, $startDate, $endDate, $dimensions);
        
        if (!empty($rows) && isset($rows[0]['keys'][0])) {
            usort($rows, function($a, $b) {
                return strcmp($a['keys'][0] ?? '', $b['keys'][0] ?? '');
            });
        }
        
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        log_message('warning', 'Search Console fetch failed', ['client_id' => $client_id, 'error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'pagespeed') {
    $url = $_GET['url'] ?? '';
    if (empty($url)) {
        echo json_encode(['success' => false, 'error' => 'URL is required.']);
        exit();
    }
    try {
        $result = PageSpeedHandler::analyze($url, $_GET['strategy'] ?? 'mobile');
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        log_message('warning', 'PageSpeed fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
