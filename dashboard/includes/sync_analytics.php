<?php
/**
 * Sync Client Analytics & DB Storage Helper.
 * Deployed at: /dashboard/includes/sync_analytics.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/hub_client.php';

/**
 * Ensures analytics_cache table and post metric columns exist in database.
 */
function ensureAnalyticsDatabaseTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `analytics_cache` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `client_id` INT NOT NULL,
              `platform` VARCHAR(50) NOT NULL,
              `metric_name` VARCHAR(100) NOT NULL,
              `metric_value` TEXT NOT NULL,
              `period` VARCHAR(50) DEFAULT 'lifetime',
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `idx_client_plat_metric` (`client_id`, `platform`, `metric_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Add metric columns to posts_cache if not already present
        $cols = $pdo->query("SHOW COLUMNS FROM `posts_cache`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('views_count', $cols)) {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN `views_count` INT DEFAULT 0");
        }
        if (!in_array('likes_count', $cols)) {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN `likes_count` INT DEFAULT 0");
        }
        if (!in_array('comments_count', $cols)) {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN `comments_count` INT DEFAULT 0");
        }
        if (!in_array('duration', $cols)) {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN `duration` VARCHAR(50) DEFAULT NULL");
        }
    } catch (Exception $e) {
        error_log("Analytics schema sync warning: " . $e->getMessage());
    }
}

/**
 * Fetches and persists live analytics data for a client into the local DB upon login or refresh.
 */
function syncClientAnalytics($clientId, $pdo) {
    if (!$clientId || !($pdo instanceof PDO)) return;

    ensureAnalyticsDatabaseTables($pdo);

    try {
        // 1. Check connected platform accounts
        $connRes = hubGetConnectionsStatus($clientId);
        if (!empty($connRes['connections']) && is_array($connRes['connections'])) {
            foreach ($connRes['connections'] as $conn) {
                if (($conn['status'] ?? '') !== 'connected') continue;
                $platform = $conn['platform'];

                // Fetch live account analytics from Hub
                $aRes = hubGetAnalytics($clientId, $platform, 0);
                if (!empty($aRes['success']) && is_array($aRes['metrics'])) {
                    $stmtCache = $pdo->prepare("
                        INSERT INTO analytics_cache (client_id, platform, metric_name, metric_value, period)
                        VALUES (:client_id, :platform, :metric_name, :val, :period)
                        ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), updated_at = CURRENT_TIMESTAMP
                    ");
                    foreach ($aRes['metrics'] as $m) {
                        $stmtCache->execute([
                            'client_id'   => $clientId,
                            'platform'    => $platform,
                            'metric_name' => strtolower($m['metric_name']),
                            'val'         => is_array($m['value']) ? json_encode($m['value']) : (string)$m['value'],
                            'period'      => $m['period'] ?? 'lifetime'
                        ]);
                    }
                    // Auto-import historical channel videos & posts into posts_cache table
                    $stmtCheck = $pdo->prepare("SELECT id FROM posts_cache WHERE client_id = :client_id AND hub_post_id = :hId LIMIT 1");
                    $stmtIns = $pdo->prepare("
                        INSERT INTO posts_cache (hub_post_id, client_id, content, status, platform, media_path, published_at, created_at, views_count, likes_count, comments_count, duration)
                        VALUES (:hId, :client_id, :content, 'published', :platform, :media_path, :pubDate, :pubDate, :v, :l, :c, :d)
                    ");

                    foreach ($aRes['metrics'] as $m) {
                        $mName = strtolower($m['metric_name']);
                        if (strpos($mName, 'yt_video_') === 0 && !empty($m['value'])) {
                            $vData = json_decode($m['value'], true);
                            if (!empty($vData['video_id'])) {
                                $vId = $vData['video_id'];
                                $stmtCheck->execute(['client_id' => $clientId, 'hId' => $vId]);
                                if (!$stmtCheck->fetch()) {
                                    $pubDate = !empty($vData['published_at']) ? date('Y-m-d H:i:s', strtotime($vData['published_at'])) : date('Y-m-d H:i:s');
                                    $stmtIns->execute([
                                        'hId'       => $vId,
                                        'client_id' => $clientId,
                                        'content'   => $vData['title'] ?? ('YouTube Video ' . $vId),
                                        'platform'  => 'youtube',
                                        'media_path'=> 'video.mp4',
                                        'pubDate'   => $pubDate,
                                        'v'         => (int)($vData['views'] ?? 0),
                                        'l'         => (int)($vData['likes'] ?? 0),
                                        'c'         => (int)($vData['comments'] ?? 0),
                                        'd'         => $vData['duration'] ?? null
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Fetch and store live metrics for top published posts in posts_cache
        $stmtPosts = $pdo->prepare("
            SELECT id, platform, hub_post_id 
            FROM posts_cache 
            WHERE client_id = :client_id AND status = 'published'
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtPosts->execute(['client_id' => $clientId]);
        $pubPosts = $stmtPosts->fetchAll();

        if (!empty($pubPosts)) {
            foreach ($pubPosts as $p) {
                $pRes = hubGetAnalytics($clientId, $p['platform'], $p['id']);
                if (!empty($pRes['success']) && is_array($pRes['metrics'])) {
                    $pViews = 0; $pLikes = 0; $pComments = 0; $pDur = null;
                    foreach ($pRes['metrics'] as $pm) {
                        $name = strtolower($pm['metric_name']);
                        if (in_array($name, ['view_count', 'views', 'reach', 'impressions'])) $pViews = (int)$pm['value'];
                        if (in_array($name, ['like_count', 'likes', 'engagement'])) $pLikes = (int)$pm['value'];
                        if (in_array($name, ['comment_count', 'comments'])) $pComments = (int)$pm['value'];
                        if ($name === 'duration') $pDur = (string)$pm['value'];
                    }

                    $stmtUpd = $pdo->prepare("
                        UPDATE posts_cache 
                        SET views_count = :v, likes_count = :l, comments_count = :c, duration = :d
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        'v'  => $pViews,
                        'l'  => $pLikes,
                        'c'  => $pComments,
                        'd'  => $pDur,
                        'id' => $p['id']
                    ]);
                }
            }
        }

        // 3. Automatically purge any orphan files from upload folders that belong to deleted posts
        require_once __DIR__ . '/../../hub/storage/StorageService.php';
        StorageService::cleanOrphanUploads($pdo);

    } catch (Exception $e) {
        error_log("syncClientAnalytics warning: " . $e->getMessage());
    }
}
