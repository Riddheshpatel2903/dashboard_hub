<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = '94a21f797c70cf6c158c088c32a94bf89a1e400b646989b4cb445c8ff15a9978';
$url = 'http://localhost/dashboard_hub/hub/api/analytics.php?platform=youtube&post_id=0&start_date=2026-07-01&end_date=2026-07-29';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $res\n";
