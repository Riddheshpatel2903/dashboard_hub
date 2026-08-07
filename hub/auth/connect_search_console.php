<?php
/**
 * Initiates the Google OAuth flow for Google Search Console.
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

$googleConfig = $platforms['google'];

// Generate a random CSRF nonce
$nonce = bin2hex(random_bytes(16));
$_SESSION['oauth_state_nonce'] = $nonce;

// Base64 encode JSON containing client_id, nonce, and dashboard_url
$stateData = [
    'client_id'     => $clientId,
    'nonce'         => $nonce,
    'dashboard_url' => $_GET['dashboard_url'] ?? ''
];
$state = base64_encode(json_encode($stateData));

$scopes = [
    'https://www.googleapis.com/auth/webmasters.readonly'
];

$authUrl = sprintf(
    "https://accounts.google.com/o/oauth2/v2/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s&access_type=offline&prompt=consent",
    $googleConfig['client_id'],
    urlencode($googleConfig['redirect_uri_search_console']),
    urlencode(implode(' ', $scopes)),
    $state
);

header("Location: " . $authUrl);
exit();
