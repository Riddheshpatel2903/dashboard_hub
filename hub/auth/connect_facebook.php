<?php
/**
 * Initiates the Facebook OAuth flow.
 * Expects client_id as a query parameter.
 */

session_start();
$platforms = require_once __DIR__ . '/../config/platforms.php';

$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if ($clientId <= 0) {
    http_response_code(400);
    echo "Error: Missing or invalid client_id parameter.";
    exit();
}

$fbConfig = $platforms['facebook'];

// Generate a random CSRF nonce
$nonce = bin2hex(random_bytes(16));

// Store nonce in session for verification in the callback
$_SESSION['oauth_state_nonce'] = $nonce;

$platform = $_GET['platform'] ?? 'facebook';

// Base64 encode JSON containing client_id, nonce, and target platform
$stateData = [
    'client_id' => $clientId,
    'nonce'     => $nonce,
    'platform'  => $platform
];
$state = base64_encode(json_encode($stateData));

// Scopes required for Facebook Pages and Instagram Business posting and read
$scopes = [
    'pages_show_list',
    'pages_read_engagement',
    'pages_manage_posts',
    'pages_manage_metadata',
    'instagram_basic',
    'instagram_content_publish'
];

$authUrl = sprintf(
    "https://www.facebook.com/%s/dialog/oauth?client_id=%s&redirect_uri=%s&state=%s&scope=%s&response_type=code&auth_type=rerequest",
    $fbConfig['graph_api_version'],
    $fbConfig['app_id'],
    urlencode($fbConfig['redirect_uri']),
    $state,
    urlencode(implode(',', $scopes))
);

header("Location: " . $authUrl);
exit();
