<?php
/**
 * Admin Upload Storage Garbage Collector & Maintenance Tool.
 * Scans uploads directories and purges unreferenced media files from deleted posts.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../../hub/storage/StorageService.php';

$res = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['run'])) {
    $res = StorageService::cleanOrphanUploads($pdo);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Storage Cleanup Maintenance | Mission Control</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-background text-on-surface font-body-md antialiased min-h-screen">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <main class="ml-[240px] pt-16 p-lg">
        <div class="max-w-4xl mx-auto space-y-lg">
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface font-bold">Storage Cleanup Maintenance</h1>
                <p class="font-body-md text-on-surface-variant">Purge unreferenced media files from deleted posts to free up server disk space.</p>
            </div>

            <?php if ($res): ?>
                <div class="p-lg bg-surface-container-lowest border border-primary/20 rounded-xl shadow-xs space-y-xs">
                    <div class="flex items-center gap-sm text-[#1F9D6B] font-bold text-lg">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>Storage Garbage Collection Complete!</span>
                    </div>
                    <p class="text-sm text-on-surface"><strong>Files Deleted:</strong> <?php echo (int)$res['files_deleted']; ?></p>
                    <p class="text-sm text-on-surface"><strong>Disk Space Freed:</strong> <?php echo htmlspecialchars($res['formatted_size']); ?></p>
                </div>
            <?php endif; ?>

            <div class="p-lg bg-surface-container-lowest border border-surface-variant rounded-xl shadow-xs space-y-md">
                <h3 class="font-headline-sm text-lg font-bold text-on-surface">Run Disk Storage Cleanup</h3>
                <p class="text-xs text-on-surface-variant">
                    This tool scans <code>/hub/uploads/</code> and <code>/dashboard/uploads/</code> against active database post records. 
                    Any media file on disk that does not belong to an active post (or was left behind after deletion) will be permanently unlinked.
                </p>
                <form method="POST">
                    <button type="submit" class="px-lg h-10 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-xs hover:opacity-90 transition-all shadow-xs">
                        <span class="material-symbols-outlined text-sm">cleaning_services</span>
                        <span>Run Storage Garbage Collector Now</span>
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
