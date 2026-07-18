<?php
/**
 * WhatsApp Webhook Endpoint.
 * Endpoint: /webhooks/whatsapp_webhook.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../utils/logger.php';

$method = $_SERVER['REQUEST_METHOD'];

// 1. Handle Meta Webhook Verification Handshake (GET Request)
if ($method === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === WHATSAPP_VERIFY_TOKEN) {
        log_message('info', 'WhatsApp webhook verified successfully.');
        echo $challenge;
        exit();
    } else {
        http_response_code(403);
        log_message('warning', 'WhatsApp webhook verification failed.', ['received_token' => $token]);
        echo 'Forbidden';
        exit();
    }
}

// 2. Handle Webhook Payload (POST Request)
if ($method === 'POST') {
    $rawPayload = file_get_contents('php://input');
    $payload = json_decode($rawPayload, true);

    if (!$payload) {
        http_response_code(400);
        echo 'Invalid Payload';
        exit();
    }

    try {
        // A. Insert raw payload immediately for tracking
        $stmt = $pdo->prepare("
            INSERT INTO webhook_events (platform, raw_payload, processed)
            VALUES ('whatsapp', :payload, 0)
        ");
        $stmt->execute(['payload' => $rawPayload]);
        $eventId = $pdo->lastInsertId();

        // B. Send fast HTTP 200 response to Meta so they do not timeout and retry
        if (function_exists('fastcgi_finish_request')) {
            echo json_encode(['success' => true]);
            fastcgi_finish_request(); // Terminate connection with client while running tasks in background
        } else {
            // If not php-fpm, we process quickly
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        }

        // C. Parse the payload details
        $phoneNumberId = null;
        $messages = [];
        
        if (!empty($payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'])) {
            $phoneNumberId = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
        }

        if (!empty($payload['entry'][0]['changes'][0]['value']['messages'])) {
            $messages = $payload['entry'][0]['changes'][0]['value']['messages'];
        }

        if ($phoneNumberId && !empty($messages)) {
            // Resolve WABA connection to Client ID
            $stmt = $pdo->prepare("
                SELECT client_id FROM platform_connections 
                WHERE platform = 'whatsapp' AND external_account_id = :phone_number_id 
                LIMIT 1
            ");
            $stmt->execute(['phone_number_id' => $phoneNumberId]);
            $clientId = $stmt->fetchColumn();

            if ($clientId) {
                // Update client_id in webhook_events
                $stmt = $pdo->prepare("
                    UPDATE webhook_events SET client_id = :client_id WHERE id = :event_id
                ");
                $stmt->execute([
                    'client_id' => $clientId,
                    'event_id'  => $eventId
                ]);

                // Store messages in structured format
                foreach ($messages as $msg) {
                    $sender = $msg['from'] ?? '';
                    $type = $msg['type'] ?? 'text';
                    $timestamp = isset($msg['timestamp']) ? date('Y-m-d H:i:s', (int)$msg['timestamp']) : date('Y-m-d H:i:s');
                    
                    $text = null;
                    if ($type === 'text') {
                        $text = $msg['text']['body'] ?? null;
                    } elseif ($type === 'button') {
                        $text = $msg['button']['text'] ?? null;
                    } elseif ($type === 'interactive') {
                        $text = $msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? null;
                    } else {
                        // Media or other type placeholder description
                        $text = "[Received {$type} media]";
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO whatsapp_messages (client_id, sender_number, message_text, message_type, timestamp)
                        VALUES (:client_id, :sender, :text, :type, :timestamp)
                    ");
                    $stmt->execute([
                        'client_id' => $clientId,
                        'sender'    => $sender,
                        'text'      => $text,
                        'type'      => $type,
                        'timestamp' => $timestamp
                    ]);
                }
            }
        }

        // Mark event as processed
        $stmt = $pdo->prepare("
            UPDATE webhook_events SET processed = 1 WHERE id = :event_id
        ");
        $stmt->execute(['event_id' => $eventId]);

    } catch (Exception $e) {
        log_message('error', 'Error processing WhatsApp webhook', ['exception' => $e->getMessage()]);
    }
    
    exit();
}
