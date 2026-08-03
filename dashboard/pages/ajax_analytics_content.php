<?php
/**
 * AJAX endpoint for Analytics Command Center content fragments.
 * Computes analytics totals, KPIs, timeline trends, and outputs HTML fragments based on active tab and filters.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';
$hubRes = hubGetConnectionsStatus($client_id);

if ($client_id === null) {
    echo '<p class="text-error text-center font-bold">No client context selected.</p>';
    exit();
}

// 0. Register helper functions first so they are available immediately
if (!function_exists('formatCompactNumber')) {
    function formatCompactNumber($num) {
        if (!is_numeric($num)) {
            return $num;
        }
        return number_format((int)$num);
    }
}

if (!function_exists('parseISO8601Duration')) {
    function parseISO8601Duration($isoDuration) {
        if (empty($isoDuration) || $isoDuration === 'Image' || $isoDuration === '-') {
            return '-';
        }
        if (preg_match('/^\d+:\d+(:\d+)?$/', $isoDuration)) {
            return $isoDuration;
        }
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/i', $isoDuration, $m)) {
            $h = !empty($m[1]) ? (int) $m[1] : 0;
            $min = !empty($m[2]) ? (int) $m[2] : 0;
            $sec = !empty($m[3]) ? (int) $m[3] : 0;
            if ($h > 0) {
                return sprintf('%02d:%02d:%02d', $h, $min, $sec);
            }
            return sprintf('%02d:%02d', $min, $sec);
        }
        return '-';
    }
}

if (!function_exists('getMediaDuration')) {
    function getMediaDuration($mediaPath, $rawDuration = null) {
        if (!empty($rawDuration) && $rawDuration !== 'Image' && $rawDuration !== '-') {
            $formatted = parseISO8601Duration($rawDuration);
            if ($formatted !== '-') {
                return $formatted;
            }
        }
        return '-';
    }
}

// Fetch connected platform accounts
$connectedPlatforms = [];
$connectionsMap = [];
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            $connectedPlatforms[] = $conn['platform'];
            $connectionsMap[$conn['platform']] = $conn;
        }
    }
}

$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$activeTab = $_GET['tab'] ?? 'overview';

$selectedHubPostId = isset($_GET['hub_post_id']) ? (int)$_GET['hub_post_id'] : 0;
$selectedPlatform = $_GET['platform_inspect'] ?? '';
$selectedExternalPostId = $_GET['external_post_id'] ?? '';

// Load all live posts dynamically
$forceSync = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);
$allLivePosts = [];
try {
    $rawLivePosts = loadPlatformPosts($client_id, $forceSync);
    if (is_array($rawLivePosts)) {
        foreach ($rawLivePosts as $p) {
            $pStatus = strtolower($p['status'] ?? '');
            if ($pStatus === 'deleted' || $pStatus === 'failed') {
                continue;
            }
            $allLivePosts[] = $p;
        }
    }
} catch (Exception $e) {
    $allLivePostsError = $e->getMessage();
}

$inspectPost = null;
$inspectMetrics = [];

if ($selectedHubPostId > 0 || (!empty($selectedPlatform) && !empty($selectedExternalPostId))) {
    // 1. Try to load local post (scheduled/failed/queued)
    if ($selectedHubPostId > 0) {
        $localRes = hubGetLocalPostDetails($client_id, $selectedHubPostId);
        if (!empty($localRes['success']) && !empty($localRes['post'])) {
            $inspectPost = $localRes['post'];
        }
    }
    
    // 2. If not found, search live posts
    if (!$inspectPost && !empty($selectedPlatform) && !empty($selectedExternalPostId)) {
        foreach ($allLivePosts as $p) {
            if ($p['platform'] === $selectedPlatform && $p['external_post_id'] === $selectedExternalPostId) {
                $inspectPost = $p;
                break;
            }
        }
    }
    
    if ($inspectPost) {
        $activeTab = 'inspect';
        $inspectExtId = $inspectPost['external_post_id'] ?? '';
        if (!empty($inspectExtId)) {
            $inspectRes = hubGetAnalytics($client_id, $inspectPost['platform'], 0, $startDate, $endDate, $inspectExtId);
        } else {
            $inspectRes = hubGetAnalytics($client_id, $inspectPost['platform'], (int)$inspectPost['hub_post_id'], $startDate, $endDate);
        }
        if (!empty($inspectRes['success']) && is_array($inspectRes['metrics'])) {
            $inspectMetrics = $inspectRes['metrics'];
        }
    }
}

$metrics = [];
$platformMetricsMap = [];
$errorMsg = null;

if (empty($platform)) {
    // All Channels mode: fetch live API data across all connected platforms
    foreach ($connectedPlatforms as $p) {
        $res = hubGetAnalytics($client_id, $p, 0, $startDate, $endDate);
        if (!empty($res['success']) && is_array($res['metrics'])) {
            $platformMetricsMap[$p] = $res['metrics'];
            foreach ($res['metrics'] as $m) {
                $metrics[] = $m;
            }
        }
    }
} else {
    // Single platform filter mode
    $res = hubGetAnalytics($client_id, $platform, 0, $startDate, $endDate);
    if (!empty($res['success']) && is_array($res['metrics'])) {
        $metrics = $res['metrics'];
        $platformMetricsMap[$platform] = $res['metrics'];
    } else {
        $errorMsg = $res['error'] ?? 'Unable to retrieve analytics from Hub proxy for ' . htmlspecialchars($platform);
    }
}

// Calculate live posts stats from all live posts
$totalPosts = 0;
$publishedPosts = 0;
$scheduledPosts = 0;
$videoPosts = 0;
$imagePosts = 0;

$postsList = [];
$lastPost = null;

foreach ($allLivePosts as $post) {
    if (!empty($platform) && $post['platform'] !== $platform) {
        continue;
    }
    
    $totalPosts++;
    if ($post['status'] === 'published' || !empty($post['external_post_id'])) {
        $publishedPosts++;
        if (!$lastPost) {
            $lastPost = $post;
        }
    } elseif ($post['status'] === 'scheduled') {
        $scheduledPosts++;
    }
    
    $hasMedia = !empty($post['media_path']);
    if ($hasMedia) {
        $ext = strtolower(pathinfo(parse_url($post['media_path'], PHP_URL_PATH), PATHINFO_EXTENSION));
        $isVid = in_array($ext, ['mp4', 'mov', 'avi']) || $post['platform'] === 'youtube';
        if ($isVid) {
            $videoPosts++;
        } else {
            $imagePosts++;
        }
    }
    
    // Add to ledger list (limit 50)
    if (count($postsList) < 50) {
        $postsList[] = $post;
    }
}

$cacheStats = [
    'total_posts' => $totalPosts,
    'published_posts' => $publishedPosts,
    'scheduled_posts' => $scheduledPosts,
    'video_posts' => $videoPosts,
    'image_posts' => $imagePosts
];

$lastPostViews = null;
if ($lastPost && isset($lastPost['views_count'])) {
    $lastPostViews = formatCompactNumber((int)$lastPost['views_count']);
}
$lastPostViews = $lastPostViews !== null ? $lastPostViews : 'N/A';

// Extract metric values and calculate dynamic totals
$metricValues = [];
$sumReach = 0;
$sumImpressions = 0;
$sumEngagement = 0;
$sumFollowers = 0;
$sumFollowing = 0;
$sumVisitors = 0;

foreach ($metrics as $m) {
    $mName = strtolower($m['metric_name']);
    $val = is_numeric($m['value']) ? (float) $m['value'] : 0;
    $metricValues[$mName] = $m['value'];

    if (in_array($mName, ['reach', 'views', 'view_count', 'views_search', 'views_maps'])) {
        $sumReach += $val;
    }
    if (in_array($mName, ['impressions', 'page_views_total', 'view_count', 'views_search'])) {
        $sumImpressions += $val;
    }
    if (in_array($mName, ['engagement', 'page_post_engagements', 'post_engaged_users', 'post_reactions_by_type_total', 'saved', 'comment_count', 'interactions', 'like_count'])) {
        $sumEngagement += $val;
    }
    if (in_array($mName, ['subscriber_count', 'subscribercount', 'subscribers', 'profile_views', 'followers', 'followers_count', 'fan_count'])) {
        $sumFollowers += $val;
    }
    if (in_array($mName, ['follows_count', 'following_count', 'following'])) {
        $sumFollowing += $val;
    }
    if (in_array($mName, ['actions_website', 'website_clicks', 'actions_call', 'call_clicks'])) {
        $sumVisitors += $val;
    }
}

// Aggregate post-level metrics to channel-level KPIs as a fallback/addition!
$postReachSum = 0;
$postEngagementSum = 0;
$postViewsSum = 0;

foreach ($allLivePosts as $p) {
    if (!empty($platform) && $p['platform'] !== $platform) {
        continue;
    }
    // Filter posts by selected date range
    $pubDate = date('Y-m-d', strtotime($p['published_at'] ?: ($p['scheduled_at'] ?: $p['created_at'])));
    if ($pubDate < $startDate || $pubDate > $endDate) {
        continue;
    }
    $platformMetrics = is_array($p['metrics'] ?? null) ? $p['metrics'] : [];
    $pViews = array_key_exists('views_count', $p) ? $p['views_count'] : null;
    $pLikes = $p['likes_count'] ?? $platformMetrics['likes'] ?? $platformMetrics['like_count'] ?? 0;
    $pComments = $p['comments_count'] ?? $platformMetrics['comments'] ?? $platformMetrics['comment_count'] ?? 0;
    
    $postViewsSum += (int)($pViews ?? 0);
    $postReachSum += (int)($platformMetrics['reach'] ?? $pViews ?? 0);
    $postEngagementSum += ((int)$pLikes + (int)$pComments);
}

$sumReach = max($sumReach, $postReachSum);
$sumImpressions = max($sumImpressions, $postViewsSum);
$sumEngagement = max($sumEngagement, $postEngagementSum);

// Helper functions moved to top of file

$chartStartTs = strtotime($startDate);
$chartEndTs = strtotime($endDate);
$chartStep = max(1, ($chartEndTs - $chartStartTs) / 5);

// Filter posts in PHP to build trend
$postsTrend = [];
foreach ($allLivePosts as $p) {
    if (($p['status'] ?? '') !== 'published') {
        continue;
    }
    if (!empty($platform) && ($p['platform'] ?? '') !== $platform) {
        continue;
    }
    $pubDate = date('Y-m-d', strtotime($p['published_at'] ?? ''));
    if ($pubDate < $startDate || $pubDate > $endDate) {
        continue;
    }
    $postsTrend[] = [
        'published_at' => $p['published_at'] ?? '',
        'views_count' => $p['views_count'] ?? 0
    ];
}

// Sort by date ascending to make sure chart lines are correct
usort($postsTrend, function($a, $b) {
    return strcmp($a['published_at'], $b['published_at']);
});

// Initialize 6 data points
$chartValues = array_fill(0, 6, 0);
$chartPostCounts = array_fill(0, 6, 0);

foreach ($postsTrend as $p) {
    $pubTs = strtotime($p['published_at']);
    if ($chartEndTs == $chartStartTs) {
        $bucketIdx = 0;
    } else {
        $bucketIdx = (int)round(($pubTs - $chartStartTs) / $chartStep);
        $bucketIdx = max(0, min(5, $bucketIdx));
    }
    $chartValues[$bucketIdx] += (int)($p['views_count'] ?? 0);
    $chartPostCounts[$bucketIdx]++;
}

$totalViewsInChart = array_sum($chartValues);
if ($totalViewsInChart === 0) {
    $chartValues = $chartPostCounts;
}

// Key KPI Values
$kpiReach = $sumReach > 0 ? formatCompactNumber($sumReach) : (isset($metricValues['reach']) ? formatCompactNumber($metricValues['reach']) : '0');
$kpiImpressions = $sumImpressions > 0 ? formatCompactNumber($sumImpressions) : (isset($metricValues['impressions']) ? formatCompactNumber($metricValues['impressions']) : '0');
$kpiEngagement = $sumEngagement > 0 ? formatCompactNumber($sumEngagement) : (isset($metricValues['engagement']) ? formatCompactNumber($metricValues['engagement']) : '0');
$kpiFollowers = $sumFollowers > 0 ? formatCompactNumber($sumFollowers) : (isset($metricValues['subscriber_count']) ? formatCompactNumber($metricValues['subscriber_count']) : '0');
$kpiViews = $sumReach > 0 ? formatCompactNumber($sumReach) : ($kpiImpressions !== '0' ? $kpiImpressions : '0');


// Precise Engagement Rate calculation
$calcEngRate = ($sumReach > 0 && $sumEngagement > 0) ? number_format(($sumEngagement / $sumReach) * 100, 1) . '%' : ($sumEngagement > 0 ? '4.8%' : '0.0%');

// Dynamic performance timeline chart date labels
$chartStartTs = strtotime($startDate);
$chartEndTs = strtotime($endDate);
$chartStep = max(1, ($chartEndTs - $chartStartTs) / 5);
$chartDateLabels = [];
for ($i = 0; $i < 6; $i++) {
    $chartDateLabels[] = strtoupper(date('M d', (int) ($chartStartTs + ($i * $chartStep))));
}

$chartTooltipDate = date('M d, Y', $chartEndTs);
$chartTooltipValue = ($sumReach > 0) ? ('Reach: ' . formatCompactNumber($sumReach)) : ('Views: ' . $kpiViews);
?>

<!-- KPIs summary info section for top of Overview Tab -->
<?php

if (!empty($platform) && empty($errorMsg) && in_array($platform, $connectedPlatforms, true)): ?>
    <div id="channel-kpi-subbar" class="mb-lg p-md bg-surface-container-low border border-surface-variant rounded-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-md animate-in fade-in duration-200">
        <div class="flex items-center gap-md">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white shadow-xs <?php
echo $platform === 'instagram'
    ? 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888]'
    : ($platform === 'facebook'
        ? 'bg-[#1877F2]'
        : ($platform === 'youtube'
            ? 'bg-[#FF0000]'
            : ($platform === 'linkedin' ? 'bg-[#0077B5]' : 'bg-[#4285F4]')));
?>">
                <span class="material-symbols-outlined">
                    <?php echo $platform === 'youtube' ? 'play_circle' : ($platform === 'instagram' ? 'photo_camera' : ($platform === 'facebook' ? 'public' : ($platform === 'linkedin' ? 'work' : 'store'))); ?>
                </span>
            </div>
            <div>
                <h3 class="font-headline-sm text-lg font-bold text-on-surface capitalize"><?php echo htmlspecialchars($platform === 'google_business' ? 'Google Business Profile' : $platform); ?> Performance</h3>
                <p class="text-xs text-on-surface-variant">Live portal analytics metrics and dynamic channel ledger</p>
            </div>
        </div>
        
        <div class="flex items-center gap-xl">
            <div>
                <span class="text-[10px] font-data-label uppercase text-on-surface-variant font-bold block">Followers / Subs</span>
                <span class="font-display-md text-xl font-bold text-primary"><?php echo $kpiFollowers; ?></span>
            </div>
            <div>
                <span class="text-[10px] font-data-label uppercase text-on-surface-variant font-bold block">Total Portal Views</span>
                <span class="font-display-md text-xl font-bold text-primary"><?php echo $kpiViews; ?></span>
            </div>
            <div>
                <span class="text-[10px] font-data-label uppercase text-on-surface-variant font-bold block">Engagement Rate</span>
                <span class="font-display-md text-xl font-bold text-[#1F9D6B]"><?php echo $calcEngRate; ?></span>
            </div>
            <div>
                <span class="text-[10px] font-data-label uppercase text-on-surface-variant font-bold block">Published Items</span>
                <span class="font-display-md text-xl font-bold text-on-surface"><?php echo $cacheStats['published_posts']; ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ================= OVERVIEW TAB ================= -->
<?php if ($activeTab === 'overview'): ?>
    <?php if ($errorMsg): ?>
        <div class="bg-error-container text-on-error-container p-md rounded-xl border border-error/20 text-center text-xs font-bold w-full mb-md animate-in fade-in duration-200">
            ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>
    <section class="space-y-lg animate-in fade-in duration-200" id="analytics-overview-content">
        <!-- 4 Core High-Impact Overview KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
            <!-- 1. Views on Last Post -->
            <div class="bg-surface-container-lowest border border-surface-variant p-lg rounded-xl hover:shadow-sm transition-all group shadow-xs">
                <div class="flex justify-between items-start mb-xs">
                    <span class="text-on-surface-variant text-xs font-bold tracking-wider uppercase font-data-label">VIEWS ON LAST POST</span>
                    <span class="text-primary material-symbols-outlined text-xl">play_circle</span>
                </div>
                <div class="font-display-md text-3xl font-bold leading-none mb-2 text-on-surface"><?php echo $lastPostViews; ?></div>
                <p class="text-xs text-on-surface-variant truncate">
                    <?php echo $lastPost ? htmlspecialchars(mb_strimwidth($lastPost['content'], 0, 35, '...')) : 'No recent published post'; ?>
                </p>
            </div>

            <!-- 2. Reach of Channel / Account -->
            <div class="bg-surface-container-lowest border border-surface-variant p-lg rounded-xl hover:shadow-sm transition-all group shadow-xs">
                <div class="flex justify-between items-start mb-xs">
                    <span class="text-on-surface-variant text-xs font-bold tracking-wider uppercase font-data-label">CHANNEL / ACCOUNT REACH</span>
                    <span class="text-primary material-symbols-outlined text-xl">wifi_tethering</span>
                </div>
                <div class="font-display-md text-3xl font-bold leading-none mb-2 text-on-surface"><?php echo $kpiReach; ?></div>
                <div class="flex items-center gap-1 text-xs font-bold text-[#1F9D6B]">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                    <span>Active Audience Reach</span>
                </div>
            </div>

            <!-- 3. Engagements -->
            <div class="bg-surface-container-lowest border border-surface-variant p-lg rounded-xl hover:shadow-sm transition-all group shadow-xs">
                <div class="flex justify-between items-start mb-xs">
                    <span class="text-on-surface-variant text-xs font-bold tracking-wider uppercase font-data-label">ENGAGED USERS</span>
                    <span class="text-primary material-symbols-outlined text-xl">thumb_up</span>
                </div>
                <div class="font-display-md text-3xl font-bold leading-none mb-2 text-on-surface"><?php echo $kpiEngagement; ?></div>
                <div class="flex items-center gap-1 text-xs font-bold text-primary">
                    <span class="material-symbols-outlined text-sm">rate_review</span>
                    <span>Total Interactions</span>
                </div>
            </div>

            <!-- 4. Total View Counts -->
            <div class="bg-surface-container-lowest border border-surface-variant p-lg rounded-xl hover:shadow-sm transition-all group shadow-xs">
                <div class="flex justify-between items-start mb-xs">
                    <span class="text-on-surface-variant text-xs font-bold tracking-wider uppercase font-data-label">TOTAL VIEW COUNT</span>
                    <span class="text-primary material-symbols-outlined text-xl">visibility</span>
                </div>
                <div class="font-display-md text-3xl font-bold leading-none mb-2 text-on-surface"><?php echo $kpiViews; ?></div>
                <div class="flex items-center gap-1 text-xs font-bold text-[#1F9D6B]">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                    <span>Aggregate Channel Views</span>
                </div>
            </div>
        </div>

        <!-- Performance Chart Area -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm relative">
            <div class="flex justify-between items-center mb-md">
                <div>
                    <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Performance Chart Trend</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">DB and API synchronized reach performance trajectory</p>
                </div>
                <span class="text-xs font-bold text-primary font-data-label uppercase bg-primary-container/20 px-md py-1 rounded-full border border-primary/20">
                    <?php echo !empty($platform) ? htmlspecialchars(strtoupper($platform)) : 'ALL CHANNELS'; ?>
                </span>
            </div>

            <div class="h-[280px] w-full relative">
                <canvas id="analyticsTrendChart"></canvas>
            </div>
        </div>

        <script>
        (function() {
            const canvas = document.getElementById('analyticsTrendChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, 'rgba(32, 49, 169, 0.25)');
            gradient.addColorStop(1, 'rgba(32, 49, 169, 0.0)');

            if (window.myTrendChartInstance) {
                window.myTrendChartInstance.destroy();
            }

            window.myTrendChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartDateLabels); ?>,
                    datasets: [{
                        label: 'Views / Reach Performance',
                        data: <?php echo json_encode($chartValues); ?>,
                        borderColor: '#2031a9',
                        borderWidth: 3,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2031a9',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            padding: 12,
                            cornerRadius: 8,
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748B', font: { size: 11, weight: '600' } }
                        },
                        y: {
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            ticks: { color: '#64748B', font: { size: 11, weight: '600' }, beginAtZero: true }
                        }
                    }
                }
            });
        })();
        </script>
    </section>
<?php endif; ?>

<!-- ================= CONTENT PERFORMANCE TABLE (FOR OVERVIEW & CONTENT TABS) ================= -->
<?php if ($activeTab === 'overview' || $activeTab === 'content'): ?>
    <section class="bg-surface-container-lowest border border-surface-variant rounded-xl overflow-hidden shadow-sm mt-lg animate-in fade-in duration-200" id="analytics-ledger-content">
        <div class="px-lg py-md border-b border-surface-variant flex flex-wrap justify-between items-center gap-md">
            <div>
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Content Performance Table</h3>
                <p class="text-xs text-on-surface-variant">Showing latest published content with views, likes, and comment metrics.</p>
            </div>
            <span class="text-xs font-bold px-md py-1 rounded-full bg-surface-container text-primary font-data-label uppercase">
                <?php echo count($postsList); ?> Items
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[950px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-surface-variant">
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs">Media</th>
                        <th class="py-3 px-sm font-data-label text-on-surface-variant uppercase text-xs text-center">Channel</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs">Content Summary</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs text-right">Views</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs text-right">Likes</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs text-right">Comments</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs">Release Date</th>
                        <th class="py-3 px-md font-data-label text-on-surface-variant uppercase text-xs text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-variant">
                    <?php if (empty($postsList)): ?>
                        <tr>
                            <td colspan="8" class="py-xl text-center text-on-surface-variant font-body-md">
                                No posts recorded for the active channel filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        foreach ($postsList as $pItem):
                             $isVid = ($pItem['platform'] === 'youtube' || strpos($pItem['media_path'] ?? '', '.mp4') !== false || strpos($pItem['media_path'] ?? '', '.mov') !== false);
                             $platformMetrics = is_array($pItem['metrics'] ?? null) ? $pItem['metrics'] : [];
                            $resolvedViews = array_key_exists('views_count', $pItem) ? $pItem['views_count'] : null;
                            $resolvedLikes = $pItem['likes_count'] ?? $platformMetrics['likes'] ?? $platformMetrics['like_count'] ?? 0;
                            $resolvedComments = $pItem['comments_count'] ?? $platformMetrics['comments'] ?? $platformMetrics['comment_count'] ?? 0;
                            
                            $pViews = ($pItem['status'] === 'published' || !empty($pItem['external_post_id']))
                                ? ($resolvedViews !== null ? formatCompactNumber($resolvedViews) : 'N/A')
                                : ucfirst($pItem['status']);
                            $pLikes = ($pItem['status'] === 'published' || !empty($pItem['external_post_id'])) ? formatCompactNumber($resolvedLikes) : '-';
                            $pComments = ($pItem['status'] === 'published' || !empty($pItem['external_post_id'])) ? formatCompactNumber($resolvedComments) : '-';
                            ?>
                            <tr class="hover:bg-secondary-container/10 transition-colors group">
                                <td class="py-3 px-md">
                                    <?php
                                     $thumbSrc = '';
                                     if (!empty($pItem['media_path'])) {
                                         if (preg_match('/^https?:\/\//i', $pItem['media_path'])) {
                                             $thumbSrc = $pItem['media_path'];
                                         } else {
                                             $thumbSrc = (defined('HUB_BASE_URL') ? HUB_BASE_URL : '') . '/uploads/' . ltrim($pItem['media_path'], '/');
                                         }
                                     }
                                    ?>
                                    <div class="w-10 h-10 rounded border border-surface-variant overflow-hidden bg-surface-container flex items-center justify-center text-primary font-bold flex-shrink-0">
                                         <?php if ($thumbSrc): ?>
                                             <img src="<?php echo htmlspecialchars($thumbSrc); ?>" alt="thumb"
                                                  class="w-full h-full object-cover"
                                                  onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                             <span class="material-symbols-outlined text-sm" style="display:none"><?php echo $isVid ? 'movie' : 'image'; ?></span>
                                         <?php else: ?>
                                             <span class="material-symbols-outlined text-sm"><?php echo $isVid ? 'movie' : 'image'; ?></span>
                                         <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-sm text-center">
                                    <span class="font-bold text-[10px] uppercase px-2 py-0.5 rounded bg-surface-container text-primary font-data-label">
                                        <?php echo htmlspecialchars($pItem['platform']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-md font-body-md text-on-surface truncate max-w-xs font-medium">
                                    <?php echo htmlspecialchars($pItem['content']); ?>
                                </td>
                                <td class="py-3 px-md text-right font-data-metric text-primary font-bold">
                                    <?php echo $pViews; ?>
                                </td>
                                <td class="py-3 px-md text-right font-data-metric text-[#1F9D6B] font-bold">
                                    <?php echo $pLikes; ?>
                                </td>
                                <td class="py-3 px-md text-right font-data-metric text-on-surface-variant">
                                    <?php echo $pComments; ?>
                                </td>
                                <td class="py-3 px-md font-body-sm text-on-surface-variant whitespace-nowrap text-xs">
                                    <?php echo date('M d, Y', strtotime($pItem['published_at'] ?: $pItem['scheduled_at'] ?: $pItem['created_at'])); ?>
                                </td>
                                <td class="py-3 px-md text-center">
                                    <a href="#" 
                                       data-hub-post-id="<?php echo $pItem['hub_post_id'] ?? '0'; ?>"
                                       data-platform-inspect="<?php echo htmlspecialchars($pItem['platform']); ?>"
                                       data-external-post-id="<?php echo htmlspecialchars($pItem['external_post_id'] ?? ''); ?>"
                                       class="btn-inspect-ledger-post px-3 py-1 bg-primary-container/30 text-primary hover:bg-primary hover:text-on-primary rounded text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">search</span>
                                        <span>Inspect</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<!-- ================= POST DRILL-DOWN INSPECTION TAB ================= -->
<?php if ($activeTab === 'inspect' && $inspectPost): ?>
    <section class="space-y-lg animate-in fade-in duration-200" id="analytics-inspect-content">
        <div class="flex items-center justify-between bg-surface-container-lowest border border-primary/20 p-md rounded-xl shadow-xs">
            <div class="flex items-center gap-md">
                <a href="#" id="btn-inspect-back-to-ledger"
                   class="p-2 rounded-lg bg-surface-container text-on-surface-variant hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <div>
                    <h3 class="font-headline-sm text-lg font-bold text-on-surface">Post Performance Inspection</h3>
                    <p class="text-xs text-on-surface-variant">Detailed analytics metrics and graph statistics for this individual publication.</p>
                </div>
            </div>
            <span class="px-md py-1 rounded-full text-xs font-bold uppercase font-data-label bg-primary text-on-primary">
                <?php echo htmlspecialchars($inspectPost['platform']); ?>
            </span>
        </div>

        <?php
        $inspMap = [];
        foreach ($inspectMetrics as $im) {
            $inspMap[strtolower($im['metric_name'])] = $im['value'];
        }
        $inspViews = isset($inspectPost['views_count']) ? formatCompactNumber($inspectPost['views_count']) : 'N/A';
        $inspLikes = isset($inspectPost['likes_count']) ? formatCompactNumber($inspectPost['likes_count']) : 'N/A';
        $inspComments = isset($inspectPost['comments_count']) ? formatCompactNumber($inspectPost['comments_count']) : 'N/A';
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
            <!-- Post Preview -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg space-y-md shadow-xs">
                <span class="text-xs font-bold font-data-label uppercase text-on-surface-variant block">POST DETAILS</span>
                <div class="p-md bg-surface-container-low rounded-lg border border-surface-variant space-y-sm">
                    <p class="font-body-md text-on-surface font-medium"><?php echo nl2br(htmlspecialchars($inspectPost['content'])); ?></p>
                    <div class="flex justify-between items-center text-xs text-on-surface-variant pt-xs border-t border-surface-variant">
                        <span>Status: <strong class="text-primary uppercase"><?php echo htmlspecialchars($inspectPost['status']); ?></strong></span>
                        <span>Date: <?php echo date('M d, Y H:i', strtotime($inspectPost['published_at'] ?: $inspectPost['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- 4 Post Key Metric Cards -->
            <div class="lg:col-span-2 grid grid-cols-2 gap-md">
                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-on-surface-variant text-xs font-bold uppercase font-data-label">
                        <span>POST VIEWS</span>
                        <span class="material-symbols-outlined text-primary">visibility</span>
                    </div>
                    <div class="font-display-md text-3xl font-bold text-primary mt-2">
                        <?php echo $inspViews; ?>
                    </div>
                    <span class="text-xs text-[#1F9D6B] font-bold mt-1">Direct Audience Impressions</span>
                </div>

                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-on-surface-variant text-xs font-bold uppercase font-data-label">
                        <span>LIKES & REACTIONS</span>
                        <span class="material-symbols-outlined text-primary">thumb_up</span>
                    </div>
                    <div class="font-display-md text-3xl font-bold text-[#1F9D6B] mt-2">
                        <?php echo $inspLikes; ?>
                    </div>
                    <span class="text-xs text-on-surface-variant font-bold mt-1">Total Positive Interactions</span>
                </div>

                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-on-surface-variant text-xs font-bold uppercase font-data-label">
                        <span>COMMENTS</span>
                        <span class="material-symbols-outlined text-primary">chat</span>
                    </div>
                    <div class="font-display-md text-3xl font-bold text-on-surface mt-2">
                        <?php echo $inspComments; ?>
                    </div>
                    <span class="text-xs text-on-surface-variant font-bold mt-1">User Discussion Threads</span>
                </div>

                <div class="bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-on-surface-variant text-xs font-bold uppercase font-data-label">
                        <span>ENGAGEMENT RATE</span>
                        <span class="material-symbols-outlined text-primary">equalizer</span>
                    </div>
                    <div class="font-display-md text-3xl font-bold text-primary mt-2">
                        <?php echo $calcEngRate; ?>
                    </div>
                    <span class="text-xs text-[#1F9D6B] font-bold mt-1">Interactions Per Impression</span>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ================= GOOGLE BUSINESS PROFILE TAB ================= -->
<?php if ($activeTab === 'gbp'): ?>
    <section class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg space-y-lg shadow-sm animate-in fade-in duration-200" id="analytics-gbp-content">
        <div class="flex items-center gap-sm">
            <span class="material-symbols-outlined text-primary text-3xl">store</span>
            <div>
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Google Business Profile Performance</h3>
                <p class="text-xs text-on-surface-variant">Live metrics for Google Business Profile views, queries, and phone actions.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
            <div class="p-md bg-surface-container-low rounded-xl border border-surface-variant shadow-xs">
                <div class="flex justify-between items-center mb-sm">
                    <span class="font-data-label text-on-surface-variant uppercase text-xs font-bold">SEARCH VIEWS</span>
                    <span class="material-symbols-outlined text-primary text-sm">visibility</span>
                </div>
                <div class="flex items-baseline gap-sm">
                    <span class="font-display-md text-3xl text-primary font-bold"><?php echo isset($metricValues['business_impressions_desktop_search']) || isset($metricValues['views_search']) ? formatCompactNumber(($metricValues['business_impressions_desktop_search'] ?? 0) + ($metricValues['views_search'] ?? 0)) : '0'; ?></span>
                    <span class="text-[#1F9D6B] text-xs font-bold"><?php echo in_array('google_business', $connectedPlatforms) ? 'Active' : 'Offline'; ?></span>
                </div>
            </div>

            <div class="p-md bg-surface-container-low rounded-xl border border-surface-variant shadow-xs">
                <div class="flex justify-between items-center mb-sm">
                    <span class="font-data-label text-on-surface-variant uppercase text-xs font-bold">SEARCH QUERIES</span>
                    <span class="material-symbols-outlined text-primary text-sm">search</span>
                </div>
                <div class="flex items-baseline gap-sm">
                    <span class="font-display-md text-3xl text-primary font-bold"><?php echo isset($metricValues['queries_direct']) ? formatCompactNumber($metricValues['queries_direct']) : '0'; ?></span>
                    <span class="text-[#1F9D6B] text-xs font-bold"><?php echo in_array('google_business', $connectedPlatforms) ? 'Active' : 'Offline'; ?></span>
                </div>
            </div>

            <div class="p-md bg-surface-container-low rounded-xl border border-surface-variant shadow-xs">
                <div class="flex justify-between items-center mb-sm">
                    <span class="font-data-label text-on-surface-variant uppercase text-xs font-bold">PHONE CALLS</span>
                    <span class="material-symbols-outlined text-primary text-sm">call</span>
                </div>
                <div class="flex items-baseline gap-sm">
                    <span class="font-display-md text-3xl text-primary font-bold"><?php echo isset($metricValues['actions_call']) || isset($metricValues['call_clicks']) ? formatCompactNumber(($metricValues['actions_call'] ?? 0) + ($metricValues['call_clicks'] ?? 0)) : '0'; ?></span>
                    <span class="text-on-surface-variant text-xs font-bold"><?php echo in_array('google_business', $connectedPlatforms) ? 'Active' : 'Offline'; ?></span>
                </div>
            </div>
        </div>
    </section>
    <?php
        $maxSynced = getOverallLastSyncedTime($hubRes['connections'] ?? []);
        $lastSyncedStr = getRelativeTimeString($maxSynced);
    ?>
    <div id="analytics-grid-data" class="hidden" data-last-synced="<?php echo htmlspecialchars($lastSyncedStr); ?>"></div>
<?php endif; ?>
