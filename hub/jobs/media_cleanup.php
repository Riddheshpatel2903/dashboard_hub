<?php
/**
 * Media Cleanup Job.
 * Daily cron job enforcing the 90-day media retention policy.
 * Run via cron: 0 1 * * * php /path/to/hub/jobs/media_cleanup.php
 */

$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../storage/StorageService.php';
require_once __DIR__ . '/../utils/logger.php';

try {
    // 1. Fetch expired media entries
    $stmt = $pdo->prepare("
        SELECT id, storage_path, file_size_bytes FROM media_files 
        WHERE delete_after <= CURRENT_TIMESTAMP
    ");
    $stmt->execute();
    $expiredMedia = $stmt->fetchAll();

    if (empty($expiredMedia)) {
        exit(); // No files require cleanup
    }

    $deletedCount = 0;
    $bytesCleaned = 0;

    $stmtDelete = $pdo->prepare("
        DELETE FROM media_files 
        WHERE id = :id
    ");

    foreach ($expiredMedia as $media) {
        $fileId = $media['id'];
        $path = $media['storage_path'];
        $size = (int)$media['file_size_bytes'];

        // A. Remove from Backblaze B2 bucket
        $removed = StorageService::deleteFile($path);
        
        if ($removed) {
            // B. Delete DB tracking row
            $stmtDelete->execute(['id' => $fileId]);
            $deletedCount++;
            $bytesCleaned += $size;
        } else {
            log_message('error', "Media cleanup job failed to delete file: {$path} from B2. DB row was preserved.");
        }
    }

    $mbCleaned = round($bytesCleaned / (1024 * 1024), 2);
    log_message('info', "Media cleanup job completed: deleted {$deletedCount} files, freeing {$mbCleaned} MB.");

} catch (Exception $e) {
    log_message('error', "Media cleanup job execution failed", ['error' => $e->getMessage()]);
}
