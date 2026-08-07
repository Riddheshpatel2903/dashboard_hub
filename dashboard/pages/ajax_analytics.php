<?php
/**
 * AJAX endpoint for loading dashboard analytics widgets (Stitch Social Mission Control Design).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

// Check and run synchronization if needed - sync_analytics is removed, so we don't call it.

header('Content-Type: text/html; charset=utf-8');

if ($client_id === null) {
    echo '<p class="text-error text-center font-bold">No client selected.</p>';
    exit();
}

// Get connected platforms to validate/default
$connectedPlatforms = [];
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected' && $conn['platform'] !== 'whatsapp') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}

// Fetch Google Search Console connection locally and add it to platforms
$stmtSeo = $pdo->prepare("SELECT site_url FROM seo_connections WHERE client_id = :client_id AND status = 'connected' LIMIT 1");
$stmtSeo->execute(['client_id' => $client_id]);
$seoSiteUrl = $stmtSeo->fetchColumn();
if ($seoSiteUrl) {
    $connectedPlatforms[] = 'google_search_console';
}

if (empty($connectedPlatforms)) {
    echo '<div class="p-lg text-center space-y-sm text-on-surface-variant w-full">
        <span class="material-symbols-outlined text-4xl text-outline-variant">analytics</span>
        <p class="font-bold text-sm">No connected channels found.</p>
        <p class="text-xs">Link your accounts in Connections to track performance analytics.</p>
    </div>';
    exit();
}

$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$activePlatform = !empty($platform) ? $platform : $connectedPlatforms[0];

$metrics = [];
$errorMsg = null;
$seoData = null;

if ($activePlatform === 'search_console') {
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
    $avgCtr = ($sumImpressions > 0) ? round(($sumClicks / $sumImpressions) * 100, 2) : 0;
    $avgPos = ($sumImpressions > 0) ? round($weightedPosSum / $sumImpressions, 1) : 0.0;

    $seoData = [
        'summary' => [
            'clicks' => $sumClicks,
            'impressions' => $sumImpressions,
            'ctr' => $avgCtr,
            'position' => $avgPos
        ],
        'rows' => $rows
    ];
    
    $metrics = [
        ['platform' => 'search_console', 'metric_name' => 'total_clicks',      'value' => $seoData['summary']['clicks'], 'period' => 'range'],
        ['platform' => 'search_console', 'metric_name' => 'total_impressions', 'value' => $seoData['summary']['impressions'], 'period' => 'range'],
        ['platform' => 'search_console', 'metric_name' => 'avg_ctr',           'value' => $seoData['summary']['ctr'] . '%', 'period' => 'range'],
        ['platform' => 'search_console', 'metric_name' => 'avg_position',      'value' => $seoData['summary']['position'], 'period' => 'range']
    ];
} else {
    $analyticsRes = hubGetAnalytics($client_id, $activePlatform, 0, $startDate, $endDate);
    if (!empty($analyticsRes['success']) && is_array($analyticsRes['metrics'])) {
        $metrics = $analyticsRes['metrics'];
    } else {
        $errorMsg = $analyticsRes['error'] ?? 'Unable to retrieve analytics from Hub proxy.';
    }
}

// Compute dynamic chart date range and metric totals
$chartViews = 0;
$chartMetricName = 'Views / Reach';
foreach ($metrics as $m) {
    $mName = strtolower($m['metric_name']);
    $val = is_numeric($m['value']) ? (float)$m['value'] : 0;
    if (in_array($mName, ['view_count', 'views', 'reach', 'impressions', 'page_views_total', 'page_media_view', 'post_media_view', 'post_total_media_view_unique', 'views_search', 'subscriber_count', 'total_clicks'])) {
        $chartViews += $val;
        $chartMetricName = ucwords(str_replace('_', ' ', $m['metric_name']));
    }
}

$chartStartTs = strtotime($startDate);
$chartEndTs = strtotime($endDate);
$chartStep = max(1, ($chartEndTs - $chartStartTs) / 5);
$chartDateLabels = [];
for ($i = 0; $i < 6; $i++) {
    $chartDateLabels[] = strtoupper(date('M d', (int)($chartStartTs + ($i * $chartStep))));
}

$chartTooltipDate = date('M d, Y', $chartEndTs);
$chartTooltipValue = $chartMetricName . ': ' . (is_numeric($chartViews) && $chartViews > 0 ? number_format($chartViews) : '0');
?>

<?php if ($errorMsg): ?>
    <div class="bg-error-container text-on-error-container p-md rounded-xl border border-error/20 text-center text-xs font-bold w-full">
        ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter w-full">
        <!-- Performance Area Chart (Exact Stitch Curve & Vertical Gridlines) -->
        <div class="lg:col-span-2 flex flex-col justify-between">
            <div class="flex justify-between items-center mb-xs">
                <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">Performance Chart Trend</h4>
                <span class="text-[10px] font-bold text-primary uppercase font-data-label bg-primary-container/10 px-xs py-0.5 rounded border border-primary/20"><?php echo htmlspecialchars(strtoupper($activePlatform)); ?></span>
            </div>
            
            <?php
            // Fetch live posts
            $allLivePosts = [];
            try {
                $allLivePosts = loadPlatformPosts($client_id);
            } catch (Exception $e) {
                // Gracefully fallback
            }

            // Initialize 6 data points
            $chartValues = array_fill(0, 6, 0);
            $chartImpressions = array_fill(0, 6, 0);

            if ($activePlatform === 'search_console') {
                if ($seoData && !empty($seoData['rows'])) {
                    foreach ($seoData['rows'] as $row) {
                        $dateStr = $row['keys'][0] ?? '';
                        $rowTs = strtotime($dateStr);
                        if ($chartEndTs == $chartStartTs) {
                            $bucketIdx = 0;
                        } else {
                            $bucketIdx = (int)round(($rowTs - $chartStartTs) / $chartStep);
                            $bucketIdx = max(0, min(5, $bucketIdx));
                        }
                        $chartValues[$bucketIdx] += (int)($row['clicks'] ?? 0);
                        $chartImpressions[$bucketIdx] += (int)($row['impressions'] ?? 0);
                    }
                }
            } else {
                // Filter posts in PHP to build trend
                $postsTrend = [];
                foreach ($allLivePosts as $p) {
                    if ($p['status'] !== 'published') {
                        continue;
                    }
                    if (!empty($activePlatform) && $p['platform'] !== $activePlatform) {
                        continue;
                    }
                    $pubDate = date('Y-m-d', strtotime($p['published_at']));
                    if ($pubDate < $startDate || $pubDate > $endDate) {
                        continue;
                    }
                    $postsTrend[] = [
                        'published_at' => $p['published_at'],
                        'views_count' => $p['views_count']
                    ];
                }

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
                    // Fall back to post counts so the graph has activity if views are not loaded/present
                    $chartValues = $chartPostCounts;
                }
            }
            ?>
            <div class="relative h-64 w-full rounded-xl border border-surface-variant/50 overflow-hidden bg-surface-container-lowest shadow-xs p-2">
                <canvas id="ajaxDashTrendChart"></canvas>
            </div>
            <script>
            (function() {
                const canvas = document.getElementById('ajaxDashTrendChart');
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 240);
                gradient.addColorStop(0, 'rgba(0, 122, 135, 0.22)');
                gradient.addColorStop(1, 'rgba(0, 122, 135, 0.0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chartDateLabels); ?>,
                        datasets: <?php if ($activePlatform === 'google_search_console'): ?>
                        [
                            {
                                label: 'Total Clicks',
                                data: <?php echo json_encode($chartValues); ?>,
                                borderColor: '#4285F4',
                                borderWidth: 3,
                                backgroundColor: 'rgba(66, 133, 244, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#4285F4',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            },
                            {
                                label: 'Total Impressions',
                                data: <?php echo json_encode($chartImpressions); ?>,
                                borderColor: '#7C3AED',
                                borderWidth: 3,
                                backgroundColor: 'rgba(124, 58, 237, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#7C3AED',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }
                        ]
                        <?php else: ?>
                        [{
                            label: 'Reach / Views',
                            data: <?php echo json_encode($chartValues); ?>,
                            borderColor: '#007a87',
                            borderWidth: 3,
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#007a87',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 7
                        }]
                        <?php endif; ?>
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1E293B',
                                padding: 10,
                                cornerRadius: 8,
                                titleFont: { size: 12, weight: 'bold' },
                                bodyFont: { size: 11 },
                                displayColors: false
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#64748B', font: { size: 10, weight: '600' } }
                            },
                            y: {
                                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                                ticks: { color: '#64748B', font: { size: 10, weight: '600' }, beginAtZero: true }
                            }
                        }
                    }
                });
            })();
            </script>
        </div>

        <!-- Metric Cards -->
        <div class="space-y-xs">
            <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider mb-xs">Normalized Dimensions</h4>
            <div class="grid grid-cols-1 gap-xs max-h-[280px] overflow-y-auto pr-xs">
                <?php foreach ($metrics as $m): ?>
                    <div class="bg-surface-container-low border border-surface-variant p-sm rounded-lg flex justify-between items-center shadow-xs">
                        <div class="min-w-0">
                            <div class="font-bold text-on-surface text-xs capitalize truncate">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', preg_replace('/(?<!^)[A-Z]/', '_$0', $m['metric_name'])))); ?>
                            </div>
                            <div class="text-[9px] font-data-label text-on-surface-variant uppercase mt-xs">
                                Period: <?php echo htmlspecialchars($m['period'] ?? 'n/a'); ?>
                            </div>
                        </div>
                        <div class="text-lg text-primary font-bold shrink-0 pl-sm">
                            <?php 
                                if (is_numeric($m['value'])) {
                                    echo number_format((int)$m['value']);
                                } else {
                                    echo '<span class="text-[10px] font-normal text-on-surface-variant">' . htmlspecialchars(mb_strimwidth($m['value'], 0, 30, '...')) . '</span>';
                                }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

