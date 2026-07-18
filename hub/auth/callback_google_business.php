<?php
/**
 * Google OAuth Callback handler for Google Business Profile.
 * Exchanges authorization code for access and refresh tokens, lists accounts, and registers locations.
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
if (empty($_SESSION['oauth_state_nonce']) || $stateData['nonce'] !== $_SESSION['oauth_state_nonce']) {
    http_response_code(403);
    echo "Error: CSRF validation failed.";
    exit();
}
unset($_SESSION['oauth_state_nonce']);

$clientId = (int)$stateData['client_id'];
$googleConfig = $platforms['google'];

// 1. Exchange OAuth code for tokens
$tokenUrl = "https://oauth2.googleapis.com/token";
$payload = [
    'code'          => $code,
    'client_id'     => $googleConfig['client_id'],
    'client_secret' => $googleConfig['client_secret'],
    'redirect_uri'  => $googleConfig['redirect_uri_business'],
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
    log_message('error', "Google Business OAuth exchange failed", ['response' => $response]);
    echo "Error: Google token exchange failed.";
    exit();
}

$accessToken = $tokenData['access_token'];
$refreshToken = $tokenData['refresh_token'] ?? null;
$expiresIn = (int)($tokenData['expires_in'] ?? 3600);
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// 2. Fetch Business Accounts
$accountsUrl = "https://mybusinessaccountmanagement.googleapis.com/v1/accounts";
$ch = curl_init($accountsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
$accountsResponse = curl_exec($ch);
$accountsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$accountsData = json_decode($accountsResponse, true);
if ($accountsHttpCode !== 200 || empty($accountsData['accounts'])) {
    log_message('error', "Failed to retrieve Google Business accounts", ['response' => $accountsResponse]);
    echo "Error: Failed to retrieve Google Business accounts.";
    exit();
}

$locationsFound = [];

// 3. For each account, fetch its locations
foreach ($accountsData['accounts'] as $account) {
    $accountName = $account['name']; // Format: accounts/{accountId}
    
    $locationsUrl = sprintf(
        "https://mybusinessbusinessinformation.googleapis.com/v1/%s/locations?readMask=name,title",
        $accountName
    );

    $ch = curl_init($locationsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    $locResponse = curl_exec($ch);
    $locHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $locData = json_decode($locResponse, true);
    if ($locHttpCode === 200 && !empty($locData['locations'])) {
        foreach ($locData['locations'] as $loc) {
            // $loc['name'] is of the form "locations/{locationId}"
            $locationsFound[] = [
                'id'    => $loc['name'],
                'title' => $loc['title'] ?? 'Unnamed Location'
            ];
        }
    }
}

if (empty($locationsFound)) {
    echo "Warning: No Google Business locations found.";
    exit();
}

try {
    $pdo->beginTransaction();

    foreach ($locationsFound as $loc) {
        $locationId = $loc['id']; // "locations/{locationId}"

        // A. Insert or update platform connection
        $stmt = $pdo->prepare("
            INSERT INTO platform_connections (client_id, platform, external_account_id, status)
            VALUES (:client_id, 'google_business', :external_id, 'connected')
            ON DUPLICATE KEY UPDATE status = 'connected', connected_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'client_id'   => $clientId,
            'external_id' => $locationId
        ]);

        $connectionId = $pdo->lastInsertId();
        if (!$connectionId) {
            $stmt = $pdo->prepare("
                SELECT id FROM platform_connections 
                WHERE client_id = :client_id AND platform = 'google_business' AND external_account_id = :external_id
            ");
            $stmt->execute([
                'client_id'   => $clientId,
                'external_id' => $locationId
            ]);
            $connectionId = $stmt->fetchColumn();
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
    }

    $pdo->commit();
    log_message('info', "Google Business Profile connection successful", ['client_id' => $clientId, 'locations_count' => count($locationsFound)]);

    header("Location: " . HUB_BASE_URL . "/auth/success.php?platform=google_business");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_message('error', "Google Business OAuth callback transaction failed", ['exception' => $e->getMessage()]);
    echo "Error: Database transaction failure.";
    exit();
}
