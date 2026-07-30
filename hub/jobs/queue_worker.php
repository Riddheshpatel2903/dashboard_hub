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

// Include Platform Handlers
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/WhatsAppHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

// 1. Endpoint Security
$isCli = (PHP_SAPI === 'cli');
$passedToken = $_GET['token'] ?? '';

if (!$isCli && $passedToken !== CRON_SECRET) {
    http_response_code(403);
    log_message('warning', "Queue Worker: Unauthorized execution attempt blocked from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing secret cron token.']);
    exit();
}

log_message('info', "Queue Worker: Started execution.");

try {
    $pdo->beginTransaction();

    // 2. Fetch and Lock queued posts limited by batch size
    $stmt = $pdo->prepare("
        SELECT p.id as post_id, p.client_id, p.content, p.media_temp_path, 
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
        log_message('info', "Queue Worker: No queued posts found. Finished.", ['execution_time_seconds' => $duration]);
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
            "mysql:host=" . DASHBOARD_DB_HOST . ";port=3306;dbname=" . DASHBOARD_DB_NAME . ";charset=utf8mb4",
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
        log_message('error', "Queue Worker: Failed to connect to dashboard_db during cache sync", ['error' => $e->getMessage()]);
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
        $mediaTempPath = $post['media_temp_path'];
        $externalAccountId = $post['external_account_id'];
        $currentRetryCount = (int)$post['retry_count'];
        
        $token = decrypt($post['access_token_encrypted']);
        $mediaPublicUrl = $mediaTempPath ? StorageService::getPublicUrl($mediaTempPath) : null;

        $externalPostId = null;
        $responseBody = '';
        $httpStatusCode = 200;
        $success = false;

        try {
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
                        throw new Exception("Instagram requires media. No media provided.");
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
                    $res = YouTubeHandler::uploadVideo($token, $localPath, "Scheduled Video", $content);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'linkedin':
                    $res = LinkedInHandler::publishPost($token, $externalAccountId, $content);
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

            try {
                $pdo->beginTransaction();
                
                // Verify the post still exists in posts table before inserting log
                $stmtCheck = $pdo->prepare("SELECT id FROM posts WHERE id = ? FOR UPDATE");
                $stmtCheck->execute([$postId]);
                if ($stmtCheck->fetch()) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE posts 
                        SET status = 'published', external_post_id = :ext_id, published_at = CURRENT_TIMESTAMP, retry_count = 0
                        WHERE id = :post_id
                    ");
                    $stmtUpdate->execute([
                        'ext_id'  => $externalPostId,
                        'post_id' => $postId
                    ]);

                    $stmtLog = $pdo->prepare("
                        INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                        VALUES (:post_id, 200, :response, 1)
                    ");
                    $stmtLog->execute([
                        'post_id'  => $postId,
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
                                'ext_id'      => $externalPostId,
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
            $cleanErrorMsg = ucfirst($platform) . " error: " . $cleanErrorMsg;

            log_message('error', "Queue Worker: Failed publishing post ID {$postId} on {$platform}", ['error' => $cleanErrorMsg]);

            // Determine if error is temporary and retryable (e.g. timeout, rate limit, HTTP 5xx)
            $isRetryable = false;
            // Common temporary error indicators (cURL timeout is 28, rate limits 429 or code 4, 17, 368 etc. for Meta)
            if ($httpStatusCode === 429 || $httpStatusCode >= 500 || $httpStatusCode === 0 || strpos(strtolower($responseBody), 'timeout') !== false || strpos(strtolower($responseBody), 'rate limit') !== false || strpos(strtolower($responseBody), 'temporary') !== false) {
                $isRetryable = true;
            }

            try {
                $pdo->beginTransaction();
                
                // Verify the post still exists in posts table before inserting log
                $stmtCheck = $pdo->prepare("SELECT id FROM posts WHERE id = ? FOR UPDATE");
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
                            'post_id'   => $postId
                        ]);

                        $stmtLog = $pdo->prepare("
                            INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                            VALUES (:post_id, :http_code, :response, 0)
                        ");
                        $stmtLog->execute([
                            'post_id'   => $postId,
                            'http_code' => $httpStatusCode,
                            'response'  => "Retry Attempt #{$newRetryCount} scheduled at {$newScheduledTime}. Error: " . $cleanErrorMsg
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
                                    'sched_at'    => $newScheduledTime,
                                    'retry_cnt'   => $newRetryCount,
                                    'hub_post_id' => $postId
                                ]);
                            } catch (Exception $dashEx) {
                                log_message('error', "Queue Worker: Failed to update dashboard cache retry status for post ID {$postId}", ['error' => $dashEx->getMessage()]);
                            }
                        }

                        log_message('info', "Queue Worker: Scheduled retry #{$newRetryCount} for post ID {$postId} at {$newScheduledTime}");

                    } else {
                        // Max retries exceeded or non-retryable error
                        $stmtUpdateFailed = $pdo->prepare("
                            UPDATE posts 
                            SET status = 'failed', last_attempt_at = CURRENT_TIMESTAMP
                            WHERE id = :post_id
                        ");
                        $stmtUpdateFailed->execute(['post_id' => $postId]);

                        $stmtLog = $pdo->prepare("
                            INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                            VALUES (:post_id, :http_code, :response, 0)
                        ");
                        $stmtLog->execute([
                            'post_id'   => $postId,
                            'http_code' => $httpStatusCode,
                            'response'  => "Failure: " . $cleanErrorMsg
                        ]);

                        // Sync with Dashboard Cache
                        if ($dashPdo) {
                            try {
                                $stmtDash = $dashPdo->prepare("
                                    UPDATE posts_cache 
                                    SET status = 'failed', last_attempt_at = CURRENT_TIMESTAMP
                                    WHERE hub_post_id = :hub_post_id
                                ");
                                $stmtDash->execute(['hub_post_id' => $postId]);
                            } catch (Exception $dashEx) {
                                log_message('error', "Queue Worker: Failed to update dashboard cache to failed for post ID {$postId}", ['error' => $dashEx->getMessage()]);
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
    log_message('info', "Queue Worker: Execution completed.", [
        'processed_posts_count' => $processedCount,
        'execution_time_seconds' => $duration
    ]);

    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'               => true,
            'processed_posts_count' => $processedCount,
            'execution_time'        => $duration
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $duration = round(microtime(true) - $startTime, 4);
    log_message('error', "Queue Worker: Execution failed.", [
        'error'                  => $e->getMessage(),
        'trace'                  => $e->getTraceAsString(),
        'execution_time_seconds' => $duration
    ]);

    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success'        => false,
            'error'          => $e->getMessage(),
            'execution_time' => $duration
        ]);
    }
}
