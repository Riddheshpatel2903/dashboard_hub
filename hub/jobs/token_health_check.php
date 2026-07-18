<?php
/**
 * Token Health Check Job.
 * Daily cron job checking for expired, expiring, or broken connection tokens.
 * Run via cron: 0 0 * * * php /path/to/hub/jobs/token_health_check.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

try {
    // 1. Flag tokens expiring in next 7 days as 'expiring'
    $stmtExpiring = $pdo->prepare("
        UPDATE platform_connections 
        SET status = 'expiring'
        WHERE id IN (
            SELECT platform_connection_id FROM platform_tokens
            WHERE expires_at IS NOT NULL 
              AND expires_at <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
              AND expires_at > CURRENT_TIMESTAMP
        )
    ");
    $stmtExpiring->execute();
    $expiringCount = $stmtExpiring->rowCount();

    // 2. Flag already expired tokens as 'expired'
    $stmtExpired = $pdo->prepare("
        UPDATE platform_connections 
        SET status = 'expired'
        WHERE id IN (
            SELECT platform_connection_id FROM platform_tokens
            WHERE expires_at IS NOT NULL 
              AND expires_at <= CURRENT_TIMESTAMP
        )
    ");
    $stmtExpired->execute();
    $expiredCount = $stmtExpired->rowCount();

    // 3. Scan for active connections exhibiting repeated auth failures (last 3 attempts failed with 401/403)
    $stmtActive = $pdo->prepare("
        SELECT id, platform, client_id FROM platform_connections 
        WHERE status IN ('connected', 'expiring')
    ");
    $stmtActive->execute();
    $activeConnections = $stmtActive->fetchAll();

    $brokenCount = 0;

    $stmtLogs = $pdo->prepare("
        SELECT pl.success, pl.http_status_code 
        FROM post_logs pl
        JOIN posts p ON pl.post_id = p.id
        WHERE p.platform_connection_id = :conn_id
        ORDER BY pl.attempted_at DESC
        LIMIT 3
    ");

    $stmtMarkExpired = $pdo->prepare("
        UPDATE platform_connections 
        SET status = 'expired' 
        WHERE id = :conn_id
    ");

    foreach ($activeConnections as $conn) {
        $connId = $conn['id'];
        $stmtLogs->execute(['conn_id' => $connId]);
        $recentLogs = $stmtLogs->fetchAll();

        // Check if there are at least 2 attempts, and all recent attempts failed due to auth problems (401/403)
        if (count($recentLogs) >= 2) {
            $allAuthFailed = true;
            foreach ($recentLogs as $log) {
                if ($log['success'] == 1 || !in_array((int)$log['http_status_code'], [401, 403])) {
                    $allAuthFailed = false;
                    break;
                }
            }

            if ($allAuthFailed) {
                $stmtMarkExpired->execute(['conn_id' => $connId]);
                $brokenCount++;
                log_message('warning', "Connection flagged as EXPIRED due to repeated auth failures", [
                    'connection_id' => $connId,
                    'platform'      => $conn['platform'],
                    'client_id'     => $conn['client_id']
                ]);
            }
        }
    }

    log_message('info', "Token health check job finished: {$expiringCount} expiring, {$expiredCount} expired, {$brokenCount} broken connections marked.");

} catch (Exception $e) {
    log_message('error', "Token health check job failed", ['error' => $e->getMessage()]);
}
