<?php
/**
 * LinkedIn OAuth Callback handler.
 * Exchanges authorization code for access tokens and resolves the member's profile URN.
 */

session_start();
$pdo = require_once __DIR__ . '/../db/connection.php';
$platforms = require_once __DIR__ . '/../config/platforms.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

if (isset($_GET['error'])) {
    http_response_code(400);
    echo "Connection was not approved. Error: " . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
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
$liConfig = $platforms['linkedin'];

// 1. Exchange OAuth code for an access token
$tokenUrl = "https://www.linkedin.com/oauth/v2/accessToken";
$payload = [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $liConfig['redirect_uri'],
    'client_id'     => $liConfig['client_id'],
    'client_secret' => $liConfig['client_secret']
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
    log_message('error', "LinkedIn token exchange failed", ['response' => $response]);
    echo "Error: LinkedIn token exchange failed.";
    exit();
}

$accessToken = $tokenData['access_token'];
$expiresIn = (int)($tokenData['expires_in'] ?? 5184000); // Usually 60 days
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// 2. Fetch User Profile URN via OpenID Connect UserInfo API
$userInfoUrl = "https://api.linkedin.com/v2/userinfo";
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
$userResponse = curl_exec($ch);
$userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$userData = json_decode($userResponse, true);
if ($userHttpCode !== 200 || empty($userData['sub'])) {
    log_message('error', "LinkedIn userinfo fetch failed", ['response' => $userResponse]);
    echo "Error: Failed to retrieve LinkedIn profile information.";
    exit();
}

// LinkedIn personal profile URN uses format: urn:li:person:<sub_id>
$authorUrn = 'urn:li:person:' . $userData['sub'];

try {
    $pdo->beginTransaction();

    // A. Insert or update platform connection (ensure only one connection per client per platform)
    $stmt = $pdo->prepare("
        SELECT id FROM platform_connections 
        WHERE client_id = :client_id AND platform = 'linkedin'
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
            'external_id' => $authorUrn,
            'id'          => $connectionId
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO platform_connections (client_id, platform, external_account_id, status)
            VALUES (:client_id, 'linkedin', :external_id, 'connected')
        ");
        $stmt->execute([
            'client_id'   => $clientId,
            'external_id' => $authorUrn
        ]);
        $connectionId = $pdo->lastInsertId();
    }

    // B. Store encrypted access token
    $encryptedToken = encrypt($accessToken);
    $stmt = $pdo->prepare("
        INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, expires_at)
        VALUES (:connection_id, :token, :expires_at)
        ON DUPLICATE KEY UPDATE access_token_encrypted = VALUES(access_token_encrypted), expires_at = VALUES(expires_at)
    ");
    $stmt->execute([
        'connection_id' => $connectionId,
        'token'         => $encryptedToken,
        'expires_at'    => $expiresAt
    ]);

    $pdo->commit();
    log_message('info', "LinkedIn connection successful", ['client_id' => $clientId, 'urn' => $authorUrn]);

    $dashboardUrl = !empty($stateData['dashboard_url']) ? $stateData['dashboard_url'] : '';
    header("Location: " . HUB_BASE_URL . "/auth/success.php?platform=linkedin&dashboard_url=" . urlencode($dashboardUrl));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_message('error', "LinkedIn OAuth callback transaction failed", ['exception' => $e->getMessage()]);
    echo "Error: Database transaction failure.";
    exit();
}
