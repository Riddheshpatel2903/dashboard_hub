<?php
/**
 * Composer Submit Handler.
 * Endpoint: POST /dashboard/pages/composer_submit.php
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$platforms = $_POST['platforms'] ?? [];
$content = trim($_POST['content'] ?? '');
$scheduleType = $_POST['schedule_type'] ?? 'now';
$scheduledAt = $_POST['scheduled_at'] ?? null;
$title = $_POST['title'] ?? 'New Dashboard Upload'; // Supported for YouTube titles

if (empty($platforms)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please select at least one social media platform.']);
    exit();
}

if (empty($content) && empty($_FILES['media'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide either text content or upload a media attachment.']);
    exit();
}

// Convert HTML datetime-local format (YYYY-MM-DDTHH:MM) to SQL format
$sqlScheduledAt = null;
if ($scheduleType === 'later' && !empty($scheduledAt)) {
    $sqlScheduledAt = date('Y-m-d H:i:s', strtotime($scheduledAt));
}

try {
    $mediaTempPath = null;

    // 1. Upload media attachment to the Hub if present
    if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media'];
        
        $uploadRes = hubUploadFile($client_id, $file['tmp_name'], $file['name'], $file['type']);
        if (empty($uploadRes['success']) || empty($uploadRes['media_temp_path'])) {
            $err = $uploadRes['error'] ?? 'File upload failed on Hub server.';
            throw new Exception("Media Upload Failure: " . $err);
        }
        $mediaTempPath = $uploadRes['media_temp_path'];
    }

    $results = [];

    // 2. Loop through selected platforms and call the Hub
    $overallSuccess = true;
    $errors = [];

    foreach ($platforms as $platform) {
        $additional = [];
        if ($sqlScheduledAt) {
            $additional['scheduled_at'] = $sqlScheduledAt;
        }
        if ($platform === 'youtube') {
            $additional['title'] = $title;
        }

        $res = hubPost($client_id, $platform, $content, $mediaTempPath, $additional);
        
        $platformRes = $res['results'][$platform] ?? null;
        $isSuccess = !empty($res['success']) && !empty($platformRes) && !empty($platformRes['success']);
        
        if ($platformRes) {
            $hubPostId = !empty($platformRes['post_id']) ? (int)$platformRes['post_id'] : null;
            $status = $isSuccess ? ($platformRes['status'] ?? 'published') : 'failed';
            $externalPostId = $platformRes['external_id'] ?? null;
            $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("
                INSERT INTO posts_cache (hub_post_id, client_id, content, status, platform, media_path, scheduled_at, published_at, external_post_id)
                VALUES (:hub_post_id, :client_id, :content, :status, :platform, :media_path, :scheduled_at, :published_at, :ext_id)
                ON DUPLICATE KEY UPDATE status = VALUES(status), external_post_id = VALUES(external_post_id), media_path = VALUES(media_path)
            ");
            $stmt->execute([
                'hub_post_id'  => $hubPostId,
                'client_id'    => $client_id,
                'content'      => $content,
                'status'       => $status,
                'platform'     => $platform,
                'media_path'   => $mediaTempPath,
                'scheduled_at' => $sqlScheduledAt,
                'published_at' => $publishedAt,
                'ext_id'       => $externalPostId
            ]);
        }

        if ($isSuccess) {
            $results[$platform] = [
                'success' => true,
                'status'  => $status,
                'post_id' => $hubPostId
            ];
        } else {
            $overallSuccess = false;
            $errMessage = 'Hub server rejected publication.';
            if (!empty($res['error'])) {
                $errMessage = $res['error'];
            } elseif (isset($platformRes['error'])) {
                $errMessage = $platformRes['error'];
            }
            $errors[] = ucfirst($platform) . ": " . $errMessage;
            
            $results[$platform] = [
                'success' => false,
                'error'   => $errMessage
            ];
        }
    }

    echo json_encode([
        'success' => $overallSuccess,
        'results' => $results,
        'error'   => implode(' | ', $errors)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
