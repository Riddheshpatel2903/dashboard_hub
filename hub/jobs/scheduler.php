<?php
/**
 * Post Scheduler Job.
 * Scans for posts scheduled for release and queues them.
 * Run via cron: * /5 * * * * php /path/to/hub/jobs/scheduler.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

try {
    // 1. Identify posts due for release
    $stmt = $pdo->prepare("
        SELECT id FROM posts 
        WHERE status = 'scheduled' AND scheduled_at <= CURRENT_TIMESTAMP
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();

    if (empty($posts)) {
        exit(); // Nothing to queue
    }

    $queuedCount = 0;
    
    // 2. Mark posts as queued
    $stmtUpdate = $pdo->prepare("
        UPDATE posts 
        SET status = 'queued' 
        WHERE id = :post_id
    ");

    foreach ($posts as $post) {
        $stmtUpdate->execute(['post_id' => $post['id']]);
        $queuedCount++;
    }

    log_message('info', "Scheduler job completed: successfully queued {$queuedCount} posts.");

} catch (Exception $e) {
    log_message('error', "Scheduler job failed", ['error' => $e->getMessage()]);
}
