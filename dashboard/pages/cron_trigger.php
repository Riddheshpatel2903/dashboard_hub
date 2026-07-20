<?php
/**
 * Cron Trigger Proxy.
 * Safely calls the Hub's scheduler and queue worker via background server-side cURL requests.
 * Uses CRON_SECRET security token and handles HTTP errors gracefully.
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

$results = [
    'scheduler' => ['success' => false, 'error' => null, 'http_code' => 0, 'response' => null],
    'worker'    => ['success' => false, 'error' => null, 'http_code' => 0, 'response' => null]
];

// Helper to make a secure cURL request
function makeCronRequest($endpoint) {
    // Append the secret token to the request
    $url = $endpoint . '?token=' . urlencode(CRON_SECRET);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds max timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore local SSL issues during dev
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success'   => false,
            'error'     => "cURL Error: {$curlError}",
            'http_code' => $httpCode,
            'response'  => null
        ];
    }
    
    $decoded = json_decode($response, true);
    $isSuccess = ($httpCode === 200 && (!isset($decoded['success']) || $decoded['success'] === true));
    
    return [
        'success'   => $isSuccess,
        'error'     => $httpCode !== 200 ? "HTTP Error Code {$httpCode}" : ($decoded['error'] ?? null),
        'http_code' => $httpCode,
        'response'  => $decoded
    ];
}

// 1. Trigger Scheduler
$schedEndpoint = HUB_BASE_URL . '/jobs/scheduler.php';
$results['scheduler'] = makeCronRequest($schedEndpoint);

// 2. Trigger Queue Worker
$workerEndpoint = HUB_BASE_URL . '/jobs/queue_worker.php';
$results['worker'] = makeCronRequest($workerEndpoint);

// Set appropriate HTTP status if either job fails
$overallSuccess = ($results['scheduler']['success'] && $results['worker']['success']);
if (!$overallSuccess) {
    http_response_code(500);
}

echo json_encode([
    'success' => $overallSuccess,
    'details' => $results
]);
