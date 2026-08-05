<?php
/**
 * OAuth Token Refresh Helper.
 * Handles checks for token expiration and automatically requests new access tokens from Google.
 */

require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/logger.php';

/**
 * Gets a valid decrypted access token for a platform connection.
 * If the token is expired (or close to it) and a refresh token is available, it automatically refreshes it.
 *
 * @param PDO    $pdo
 * @param int    $clientId
 * @param string $platform
 * @return string|null Decrypted access token, or null on failure
 */
function get_valid_platform_token(PDO $pdo, int $clientId, string $platform) {
    // 1. Fetch connection and token details
    $stmt = $pdo->prepare("
        SELECT pc.id AS connection_id, pt.id AS token_id, pt.access_token_encrypted, pt.refresh_token_encrypted, pt.expires_at
        FROM platform_connections pc
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
        LIMIT 1
    ");
    $stmt->execute([
        'client_id' => $clientId,
        'platform'  => $platform
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $accessToken = decrypt($row['access_token_encrypted']);
    $refreshToken = !empty($row['refresh_token_encrypted']) ? decrypt($row['refresh_token_encrypted']) : null;
    $expiresAt = $row['expires_at'];

    // 2. Check if expired or expiring within 5 minutes (300 seconds)
    $isExpired = false;
    if ($expiresAt) {
        $expiresTimestamp = strtotime($expiresAt);
        if ($expiresTimestamp - time() < 300) {
            $isExpired = true;
        }
    } else {
        // If expires_at is null, assume it doesn't expire (e.g. Facebook long-lived token) unless it's Google
        if ($platform === 'youtube' || $platform === 'google_business') {
            $isExpired = true; // Google tokens always expire in 1 hour
        }
    }

    if ($isExpired && $refreshToken && ($platform === 'youtube' || $platform === 'google_business')) {
        // Refresh token
        try {
            $platformsConfig = require __DIR__ . '/../config/platforms.php';
            $googleConfig = $platformsConfig['google'] ?? null;
            if (!$googleConfig) {
                throw new Exception("Google platform config not found.");
            }

            $tokenUrl = "https://oauth2.googleapis.com/token";
            $payload = [
                'client_id'     => $googleConfig['client_id'],
                'client_secret' => $googleConfig['client_secret'],
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token'
            ];

            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $tokenData = json_decode($response, true);
            if ($httpCode === 200 && !empty($tokenData['access_token'])) {
                $newAccessToken = $tokenData['access_token'];
                $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
                $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                $encryptedNewAccessToken = encrypt($newAccessToken);

                // Update database
                $updateStmt = $pdo->prepare("
                    UPDATE platform_tokens 
                    SET access_token_encrypted = :access_token, expires_at = :expires_at
                    WHERE id = :token_id
                ");
                $updateStmt->execute([
                    'access_token' => $encryptedNewAccessToken,
                    'expires_at'    => $newExpiresAt,
                    'token_id'      => $row['token_id']
                ]);

                log_message('info', "Successfully refreshed Google OAuth token for client {$clientId} on {$platform}.");
                return $newAccessToken;
            } else {
                log_message('error', "Failed to refresh Google OAuth token for client {$clientId} on {$platform}. Response: " . $response);
            }
        } catch (Exception $e) {
            log_message('error', "Exception during token refresh for client {$clientId} on {$platform}: " . $e->getMessage());
        }
    }

    if ($isExpired && $refreshToken && $platform === 'linkedin') {
        // Refresh token for LinkedIn
        try {
            $platformsConfig = require __DIR__ . '/../config/platforms.php';
            $liConfig = $platformsConfig['linkedin'] ?? null;
            if (!$liConfig) {
                throw new Exception("LinkedIn platform config not found.");
            }

            $tokenUrl = "https://www.linkedin.com/oauth/v2/accessToken";
            $payload = [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $liConfig['client_id'],
                'client_secret' => $liConfig['client_secret']
            ];

            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $tokenData = json_decode($response, true);
            if ($httpCode === 200 && !empty($tokenData['access_token'])) {
                $newAccessToken = $tokenData['access_token'];
                $expiresIn = (int)($tokenData['expires_in'] ?? 5184000);
                $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                $encryptedNewAccessToken = encrypt($newAccessToken);
                $newRefreshToken = $tokenData['refresh_token'] ?? null;
                $encryptedNewRefreshToken = $newRefreshToken ? encrypt($newRefreshToken) : null;

                if ($encryptedNewRefreshToken) {
                    $updateStmt = $pdo->prepare("
                        UPDATE platform_tokens 
                        SET access_token_encrypted = :access_token, refresh_token_encrypted = :refresh_token, expires_at = :expires_at
                        WHERE id = :token_id
                    ");
                    $updateStmt->execute([
                        'access_token'  => $encryptedNewAccessToken,
                        'refresh_token' => $encryptedNewRefreshToken,
                        'expires_at'     => $newExpiresAt,
                        'token_id'       => $row['token_id']
                    ]);
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE platform_tokens 
                        SET access_token_encrypted = :access_token, expires_at = :expires_at
                        WHERE id = :token_id
                    ");
                    $updateStmt->execute([
                        'access_token' => $encryptedNewAccessToken,
                        'expires_at'    => $newExpiresAt,
                        'token_id'      => $row['token_id']
                    ]);
                }

                log_message('info', "Successfully refreshed LinkedIn OAuth token for client {$clientId}.");
                return $newAccessToken;
            } else {
                log_message('error', "Failed to refresh LinkedIn OAuth token for client {$clientId}. Response: " . $response);
            }
        } catch (Exception $e) {
            log_message('error', "Exception during LinkedIn token refresh for client {$clientId}: " . $e->getMessage());
        }
    }

    return $accessToken;
}

/**
 * Ensures a Facebook post ID is composite formatted as {page_id}_{post_id}
 *
 * @param PDO    $pdo
 * @param int    $clientId
 * @param string $externalId
 * @return string
 */
function ensureFacebookCompositeId(PDO $pdo, int $clientId, $externalId) {
    if (empty($externalId) || strpos($externalId, '_') !== false) {
        return $externalId;
    }
    try {
        $connStmt = $pdo->prepare("SELECT external_account_id FROM platform_connections WHERE client_id = :client_id AND platform = 'facebook' AND status = 'connected' LIMIT 1");
        $connStmt->execute(['client_id' => $clientId]);
        $pageId = $connStmt->fetchColumn();
        if ($pageId) {
            return $pageId . '_' . $externalId;
        }
    } catch (Exception $e) {
        // Ignore
    }
    return $externalId;
}
