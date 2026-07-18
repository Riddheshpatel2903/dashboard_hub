<?php
/**
 * Queue Worker Job.
 * Processes batches of queued posts to prevent API rate limit bursts.
 * Run via cron: * * * * * php /path/to/hub/jobs/queue_worker.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../storage/StorageService.php';

// Include Platform Handlers
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/WhatsAppHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

try {
    // 1. Fetch queued posts limited by batch size
    $stmt = $pdo->prepare("
        SELECT p.id as post_id, p.client_id, p.content, p.media_temp_path, 
               pc.platform, pc.external_account_id, pt.access_token_encrypted
        FROM posts p
        JOIN platform_connections pc ON p.platform_connection_id = pc.id
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE p.status = 'queued'
        LIMIT :batch_size
    ");
    // Bind as integer specifically for LIMIT clause compatibility
    $stmt->bindValue(':batch_size', QUEUE_BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $queuedPosts = $stmt->fetchAll();

    if (empty($queuedPosts)) {
        exit(); // No posts to process
    }

    foreach ($queuedPosts as $post) {
        $postId = $post['post_id'];
        $clientId = $post['client_id'];
        $platform = $post['platform'];
        $content = $post['content'];
        $mediaTempPath = $post['media_temp_path'];
        $externalAccountId = $post['external_account_id'];
        
        $token = decrypt($post['access_token_encrypted']);
        $mediaPublicUrl = $mediaTempPath ? StorageService::getPublicUrl($mediaTempPath) : null;

        $externalPostId = null;
        $responseBody = '';
        $httpStatusCode = 200;
        $success = false;

        try {
            // 2. Publish post using specific platform handler
            switch ($platform) {
                case 'facebook':
                    $res = FacebookHandler::publishPost($token, $externalAccountId, $content, $mediaPublicUrl);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;
                    
                case 'instagram':
                    if (empty($mediaPublicUrl)) {
                        throw new Exception("Instagram requires media. No media provided.");
                    }
                    $res = InstagramHandler::publishPost($token, $externalAccountId, $content, $mediaPublicUrl);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'whatsapp':
                    // Queue processing sends text message by default
                    $res = WhatsAppHandler::sendTextMessage($token, $externalAccountId, '[Recipient Placeholder]', $content);
                    $externalPostId = $res['messages'][0]['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'youtube':
                    $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                    if (!file_exists($localPath)) {
                        $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                        if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                            $localPath = $mediaTempPath;
                        }
                    }
                    $res = YouTubeHandler::uploadVideo($token, $localPath, "Scheduled Video", $content);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'linkedin':
                    $res = LinkedInHandler::publishPost($token, $externalAccountId, $content);
                    $externalPostId = $res['id'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                case 'google_business':
                    $res = GoogleBusinessHandler::createPost($token, $externalAccountId, $content, $mediaPublicUrl);
                    $externalPostId = $res['name'] ?? null;
                    $responseBody = json_encode($res);
                    $success = true;
                    break;

                default:
                    throw new Exception("Unsupported platform: {$platform}");
            }

            // 3. Update post status to published
            $stmtUpdate = $pdo->prepare("
                UPDATE posts 
                SET status = 'published', external_post_id = :ext_id, published_at = CURRENT_TIMESTAMP
                WHERE id = :post_id
            ");
            $stmtUpdate->execute([
                'ext_id'  => $externalPostId,
                'post_id' => $postId
            ]);

            $stmtLog = $pdo->prepare("
                INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                VALUES (:post_id, 200, :response, 1)
            ");
            $stmtLog->execute([
                'post_id'  => $postId,
                'response' => $responseBody
            ]);

            log_message('info', "Queue worker published post ID {$postId} successfully on {$platform}");

        } catch (Exception $e) {
            $httpStatusCode = $e->getCode() ?: 500;
            $responseBody = $e->getMessage();
            log_message('error', "Queue worker failed publishing post ID {$postId} on {$platform}", ['error' => $responseBody]);

            $stmtUpdate = $pdo->prepare("
                UPDATE posts SET status = 'failed' WHERE id = :post_id
            ");
            $stmtUpdate->execute(['post_id' => $postId]);

            $stmtLog = $pdo->prepare("
                INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                VALUES (:post_id, :http_code, :response, 0)
            ");
            $stmtLog->execute([
                'post_id'   => $postId,
                'http_code' => $httpStatusCode,
                'response'  => $responseBody
            ]);
        }
    }

} catch (Exception $e) {
    log_message('error', "Queue worker execution failed", ['error' => $e->getMessage()]);
}
