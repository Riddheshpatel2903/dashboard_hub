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
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';

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

function formatLocalPost(array $post): array {
    return [
        'id' => 0,
        'hub_post_id' => (int)($post['id'] ?? 0),
        'content' => $post['content'] ?? '',
        'status' => $post['status'] ?? 'draft',
        'platform' => $post['platform'] ?? 'unknown',
        'media_path' => $post['media_path'] ?? null,
        'scheduled_at' => $post['scheduled_at'] ?? null,
        'published_at' => $post['published_at'] ?? $post['created_at'] ?? null,
        'created_at' => $post['created_at'] ?? null,
        'external_post_id' => $post['external_post_id'] ?? null,
        'views_count' => 0,
        'likes_count' => 0,
        'comments_count' => 0,
        'source' => 'local'
    ];
}

function normalizeFacebookMetrics(array $postItem, array $insights = []): array {
    $likes = (int)($postItem['likes']['summary']['total_count'] ?? 0);
    $comments = (int)($postItem['comments']['summary']['total_count'] ?? 0);
    $shares = (int)($postItem['shares']['count'] ?? 0);
    $reach = null;
    $impressions = null;
    $clicks = null;
    $engagement = null;

    foreach ($insights as $insight) {
        if (empty($insight['name'])) {
            continue;
        }
        $value = $insight['values'][0]['value'] ?? null;
        if ($value === null) {
            continue;
        }
        if ($insight['name'] === 'post_impressions') {
            $impressions = (int)$value;
            $reach = $reach ?? (int)$value;
        } elseif ($insight['name'] === 'post_engaged_users') {
            $engagement = (int)$value;
        } elseif ($insight['name'] === 'post_reactions_by_type_total') {
            $engagement = $engagement ?? (int)$value;
        }
    }

    return [
        'likes' => $likes,
        'comments' => $comments,
        'shares' => $shares,
        'reach' => $reach,
        'impressions' => $impressions,
        'clicks' => $clicks,
        'engagement' => $engagement,
    ];
}

function normalizeYouTubeMetrics(array $videoItem, array $analytics = []): array {
    $stats = $videoItem['statistics'] ?? [];
    $metrics = [
        'views' => isset($stats['viewCount']) ? (int)$stats['viewCount'] : 0,
        'likes' => isset($stats['likeCount']) ? (int)$stats['likeCount'] : 0,
        'comments' => isset($stats['commentCount']) ? (int)$stats['commentCount'] : 0,
        'favorites' => isset($stats['favoriteCount']) ? (int)$stats['favoriteCount'] : 0,
        'watch_time' => null,
        'estimated_minutes_watched' => null,
        'average_view_duration' => null,
        'ctr' => null,
        'subscribers_gained' => null,
        'subscribers_lost' => null,
    ];

    if (!empty($analytics['rows']) && is_array($analytics['rows'])) {
        foreach ($analytics['rows'] as $row) {
            $rowValues = array_values($row);
            if (count($rowValues) < 2) {
                continue;
            }
            $metrics['watch_time'] = $rowValues[0] ?? null;
            $metrics['estimated_minutes_watched'] = $rowValues[1] ?? null;
            $metrics['average_view_duration'] = $rowValues[2] ?? null;
            $metrics['ctr'] = $rowValues[3] ?? null;
            $metrics['subscribers_gained'] = $rowValues[4] ?? null;
            $metrics['subscribers_lost'] = $rowValues[5] ?? null;
            break;
        }
    }

    return $metrics;
}

try {
    ensurePlatformPostsTable($pdo);

    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    $platformFilter = trim(strtolower($_GET['platform'] ?? ''));
    $includePlatform = !isset($_GET['include_platform']) || in_array(strtolower($_GET['include_platform'] ?? '1'), ['1', 'true', 'yes'], true);
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;

    if ($postId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id
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

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'post' => formatLocalPost([
                'id' => $post['id'],
                'content' => $post['content'],
                'status' => $post['status'],
                'platform' => $post['platform'],
                'media_path' => $post['media_path'],
                'scheduled_at' => $post['scheduled_at'],
                'published_at' => $post['published_at'],
                'created_at' => $post['created_at'],
                'external_post_id' => $post['external_post_id'],
            ])
        ]);
        exit();
    }

    $formatted = [];

    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE p.client_id = :client_id AND p.status IN ('scheduled', 'failed', 'queued', 'published')
        ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue('client_id', $client_id, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll() ?: [];

    foreach ($posts as $post) {
        if (!empty($platformFilter) && strtolower($post['platform'] ?? '') !== $platformFilter) {
            continue;
        }
        $formatted[] = formatLocalPost($post);
    }

    if ($includePlatform) {
        $connStmt = $pdo->prepare("
            SELECT pc.id, pc.platform, pc.external_account_id, pt.access_token_encrypted
            FROM platform_connections pc
            LEFT JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.status = 'connected'
            ORDER BY pc.id ASC
        ");
        $connStmt->execute(['client_id' => $client_id]);
        $connections = $connStmt->fetchAll() ?: [];

        foreach ($connections as $conn) {
            $platform = strtolower($conn['platform'] ?? '');
            if ($platform === 'whatsapp') {
                continue;
            }
            if (!empty($platformFilter) && $platform !== $platformFilter) {
                continue;
            }

            $token = !empty($conn['access_token_encrypted']) ? decrypt($conn['access_token_encrypted']) : '';
            if (empty($token)) {
                continue;
            }

            try {
                if ($platform === 'facebook') {
                    $recentPostsRaw = FacebookHandler::getRecentPosts($token, $conn['external_account_id'], $limit);
                    if (!empty($recentPostsRaw['data'])) {
                        foreach ($recentPostsRaw['data'] as $postItem) {
                            $postIdValue = $postItem['id'] ?? '';
                            if (empty($postIdValue)) {
                                continue;
                            }

                            $metrics = ['likes' => 0, 'comments' => 0, 'shares' => 0, 'reach' => null, 'impressions' => null, 'clicks' => null, 'engagement' => null];
                            $insights = [];
                            try {
                                $rawInsights = FacebookHandler::getInsights($token, $postIdValue, ['post_impressions', 'post_engaged_users', 'post_reactions_by_type_total']);
                                $insights = $rawInsights['data'] ?? [];
                                $metrics = normalizeFacebookMetrics($postItem, $insights);
                            } catch (Exception $insightEx) {
                                log_message('warning', 'Facebook post insights unavailable', ['post_id' => $postIdValue, 'error' => $insightEx->getMessage()]);
                            }

                            $entry = [
                                'id' => 0,
                                'hub_post_id' => null,
                                'content' => $postItem['message'] ?? '',
                                'status' => 'published',
                                'platform' => 'facebook',
                                'media_path' => $postItem['attachments']['data'][0]['media']['image']['src'] ?? ($postItem['full_picture'] ?? null),
                                'scheduled_at' => null,
                                'published_at' => $postItem['created_time'] ?? null,
                                'created_at' => $postItem['created_time'] ?? null,
                                'external_post_id' => $postIdValue,
                                'views_count' => (int)($metrics['impressions'] ?? 0),
                                'likes_count' => (int)$metrics['likes'],
                                'comments_count' => (int)$metrics['comments'],
                                'duration' => null,
                                'metrics' => $metrics,
                                'source' => 'facebook'
                            ];

                            $formatted[] = $entry;
                            upsertPlatformPost($pdo, $client_id, 'facebook', [
                                'platform_post_id' => $postIdValue,
                                'message' => $postItem['message'] ?? '',
                                'created_time' => $postItem['created_time'] ?? null,
                                'permalink' => $postItem['permalink_url'] ?? null,
                                'picture' => $postItem['full_picture'] ?? null,
                                'attachments' => $postItem['attachments'] ?? null,
                                'reactions' => [
                                    'likes' => $metrics['likes'],
                                    'comments' => $metrics['comments'],
                                    'shares' => $metrics['shares'],
                                ],
                                'comments_count' => $metrics['comments'],
                                'shares_count' => $metrics['shares'],
                                'impressions' => $metrics['impressions'],
                                'reach' => $metrics['reach'],
                                'clicks' => $metrics['clicks'],
                                'engagement' => $metrics['engagement'],
                                'published_at' => $postItem['created_time'] ?? null,
                                'raw_platform_response' => $postItem,
                            ]);
                        }
                    }
                } elseif ($platform === 'youtube') {
                    $recentVideosRaw = YouTubeHandler::getRecentChannelVideos($token, $limit);
                    if (!empty($recentVideosRaw['items'])) {
                        foreach ($recentVideosRaw['items'] as $videoItem) {
                            $videoId = $videoItem['id'] ?? '';
                            if (empty($videoId)) {
                                continue;
                            }

                            $metrics = ['views' => 0, 'likes' => 0, 'comments' => 0, 'favorites' => 0, 'watch_time' => null, 'estimated_minutes_watched' => null, 'average_view_duration' => null, 'ctr' => null, 'subscribers_gained' => null, 'subscribers_lost' => null];
                            try {
                                $analyticsRaw = YouTubeHandler::getVideoAnalytics($token, $videoId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
                                $metrics = normalizeYouTubeMetrics($videoItem, $analyticsRaw);
                            } catch (Exception $analyticsEx) {
                                log_message('warning', 'YouTube video analytics unavailable', ['video_id' => $videoId, 'error' => $analyticsEx->getMessage()]);
                            }

                            $thumbs = $videoItem['snippet']['thumbnails'] ?? [];
                            $thumbnailUrl = $thumbs['maxres']['url'] ?? $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? '';
                            $entry = [
                                'id' => 0,
                                'hub_post_id' => null,
                                'content' => $videoItem['snippet']['title'] ?? '',
                                'status' => 'published',
                                'platform' => 'youtube',
                                'media_path' => $thumbnailUrl,
                                'scheduled_at' => null,
                                'published_at' => $videoItem['snippet']['publishedAt'] ?? null,
                                'created_at' => $videoItem['snippet']['publishedAt'] ?? null,
                                'external_post_id' => $videoId,
                                'views_count' => (int)($metrics['views'] ?? 0),
                                'likes_count' => (int)($metrics['likes'] ?? 0),
                                'comments_count' => (int)($metrics['comments'] ?? 0),
                                'duration' => $videoItem['contentDetails']['duration'] ?? null,
                                'metrics' => $metrics,
                                'source' => 'youtube'
                            ];

                            $formatted[] = $entry;
                            upsertPlatformPost($pdo, $client_id, 'youtube', [
                                'platform_post_id' => $videoId,
                                'message' => $videoItem['snippet']['title'] ?? '',
                                'created_time' => $videoItem['snippet']['publishedAt'] ?? null,
                                'permalink' => 'https://www.youtube.com/watch?v=' . $videoId,
                                'picture' => $thumbnailUrl,
                                'attachments' => ['description' => $videoItem['snippet']['description'] ?? '', 'duration' => $videoItem['contentDetails']['duration'] ?? null],
                                'reactions' => [
                                    'views' => $metrics['views'],
                                    'likes' => $metrics['likes'],
                                    'comments' => $metrics['comments'],
                                ],
                                'comments_count' => $metrics['comments'],
                                'shares_count' => 0,
                                'impressions' => $metrics['views'],
                                'reach' => null,
                                'clicks' => null,
                                'engagement' => $metrics['likes'] + $metrics['comments'],
                                'published_at' => $videoItem['snippet']['publishedAt'] ?? null,
                                'raw_platform_response' => $videoItem,
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                log_message('warning', 'Platform post fetch failed', ['platform' => $platform, 'error' => $e->getMessage()]);
            }
        }
    }

    usort($formatted, function ($a, $b) {
        $dateA = $a['published_at'] ?: ($a['scheduled_at'] ?: $a['created_at']);
        $dateB = $b['published_at'] ?: ($b['scheduled_at'] ?: $b['created_at']);
        return strcmp($dateB, $dateA);
    });

    $formatted = array_slice($formatted, 0, $limit);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'posts' => $formatted,
        'local_posts' => array_values(array_filter($formatted, function ($item) { return ($item['source'] ?? '') === 'local'; })),
        'platform_posts' => array_values(array_filter($formatted, function ($item) { return ($item['source'] ?? '') !== 'local'; })),
    ]);
} catch (Exception $e) {
    log_message('error', 'Posts fetch failure', ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
