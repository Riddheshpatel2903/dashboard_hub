<?php
/**
 * Dashboard decoupled HTTP client.
 * Exclusively handles API communication with the Hub.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db/connection.php';

/**
 * Perform a request to the Hub API using a client's API Key.
 */
function hubRequest($clientId, $endpoint, $method = 'GET', array $data = []) {
    $pdo = require __DIR__ . '/../db/connection.php';
    
    // Look up the Hub API key for this client
    $stmt = $pdo->prepare("
        SELECT hub_api_key FROM client_hub_keys 
        WHERE client_id = :client_id 
        LIMIT 1
    ");
    $stmt->execute(['client_id' => $clientId]);
    $apiKey = $stmt->fetchColumn();

    if (!$apiKey) {
        return [
            'success' => false,
            'error'   => 'Hub API Key not configured for Client ID ' . $clientId
        ];
    }

    $url = HUB_BASE_URL . $endpoint;
    return executeCurl($url, $method, $data, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
}

/**
 * Dispatches a post creation request to the Hub.
 */
function hubPost($clientId, $platform, $content, $mediaTempPath = null, array $additional = []) {
    $payload = array_merge([
        'platform'        => $platform,
        'content'         => $content,
        'media_temp_path' => $mediaTempPath
    ], $additional);

    return hubRequest($clientId, '/api/post.php', 'POST', $payload);
}

/**
 * Edits a post on the Hub.
 */
function hubEdit($clientId, $postId, $content, $title = '') {
    $payload = [
        'post_id' => (int)$postId,
        'content' => $content,
        'title'   => $title
    ];
    return hubRequest($clientId, '/api/edit.php', 'POST', $payload);
}

/**
 * Deletes a post from the Hub.
 */
function hubDelete($clientId, $postId, $platform = null, $externalPostId = null) {
    $payload = [
        'post_id' => (int)$postId
    ];
    if ($platform) {
        $payload['platform'] = $platform;
    }
    if ($externalPostId) {
        $payload['external_post_id'] = $externalPostId;
    }
    return hubRequest($clientId, '/api/delete.php', 'POST', $payload);
}

/**
 * Gets analytics for a client.
 */
function hubGetAnalytics($clientId, $platform, $postId = 0, $startDate = null, $endDate = null, $externalPostId = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $params = [
        'platform' => $platform,
        'post_id'  => (int)$postId
    ];
    if ($startDate) $params['start_date'] = $startDate;
    if ($endDate) $params['end_date'] = $endDate;
    if ($externalPostId) $params['external_post_id'] = $externalPostId;

    $cacheKey = 'analytics_' . $clientId . '_' . md5(serialize($params));
    $now = time();

    $bypassCache = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);

    if (!$bypassCache && isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_expires']) && $_SESSION[$cacheKey . '_expires'] > $now) {
        return $_SESSION[$cacheKey];
    }

    $endpoint = '/api/analytics.php?' . http_build_query($params);
    $res = hubRequest($clientId, $endpoint, 'GET');

    if (!empty($res['success'])) {
        $_SESSION[$cacheKey] = $res;
        $_SESSION[$cacheKey . '_expires'] = $now + 300; // 5 minutes cache
    }

    return $res;
}

/**
 * Gets local and platform posts from the Hub.
 * If $forceSync is true, also syncs results into the Dashboard's own posts_cache.
 */
function hubGetPlatformPosts($clientId, $limit = 100, $forceSync = false) {
    $url = '/api/posts.php?include_platform=1&limit=' . (int)$limit;
    if ($forceSync) {
        $url .= '&force_sync=1';
    }
    $response = hubRequest($clientId, $url, 'GET');

    // A3: After a force_sync, write the fresh platform posts into the Dashboard's own
    // posts_cache using the Dashboard's already-working DB connection.
    // This eliminates the need for a separate cross-database cron job.
    if ($forceSync && isset($response['posts']) && is_array($response['posts'])) {
        try {
            syncPostsCacheFromHubResponse($clientId, $response['posts']);
        } catch (Exception $cacheEx) {
            // Non-fatal — log but don't break the response
            error_log('[hub_client] posts_cache sync failed: ' . $cacheEx->getMessage());
        }
    }

    return $response;
}

/**
 * A3: Sync fresh platform posts into the Dashboard's own posts_cache table.
 * Called immediately after a successful force_sync so the Calendar/Analytics pages
 * see the same data as Post History without needing a separate cron.
 */
function syncPostsCacheFromHubResponse($clientId, array $platformPosts) {
    $dashPdo = require __DIR__ . '/../db/connection.php';

    // Get connected platforms for this client to ensure we clear the cache even if a platform has zero posts
    $platforms = [];
    try {
        $connStatus = hubGetConnectionsStatus($clientId);
        if (!empty($connStatus['connections']) && is_array($connStatus['connections'])) {
            foreach ($connStatus['connections'] as $conn) {
                if (!empty($conn['platform']) && strtolower($conn['status']) === 'connected') {
                    $platforms[] = strtolower($conn['platform']);
                }
            }
        }
    } catch (Exception $e) {
        // Fallback
    }

    // Merge with any platforms present in the posts array just in case
    $postPlatforms = array_unique(array_filter(array_column($platformPosts, 'platform')));
    $platforms = array_unique(array_merge($platforms, $postPlatforms));

    foreach ($platforms as $platform) {
        if (empty($platform)) continue;
        
        // Completely clear the cache of published, failed, and pending_delete posts for this platform
        // This ensures the local database is rewritten with the fresh live portal data only
        $del = $dashPdo->prepare("
            DELETE FROM posts_cache 
            WHERE client_id = :client_id 
              AND platform = :platform 
              AND status IN ('published', 'failed', 'pending_delete')
        ");
        $del->execute([
            'client_id' => $clientId,
            'platform'  => $platform
        ]);
    }

    $insert = $dashPdo->prepare("
        INSERT INTO posts_cache
            (hub_post_id, client_id, content, status, platform, media_path,
             scheduled_at, published_at, external_post_id, likes_count, comments_count, views_count,
             shares_count, impressions_count, reach_count, clicks_count, engagement_count)
        VALUES
            (:hub_post_id, :client_id, :content, :status, :platform, :media_path,
             :scheduled_at, :published_at, :external_post_id, :likes_count, :comments_count, :views_count,
             :shares_count, :impressions_count, :reach_count, :clicks_count, :engagement_count)
        ON DUPLICATE KEY UPDATE
            hub_post_id       = COALESCE(VALUES(hub_post_id), hub_post_id),
            content           = VALUES(content),
            status            = VALUES(status),
            media_path        = VALUES(media_path),
            scheduled_at      = VALUES(scheduled_at),
            published_at      = VALUES(published_at),
            likes_count       = VALUES(likes_count),
            comments_count    = VALUES(comments_count),
            views_count       = VALUES(views_count),
            shares_count      = VALUES(shares_count),
            impressions_count = VALUES(impressions_count),
            reach_count       = VALUES(reach_count),
            clicks_count      = VALUES(clicks_count),
            engagement_count  = VALUES(engagement_count)
    ");

    foreach ($platformPosts as $post) {
        if (empty($post['platform'])) continue;
        
        $m = is_array($post['metrics'] ?? null) ? $post['metrics'] : [];
        $viewsCount = (int)($post['views_count'] ?? $m['views'] ?? $m['view_count'] ?? $m['impressions'] ?? $m['reach'] ?? 0);
        $likesCount = (int)($post['likes_count'] ?? $m['likes'] ?? $m['like_count'] ?? 0);
        $commentsCount = (int)($post['comments_count'] ?? $m['comments'] ?? $m['comment_count'] ?? 0);

        $insert->execute([
            'hub_post_id'       => !empty($post['hub_post_id']) ? (int)$post['hub_post_id'] : null,
            'client_id'         => $clientId,
            'content'           => $post['content'] ?? '',
            'status'            => $post['status'] ?? 'published',
            'platform'          => $post['platform'],
            'media_path'        => $post['media_path'] ?? null,
            'scheduled_at'      => $post['scheduled_at'] ?? null,
            'published_at'      => $post['published_at'] ?? null,
            'external_post_id'  => !empty($post['external_post_id']) ? $post['external_post_id'] : null,
            'likes_count'       => $likesCount,
            'comments_count'    => $commentsCount,
            'views_count'       => $viewsCount,
            'shares_count'      => (int)($m['shares'] ?? $post['shares_count'] ?? 0),
            'impressions_count' => (int)($m['impressions'] ?? $viewsCount ?? $post['impressions'] ?? 0),
            'reach_count'       => (int)($m['reach'] ?? $post['reach'] ?? 0),
            'clicks_count'      => (int)($m['clicks'] ?? $post['clicks'] ?? 0),
            'engagement_count'  => (int)($m['engagement'] ?? $post['engagement'] ?? 0),
        ]);
    }
    // Auto clean up failed posts from cache table
    $dashPdo->prepare("DELETE FROM posts_cache WHERE client_id = :client_id AND status = 'failed'")->execute(['client_id' => $clientId]);
}



/**
 * Gets local scheduled, queued, and failed posts from the Hub.
 */
function hubGetLocalPosts($clientId) {
    return hubRequest($clientId, '/api/posts.php?include_platform=0', 'GET');
}

/**
 * Gets local post details from the Hub database by Hub post ID.
 */
function hubGetLocalPostDetails($clientId, $hubPostId) {
    return hubRequest($clientId, '/api/posts.php?post_id=' . (int)$hubPostId, 'GET');
}

/**
 * Loads platform posts from the Hub posts endpoint.
 * This replaces the old analytics-based reconstruction logic.
 */
function loadPlatformPosts($clientId, $forceSync = false) {
    $dashPdo = null;
    try {
        $dashPdo = require __DIR__ . '/../db/connection.php';
    } catch (Exception $e) {
        // Ignore and proceed
    }

    if (!$forceSync && $dashPdo) {
        // Time-based cache expiry: automatically sync if cache is empty or older than 15 minutes
        try {
            // Auto clean up failed posts from cache table so they don't show up in calendar or history
            $dashPdo->prepare("DELETE FROM posts_cache WHERE client_id = :client_id AND status = 'failed'")->execute(['client_id' => $clientId]);

            $stmt = $dashPdo->prepare("SELECT MAX(created_at) FROM posts_cache WHERE client_id = :client_id");
            $stmt->execute(['client_id' => $clientId]);
            $lastSync = $stmt->fetchColumn();
            
            $cacheExpired = true;
            if ($lastSync) {
                $lastSyncTs = strtotime($lastSync);
                // Expire cache after 15 minutes (900 seconds)
                if ((time() - $lastSyncTs) < 900) {
                    $cacheExpired = false;
                }
            }
            
            if ($cacheExpired) {
                $forceSync = true;
            }
        } catch (Exception $e) {
            // Proceed with fallback reading from DB if cache check fails
        }
    }

    if (!$forceSync) {
        // Read directly from local posts_cache to save network roundtrips!
        try {
            $stmt = $dashPdo->prepare("
                SELECT hub_post_id, platform, content, media_path, status, 
                       scheduled_at, published_at, external_post_id, 
                       likes_count, comments_count, views_count, 
                       shares_count, impressions_count, reach_count, clicks_count, engagement_count,
                       id, id as post_id, created_at
                FROM posts_cache
                WHERE client_id = :client_id
                ORDER BY COALESCE(published_at, scheduled_at, created_at) DESC
            ");
            $stmt->execute(['client_id' => $clientId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    $res = hubGetPlatformPosts($clientId, 5000, $forceSync);
    if (!empty($res['platform_errors'])) {
        $GLOBALS['platform_errors'] = $res['platform_errors'];
    }
    if (!empty($res['success']) && is_array($res['posts'])) {
        $posts = [];
        foreach ($res['posts'] as $post) {
            if (!empty($post['published_at'])) {
                $post['published_at'] = date('Y-m-d H:i:s', strtotime($post['published_at']));
            }
            if (!empty($post['created_at'])) {
                $post['created_at'] = date('Y-m-d H:i:s', strtotime($post['created_at']));
            }
            if (!empty($post['scheduled_at'])) {
                $post['scheduled_at'] = date('Y-m-d H:i:s', strtotime($post['scheduled_at']));
            }

            if (empty($post['views_count']) || empty($post['likes_count']) || empty($post['comments_count'])) {
                $metrics = is_array($post['metrics'] ?? null) ? $post['metrics'] : [];
                if (empty($post['views_count'])) {
                    $post['views_count'] = isset($metrics['views']) ? (int)$metrics['views'] : (isset($metrics['view_count']) ? (int)$metrics['view_count'] : (isset($metrics['impressions']) ? (int)$metrics['impressions'] : (isset($metrics['reach']) ? (int)$metrics['reach'] : 0)));
                }
                if (empty($post['likes_count'])) {
                    $post['likes_count'] = isset($metrics['likes']) ? (int)$metrics['likes'] : (isset($metrics['like_count']) ? (int)$metrics['like_count'] : 0);
                }
                if (empty($post['comments_count'])) {
                    $post['comments_count'] = isset($metrics['comments']) ? (int)$metrics['comments'] : (isset($metrics['comment_count']) ? (int)$metrics['comment_count'] : 0);
                }
            }

            // Auto-heal local dashboard posts_cache status if Hub returned it as published or it has an external_post_id
            if (!empty($post['hub_post_id'])) {
                try {
                    $dashPdo = require __DIR__ . '/../db/connection.php';
                    $checkStmt = $dashPdo->prepare("SELECT id, status, external_post_id FROM posts_cache WHERE hub_post_id = :hub_post_id LIMIT 1");
                    $checkStmt->execute(['hub_post_id' => $post['hub_post_id']]);
                    $cached = $checkStmt->fetch();
                    if ($cached) {
                        $needsUpdate = false;
                        $updateParams = ['hub_post_id' => $post['hub_post_id']];
                        
                        if ($cached['status'] !== 'published' && ($post['status'] === 'published' || !empty($post['external_post_id']))) {
                            $needsUpdate = true;
                            $updateParams['status'] = 'published';
                            $updateParams['published_at'] = !empty($post['published_at']) ? $post['published_at'] : date('Y-m-d H:i:s');
                        }
                        if (empty($cached['external_post_id']) && !empty($post['external_post_id'])) {
                            $needsUpdate = true;
                            $updateParams['external_post_id'] = $post['external_post_id'];
                        }
                        
                        if ($needsUpdate) {
                            $sql = "UPDATE posts_cache SET ";
                            $sets = [];
                            if (isset($updateParams['status'])) $sets[] = "status = :status";
                            if (isset($updateParams['published_at'])) $sets[] = "published_at = :published_at";
                            if (isset($updateParams['external_post_id'])) $sets[] = "external_post_id = :external_post_id";
                            $sql .= implode(', ', $sets);
                            $sql .= " WHERE hub_post_id = :hub_post_id";
                            
                            $updStmt = $dashPdo->prepare($sql);
                            $updStmt->execute($updateParams);
                        }
                    }
                } catch (Exception $dbEx) {
                    // Ignore local cache update failures
                }
            }

            $posts[] = $post;
        }

        usort($posts, function ($a, $b) {
            $dateA = $a['published_at'] ?: ($a['scheduled_at'] ?: $a['created_at']);
            $dateB = $b['published_at'] ?: ($b['scheduled_at'] ?: $b['created_at']);
            return strcmp($dateB, $dateA);
        });

        return $posts;
    }

    if (!empty($res['error'])) {
        throw new Exception($res['error']);
    }

    return [];
}

function loadAllLivePosts($clientId) {
    return loadPlatformPosts($clientId);
}

/**
 * Gets active integration statuses.
 */
function hubGetConnectionsStatus($clientId) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $cacheKey = 'connections_status_' . $clientId;
    $now = time();
    
    $bypassCache = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);
    
    if (!$bypassCache && isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_expires']) && $_SESSION[$cacheKey . '_expires'] > $now) {
        return $_SESSION[$cacheKey];
    }
    
    $status = hubRequest($clientId, '/api/connections_status.php', 'GET');
    
    if (!empty($status['success'])) {
        $_SESSION[$cacheKey] = $status;
        $_SESSION[$cacheKey . '_expires'] = $now + 300; // 5 minutes cache
    }
    
    return $status;
}

/**
 * Retrieves comments or reviews inbox data.
 */
function hubGetInbox($clientId, $platform, $type, $postId = 0, $senderNumber = null) {
    $params = [
        'platform' => $platform,
        'type'     => $type,
        'post_id'  => (int)$postId
    ];
    if ($senderNumber) $params['sender_number'] = $senderNumber;

    $endpoint = '/api/inbox.php?' . http_build_query($params);
    return hubRequest($clientId, $endpoint, 'GET');
}

/**
 * Uploads a file to the Hub's server-side storage service.
 */
function hubUploadFile($clientId, $localFilePath, $fileName, $mimeType) {
    $pdo = require __DIR__ . '/../db/connection.php';
    
    $stmt = $pdo->prepare("
        SELECT hub_api_key FROM client_hub_keys 
        WHERE client_id = :client_id 
        LIMIT 1
    ");
    $stmt->execute(['client_id' => $clientId]);
    $apiKey = $stmt->fetchColumn();

    if (!$apiKey) {
        return [
            'success' => false,
            'error'   => 'Hub API Key not configured for Client ID ' . $clientId
        ];
    }

    $url = HUB_BASE_URL . '/api/upload.php';
    
    // PHP cURL file upload requires CURLFile
    $cfile = new CURLFile($localFilePath, $mimeType, $fileName);
    $payload = ['media' => $cfile];

    return executeCurl($url, 'POST', $payload, [
        'X-API-Key: ' . $apiKey
    ], false); // Don't JSON encode the multipart file upload
}

/**
 * Registers a new client on the Hub (Admin tool).
 */
function hubRegisterClient($name, $websiteUrl) {
    $url = HUB_BASE_URL . '/api/clients.php';
    $payload = [
        'name'        => $name,
        'website_url' => $websiteUrl
    ];

    return executeCurl($url, 'POST', $payload, [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY,
        'Content-Type: application/json'
    ]);
}

/**
 * Updates an existing client details in the Hub (Admin tool).
 */
function hubUpdateClient($clientId, $name, $websiteUrl) {
    $url = HUB_BASE_URL . '/api/clients.php';
    $payload = [
        'action'      => 'update',
        'client_id'   => (int)$clientId,
        'name'        => $name,
        'website_url' => $websiteUrl
    ];

    return executeCurl($url, 'POST', $payload, [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY,
        'Content-Type: application/json'
    ]);
}

/**
 * Fetches client profile details from the Hub (Admin tool).
 */
function hubGetClient($clientId) {
    $url = HUB_BASE_URL . '/api/clients.php?client_id=' . (int)$clientId;
    return executeCurl($url, 'GET', [], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Sends a POST request to permanently delete a client and all associated data from the Hub.
 */
function hubDeleteClient($clientId) {
    $url = HUB_BASE_URL . '/api/clients.php';
    return executeCurl($url, 'POST', [
        'action'    => 'delete',
        'client_id' => (int)$clientId
    ], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Sends a POST request to extend a client's subscription by 1 year.
 */
function hubExtendClient($clientId) {
    $url = HUB_BASE_URL . '/api/clients.php';
    return executeCurl($url, 'POST', [
        'action'    => 'extend',
        'client_id' => (int)$clientId
    ], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Sends a POST request to change a client's status (active/inactive).
 */
function hubToggleClientStatus($clientId, $status) {
    $url = HUB_BASE_URL . '/api/clients.php';
    return executeCurl($url, 'POST', [
        'action'    => 'status',
        'status'    => $status,
        'client_id' => (int)$clientId
    ], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Lists all clients onboarded on the Hub (Admin tool).
 */
function hubListClients() {
    $url = HUB_BASE_URL . '/api/clients.php';
    return executeCurl($url, 'GET', [], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Fetches cross-client system health metrics from the Hub (Admin tool).
 */
function hubGetSystemHealth() {
    $url = HUB_BASE_URL . '/api/system_health.php';
    return executeCurl($url, 'GET', [], [
        'X-Hub-Admin-Key: ' . HUB_ADMIN_MASTER_KEY
    ]);
}

/**
 * Disconnects a platform connection on the Hub.
 */
function hubDisconnectConnection($clientId, $platform) {
    return hubRequest($clientId, '/api/disconnect.php', 'POST', ['platform' => $platform]);
}

/**
 * Base Curl dispatcher.
 */
function executeCurl($url, $method, array $data = [], array $headers = [], $jsonEncode = true) {
    // Bypass local IPv4 port conflict with Herd/Nginx by rewriting localhost to [::1] for server-to-server requests (disabled on Windows)
    if (PHP_OS_FAMILY !== 'Windows') {
        $url = preg_replace('/^(https?:\/\/)(localhost|127\.0\.0\.1)(:\d+)?\//i', '$1[::1]$3/', $url);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Disable SSL verification to avoid self-signed cert / local issuer issues in local XAMPP environments
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($jsonEncode) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed = json_decode($response, true);
    if ($httpCode >= 400) {
        return [
            'success' => false,
            'error'   => $parsed['error'] ?? 'HTTP request failed with status code ' . $httpCode,
            'code'    => $httpCode,
            'raw'     => $response
        ];
    }

    // Treat 2xx as success even if JSON can't be parsed (e.g. empty body)
    if (!$parsed) {
        return [
            'success' => false,
            'error'   => 'Invalid or empty JSON response from Hub (HTTP ' . $httpCode . ')',
            'code'    => $httpCode,
            'raw'     => $response
        ];
    }

    return $parsed;
}

function getRelativeTimeString($datetime) {
    if (!$datetime) {
        return 'Never';
    }
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    if ($diff < 0) {
        return 'Just now';
    }
    if ($diff < 60) {
        return 'Just now';
    }
    $mins = round($diff / 60);
    if ($mins < 60) {
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    }
    $hours = round($diff / 3600);
    if ($hours < 24) {
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    }
    $days = round($diff / 86400);
    if ($days < 30) {
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $time);
}

function getOverallLastSyncedTime($connections) {
    $maxTime = null;
    if (is_array($connections)) {
        foreach ($connections as $conn) {
            if ($conn['status'] === 'connected' && $conn['platform'] !== 'whatsapp') {
                if (!empty($conn['last_synced_at'])) {
                    $t = strtotime($conn['last_synced_at']);
                    if ($maxTime === null || $t > $maxTime) {
                        $maxTime = $t;
                    }
                }
            }
        }
    }
    return $maxTime ? date('Y-m-d H:i:s', $maxTime) : null;
}

function hubGetSearchAnalytics($clientId, $startDate = null, $endDate = null, $dimensions = 'date') {
    $params = ['action' => 'search_analytics'];
    if ($startDate) $params['start_date'] = $startDate;
    if ($endDate) $params['end_date'] = $endDate;
    $params['dimensions'] = $dimensions;
    return hubRequest($clientId, '/api/seo.php?' . http_build_query($params), 'GET');
}

function hubGetPageSpeed($clientId, $url, $strategy = 'mobile') {
    return hubRequest($clientId, '/api/seo.php?' . http_build_query([
        'action' => 'pagespeed',
        'url' => $url,
        'strategy' => $strategy,
    ]), 'GET');
}
