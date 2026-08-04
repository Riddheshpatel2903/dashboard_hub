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
$postType = isset($_POST['post_type']) && strtolower($_POST['post_type']) === 'video' ? 'video' : 'image';
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

$allowedByPostType = [
    'image' => ['facebook', 'instagram', 'linkedin', 'google_business'],
    'video' => ['facebook', 'instagram', 'youtube', 'google_business']
];

foreach ($platforms as $platform) {
    if (!in_array($platform, $allowedByPostType[$postType] ?? [], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ucfirst($postType) . ' posts cannot be sent to ' . ucfirst(str_replace('google_business', 'Google Business Profile', $platform)) . '.']);
        exit();
    }
}

if (!empty($_FILES['media']['name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $fileName = strtolower($_FILES['media']['name']);
    $mimeType = strtolower($_FILES['media']['type'] ?? '');
    $isVideo = strpos($mimeType, 'video/') === 0 || preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $fileName);
    $isImage = strpos($mimeType, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $fileName);

    if ($postType === 'video' && !$isVideo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Video posts require a video attachment.']);
        exit();
    }

    if ($postType === 'image' && !$isImage) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image posts require an image attachment.']);
        exit();
    }
}

// Convert HTML datetime-local format (YYYY-MM-DDTHH:MM) to SQL format
$sqlScheduledAt = null;
if ($scheduleType === 'later' && !empty($scheduledAt)) {
    $schedTime = strtotime($scheduledAt);
    if ($schedTime <= time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Scheduled release time must be in the future.']);
        exit();
    }
    $sqlScheduledAt = date('Y-m-d H:i:s', $schedTime);
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
        
        // Re-establish DB connection to prevent "MySQL server has gone away" 
        // in case the file upload took too long and the server connection timed out.
        $GLOBALS['dashboard_pdo'] = null;
        $pdo = require __DIR__ . '/../db/connection.php';
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

            // Verify database connection is alive before query execution (timeouts can happen during long cURL calls)
            try {
                $pdo->query("SELECT 1");
            } catch (Exception $connEx) {
                $GLOBALS['dashboard_pdo'] = null;
                $pdo = require __DIR__ . '/../db/connection.php';
            }

            if ($status === 'failed') {
                if ($hubPostId) {
                    $stmtDel = $pdo->prepare("DELETE FROM posts_cache WHERE hub_post_id = :hub_post_id");
                    $stmtDel->execute(['hub_post_id' => $hubPostId]);
                }
            } else {
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
