<?php
/**
 * Hub Media Upload Endpoint.
 * Endpoint: POST /api/upload.php
 */

require_once __DIR__ . '/authenticate_request.php'; // Defines $client_id
require_once __DIR__ . '/../storage/StorageService.php';
require_once __DIR__ . '/../utils/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json', true, 405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

if (empty($_FILES['media'])) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'No media file provided in upload.']);
    exit();
}

$file = $_FILES['media'];

// Validate file upload error code
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['success' => false, 'error' => 'File upload error code: ' . $file['error']]);
    exit();
}

try {
    // Generate a temporary folder inside hub if it does not exist
    $tempDir = __DIR__ . '/../storage/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $tempLocalPath = $tempDir . '/' . time() . '_' . basename($file['name']);
    
    // Move uploaded file to our temporary location
    if (!move_uploaded_file($file['tmp_name'], $tempLocalPath)) {
        throw new Exception("Failed to move uploaded file to temporary directory.");
    }

    // Call storage service to upload to B2
    $storagePath = StorageService::uploadTempFile($tempLocalPath, $client_id);

    if (!$storagePath) {
        throw new Exception("B2 upload failed via StorageService.");
    }

    // Clean up temporary local file
    unlink($tempLocalPath);

    header('Content-Type: application/json', true, 201);
    echo json_encode([
        'success'         => true,
        'media_temp_path' => $storagePath,
        'public_url'      => StorageService::getPublicUrl($storagePath)
    ]);

} catch (Exception $e) {
    log_message('error', 'API upload failure', ['exception' => $e->getMessage()]);
    if (isset($tempLocalPath) && file_exists($tempLocalPath)) {
        unlink($tempLocalPath);
    }
    
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
