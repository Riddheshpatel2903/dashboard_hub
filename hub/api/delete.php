<?php
/**
 * Delete Post Endpoint.
 * Endpoint: POST /api/delete.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/token_helper.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$platform = $input['platform'] ?? null;
$externalPostId = $input['external_post_id'] ?? null;

if ($postId <= 0 && (empty($platform) || empty($externalPostId))) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameter: post_id or (platform and external_post_id)']);
    exit();
}

try {
    $connectionId = 0;
    if (!empty($platform)) {
        $connStmt = $pdo->prepare("SELECT id FROM platform_connections WHERE client_id = :client_id AND platform = :platform LIMIT 1");
        $connStmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $connectionId = (int)$connStmt->fetchColumn();
    }

    $targetPostId = 0;
    $targetExternalPostId = $externalPostId;
    $targetPlatform = $platform;

    // Check if post exists in posts table by ID
    if ($postId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.external_post_id, pc.platform, p.platform_connection_id
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.id = :post_id AND p.client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([
            'post_id'   => $postId,
            'client_id' => $client_id
        ]);
        $post = $stmt->fetch();
        if ($post) {
            $targetPostId = (int)$post['id'];
            $targetExternalPostId = $post['external_post_id'];
            $targetPlatform = $post['platform'];
            if (!$connectionId) {
                $connectionId = (int)$post['platform_connection_id'];
            }
        }
    }

    // If not found by ID but we have external_post_id, check if exists by external_post_id
    if (!$targetPostId && !empty($targetExternalPostId)) {
        $stmt = $pdo->prepare("
            SELECT p.id, pc.platform, p.platform_connection_id
            FROM posts p
            JOIN platform_connections pc ON p.platform_connection_id = pc.id
            WHERE p.external_post_id = :external_post_id AND p.client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([
            'external_post_id' => $targetExternalPostId,
            'client_id'        => $client_id
        ]);
        $post = $stmt->fetch();
        if ($post) {
            $targetPostId = (int)$post['id'];
            $targetPlatform = $post['platform'];
            if (!$connectionId) {
                $connectionId = (int)$post['platform_connection_id'];
            }
        }
    }

    // Queue the delete
    if ($targetPostId > 0) {
        // Post exists, set status to pending_delete
        $upStmt = $pdo->prepare("UPDATE posts SET status = 'pending_delete' WHERE id = :post_id");
        $upStmt->execute(['post_id' => $targetPostId]);
    } else {
        // Post does not exist in posts table. If we have connection and external ID, create a placeholder post
        if ($connectionId > 0 && !empty($targetExternalPostId)) {
            $insStmt = $pdo->prepare("
                INSERT INTO posts (client_id, platform_connection_id, external_post_id, status, content)
                VALUES (:client_id, :connection_id, :external_post_id, 'pending_delete', 'Placeholder for queued deletion')
            ");
            $insStmt->execute([
                'client_id'         => $client_id,
                'connection_id'     => $connectionId,
                'external_post_id' => $targetExternalPostId
            ]);
            $targetPostId = (int)$pdo->lastInsertId();
        }
    }

    // Clean up cache immediately from platform_posts so it disappears from cache fetches
    if (!empty($targetPlatform) && !empty($targetExternalPostId)) {
        $pdo->prepare("
            DELETE FROM platform_posts 
            WHERE platform = :platform AND platform_post_id = :external_post_id AND client_id = :client_id
        ")->execute([
            'platform'         => $targetPlatform,
            'external_post_id' => $targetExternalPostId,
            'client_id'        => $client_id
        ]);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Post deletion has been queued and will be processed in the background.'
    ]);
    exit();

} catch (Exception $e) {
    log_message('error', "Failed to queue post deletion on {$platform}", ['error' => $e->getMessage()]);

    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
