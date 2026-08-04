<?php
/**
 * One-time analytics cache clearing utility.
 * Run this after deploying the v22+ metric fixes to force fresh API calls.
 * DELETE this file after running it once!
 */

require_once __DIR__ . '/config/config.php';
$pdo = require __DIR__ . '/db/connection.php';

try {
    // Clear all analytics cache rows (they contain stale deprecated metrics like page_views_total)
    $stmt = $pdo->exec("TRUNCATE TABLE analytics_cache");
    echo "✅ analytics_cache table cleared successfully. Fresh API calls will use v22+ metrics.\n";

    // Also clear platform_posts impressions/reach columns that were populated with old metric data
    // We reset impressions/reach to NULL so they get re-populated from the next sync with correct metrics
    $stmt2 = $pdo->exec("UPDATE platform_posts SET impressions = NULL, reach = NULL, engagement = NULL WHERE platform IN ('facebook', 'instagram')");
    echo "✅ platform_posts impression/reach data reset for facebook+instagram. Will re-sync on next fetch.\n";

    echo "\n⚠️  IMPORTANT: Delete this file now! It should only be run once.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
