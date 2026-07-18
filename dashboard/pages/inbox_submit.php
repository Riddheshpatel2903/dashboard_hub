<?php
/**
 * Inbox Reply Submit Handler.
 * Endpoint: POST /dashboard/pages/inbox_submit.php
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/hub_client.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$platform = $input['platform'] ?? 'whatsapp';
$recipient = trim($input['recipient'] ?? '');
$messageText = trim($input['message'] ?? '');
$templateName = $input['template_name'] ?? null;

if (empty($recipient)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing recipient conversation reference.']);
    exit();
}

if (empty($messageText) && empty($templateName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message body cannot be empty.']);
    exit();
}

try {
    if ($platform === 'whatsapp') {
        $additional = ['to' => $recipient];
        
        if (!empty($templateName)) {
            // Send Template Message
            $additional['template_name'] = $templateName;
            $additional['language_code'] = $input['language_code'] ?? 'en_US';
            $additional['template_components'] = $input['template_components'] ?? [];
            $res = hubPost($client_id, 'whatsapp', '', null, $additional);
        } else {
            // Send standard text message (enforces 24h window on Hub)
            $res = hubPost($client_id, 'whatsapp', $messageText, null, $additional);
        }

        if (!empty($res['success']) && !empty($res['results']['whatsapp']['success'])) {
            echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
        } else {
            $err = $res['error'] ?? $res['results']['whatsapp']['error'] ?? 'Hub rejected message dispatch.';
            throw new Exception($err);
        }
    } else {
        throw new Exception("Replying is currently only supported for WhatsApp conversations.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
