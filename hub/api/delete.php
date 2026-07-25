<?php
/**
 * Delete Post Endpoint.
 * Endpoint: POST /api/delete.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;

if ($postId <= 0) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid post_id parameter']);
    exit();
}

try {
    // 1. Retrieve the post details, platform, media_path, and tokens
    $stmt = $pdo->prepare("
        SELECT p.id, p.external_post_id, p.media_path, pc.platform, pc.external_account_id, pt.access_token_encrypted
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE p.id = :post_id AND p.client_id = :client_id
        LIMIT 1
    ");
    $stmt->execute([
        'post_id'   => $postId,
        'client_id' => $client_id
    ]);
    
    $post = $stmt->fetch();
    if (!$post) {
        header('Content-Type: application/json', true, 404);
        echo json_encode(['success' => false, 'error' => 'Post not found or unauthorized']);
        exit();
    }

    $platform = $post['platform'];
    $externalPostId = $post['external_post_id'];
    $mediaPath = $post['media_path'] ?? '';
    $token = decrypt($post['access_token_encrypted']);

    $response = [];
    
    // 2. Dispatch Delete request if external ID is present
    if (!empty($externalPostId)) {
        switch ($platform) {
            case 'facebook':
                $response = FacebookHandler::deletePost($token, $externalPostId);
                break;
                
            case 'instagram':
                $response = InstagramHandler::deletePost($token, $externalPostId);
                break;
                
            case 'linkedin':
                $response = LinkedInHandler::deletePost($token, $externalPostId);
                break;
                
            case 'google_business':
                $response = GoogleBusinessHandler::deletePost($token, $externalPostId);
                break;
    
            case 'youtube':
                $response = YouTubeHandler::deleteVideo($token, $externalPostId);
                break;
    
            default:
                throw new Exception("Deletion is not supported for {$platform} posts.");
        }
    } else {
        $response = ['message' => 'Post cleared locally (no external post ID found).'];
    }

    // 3. Delete physical media file from disk
    if (!empty($mediaPath)) {
        require_once __DIR__ . '/../storage/StorageService.php';
        StorageService::deletePostMedia($mediaPath, $client_id);
    }

    // 4. Hard delete post from Hub posts table
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :post_id");
    $stmt->execute(['post_id' => $postId]);

    $stmt = $pdo->prepare("
        INSERT INTO post_logs (post_id, http_status_code, response_body, success)
        VALUES (:post_id, 200, :response, 1)
    ");
    $stmt->execute([
        'post_id'  => $postId,
        'response' => json_encode($response)
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success'  => true,
        'message'  => 'Post deleted successfully',
        'response' => $response
    ]);

} catch (Exception $e) {
    $httpCode = $e->getCode() ?: 500;
    log_message('error', "Failed to delete post ID {$postId} on {$platform}", ['error' => $e->getMessage()]);

    $stmt = $pdo->prepare("
        INSERT INTO post_logs (post_id, http_status_code, response_body, success)
        VALUES (:post_id, :http_code, :response, 0)
    ");
    $stmt->execute([
        'post_id'   => $postId,
        'http_code' => $httpCode,
        'response'  => $e->getMessage()
    ]);

    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
