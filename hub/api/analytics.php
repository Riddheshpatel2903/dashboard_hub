<?php
/**
 * Platform Analytics Endpoint.
 * Endpoint: GET /api/analytics.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/token_helper.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

function ensureAnalyticsCacheTable(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        cache_key VARCHAR(64) NOT NULL,
        response_json LONGTEXT,
        fetched_at DATETIME NOT NULL,
        UNIQUE KEY uniq_cache (client_id, platform, cache_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getCachedOrFetch(PDO $pdo, $clientId, $platform, $cacheKey, callable $fetchFn, $ttlSeconds = 900)
{
    ensureAnalyticsCacheTable($pdo);
    $cacheHash = hash('sha256', $cacheKey);

    $stmt = $pdo->prepare("SELECT response_json, fetched_at FROM analytics_cache WHERE client_id = :client_id AND platform = :platform AND cache_key = :cache_key LIMIT 1");
    $stmt->execute([
        'client_id' => $clientId,
        'platform' => $platform,
        'cache_key' => $cacheHash
    ]);
    $row = $stmt->fetch();

    if ($row && (time() - strtotime($row['fetched_at'])) < $ttlSeconds) {
        return json_decode($row['response_json'], true);
    }

    $result = $fetchFn();

    $upsert = $pdo->prepare("INSERT INTO analytics_cache (client_id, platform, cache_key, response_json, fetched_at)
        VALUES (:client_id, :platform, :cache_key, :response_json, NOW())
        ON DUPLICATE KEY UPDATE response_json = :response_json2, fetched_at = NOW()");
    $encoded = json_encode($result);
    $upsert->execute([
        'client_id' => $clientId,
        'platform' => $platform,
        'cache_key' => $cacheHash,
        'response_json' => $encoded,
        'response_json2' => $encoded
    ]);

    return $result;
}

$platformInput = $_GET['platform'] ?? '';
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$externalPostIdInput = $_GET['external_post_id'] ?? '';

if (empty($platformInput) && $postId <= 0 && empty($externalPostIdInput)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing platform, post_id, or external_post_id parameter']);
    exit();
}

try {
    $platform = $platformInput;
    $externalId = null;
    $token = null;

    if (!empty($externalPostIdInput)) {
        $platform = $platformInput;
        $externalId = $externalPostIdInput;
        if ($platform === 'facebook') {
            $externalId = ensureFacebookCompositeId($pdo, $client_id, $externalId);
        }
        $token = get_valid_platform_token($pdo, $client_id, $platform);
        if (!$token) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'No active connection found for ' . $platform]);
            exit();
        }
        $postId = 1; // Treat as post-level metrics fetching
    } elseif ($postId > 0) {
        // Resolve post's external account / platform
        $stmt = $pdo->prepare("
            SELECT p.external_post_id, pc.platform, pc.external_account_id
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.id = :post_id AND p.client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([
            'post_id'   => $postId,
            'client_id' => $client_id
        ]);
        $postData = $stmt->fetch();
        
        if (!$postData) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Post not found or unauthorized']);
            exit();
        }

        $platform = $postData['platform'];
        $externalId = $postData['external_post_id'];
        if ($platform === 'facebook') {
            $externalId = ensureFacebookCompositeId($pdo, $client_id, $externalId);
        }
        $token = get_valid_platform_token($pdo, $client_id, $platform);
    } else {
        // Get account level metrics by finding the first connection for this client + platform
        $token = get_valid_platform_token($pdo, $client_id, $platform);
        if (!$token) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'No active connection found for ' . $platform]);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT external_account_id
            FROM platform_connections
            WHERE client_id = :client_id AND platform = :platform AND status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $externalId = $stmt->fetchColumn();
    }

    $normalizedMetrics = [];

    // Dispatch and normalize responses
    switch ($platform) {
        case 'facebook':
            if ($postId > 0) {
                // Post-level: fetch likes/comments/shares via post fields (no deprecated Insights metrics)
                try {
                    $engagement = FacebookHandler::getEngagementCounts($token, $externalId);
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'likes',    'value' => (int)($engagement['likes']    ?? 0), 'period' => 'lifetime'];
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'comments', 'value' => (int)($engagement['comments'] ?? 0), 'period' => 'lifetime'];
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'shares',   'value' => (int)($engagement['shares']   ?? 0), 'period' => 'lifetime'];
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Facebook post engagement: " . $e->getMessage());
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'likes',    'value' => 0, 'period' => 'lifetime'];
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'comments', 'value' => 0, 'period' => 'lifetime'];
                    $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'shares',   'value' => 0, 'period' => 'lifetime'];
                }
            } else {
                // Page-level: page views via /me/insights, plus followers/fans via /me account info
                try {
                    $metrics = ['page_views_total'];
                    $raw = FacebookHandler::getPageInsights($token, $metrics, 'day');
                    if (!empty($raw['data'])) {
                        foreach ($raw['data'] as $item) {
                            $name = $item['name'];
                            $period = $item['period'] ?? 'lifetime';
                            $val = 0;
                            if (!empty($item['values'])) {
                                $val = end($item['values'])['value'] ?? 0;
                            }
                            $normalizedMetrics[] = [
                                'platform'    => 'facebook',
                                'metric_name' => $name,
                                'value'       => is_array($val) ? json_encode($val) : $val,
                                'period'      => $period
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Facebook page insights: " . $e->getMessage());
                }

                try {
                    $accInfo = FacebookHandler::getAccountInfo($token, $externalId);
                    if (!empty($accInfo['followers_count'])) {
                        $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'followers_count', 'value' => (int)$accInfo['followers_count'], 'period' => 'lifetime'];
                    }
                    if (!empty($accInfo['fan_count'])) {
                        $normalizedMetrics[] = ['platform' => 'facebook', 'metric_name' => 'fan_count', 'value' => (int)$accInfo['fan_count'], 'period' => 'lifetime'];
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Facebook account info: " . $e->getMessage());
                }
            }
            break;

        case 'instagram':
            if ($postId > 0) {
                $mediaType = 'IMAGE';
                $likeCount = 0;
                $commentCount = 0;
                try {
                    $mediaFields = ['id', 'caption', 'media_type', 'media_url', 'timestamp', 'like_count', 'comments_count'];
                    $mediaDetail = InstagramHandler::getMediaDetails($token, $externalId, $mediaFields);
                    if (!empty($mediaDetail['id'])) {
                        $mediaType = $mediaDetail['media_type'] ?? 'IMAGE';
                        $likeCount = (int)($mediaDetail['like_count'] ?? 0);
                        $commentCount = (int)($mediaDetail['comments_count'] ?? 0);
                        
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'like_count', 'value' => $likeCount, 'period' => 'lifetime'];
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'comment_count', 'value' => $commentCount, 'period' => 'lifetime'];
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram media details: " . $e->getMessage());
                }

                try {
                    $metrics = ['reach', 'saved', 'views'];
                    $raw = InstagramHandler::getInsights($token, $externalId, $metrics);

                    if (!empty($raw['data'])) {
                        foreach ($raw['data'] as $item) {
                            $name = $item['name'];
                            $period = $item['period'] ?? 'lifetime';
                            $val = 0;
                            if (!empty($item['values'])) {
                                $val = end($item['values'])['value'] ?? 0;
                            }
                            $normalizedMetrics[] = [
                                'platform'    => 'instagram',
                                'metric_name' => $name,
                                'value'       => $val,
                                'period'      => $period
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram post insights: " . $e->getMessage());
                }
            } else {
                try {
                    $metrics = ['reach'];
                    $raw = getCachedOrFetch($pdo, $client_id, 'instagram', "instagram_account_insights:{$externalId}:reach:total_value", function () use ($token, $externalId, $metrics) {
                        return InstagramHandler::getInsights($token, $externalId, $metrics, 'day', 'total_value');
                    }, 900);
                    if (!empty($raw['data'])) {
                        foreach ($raw['data'] as $item) {
                            $name = $item['name'];
                            $period = $item['period'] ?? 'lifetime';
                            $val = 0;
                            if (!empty($item['values'])) {
                                $val = end($item['values'])['value'] ?? 0;
                            }
                            $normalizedMetrics[] = [
                                'platform'    => 'instagram',
                                'metric_name' => $name,
                                'value'       => $val,
                                'period'      => $period
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram insights: " . $e->getMessage());
                }

                try {
                    $accInfo = InstagramHandler::getAccountInfo($token, $externalId);
                    if (!empty($accInfo['followers_count'])) {
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'followers_count', 'value' => (int)$accInfo['followers_count'], 'period' => 'lifetime'];
                    }
                    if (!empty($accInfo['follows_count'])) {
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'follows_count', 'value' => (int)$accInfo['follows_count'], 'period' => 'lifetime'];
                    }
                    if (!empty($accInfo['media_count'])) {
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'media_count', 'value' => (int)$accInfo['media_count'], 'period' => 'lifetime'];
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram account info: " . $e->getMessage());
                }

                // Fetch recent media
                try {
                    $recentMediaRaw = InstagramHandler::getRecentMedia($token, $externalId, 50);
                    if (!empty($recentMediaRaw['data'])) {
                        foreach ($recentMediaRaw['data'] as $mItem) {
                            $mId = $mItem['id'] ?? '';
                            if ($mId) {
                                $views = 0;
                                if (!empty($mItem['insights']['data']) && is_array($mItem['insights']['data'])) {
                                    foreach ($mItem['insights']['data'] as $insight) {
                                        if (empty($insight['name']) || empty($insight['values'][0]['value'])) {
                                            continue;
                                        }
                                        $metricValue = (int)$insight['values'][0]['value'];
                                        if ($insight['name'] === 'views' || $insight['name'] === 'reach') {
                                            $views = max($views, $metricValue);
                                        }
                                    }
                                }

                                $normalizedMetrics[] = [
                                    'platform'    => 'instagram',
                                    'metric_name' => 'ig_post_' . $mId,
                                    'value'       => json_encode([
                                        'media_id'     => $mId,
                                        'caption'      => $mItem['caption'] ?? '',
                                        'published_at' => $mItem['timestamp'] ?? '',
                                        'media_url'    => $mItem['media_url'] ?? '',
                                        'media_type'   => $mItem['media_type'] ?? 'IMAGE',
                                        'likes'        => (int)($mItem['like_count'] ?? 0),
                                        'comments'     => (int)($mItem['comments_count'] ?? 0),
                                        'views'        => $views
                                    ]),
                                    'period'      => 'lifetime'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram recent media: " . $e->getMessage());
                }
            }
            break;

        case 'youtube':
            if ($postId > 0) {
                // Try live YouTube Data API v3 video statistics first
                try {
                    $statsRaw = YouTubeHandler::getVideoStats($token, $externalId);
                    if (!empty($statsRaw['items'][0]['statistics'])) {
                        $item = $statsRaw['items'][0];
                        $vStats = $item['statistics'];
                        foreach ($vStats as $name => $val) {
                            $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
                            $normalizedMetrics[] = [
                                'platform'    => 'youtube',
                                'metric_name' => $snakeName,
                                'value'       => (int)$val,
                                'period'      => 'lifetime'
                            ];
                        }
                        if (!empty($item['contentDetails']['duration'])) {
                            $normalizedMetrics[] = [
                                'platform'    => 'youtube',
                                'metric_name' => 'duration',
                                'value'       => $item['contentDetails']['duration'],
                                'period'      => 'lifetime'
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch YouTube video stats: " . $e->getMessage());
                }

                // Try YouTube Analytics API for advanced insights (watch time, CTR, etc.)
                try {
                    $raw = YouTubeHandler::getVideoAnalytics($token, $externalId, $startDate, $endDate);
                    if (!empty($raw['columnHeaders']) && !empty($raw['rows'])) {
                        $headers = array_column($raw['columnHeaders'], 'name');
                        foreach ($raw['rows'] as $row) {
                            foreach ($row as $colIdx => $val) {
                                $metricName = $headers[$colIdx];
                                if ($metricName !== 'video') {
                                    $normalizedMetrics[] = [
                                        'platform'    => 'youtube',
                                        'metric_name' => $metricName,
                                        'value'       => $val,
                                        'period'      => 'range'
                                    ];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch YouTube video analytics: " . $e->getMessage());
                }
            } else {
                try {
                    $raw = YouTubeHandler::getChannelStats($token, $externalId);
                    if (empty($raw['items'])) {
                        $raw = YouTubeHandler::getChannelStats($token, 'mine');
                    }
                    if (!empty($raw['items'][0]['statistics'])) {
                        $stats = $raw['items'][0]['statistics'];
                        foreach ($stats as $name => $val) {
                            if ($name === 'hiddenSubscriberCount') {
                                continue;
                            }
                            
                            $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

                            $normalizedMetrics[] = [
                                'platform'    => 'youtube',
                                'metric_name' => $snakeName,
                                'value'       => (int)$val,
                                'period'      => 'lifetime'
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch YouTube channel stats: " . $e->getMessage());
                }

            }
            break;

        case 'google_business':
            $range = [];
            if ($startDate && $endDate) {
                $range = [
                    'start_year'  => (int)date('Y', strtotime($startDate)),
                    'start_month' => (int)date('n', strtotime($startDate)),
                    'start_day'   => (int)date('j', strtotime($startDate)),
                    'end_year'    => (int)date('Y', strtotime($endDate)),
                    'end_month'   => (int)date('n', strtotime($endDate)),
                    'end_day'     => (int)date('j', strtotime($endDate))
                ];
            }
            
            try {
                $raw = GoogleBusinessHandler::getPerformanceMetrics($token, $externalId, $range);
            } catch (Exception $e) {
                log_message('warning', "Failed to fetch Google Business performance metrics: " . $e->getMessage());
                $raw = [];
            }
            
            // Standardize Performance metrics
            if (!empty($raw['multiDailyMetricTimeSeries'])) {
                foreach ($raw['multiDailyMetricTimeSeries'] as $seriesList) {
                    if (!empty($seriesList['dailyMetricTimeSeries'])) {
                        foreach ($seriesList['dailyMetricTimeSeries'] as $timeSeries) {
                            $metricName = $timeSeries['dailyMetric'] ?? 'unknown_metric';
                            $totalVal = 0;
                            if (!empty($timeSeries['timeSeries']['timeSeriesValues'])) {
                                foreach ($timeSeries['timeSeries']['timeSeriesValues'] as $tsVal) {
                                    $totalVal += (int)($tsVal['value'] ?? 0);
                                }
                            }
                            
                            $normalizedMetrics[] = [
                                'platform'    => 'google_business',
                                'metric_name' => strtolower($metricName),
                                'value'       => $totalVal,
                                'period'      => 'range'
                            ];
                        }
                    }
                }
            }
            // Fetch recent local posts
            if ($postId == 0) {
                try {
                    $recentPostsRaw = GoogleBusinessHandler::getRecentPosts($token, $externalId, 50);
                    if (!empty($recentPostsRaw['localPosts'])) {
                        foreach ($recentPostsRaw['localPosts'] as $pItem) {
                            $pName = $pItem['name'] ?? '';
                            $parts = explode('/', $pName);
                            $pId = end($parts);
                            if ($pId) {
                                $mediaUrl = '';
                                if (!empty($pItem['media'][0]['googleUrl'])) {
                                    $mediaUrl = $pItem['media'][0]['googleUrl'];
                                } elseif (!empty($pItem['media'][0]['sourceUrl'])) {
                                    $mediaUrl = $pItem['media'][0]['sourceUrl'];
                                }
                                
                                $normalizedMetrics[] = [
                                    'platform'    => 'google_business',
                                    'metric_name' => 'gbp_post_' . $pId,
                                    'value'       => json_encode([
                                        'post_id'      => $pName,
                                        'summary'      => $pItem['summary'] ?? '',
                                        'published_at' => $pItem['createTime'] ?? '',
                                        'media_url'    => $mediaUrl
                                    ]),
                                    'period'      => 'lifetime'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Google Business recent posts: " . $e->getMessage());
                }
            }
            break;

        case 'linkedin':
            // Follower and post analytics require MDP.
            $normalizedMetrics[] = [
                'platform'    => 'linkedin',
                'metric_name' => 'analytics_unsupported',
                'value'       => 'Requires LinkedIn Marketing Developer Platform approval. Personal profile analytics not accessible via standard posts API.',
                'period'      => 'n/a'
            ];
            
            break;

        default:
            throw new Exception("Analytics not supported for platform: {$platform}");
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'metrics' => $normalizedMetrics
    ]);

} catch (Exception $e) {
    log_message('error', "Analytics request failure", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
