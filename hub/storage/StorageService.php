<?php
/**
 * StorageService.
 * Handles local file uploads and deletions in the local uploads folder.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/logger.php';

class StorageService {

    /**
     * Upload a local file to the local uploads directory.
     *
     * @param string $localPath Path to local file on disk
     * @param int    $clientId  Client identifier to structure folder paths
     * @return string|false     The storage path (relative path) or false on failure
     */
    public static function uploadTempFile($localPath, $clientId) {
        if (!file_exists($localPath)) {
            log_message('error', "Upload failed: file not found at {$localPath}");
            return false;
        }

        $fileSize = filesize($localPath);
        $mimeType = mime_content_type($localPath);
        $isImage = strpos($mimeType, 'image/') === 0;
        $isVideo = strpos($mimeType, 'video/') === 0;

        // Size validations: 8MB for images, 70MB for videos
        if ($isImage && $fileSize > (8 * 1024 * 1024)) {
            log_message('error', "Upload validation failed: image size {$fileSize} exceeds 8MB limit", ['client_id' => $clientId]);
            return false;
        }

        if ($isVideo && $fileSize > (70 * 1024 * 1024)) {
            log_message('error', "Upload validation failed: video size {$fileSize} exceeds 70MB limit", ['client_id' => $clientId]);
            return false;
        }

        if (!$isImage && !$isVideo) {
            log_message('error', "Upload validation failed: unsupported mime type {$mimeType}", ['client_id' => $clientId]);
            return false;
        }

        $tempJpgCreated = false;
        // Auto convert images to JPEG if they are not already JPEG (for Instagram API compatibility)
        if ($isImage && $mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') {
            try {
                $srcImg = null;
                if ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
                    $srcImg = imagecreatefrompng($localPath);
                } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
                    $srcImg = imagecreatefromwebp($localPath);
                } elseif ($mimeType === 'image/gif' && function_exists('imagecreatefromgif')) {
                    $srcImg = imagecreatefromgif($localPath);
                }
                
                if ($srcImg) {
                    $jpgPath = $localPath . '.jpg';
                    // Convert transparency to white background
                    $bg = imagecreatetruecolor(imagesx($srcImg), imagesy($srcImg));
                    $white = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $white);
                    imagecopy($bg, $srcImg, 0, 0, 0, 0, imagesx($srcImg), imagesy($srcImg));
                    
                    if (imagejpeg($bg, $jpgPath, 90)) {
                        imagedestroy($srcImg);
                        imagedestroy($bg);
                        // Point localPath to the newly converted JPG
                        $localPath = $jpgPath;
                        $mimeType = 'image/jpeg';
                        $fileSize = filesize($localPath);
                        $tempJpgCreated = true;
                    } else {
                        imagedestroy($srcImg);
                        imagedestroy($bg);
                    }
                }
            } catch (Exception $e) {
                log_message('warning', "Image conversion to JPEG failed: " . $e->getMessage());
            }
        }

        // Target path: clients/{client_id}/{timestamp}_{filename}
        $fileName = 'clients/' . $clientId . '/' . time() . '_' . basename($localPath);
        
        $destDir = __DIR__ . '/../uploads/clients/' . $clientId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = __DIR__ . '/../uploads/' . $fileName;

        $copied = copy($localPath, $destPath);

        // If we created a temp converted JPG, clean it up from the temp folder
        if ($tempJpgCreated && file_exists($localPath)) {
            @unlink($localPath);
        }

        if (!$copied) {
            log_message('error', "Upload failed: unable to copy file to local destination: {$destPath}", ['client_id' => $clientId]);
            return false;
        }

        log_message('info', "Local file uploaded successfully: {$fileName}", ['client_id' => $clientId, 'size' => $fileSize]);
        return $fileName;
    }

    /**
     * Get local public URL for a given storage path.
     *
     * @param string $storagePath
     * @return string Local URL
     */
    public static function getPublicUrl($storagePath) {
        $prefix = defined('PUBLIC_TUNNEL_URL') && !empty(PUBLIC_TUNNEL_URL) ? PUBLIC_TUNNEL_URL : HUB_BASE_URL;
        return rtrim($prefix, '/') . '/uploads/' . ltrim($storagePath, '/');
    }

    /**
     * Delete a file from the local uploads directory.
     *
     * @param string $storagePath
     * @return bool True on success, false on failure
     */
    public static function deleteFile($storagePath) {
        $fullPath = __DIR__ . '/../uploads/' . ltrim($storagePath, '/');
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                log_message('info', "Local file deleted: {$storagePath}");
                return true;
            }
            log_message('error', "Failed to delete local file: {$storagePath}");
            return false;
        }
        log_message('error', "Local delete failed: file not found at {$storagePath}");
        return false;
    }

    /**
     * Generate local URL.
     *
     * @param string $storagePath
     * @param int    $expiresInSeconds
     * @return string Local URL
     */
    public static function generateSignedUrl($storagePath, $expiresInSeconds = 3600) {
        return self::getPublicUrl($storagePath);
    }
}
