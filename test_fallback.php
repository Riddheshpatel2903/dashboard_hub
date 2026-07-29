<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Hub config and DB connection
require_once __DIR__ . '/hub/config/config.php';
$pdo = require __DIR__ . '/hub/db/connection.php';
require_once __DIR__ . '/hub/platforms/FacebookHandler.php';
require_once __DIR__ . '/hub/platforms/InstagramHandler.php';
require_once __DIR__ . '/hub/api/posts.php';

$client_id = 1;
$postId = 116;

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id, pc.external_account_id as page_id
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        die("Post not found in DB\n");
    }

    echo "<pre>Post in DB:\n";
    print_r($post);

    echo "\nExecuting formatLocalPost...\n";
    $formatted = formatLocalPost($pdo, $post, $client_id);
    print_r($formatted);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
