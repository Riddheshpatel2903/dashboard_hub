<?php
/**
 * Initiates the LinkedIn OAuth flow.
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

$liConfig = $platforms['linkedin'];

// Generate a random CSRF nonce
$nonce = bin2hex(random_bytes(16));
$_SESSION['oauth_state_nonce'] = $nonce;

// Base64 encode JSON containing client_id and nonce
$stateData = [
    'client_id' => $clientId,
    'nonce'     => $nonce
];
$state = base64_encode(json_encode($stateData));

// Scopes required for OpenID Connect + Posting
$scopes = [
    'openid',
    'profile',
    'w_member_social'
];

$authUrl = sprintf(
    "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=%s&redirect_uri=%s&state=%s&scope=%s",
    $liConfig['client_id'],
    urlencode($liConfig['redirect_uri']),
    $state,
    urlencode(implode(' ', $scopes))
);

header("Location: " . $authUrl);
exit();
