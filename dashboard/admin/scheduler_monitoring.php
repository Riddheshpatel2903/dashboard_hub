<?php
/**
 * Cron & Post Scheduler Monitoring Page (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/role_check.php'; // Ensures logged-in staff/admin
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

$error = '';
$successMsg = '';

// Handle manual trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_cron') {
    // Call cron trigger proxy
    $triggerUrl = HUB_BASE_URL . '/jobs/scheduler.php?token=' . urlencode(CRON_SECRET);
    $ch = curl_init($triggerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $schedRes = curl_exec($ch);
    curl_close($ch);

    $triggerUrlWorker = HUB_BASE_URL . '/jobs/queue_worker.php?token=' . urlencode(CRON_SECRET);
    $ch = curl_init($triggerUrlWorker);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $workerRes = curl_exec($ch);
    curl_close($ch);

    $successMsg = 'Scheduler and Queue Worker executed successfully.';
}

// 1. Get statistics
$stats = [
    'scheduled' => 0,
    'processing' => 0,
    'queued' => 0,
    'publishing' => 0,
    'published' => 0,
    'failed' => 0
];

$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM posts_cache 
    GROUP BY status
");
while ($row = $stmt->fetch()) {
    $status = $row['status'];
    if (array_key_exists($status, $stats)) {
        $stats[$status] = (int)$row['count'];
    }
}

// 2. Fetch upcoming scheduled posts
$stmtScheduled = $pdo->query("
    SELECT pc.id, pc.client_id, c.name as client_name, pc.content, pc.platform, pc.scheduled_at, pc.retry_count
    FROM posts_cache pc
    LEFT JOIN clients c ON pc.client_id = c.id
    WHERE pc.status = 'scheduled'
    ORDER BY pc.scheduled_at ASC
    LIMIT 10
");
$upcomingPosts = $stmtScheduled->fetchAll();

// 3. Fetch failed posts in retry queue or permanent failures
$stmtFailed = $pdo->query("
    SELECT pc.id, pc.client_id, c.name as client_name, pc.content, pc.platform, pc.last_attempt_at, pc.retry_count
    FROM posts_cache pc
    LEFT JOIN clients c ON pc.client_id = c.id
    WHERE pc.status = 'failed'
    ORDER BY pc.last_attempt_at DESC
    LIMIT 10
");
$failedPosts = $stmtFailed->fetchAll();

// 4. Fetch last 50 lines of app.log
function getLastLogLines($filename, $lines = 50) {
    if (!file_exists($filename)) {
        return "Log file not found at " . htmlspecialchars($filename);
    }
    
    $file = fopen($filename, "r");
    if (!$file) {
        return "Failed to open log file.";
    }
    
    $line_arr = [];
    while (($line = fgets($file)) !== false) {
        $line_arr[] = $line;
    }
    fclose($file);
    
    $start = max(0, count($line_arr) - $lines);
    $slice = array_slice($line_arr, $start);
    return implode("", $slice);
}

$logPath = __DIR__ . '/../../hub/logs/app.log';
$logConsoleContent = getLastLogLines($logPath, 50);

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Post Scheduler Monitor | Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content Area -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="p-lg max-w-[1440px] mx-auto space-y-lg">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface">Scheduler & Worker Control</h1>
                    <p class="font-body-md text-on-surface-variant">Real-time cron scheduler status logs, processing queues, and error retries.</p>
                </div>
                <div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="run_cron">
                        <button type="submit" class="inline-flex items-center gap-xs px-lg py-md bg-primary text-on-primary rounded-lg font-bold hover:opacity-90 active:scale-95 transition-all shadow-md">
                            <span class="material-symbols-outlined text-sm">run_circle</span>
                            <span>Trigger Cron Run Now</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($successMsg): ?>
                <div class="bg-success-container text-on-success-container p-md rounded-lg flex items-center gap-md border border-success/20">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($successMsg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Overview Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-gutter">
                <!-- Scheduled -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-primary text-2xl">schedule</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['scheduled']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Scheduled</div>
                </div>

                <!-- Processing -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-info text-2xl animate-spin">sync</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['processing']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Processing</div>
                </div>

                <!-- Queued -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-[#F59E0B] text-2xl">view_list</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['queued']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Queued</div>
                </div>

                <!-- Publishing -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-[#6366F1] text-2xl animate-pulse">cloud_upload</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['publishing']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Publishing</div>
                </div>

                <!-- Published -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-success text-2xl">task_alt</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['published']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Published</div>
                </div>

                <!-- Failed -->
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs text-center space-y-xs">
                    <span class="material-symbols-outlined text-error text-2xl">error_outline</span>
                    <div class="font-display-sm text-display-sm font-bold text-on-surface"><?php echo $stats['failed']; ?></div>
                    <div class="font-body-sm text-on-surface-variant uppercase text-[10px] font-bold">Failed</div>
                </div>
            </div>

            <!-- Two column content section -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
                
                <!-- Left Column: Upcoming & Failed queues -->
                <div class="lg:col-span-7 space-y-lg">
                    
                    <!-- Upcoming scheduled posts -->
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-xs p-lg space-y-md">
                        <div class="flex justify-between items-center">
                            <h3 class="font-headline-sm text-headline-sm font-bold">Upcoming Scheduled Posts</h3>
                            <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">Queue Limit: 10</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-body-sm">
                                <thead>
                                    <tr class="border-b border-surface-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">
                                        <th class="py-sm">Client</th>
                                        <th class="py-sm">Platform</th>
                                        <th class="py-sm">Content</th>
                                        <th class="py-sm">Release Time</th>
                                        <th class="py-sm">Retries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($upcomingPosts)): ?>
                                        <tr>
                                            <td colspan="5" class="py-md text-center text-on-surface-variant italic">No posts currently scheduled.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($upcomingPosts as $post): ?>
                                            <tr class="border-b border-surface-variant/40 hover:bg-surface-container-low transition-colors">
                                                <td class="py-sm font-bold"><?php echo htmlspecialchars($post['client_name']); ?></td>
                                                <td class="py-sm capitalize font-semibold"><?php echo htmlspecialchars($post['platform']); ?></td>
                                                <td class="py-sm truncate max-w-[200px]"><?php echo htmlspecialchars($post['content']); ?></td>
                                                <td class="py-sm"><?php echo htmlspecialchars($post['scheduled_at']); ?></td>
                                                <td class="py-sm"><?php echo $post['retry_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Failed queue -->
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-xs p-lg space-y-md">
                        <div class="flex justify-between items-center">
                            <h3 class="font-headline-sm text-headline-sm font-bold text-error">Failed Posts Queue</h3>
                            <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">Show Last: 10</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-body-sm">
                                <thead>
                                    <tr class="border-b border-surface-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">
                                        <th class="py-sm">Client</th>
                                        <th class="py-sm">Platform</th>
                                        <th class="py-sm">Content</th>
                                        <th class="py-sm">Last Attempt</th>
                                        <th class="py-sm">Retries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($failedPosts)): ?>
                                        <tr>
                                            <td colspan="5" class="py-md text-center text-on-surface-variant italic">No failed postings recorded.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($failedPosts as $post): ?>
                                            <tr class="border-b border-surface-variant/40 hover:bg-surface-container-low transition-colors">
                                                <td class="py-sm font-bold"><?php echo htmlspecialchars($post['client_name']); ?></td>
                                                <td class="py-sm capitalize font-semibold text-error"><?php echo htmlspecialchars($post['platform']); ?></td>
                                                <td class="py-sm truncate max-w-[200px]"><?php echo htmlspecialchars($post['content']); ?></td>
                                                <td class="py-sm"><?php echo htmlspecialchars($post['last_attempt_at']); ?></td>
                                                <td class="py-sm"><?php echo $post['retry_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Console Logs -->
                <div class="lg:col-span-5 flex flex-col">
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-xs p-lg space-y-md flex-grow flex flex-col">
                        <div>
                            <h3 class="font-headline-sm text-headline-sm font-bold">Cron Console Log</h3>
                            <p class="text-xs text-on-surface-variant">Last 50 events from the application log (`app.log`).</p>
                        </div>
                        
                        <!-- Log Terminal Window -->
                        <div class="flex-grow bg-[#0f172a] text-[#38bdf8] font-mono p-md rounded-lg overflow-y-auto max-h-[500px] text-xs leading-normal select-all shadow-inner border border-slate-800 whitespace-pre-wrap">
                            <?php echo htmlspecialchars($logConsoleContent); ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</body>
</html>
