<?php
/**
 * Platform Analytics Endpoint.
 * Endpoint: GET /api/analytics.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

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
        // Find token for this platform
        $stmt = $pdo->prepare("
            SELECT pc.external_account_id, pt.access_token_encrypted
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $connData = $stmt->fetch();
        if (!$connData) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'No active connection found for ' . $platform]);
            exit();
        }
        $token = !empty($connData['access_token_encrypted']) ? decrypt($connData['access_token_encrypted']) : '';
        $postId = 1; // Treat as post-level metrics fetching
    } elseif ($postId > 0) {
        // Resolve post's external account / platform
        $stmt = $pdo->prepare("
            SELECT p.external_post_id, pc.platform, pc.external_account_id, pt.access_token_encrypted
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
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
        $token = !empty($postData['access_token_encrypted']) ? decrypt($postData['access_token_encrypted']) : '';
    } else {
        // Get account level metrics by finding the first connection for this client + platform
        $stmt = $pdo->prepare("
            SELECT pc.external_account_id, pt.access_token_encrypted
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $connData = $stmt->fetch();
        
        if (!$connData) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'No active connection found for ' . $platform]);
            exit();
        }

        $externalId = $connData['external_account_id'];
        $token = !empty($connData['access_token_encrypted']) ? decrypt($connData['access_token_encrypted']) : '';
    }

    $normalizedMetrics = [];

    // Dispatch and normalize responses
    switch ($platform) {
        case 'facebook':
            if ($postId > 0) {
                try {
                    $metrics = ['post_engaged_users', 'post_reactions_by_type_total'];
                    $raw = FacebookHandler::getInsights($token, $externalId, $metrics);
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
                    log_message('warning', "Failed to fetch Facebook post insights: " . $e->getMessage());
                }
            } else {
                try {
                    $metrics = ['page_post_engagements', 'page_views_total'];
                    $raw = FacebookHandler::getInsights($token, $externalId, $metrics, 'day');
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

                // Fetch recent posts
                try {
                    $recentPostsRaw = FacebookHandler::getRecentPosts($token, $externalId, 50);
                    if (!empty($recentPostsRaw['data'])) {
                        foreach ($recentPostsRaw['data'] as $pItem) {
                            $pId = $pItem['id'] ?? '';
                            if ($pId) {
                                $mediaUrl = '';
                                if (!empty($pItem['attachments']['data'][0]['media']['image']['src'])) {
                                    $mediaUrl = $pItem['attachments']['data'][0]['media']['image']['src'];
                                }
                                $likes = $pItem['likes']['summary']['total_count'] ?? 0;
                                $comments = $pItem['comments']['summary']['total_count'] ?? 0;
                                $shares = $pItem['shares']['count'] ?? 0;
                                
                                $normalizedMetrics[] = [
                                    'platform'    => 'facebook',
                                    'metric_name' => 'fb_post_' . $pId,
                                    'value'       => json_encode([
                                        'post_id'      => $pId,
                                        'message'      => $pItem['message'] ?? '',
                                        'published_at' => $pItem['created_time'] ?? '',
                                        'media_url'    => $mediaUrl,
                                        'likes'        => (int)$likes,
                                        'comments'     => (int)$comments,
                                        'shares'       => (int)$shares
                                    ]),
                                    'period'      => 'lifetime'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch Facebook recent posts: " . $e->getMessage());
                }
            }
            break;

        case 'instagram':
            if ($postId > 0) {
                try {
                    $metrics = ['impressions', 'reach', 'engagement', 'saved', 'video_views'];
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
                    $metrics = ['impressions', 'reach', 'profile_views'];
                    $raw = InstagramHandler::getInsights($token, $externalId, $metrics, 'day');
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
                                        'comments'     => (int)($mItem['comments_count'] ?? 0)
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
                    // Fallback to YouTube Analytics API
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

                // ALSO fetch recent videos live stats & durations directly from YouTube channel
                try {
                    $recentVidRaw = YouTubeHandler::getRecentChannelVideos($token, 50);
                    if (!empty($recentVidRaw['items'])) {
                        foreach ($recentVidRaw['items'] as $vItem) {
                            $vId = $vItem['id'] ?? '';
                            $vStat = $vItem['statistics'] ?? [];
                            $vDur = $vItem['contentDetails']['duration'] ?? '';
                            $vPublishedAt = $vItem['snippet']['publishedAt'] ?? '';
                            $vTitle = $vItem['snippet']['title'] ?? '';

                            if ($vId) {
                                // Pick best thumbnail resolution (maxres > high > medium > default)
                                $thumbs = $vItem['snippet']['thumbnails'] ?? [];
                                $thumbUrl = $thumbs['maxres']['url']
                                    ?? $thumbs['high']['url']
                                    ?? $thumbs['medium']['url']
                                    ?? $thumbs['default']['url']
                                    ?? '';

                                $normalizedMetrics[] = [
                                    'platform'    => 'youtube',
                                    'metric_name' => 'yt_video_' . $vId,
                                    'value'       => json_encode([
                                        'video_id'      => $vId,
                                        'title'         => $vTitle,
                                        'published_at'  => $vPublishedAt,
                                        'thumbnail_url' => $thumbUrl,
                                        'views'         => (int)($vStat['viewCount'] ?? 0),
                                        'likes'         => (int)($vStat['likeCount'] ?? 0),
                                        'comments'      => (int)($vStat['commentCount'] ?? 0),
                                        'duration'      => $vDur
                                    ]),
                                    'period'      => 'lifetime'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Log warning if recent videos call fails
                    log_message('warning', "Failed to fetch YouTube recent channel videos: " . $e->getMessage());
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
            
            // Fetch recent posts
            if ($postId == 0) {
                try {
                    $recentPostsRaw = LinkedInHandler::getRecentPosts($token, $externalId, 50);
                    if (!empty($recentPostsRaw['elements'])) {
                        foreach ($recentPostsRaw['elements'] as $pItem) {
                            $pId = $pItem['id'] ?? '';
                            if ($pId) {
                                $message = $pItem['commentary'] ?? '';
                                $pubTime = $pItem['createdAt'] ?? ''; // Milliseconds timestamp
                                $publishedAt = $pubTime ? date('Y-m-d H:i:s', $pubTime / 1000) : '';
                                
                                $normalizedMetrics[] = [
                                    'platform'    => 'linkedin',
                                    'metric_name' => 'linkedin_post_' . $pId,
                                    'value'       => json_encode([
                                        'post_id'      => $pId,
                                        'message'      => $message,
                                        'published_at' => $publishedAt,
                                        'media_url'    => '',
                                        'likes'        => 0,
                                        'comments'     => 0
                                    ]),
                                    'period'      => 'lifetime'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_message('warning', "Failed to fetch LinkedIn recent posts: " . $e->getMessage());
                }
            }
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
