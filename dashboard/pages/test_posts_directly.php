<?php
/**
 * Test posts endpoint directly.
 */
header('Content-Type: text/plain');

try {
    $dashPdo = require __DIR__ . '/../db/connection.php';
    $stmt = $dashPdo->prepare("SELECT hub_api_key FROM client_hub_keys WHERE client_id = 1 LIMIT 1");
    $stmt->execute();
    $apiKey = $stmt->fetchColumn();
    
    if (!$apiKey) {
        echo "Error: No API key found in dashboard database client_hub_keys for client_id=1\n";
        exit();
    }
    
    echo "API Key: " . substr($apiKey, 0, 8) . "...\n";
    
    $hubUrl = 'https://rbfitness.in/new-site/hub/api/posts.php';
    echo "Requesting URL: $hubUrl\n";
    
    $ch = curl_init($hubUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Status Code: $httpCode\n";
    if ($curlError) {
        echo "cURL Error: $curlError\n";
    }
    echo "Response Body:\n";
    echo $response . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
