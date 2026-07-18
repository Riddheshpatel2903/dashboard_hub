<?php
/**
 * Client Dashboard Home (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

// If no client context exists (e.g., admin who hasn't picked a client), redirect to admin area
if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

$connCount = 0;
$platformsList = [];

// 1. Fetch connection statuses from the Hub
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            $connCount++;
            $platformsList[] = $conn['platform'];
        }
    }
}

// 2. Fetch total and scheduled posts count from local cache
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
    FROM posts_cache 
    WHERE client_id = :client_id
");
$stmt->execute(['client_id' => $client_id]);
$postStats = $stmt->fetch() ?: ['total' => 0, 'published' => 0, 'scheduled' => 0];

// Fetch 5 most recent posts
$stmtRecent = $pdo->prepare("
    SELECT id, hub_post_id, content, status, platform, scheduled_at, published_at 
    FROM posts_cache 
    WHERE client_id = :client_id AND status != 'deleted'
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmtRecent->execute(['client_id' => $client_id]);
$recentPosts = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Dashboard - Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 p-lg min-h-screen">
        <div class="max-w-[1440px] mx-auto space-y-lg">
            <!-- Page Header Actions -->
            <div class="flex justify-between items-end">
                <div>
                    <h2 class="font-display-lg text-display-lg text-on-surface">Dashboard Home</h2>
                    <p class="font-body-md text-on-surface-variant">Here's what's happening across your social landscape today.</p>
                </div>
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                   class="px-lg h-12 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-sm hover:opacity-90 transition-all shadow-sm active:scale-95">
                    <span class="material-symbols-outlined" data-icon="add_box">add_box</span>
                    <span>Create Post</span>
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                <!-- Card 1: Platforms -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Platforms</span>
                        <span class="text-primary font-data-metric text-data-metric bg-primary-container/10 px-xs rounded">Active</span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-md text-display-md leading-none"><?php echo $connCount; ?> <span class="text-body-sm font-normal text-on-surface-variant">/ 6 active</span></h3>
                        <p class="text-on-surface-variant text-body-sm">Connected Channels</p>
                    </div>
                    <div class="absolute bottom-0 right-0 w-24 h-12 opacity-50">
                        <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                            <path d="M0 35 Q 25 35 50 35 T 100 35" fill="none" stroke="#2031a9" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 2: Total Posts -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Total Cached</span>
                        <span class="text-green-600 font-data-metric text-data-metric bg-green-100 px-xs rounded">+100%</span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-md text-display-md leading-none"><?php echo (int)$postStats['total']; ?></h3>
                        <p class="text-on-surface-variant text-body-sm">All Time Count</p>
                    </div>
                    <div class="absolute bottom-0 right-0 w-24 h-12 opacity-50">
                        <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                            <path d="M0 35 L 20 25 L 40 30 L 60 15 L 80 20 L 100 5" fill="none" stroke="#2031a9" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 3: Published Posts -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Published</span>
                        <span class="text-green-600 font-data-metric text-data-metric bg-green-100 px-xs rounded">Live</span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-md text-display-md leading-none"><?php echo (int)$postStats['published']; ?></h3>
                        <p class="text-on-surface-variant text-body-sm">Released Content</p>
                    </div>
                    <div class="absolute bottom-0 right-0 w-24 h-12 opacity-50">
                        <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                            <path d="M0 38 L 10 30 L 20 35 L 30 25 L 40 30 L 50 15 L 60 20 L 70 10 L 80 15 L 90 5 L 100 12" fill="none" stroke="#2031a9" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>

                <!-- Card 4: Scheduled Queue -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Scheduled</span>
                        <span class="text-primary font-data-metric text-data-metric bg-primary-container/10 px-xs rounded">Queue</span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-md text-display-md leading-none text-primary"><?php echo (int)$postStats['scheduled']; ?></h3>
                        <p class="text-on-surface-variant text-body-sm">Awaiting Dispatch</p>
                    </div>
                    <div class="absolute bottom-0 right-0 w-24 h-12 opacity-30">
                        <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                            <circle cx="20" cy="20" fill="none" r="15" stroke="#2031a9" stroke-width="1"></circle>
                            <circle cx="80" cy="20" fill="none" r="10" stroke="#2031a9" stroke-width="1"></circle>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Area -->
            <div class="grid grid-cols-12 gap-gutter">
                <!-- Recent Activity Table/List -->
                <div class="col-span-12 bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden">
                    <div class="px-lg py-md border-b border-surface-variant flex justify-between items-center">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Recent Activity</h3>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="text-primary font-body-sm hover:underline cursor-pointer">View History</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-surface-container-low border-b border-surface-variant">
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Platform</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Summary</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Release Date</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Status</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-variant">
                                <?php if (empty($recentPosts)): ?>
                                    <tr>
                                        <td colspan="5" class="px-lg py-md text-center text-on-surface-variant font-body-md">
                                            No posts found. Start by composing a message!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentPosts as $post): 
                                        $platformIcon = 'face';
                                        $platformBg = 'bg-primary';
                                        if ($post['platform'] === 'facebook') {
                                            $platformIcon = 'facebook';
                                            $platformBg = 'bg-blue-600';
                                        } elseif ($post['platform'] === 'instagram') {
                                            $platformIcon = 'photo_camera';
                                            $platformBg = 'bg-pink-600';
                                        } elseif ($post['platform'] === 'youtube') {
                                            $platformIcon = 'play_circle';
                                            $platformBg = 'bg-red-600';
                                        } elseif ($post['platform'] === 'whatsapp') {
                                            $platformIcon = 'chat';
                                            $platformBg = 'bg-green-500';
                                        } elseif ($post['platform'] === 'linkedin') {
                                            $platformIcon = 'work';
                                            $platformBg = 'bg-blue-800';
                                        } elseif ($post['platform'] === 'google_business') {
                                            $platformIcon = 'store';
                                            $platformBg = 'bg-indigo-600';
                                        }
                                        
                                        $statusClass = 'bg-surface-container text-on-surface-variant';
                                        if ($post['status'] === 'published') {
                                            $statusClass = 'bg-green-100 text-green-700';
                                        } elseif ($post['status'] === 'scheduled') {
                                            $statusClass = 'bg-primary-container/20 text-primary';
                                        } elseif ($post['status'] === 'failed') {
                                            $statusClass = 'bg-error-container text-error';
                                        }
                                        
                                        $releaseTime = 'n/a';
                                        if ($post['status'] === 'published' && $post['published_at']) {
                                            $releaseTime = date('M d, H:i', strtotime($post['published_at']));
                                        } elseif ($post['status'] === 'scheduled' && $post['scheduled_at']) {
                                            $releaseTime = date('M d, H:i', strtotime($post['scheduled_at']));
                                        }
                                    ?>
                                        <tr class="hover:bg-secondary-container/10 transition-colors">
                                            <td class="px-lg py-md">
                                                <div class="flex items-center gap-xs">
                                                    <div class="w-8 h-8 rounded <?php echo $platformBg; ?> flex items-center justify-center text-white">
                                                        <span class="material-symbols-outlined text-sm"><?php echo $platformIcon; ?></span>
                                                    </div>
                                                    <span class="font-bold text-xs uppercase tracking-tight text-on-surface-variant ml-xs"><?php echo htmlspecialchars($post['platform']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-lg py-md text-on-surface font-body-md truncate max-w-xs">
                                                <?php echo htmlspecialchars($post['content']); ?>
                                            </td>
                                            <td class="px-lg py-md font-data-metric text-data-metric text-on-surface-variant">
                                                <?php echo htmlspecialchars($releaseTime); ?>
                                            </td>
                                            <td class="px-lg py-md">
                                                <span class="px-sm py-1 rounded-full text-xs font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($post['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-lg py-md text-right">
                                                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php?date=<?php echo date('Y-m-d', strtotime($post['published_at'] ?: $post['scheduled_at'] ?: '')); ?>" class="material-symbols-outlined text-on-surface-variant hover:text-primary transition-colors">more_vert</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
