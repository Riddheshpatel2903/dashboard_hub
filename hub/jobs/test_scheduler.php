<?php
/**
 * Automated Diagnostic Test for Scheduled Post System.
 * Run in CLI: php /path/to/hub/jobs/test_scheduler.php
 */

header('Content-Type: text/plain');

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/logger.php';

echo "=== STARTING DIAGNOSTIC TESTS ===\n";

// Helper to find a connection for testing
$stmt = $pdo->query("SELECT id FROM platform_connections WHERE client_id = 1 LIMIT 1");
$connId = $stmt->fetchColumn();

if (!$connId) {
    echo "FAIL: No platform connections found for client_id=1. Create a connection first.\n";
    exit(1);
}
echo "PASS: Using test platform connection ID: {$connId}\n";

$testContent = "TEST_SCHEDULER_AUTO_" . time();

try {
    // 1. Insert a dummy scheduled post in the past (should be picked up by scheduler)
    $stmt = $pdo->prepare("
        INSERT INTO posts (client_id, platform_connection_id, content, status, scheduled_at, created_at)
        VALUES (1, :conn_id, :content, 'scheduled', :sched_at, NOW())
    ");
    
    $pastTime = date('Y-m-d H:i:s', time() - 600); // 10 minutes ago
    $stmt->execute(['conn_id' => $connId, 'content' => $testContent, 'sched_at' => $pastTime]);
    $pastPostId = $pdo->lastInsertId();
    echo "PASS: Inserted past-due scheduled test post. ID: {$pastPostId}\n";

    // 2. Insert a dummy scheduled post in the future (should NOT be picked up by scheduler)
    $futureTime = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now
    $stmt->execute(['conn_id' => $connId, 'content' => $testContent . "_FUTURE", 'sched_at' => $futureTime]);
    $futurePostId = $pdo->lastInsertId();
    echo "PASS: Inserted future scheduled test post. ID: {$futurePostId}\n";

    // 3. Trigger Scheduler locally
    echo "Executing Scheduler...\n";
    ob_start();
    // Simulate CLI call by setting PHP_SAPI checks or passing token if needed
    $_GET['token'] = CRON_SECRET;
    include __DIR__ . '/scheduler.php';
    $schedOutput = ob_get_clean();

    // Verify Scheduler transition: pastPostId should be 'queued', futurePostId should remain 'scheduled'
    $stmt = $pdo->prepare("SELECT status FROM posts WHERE id = ?");
    
    $stmt->execute([$pastPostId]);
    $pastStatus = $stmt->fetchColumn();
    
    $stmt->execute([$futurePostId]);
    $futureStatus = $stmt->fetchColumn();

    if ($pastStatus === 'queued' && $futureStatus === 'scheduled') {
        echo "PASS: Scheduler processed posts correctly. Past-due is 'queued', future is 'scheduled'.\n";
    } else {
        echo "FAIL: Scheduler status mismatch. Past-due status: {$pastStatus}, Future status: {$futureStatus}\n";
    }

    // 4. Verify Locking & Queue Worker Execution (Simulation)
    echo "Executing Queue Worker...\n";
    ob_start();
    include __DIR__ . '/queue_worker.php';
    $workerOutput = ob_get_clean();

    // Query result of past post
    $finalStatus = $pdo->query("SELECT status FROM posts WHERE id = " . (int)$pastPostId)->fetchColumn();
    echo "INFO: Past post final status: {$finalStatus}\n";

    // Clean up test posts
    $pdo->exec("DELETE FROM posts WHERE content LIKE 'TEST_SCHEDULER_AUTO_%'");
    echo "PASS: Cleaned up diagnostic test posts.\n";

    // Clean up dashboard posts_cache
    $dashPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=dashboard_db;charset=utf8mb4", "root", "");
    $dashPdo->exec("DELETE FROM posts_cache WHERE content LIKE 'TEST_SCHEDULER_AUTO_%'");
    echo "PASS: Cleaned up dashboard posts_cache database rows.\n";

    echo "=== ALL DIAGNOSTIC TESTS COMPLETED ===\n";

} catch (Exception $e) {
    echo "FAIL: Exception occurred during test run: " . $e->getMessage() . "\n";
    // Cleanup anyway
    @$pdo->exec("DELETE FROM posts WHERE content LIKE 'TEST_SCHEDULER_AUTO_%'");
}
