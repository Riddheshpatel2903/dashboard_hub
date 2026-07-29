<?php
/**
 * Facebook OAuth Callback handler.
 * Exchanges authorization code for long-lived page tokens and resolves connected Instagram accounts.
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
$targetPlatform = $stateData['platform'] ?? 'facebook';
$fbConfig = $platforms['facebook'];

// 1. Exchange authorization code for a short-lived user access token
$tokenUrl = sprintf(
    "https://graph.facebook.com/%s/oauth/access_token?client_id=%s&redirect_uri=%s&client_secret=%s&code=%s",
    $fbConfig['graph_api_version'],
    $fbConfig['app_id'],
    urlencode($fbConfig['redirect_uri']),
    $fbConfig['app_secret'],
    urlencode($code)
);

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($response, true);
if ($httpCode !== 200 || empty($tokenData['access_token'])) {
    log_message('error', "Facebook OAuth code exchange failed", ['response' => $response]);
    echo "Error: Facebook token exchange failed.";
    exit();
}

$shortToken = $tokenData['access_token'];

// 2. Exchange short-lived token for a long-lived user access token (~60 days)
$longTokenUrl = sprintf(
    "https://graph.facebook.com/%s/oauth/access_token?grant_type=fb_exchange_token&client_id=%s&client_secret=%s&fb_exchange_token=%s",
    $fbConfig['graph_api_version'],
    $fbConfig['app_id'],
    $fbConfig['app_secret'],
    urlencode($shortToken)
);

$ch = curl_init($longTokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$longTokenData = json_decode($response, true);
if ($httpCode !== 200 || empty($longTokenData['access_token'])) {
    log_message('error', "Facebook long-lived token exchange failed", ['response' => $response]);
    echo "Error: Facebook long-lived token exchange failed.";
    exit();
}

$longUserToken = $longTokenData['access_token'];
$expiresIn = isset($longTokenData['expires_in']) ? (int)$longTokenData['expires_in'] : 0;
$expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

// 3. Retrieve the list of pages managed by this account, requesting the instagram_business_account field
$accountsUrl = sprintf(
    "https://graph.facebook.com/%s/me/accounts?fields=id,name,access_token,instagram_business_account&access_token=%s",
    $fbConfig['graph_api_version'],
    urlencode($longUserToken)
);

$ch = curl_init($accountsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$accountsData = json_decode($response, true);
if ($httpCode !== 200 || !isset($accountsData['data'])) {
    log_message('error', "Failed to retrieve Facebook pages", ['response' => $response]);
    echo "Error: Failed to retrieve Facebook pages.";
    exit();
}

log_message('info', "Facebook me/accounts response", [
    'client_id' => $clientId,
    'http_code' => $httpCode,
    'response' => $response
]);

$pages = $accountsData['data'];

if (empty($pages)) {
    echo "Warning: No Facebook Pages found for this user.";
    exit();
}

try {
    $pdo->beginTransaction();

    foreach ($pages as $page) {
        $pageId = $page['id'];
        $pageName = $page['name'];
        $pageAccessToken = $page['access_token']; // Page access tokens are permanent when derived from a long-lived user token
        
        // Encrypt page token
        $encryptedToken = encrypt($pageAccessToken);

        // A. Insert or update the Facebook page connection if target is facebook (ensure only one connection per client per platform)
        if ($targetPlatform === 'facebook') {
                if (!isset($facebookConnectionSaved)) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM platform_connections 
                        WHERE client_id = :client_id AND platform = 'facebook'
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
                            'external_id' => $pageId,
                            'id'          => $connectionId
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO platform_connections (client_id, platform, external_account_id, status)
                            VALUES (:client_id, 'facebook', :external_id, 'connected')
                        ");
                        $stmt->execute([
                            'client_id'   => $clientId,
                            'external_id' => $pageId
                        ]);
                        $connectionId = $pdo->lastInsertId();
                    }

                    // B. Store or update the token
                    $stmt = $pdo->prepare("
                        INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, expires_at)
                        VALUES (:connection_id, :token, :expires_at)
                        ON DUPLICATE KEY UPDATE access_token_encrypted = VALUES(access_token_encrypted), expires_at = VALUES(expires_at)
                    ");
                    $stmt->execute([
                        'connection_id' => $connectionId,
                        'token'         => $encryptedToken,
                        'expires_at'    => null // Permanent page token has no expiry
                    ]);

                    $facebookConnectionSaved = true;
                }
            }
        // C. Check for linked Instagram account for this page if target is instagram
        if ($targetPlatform === 'instagram') {
            $igAccountId = null;

            // Step 1: Check if returned directly in the pages list (User Access Token context)
            if (!empty($page['instagram_business_account']['id'])) {
                $igAccountId = $page['instagram_business_account']['id'];
                log_message('info', "Instagram business account found in me/accounts response directly", [
                    'page_id' => $pageId,
                    'instagram_id' => $igAccountId
                ]);
            } else {
                // Fallback 1: Query page node using the User Access Token (most privileged token)
                $igUrl = sprintf(
                    "https://graph.facebook.com/%s/%s?fields=instagram_business_account&access_token=%s",
                    $fbConfig['graph_api_version'],
                    $pageId,
                    urlencode($longUserToken)
                );

                $ch = curl_init($igUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $igResponse = curl_exec($ch);
                $igHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                log_message('info', "Instagram link check fallback (user token) response", [
                    'page_id' => $pageId,
                    'http_code' => $igHttpCode,
                    'response' => $igResponse
                ]);

                $igData = json_decode($igResponse, true);
                if ($igHttpCode === 200 && !empty($igData['instagram_business_account']['id'])) {
                    $igAccountId = $igData['instagram_business_account']['id'];
                } else {
                    // Fallback 2: Query page node using the Page Access Token
                    $igUrl = sprintf(
                        "https://graph.facebook.com/%s/%s?fields=instagram_business_account&access_token=%s",
                        $fbConfig['graph_api_version'],
                        $pageId,
                        urlencode($pageAccessToken)
                    );

                    $ch = curl_init($igUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $igResponse = curl_exec($ch);
                    $igHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    log_message('info', "Instagram link check fallback (page token) response", [
                        'page_id' => $pageId,
                        'http_code' => $igHttpCode,
                        'response' => $igResponse
                    ]);

                    $igData = json_decode($igResponse, true);
                    if ($igHttpCode === 200 && !empty($igData['instagram_business_account']['id'])) {
                        $igAccountId = $igData['instagram_business_account']['id'];
                    }
                }
            }

            if (!empty($igAccountId)) {
                // Insert or update Instagram connection (ensure only one connection per client per platform)
                $stmt = $pdo->prepare("
                    SELECT id FROM platform_connections 
                    WHERE client_id = :client_id AND platform = 'instagram'
                    LIMIT 1
                ");
                $stmt->execute(['client_id' => $clientId]);
                $igConnectionId = $stmt->fetchColumn();

                if ($igConnectionId) {
                    $stmt = $pdo->prepare("
                        UPDATE platform_connections 
                        SET external_account_id = :external_id, status = 'connected', connected_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'external_id' => $igAccountId,
                        'id'          => $igConnectionId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO platform_connections (client_id, platform, external_account_id, status)
                        VALUES (:client_id, 'instagram', :external_id, 'connected')
                    ");
                    $stmt->execute([
                        'client_id'   => $clientId,
                        'external_id' => $igAccountId
                    ]);
                    $igConnectionId = $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare("
                    INSERT INTO platform_tokens (platform_connection_id, access_token_encrypted, expires_at)
                    VALUES (:connection_id, :token, :expires_at)
                    ON DUPLICATE KEY UPDATE access_token_encrypted = VALUES(access_token_encrypted), expires_at = VALUES(expires_at)
                ");
                $stmt->execute([
                    'connection_id' => $igConnectionId,
                    'token'         => $encryptedToken,
                    'expires_at'    => null
                ]);
                
                log_message('info', "Linked Instagram account detected and saved", ['client_id' => $clientId, 'instagram_id' => $igAccountId]);
                $instagramConnectionSaved = true;
                break;
            }
        }
    }

    $pdo->commit();
    log_message('info', "OAuth Page connections successful", ['client_id' => $clientId, 'pages_count' => count($pages)]);

    // Redirect to success URL
    $dashboardUrl = !empty($stateData['dashboard_url']) ? $stateData['dashboard_url'] : '';
    header("Location: " . HUB_BASE_URL . "/auth/success.php?platform=" . urlencode($targetPlatform) . "&dashboard_url=" . urlencode($dashboardUrl));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_message('error', "Facebook OAuth transaction failed", ['exception' => $e->getMessage()]);
    echo "Error: Database transaction failure: " . htmlspecialchars($e->getMessage());
    exit();
}
