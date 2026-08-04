<?php
header('Content-Type: text/plain');

require_once __DIR__ . '/hub/utils/encryption.php';

try {
    // Connect to local Hub database
    $localPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=hub_db;charset=utf8mb4", 'root', '');
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch latest LinkedIn connection
    $stmt = $localPdo->query("
        SELECT pc.id, pc.external_account_id, pt.access_token_encrypted 
        FROM platform_connections pc
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.platform = 'linkedin' AND pc.status = 'connected'
        ORDER BY pc.id DESC
        LIMIT 1
    ");
    $conn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conn) {
        // Try without connected status
        $stmt = $localPdo->query("
            SELECT pc.id, pc.external_account_id, pt.access_token_encrypted 
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
    }

    echo "Found LinkedIn Connection: URN={$conn['external_account_id']}\n";
    $token = decrypt_token($conn['access_token_encrypted']);
    if (!$token) {
        die("Failed to decrypt access token.");
    }
    echo "Decrypted Token: " . substr($token, 0, 15) . "...\n\n";

    // Step A: Register the image asset upload
    $registerUrl = "https://api.linkedin.com/v2/assets?action=registerUpload";
    $registerPayload = [
        'registerUploadRequest' => [
            'recipes' => [
                'urn:li:digitalmediaRecipe:feedshare-image'
            ],
            'owner' => $conn['external_account_id'],
            'supportedUploadMechanism' => [
                'SYNCHRONOUS_UPLOAD'
            ]
        ]
    ];

    echo "1. Registering image...\n";
    $ch = curl_init($registerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerPayload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0'
    ]);
    $regRes = curl_exec($ch);
    $regCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Register Response Code: {$regCode}\n";
    echo "Register Response Body: {$regRes}\n\n";

    $regData = json_decode($regRes, true);
    if (empty($regData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadMechanism']['uploadUrl'])) {
        die("Failed to retrieve uploadUrl.");
    }

    $uploadUrl = $regData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadMechanism']['uploadUrl'];
    $assetUrn = $regData['value']['asset'] ?? null;

    // Create a dummy image
    $tempImage = __DIR__ . '/scratch_test_image.jpg';
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagejpeg($img, $tempImage);
    imagedestroy($img);

    echo "2. Uploading binary file...\n";
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tempImage));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: image/jpeg'
    ]);
    $uploadRes = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Upload Response Code: {$uploadCode}\n";
    echo "Upload Response Body: {$uploadRes}\n\n";

    @unlink($tempImage);

    if ($uploadCode < 200 || $uploadCode >= 300) {
        die("Binary upload failed.");
    }

    // Step C: Create the post
    $url = "https://api.linkedin.com/v2/posts";
    $imageUrn = str_replace('urn:li:digitalmediaAsset:', 'urn:li:image:', $assetUrn);

    $payload = [
        'author'         => $conn['external_account_id'],
        'commentary'     => 'Test Image Post from Debug Script',
        'visibility'     => 'PUBLIC',
        'distribution'   => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities'   => []
        ],
        'lifecycleState' => 'PUBLISHED',
        'content' => [
            'media' => [
                'id' => $imageUrn
            ]
        ]
    ];

    echo "3. Creating Post via /v2/posts...\n";
    echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0'
    ]);
    $postRes = curl_exec($ch);
    $postCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Post Response Code: {$postCode}\n";
    echo "Post Response Body: {$postRes}\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
