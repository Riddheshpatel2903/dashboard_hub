<?php
/**
 * AJAX proxy endpoint for Google PageSpeed Insights.
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

if ($client_id === null) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$url = $_GET['url'] ?? '';
if (empty($url)) {
    echo json_encode(['success' => false, 'error' => 'URL is required']);
    exit();
}

$strategy = $_GET['strategy'] ?? 'mobile';

try {
    $res = hubGetPageSpeed($client_id, $url, $strategy);
    echo json_encode($res);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
