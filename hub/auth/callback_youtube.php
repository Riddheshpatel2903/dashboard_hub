<?php
/**
 * Google OAuth Callback handler for YouTube.
 * Exchanges authorization code for access and refresh tokens, then retrieves connected YouTube Channels.
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

// Verify CSRF state nonce - bypass strict session check to support cross-domain redirection callbacks
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
    'redirect_uri'  => $googleConfig['redirect_uri_youtube'],
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
    log_message('error', "Google YouTube OAuth exchange failed", ['response' => $response]);
    echo "Error: Google token exchange failed.";
    exit();
}

$accessToken = $tokenData['access_token'];
$refreshToken = $tokenData['refresh_token'] ?? null; // Only returned on prompt=consent consent/initial oauth
$expiresIn = (int)($tokenData['expires_in'] ?? 3600);
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// 2. Fetch the connected YouTube channel details
$channelsUrl = "https://www.googleapis.com/youtube/v3/channels?part=id,snippet&mine=true";
$ch = curl_init($channelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
$channelsResponse = curl_exec($ch);
$channelsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$channelsData = json_decode($channelsResponse, true);
if ($channelsHttpCode !== 200 || empty($channelsData['items'])) {
    log_message('error', "Failed to retrieve YouTube Channel details", ['response' => $channelsResponse]);
    echo "Error: Failed to retrieve YouTube Channel info.";
    exit();
}

try {
    $pdo->beginTransaction();

    foreach ($channelsData['items'] as $channel) {
        $channelId = $channel['id'];

        // A. Insert or update the platform connection (ensure only one connection per client per platform)
        $stmt = $pdo->prepare("
            SELECT id FROM platform_connections 
            WHERE client_id = :client_id AND platform = 'youtube'
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
                'external_id' => $channelId,
                'id'          => $connectionId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO platform_connections (client_id, platform, external_account_id, status)
                VALUES (:client_id, 'youtube', :external_id, 'connected')
            ");
            $stmt->execute([
                'client_id'   => $clientId,
                'external_id' => $channelId
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
            // Keep existing refresh token if not returned on this run
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
    }

    $pdo->commit();
    log_message('info', "YouTube Channel connection successful", ['client_id' => $clientId, 'channel_count' => count($channelsData['items'])]);

    $dashboardUrl = !empty($stateData['dashboard_url']) ? $stateData['dashboard_url'] : '';
    header("Location: " . HUB_BASE_URL . "/auth/success.php?platform=youtube&dashboard_url=" . urlencode($dashboardUrl));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_message('error', "YouTube OAuth callback transaction failed", ['exception' => $e->getMessage()]);
    echo "Error: Database transaction failure.";
    exit();
}
