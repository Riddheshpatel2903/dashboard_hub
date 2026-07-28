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
    $params = [
        'platform' => $platform,
        'post_id'  => (int)$postId
    ];
    if ($startDate) $params['start_date'] = $startDate;
    if ($endDate) $params['end_date'] = $endDate;
    if ($externalPostId) $params['external_post_id'] = $externalPostId;

    $endpoint = '/api/analytics.php?' . http_build_query($params);
    return hubRequest($clientId, $endpoint, 'GET');
}

/**
 * Gets local scheduled, queued, and failed posts from the Hub.
 */
function hubGetLocalPosts($clientId) {
    return hubRequest($clientId, '/api/posts.php', 'GET');
}

/**
 * Gets local post details from the Hub database by Hub post ID.
 */
function hubGetLocalPostDetails($clientId, $hubPostId) {
    return hubRequest($clientId, '/api/posts.php?post_id=' . (int)$hubPostId, 'GET');
}

/**
 * Loads all live posts from connected platforms + local Hub scheduled/failed/queued posts.
 * Sorts them descending by date.
 */
function loadAllLivePosts($clientId) {
    $allPosts = [];
    
    // 1. Get local posts from Hub (scheduled, failed, queued)
    $localRes = hubGetLocalPosts($clientId);
    if (!empty($localRes['success']) && is_array($localRes['posts'])) {
        foreach ($localRes['posts'] as $p) {
            $allPosts[] = $p;
        }
    }
    
    // 2. Get active connections to fetch platform posts
    $connRes = hubGetConnectionsStatus($clientId);
    if (!empty($connRes['connections']) && is_array($connRes['connections'])) {
        foreach ($connRes['connections'] as $conn) {
            if ($conn['status'] !== 'connected') continue;
            $platform = $conn['platform'];
            if ($platform === 'whatsapp') continue; // Skip WhatsApp
            
            // Fetch recent posts from Hub analytics API
            $aRes = hubGetAnalytics($clientId, $platform, 0);
            if (!empty($aRes['success']) && is_array($aRes['metrics'])) {
                foreach ($aRes['metrics'] as $m) {
                    $mName = strtolower($m['metric_name']);
                    
                    // Parse based on platform
                    if ($platform === 'facebook' && strpos($mName, 'fb_post_') === 0 && !empty($m['value'])) {
                        $pData = json_decode($m['value'], true);
                        if (!empty($pData['post_id'])) {
                            $allPosts[] = [
                                'id'               => 0,
                                'hub_post_id'      => null,
                                'content'          => $pData['message'] ?? 'Facebook Post',
                                'status'           => 'published',
                                'platform'         => 'facebook',
                                'media_path'       => $pData['media_url'] ?? null,
                                'scheduled_at'     => null,
                                'published_at'     => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'created_at'       => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'external_post_id' => $pData['post_id'],
                                'views_count'      => (int)($pData['views'] ?? 0),
                                'likes_count'      => (int)($pData['likes'] ?? 0),
                                'comments_count'   => (int)($pData['comments'] ?? 0),
                                'duration'         => null
                            ];
                        }
                    } elseif ($platform === 'instagram' && strpos($mName, 'ig_post_') === 0 && !empty($m['value'])) {
                        $pData = json_decode($m['value'], true);
                        if (!empty($pData['media_id'])) {
                            $isVid = (strtoupper($pData['media_type'] ?? '') === 'VIDEO');
                            $allPosts[] = [
                                'id'               => 0,
                                'hub_post_id'      => null,
                                'content'          => $pData['caption'] ?? 'Instagram Post',
                                'status'           => 'published',
                                'platform'         => 'instagram',
                                'media_path'       => $pData['media_url'] ?? null,
                                'scheduled_at'     => null,
                                'published_at'     => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'created_at'       => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'external_post_id' => $pData['media_id'],
                                'views_count'      => (int)($pData['views'] ?? 0),
                                'likes_count'      => (int)($pData['likes'] ?? 0),
                                'comments_count'   => (int)($pData['comments'] ?? 0),
                                'duration'         => $isVid ? '00:00' : 'Image'
                            ];
                        }
                    } elseif ($platform === 'youtube' && strpos($mName, 'yt_video_') === 0 && !empty($m['value'])) {
                        $pData = json_decode($m['value'], true);
                        if (!empty($pData['video_id'])) {
                            $allPosts[] = [
                                'id'               => 0,
                                'hub_post_id'      => null,
                                'content'          => $pData['title'] ?? 'YouTube Video',
                                'status'           => 'published',
                                'platform'         => 'youtube',
                                'media_path'       => $pData['thumbnail_url'] ?? null,
                                'scheduled_at'     => null,
                                'published_at'     => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'created_at'       => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'external_post_id' => $pData['video_id'],
                                'views_count'      => (int)($pData['views'] ?? 0),
                                'likes_count'      => (int)($pData['likes'] ?? 0),
                                'comments_count'   => (int)($pData['comments'] ?? 0),
                                'duration'         => $pData['duration'] ?? null
                            ];
                        }
                    } elseif ($platform === 'google_business' && strpos($mName, 'gbp_post_') === 0 && !empty($m['value'])) {
                        $pData = json_decode($m['value'], true);
                        if (!empty($pData['post_id'])) {
                            $allPosts[] = [
                                'id'               => 0,
                                'hub_post_id'      => null,
                                'content'          => $pData['summary'] ?? 'Google Profile Post',
                                'status'           => 'published',
                                'platform'         => 'google_business',
                                'media_path'       => $pData['media_url'] ?? null,
                                'scheduled_at'     => null,
                                'published_at'     => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'created_at'       => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'external_post_id' => $pData['post_id'],
                                'views_count'      => 0,
                                'likes_count'      => 0,
                                'comments_count'   => 0,
                                'duration'         => null
                            ];
                        }
                    } elseif ($platform === 'linkedin' && strpos($mName, 'linkedin_post_') === 0 && !empty($m['value'])) {
                        $pData = json_decode($m['value'], true);
                        if (!empty($pData['post_id'])) {
                            $allPosts[] = [
                                'id'               => 0,
                                'hub_post_id'      => null,
                                'content'          => $pData['message'] ?? 'LinkedIn Post',
                                'status'           => 'published',
                                'platform'         => 'linkedin',
                                'media_path'       => $pData['media_url'] ?? null,
                                'scheduled_at'     => null,
                                'published_at'     => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'created_at'       => !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s'),
                                'external_post_id' => $pData['post_id'],
                                'views_count'      => 0,
                                'likes_count'      => 0,
                                'comments_count'   => 0,
                                'duration'         => null
                            ];
                        }
                    }
                }
            }
        }
    }
    
    // Sort all posts descending by publication date
    usort($allPosts, function($a, $b) {
        $dateA = $a['published_at'] ?: ($a['scheduled_at'] ?: $a['created_at']);
        $dateB = $b['published_at'] ?: ($b['scheduled_at'] ?: $b['created_at']);
        return strcmp($dateB, $dateA); // Descending
    });
    
    return $allPosts;
}

/**
 * Gets active integration statuses.
 */
function hubGetConnectionsStatus($clientId) {
    return hubRequest($clientId, '/api/connections_status.php', 'GET');
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
    // Bypass local IPv4 port conflict with Herd/Nginx by rewriting localhost to [::1] for server-to-server requests
    $url = preg_replace('/^(https?:\/\/)(localhost|127\.0\.0\.1)(:\d+)?\//i', '$1[::1]$3/', $url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
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
