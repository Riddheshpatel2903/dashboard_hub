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
        if (!in_array('external_post_id', $cols)) {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN `external_post_id` VARCHAR(255) DEFAULT NULL");
        }

        // Migration: Make hub_post_id nullable to support imported posts (which do not have a hub post ID)
        // without violating the UNIQUE constraint
        $pdo->exec("ALTER TABLE `posts_cache` MODIFY `hub_post_id` INT NULL");
        $pdo->exec("UPDATE `posts_cache` SET `hub_post_id` = NULL WHERE `hub_post_id` = 0");
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

                    // Auto-import historical platform posts/videos into posts_cache table
                    $stmtCheck = $pdo->prepare("
                        SELECT id FROM posts_cache 
                        WHERE client_id = :client_id 
                          AND (
                            (hub_post_id = :hId AND hub_post_id > 0) 
                            OR (external_post_id = :extId AND external_post_id IS NOT NULL AND external_post_id != '')
                          ) 
                        LIMIT 1
                    ");
                    $stmtIns = $pdo->prepare("
                        INSERT INTO posts_cache (hub_post_id, client_id, content, status, platform, media_path, published_at, created_at, views_count, likes_count, comments_count, duration, external_post_id)
                        VALUES (:hId, :client_id, :content, 'published', :platform, :media_path, :pubDate, :pubDate, :v, :l, :c, :d, :extId)
                    ");
                    $stmtUpdStats = $pdo->prepare("
                        UPDATE posts_cache
                        SET views_count = :v, likes_count = :l, comments_count = :c
                        WHERE id = :id
                    ");

                    foreach ($aRes['metrics'] as $m) {
                        $mName = strtolower($m['metric_name']);
                        
                        // 1. YouTube Video
                        if (strpos($mName, 'yt_video_') === 0 && !empty($m['value'])) {
                            $vData = json_decode($m['value'], true);
                            if (!empty($vData['video_id'])) {
                                $vId = $vData['video_id'];
                                $stmtCheck->execute(['client_id' => $clientId, 'hId' => null, 'extId' => $vId]);
                                $existing = $stmtCheck->fetch();
                                // Use YouTube thumbnail URL as media_path (high quality > medium > default)
                                $thumbUrl = !empty($vData['thumbnail_url']) ? $vData['thumbnail_url'] : null;
                                if (!$existing) {
                                    $pubDate = !empty($vData['published_at']) ? date('Y-m-d H:i:s', strtotime($vData['published_at'])) : date('Y-m-d H:i:s');
                                    $stmtIns->execute([
                                        'hId'       => null,
                                        'client_id' => $clientId,
                                        'content'   => $vData['title'] ?? ('YouTube Video ' . $vId),
                                        'platform'  => 'youtube',
                                        'media_path'=> $thumbUrl,
                                        'pubDate'   => $pubDate,
                                        'v'         => (int)($vData['views'] ?? 0),
                                        'l'         => (int)($vData['likes'] ?? 0),
                                        'c'         => (int)($vData['comments'] ?? 0),
                                        'd'         => $vData['duration'] ?? null,
                                        'extId'     => $vId
                                    ]);
                                } else {
                                    // Update stats AND thumbnail if it changed
                                    $stmtUpdYt = $pdo->prepare("
                                        UPDATE posts_cache
                                        SET views_count = :v, likes_count = :l, comments_count = :c,
                                            media_path = COALESCE(NULLIF(media_path, 'video.mp4'), :thumb, media_path)
                                        WHERE id = :id
                                    ");
                                    $stmtUpdYt->execute([
                                        'v'     => (int)($vData['views'] ?? 0),
                                        'l'     => (int)($vData['likes'] ?? 0),
                                        'c'     => (int)($vData['comments'] ?? 0),
                                        'thumb' => $thumbUrl,
                                        'id'    => $existing['id']
                                    ]);
                                }
                            }
                        }

                        // 2. Facebook Post
                        if (strpos($mName, 'fb_post_') === 0 && !empty($m['value'])) {
                            $pData = json_decode($m['value'], true);
                            if (!empty($pData['post_id'])) {
                                $pId = $pData['post_id'];
                                $stmtCheck->execute(['client_id' => $clientId, 'hId' => null, 'extId' => $pId]);
                                $existing = $stmtCheck->fetch();
                                if (!$existing) {
                                    $pubDate = !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s');
                                    $stmtIns->execute([
                                        'hId'       => null,
                                        'client_id' => $clientId,
                                        'content'   => !empty($pData['message']) ? $pData['message'] : 'Facebook Post',
                                        'platform'  => 'facebook',
                                        'media_path'=> !empty($pData['media_url']) ? $pData['media_url'] : null,
                                        'pubDate'   => $pubDate,
                                        'v'         => 0,
                                        'l'         => (int)($pData['likes'] ?? 0),
                                        'c'         => (int)($pData['comments'] ?? 0),
                                        'd'         => null,
                                        'extId'     => $pId
                                    ]);
                                } else {
                                    $stmtUpdStats->execute([
                                        'v'  => 0,
                                        'l'  => (int)($pData['likes'] ?? 0),
                                        'c'  => (int)($pData['comments'] ?? 0),
                                        'id' => $existing['id']
                                    ]);
                                }
                            }
                        }

                        // 3. Instagram Post
                        if (strpos($mName, 'ig_post_') === 0 && !empty($m['value'])) {
                            $pData = json_decode($m['value'], true);
                            if (!empty($pData['media_id'])) {
                                $mId = $pData['media_id'];
                                $stmtCheck->execute(['client_id' => $clientId, 'hId' => null, 'extId' => $mId]);
                                $existing = $stmtCheck->fetch();
                                if (!$existing) {
                                    $pubDate = !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s');
                                    $isVid = (strtoupper($pData['media_type'] ?? '') === 'VIDEO');
                                    $stmtIns->execute([
                                        'hId'       => null,
                                        'client_id' => $clientId,
                                        'content'   => !empty($pData['caption']) ? $pData['caption'] : 'Instagram Post',
                                        'platform'  => 'instagram',
                                        'media_path'=> !empty($pData['media_url']) ? $pData['media_url'] : null,
                                        'pubDate'   => $pubDate,
                                        'v'         => 0,
                                        'l'         => (int)($pData['likes'] ?? 0),
                                        'c'         => (int)($pData['comments'] ?? 0),
                                        'd'         => $isVid ? '00:00' : 'Image',
                                        'extId'     => $mId
                                    ]);
                                } else {
                                    $stmtUpdStats->execute([
                                        'v'  => 0,
                                        'l'  => (int)($pData['likes'] ?? 0),
                                        'c'  => (int)($pData['comments'] ?? 0),
                                        'id' => $existing['id']
                                    ]);
                                }
                            }
                        }

                        // 4. Google Business Profile Post
                        if (strpos($mName, 'gbp_post_') === 0 && !empty($m['value'])) {
                            $pData = json_decode($m['value'], true);
                            if (!empty($pData['post_id'])) {
                                $pId = $pData['post_id'];
                                $stmtCheck->execute(['client_id' => $clientId, 'hId' => null, 'extId' => $pId]);
                                $existing = $stmtCheck->fetch();
                                if (!$existing) {
                                    $pubDate = !empty($pData['published_at']) ? date('Y-m-d H:i:s', strtotime($pData['published_at'])) : date('Y-m-d H:i:s');
                                    $stmtIns->execute([
                                        'hId'       => null,
                                        'client_id' => $clientId,
                                        'content'   => !empty($pData['summary']) ? $pData['summary'] : 'Google Profile Post',
                                        'platform'  => 'google_business',
                                        'media_path'=> !empty($pData['media_url']) ? $pData['media_url'] : null,
                                        'pubDate'   => $pubDate,
                                        'v'         => 0,
                                        'l'         => 0,
                                        'c'         => 0,
                                        'd'         => null,
                                        'extId'     => $pId
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
            SELECT id, platform, hub_post_id, external_post_id 
            FROM posts_cache 
            WHERE client_id = :client_id AND status = 'published'
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtPosts->execute(['client_id' => $clientId]);
        $pubPosts = $stmtPosts->fetchAll();

        if (!empty($pubPosts)) {
            foreach ($pubPosts as $p) {
                // Prefer external_post_id with post_id=0 so the Hub queries via platform_connections
                // rather than the Hub posts table (rows there may be deleted for old/synced posts)
                $extId = $p['external_post_id'] ?? '';
                if (!empty($extId)) {
                    $pRes = hubGetAnalytics($clientId, $p['platform'], 0, null, null, $extId);
                } elseif (!empty($p['hub_post_id'])) {
                    $pRes = hubGetAnalytics($clientId, $p['platform'], (int)$p['hub_post_id']);
                } else {
                    continue;
                }
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

        // 2.5 Check for posts with missing media and remove them.
        // IMPORTANT: Only delete if the Hub is running locally (same machine as Dashboard).
        // When Hub is on a remote server (Hostinger), file_exists() on a local path will ALWAYS
        // return false even though the file exists remotely — causing all posts to be wrongly deleted.
        $hubUrl = defined('HUB_BASE_URL') ? HUB_BASE_URL : '';
        $hubIsLocal = (
            strpos($hubUrl, 'localhost') !== false ||
            strpos($hubUrl, '127.0.0.1') !== false ||
            strpos($hubUrl, '::1') !== false
        );

        if ($hubIsLocal) {
            // Hub is local — safe to check file system directly
            $stmtMediaCheck = $pdo->prepare("
                SELECT id, hub_post_id, platform, media_path, external_post_id
                FROM posts_cache
                WHERE client_id = :client_id
                  AND media_path IS NOT NULL
                  AND media_path != ''
                  AND status != 'deleted'
            ");
            $stmtMediaCheck->execute(['client_id' => $clientId]);
            $postsToCheck = $stmtMediaCheck->fetchAll();

            foreach ($postsToCheck as $post) {
                $mPath = $post['media_path'];
                // Only check local relative paths, not full HTTP URLs
                if (!preg_match('/^https?:\/\//i', $mPath)) {
                    $localPath = __DIR__ . '/../../hub/uploads/' . ltrim(str_replace('uploads/', '', $mPath), '/');
                    if (!file_exists($localPath)) {
                        $hubPostId = (int)$post['hub_post_id'];
                        $platform  = $post['platform'];
                        $externalPostId = $post['external_post_id'] ?? '';
                        hubDelete($clientId, $hubPostId, $platform, $externalPostId);
                        $stmtDel = $pdo->prepare("DELETE FROM posts_cache WHERE id = :id");
                        $stmtDel->execute(['id' => $post['id']]);
                    }
                }
            }
        }
        // When Hub is remote: media files live on the remote server.
        // Do NOT delete posts based on local file checks — the files exist remotely.


        // 3. Purge orphan local upload files — only when Hub runs on the same machine as Dashboard.
        if ($hubIsLocal) {
            require_once __DIR__ . '/../../hub/storage/StorageService.php';
            StorageService::cleanOrphanUploads($pdo);
        }

    } catch (Exception $e) {
        error_log("syncClientAnalytics warning: " . $e->getMessage());
    }
}
