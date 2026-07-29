<?php
/**
 * Inbox / Feedbacks / Messages aggregation endpoint.
 * Endpoint: GET /api/inbox.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/encryption.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/token_helper.php';

require_once __DIR__ . '/../platforms/FacebookHandler.php';
require_once __DIR__ . '/../platforms/InstagramHandler.php';
require_once __DIR__ . '/../platforms/GoogleBusinessHandler.php';

$platform = $_GET['platform'] ?? '';
$type = $_GET['type'] ?? ''; // comments, messages, or reviews
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$senderNumber = $_GET['sender_number'] ?? null; // WhatsApp conversations filter

if (empty($platform) || empty($type)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'Missing platform or type parameter']);
    exit();
}

try {
    $results = [];

    // WhatsApp Inbox reads exclusively from the local database
    if ($platform === 'whatsapp') {
        if ($type !== 'messages') {
            throw new Exception("WhatsApp only supports 'messages' inbox type.");
        }

        $sql = "SELECT id, sender_number, message_text, message_type, timestamp, created_at 
                FROM whatsapp_messages 
                WHERE client_id = :client_id";
        $params = ['client_id' => $client_id];

        if ($senderNumber) {
            $sql .= " AND sender_number = :sender_number";
            $params['sender_number'] = $senderNumber;
        }

        $sql .= " ORDER BY timestamp DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
    } else {
        // Retrieve credentials for API connections
        $connSql = "SELECT pc.id as connection_id, pc.external_account_id, pt.access_token_encrypted 
                    FROM platform_connections pc
                    JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
                    WHERE pc.client_id = :client_id AND pc.platform = :platform AND pc.status = 'connected'";
        
        $params = [
            'client_id' => $client_id,
            'platform'  => $platform
        ];

        // If post_id is supplied, look up the connection linked specifically to that post
        if ($postId > 0) {
            $connSql = "SELECT pc.id as connection_id, pc.external_account_id, pt.access_token_encrypted, p.external_post_id
                        FROM posts p
                        JOIN platform_connections pc ON p.platform_connection_id = pc.id
                        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
                        WHERE p.id = :post_id AND p.client_id = :client_id AND pc.platform = :platform";
            $params['post_id'] = $postId;
        }

        $stmt = $pdo->prepare($connSql);
        $stmt->execute($params);
        $connection = $stmt->fetch();

        if (!$connection) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'error' => "No active connection or post found for client on platform '{$platform}'."]);
            exit();
        }

        $externalAccountId = $connection['external_account_id'];
        $token = get_valid_platform_token($pdo, $client_id, $platform);
        $externalPostId = $connection['external_post_id'] ?? null;

        // Fetch feed comments or reviews from API
        if ($type === 'comments') {
            if (empty($externalPostId)) {
                throw new Exception("Comments fetch requires a valid published post_id.");
            }

            if ($platform === 'facebook') {
                $results = FacebookHandler::getComments($token, ensureFacebookCompositeId($pdo, $client_id, $externalPostId));
            } elseif ($platform === 'instagram') {
                $results = InstagramHandler::getComments($token, $externalPostId);
            } elseif ($platform === 'youtube') {
                // YouTube commentThread fetching
                $url = "https://www.googleapis.com/youtube/v3/commentThreads?part=snippet&videoId=" . urlencode($externalPostId);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]);
                $res = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $data = json_decode($res, true);
                if ($httpCode >= 400) {
                    throw new Exception("YouTube Comments API failure: " . ($data['error']['message'] ?? 'Unknown Error'));
                }
                $results = $data;
            } else {
                throw new Exception("Platform '{$platform}' does not support comments fetch.");
            }

        } elseif ($type === 'reviews') {
            if ($platform === 'google_business') {
                $results = GoogleBusinessHandler::getReviews($token, $externalAccountId);
            } else {
                throw new Exception("Platform '{$platform}' does not support reviews.");
            }
        } else {
            throw new Exception("Unsupported inbox query type: '{$type}'");
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data'    => $results
    ]);

} catch (Exception $e) {
    log_message('error', "Inbox aggregation request failure", ['error' => $e->getMessage()]);
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
