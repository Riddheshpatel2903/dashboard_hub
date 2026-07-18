<?php
/**
 * WhatsApp Cloud API Handler.
 * Manages sending template and text messages.
 */

class WhatsAppHandler {

    private static $version = 'v18.0';

    /**
     * Sends a pre-approved WhatsApp Template Message.
     * Note: Template messages do not require active 24-hour conversation sessions.
     *
     * @param string $token          System User access token
     * @param string $phoneNumberId  WhatsApp Phone Number ID (external account ID)
     * @param string $to             Recipient phone number (with country code, no +)
     * @param string $templateName   Name of template
     * @param string $languageCode   Language code (default: en_US)
     * @param array  $components     Template components variables (optional)
     * @return array
     * @throws Exception
     */
    public static function sendTemplateMessage($token, $phoneNumberId, $to, $templateName, $languageCode = 'en_US', array $components = []) {
        $url = "https://graph.facebook.com/" . self::$version . "/{$phoneNumberId}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return self::executeRequest($token, $url, $payload);
    }

    /**
     * Sends a free-form WhatsApp Text Message.
     * Enforces the 24-hour communication session window.
     *
     * @param string $token         System User access token
     * @param string $phoneNumberId WhatsApp Phone Number ID (external account ID)
     * @param string $to            Recipient phone number (with country code, no +)
     * @param string $text          Text content
     * @return array
     * @throws Exception
     */
    public static function sendTextMessage($token, $phoneNumberId, $to, $text) {
        $pdo = require __DIR__ . '/../db/connection.php';

        // 1. Resolve client_id from the connection's external_account_id
        $stmt = $pdo->prepare("
            SELECT client_id FROM platform_connections 
            WHERE platform = 'whatsapp' AND external_account_id = :phone_number_id 
            LIMIT 1
        ");
        $stmt->execute(['phone_number_id' => $phoneNumberId]);
        $clientId = $stmt->fetchColumn();

        if (!$clientId) {
            throw new Exception("Local connection not found for WhatsApp Phone Number ID: {$phoneNumberId}");
        }

        // 2. Fetch the last inbound message timestamp from this recipient ($to)
        $stmt = $pdo->prepare("
            SELECT timestamp FROM whatsapp_messages 
            WHERE client_id = :client_id AND sender_number = :sender_number 
            ORDER BY timestamp DESC LIMIT 1
        ");
        $stmt->execute([
            'client_id'     => $clientId,
            'sender_number' => $to
        ]);
        $lastInboundTime = $stmt->fetchColumn();

        if (!$lastInboundTime) {
            throw new Exception("WhatsApp 24-hour session validation failed: No previous inbound messages recorded from {$to}.");
        }

        $elapsedSeconds = time() - strtotime($lastInboundTime);
        if ($elapsedSeconds > 86400) {
            throw new Exception("WhatsApp 24-hour session validation failed: Last inbound message from {$to} was " . round($elapsedSeconds / 3600, 1) . " hours ago (max 24 hours). Template messages must be used to open conversations.");
        }

        // 3. Dispatch text message
        $url = "https://graph.facebook.com/" . self::$version . "/{$phoneNumberId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'body' => $text
            ]
        ];

        return self::executeRequest($token, $url, $payload);
    }

    /**
     * Shared helper to execute WhatsApp Graph requests.
     */
    private static function executeRequest($token, $url, array $payload) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Unknown WhatsApp API Error';
            $code = $data['error']['code'] ?? $httpCode;
            throw new Exception("WhatsApp API Exception (Code: {$code}): {$msg}", $httpCode);
        }

        return $data;
    }
}
