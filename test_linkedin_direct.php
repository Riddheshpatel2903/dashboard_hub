<?php
header('Content-Type: text/plain');

require_once __DIR__ . '/hub/utils/encryption.php';
require_once __DIR__ . '/hub/utils/logger.php';
require_once __DIR__ . '/hub/platforms/LinkedInHandler.php';

try {
    // Connect to local Hub database
    $localPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=hub_db;charset=utf8mb4", 'root', '');
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch latest LinkedIn connection
    $stmt = $localPdo->query("
        SELECT pc.external_account_id, pt.access_token_encrypted 
        FROM platform_connections pc
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.platform = 'linkedin'
        ORDER BY pc.id DESC
        LIMIT 1
    ");
    $conn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conn) {
        die("No LinkedIn connection found in local database.");
    }

    $token = decrypt_token($conn['access_token_encrypted']);
    $authorUrn = $conn['external_account_id'];

    echo "Author URN: {$authorUrn}\n";

    // Create a dummy image
    $tempImage = __DIR__ . '/test_image.jpg';
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagejpeg($img, $tempImage);
    imagedestroy($img);

    // Run initializeUpload
    $initUrl = 'https://api.linkedin.com/rest/images?action=initializeUpload';
    $initPayload = [
        'initializeUploadRequest' => [
            'owner' => $authorUrn
        ]
    ];

    echo "1. Initializing upload...\n";
    $ch = curl_init($initUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($initPayload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: 202607'
    ]);
    $initRes = curl_exec($ch);
    $initCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Init HTTP Code: {$initCode}\n";
    echo "Init Response: {$initRes}\n\n";

    $initData = json_decode($initRes, true);
    $uploadUrl = $initData['value']['uploadUrl'] ?? null;
    $imageUrn = $initData['value']['image'] ?? null;

    if (!$uploadUrl) {
        die("Failed to get uploadUrl.");
    }

    echo "Upload URL: {$uploadUrl}\n\n";

    // Try PUT upload
    echo "2. Trying PUT upload...\n";
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tempImage));
    curl_setopt($ch, CURLOPT_HEADER, true); // Get headers to see the server name/error
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    
    $uploadRes = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Upload HTTP Code: {$uploadCode}\n";
    echo "Upload Response (Headers + Body):\n{$uploadRes}\n\n";

    @unlink($tempImage);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Clean up self
@unlink(__FILE__);
