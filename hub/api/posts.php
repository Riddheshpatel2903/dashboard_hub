<?php
/**
 * Posts Endpoint.
 * Endpoint: GET /api/posts.php
 * Returns local Hub posts plus platform posts from Facebook and YouTube.
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
require_once __DIR__ . '/../config/config.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/token_helper.php';
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';
require_once __DIR__ . '/../storage/StorageService.php';

function ensurePlatformPostsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            platform VARCHAR(50) NOT NULL,
            platform_post_id VARCHAR(255) NOT NULL,
            message TEXT DEFAULT NULL,
            created_time DATETIME DEFAULT NULL,
            permalink VARCHAR(2048) DEFAULT NULL,
            picture VARCHAR(2048) DEFAULT NULL,
            attachments LONGTEXT DEFAULT NULL,
            reactions LONGTEXT DEFAULT NULL,
            comments_count INT DEFAULT 0,
            shares_count INT DEFAULT 0,
            impressions INT DEFAULT NULL,
            reach INT DEFAULT NULL,
            clicks INT DEFAULT NULL,
            engagement INT DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            sync_status VARCHAR(50) NOT NULL DEFAULT 'synced',
            last_synced_at TIMESTAMP NULL DEFAULT NULL,
            raw_platform_response LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_platform_post (client_id, platform, platform_post_id),
            KEY idx_client_platform_published (client_id, platform, published_at),
            KEY idx_sync_status (sync_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function normalizePlatformMediaPath($mediaUrl, $client_id) {
    if (empty($mediaUrl)) {
        return null;
    }

    if (!preg_match('/^https?:\/\//i', $mediaUrl)) {
        return $mediaUrl;
    }

    if (defined('HUB_BASE_URL') && stripos($mediaUrl, rtrim(HUB_BASE_URL, '/')) !== false) {
        $trimmed = preg_replace('#^https?://[^/]+/uploads/#i', '', $mediaUrl);
        return $trimmed ?: $mediaUrl;
    }

    // Do not download/re-host remote platform CDN media files to save local server disk space
    return $mediaUrl;
}

function upsertPlatformPost($pdo, $client_id, $platform, array $data) {
    $stmt = $pdo->prepare("
        INSERT INTO platform_posts (
            client_id, platform, platform_post_id, message, created_time, permalink, picture,
            attachments, reactions, comments_count, shares_count, impressions, reach, clicks, engagement,
            published_at, sync_status, last_synced_at, raw_platform_response
        ) VALUES (
            :client_id, :platform, :platform_post_id, :message, :created_time, :permalink, :picture,
            :attachments, :reactions, :comments_count, :shares_count, :impressions, :reach, :clicks, :engagement,
            :published_at, :sync_status, NOW(), :raw_platform_response
        )
        ON DUPLICATE KEY UPDATE
            message = VALUES(message),
            created_time = VALUES(created_time),
            permalink = VALUES(permalink),
            picture = VALUES(picture),
            attachments = VALUES(attachments),
            reactions = VALUES(reactions),
            comments_count = VALUES(comments_count),
            shares_count = VALUES(shares_count),
            impressions = VALUES(impressions),
            reach = VALUES(reach),
            clicks = VALUES(clicks),
            engagement = VALUES(engagement),
            published_at = VALUES(published_at),
            sync_status = VALUES(sync_status),
            last_synced_at = NOW(),
            raw_platform_response = VALUES(raw_platform_response)
    ");

    $stmt->execute([
        'client_id' => $client_id,
        'platform' => $platform,
        'platform_post_id' => $data['platform_post_id'] ?? '',
        'message' => $data['message'] ?? null,
        'created_time' => $data['created_time'] ?? null,
        'permalink' => $data['permalink'] ?? null,
        'picture' => $data['picture'] ?? null,
        'attachments' => is_array($data['attachments'] ?? null) ? json_encode($data['attachments'], JSON_UNESCAPED_SLASHES) : null,
        'reactions' => is_array($data['reactions'] ?? null) ? json_encode($data['reactions'], JSON_UNESCAPED_SLASHES) : null,
        'comments_count' => isset($data['comments_count']) ? (int)$data['comments_count'] : 0,
        'shares_count' => isset($data['shares_count']) ? (int)$data['shares_count'] : 0,
        'impressions' => isset($data['impressions']) ? (int)$data['impressions'] : null,
        'reach' => isset($data['reach']) ? (int)$data['reach'] : null,
        'clicks' => isset($data['clicks']) ? (int)$data['clicks'] : null,
        'engagement' => isset($data['engagement']) ? (int)$data['engagement'] : null,
        'published_at' => $data['published_at'] ?? null,
        'sync_status' => $data['sync_status'] ?? 'synced',
        'raw_platform_response' => is_array($data['raw_platform_response'] ?? null) ? json_encode($data['raw_platform_response'], JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function formatLocalPost(PDO $pdo, array $post, int $client_id): array {
    $viewsCount = 0;
    $likesCount = 0;
    $commentsCount = 0;
    $metrics = [];
    $mediaPath = $post['media_path'] ?? null;

    if (!empty($post['external_post_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT reactions, comments_count, impressions, reach, clicks, engagement, picture
                FROM platform_posts
                WHERE client_id = :client_id AND platform = :platform AND platform_post_id = :external_post_id
                LIMIT 1
            ");
            $stmt->execute([
                'client_id' => $client_id,
                'platform' => $post['platform'] ?? '',
                'external_post_id' => $post['external_post_id']
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $reactions = [];
                if (!empty($row['reactions'])) {
                    $decoded = json_decode($row['reactions'], true);
                    if (is_array($decoded)) {
                        $reactions = $decoded;
                    }
                }
                $likesCount = isset($reactions['likes']) ? (int)$reactions['likes'] : 0;
                $viewsCount = isset($reactions['views']) ? (int)$reactions['views'] : (int)($row['impressions'] ?? $row['reach'] ?? 0);
                $commentsCount = isset($row['comments_count']) ? (int)$row['comments_count'] : (isset($reactions['comments']) ? (int)$reactions['comments'] : 0);
                
                if (!empty($row['picture'])) {
                    $mediaPath = $row['picture'];
                }

                $metrics = [
                    'impressions' => isset($row['impressions']) ? (int)$row['impressions'] : null,
                    'reach' => isset($row['reach']) ? (int)$row['reach'] : null,
                    'clicks' => isset($row['clicks']) ? (int)$row['clicks'] : null,
                    'engagement' => isset($row['engagement']) ? (int)$row['engagement'] : null,
                    'likes' => $likesCount,
                    'comments' => $commentsCount,
                ];
            }
        } catch (Exception $e) {
            // Ignore
        }


    }

    return [
        'id' => 0,
        'hub_post_id' => (int)($post['id'] ?? 0),
        'content' => $post['content'] ?? '',
        'status' => $post['status'] ?? 'draft',
        'platform' => $post['platform'] ?? 'unknown',
        'media_path' => $mediaPath,
        'scheduled_at' => $post['scheduled_at'] ?? null,
        'published_at' => $post['published_at'] ?? $post['created_at'] ?? null,
        'created_at' => $post['created_at'] ?? null,
        'external_post_id' => $post['external_post_id'] ?? null,
        'views_count' => $viewsCount,
        'likes_count' => $likesCount,
        'comments_count' => $commentsCount,
        'metrics' => $metrics,
        'source' => 'local'
    ];
}

function fetchCachedPlatformPosts(PDO $pdo, int $client_id, string $platformFilter = '', int $limit = 100): array {
    $sql = "SELECT platform, platform_post_id, message, picture, attachments, reactions, comments_count, shares_count, impressions, reach, clicks, engagement, published_at, created_time, raw_platform_response FROM platform_posts WHERE client_id = :client_id AND sync_status = 'synced'";
    if (!empty($platformFilter)) {
        $sql .= " AND platform = :platform";
    }
    $sql .= " ORDER BY published_at DESC, created_time DESC";
    if ($limit > 0) {
        $sql .= " LIMIT :limit";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('client_id', $client_id, PDO::PARAM_INT);
    if (!empty($platformFilter)) {
        $stmt->bindValue('platform', $platformFilter, PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $posts = [];
    foreach ($rows as $row) {
        $reactions = [];
        if (!empty($row['reactions'])) {
            $decodedReactions = json_decode($row['reactions'], true);
            if (is_array($decodedReactions)) {
                $reactions = $decodedReactions;
            }
        }

        $likesCount = isset($reactions['likes']) ? (int)$reactions['likes'] : 0;
        $viewsCount = isset($reactions['views']) ? (int)$reactions['views'] : (int)($row['impressions'] ?? 0);
        $commentsCount = isset($row['comments_count']) ? (int)$row['comments_count'] : (isset($reactions['comments']) ? (int)$reactions['comments'] : 0);

        $posts[] = [
            'id' => 0,
            'hub_post_id' => null,
            'content' => $row['message'] ?? '',
            'status' => 'published',
            'platform' => $row['platform'] ?? 'unknown',
            'media_path' => $row['picture'] ?? null,
            'scheduled_at' => null,
            'published_at' => $row['published_at'] ?? $row['created_time'] ?? null,
            'created_at' => $row['created_time'] ?? null,
            'external_post_id' => $row['platform_post_id'] ?? null,
            'views_count' => $viewsCount,
            'likes_count' => $likesCount,
            'comments_count' => $commentsCount,
            'metrics' => [
                'impressions' => isset($row['impressions']) ? (int)$row['impressions'] : null,
                'reach' => isset($row['reach']) ? (int)$row['reach'] : null,
                'clicks' => isset($row['clicks']) ? (int)$row['clicks'] : null,
                'engagement' => isset($row['engagement']) ? (int)$row['engagement'] : null,
                'likes' => $likesCount,
                'comments' => $commentsCount,
            ],
            'source' => 'platform'
        ];
    }

    return $posts;
}

function normalizeFacebookMetrics(array $postItem, array $insights = [], array $engagementData = []): array {
    $likes = (int)($engagementData['likes'] ?? $postItem['likes']['summary']['total_count'] ?? 0);
    $comments = (int)($engagementData['comments'] ?? $postItem['comments']['summary']['total_count'] ?? 0);
    $shares = (int)($engagementData['shares'] ?? $postItem['shares']['count'] ?? 0);
    $reach = null;
    $impressions = null;
    $clicks = null;
    $engagement = null;
    $views = null;

    foreach ($insights as $insight) {
        if (empty($insight['name'])) {
            continue;
        }
        // Support both legacy values[] format and new value format
        $value = $insight['values'][0]['value'] ?? $insight['value'] ?? null;
        if ($value === null) {
            continue;
        }
        $name = $insight['name'];
        // v22+ current metrics (post_media_view replaces post_impressions)
        if ($name === 'post_media_view') {
            $impressions = (int)$value;
            if ($views === null) {
                $views = (int)$value;
            }
        // v22+ unique reach (replaces post_impressions_unique)
        } elseif ($name === 'post_total_media_view_unique') {
            $reach = (int)$value;
        // Legacy fallback — retained for backwards compatibility
        } elseif ($name === 'post_impressions') {
            if ($impressions === null) {
                $impressions = (int)$value;
            }
            if ($views === null) {
                $views = (int)$value;
            }
        } elseif ($name === 'post_impressions_unique') {
            if ($reach === null) {
                $reach = (int)$value;
            }
        } elseif ($name === 'post_engaged_users') {
            if ($engagement === null) {
                $engagement = (int)$value;
            }
        } elseif ($name === 'post_engagement') {
            if ($engagement === null && is_numeric($value)) {
                $engagement = (int)$value;
            }
        }
    }

    if ($views === null) {
        $views = $impressions ?? $reach;
    }

    return [
        'likes'      => $likes,
        'comments'   => $comments,
        'shares'     => $shares,
        'reach'      => $reach,
        'impressions'=> $impressions,
        'views'      => $views,
        'clicks'     => $clicks,
        'engagement' => $engagement,
    ];
}

function normalizeInstagramMetrics(array $mediaItem, array $insights = []): array {
    $likes = isset($mediaItem['like_count']) ? (int)$mediaItem['like_count'] : 0;
    $comments = isset($mediaItem['comments_count']) ? (int)$mediaItem['comments_count'] : 0;
    $views = null;
    $impressions = null;
    $reach = null;
    $engagement = $likes + $comments;

    foreach ($insights as $insight) {
        if (empty($insight['name'])) {
            continue;
        }
        $value = $insight['values'][0]['value'] ?? null;
        if ($value === null) {
            continue;
        }
        if ($insight['name'] === 'views') {
            $views = (int)$value;
        } elseif ($insight['name'] === 'impressions') {
            $impressions = (int)$value;
        } elseif ($insight['name'] === 'reach') {
            $reach = (int)$value;
        }
    }

    if ($views === null) {
        $views = $impressions ?? $reach;
    }

    return [
        'likes' => $likes,
        'comments' => $comments,
        'views' => $views,
        'impressions' => $impressions,
        'reach' => $reach,
        'engagement' => $engagement,
    ];
}

function normalizeYouTubeMetrics(array $videoItem, array $analytics = []): array {
    $views = isset($videoItem['statistics']['viewCount']) ? (int)$videoItem['statistics']['viewCount'] : 0;
    $likes = isset($videoItem['statistics']['likeCount']) ? (int)$videoItem['statistics']['likeCount'] : 0;
    $comments = isset($videoItem['statistics']['commentCount']) ? (int)$videoItem['statistics']['commentCount'] : 0;

    $estimatedMinutesWatched = null;
    $subscribersGained = null;

    if (!empty($analytics['rows'][0])) {
        $row = $analytics['rows'][0];
        $views = isset($row[0]) ? (int)$row[0] : $views;
        $comments = isset($row[1]) ? (int)$row[1] : $comments;
        $likes = isset($row[2]) ? (int)$row[2] : $likes;
        $estimatedMinutesWatched = isset($row[4]) ? (int)$row[4] : null;
        $subscribersGained = isset($row[6]) ? (int)$row[6] : null;
    }

    return [
        'views' => $views,
        'likes' => $likes,
        'comments' => $comments,
        'estimated_minutes_watched' => $estimatedMinutesWatched,
        'subscribers_gained' => $subscribersGained,
    ];
}

/**
 * Fetch live posts for a single platform connection.
 *
 * Returns null on total fetch failure (caller must NOT wipe cache).
 * Returns array of ['upsert_data'=>..., 'formatted'=>...] on success (may be empty array if platform returned zero posts).
 */
function fetchLivePostsForPlatform(string $token, array $conn, string $platform, int $limit, array &$platformErrors): ?array {
    $results = [];

    try {
        if ($platform === 'facebook') {
            try {
                $recentPostsRaw = FacebookHandler::getRecentPosts($token, $conn['external_account_id'], $limit);
            } catch (Exception $e) {
                $platformErrors[$platform] = $e->getMessage();
                if (strpos($e->getMessage(), 'pages_read_engagement') !== false) {
                    log_message('warning', 'Facebook fetch failed — token missing pages_read_engagement scope, reconnect required', ['platform' => $platform, 'client_id' => $conn['client_id'] ?? '']);
                } else {
                    log_message('warning', 'Platform post fetch failed', ['platform' => $platform, 'error' => $e->getMessage()]);
                }
                return null; // Signal: keep existing cache
            }

            if (!empty($recentPostsRaw['data'])) {
                foreach ($recentPostsRaw['data'] as $postItem) {
                    $postIdValue = $postItem['id'] ?? '';
                    if (empty($postIdValue)) continue;

                    $mediaPath = $postItem['attachments']['data'][0]['media']['image']['src'] ?? ($postItem['full_picture'] ?? null);
                    $engagementData = [];
                    $insights = [];
                    try {
                        $engagementData = FacebookHandler::getEngagementCounts($token, $postIdValue);
                    } catch (Exception $e) {
                        log_message('warning', 'Facebook post engagement fetch failed', ['post_id' => $postIdValue, 'error' => $e->getMessage()]);
                    }
                    try {
                        // Query standard post impressions and unique impressions (reach) first
                        $rawInsights = FacebookHandler::getInsights($token, $postIdValue, ['post_impressions', 'post_impressions_unique'], 'lifetime');
                        $insights = $rawInsights['data'] ?? [];
                    } catch (Exception $e) {
                        // Fallback to media view metrics if post_impressions fails
                        try {
                            $rawInsights = FacebookHandler::getInsights($token, $postIdValue, ['post_media_view', 'post_total_media_view_unique'], 'lifetime');
                            $insights = $rawInsights['data'] ?? [];
                        } catch (Exception $fallbackEx) {
                            log_message('warning', 'Facebook post insights fetch failed', ['post_id' => $postIdValue, 'error' => $fallbackEx->getMessage()]);
                            // Silently retry with no insights — engagement counts still populate from getEngagementCounts()
                            $insights = [];
                        }
                    }
                    $metrics = normalizeFacebookMetrics($postItem, $insights, $engagementData);

                    $results[] = [
                        'formatted' => [
                            'id'              => 0,
                            'hub_post_id'     => null,
                            'content'         => $postItem['message'] ?? '',
                            'status'          => 'published',
                            'platform'        => 'facebook',
                            'media_path'      => $mediaPath,
                            'scheduled_at'    => null,
                            'published_at'    => $postItem['created_time'] ?? null,
                            'created_at'      => $postItem['created_time'] ?? null,
                            'external_post_id'=> $postIdValue,
                            'views_count'     => (int)($metrics['views'] ?? 0),
                            'likes_count'     => (int)$metrics['likes'],
                            'comments_count'  => (int)$metrics['comments'],
                                'metrics'         => $metrics,
                            'source'          => 'facebook',
                        ],
                        'upsert_data' => [
                            'platform_post_id'     => $postIdValue,
                            'message'              => $postItem['message'] ?? '',
                            'created_time'         => $postItem['created_time'] ?? null,
                            'permalink'            => $postItem['permalink_url'] ?? null,
                            'picture'              => $mediaPath,
                            'attachments'          => $postItem['attachments'] ?? null,
                            'reactions'            => ['likes' => $metrics['likes'], 'comments' => $metrics['comments'], 'shares' => $metrics['shares']],
                            'comments_count'       => $metrics['comments'],
                            'shares_count'         => $metrics['shares'],
                            'impressions'          => $metrics['impressions'],
                            'reach'                => $metrics['reach'],
                            'clicks'               => $metrics['clicks'],
                            'engagement'           => $metrics['engagement'],
                            'published_at'         => $postItem['created_time'] ?? null,
                            'raw_platform_response'=> $postItem,
                        ],
                    ];
                }
            }

        } elseif ($platform === 'instagram') {
            try {
                $recentMediaRaw = InstagramHandler::getRecentMedia($token, $conn['external_account_id'], $limit);
            } catch (Exception $e) {
                $platformErrors[$platform] = $e->getMessage();
                log_message('warning', 'Instagram fetch failed', ['error' => $e->getMessage()]);
                return null;
            }

            if (!empty($recentMediaRaw['data'])) {
                foreach ($recentMediaRaw['data'] as $mediaItem) {
                    $mediaId = $mediaItem['id'] ?? '';
                    if (empty($mediaId)) continue;

                    $mediaUrl = $mediaItem['media_url'] ?? null;
                    $mediaPath = normalizePlatformMediaPath($mediaUrl, $conn['client_id'] ?? 0);
                    $timestamp = $mediaItem['timestamp'] ?? null;
                    $insights = [];
                    // Determine media type for correct metric selection
                    $mediaType = strtoupper($mediaItem['media_type'] ?? 'IMAGE');
                    try {
                        // In Graph API v22.0+, 'views' is the unified metric for ALL media types (including images/carousels)
                        $rawInsights = InstagramHandler::getInsights($token, $mediaId, ['views', 'reach'], 'lifetime');
                        $insights = $rawInsights['data'] ?? [];
                    } catch (Exception $e) {
                        // Fallback for older API versions where 'views' is only valid for video, try impressions and reach
                        try {
                            $rawInsights = InstagramHandler::getInsights($token, $mediaId, ['impressions', 'reach'], 'lifetime');
                            $insights = $rawInsights['data'] ?? [];
                        } catch (Exception $fallbackEx) {
                            // Last resort fallback: try reach only
                            try {
                                $rawInsights = InstagramHandler::getInsights($token, $mediaId, ['reach'], 'lifetime');
                                $insights = $rawInsights['data'] ?? [];
                            } catch (Exception $lastEx) {
                                log_message('warning', 'Instagram media insights unavailable', ['media_id' => $mediaId, 'media_type' => $mediaType, 'error' => $lastEx->getMessage()]);
                                $insights = [];
                            }
                        }
                    }
                    $metrics = normalizeInstagramMetrics($mediaItem, $insights);

                    $results[] = [
                        'formatted' => [
                            'id'              => 0,
                            'hub_post_id'     => null,
                            'content'         => $mediaItem['caption'] ?? '',
                            'status'          => 'published',
                            'platform'        => 'instagram',
                            'media_path'      => $mediaPath,
                            'scheduled_at'    => null,
                            'published_at'    => $timestamp,
                            'created_at'      => $timestamp,
                            'external_post_id'=> $mediaId,
                            'views_count'     => isset($metrics['views']) ? (int)$metrics['views'] : null,
                            'likes_count'     => (int)($metrics['likes'] ?? 0),
                            'comments_count'  => (int)($metrics['comments'] ?? 0),
                            'metrics'         => $metrics,
                            'source'          => 'instagram',
                        ],
                        'upsert_data' => [
                            'platform_post_id'     => $mediaId,
                            'message'              => $mediaItem['caption'] ?? '',
                            'created_time'         => $timestamp,
                            'permalink'            => null,
                            'picture'              => $mediaPath,
                            'attachments'          => ['media_type' => $mediaItem['media_type'] ?? null, 'thumbnail_url' => $mediaPath],
                            'reactions'            => ['likes' => $metrics['likes'] ?? 0, 'comments' => $metrics['comments'] ?? 0],
                            'comments_count'       => $metrics['comments'] ?? 0,
                            'shares_count'         => 0,
                            'impressions'          => $metrics['impressions'] ?? null,
                            'reach'                => $metrics['reach'] ?? null,
                            'clicks'               => null,
                            'engagement'           => $metrics['engagement'] ?? null,
                            'published_at'         => $timestamp,
                            'raw_platform_response'=> $mediaItem,
                        ],
                    ];
                }
            }

        } elseif ($platform === 'youtube') {
            try {
                $recentVideosRaw = YouTubeHandler::getRecentChannelVideos($token, $limit);
            } catch (Exception $e) {
                $platformErrors[$platform] = $e->getMessage();
                log_message('warning', 'YouTube fetch failed', ['error' => $e->getMessage()]);
                return null;
            }

            if (!empty($recentVideosRaw['items'])) {
                foreach ($recentVideosRaw['items'] as $videoItem) {
                    $videoId = $videoItem['id'] ?? '';
                    if (empty($videoId)) continue;

                    try {
                        $analyticsRaw = YouTubeHandler::getVideoAnalytics($token, $videoId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
                        $metrics = normalizeYouTubeMetrics($videoItem, $analyticsRaw);
                    } catch (Exception $analyticsEx) {
                        log_message('warning', 'YouTube video analytics unavailable', ['video_id' => $videoId, 'error' => $analyticsEx->getMessage()]);
                        $metrics = normalizeYouTubeMetrics($videoItem, []);
                    }

                    $thumbs = $videoItem['snippet']['thumbnails'] ?? [];
                    $thumbnailUrl = $thumbs['maxres']['url'] ?? $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? '';

                    $results[] = [
                        'formatted' => [
                            'id'              => 0,
                            'hub_post_id'     => null,
                            'content'         => $videoItem['snippet']['title'] ?? '',
                            'status'          => 'published',
                            'platform'        => 'youtube',
                            'media_path'      => $thumbnailUrl,
                            'scheduled_at'    => null,
                            'published_at'    => $videoItem['snippet']['publishedAt'] ?? null,
                            'created_at'      => $videoItem['snippet']['publishedAt'] ?? null,
                            'external_post_id'=> $videoId,
                            'views_count'     => (int)($metrics['views'] ?? 0),
                            'likes_count'     => (int)($metrics['likes'] ?? 0),
                            'comments_count'  => (int)($metrics['comments'] ?? 0),
                                'metrics'         => $metrics,
                            'source'          => 'youtube',
                        ],
                        'upsert_data' => [
                            'platform_post_id'     => $videoId,
                            'message'              => $videoItem['snippet']['title'] ?? '',
                            'created_time'         => $videoItem['snippet']['publishedAt'] ?? null,
                            'permalink'            => 'https://www.youtube.com/watch?v=' . $videoId,
                            'picture'              => $thumbnailUrl,
                            'attachments'          => ['description' => $videoItem['snippet']['description'] ?? '', 'duration' => $videoItem['contentDetails']['duration'] ?? null],
                            'reactions'            => ['views' => $metrics['views'], 'likes' => $metrics['likes'], 'comments' => $metrics['comments']],
                            'comments_count'       => $metrics['comments'],
                            'shares_count'         => 0,
                            'impressions'          => $metrics['views'],
                            'reach'                => null,
                            'clicks'               => null,
                            'engagement'           => $metrics['likes'] + $metrics['comments'],
                            'published_at'         => $videoItem['snippet']['publishedAt'] ?? null,
                            'raw_platform_response'=> $videoItem,
                        ],
                    ];
                }
            }
        } elseif ($platform === 'google_business') {
            try {
                $recentPostsRaw = GoogleBusinessHandler::getRecentPosts($token, $conn['external_account_id'], $limit);
            } catch (Exception $e) {
                $platformErrors[$platform] = $e->getMessage();
                log_message('warning', 'Google Business fetch failed', ['error' => $e->getMessage()]);
                return null;
            }

            if (!empty($recentPostsRaw['localPosts'])) {
                foreach ($recentPostsRaw['localPosts'] as $postItem) {
                    $postIdValue = $postItem['name'] ?? '';
                    if (empty($postIdValue)) continue;

                    $mediaUrl = null;
                    if (!empty($postItem['media'][0]['googleUrl'])) {
                        $mediaUrl = $postItem['media'][0]['googleUrl'];
                    }
                    $mediaPath = normalizePlatformMediaPath($mediaUrl, $conn['client_id'] ?? 0);

                    $views = 0;
                    $results[] = [
                        'formatted' => [
                            'id'              => 0,
                            'hub_post_id'     => null,
                            'content'         => $postItem['summary'] ?? '',
                            'status'          => 'published',
                            'platform'        => 'google_business',
                            'media_path'      => $mediaPath,
                            'scheduled_at'    => null,
                            'published_at'    => $postItem['createTime'] ?? null,
                            'created_at'      => $postItem['createTime'] ?? null,
                            'external_post_id'=> $postIdValue,
                            'views_count'     => $views,
                            'likes_count'     => 0,
                            'comments_count'  => 0,
                            'metrics'         => [
                                'impressions' => $views,
                                'reach'       => $views,
                                'clicks'      => 0,
                                'engagement'  => 0,
                                'likes'       => 0,
                                'comments'    => 0,
                            ],
                            'source'          => 'google_business',
                        ],
                        'upsert_data' => [
                            'platform_post_id'     => $postIdValue,
                            'message'              => $postItem['summary'] ?? '',
                            'created_time'         => $postItem['createTime'] ?? null,
                            'permalink'            => null,
                            'picture'              => $mediaPath,
                            'attachments'          => $postItem['media'] ?? null,
                            'reactions'            => ['likes' => 0, 'comments' => 0],
                            'comments_count'       => 0,
                            'shares_count'         => 0,
                            'impressions'          => $views,
                            'reach'                => $views,
                            'clicks'               => 0,
                            'engagement'           => 0,
                            'published_at'         => $postItem['createTime'] ?? null,
                            'raw_platform_response'=> $postItem,
                        ],
                    ];
                }
            }

        } elseif ($platform === 'linkedin') {
            try {
                $recentPostsRaw = LinkedInHandler::getRecentPosts($token, $conn['external_account_id'], $limit);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if (stripos($msg, '403') !== false || stripos($msg, 'permissions') !== false) {
                    log_message('info', 'LinkedIn post fetching is restricted by API scope permissions.', ['error' => $msg]);
                    return []; // Return empty instead of null to skip error banner
                }
                $platformErrors[$platform] = $msg;
                log_message('warning', 'LinkedIn fetch failed', ['error' => $msg]);
                return null;
            }

            if (!empty($recentPostsRaw['elements'])) {
                foreach ($recentPostsRaw['elements'] as $postItem) {
                    $postIdValue = $postItem['id'] ?? '';
                    if (empty($postIdValue)) continue;

                    $message = '';
                    $shareContent = $postItem['specificContent']['com.linkedin.ugc.ShareContent'] ?? null;
                    if ($shareContent && !empty($shareContent['shareCommentary']['text'])) {
                        $message = $shareContent['shareCommentary']['text'];
                    }

                    $mediaPath = null;
                    $publishedAt = null;
                    if (!empty($postItem['created']['time'])) {
                        $publishedAt = date('Y-m-d H:i:s', $postItem['created']['time'] / 1000);
                    }

                    $results[] = [
                        'formatted' => [
                            'id'              => 0,
                            'hub_post_id'     => null,
                            'content'         => $message,
                            'status'          => 'published',
                            'platform'        => 'linkedin',
                            'media_path'      => $mediaPath,
                            'scheduled_at'    => null,
                            'published_at'    => $publishedAt,
                            'created_at'      => $publishedAt,
                            'external_post_id'=> $postIdValue,
                            'views_count'     => 0,
                            'likes_count'     => 0,
                            'comments_count'  => 0,
                            'metrics'         => [
                                'impressions' => 0,
                                'reach'       => 0,
                                'clicks'      => 0,
                                'engagement'  => 0,
                                'likes'       => 0,
                                'comments'    => 0,
                            ],
                            'source'          => 'linkedin',
                        ],
                        'upsert_data' => [
                            'platform_post_id'     => $postIdValue,
                            'message'              => $message,
                            'created_time'         => $publishedAt,
                            'permalink'            => null,
                            'picture'              => $mediaPath,
                            'attachments'          => $shareContent,
                            'reactions'            => ['likes' => 0, 'comments' => 0],
                            'comments_count'       => 0,
                            'shares_count'         => 0,
                            'impressions'          => 0,
                            'reach'                => 0,
                            'clicks'               => 0,
                            'engagement'           => 0,
                            'published_at'         => $publishedAt,
                            'raw_platform_response'=> $postItem,
                        ],
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $platformErrors[$platform] = $e->getMessage();
        log_message('warning', 'Platform post fetch failed', ['platform' => $platform, 'error' => $e->getMessage()]);
        return null;
    }

    return $results;
}

/**
 * Live sync for all connected platforms for a client.
 *
 * Fetch-first, delete-on-success: a failed API call never wipes good existing cache data.
 * Returns array of freshly formatted posts ready to merge into $formatted.
 */
function performLiveSync(PDO $pdo, int $client_id, string $platformFilter, int $limit, array &$platformErrors): array {
    $connStmt = $pdo->prepare("
        SELECT pc.id, pc.client_id, pc.platform, pc.external_account_id, pt.access_token_encrypted
        FROM platform_connections pc
        LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.client_id = :client_id AND pc.status = 'connected'
        ORDER BY pc.id ASC
    ");
    $connStmt->execute(['client_id' => $client_id]);
    $connections = $connStmt->fetchAll() ?: [];

    // Filter connected platforms
    $activeConns = [];
    foreach ($connections as $conn) {
        $platform = strtolower($conn['platform'] ?? '');
        if ($platform === 'whatsapp') continue;
        if (!empty($platformFilter) && $platform !== $platformFilter) continue;
        $activeConns[] = $conn;
    }

    // Normal sequential sync loop
    $allFresh = [];
    foreach ($activeConns as $conn) {
        $platform = strtolower($conn['platform']);
        $token = get_valid_platform_token($pdo, $client_id, $platform);
        if (empty($token)) continue;

        // Retrieve pending delete external post IDs for this platform and client
        $pendingDeleteStmt = $pdo->prepare("
            SELECT DISTINCT p.external_post_id 
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.client_id = :client_id AND pc.platform = :platform AND p.status = 'pending_delete' AND p.external_post_id IS NOT NULL
        ");
        $pendingDeleteStmt->execute(['client_id' => $client_id, 'platform' => $platform]);
        $pendingDeleteIds = $pendingDeleteStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $freshForPlatform = fetchLivePostsForPlatform($token, $conn, $platform, $limit, $platformErrors);

        if ($freshForPlatform === null) {
            log_message('info', 'Keeping existing platform cache due to fetch failure', ['platform' => $platform, 'client_id' => $client_id]);
            
            // Fetch cached posts for this platform and append to allFresh (filtering out pending deletes)
            $cached = fetchCachedPlatformPosts($pdo, $client_id, $platform, $limit);
            foreach ($cached as $cPost) {
                if (!empty($cPost['external_post_id']) && in_array($cPost['external_post_id'], $pendingDeleteIds, true)) {
                    continue;
                }
                $allFresh[] = $cPost;
            }
            continue;
        }

        $deleteStmt = $pdo->prepare("DELETE FROM platform_posts WHERE client_id = :client_id AND platform = :platform");
        $deleteStmt->execute(['client_id' => $client_id, 'platform' => $platform]);

        $fetchedExternalIds = [];
        $fetchedTimestamps = [];
        foreach ($freshForPlatform as $entry) {
            $extPostId = $entry['upsert_data']['platform_post_id'] ?? null;
            if ($extPostId && in_array($extPostId, $pendingDeleteIds, true)) {
                log_message('debug', 'Sync: Excluded pending delete post from sync results', ['external_id' => $extPostId]);
                continue; // Skip pending delete posts
            }

            upsertPlatformPost($pdo, $client_id, $platform, $entry['upsert_data']);
            $allFresh[] = $entry['formatted'];
            if (!empty($entry['upsert_data']['platform_post_id'])) {
                $fetchedExternalIds[] = $entry['upsert_data']['platform_post_id'];
            }
            if (!empty($entry['upsert_data']['published_at'])) {
                $fetchedTimestamps[] = strtotime($entry['upsert_data']['published_at']);
            }
        }

        // Auto-heal local posts table: if a local post is newer than the oldest fetched post
        // but not in the fetched list, it was deleted on the platform.
        if (!empty($fetchedExternalIds) && !empty($fetchedTimestamps)) {
            $minDate = date('Y-m-d H:i:s', min($fetchedTimestamps));
            $placeholders = implode(',', array_fill(0, count($fetchedExternalIds), '?'));
            
            $stmtFindOrphan = $pdo->prepare("
                SELECT p.id 
                FROM posts p
                JOIN platform_connections pc ON p.platform_connection_id = pc.id
                WHERE p.client_id = ? 
                  AND pc.platform = ? 
                  AND p.external_post_id IS NOT NULL 
                  AND p.external_post_id != '' 
                  AND p.published_at >= ?
                  AND p.external_post_id NOT IN ($placeholders)
            ");
            $params = array_merge([$client_id, $platform, $minDate], $fetchedExternalIds);
            $stmtFindOrphan->execute($params);
            $orphanIds = $stmtFindOrphan->fetchAll(PDO::FETCH_COLUMN) ?: [];
            
            if (!empty($orphanIds)) {
                $orphanPlaceholders = implode(',', array_fill(0, count($orphanIds), '?'));
                $pdo->prepare("DELETE FROM posts WHERE id IN ($orphanPlaceholders)")->execute($orphanIds);
                log_message('info', 'Auto-healed deleted platform posts from Hub posts table', ['platform' => $platform, 'deleted_count' => count($orphanIds)]);
            }
        }

        try {
            $pdo->prepare("UPDATE platform_connections SET last_synced_at = NOW() WHERE client_id = :client_id AND platform = :platform")
                ->execute(['client_id' => $client_id, 'platform' => $platform]);
        } catch (Exception $e) {}
    }

    return $allFresh;
}

try {
    ensurePlatformPostsTable($pdo);

    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    $platformFilter = trim(strtolower($_GET['platform'] ?? ''));
    $includePlatform = !isset($_GET['include_platform']) || in_array(strtolower($_GET['include_platform'] ?? '1'), ['1', 'true', 'yes'], true);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5000;

    if ($postId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id, pc.external_account_id as page_id
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.client_id = :client_id AND p.id = :post_id
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'post_id' => $postId
        ]);
        $post = $stmt->fetch();

        if (!$post) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit();
        }

        if ($post['status'] === 'failed' && !empty($post['external_post_id'])) {
            try {
                $healStmt = $pdo->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = :id");
                $healStmt->execute(['id' => $post['id']]);
                $post['status'] = 'published';
            } catch (Exception $e) {
                // Ignore
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'post' => formatLocalPost($pdo, [
                'id' => $post['id'],
                'content' => $post['content'],
                'status' => $post['status'],
                'platform' => $post['platform'],
                'media_path' => $post['media_path'],
                'scheduled_at' => $post['scheduled_at'],
                'published_at' => $post['published_at'],
                'created_at' => $post['created_at'],
                'external_post_id' => $post['external_post_id'],
                'page_id' => $post['page_id'] ?? null,
            ], $client_id)
        ]);
        exit();
    }

    // Auto clean up failed posts from Hub database (only if they don't have an external post ID)
    try {
        $pdo->prepare("DELETE FROM posts WHERE client_id = :client_id AND status = 'failed' AND (external_post_id IS NULL OR external_post_id = '')")->execute(['client_id' => $client_id]);
    } catch (Exception $e) {
        // Ignore
    }

    $formatted = [];

    $sql = "
        SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id, pc.external_account_id as page_id
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE p.client_id = :client_id AND p.status != 'pending_delete'
        ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.created_at DESC
    ";
    if ($limit > 0) {
        $sql .= " LIMIT :limit";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('client_id', $client_id, PDO::PARAM_INT);
    if ($limit > 0) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $posts = $stmt->fetchAll() ?: [];

    foreach ($posts as $post) {
        if (!empty($platformFilter) && strtolower($post['platform'] ?? '') !== $platformFilter) {
            continue;
        }

        if ($post['status'] === 'failed' && !empty($post['external_post_id'])) {
            try {
                $healStmt = $pdo->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = :id");
                $healStmt->execute(['id' => $post['id']]);
                $post['status'] = 'published';
            } catch (Exception $e) {
                // Ignore
            }
        }

        $formatted[] = formatLocalPost($pdo, $post, $client_id);
    }

    $platformErrors = [];
    if ($includePlatform) {
        $forceSync = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);

        if ($forceSync) {
            $lockName = 'sync_posts_' . $client_id;
            $lockStmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, 5)");
            $lockStmt->execute(['lock_name' => $lockName]);
            $lockAcquired = (int)$lockStmt->fetchColumn() === 1;

            if ($lockAcquired) {
                try {
                    // Full replace-on-sync: fetch live first, delete old cache only on success.
                    $freshPosts = performLiveSync($pdo, $client_id, $platformFilter, $limit, $platformErrors);
                    foreach ($freshPosts as $post) {
                        $formatted[] = $post;
                    }
                } finally {
                    $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
                    $unlockStmt->execute(['lock_name' => $lockName]);
                }

                // Trigger storage garbage collector to clean up local media copies that are now hosted on platform CDNs
                try {
                    require_once __DIR__ . '/../storage/StorageService.php';
                    StorageService::cleanOrphanUploads($pdo);
                } catch (Exception $cleanEx) {
                    log_message('warning', 'Automated storage cleanup after sync failed: ' . $cleanEx->getMessage());
                }
            } else {
                // Fallback to cache since another sync is already in progress
                $cachedPosts = fetchCachedPlatformPosts($pdo, $client_id, $platformFilter, $limit);
                foreach ($cachedPosts as $post) {
                    $formatted[] = $post;
                }
                $platformErrors['sync'] = 'Another synchronization is already in progress. Showing cached data.';
            }
        } else {
            // Normal page load: read from cache only, no live API calls.
            $cachedPosts = fetchCachedPlatformPosts($pdo, $client_id, $platformFilter, $limit);
            foreach ($cachedPosts as $post) {
                $formatted[] = $post;
            }
        }
    }

    // Deduplicate posts centrally (merge local database entries with platform/live API entries)
    $unique = [];
    foreach ($formatted as $post) {
        $key = null;
        if (!empty($post['platform']) && !empty($post['external_post_id'])) {
            $key = $post['platform'] . '_' . $post['external_post_id'];
        } elseif (!empty($post['hub_post_id'])) {
            $key = ($post['platform'] ?? 'unknown') . '_local_' . $post['hub_post_id'];
        }

        if ($key) {
            if (!isset($unique[$key])) {
                $unique[$key] = $post;
            } else {
                $existing = $unique[$key];
                $hubPostId = $post['hub_post_id'] ?: ($existing['hub_post_id'] ?: null);
                $pref = ($post['source'] !== 'local') ? $post : $existing;
                $local = ($post['source'] === 'local') ? $post : $existing;

                $merged = $pref;
                $merged['hub_post_id'] = $hubPostId;
                $merged['id'] = $local['id'] ?: ($pref['id'] ?: 0);
                if (empty($merged['content']) && !empty($local['content'])) {
                    $merged['content'] = $local['content'];
                }
                if ($post['source'] === 'local' || $existing['source'] === 'local') {
                    $merged['source'] = 'local';
                }
                $unique[$key] = $merged;
            }
        } else {
            $unique[] = $post;
        }
    }
    $formatted = array_values($unique);

    usort($formatted, function ($a, $b) {
        $dateA = $a['published_at'] ?: ($a['scheduled_at'] ?: $a['created_at']);
        $dateB = $b['published_at'] ?: ($b['scheduled_at'] ?: $b['created_at']);
        return strcmp($dateB, $dateA);
    });

    if ($limit > 0) {
        $formatted = array_slice($formatted, 0, $limit);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'posts' => $formatted,
        'local_posts' => array_values(array_filter($formatted, function ($item) { return ($item['source'] ?? '') === 'local'; })),
        'platform_posts' => array_values(array_filter($formatted, function ($item) { return ($item['source'] ?? '') !== 'local'; })),
        'platform_errors' => $platformErrors
    ]);
} catch (Throwable $e) {
    log_message('error', 'Posts fetch failure', ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
