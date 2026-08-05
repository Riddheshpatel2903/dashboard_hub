<?php

/**
 * Queue Worker Job.
 * Processes batches of queued posts using transaction-level locking.
 * Runs via CLI or HTTP trigger secured with a token.
 */

// Allow long execution time and continue running even if HTTP client aborts/disconnects
ignore_user_abort(true);
set_time_limit(0);

$startTime = microtime(true);

$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../storage/StorageService.php';
require_once __DIR__ . '/../utils/token_helper.php';

// Include Platform Handlers
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/WhatsAppHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

/**
 * Ensure the posts.title column exists before querying or inserting posts.
 * If it is missing, attempt to add it automatically so scheduled/video posts can still work.
 */
function ensurePostsTitleColumn(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM posts LIKE 'title'");
        $hasColumn = (bool) $stmt->fetch();
        if ($hasColumn) {
            return true;
        }

        $pdo->exec('ALTER TABLE posts ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER content');
        log_message('info', 'Queue Worker: Added missing posts.title column to posts table');
        return true;
    } catch (Exception $e) {
        log_message('warning', 'Queue Worker: posts.title column missing and could not be added', ['error' => $e->getMessage()]);
        return false;
    }
}

// 1. Endpoint Security
$isCli = (PHP_SAPI === 'cli');
$passedToken = $_GET['token'] ?? '';

if (!$isCli && $passedToken !== CRON_SECRET) {
    http_response_code(403);
    log_message('warning', 'Queue Worker: Unauthorized execution attempt blocked from IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing secret cron token.']);
    exit();
}

log_message('info', 'Queue Worker: Started execution.');

$hasTitleColumn = ensurePostsTitleColumn($pdo);
$selectTitleColumn = $hasTitleColumn ? 'p.title, ' : '';

// --- ASYNC DELETIONS PROCESSING ---
try {
    // Fetch posts marked for async deletion
    $stmtDel = $pdo->query("
        SELECT p.id as post_id, p.client_id, p.media_temp_path, p.external_post_id,
               pc.platform
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE p.status = 'pending_delete'
    ");
    $deletingPosts = $stmtDel->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($deletingPosts)) {
        log_message('info', 'Queue Worker: Found ' . count($deletingPosts) . ' pending deletes.');

        // Connect to Dashboard Database for Cache Deletes
        $dashPdo = null;
        try {
            $dashPdo = new PDO(
                'mysql:host=' . DASHBOARD_DB_HOST . ';port=3306;dbname=' . DASHBOARD_DB_NAME . ';charset=utf8mb4',
                DASHBOARD_DB_USER,
                DASHBOARD_DB_PASS
            );
            $dashPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            log_message('error', 'Queue Worker Deletes: Failed to connect to dashboard_db', ['error' => $e->getMessage()]);
        }

        foreach ($deletingPosts as $dp) {
            $postId = (int) $dp['post_id'];
            $clientId = (int) $dp['client_id'];
            $platform = $dp['platform'];
            $externalPostId = $dp['external_post_id'];
            $mediaPath = $dp['media_temp_path'] ?? '';

            log_message('info', "Queue Worker Deletes: Processing deletion for Post ID {$postId} on {$platform}");

            $externalDeleteSucceeded = true;

            if (!empty($externalPostId)) {
                $token = get_valid_platform_token($pdo, $clientId, $platform);
                try {
                    switch ($platform) {
                        case 'facebook':
                            $fbPostId = ensureFacebookCompositeId($pdo, $clientId, $externalPostId);
                            FacebookHandler::deletePost($token, $fbPostId);
                            break;

                        case 'instagram':
                            if (empty($token)) {
                                $token = get_valid_platform_token($pdo, $clientId, 'facebook');
                            }
                            InstagramHandler::deletePost($token, $externalPostId);
                            break;

                        case 'linkedin':
                            LinkedInHandler::deletePost($token, $externalPostId);
                            break;

                        case 'google_business':
                            GoogleBusinessHandler::deletePost($token, $externalPostId);
                            break;

                        case 'youtube':
                            YouTubeHandler::deleteVideo($token, $externalPostId);
                            break;
                    }
                } catch (Exception $platEx) {
                    $err = $platEx->getMessage();
                    $alreadyDeleted = false;
                    $deletedPhrases = ['not found', 'does not exist', 'invalid object', 'unsupported get request', 'cannot be found'];
                    foreach ($deletedPhrases as $phrase) {
                        if (stripos($err, $phrase) !== false) {
                            $alreadyDeleted = true;
                            break;
                        }
                    }
                    if (!$alreadyDeleted && !($platform === 'instagram' && (stripos($err, 'permissions') !== false || stripos($err, 'Code 10') !== false))) {
                        $externalDeleteSucceeded = false;
                        log_message('error', "Queue Worker Deletes: Platform API delete failed for Post ID {$postId}", ['error' => $err]);
                    }
                }
            }

            if ($externalDeleteSucceeded) {
                // Delete media file
                if (!empty($mediaPath)) {
                    StorageService::deletePostMedia($mediaPath, $clientId);
                }

                // Delete from posts table
                $pdo->prepare('DELETE FROM posts WHERE id = :post_id')->execute(['post_id' => $postId]);

                // Clear from platform_posts cache table as well
                if (!empty($externalPostId)) {
                    $pdo->prepare('
                        DELETE FROM platform_posts 
                        WHERE platform = :platform AND platform_post_id = :external_post_id AND client_id = :client_id
                    ')->execute([
                        'platform' => $platform,
                        'external_post_id' => $externalPostId,
                        'client_id' => $clientId
                    ]);
                }
                log_message('info', "Queue Worker Deletes: Hard deleted Post ID {$postId} from Hub database and cleared platform_posts cache.");

                // Delete from Dashboard posts_cache table
                if ($dashPdo) {
                    try {
                        if (!empty($externalPostId)) {
                            $stmtDelCache = $dashPdo->prepare('
                                DELETE FROM posts_cache 
                                WHERE (hub_post_id = :hub_post_id OR (platform = :platform AND external_post_id = :external_post_id)) 
                                  AND client_id = :client_id
                            ');
                            $stmtDelCache->execute([
                                'hub_post_id' => $postId,
                                'platform' => $platform,
                                'external_post_id' => $externalPostId,
                                'client_id' => $clientId
                            ]);
                        } else {
                            $stmtDelCache = $dashPdo->prepare('
                                DELETE FROM posts_cache 
                                WHERE hub_post_id = :hub_post_id AND client_id = :client_id
                            ');
                            $stmtDelCache->execute([
                                'hub_post_id' => $postId,
                                'client_id' => $clientId
                            ]);
                        }
                        log_message('info', "Queue Worker Deletes: Deleted Post ID {$postId} from Dashboard posts_cache.");
                    } catch (Exception $cacheEx) {
                        log_message('error', "Queue Worker Deletes: Failed to clear Dashboard cache for Post ID {$postId}", ['error' => $cacheEx->getMessage()]);
                    }
                }
            } else {
                // Mark as failed delete so it doesn't loop forever
                $pdo->prepare("UPDATE posts SET status = 'delete_failed' WHERE id = :post_id")->execute(['post_id' => $postId]);
            }
        }
    }
} catch (Exception $delEx) {
    log_message('error', 'Queue Worker Deletes: Error in deletion loop: ' . $delEx->getMessage());
}
// --- END ASYNC DELETIONS ---

try {
    $pdo->beginTransaction();

    // 2. Fetch and Lock queued posts limited by batch size
    $stmt = $pdo->prepare("
        SELECT p.id as post_id, p.client_id, p.content, {$selectTitleColumn}p.media_temp_path, 
               pc.platform, pc.external_account_id, pt.access_token_encrypted,
               p.retry_count
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE p.status = 'queued'
        LIMIT :batch_size
        FOR UPDATE
    ");
    $stmt->bindValue(':batch_size', QUEUE_BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $queuedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queuedPosts)) {
        $pdo->commit();
        $duration = round(microtime(true) - $startTime, 4);
        log_message('info', 'Queue Worker: No queued posts found. Finished.', ['execution_time_seconds' => $duration]);
        if (!$isCli) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'processed_posts_count' => 0, 'execution_time' => $duration]);
        }
        exit();
    }

    $postIds = array_column($queuedPosts, 'post_id');
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    // 3. Mark posts as 'publishing' immediately to prevent overlapping workers
    $stmtUpdatePublishing = $pdo->prepare("
        UPDATE posts 
        SET status = 'publishing', last_attempt_at = NOW() 
        WHERE id IN ($placeholders)
    ");
    $stmtUpdatePublishing->execute($postIds);

    $pdo->commit();

    // Connect to Dashboard Database for Cache Updates
    $dashPdo = null;
    try {
        $dashPdo = new PDO(
            'mysql:host=' . DASHBOARD_DB_HOST . ';port=3306;dbname=' . DASHBOARD_DB_NAME . ';charset=utf8mb4',
            DASHBOARD_DB_USER,
            DASHBOARD_DB_PASS
        );
        $dashPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $dashPdo->exec("SET time_zone = '+05:30'");
        } catch (Exception $tzEx) {
            // Ignore
        }
    } catch (Exception $e) {
        log_message('error', 'Queue Worker: Failed to connect to dashboard_db during cache sync', ['error' => $e->getMessage()]);
    }

    $stmtUpdateCacheStatus = $dashPdo ? $dashPdo->prepare("
        UPDATE posts_cache 
        SET status = 'publishing', last_attempt_at = NOW() 
        WHERE hub_post_id = ?
    ") : null;

    if ($stmtUpdateCacheStatus) {
        foreach ($postIds as $id) {
            try {
                $stmtUpdateCacheStatus->execute([$id]);
            } catch (Exception $ex) {
                log_message('error', "Queue Worker: Failed to update status to publishing in posts_cache for post ID {$id}", ['error' => $ex->getMessage()]);
            }
        }
    }

    // 4. Process each post
    $processedCount = 0;

    foreach ($queuedPosts as $post) {
        $postId = $post['post_id'];
        $clientId = $post['client_id'];
        $platform = $post['platform'];
        $content = $post['content'];
        $title = isset($post['title']) && $post['title'] !== '' ? $post['title'] : null;
        $mediaTempPath = $post['media_temp_path'];
        $externalAccountId = $post['external_account_id'];
        $currentRetryCount = (int) $post['retry_count'];

        $token = get_valid_platform_token($pdo, $clientId, $platform);
        $mediaPublicUrl = $mediaTempPath ? StorageService::getPublicUrl($mediaTempPath) : null;

        $externalPostId = null;
        $responseBody = '';
        $httpStatusCode = 200;
        $success = false;

        if (empty($token)) {
            $token = '';  // fallback
        }

        try {
            if (empty($token)) {
                throw new Exception('Authentication token is invalid or expired, and could not be refreshed. Please reconnect the platform connection in settings.', 401);
            }
            // Publish using platform handler
            switch ($platform) {
                case 'facebook':
                    $localPath = null;
                    if ($mediaTempPath) {
                        $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                        if (!file_exists($localPath)) {
                            $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                            if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                                $localPath = $mediaTempPath;
                            }
                        }
                    }
                    $res = FacebookHandler::publishPost($token, $externalAccountId, $content, $mediaPublicUrl, $localPath);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'instagram':
                    if (empty($mediaPublicUrl)) {
                        throw new Exception('Instagram requires media. No media provided.');
                    }
                    $res = InstagramHandler::publishPost($token, $externalAccountId, $content, $mediaPublicUrl);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'whatsapp':
                    // Queue processing sends text message by default
                    $res = WhatsAppHandler::sendTextMessage($token, $externalAccountId, '[Recipient Placeholder]', $content);
                    $externalPostId = $res['messages'][0]['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'youtube':
                    $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                    if (!file_exists($localPath)) {
                        $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                        if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                            $localPath = $mediaTempPath;
                        }
                    }
                    $res = YouTubeHandler::uploadVideo($token, $localPath, $title, $content);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'linkedin':
                    $localPath = null;
                    if ($mediaTempPath) {
                        $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                        if (!file_exists($localPath)) {
                            $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                            if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                                $localPath = $mediaTempPath;
                            }
                        }
                    }
                    $res = LinkedInHandler::publishPost($token, $externalAccountId, $content, $localPath);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'google_business':
                    $res = GoogleBusinessHandler::createPost($token, $externalAccountId, $content, $mediaPublicUrl);
                    $externalPostId = $res['name'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                default:
                    throw new Exception("Unsupported platform: {$platform}");
            }

            verifyAndReconnectDb($pdo, $dashPdo, $clientId);
            try {
                $pdo->beginTransaction();

                // Verify the post still exists in posts table before inserting log
                $stmtCheck = $pdo->prepare('SELECT id FROM posts WHERE id = ? FOR UPDATE');
                $stmtCheck->execute([$postId]);
                if ($stmtCheck->fetch()) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE posts 
                        SET status = 'published', external_post_id = :ext_id, published_at = CURRENT_TIMESTAMP, retry_count = 0
                        WHERE id = :post_id
                    ");
                    $stmtUpdate->execute([
                        'ext_id' => $externalPostId,
                        'post_id' => $postId
                    ]);

                    $stmtLog = $pdo->prepare('
                        INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                        VALUES (:post_id, 200, :response, 1)
                    ');
                    $stmtLog->execute([
                        'post_id' => $postId,
                        'response' => $responseBody
                    ]);

                    // Sync with Dashboard Cache
                    if ($dashPdo) {
                        try {
                            $stmtDash = $dashPdo->prepare("
                                UPDATE posts_cache 
                                SET status = 'published', external_post_id = :ext_id, published_at = CURRENT_TIMESTAMP, retry_count = 0
                                WHERE hub_post_id = :hub_post_id
                            ");
                            $stmtDash->execute([
                                'ext_id' => $externalPostId,
                                'hub_post_id' => $postId
                            ]);
                        } catch (Exception $dashEx) {
                            log_message('error', "Queue Worker: Failed to update dashboard cache to published for post ID {$postId}", ['error' => $dashEx->getMessage()]);
                        }
                    }

                    log_message('info', "Queue Worker: Published post ID {$postId} successfully on {$platform}");
                    $processedCount++;
                } else {
                    log_message('warning', "Queue Worker: Post ID {$postId} was deleted externally during processing. Skipping DB success updates.");
                }

                $pdo->commit();
            } catch (Exception $dbEx) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                log_message('error', "Queue Worker: DB update failed for successful post ID {$postId}", ['error' => $dbEx->getMessage()]);
            }
        } catch (Exception $e) {
            $httpStatusCode = $e->getCode() ?: 500;
            $responseBody = $e->getMessage();

            // Simplify error message to raw simple text
            $cleanErrorMsg = $responseBody;
            if (strpos($cleanErrorMsg, '{') !== false) {
                $decoded = json_decode($cleanErrorMsg, true);
                if (!empty($decoded['error']['message'])) {
                    $cleanErrorMsg = $decoded['error']['message'];
                } elseif (!empty($decoded['error_description'])) {
                    $cleanErrorMsg = $decoded['error_description'];
                }
            }
            $cleanErrorMsg = preg_replace('/(?:Exception|Error):\s*/i', '', $cleanErrorMsg);
            $cleanErrorMsg = str_ireplace([
                'Instagram Graph Exception',
                'Facebook Graph API Exception',
                'YouTube API Exception',
                'LinkedIn API Exception'
            ], '', $cleanErrorMsg);
            $cleanErrorMsg = trim(preg_replace('/\(Code:\s*\d+\)/i', '', $cleanErrorMsg));
            $cleanErrorMsg = trim(preg_replace('/Code\s*\d+\s*:/i', '', $cleanErrorMsg));
            $cleanErrorMsg = trim(preg_replace('/#\d+/i', '', $cleanErrorMsg));
            $cleanErrorMsg = ltrim($cleanErrorMsg, ': ');
            $cleanErrorMsg = ucfirst($platform) . ' error: ' . $cleanErrorMsg;

            log_message('error', "Queue Worker: Failed publishing post ID {$postId} on {$platform}", ['error' => $cleanErrorMsg]);

            // Determine if error is temporary and retryable (e.g. timeout, rate limit, HTTP 5xx)
            $isRetryable = false;
            // Common temporary error indicators (cURL timeout is 28, rate limits 429 or code 4, 17, 368 etc. for Meta)
            if ($httpStatusCode === 429 || $httpStatusCode >= 500 || $httpStatusCode === 0 || strpos(strtolower($responseBody), 'timeout') !== false || strpos(strtolower($responseBody), 'rate limit') !== false || strpos(strtolower($responseBody), 'temporary') !== false) {
                $isRetryable = true;
            }

            verifyAndReconnectDb($pdo, $dashPdo, $clientId);
            try {
                $pdo->beginTransaction();

                // Verify the post still exists in posts table before inserting log
                $stmtCheck = $pdo->prepare('SELECT id FROM posts WHERE id = ? FOR UPDATE');
                $stmtCheck->execute([$postId]);
                if ($stmtCheck->fetch()) {
                    if ($isRetryable && $currentRetryCount < MAX_RETRIES) {
                        $newRetryCount = $currentRetryCount + 1;
                        // Calculate backoff time (RETRY_BACKOFF_FACTOR * 2^(retry_count - 1) minutes)
                        $backoffMinutes = RETRY_BACKOFF_FACTOR * pow(2, $newRetryCount - 1);
                        $newScheduledTime = date('Y-m-d H:i:s', time() + ($backoffMinutes * 60));

                        $stmtRetry = $pdo->prepare("
                            UPDATE posts 
                            SET status = 'scheduled', scheduled_at = :sched_at, retry_count = :retry_cnt, last_attempt_at = CURRENT_TIMESTAMP
                            WHERE id = :post_id
                        ");
                        $stmtRetry->execute([
                            'sched_at' => $newScheduledTime,
                            'retry_cnt' => $newRetryCount,
                            'post_id' => $postId
                        ]);

                        $stmtLog = $pdo->prepare('
                            INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                            VALUES (:post_id, :http_code, :response, 0)
                        ');
                        $stmtLog->execute([
                            'post_id' => $postId,
                            'http_code' => $httpStatusCode,
                            'response' => "Retry Attempt #{$newRetryCount} scheduled at {$newScheduledTime}. Error: " . $cleanErrorMsg
                        ]);

                        // Sync with Dashboard Cache
                        if ($dashPdo) {
                            try {
                                $stmtDash = $dashPdo->prepare("
                                    UPDATE posts_cache 
                                    SET status = 'scheduled', scheduled_at = :sched_at, retry_count = :retry_cnt, last_attempt_at = CURRENT_TIMESTAMP
                                    WHERE hub_post_id = :hub_post_id
                                ");
                                $stmtDash->execute([
                                    'sched_at' => $newScheduledTime,
                                    'retry_cnt' => $newRetryCount,
                                    'hub_post_id' => $postId
                                ]);
                            } catch (Exception $dashEx) {
                                log_message('error', "Queue Worker: Failed to update dashboard cache retry status for post ID {$postId}", ['error' => $dashEx->getMessage()]);
                            }
                        }

                        log_message('info', "Queue Worker: Scheduled retry #{$newRetryCount} for post ID {$postId} at {$newScheduledTime}");
                    } else {
                        // Max retries exceeded or non-retryable error, delete post
                        $stmtDeleteFailed = $pdo->prepare('
                            DELETE FROM posts WHERE id = :post_id
                        ');
                        $stmtDeleteFailed->execute(['post_id' => $postId]);

                        // Delete media file from disk to save space
                        if (!empty($mediaTempPath)) {
                            try {
                                StorageService::deletePostMedia($mediaTempPath, $clientId);
                            } catch (Exception $delEx) {
                                log_message('warning', "Queue Worker: Failed to delete media on failure for post ID {$postId}", ['error' => $delEx->getMessage()]);
                            }
                        }

                        // Sync with Dashboard Cache (delete instead of setting to failed)
                        if ($dashPdo) {
                            try {
                                $stmtDash = $dashPdo->prepare('
                                    DELETE FROM posts_cache WHERE hub_post_id = :hub_post_id
                                ');
                                $stmtDash->execute(['hub_post_id' => $postId]);
                            } catch (Exception $dashEx) {
                                log_message('error', "Queue Worker: Failed to delete dashboard cache post for post ID {$postId}", ['error' => $dashEx->getMessage()]);
                            }
                        }

                        log_message('error', "Queue Worker: Permanent publication failure for post ID {$postId} on {$platform}");
                    }
                } else {
                    log_message('warning', "Queue Worker: Post ID {$postId} was deleted externally during processing. Skipping DB failure updates.");
                }

                $pdo->commit();
            } catch (Exception $dbEx) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                log_message('error', "Queue Worker: DB update failed for failed post ID {$postId}", ['error' => $dbEx->getMessage()]);
            }

            // Introduce a brief 2-second sleep/delay between processing posts to publish them sequentially one by one
            sleep(2);
        }
    }

    $duration = round(microtime(true) - $startTime, 4);
    log_message('info', 'Queue Worker: Execution completed.', [
        'processed_posts_count' => $processedCount,
        'execution_time_seconds' => $duration
    ]);

    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'processed_posts_count' => $processedCount,
            'execution_time' => $duration
        ]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $duration = round(microtime(true) - $startTime, 4);
    log_message('error', 'Queue Worker: Execution failed.', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'execution_time_seconds' => $duration
    ]);

    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'execution_time' => $duration
        ]);
    }
}

/**
 * Ensures the Hub and Dashboard database connections are alive, reconnecting them if necessary.
 */
function verifyAndReconnectDb(&$pdo, &$dashPdo, $client_id)
{
    // 1. Verify Hub PDO
    try {
        if ($pdo) {
            $pdo->query('SELECT 1');
        } else {
            throw new Exception('PDO is null');
        }
    } catch (Exception $e) {
        log_message('info', 'Queue Worker: Reconnecting Hub database due to timeout: ' . $e->getMessage());
        $GLOBALS['hub_pdo'] = null;  // Clear cached connection
        $pdo = require __DIR__ . '/../db/connection.php';
    }

    // 2. Verify Dashboard PDO
    try {
        if ($dashPdo) {
            $dashPdo->query('SELECT 1');
        } else {
            throw new Exception('dashPdo is null');
        }
    } catch (Exception $e) {
        log_message('info', 'Queue Worker: Reconnecting Dashboard database due to timeout: ' . $e->getMessage());
        try {
            $dashPdo = new PDO(
                'mysql:host=' . DASHBOARD_DB_HOST . ';port=3306;dbname=' . DASHBOARD_DB_NAME . ';charset=utf8mb4',
                DASHBOARD_DB_USER,
                DASHBOARD_DB_PASS
            );
            $dashPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            try {
                $dashPdo->exec("SET time_zone = '+05:30'");
            } catch (Exception $tzEx) {
                // Ignore
            }
        } catch (Exception $dashEx) {
            log_message('error', 'Queue Worker: Failed to reconnect to dashboard_db', ['error' => $dashEx->getMessage()]);
            $dashPdo = null;
        }
    }
}
