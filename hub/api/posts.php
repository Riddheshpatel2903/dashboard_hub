<?php
/**
 * Retrieve Client Local Posts (Scheduled / Failed / Queued).
 * Endpoint: GET /api/posts.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

try {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    
    if ($postId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.client_id = :client_id AND p.id = :post_id
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'post_id'   => $postId
        ]);
        $post = $stmt->fetch();
        
        if (!$post) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit();
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'post' => [
                'id'               => 0,
                'hub_post_id'      => (int)$post['id'],
                'content'          => $post['content'],
                'status'           => $post['status'],
                'platform'         => $post['platform'],
                'media_path'       => $post['media_path'],
                'scheduled_at'     => $post['scheduled_at'],
                'published_at'     => $post['published_at'],
                'created_at'       => $post['created_at'],
                'external_post_id' => $post['external_post_id'],
                'views_count'      => 0,
                'likes_count'      => 0,
                'comments_count'   => 0
            ]
        ]);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.status, p.media_temp_path as media_path, p.scheduled_at, p.published_at, p.created_at, pc.platform, p.external_post_id
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        WHERE p.client_id = :client_id AND p.status IN ('scheduled', 'failed', 'queued')
        ORDER BY p.created_at DESC
    ");
    $stmt->execute(['client_id' => $client_id]);
    $posts = $stmt->fetchAll() ?: [];

    // Ensure numeric fields and null conversions match expected format
    $formatted = [];
    foreach ($posts as $post) {
        $formatted[] = [
            'id'               => 0, // Since they are not saved in posts_cache, return 0
            'hub_post_id'      => (int)$post['id'],
            'content'          => $post['content'],
            'status'           => $post['status'],
            'platform'         => $post['platform'],
            'media_path'       => $post['media_path'],
            'scheduled_at'     => $post['scheduled_at'],
            'published_at'     => $post['published_at'],
            'created_at'       => $post['created_at'],
            'external_post_id' => $post['external_post_id'],
            'views_count'      => 0,
            'likes_count'      => 0,
            'comments_count'   => 0
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'posts'   => $formatted
    ]);

} catch (Exception $e) {
    log_message('error', "Local posts fetch failure", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
