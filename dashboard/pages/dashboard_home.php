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
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['connections_status_' . $client_id]);
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected' && $conn['platform'] !== 'whatsapp') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}

// Check for Google Search Console connection status via Hub connections list
$isSeoConnected = in_array('search_console', $connectedPlatforms);

$seoSummary = [
    'clicks' => 0,
    'impressions' => 0,
    'ctr' => 0.0,
    'position' => 0.0,
    'is_connected' => false,
    'site_url' => ''
];

if ($isSeoConnected) {
    $seoSummary['is_connected'] = true;

    // Resolve the GSC verified property url
    $siteUrl = '';
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['platform'] === 'search_console') {
            $siteUrl = $conn['external_account_id'];
            break;
        }
    }
    $seoSummary['site_url'] = $siteUrl;

    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');

    $res = hubGetSearchAnalytics($client_id, $startDate, $endDate);
    $rows = [];
    if (!empty($res['success']) && is_array($res['data'])) {
        $rows = $res['data'];
    }

    $sumClicks = 0;
    $sumImpressions = 0;
    $weightedPosSum = 0;
    foreach ($rows as $r) {
        $clicks = $r['clicks'] ?? 0;
        $impressions = $r['impressions'] ?? 0;
        $position = $r['position'] ?? 0.0;

        $sumClicks += $clicks;
        $sumImpressions += $impressions;
        $weightedPosSum += ($position * $impressions);
    }
    $seoSummary['clicks'] = $sumClicks;
    $seoSummary['impressions'] = $sumImpressions;
    $seoSummary['ctr'] = ($sumImpressions > 0) ? round(($sumClicks / $sumImpressions) * 100, 2) : 0;
    $seoSummary['position'] = ($sumImpressions > 0) ? round($weightedPosSum / $sumImpressions, 1) : 0.0;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Home | Social Hub</title>
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
                    <h1 class="font-display-lg text-display-lg text-on-surface">Home</h1>
                </div>
                <div class="flex items-center gap-md">
                    <?php
                    $maxSynced = getOverallLastSyncedTime($hubRes['connections'] ?? []);
                    $lastSyncedStr = getRelativeTimeString($maxSynced);
                    ?>
                    <span id="last-synced-label" class="text-xs text-on-surface-variant font-medium">Last synced: <?php echo $lastSyncedStr; ?></span>
                    
                    <button id="btn-refresh-posts" type="button" onclick="triggerDashboardSync(this)"
                       class="px-md h-12 bg-surface-container hover:bg-surface-container-high text-on-surface-variant rounded-lg font-bold flex items-center gap-sm transition-all shadow-sm active:scale-95">
                        <span class="material-symbols-outlined text-sm" id="refresh-icon">sync</span>
                        <span id="refresh-label">Refresh Data</span>
                    </button>
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                       class="px-lg h-12 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-sm hover:opacity-90 transition-all shadow-sm active:scale-95">
                        <span class="material-symbols-outlined">add_box</span>
                        <span>Create Post</span>
                    </a>
                </div>
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
                    
                </div>

                <!-- Card 2: Total Posts -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Total Posts</span>
                    </div>
                    <div class="z-10">
                        <h2 id="stat-total-posts" class="font-display-lg text-display-lg leading-none"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h2>
                    
                    </div>
                    
                </div>

                <!-- Card 3: Published Posts -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Published</span>
                    </div>
                    <div class="z-10">
                        <h3 id="stat-published-posts" class="font-display-lg text-display-lg leading-none"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>

                    </div>
                </div>

                <!-- Card 4: Scheduled Queue -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-data-label uppercase tracking-wider">Scheduled</span>

                    </div>
                    <div class="z-10">
                        <h3 id="stat-scheduled-posts" class="font-display-lg text-display-lg leading-none text-primary"><span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span></h3>
                    </div>
                </div>
            </div>
            <!-- Two Column Layout: SEO Analytics (Left) vs. Recent Activity (Right) -->
            <div class="grid grid-cols-12 gap-gutter items-start">
                <!-- Column 1: SEO Analytics Overview (Left, 6/12 width) -->
                <div class="col-span-12 lg:col-span-6 bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden p-lg flex flex-col justify-between">
                    <div class="border-b border-surface-variant pb-sm mb-md flex justify-between items-center">
                        <div>
                            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">SEO Analytics</h3>
                            <p class="text-on-surface-variant text-[11px] mt-xs"><span class="font-bold text-primary"><?php echo htmlspecialchars($seoSummary['site_url'] ?: 'No site linked'); ?></span></p>
                        </div>
                        <?php if ($seoSummary['is_connected']): ?>
                            <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/seo.php" class="text-primary font-body-sm font-bold  cursor-pointer flex items-center gap-xs">
                                <span>View More</span>
                                <span class="material-symbols-outlined text-xs">arrow_forward</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$seoSummary['is_connected']): ?>
                        <div class="flex-grow flex flex-col items-center justify-center py-xl text-center space-y-md text-on-surface-variant">
                            <span class="material-symbols-outlined text-4xl text-outline-variant">search</span>
                            <h4 class="font-bold text-sm text-on-surface">Google Search Console Not Connected</h4>
                            <a href="connections.php" class="inline-flex items-center gap-xs px-md h-9 bg-primary text-on-primary rounded-lg font-bold text-xs hover:opacity-90 active:scale-95 transition-all">
                                <span class="material-symbols-outlined text-sm">link</span>
                                <span>Link Account</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- GSC Metric Blocks Grid -->
                        <div class="grid grid-cols-2 gap-md py-xs flex-grow">
                            <!-- Clicks Card -->
                            <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl flex items-center gap-md shadow-xs">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                                    <span class="material-symbols-outlined">ads_click</span>
                                </div>
                                <div>
                                    <p class="font-data-label text-on-surface-variant uppercase text-[10px]">Total Clicks</p>
                                    <p class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo number_format($seoSummary['clicks']); ?></p>
                                </div>
                            </div>

                            <!-- Impressions Card -->
                            <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl flex items-center gap-md shadow-xs">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                                    <span class="material-symbols-outlined">visibility</span>
                                </div>
                                <div>
                                    <p class="font-data-label text-on-surface-variant uppercase text-[10px]">Total Impressions</p>
                                    <p class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo number_format($seoSummary['impressions']); ?></p>
                                </div>
                            </div>

                            <!-- CTR Card -->
                            <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl flex items-center gap-md shadow-xs">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
                                    <span class="material-symbols-outlined">percent</span>
                                </div>
                                <div>
                                    <p class="font-data-label text-on-surface-variant uppercase text-[10px]">Average CTR</p>
                                    <p class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo number_format($seoSummary['ctr'], 2); ?>%</p>
                                </div>
                            </div>

                            <!-- Avg Position Card -->
                            <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl flex items-center gap-md shadow-xs">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
                                    <span class="material-symbols-outlined">trending_up</span>
                                </div>
                                <div>
                                    <p class="font-data-label text-on-surface-variant uppercase text-[10px]">Average Position</p>
                                    <p class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo number_format($seoSummary['position'], 1); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Column 2: Recent Activity / Recent Posts (Right, 6/12 width) -->
                <div class="col-span-12 lg:col-span-6 bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden p-lg flex flex-col justify-between">
                    <div class="border-b border-surface-variant pb-sm mb-md flex justify-between items-center">
                        <div>
                            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Recent Posts</h3>
                            <p class="text-on-surface-variant text-[11px] mt-xs">Recently Posted...</p>
                        </div>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="text-primary font-body-sm font-bold hover:underline cursor-pointer flex items-center gap-xs">
                            <span>View History</span>
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </a>
                    </div>

                    <div class="overflow-x-auto flex-grow">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-surface-variant text-[10px] text-on-surface-variant uppercase tracking-wider font-bold">
                                    <th class="py-sm px-xs text-left">Platform</th>
                                    <th class="py-sm px-xs text-left">Title</th>
                                    <th class="py-sm px-xs text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody id="recent-activity-tbody" class="divide-y divide-surface-variant/30 text-xs">
                                <tr>
                                    <td colspan="3" class="py-xl text-center">
                                        <span class="inline-block w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function reloadDashboardData(force = false) {
        const platform = "";
        
        // Default to last 30 days
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        const startDate = thirtyDaysAgo.toISOString().split('T')[0];
        const endDate = today.toISOString().split('T')[0];

        // 1. Show spinners
        const spinner = '<span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>';
        document.getElementById('stat-connections').innerHTML = spinner;
        document.getElementById('stat-total-posts').innerHTML = spinner;
        document.getElementById('stat-published-posts').innerHTML = spinner;
        document.getElementById('stat-scheduled-posts').innerHTML = spinner;
        
        // 2. Fetch statistics counts
        const params = {
            platform: platform,
            start_date: startDate,
            end_date: endDate
        };
        if (force) {
            params.force_sync = 1;
        }
        const queryParams = new URLSearchParams(params).toString();

        const p1 = fetch(`<?php echo DASHBOARD_BASE_URL; ?>/pages/ajax_stats.php?${queryParams}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const activeCountText = ' Active';
                    document.getElementById('stat-connections').innerHTML = 
                        data.connections_count + ` <span class="text-body-sm font-normal text-on-surface-variant">${activeCountText}</span>`;
                    document.getElementById('stat-total-posts').textContent = data.total_posts;
                    document.getElementById('stat-published-posts').textContent = data.published_posts;
                    document.getElementById('stat-scheduled-posts').textContent = data.scheduled_posts;
                    if (data.last_synced_str) {
                        const lbl = document.getElementById('last-synced-label');
                        if (lbl) lbl.textContent = 'Last synced: ' + data.last_synced_str;
                    }
                } else {
                    console.error("Failed to load stats:", data.error);
                }
            })
            .catch(err => console.error("Error loading stats:", err));

        // 3. Fetch recent activity ledger
        const p3 = fetch(`<?php echo DASHBOARD_BASE_URL; ?>/pages/ajax_recent_posts.php`)
            .then(res => res.text())
            .then(html => {
                document.getElementById('recent-activity-tbody').innerHTML = html;
            })
            .catch(err => console.error("Error loading recent posts:", err));

        return Promise.all([p1, p3]);
    }

    function triggerDashboardSync(btn) {
        const icon = document.getElementById('refresh-icon');
        const label = document.getElementById('refresh-label');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('animate-spin');
        if (label) label.textContent = 'Syncing...';

        reloadDashboardData(true).finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('animate-spin');
            if (label) label.textContent = 'Refresh Data';
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        reloadDashboardData();
    });
    </script>
</body>
</html>
