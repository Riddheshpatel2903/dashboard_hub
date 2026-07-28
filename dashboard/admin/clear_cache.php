<?php
/**
 * One-off script to truncate local cache tables.
 */
$pdo = require __DIR__ . '/../db/connection.php';

try {
    $pdo->exec("TRUNCATE TABLE posts_cache");
    $pdo->exec("TRUNCATE TABLE analytics_cache");
    echo "Cache tables posts_cache and analytics_cache truncated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
