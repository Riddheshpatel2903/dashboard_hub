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

        // Fallback mime detection via extension if finfo / PHP returns application/octet-stream
        if (empty($mimeType) || $mimeType === 'application/octet-stream') {
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $extMap = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'bmp'  => 'image/bmp',
                'mp4'  => 'video/mp4',
                'mov'  => 'video/quicktime',
                'avi'  => 'video/x-msvideo',
                'mkv'  => 'video/x-matroska',
                'webm' => 'video/webm',
                'm4v'  => 'video/mp4',
                '3gp'  => 'video/3gpp'
            ];
            if (isset($extMap[$ext])) {
                $mimeType = $extMap[$ext];
            }
        }

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

        // Track the original temp file separately so we can clean it up regardless of conversion
        $originalTempPath = $localPath;
        $convertedTempPath = null; // Will be set if we create a converted file

        log_message('debug', "Before image conversion check: isImage=" . ($isImage?'yes':'no') . ", mimeType=" . $mimeType);

        // Auto convert non-JPEG images to JPEG for Instagram API compatibility.
        // BUG FIX: Save converted file with a CLEAN .jpg extension (not double-extension like photo.png.jpg)
        if ($isImage && $mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') {
            log_message('debug', "Entering conversion block for mimeType=" . $mimeType);
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
                    // Build a clean JPEG path: strip original extension, append .jpg
                    $baseWithoutExt = preg_replace('/\.[^.]+$/', '', $localPath);
                    $jpgPath = $baseWithoutExt . '.jpg';

                    // Convert transparency to white background
                    $bg = imagecreatetruecolor(imagesx($srcImg), imagesy($srcImg));
                    $white = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $white);
                    imagecopy($bg, $srcImg, 0, 0, 0, 0, imagesx($srcImg), imagesy($srcImg));

                    if (imagejpeg($bg, $jpgPath, 90)) {
                        imagedestroy($srcImg);
                        imagedestroy($bg);
                        // Point $localPath to the clean JPEG — this is what gets copied to uploads
                        $localPath = $jpgPath;
                        $convertedTempPath = $jpgPath; // remember for cleanup
                        $mimeType = 'image/jpeg';
                        $fileSize = filesize($localPath);
                        log_message('debug', "Successfully converted to JPEG: " . $jpgPath);
                    } else {
                        imagedestroy($srcImg);
                        imagedestroy($bg);
                        log_message('warning', "imagejpeg() call returned false — proceeding with original file.");
                    }
                } else {
                    log_message('warning', "Failed to create GdImage from source file — proceeding with original file.");
                }
            } catch (Exception $e) {
                log_message('warning', "Image conversion to JPEG failed: " . $e->getMessage() . " — proceeding with original file.");
            }
        }

        // Build the final storage filename. The incoming temp file already carries a timestamp
        // prefix (added by upload.php), so we just use its clean basename as-is.
        $cleanBasename = basename($localPath);
        $fileName = 'clients/' . $clientId . '/' . $cleanBasename;

        $destDir = __DIR__ . '/../uploads/clients/' . $clientId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = __DIR__ . '/../uploads/' . $fileName;

        $copied = copy($localPath, $destPath);

        // Clean up temp files: always delete the converted JPEG temp (if created),
        // and also delete the original source temp if it differs from the converted path.
        if ($convertedTempPath && file_exists($convertedTempPath)) {
            @unlink($convertedTempPath);
        }
        if ($originalTempPath !== $convertedTempPath && file_exists($originalTempPath)) {
            @unlink($originalTempPath);
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
        // HUB_BASE_URL auto-detects to the live domain on Hostinger (e.g. https://yourdomain.com/hub)
        // No tunnel needed — Instagram/Facebook fetch media directly from live HTTPS URL.
        return rtrim(trim(HUB_BASE_URL), '/') . '/uploads/' . ltrim($storagePath, '/');
    }

    /**
     * Download a remote media URL and store it in the local uploads directory.
     *
     * @param string $mediaUrl
     * @param int $clientId
     * @return string|false Relative storage path or false on failure
     */
    public static function uploadFromUrl($mediaUrl, $clientId) {
        if (empty($mediaUrl) || !filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
            log_message('warning', "StorageService::uploadFromUrl called with invalid URL", ['url' => $mediaUrl, 'client_id' => $clientId]);
            return false;
        }

        $urlParts = parse_url($mediaUrl);
        if (empty($urlParts['scheme']) || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)) {
            log_message('warning', "StorageService::uploadFromUrl unsupported URL scheme", ['url' => $mediaUrl, 'client_id' => $clientId]);
            return false;
        }

        $tempDir = sys_get_temp_dir();
        $extension = pathinfo($urlParts['path'] ?? '', PATHINFO_EXTENSION);
        $tempFile = tempnam($tempDir, 'remote_media_');
        if (!$tempFile) {
            log_message('error', "StorageService::uploadFromUrl failed to create temp file", ['client_id' => $clientId]);
            return false;
        }

        $downloaded = false;
        try {
            $ch = curl_init($mediaUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $fileData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($fileData === false || $httpCode >= 400 || empty($fileData)) {
                log_message('warning', "StorageService::uploadFromUrl failed to download remote media", ['url' => $mediaUrl, 'http_code' => $httpCode, 'client_id' => $clientId]);
                return false;
            }

            if (!empty($contentType) && empty($extension)) {
                $extensionMap = [
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    'video/mp4' => 'mp4',
                    'video/quicktime' => 'mov',
                    'video/webm' => 'webm',
                ];
                if (isset($extensionMap[$contentType])) {
                    $extension = $extensionMap[$contentType];
                }
            }

            if (!empty($extension)) {
                $newTempFile = $tempFile . '.' . $extension;
                rename($tempFile, $newTempFile);
                $tempFile = $newTempFile;
            }

            file_put_contents($tempFile, $fileData);
            $downloaded = true;
        } catch (Exception $e) {
            log_message('warning', "StorageService::uploadFromUrl exception while downloading remote media: " . $e->getMessage(), ['url' => $mediaUrl, 'client_id' => $clientId]);
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }

        if (!$downloaded) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }

        $stored = self::uploadTempFile($tempFile, $clientId);
        if ($stored === false) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }

        return $stored;
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

    /**
     * Delete all physical instances of a media file across Hub and Dashboard upload directories.
     *
     * @param string $mediaPath File path, relative path, or filename
     * @param int|null $clientId
     * @return int Count of files physically deleted
     */
    public static function deletePostMedia($mediaPath, $clientId = null) {
        if (empty($mediaPath)) return 0;

        // Skip local file deletion if it is a remote HTTP/HTTPS URL
        if (preg_match('/^https?:\/\//i', $mediaPath)) {
            return 0;
        }

        $baseName = basename($mediaPath);
        if (empty($baseName) || $baseName === '.' || $baseName === '..') return 0;

        $cleanPath = ltrim(str_replace(['\\', 'uploads/'], ['/', ''], $mediaPath), '/');

        $hubDir = realpath(__DIR__ . '/..') ?: '';
        $dashboardDir = realpath(__DIR__ . '/../../dashboard') ?: '';

        $candidatePaths = [
            $mediaPath,
            $hubDir . '/uploads/' . $cleanPath,
            $hubDir . '/uploads/' . $baseName,
            $dashboardDir . '/uploads/' . $cleanPath,
            $dashboardDir . '/uploads/' . $baseName,
        ];

        if ($clientId) {
            $candidatePaths[] = $hubDir . '/uploads/clients/' . $clientId . '/' . $baseName;
            $candidatePaths[] = $dashboardDir . '/uploads/clients/' . $clientId . '/' . $baseName;
        }

        $deletedCount = 0;
        foreach (array_unique($candidatePaths) as $p) {
            if (!empty($p) && file_exists($p) && is_file($p)) {
                if (@unlink($p)) {
                    $deletedCount++;
                    log_message('info', "Unlinked physical post media: {$p}");
                }
            }
        }
        return $deletedCount;
    }

    /**
     * Scans Hub and Dashboard uploads folders and removes any orphan media files
     * not associated with active database post records.
     *
     * @param PDO|null $pdo PDO database connection instance
     * @return array Summary of deleted files count and total bytes freed
     */
    public static function cleanOrphanUploads($pdo = null) {
        if (!$pdo) {
            try {
                $pdo = require __DIR__ . '/../db/connection.php';
            } catch (Exception $e) {
                return ['success' => false, 'error' => 'DB connection failed for storage cleanup: ' . $e->getMessage()];
            }
        }

        // 1. Gather all active media filenames in database across both Dashboard DB and Hub DB
        $activeFiles = [];

        try {
            $dashPdo = require __DIR__ . '/../../dashboard/db/connection.php';
            if ($dashPdo instanceof PDO) {
                $stmtCache = $dashPdo->query("SELECT media_path FROM posts_cache WHERE media_path IS NOT NULL AND media_path != ''");
                while ($row = $stmtCache->fetch(PDO::FETCH_ASSOC)) {
                    $bn = basename($row['media_path']);
                    if ($bn) $activeFiles[strtolower($bn)] = true;
                }
            }
        } catch (Exception $e) {
            log_message('warning', "Dashboard DB query warning during storage cleanup: " . $e->getMessage());
        }

        try {
            $hubPdo = require __DIR__ . '/../db/connection.php';
            if ($hubPdo instanceof PDO) {
                $stmtPosts = $hubPdo->query("SELECT media_temp_path FROM posts WHERE media_temp_path IS NOT NULL AND media_temp_path != '' AND status != 'deleted'");
                while ($row = $stmtPosts->fetch(PDO::FETCH_ASSOC)) {
                    $bn = basename($row['media_temp_path']);
                    if ($bn) $activeFiles[strtolower($bn)] = true;
                }

                $stmtMedia = $hubPdo->query("SELECT storage_path FROM media_files WHERE storage_path IS NOT NULL AND storage_path != ''");
                while ($row = $stmtMedia->fetch(PDO::FETCH_ASSOC)) {
                    $bn = basename($row['storage_path']);
                    if ($bn) $activeFiles[strtolower($bn)] = true;
                }
            }
        } catch (Exception $e) {
            log_message('warning', "Hub DB query warning during storage cleanup: " . $e->getMessage());
        }

        // 2. Scan uploads directories
        $hubUploads = __DIR__ . '/../uploads';
        $dashUploads = __DIR__ . '/../../dashboard/uploads';
        $dirsToScan = array_filter([$hubUploads, $dashUploads], 'is_dir');

        $deletedCount = 0;
        $bytesFreed = 0;

        foreach ($dirsToScan as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $fileName = strtolower($file->getFilename());
                    // Skip index.html / .gitignore / system files
                    if (in_array($fileName, ['index.html', 'index.php', '.gitignore', '.ds_store'])) continue;

                    // If file is NOT in active database records, physically delete it from the folder!
                    if (!isset($activeFiles[$fileName])) {
                        $fSize = $file->getSize();
                        $fPath = $file->getPathname();
                        if (@unlink($fPath)) {
                            $deletedCount++;
                            $bytesFreed += $fSize;
                            log_message('info', "Storage cleanup: physically deleted orphan file {$fPath} ({$fSize} bytes)");
                        }
                    }
                } elseif ($file->isDir()) {
                    // Remove empty subdirectories if all files inside were deleted
                    $dirPath = $file->getPathname();
                    if (count(scandir($dirPath)) <= 2) {
                        @rmdir($dirPath);
                    }
                }
            }
        }

        return [
            'success'       => true,
            'files_deleted' => $deletedCount,
            'bytes_freed'   => $bytesFreed,
            'formatted_size'=> round($bytesFreed / (1024 * 1024), 2) . ' MB'
        ];
    }
}
