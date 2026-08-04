<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

require_once __DIR__ . '/hub/utils/encryption.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=hub_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the latest LinkedIn connection
    $stmt = $pdo->query("
        SELECT pc.id, pc.external_account_id, pt.access_token_encrypted 
        FROM platform_connections pc
        JOIN platform_tokens pt ON pc.id = pt.platform_connection_id
        WHERE pc.platform = 'linkedin' AND pc.status = 'connected'
        ORDER BY pc.id DESC
        LIMIT 1
    ");
    $conn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conn) {
        die("No connected LinkedIn account found in database.");
    }

    echo "Found connection: ID={$conn['id']}, AuthorURN={$conn['external_account_id']}\n";

    // Decrypt access token
    $token = decrypt_token($conn['access_token_encrypted']);
    if (!$token) {
        die("Failed to decrypt access token.");
    }

    echo "Decrypted Token: " . substr($token, 0, 15) . "...\n\n";

    // Step 1: Register Upload
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

    echo "1. Registering Upload...\n";
    $ch = curl_init($registerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerPayload));
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
        die("Failed to get uploadUrl.");
    }

    $uploadUrl = $regData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadMechanism']['uploadUrl'];
    $assetUrn = $regData['value']['asset'] ?? null;

    echo "Upload URL: {$uploadUrl}\n";
    echo "Asset URN: {$assetUrn}\n\n";

    // Create a dummy image
    $tempImage = __DIR__ . '/scratch_test_image.jpg';
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagejpeg($img, $tempImage);
    imagedestroy($img);

    echo "2. Performing Binary Upload WITH Authorization header...\n";
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tempImage));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/octet-stream'
    ]);
    $uploadRes = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Upload (With Auth) Code: {$uploadCode}\n";
    echo "Upload (With Auth) Response: {$uploadRes}\n\n";

    echo "3. Performing Binary Upload WITHOUT Authorization header...\n";
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tempImage));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream'
    ]);
    $uploadRes2 = curl_exec($ch);
    $uploadCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Upload (No Auth) Code: {$uploadCode2}\n";
    echo "Upload (No Auth) Response: {$uploadRes2}\n\n";

    @unlink($tempImage);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
