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

                // Query standard post impressions and unique impressions (reach) first
                try {
                    $postInsightsRaw = FacebookHandler::getInsights($token, $externalId, ['post_impressions', 'post_impressions_unique'], 'lifetime');
                    $insightsData = $postInsightsRaw['data'] ?? [];
                } catch (Exception $e) {
                    // Fallback to media view metrics if post_impressions fails
                    try {
                        $postInsightsRaw = FacebookHandler::getInsights($token, $externalId, ['post_media_view', 'post_total_media_view_unique'], 'lifetime');
                        $insightsData = $postInsightsRaw['data'] ?? [];
                    } catch (Exception $fallbackEx) {
                        log_message('warning', "Failed to fetch Facebook post insights: " . $fallbackEx->getMessage());
                        $insightsData = [];
                    }
                }

                if (!empty($insightsData)) {
                    foreach ($insightsData as $item) {
                        $val = $item['values'][0]['value'] ?? $item['value'] ?? 0;
                        $metricName = $item['name'];
                        $normalizedMetrics[] = [
                            'platform'    => 'facebook',
                            'metric_name' => $metricName,
                            'value'       => is_numeric($val) ? (int)$val : $val,
                            'period'      => 'lifetime'
                        ];
                    }
                }
            } else {
                // Page-level: page_media_view replaces deprecated page_views_total in v22+
                try {
                    $metrics = ['page_media_view'];
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
                    log_message('warning', "Failed to fetch Facebook page media view insights: " . $e->getMessage());
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

                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'like_count',    'value' => $likeCount,    'period' => 'lifetime'];
                        $normalizedMetrics[] = ['platform' => 'instagram', 'metric_name' => 'comment_count', 'value' => $commentCount, 'period' => 'lifetime'];
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram media details: " . $e->getMessage());
                }

                // Try fetching views first (unified v22.0+ metric), fall back to impressions/reach for older versions
                try {
                    $raw = InstagramHandler::getInsights($token, $externalId, ['views', 'reach'], 'lifetime');
                    $insightsData = $raw['data'] ?? [];
                } catch (Exception $e) {
                    try {
                        $raw = InstagramHandler::getInsights($token, $externalId, ['impressions', 'reach'], 'lifetime');
                        $insightsData = $raw['data'] ?? [];
                    } catch (Exception $fallbackEx) {
                        try {
                            $raw = InstagramHandler::getInsights($token, $externalId, ['reach'], 'lifetime');
                            $insightsData = $raw['data'] ?? [];
                        } catch (Exception $lastEx) {
                            log_message('warning', "Failed to fetch Instagram post insights: " . $lastEx->getMessage());
                            $insightsData = [];
                        }
                    }
                }

                if (!empty($insightsData)) {
                    foreach ($insightsData as $item) {
                        $name = $item['name'];
                        $period = $item['period'] ?? 'lifetime';
                        $val = $item['values'][0]['value'] ?? $item['value'] ?? 0;
                        $normalizedMetrics[] = [
                            'platform'    => 'instagram',
                            'metric_name' => $name,
                            'value'       => $val,
                            'period'      => $period
                        ];
                    }
                }
            } else {
                // Account-level reach with date range (since/until params)
                try {
                    $sinceTs = strtotime($startDate ?? date('Y-m-d', strtotime('-30 days')));
                    $untilTs = strtotime($endDate ?? date('Y-m-d'));
                    $cacheKey = "instagram_account_insights:{$externalId}:reach:{$sinceTs}:{$untilTs}";
                    $raw = getCachedOrFetch($pdo, $client_id, 'instagram', $cacheKey, function () use ($token, $externalId, $sinceTs, $untilTs) {
                        return InstagramHandler::getInsights($token, $externalId, ['reach'], 'day', 'total_value', $sinceTs, $untilTs);
                    }, 900);
                    if (!empty($raw['data'])) {
                        foreach ($raw['data'] as $item) {
                            $name = $item['name'];
                            $period = $item['period'] ?? 'total_value';
                            // total_value format: {'value': N} or legacy values[] array
                            $val = $item['total_value']['value'] ?? (end($item['values'] ?? [])['value'] ?? 0);
                            $normalizedMetrics[] = [
                                'platform'    => 'instagram',
                                'metric_name' => $name,
                                'value'       => $val,
                                'period'      => $period
                            ];
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Instagram account reach insights: " . $e->getMessage());
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
                                // Instagram Graph API does not return insights inline in the media request.
                                // We query the insights for each media item individually (max 50, but usually fast enough).
                                try {
                                    $mType = strtoupper($mItem['media_type'] ?? 'IMAGE');
                                    try {
                                        // In Graph API v22.0+, 'views' is valid for all media types
                                        $rawInsights = InstagramHandler::getInsights($token, $mId, ['views', 'reach'], 'lifetime');
                                    } catch (Exception $e) {
                                        try {
                                            $rawInsights = InstagramHandler::getInsights($token, $mId, ['impressions', 'reach'], 'lifetime');
                                        } catch (Exception $fallbackEx) {
                                            $rawInsights = InstagramHandler::getInsights($token, $mId, ['reach'], 'lifetime');
                                        }
                                    }
                                    if (!empty($rawInsights['data'])) {
                                        foreach ($rawInsights['data'] as $insight) {
                                            if (empty($insight['name']) || empty($insight['values'][0]['value'])) {
                                                continue;
                                            }
                                            $metricValue = (int)$insight['values'][0]['value'];
                                            if ($insight['name'] === 'views' || $insight['name'] === 'impressions' || $insight['name'] === 'reach') {
                                                $views = max($views, $metricValue);
                                            }
                                        }
                                    }
                                } catch (Exception $insightEx) {
                                    $views = 0;
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
