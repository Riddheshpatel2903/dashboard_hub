<?php
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

$stmt = $pdo->query("SELECT id, content, platform, media_path, status, created_at FROM posts_cache ORDER BY id DESC");
$posts = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'total_posts_in_db' => count($posts),
    'posts' => $posts
], JSON_PRETTY_PRINT);
