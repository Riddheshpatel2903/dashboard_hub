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
$platform = $input['platform'] ?? null;
$externalPostId = $input['external_post_id'] ?? null;

if ($postId <= 0 && (empty($platform) || empty($externalPostId))) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameter: post_id or (platform and external_post_id)']);
    exit();
}

try {
    $mediaPath = '';
    $token = '';
    $dbPostFound = false;

    // 1. Retrieve the post details, platform, media_path, and tokens
    if ($postId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.external_post_id, p.media_temp_path, pc.platform, pc.external_account_id, pt.access_token_encrypted
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
        if ($post) {
            $dbPostFound = true;
            $platform = $post['platform'];
            $externalPostId = $post['external_post_id'];
            $mediaPath = $post['media_temp_path'] ?? '';
            $token = decrypt($post['access_token_encrypted']);
        }
    }

    if (!$dbPostFound) {
        if (empty($platform) || empty($externalPostId)) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Post not found or unauthorized']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT pc.id, pt.access_token_encrypted 
            FROM platform_connections pc
            JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        $conn = $stmt->fetch();
        if (!$conn) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => 'Platform connection not found or unauthorized']);
            exit();
        }
        $token = decrypt($conn['access_token_encrypted']);
    }

    $response = [];
    $externalDeleteSucceeded = true;
    
    // 2. Dispatch Delete request if external ID is present
    $platformError = null;
    if (!empty($externalPostId)) {
        try {
            switch ($platform) {
                case 'facebook':
                    // If the post ID is just a numeric media/photo ID (no page_id prefix),
                    // prefix it with the page ID (external_account_id) so Facebook knows the context.
                    $fbPostId = $externalPostId;
                    if (strpos($fbPostId, '_') === false) {
                        $pageId = $post['external_account_id'] ?? $conn['external_account_id'] ?? '';
                        if (!empty($pageId)) {
                            $fbPostId = $pageId . '_' . $fbPostId;
                        }
                    }
                    $response = FacebookHandler::deletePost($token, $fbPostId);
                    break;
                    
                case 'instagram':
                    if (empty($token)) {
                        $stmt = $pdo->prepare("\n                            SELECT pt.access_token_encrypted\n                            FROM platform_connections pc\n                            JOIN platform_tokens pt ON pc.id = pt.platform_connection_id\n                            WHERE pc.client_id = :client_id AND pc.platform = 'facebook' AND pc.status = 'connected'\n                            LIMIT 1\n                        ");
                        $stmt->execute(['client_id' => $client_id]);
                        $fbConn = $stmt->fetch();
                        if (!empty($fbConn['access_token_encrypted'])) {
                            $token = decrypt($fbConn['access_token_encrypted']);
                        }
                    }
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
                    $platformError = "Deletion is not supported for {$platform} posts.";
            }
        } catch (Exception $platEx) {
            $err = $platEx->getMessage();
            $alreadyDeleted = false;
            $deletedPhrases = ['not found', 'does not exist', 'invalid object', 'unsupported get request', 'cannot be found'];
            foreach ($deletedPhrases as $phrase) {
                if (stripos($err, $phrase) !== false) {
                    $alreadyDeleted = true;
                    break;
                }
            }
            if ($alreadyDeleted) {
                $response = ['message' => 'Post already deleted on platform.'];
            } else {
                $platformError = $err;
                $externalDeleteSucceeded = false;
            }
        }
    } else {
        $response = ['message' => 'Post cleared locally (no external post ID found).'];
    }

    // 3. Delete physical media file from disk only if external delete succeeded
    if (!empty($mediaPath) && $externalDeleteSucceeded) {
        require_once __DIR__ . '/../storage/StorageService.php';
        StorageService::deletePostMedia($mediaPath, $client_id);
    }

    // 4. Hard delete post from Hub posts table only if external delete succeeded
    if ($dbPostFound && $externalDeleteSucceeded) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :post_id");
        $stmt->execute(['post_id' => $postId]);
    }

    if ($externalDeleteSucceeded) {
        log_message('info', "Successfully deleted post ID {$postId} (External ID: {$externalPostId}) on platform {$platform}", ['response' => $response]);

        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'message'  => 'Post deleted successfully',
            'response' => $response
        ]);
    } else {
        log_message('warning', "Failed to delete post externally", ['post_id' => $postId, 'platform' => $platform, 'error' => $platformError]);

        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'error'   => $platformError,
            'response'=> $response
        ]);
    }

} catch (Exception $e) {
    log_message('error', "Failed to delete post ID {$postId} on {$platform}", ['error' => $e->getMessage()]);

    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
