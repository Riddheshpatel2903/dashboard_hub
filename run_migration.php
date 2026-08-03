<?php
/**
 * One-time Database Migration Script.
 * Run once via browser: http://localhost/dashboard_hub/run_migration.php
 * DELETE this file after running successfully.
 */

// Basic security: only allow from localhost
$allowedHosts = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedHosts)) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

require_once __DIR__ . '/dashboard/config/config.php';

$results = [];

try {
    $dsn = 'mysql:host=' . DASHBOARD_DB_HOST . ';dbname=' . DASHBOARD_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DASHBOARD_DB_USER, DASHBOARD_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // ---- Dashboard DB Migrations ----

    // 1. Make hub_post_id nullable (allows platform-synced posts with no hub origin)
    try {
        $pdo->exec('ALTER TABLE `posts_cache` MODIFY `hub_post_id` INT NULL DEFAULT NULL');
        $results[] = ['ok', 'dashboard_db: hub_post_id is now nullable'];
    } catch (Exception $e) {
        $results[] = ['warn', 'dashboard_db: hub_post_id alter: ' . $e->getMessage()];
    }

    // 2. Add likes_count column
    try {
        $pdo->exec('ALTER TABLE `posts_cache` ADD COLUMN IF NOT EXISTS `likes_count` INT NOT NULL DEFAULT 0');
        $results[] = ['ok', 'dashboard_db: likes_count column added'];
    } catch (Exception $e) {
        $results[] = ['warn', 'dashboard_db: likes_count: ' . $e->getMessage()];
    }

    // 3. Add comments_count column
    try {
        $pdo->exec('ALTER TABLE `posts_cache` ADD COLUMN IF NOT EXISTS `comments_count` INT NOT NULL DEFAULT 0');
        $results[] = ['ok', 'dashboard_db: comments_count column added'];
    } catch (Exception $e) {
        $results[] = ['warn', 'dashboard_db: comments_count: ' . $e->getMessage()];
    }

    // 4. Add views_count column
    try {
        $pdo->exec('ALTER TABLE `posts_cache` ADD COLUMN IF NOT EXISTS `views_count` INT NOT NULL DEFAULT 0');
        $results[] = ['ok', 'dashboard_db: views_count column added'];
    } catch (Exception $e) {
        $results[] = ['warn', 'dashboard_db: views_count: ' . $e->getMessage()];
    }

    // Add extra metrics columns for complete local caching
    foreach (['shares_count', 'impressions_count', 'reach_count', 'clicks_count', 'engagement_count'] as $col) {
        try {
            $pdo->exec("ALTER TABLE `posts_cache` ADD COLUMN IF NOT EXISTS `{$col}` INT NOT NULL DEFAULT 0");
            $results[] = ['ok', "dashboard_db: {$col} column added"];
        } catch (Exception $e) {
            $results[] = ['warn', "dashboard_db: {$col}: " . $e->getMessage()];
        }
    }

    // 5. Add platform-post dedup unique key
    try {
        $pdo->exec('ALTER IGNORE TABLE `posts_cache` ADD UNIQUE KEY `idx_platform_post` (`client_id`, `platform`, `external_post_id`)');
        $results[] = ['ok', 'dashboard_db: idx_platform_post unique key added'];
    } catch (Exception $e) {
        // May already exist
        $results[] = ['warn', 'dashboard_db: idx_platform_post: ' . $e->getMessage()];
    }

    $results[] = ['ok', '--- Dashboard DB migrations complete ---'];

} catch (Exception $connEx) {
    $results[] = ['error', 'Dashboard DB connection failed: ' . $connEx->getMessage()];
}

// ---- Hub DB Migration: add last_synced_at & title ----
try {
    $hubConfigContent = file_get_contents(__DIR__ . '/hub/config/config.php');
    
    // Extract Hub DB credentials from config without loading duplicate constants
    $hubDbHost = getenv('HUB_DB_HOST');
    $hubDbName = getenv('HUB_DB_NAME');
    $hubDbUser = getenv('HUB_DB_USER');
    $hubDbPass = getenv('HUB_DB_PASS');

    if (!$hubDbHost || !$hubDbName || !$hubDbUser || $hubDbPass === false) {
        $getHubConstant = function($content, $constantName, $defaultVal) {
            if (preg_match("/define\(\s*'" . $constantName . "'\s*,\s*(?:getenv\('[^']+'\)\s*(?:\?:|\!==\s*false\s*\?\s*getenv\('[^']+'\)\s*:\s*))?'([^']*)'\s*\)/i", $content, $matches)) {
                return $matches[1];
            }
            return $defaultVal;
        };
        if (!$hubDbHost) $hubDbHost = $getHubConstant($hubConfigContent, 'DB_HOST', '127.0.0.1');
        if (!$hubDbName) $hubDbName = $getHubConstant($hubConfigContent, 'DB_NAME', 'hub_db');
        if (!$hubDbUser) $hubDbUser = $getHubConstant($hubConfigContent, 'DB_USER', 'root');
        if ($hubDbPass === false) $hubDbPass = $getHubConstant($hubConfigContent, 'DB_PASS', '');
    }

    $hubDsn = "mysql:host={$hubDbHost};dbname={$hubDbName};charset=utf8mb4";
    $hubPdo = new PDO($hubDsn, $hubDbUser, $hubDbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    try {
        $hubPdo->exec('ALTER TABLE `platform_connections` ADD COLUMN IF NOT EXISTS `last_synced_at` TIMESTAMP NULL DEFAULT NULL');
        $results[] = ['ok', 'hub_db: last_synced_at column added to platform_connections'];
    } catch (Exception $e) {
        $results[] = ['warn', 'hub_db: last_synced_at: ' . $e->getMessage()];
    }

    try {
        $hubPdo->exec('ALTER TABLE `posts` ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) NULL DEFAULT NULL AFTER `content`');
        $results[] = ['ok', 'hub_db: title column added to posts'];
    } catch (Exception $e) {
        $results[] = ['warn', 'hub_db: title: ' . $e->getMessage()];
    }

    $results[] = ['ok', '--- Hub DB migrations complete ---'];

} catch (Exception $connEx) {
    $results[] = ['warn', 'Hub DB connection skipped (not available from dashboard config): ' . $connEx->getMessage()];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <title>Database Migration</title>
    <style>
        body { font-family: monospace; background: #0f1117; color: #e2e8f0; padding: 2rem; }
        h1 { color: #7c3aed; }
        .ok   { color: #4ade80; }
        .warn { color: #facc15; }
        .error { color: #f87171; }
        ul { list-style: none; padding: 0; }
        li { padding: 4px 0; border-bottom: 1px solid #1e293b; }
        .done { margin-top: 2rem; padding: 1rem; background: #1e293b; border-radius: 8px; color: #94a3b8; font-size: 0.85rem; }
    </style>
</head>
<body>
    <h1>🗃️ Database Migration</h1>
    <ul>
        <?php foreach ($results as [$type, $msg]): ?>
        <li class="<?php echo htmlspecialchars($type); ?>">
            <?php echo $type === 'ok' ? '✅' : ($type === 'warn' ? '⚠️' : '❌'); ?>
            <?php echo htmlspecialchars($msg); ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="done">
        <strong>Migration complete.</strong> You can now delete this file: <code>run_migration.php</code>
    </div>
</body>
</html>
