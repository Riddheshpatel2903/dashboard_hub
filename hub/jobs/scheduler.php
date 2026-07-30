<?php
/**
 * Post Scheduler Job.
 * Scans for posts scheduled for release and queues them.
 * Runs via CLI or HTTP trigger secured with a token.
 */

// Allow long execution time and continue running even if HTTP client aborts/disconnects
ignore_user_abort(true);
set_time_limit(0);

// Track execution duration
$startTime = microtime(true);

$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/logger.php';

// 1. Endpoint Security
$isCli = (PHP_SAPI === 'cli');
$passedToken = $_GET['token'] ?? '';

if (!$isCli && $passedToken !== CRON_SECRET) {
    http_response_code(403);
    log_message('warning', "Scheduler: Unauthorized execution attempt blocked from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing secret cron token.']);
    exit();
}

log_message('info', "Scheduler: Started execution.");

try {
    // Open connection to Dashboard Database for Cache Updates
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
        log_message('error', "Scheduler: Failed to connect to dashboard_db during cache sync initialization", ['error' => $e->getMessage()]);
    }

    // 2. Self-Healing Stuck Post Recovery
    // Check for posts stuck in 'processing' or 'publishing' for more than 15 minutes
    $stmtRecover = $pdo->prepare("
        SELECT id, status, retry_count FROM posts 
        WHERE status IN ('processing', 'publishing') 
          AND last_attempt_at IS NOT NULL 
          AND last_attempt_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmtRecover->execute();
    $stuckPosts = $stmtRecover->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($stuckPosts)) {
        foreach ($stuckPosts as $stuck) {
            $stuckId = $stuck['id'];
            $stuckStatus = $stuck['status'];
            $newRetry = (int)$stuck['retry_count'] + 1;

            if ($newRetry <= MAX_RETRIES) {
                // Reset back to 'scheduled' to retry in 5 minutes
                $newScheduledTime = date('Y-m-d H:i:s', time() + 300);
                $stmtReset = $pdo->prepare("
                    UPDATE posts 
                    SET status = 'scheduled', retry_count = :retry, last_attempt_at = NOW(), scheduled_at = :sched_at
                    WHERE id = :id
                ");
                $stmtReset->execute([
                    'retry'    => $newRetry,
                    'sched_at' => $newScheduledTime,
                    'id'       => $stuckId
                ]);

                log_message('warning', "Scheduler Recovery: Post ID {$stuckId} was stuck in '{$stuckStatus}' status. Resetting to 'scheduled' (Retry #{$newRetry} at {$newScheduledTime})");

                if ($dashPdo) {
                    try {
                        $stmtDash = $dashPdo->prepare("
                            UPDATE posts_cache 
                            SET status = 'scheduled', retry_count = :retry, last_attempt_at = NOW(), scheduled_at = :sched_at
                            WHERE hub_post_id = :id
                        ");
                        $stmtDash->execute([
                            'retry'    => $newRetry,
                            'sched_at' => $newScheduledTime,
                            'id'       => $stuckId
                        ]);
                    } catch (Exception $dashEx) {
                        log_message('error', "Scheduler Recovery: Failed to update status in posts_cache for post ID {$stuckId}", ['error' => $dashEx->getMessage()]);
                    }
                }
            } else {
                // Exceeded max retries, mark as permanently failed
                $stmtFail = $pdo->prepare("
                    UPDATE posts 
                    SET status = 'failed', last_attempt_at = NOW() 
                    WHERE id = :id
                ");
                $stmtFail->execute(['id' => $stuckId]);

                $stmtLog = $pdo->prepare("
                    INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                    VALUES (:post_id, 504, 'Stuck post recovered and marked failed after exceeding retries.', 0)
                ");
                $stmtLog->execute(['post_id' => $stuckId]);

                log_message('error', "Scheduler Recovery: Post ID {$stuckId} was stuck in '{$stuckStatus}' and exceeded MAX_RETRIES. Setting status to failed.");

                if ($dashPdo) {
                    try {
                        $stmtDash = $dashPdo->prepare("
                            UPDATE posts_cache 
                            SET status = 'failed', last_attempt_at = NOW() 
                            WHERE hub_post_id = :id
                        ");
                        $stmtDash->execute(['id' => $stuckId]);
                    } catch (Exception $dashEx) {
                        log_message('error', "Scheduler Recovery: Failed to update status in posts_cache to failed for post ID {$stuckId}", ['error' => $dashEx->getMessage()]);
                    }
                }
            }
        }
    }

    // 3. Identify and Lock posts due for release using SELECT ... FOR UPDATE
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id FROM posts 
        WHERE status = 'scheduled' AND scheduled_at <= NOW() 
        FOR UPDATE
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($posts)) {
        $pdo->commit();
        $duration = round(microtime(true) - $startTime, 4);
        log_message('info', "Scheduler: No scheduled posts due for release. Finished.", ['execution_time_seconds' => $duration]);
        if (!$isCli) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'queued_posts_count' => 0, 'execution_time' => $duration]);
        }
        exit();
    }

    $postIds = array_column($posts, 'id');
    
    // 4. Mark posts as 'processing' immediately inside transaction to lock them
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmtUpdateProcessing = $pdo->prepare("
        UPDATE posts 
        SET status = 'processing', last_attempt_at = NOW() 
        WHERE id IN ($placeholders)
    ");
    $stmtUpdateProcessing->execute($postIds);

    $pdo->commit();

    // 5. Update posts to 'queued' state and sync to Dashboard Cache
    $queuedCount = 0;
    
    $stmtUpdateQueued = $pdo->prepare("
        UPDATE posts 
        SET status = 'queued' 
        WHERE id = ?
    ");

    $stmtUpdateCache = $dashPdo ? $dashPdo->prepare("
        UPDATE posts_cache 
        SET status = 'queued' 
        WHERE hub_post_id = ?
    ") : null;

    foreach ($postIds as $id) {
        $stmtUpdateQueued->execute([$id]);
        
        if ($stmtUpdateCache) {
            try {
                $stmtUpdateCache->execute([$id]);
            } catch (Exception $ex) {
                log_message('error', "Scheduler: Failed to update status in posts_cache for post ID {$id}", ['error' => $ex->getMessage()]);
            }
        }
        $queuedCount++;
    }

    $duration = round(microtime(true) - $startTime, 4);
    log_message('info', "Scheduler: Successfully queued {$queuedCount} posts.", [
        'execution_time_seconds' => $duration,
        'queued_ids'             => $postIds
    ]);

    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'            => true,
            'queued_posts_count' => $queuedCount,
            'queued_ids'         => $postIds,
            'execution_time'     => $duration
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $duration = round(microtime(true) - $startTime, 4);
    log_message('error', "Scheduler: Job execution failed.", [
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
