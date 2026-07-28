<?php
/**
 * Publish Post Endpoint.
 * Endpoint: POST /api/post.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../storage/StorageService.php';

// Include all platform handlers
require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/YouTubeHandler.php';
require_once __DIR__ . '/../platforms/LinkedInHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$platformsInput = $input['platforms'] ?? $input['platform'] ?? [];
$content = $input['content'] ?? '';
$mediaTempPath = $input['media_temp_path'] ?? null; // e.g. path inside B2
$title = $input['title'] ?? 'New Post'; // Used for YouTube title
$recipient = $input['to'] ?? $input['recipient'] ?? null; // Used for WhatsApp

if (empty($platformsInput)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing platform(s) field']);
    exit();
}

if (!is_array($platformsInput)) {
    $platformsInput = [$platformsInput];
}

$results = [];

foreach ($platformsInput as $platform) {
    try {
        // 1. Fetch connection details and access token
        $stmt = $pdo->prepare("
            SELECT pc.id as connection_id, pc.external_account_id, pt.access_token_encrypted, pt.refresh_token_encrypted 
            FROM platform_connections pc
            JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
            WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'
            LIMIT 1
        ");
        $stmt->execute([
            'client_id' => $client_id,
            'platform'  => $platform
        ]);
        
        $connection = $stmt->fetch();
        if (!$connection) {
            $results[$platform] = [
                'success' => false,
                'error'   => "No active connected account found for this client on {$platform}."
            ];
            continue;
        }

        $connectionId = $connection['connection_id'];
        $externalAccountId = $connection['external_account_id'];
        $token = decrypt($connection['access_token_encrypted']);

        // Generate CDN public URL if media is supplied
        $mediaPublicUrl = $mediaTempPath ? StorageService::getPublicUrl($mediaTempPath) : null;
        
        $scheduledAt = !empty($input['scheduled_at']) ? $input['scheduled_at'] : null;
        $initialStatus = $scheduledAt ? 'scheduled' : 'queued';

        // Define initial post entry
        $stmt = $pdo->prepare("
            INSERT INTO posts (client_id, platform_connection_id, content, media_temp_path, status, scheduled_at)
            VALUES (:client_id, :connection_id, :content, :media_path, :status, :scheduled_at)
        ");
        $stmt->execute([
            'client_id'     => $client_id,
            'connection_id' => $connectionId,
            'content'       => $content,
            'media_path'    => $mediaTempPath,
            'status'        => $initialStatus,
            'scheduled_at'  => $scheduledAt
        ]);
        $postId = $pdo->lastInsertId();

        // If scheduled for later, do not publish immediately
        if ($scheduledAt) {
            $results[$platform] = [
                'success' => true,
                'post_id' => (int)$postId,
                'status'  => 'scheduled',
                'scheduled_at' => $scheduledAt
            ];
            continue;
        }

        $externalPostId = null;
        $responseBody = '';
        $httpStatusCode = 200;
        $success = false;

        // 2. Dispatch to specific Platform API Handler
        switch ($platform) {
            case 'facebook':
                $localPath = null;
                if ($mediaTempPath) {
                    $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                    if (!file_exists($localPath)) {
                        $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                        if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                            $localPath = $mediaTempPath;
                        }
                    }
                }
                $res = FacebookHandler::publishPost($token, $externalAccountId, $content, $mediaPublicUrl, $localPath);
                $externalPostId = $res['post_id'] ?? $res['id'] ?? null;
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


            case 'youtube':
                // YouTube resumable upload requires local absolute path
                // We resolve this from the temporary local path, or download B2 file to temp if needed.
                // For simplicity, we search for the local temp file, or build placeholder absolute path.
                $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                if (!file_exists($localPath)) {
                    $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                    if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                        $localPath = $mediaTempPath;
                    }
                }
                $res = YouTubeHandler::uploadVideo($token, $localPath, $title, $content);
                $externalPostId = $res['id'] ?? null;
                $responseBody = json_encode($res);
                $success = true;
                break;

            case 'linkedin':
                $localPath = null;
                if ($mediaTempPath) {
                    $localPath = __DIR__ . '/../uploads/' . ltrim($mediaTempPath, '/');
                    if (!file_exists($localPath)) {
                        $localPath = __DIR__ . '/../storage/temp/' . basename($mediaTempPath);
                        if (!file_exists($localPath) && $mediaTempPath && file_exists($mediaTempPath)) {
                            $localPath = $mediaTempPath;
                        }
                    }
                }
                $res = LinkedInHandler::publishPost($token, $externalAccountId, $content, $localPath);
                $externalPostId = $res['id'] ?? null;
                $responseBody = json_encode($res);
                $success = true;
                break;

            case 'google_business':
                $res = GoogleBusinessHandler::createPost($token, $externalAccountId, $content, $mediaPublicUrl);
                $externalPostId = $res['name'] ?? null; // GBP API returns localPost resource name as ID
                $responseBody = json_encode($res);
                $success = true;
                break;

            default:
                throw new Exception("Platform '{$platform}' not supported.");
        }

        // 3. Update internal post status and log attempt
        if ($success) {
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET status = 'published', external_post_id = :ext_id, published_at = CURRENT_TIMESTAMP
                WHERE id = :post_id
            ");
            $stmt->execute([
                'ext_id'  => $externalPostId,
                'post_id' => $postId
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                VALUES (:post_id, :http_code, :response, 1)
            ");
            $stmt->execute([
                'post_id'   => $postId,
                'http_code' => 200,
                'response'  => $responseBody
            ]);

            $results[$platform] = [
                'success'        => true,
                'post_id'        => $postId,
                'external_id'    => $externalPostId,
                'response'       => json_decode($responseBody, true)
            ];
        }

    } catch (Exception $e) {
        $httpStatusCode = $e->getCode() ?: 500;
        $responseBody = $e->getMessage();
        log_message('error', "Publish failed on {$platform}", ['message' => $responseBody]);

        if (isset($postId)) {
            $stmt = $pdo->prepare("
                UPDATE posts SET status = 'failed' WHERE id = :post_id
            ");
            $stmt->execute(['post_id' => $postId]);

            $stmt = $pdo->prepare("
                INSERT INTO post_logs (post_id, http_status_code, response_body, success)
                VALUES (:post_id, :http_code, :response, 0)
            ");
            $stmt->execute([
                'post_id'   => $postId,
                'http_code' => $httpStatusCode,
                'response'  => $responseBody
            ]);
        }

        $results[$platform] = [
            'success' => false,
            'post_id' => isset($postId) ? (int)$postId : null,
            'error'   => $responseBody
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'results' => $results
]);
