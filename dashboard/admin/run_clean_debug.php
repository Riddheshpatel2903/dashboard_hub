<?php
error_reporting(0);
ini_set('display_errors', '0');

$dashPdo = require __DIR__ . '/../db/connection.php';
$hubPdo = require __DIR__ . '/../../hub/db/connection.php';

$activeFiles = [];

// 1. Collect from posts_cache
$stmtCache = $dashPdo->query("SELECT id, media_path, status FROM posts_cache WHERE status != 'deleted'");
$cacheRows = $stmtCache->fetchAll();
foreach ($cacheRows as $row) {
    if (!empty($row['media_path'])) {
        $bn = strtolower(basename($row['media_path']));
        if ($bn && $bn !== 'video.mp4') {
            $activeFiles[$bn] = 'posts_cache ID ' . $row['id'];
        }
    }
}

// 2. Collect from hub posts table
$stmtPosts = $hubPdo->query("SELECT id, media_temp_path, status FROM posts WHERE status != 'deleted'");
$hubRows = $stmtPosts->fetchAll();
foreach ($hubRows as $row) {
    if (!empty($row['media_temp_path'])) {
        $bn = strtolower(basename($row['media_temp_path']));
        if ($bn && $bn !== 'video.mp4') {
            $activeFiles[$bn] = 'posts temp ID ' . $row['id'];
        }
    }
}

// 3. Scan uploads directories
$dirsToScan = [
    __DIR__ . '/../../hub/uploads',
    __DIR__ . '/../uploads'
];

$report = [
    'active_db_files_count' => count($activeFiles),
    'active_db_files' => $activeFiles,
    'deleted_files' => [],
    'kept_files' => [],
    'total_bytes_freed' => 0
];

foreach ($dirsToScan as $dir) {
    if (!is_dir($dir)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $fileName = strtolower($file->getFilename());
            if (in_array($fileName, ['index.html', 'index.php', '.gitignore', '.ds_store'])) continue;

            $filePath = $file->getPathname();
            $fileSize = $file->getSize();

            if (!isset($activeFiles[$fileName])) {
                if (@unlink($filePath)) {
                    $report['deleted_files'][] = [
                        'path' => $filePath,
                        'name' => $fileName,
                        'size_kb' => round($fileSize / 1024, 1)
                    ];
                    $report['total_bytes_freed'] += $fileSize;
                }
            } else {
                $report['kept_files'][] = [
                    'path' => $filePath,
                    'name' => $fileName,
                    'reason' => $activeFiles[$fileName]
                ];
            }
        } elseif ($file->isDir()) {
            $dirPath = $file->getPathname();
            if (is_dir($dirPath) && count(scandir($dirPath)) <= 2) {
                @rmdir($dirPath);
            }
        }
    }
}

$report['formatted_bytes_freed'] = round($report['total_bytes_freed'] / (1024 * 1024), 2) . ' MB';

header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT);
