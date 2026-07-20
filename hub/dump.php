<?php
header('Content-Type: text/plain');
$pdo = require __DIR__ . '/db/connection.php';

// Reset stuck posts
$pdo->exec("UPDATE posts SET status = 'scheduled', scheduled_at = NOW() WHERE id IN (51, 52)");
echo "Reset posts 51 and 52 to scheduled.\n";

$dashPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=dashboard_db;charset=utf8mb4", "root", "");
$dashPdo->exec("UPDATE posts_cache SET status = 'scheduled', scheduled_at = NOW() WHERE hub_post_id IN (51, 52)");
echo "Reset posts_cache for 51 and 52 to scheduled.\n";

@unlink(__FILE__);
