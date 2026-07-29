<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/hub/utils/token_helper.php';
require_once __DIR__ . '/hub/platforms/YouTubeHandler.php';

$client_id = 1;
$pdo_hub = require __DIR__ . '/hub/db/connection.php';

$token = get_valid_platform_token($pdo_hub, $client_id, 'youtube');
echo "Token: $token\n\n";

$urlMine = 'https://www.googleapis.com/youtube/v3/channels?part=statistics,contentDetails,snippet&mine=true';
$ch = curl_init($urlMine);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $res\n";
