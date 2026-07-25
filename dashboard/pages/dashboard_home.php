<?php
/**
 * Client Dashboard Home (Stitch Social Mission Control Design System).
 * Screen Reference: 478333b85abb4cb196404442b66f7964
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

// If no client context exists (e.g., admin who hasn't picked a client), redirect to admin area
if ($client_id === null && ($user_role === 'staff' || $user_role === 'admin')) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

$connectedPlatforms = [];
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}

// Fetch 5 most recent posts
$stmtRecent = $pdo->prepare("
    SELECT id, hub_post_id, content, status, platform, media_path, scheduled_at, published_at, created_at 
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
    <title>Dashboard Home | Stitch Social Mission Control</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-grid {
            background-image: radial-gradient(circle, #c6c5d6 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md antialiased overflow-x-hidden">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 p-lg min-h-screen">
        <div class="max-w-[1440px] mx-auto space-y-lg">
            <!-- Page Header Actions -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface">Dashboard Home</h1>
                    <p class="font-body-md text-on-surface-variant">Here's what's happening across your social landscape today.</p>
                </div>
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                   class="px-lg h-12 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-sm hover:opacity-90 transition-all shadow-sm active:scale-95">
                    <span class="material-symbols-outlined">add_box</span>
                    <span>Create Post</span>
                </a>
            </div>

            <!-- Filter Bar Card -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm">
                <form id="dashboard-filter-form" onsubmit="event.preventDefault(); reloadDashboardData();" class="flex flex-wrap items-end gap-md">
                    <!-- Platform Selector -->
                    <div class="flex-1 min-w-[200px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block uppercase" for="filter-platform">SELECT CHANNEL</label>
                        <select id="filter-platform" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary capitalize">
                            <option value="">All Channels</option>
                            <?php foreach ($connectedPlatforms as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>">
                                    <?php echo htmlspecialchars($p === 'google_business' ? 'Google Profile' : $p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Pickers -->
                    <div class="w-[180px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block uppercase" for="filter-start-date">START DATE</label>
                        <input type="date" id="filter-start-date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>

                    <div class="w-[180px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block uppercase" for="filter-end-date">END DATE</label>
                        <input type="date" id="filter-end-date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-sm">
                        <button type="submit" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-body-sm font-bold hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">filter_list</span>
                            <span>Filter</span>
                        </button>
                        <button type="button" onclick="clearDashboardFilters();" class="h-10 px-lg bg-surface-container text-on-surface-variant rounded-lg font-body-sm font-bold hover:bg-surface-container-high transition-all flex items-center justify-center">
                            Clear
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Grid: 4 Stitch Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                <!-- Card 1: Platforms -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Platforms</span>
                        <span class="text-primary font-data-metric text-data-metric bg-primary-container/10 px-xs rounded">Active</span>
                    </div>
                    <div class="z-10">
                        <h3 id="stat-connections" class="font-display-md text-display-md leading-none"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>
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
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Total Posts</span>
                        <span class="text-[#1F9D6B] font-data-metric text-data-metric bg-green-100 px-xs rounded">+12%</span>
                    </div>
                    <div class="z-10">
                        <h3 id="stat-total-posts" class="font-display-md text-display-md leading-none"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>
                        <p class="text-on-surface-variant text-body-sm">All Cached Content</p>
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
                        <span class="text-[#1F9D6B] font-data-metric text-data-metric bg-green-100 px-xs rounded">Live</span>
                    </div>
                    <div class="z-10">
                        <h3 id="stat-published-posts" class="font-display-md text-display-md leading-none"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>
                        <p class="text-on-surface-variant text-body-sm">Released Publications</p>
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
                        <h3 id="stat-scheduled-posts" class="font-display-md text-display-md leading-none text-primary"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>
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

            <!-- Performance Timeline Chart Container Card -->
            <div id="dashboard-analytics-card" class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-surface-variant pb-sm gap-md">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Performance Timeline</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Real-time aggregate channel metrics and trends fetched from Hub.</p>
                    </div>
                    <div id="analytics-active-badge" class="px-sm py-[2px] rounded-full text-[10px] font-bold uppercase tracking-tight bg-primary-container/20 text-primary border border-primary-fixed capitalize">
                        All Channels
                    </div>
                </div>
                <div id="dashboard-analytics-content" class="min-h-[250px] flex items-center justify-center">
                    <span class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></span>
                </div>
            </div>

            <!-- Recent Activity Ledger Table (Stitch Design System) -->
            <div class="grid grid-cols-12 gap-gutter">
                <div class="col-span-12 bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden">
                    <div class="px-lg py-md border-b border-surface-variant flex justify-between items-center">
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Recent Activity</h3>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="text-primary font-body-sm font-bold hover:underline cursor-pointer">View History</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-surface-container-low border-b border-surface-variant">
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Platform</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Content Summary</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Release Date</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase">Status</th>
                                    <th class="px-lg py-sm font-data-label text-data-label text-on-surface-variant uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-variant">
                                <?php if (empty($recentPosts)): ?>
                                    <tr>
                                        <td colspan="5" class="px-lg py-md text-center text-on-surface-variant font-body-md">
                                            No posts recorded in history yet. Start by creating a campaign post!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentPosts as $post): 
                                        $platformIcon = 'face';
                                        $platformBg = 'bg-primary';
                                        if ($post['platform'] === 'facebook') {
                                            $platformIcon = 'public';
                                            $platformBg = 'bg-[#1877F2]';
                                        } elseif ($post['platform'] === 'instagram') {
                                            $platformIcon = 'photo_camera';
                                            $platformBg = 'bg-[#cc2366]';
                                        } elseif ($post['platform'] === 'youtube') {
                                            $platformIcon = 'play_circle';
                                            $platformBg = 'bg-[#FF0000]';
                                        } elseif ($post['platform'] === 'linkedin') {
                                            $platformIcon = 'work';
                                            $platformBg = 'bg-[#0077B5]';
                                        } elseif ($post['platform'] === 'google_business') {
                                            $platformIcon = 'store';
                                            $platformBg = 'bg-[#4285F4]';
                                        }
                                        
                                        $statusClass = 'bg-surface-container text-on-surface-variant';
                                        if ($post['status'] === 'published') {
                                            $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B]';
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
                                        } else {
                                            $releaseTime = date('M d, H:i', strtotime($post['created_at']));
                                        }
                                    ?>
                                        <tr class="hover:bg-secondary-container/10 transition-colors">
                                            <td class="px-lg py-md">
                                                <div class="flex items-center gap-xs">
                                                    <div class="w-8 h-8 rounded-full <?php echo $platformBg; ?> flex items-center justify-center text-white shadow-xs">
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
                                                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="text-primary hover:underline font-bold text-xs">Inspect</a>
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

    <script>
    function reloadDashboardData() {
        const platform = document.getElementById('filter-platform').value;
        const startDate = document.getElementById('filter-start-date').value;
        const endDate = document.getElementById('filter-end-date').value;

        // 1. Show spinners
        const spinner = '<span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>';
        document.getElementById('stat-connections').innerHTML = spinner;
        document.getElementById('stat-total-posts').innerHTML = spinner;
        document.getElementById('stat-published-posts').innerHTML = spinner;
        document.getElementById('stat-scheduled-posts').innerHTML = spinner;
        
        document.getElementById('dashboard-analytics-content').innerHTML = 
            '<span class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></span>';

        // Update badge
        const badge = document.getElementById('analytics-active-badge');
        badge.textContent = platform ? platform.replace('_', ' ') : 'All Channels';

        // 2. Fetch statistics counts
        const queryParams = new URLSearchParams({
            platform: platform,
            start_date: startDate,
            end_date: endDate
        }).toString();

        fetch(`<?php echo DASHBOARD_BASE_URL; ?>/pages/ajax_stats.php?${queryParams}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const activeCountText = platform ? ' / 1 active' : ' / 6 active';
                    document.getElementById('stat-connections').innerHTML = 
                        data.connections_count + ` <span class="text-body-sm font-normal text-on-surface-variant">${activeCountText}</span>`;
                    document.getElementById('stat-total-posts').textContent = data.total_posts;
                    document.getElementById('stat-published-posts').textContent = data.published_posts;
                    document.getElementById('stat-scheduled-posts').textContent = data.scheduled_posts;
                } else {
                    console.error("Failed to load stats:", data.error);
                }
            })
            .catch(err => console.error("Error loading stats:", err));

        // 3. Fetch analytics timeline chart
        fetch(`<?php echo DASHBOARD_BASE_URL; ?>/pages/ajax_analytics.php?${queryParams}`)
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('dashboard-analytics-content');
                container.innerHTML = html;
                // Re-evaluate script elements inside AJAX response
                const scripts = container.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            })
            .catch(err => {
                console.error("Error loading analytics:", err);
                document.getElementById('dashboard-analytics-content').innerHTML = 
                    '<p class="text-error text-xs font-bold text-center">Failed to load live analytics metrics from Hub.</p>';
            });
    }

    function clearDashboardFilters() {
        document.getElementById('filter-platform').value = '';
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        document.getElementById('filter-start-date').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('filter-end-date').value = new Date().toISOString().split('T')[0];
        
        reloadDashboardData();
    }

    document.addEventListener("DOMContentLoaded", function() {
        reloadDashboardData();
    });
    </script>
</body>
</html>
