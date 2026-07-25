<?php
$dashPdo = require __DIR__ . '/../db/connection.php';
$hubPdo = require __DIR__ . '/../../hub/db/connection.php';

$cachePaths = $dashPdo->query("SELECT id, platform, media_path, status FROM posts_cache")->fetchAll();
$hubPaths = $hubPdo->query("SELECT id, media_temp_path, status FROM posts")->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'posts_cache_media_paths' => $cachePaths,
    'hub_posts_media_paths' => $hubPaths
], JSON_PRETTY_PRINT);
