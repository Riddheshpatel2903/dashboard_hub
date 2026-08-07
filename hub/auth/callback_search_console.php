<?php
/**
 * Google OAuth Callback handler for Google Search Console.
 * Exchanges authorization code for access and refresh tokens, gets verified properties list, and registers the connection.
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
$platforms = require_once __DIR__ . '/../config/platforms.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

if (isset($_GET['error'])) {
    http_response_code(400);
    echo "Connection was not approved. Error: " . htmlspecialchars($_GET['error']);
    exit();
}

$code = $_GET['code'] ?? '';
$stateParam = $_GET['state'] ?? '';

if (empty($code) || empty($stateParam)) {
    http_response_code(400);
    echo "Error: Invalid request parameters.";
    exit();
}

// Decode State
$stateData = json_decode(base64_decode($stateParam), true);
if (!$stateData || empty($stateData['client_id']) || empty($stateData['nonce'])) {
    http_response_code(400);
    echo "Error: Invalid state token.";
    exit();
}

// Verify CSRF state nonce
if (empty($stateData['nonce'])) {
    http_response_code(403);
    echo "Error: CSRF validation failed (missing nonce).";
    exit();
}
if (!empty($_SESSION['oauth_state_nonce'])) {
    unset($_SESSION['oauth_state_nonce']);
}

$clientId = (int)$stateData['client_id'];
$googleConfig = $platforms['google'];

// 1. Exchange OAuth code for tokens
$tokenUrl = "https://oauth2.googleapis.com/token";
$payload = [
    'code'          => $code,
    'client_id'     => $googleConfig['client_id'],
    'client_secret' => $googleConfig['client_secret'],
    'redirect_uri'  => $googleConfig['redirect_uri_search_console'],
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($response, true);
if ($httpCode !== 200 || empty($tokenData['access_token'])) {
    log_message('error', "Google Search Console OAuth exchange failed", ['response' => $response]);
    echo "Error: Google token exchange failed.";
    exit();
}

$accessToken = $tokenData['access_token'];
$refreshToken = $tokenData['refresh_token'] ?? null;
$expiresIn = (int)($tokenData['expires_in'] ?? 3600);
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// 2. Fetch Search Console Sites (verified properties)
$sitesUrl = "https://www.googleapis.com/webmasters/v3/sites";
$ch = curl_init($sitesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
$sitesResponse = curl_exec($ch);
$sitesHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$sitesData = json_decode($sitesResponse, true);
if ($sitesHttpCode !== 200 || empty($sitesData['siteEntry'])) {
    log_message('error', "Failed to retrieve Search Console sites", ['response' => $sitesResponse]);
    echo "Error: No Search Console verified properties found for this Google account.";
    exit();
}

// Sort properties: 1st sc-domain:, 2nd https://www., 3rd https://
usort($sitesData['siteEntry'], function($a, $b) {
    $urlA = $a['siteUrl'] ?? '';
    $urlB = $b['siteUrl'] ?? '';
    
    $scoreA = 0;
    $scoreB = 0;
    
    if (strpos($urlA, 'sc-domain:') === 0) $scoreA = 3;
    elseif (strpos($urlA, 'https://www.') === 0) $scoreA = 2;
    elseif (strpos($urlA, 'https://') === 0) $scoreA = 1;
    
    if (strpos($urlB, 'sc-domain:') === 0) $scoreB = 3;
    elseif (strpos($urlB, 'https://www.') === 0) $scoreB = 2;
    elseif (strpos($urlB, 'https://') === 0) $scoreB = 1;
    
    return $scoreB <=> $scoreA;
});

// 3. Find the best matching site URL using the client's registered website_url
$stmt = $pdo->prepare("SELECT website_url FROM clients WHERE id = :client_id LIMIT 1");
$stmt->execute(['client_id' => $clientId]);
$clientWebsite = $stmt->fetchColumn();

$selectedSite = '';
if (!empty($clientWebsite)) {
    $parsedClient = parse_url($clientWebsite, PHP_URL_HOST) ?: $clientWebsite;
    $cleanClientHost = preg_replace('/^www\./i', '', $parsedClient);
    
    foreach ($sitesData['siteEntry'] as $site) {
        $siteUrl = $site['siteUrl'] ?? '';
        $parsedSite = parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl;
        if (strpos($siteUrl, 'sc-domain:') === 0) {
            $parsedSite = substr($siteUrl, 10);
        }
        $cleanSiteHost = preg_replace('/^www\./i', '', $parsedSite);
        
        if (strcasecmp($cleanSiteHost, $cleanClientHost) === 0) {
            $selectedSite = $siteUrl;
            break;
        }
    }
}

// Fallback to first site if no match
if (empty($selectedSite) && !empty($sitesData['siteEntry'])) {
    $selectedSite = $sitesData['siteEntry'][0]['siteUrl'] ?? '';
}

if (empty($selectedSite)) {
    echo "Error: No verified properties found in Google Search Console.";
    exit();
}

try {
    $pdo->beginTransaction();

    // A. Insert or update platform connection
    $stmt = $pdo->prepare("
        SELECT id FROM platform_connections 
        WHERE client_id = :client_id AND platform = 'search_console'
        LIMIT 1
    ");
    $stmt->execute(['client_id' => $clientId]);
    $connectionId = $stmt->fetchColumn();

    if ($connectionId) {
        $stmt = $pdo->prepare("
            UPDATE platform_connections 
            SET external_account_id = :external_id, status = 'connected', connected_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([
            'external_id' => $selectedSite,
            'id'          => $connectionId
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO platform_connections (client_id, platform, external_account_id, status)
            VALUES (:client_id, 'search_console', :external_id, 'connected')
        ");
        $stmt->execute([
            'client_id'   => $clientId,
            'external_id' => $selectedSite
        ]);
        $connectionId = $pdo->lastInsertId();
    }

    // B. Store or update tokens
    $encryptedAccessToken = encrypt($accessToken);
    $encryptedRefreshToken = $refreshToken ? encrypt($refreshToken) : null;

    if ($encryptedRefreshToken) {
        $stmt = $pdo->prepare("
            INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, refresh_token_encrypted, expires_at)
            VALUES (:connection_id, :access_token, :refresh_token, :expires_at)
            ON DUPLICATE KEY UPDATE 
                access_token_encrypted = VALUES(access_token_encrypted), 
                refresh_token_encrypted = VALUES(refresh_token_encrypted), 
                expires_at = VALUES(expires_at)
        ");
        $stmt->execute([
            'connection_id' => $connectionId,
            'access_token'  => $encryptedAccessToken,
            'refresh_token' => $encryptedRefreshToken,
            'expires_at'    => $expiresAt
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, expires_at)
            VALUES (:connection_id, :access_token, :expires_at)
            ON DUPLICATE KEY UPDATE access_token_encrypted = VALUES(access_token_encrypted), expires_at = VALUES(expires_at)
        ");
        $stmt->execute([
            'connection_id' => $connectionId,
            'access_token'  => $encryptedAccessToken,
            'expires_at'    => $expiresAt
        ]);
    }

    $pdo->commit();
    log_message('info', "Google Search Console connection successful", ['client_id' => $clientId, 'site_url' => $selectedSite]);

    $dashboardUrl = !empty($stateData['dashboard_url']) ? $stateData['dashboard_url'] : '';
    header("Location: " . HUB_BASE_URL . "/auth/success.php?platform=search_console&dashboard_url=" . urlencode($dashboardUrl));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_message('error', "Google Search Console OAuth callback transaction failed", ['exception' => $e->getMessage()]);
    echo "Error: Database transaction failure.";
    exit();
}
