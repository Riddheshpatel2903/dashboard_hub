<?php
header('Content-Type: text/plain');

require_once __DIR__ . '/hub/utils/encryption.php';

try {
    // Connect to dashboard database
    $dashPdo = new PDO(
        "mysql:host=srv2216.hstgr.io;port=3306;dbname=u689131217_dashboard_db;charset=utf8mb4",
        'u689131217_dashboard_user',
        'ao3P;k~OD+1'
    );
    $dashPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the client hub key for client 3
    $stmt = $dashPdo->prepare("SELECT hub_api_key FROM client_hub_keys WHERE client_id = 3 LIMIT 1");
    $stmt->execute();
    $apiKey = $stmt->fetchColumn();

    if (!$apiKey) {
        die("No Hub API key found for client 3.");
    }

    echo "Found Hub API Key: {$apiKey}\n\n";

    // Build payload
    $payload = [
        'platforms' => ['instagram'],
        'content'  => 'Test Post from Debug Script',
        'media_temp_path' => 'clients/3/test_dummy.jpg' // Dummy image path
    ];

    $url = "http://localhost/dashboard_hub/hub/api/post.php";

    echo "Sending POST request to {$url}...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status Code: {$httpCode}\n";
    echo "Response Body:\n{$response}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
