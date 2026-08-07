<?php
/**
 * Dedicated Google Search Console (SEO) Performance Analytics page.
 * Thin-client architecture: zero stored credentials, calls Hub API endpoints only.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// 1. Fetch domain connectivity status via Hub connections manager
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['connections_status_' . $client_id]);
$connectionsStatus = hubGetConnectionsStatus($client_id);
$isSeoConnected = false;
$siteUrl = '';
if (!empty($connectionsStatus['connections']) && is_array($connectionsStatus['connections'])) {
    foreach ($connectionsStatus['connections'] as $conn) {
        if ($conn['platform'] === 'search_console' && $conn['status'] === 'connected') {
            $isSeoConnected = true;
            $siteUrl = $conn['external_account_id'];
            break;
        }
    }
}

$preset = $_GET['preset'] ?? '7days';
$today = date('Y-m-d');
if ($preset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($preset === '7days') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = $today;
} elseif ($preset === '3months') {
    $startDate = date('Y-m-d', strtotime('-90 days'));
    $endDate = $today;
} elseif ($preset === '6months') {
    $startDate = date('Y-m-d', strtotime('-180 days'));
    $endDate = $today;
} elseif ($preset === 'custom') {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $endDate = $_GET['end_date'] ?? $today;
} else {
    $preset = '7days';
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = $today;
}

$seoData = [
    'summary' => ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0],
    'rows' => []
];
$errorMsg = null;

if ($isSeoConnected) {
    $res = hubGetSearchAnalytics($client_id, $startDate, $endDate);
    if (!empty($res['success']) && is_array($res['data'])) {
        $rows = $res['data'];
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
        $seoData = [
            'summary' => [
                'clicks' => $sumClicks,
                'impressions' => $sumImpressions,
                'ctr' => ($sumImpressions > 0) ? round(($sumClicks / $sumImpressions) * 100, 2) : 0,
                'position' => ($sumImpressions > 0) ? round($weightedPosSum / $sumImpressions, 1) : 0.0
            ],
            'rows' => $rows
        ];
    } else {
        $errorMsg = $res['error'] ?? 'Search Console property is connected, but returned no analytics data.';
    }
}

// Format date array, click array, impression array for Chart.js
$chartDates = [];
$chartClicks = [];
$chartImps = [];

foreach ($seoData['rows'] as $row) {
    $chartDates[] = date('M d', strtotime($row['keys'][0] ?? ''));
    $chartClicks[] = (int)($row['clicks'] ?? 0);
    $chartImps[] = (int)($row['impressions'] ?? 0);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>SEO Performance | Social Hub</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="max-w-[1440px] mx-auto p-lg space-y-lg">
            
            <!-- Page Header Actions -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface">SEO Analytics</h1>
                    <p class="font-body-md text-on-surface-variant">Organic search performance metrics direct from Google Search Console.</p>
                </div>
                <div class="flex items-center gap-md">
                    <?php if ($isSeoConnected): ?>
                        <span class="px-sm py-1 rounded-full text-[10px] font-bold uppercase tracking-tight bg-green-100 text-green-700 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[12px]">link</span>
                            <span>Linked to <?php echo htmlspecialchars($siteUrl); ?></span>
                        </span>
                        <a href="connections.php" class="px-md h-10 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg font-bold flex items-center gap-xs text-xs transition-all">
                            <span class="material-symbols-outlined text-sm">settings</span>
                            <span>Connections Manager</span>
                        </a>
                    <?php else: ?>
                        <span class="px-sm py-1 rounded-full text-[10px] font-bold uppercase tracking-tight bg-amber-100 text-amber-700 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[12px]">warning</span>
                            <span>Not Connected</span>
                        </span>
                        <a href="connections.php" class="px-lg h-10 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-xs text-xs hover:opacity-90 active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-sm">link</span>
                            <span>Link Search Console</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isSeoConnected || $errorMsg): ?>
                <div class="bg-amber-50/60 border border-amber-200 text-amber-900 p-md rounded-xl flex items-start gap-md">
                    <span class="material-symbols-outlined text-amber-600 mt-0.5">info</span>
                    <div class="space-y-1">
                        <p class="font-body-md font-bold text-xs">Search Console Setup Pending</p>
                        <p class="text-[11px] leading-relaxed text-amber-800">
                            <?php if ($errorMsg): ?>
                                <?php echo htmlspecialchars($errorMsg); ?>
                            <?php else: ?>
                                Link your site property in the Accounts tab to pull Search Console metrics. Ensure the verified property matches your client profile's website URL.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Simplified Date Filter Presets -->
            <div class="flex flex-wrap items-center justify-between gap-md bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm">
                <div class="flex items-center gap-xs bg-surface-container-low p-xs rounded-lg border border-surface-variant/50">
                    <a href="?preset=today" class="px-md py-sm rounded-md font-bold text-xs transition-all <?php echo $preset === 'today' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'; ?>">Today</a>
                    <a href="?preset=7days" class="px-md py-sm rounded-md font-bold text-xs transition-all <?php echo $preset === '7days' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'; ?>">7 Days</a>
                    <a href="?preset=3months" class="px-md py-sm rounded-md font-bold text-xs transition-all <?php echo $preset === '3months' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'; ?>">3 Months</a>
                    <a href="?preset=6months" class="px-md py-sm rounded-md font-bold text-xs transition-all <?php echo $preset === '6months' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'; ?>">6 Months</a>
                    <button onclick="openCustomDateModal()" class="px-md py-sm rounded-md font-bold text-xs transition-all <?php echo $preset === 'custom' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'; ?>">Custom Range</button>
                </div>

                <div class="text-xs font-bold text-on-surface-variant flex items-center gap-xs">
                    <span class="material-symbols-outlined text-sm text-outline-variant">calendar_today</span>
                    <span>Range: <?php echo date('M d, Y', strtotime($startDate)); ?> – <?php echo date('M d, Y', strtotime($endDate)); ?></span>
                </div>
            </div>

            <!-- SEO Stats Cards Row (GSC exact replica) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">
                <!-- Clicks -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-[#4285F4] transition-colors shadow-sm">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-[11px] uppercase tracking-wider">Total Clicks</span>
                        <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-[#4285F4]">
                            <span class="material-symbols-outlined text-sm">ads_click</span>
                        </span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-lg text-display-lg leading-none font-bold text-[#4285F4]"><?php echo number_format($seoData['summary']['clicks']); ?></h3>
                        <p class="text-on-surface-variant text-[11px] mt-xs">Direct search traffic clicks</p>
                    </div>
                </div>

                <!-- Impressions -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-[#7C3AED] transition-colors shadow-sm">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-[11px] uppercase tracking-wider">Total Impressions</span>
                        <span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center text-[#7C3AED]">
                            <span class="material-symbols-outlined text-sm">visibility</span>
                        </span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-lg text-display-lg leading-none font-bold text-[#7C3AED]"><?php echo number_format($seoData['summary']['impressions']); ?></h3>
                        <p class="text-on-surface-variant text-[11px] mt-xs">Search results appearance</p>
                    </div>
                </div>

                <!-- Average CTR -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-[#10B981] transition-colors shadow-sm">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-[11px] uppercase tracking-wider">Average CTR</span>
                        <span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-[#10B981]">
                            <span class="material-symbols-outlined text-sm">percent</span>
                        </span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-lg text-display-lg leading-none font-bold text-[#10B981]"><?php echo number_format($seoData['summary']['ctr'], 2); ?>%</h3>
                        <p class="text-on-surface-variant text-[11px] mt-xs">Click-through rate average</p>
                    </div>
                </div>

                <!-- Average Position -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex flex-col justify-between h-32 relative overflow-hidden group hover:border-[#F59E0B] transition-colors shadow-sm">
                    <div class="flex justify-between items-start z-10">
                        <span class="text-on-surface-variant font-data-label text-[11px] uppercase tracking-wider">Average Position</span>
                        <span class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-[#F59E0B]">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                        </span>
                    </div>
                    <div class="z-10">
                        <h3 class="font-display-lg text-display-lg leading-none font-bold text-[#F59E0B]"><?php echo number_format($seoData['summary']['position'], 1); ?></h3>
                        <p class="text-on-surface-variant text-[11px] mt-xs">Keyword rank position avg</p>
                    </div>
                </div>
            </div>

            <!-- Chart Row -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md">
                <div class="border-b border-surface-variant pb-sm">
                    <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Search Console Trend</h3>
                    <p class="text-on-surface-variant text-xs mt-xs">Clicks (blue) and impressions (purple) organic trends.</p>
                </div>
                
                <div class="relative h-96 w-full p-2">
                    <canvas id="seoDetailedTrendChart"></canvas>
                </div>
            </div>

            <!-- PageSpeed Insights Audit Card -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md">
                <div class="border-b border-surface-variant pb-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-sm">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Technical SEO Audit (PageSpeed)</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Run a technical Lighthouse audit on the connected domain: <strong class="text-primary"><?php echo htmlspecialchars($siteUrl ?: 'No site connected'); ?></strong></p>
                    </div>
                    <?php if ($isSeoConnected): ?>
                        <div class="flex gap-sm">
                            <select id="pagespeed-strategy" class="h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none">
                                <option value="mobile">Mobile Strategy</option>
                                <option value="desktop">Desktop Strategy</option>
                            </select>
                            <button id="run-audit-btn" onclick="runPageSpeedAudit()" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-bold text-xs hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                                <span class="material-symbols-outlined text-sm">speed</span>
                                <span>Run Audit</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="pagespeed-loading" class="hidden py-xl text-center space-y-md text-on-surface-variant">
                    <span class="inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <p class="text-xs">Analyzing website performance metrics... (This can take up to 45 seconds)</p>
                </div>

                <div id="pagespeed-error" class="hidden p-md rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs"></div>

                <div id="pagespeed-results" class="grid grid-cols-1 md:grid-cols-3 gap-md pt-xs hidden">
                    <!-- Performance -->
                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl text-center space-y-xs shadow-xs">
                        <p class="font-bold text-xs uppercase tracking-wider text-on-surface-variant">Performance</p>
                        <div id="perf-score" class="w-16 h-16 rounded-full mx-auto flex items-center justify-center font-display-lg text-display-lg font-bold text-white transition-all duration-300">--</div>
                    </div>
                    <!-- Accessibility -->
                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl text-center space-y-xs shadow-xs">
                        <p class="font-bold text-xs uppercase tracking-wider text-on-surface-variant">Accessibility</p>
                        <div id="access-score" class="w-16 h-16 rounded-full mx-auto flex items-center justify-center font-display-lg text-display-lg font-bold text-white transition-all duration-300">--</div>
                    </div>
                    <!-- SEO -->
                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl text-center space-y-xs shadow-xs">
                        <p class="font-bold text-xs uppercase tracking-wider text-on-surface-variant">Lighthouse SEO</p>
                        <div id="lighthouse-seo-score" class="w-16 h-16 rounded-full mx-auto flex items-center justify-center font-display-lg text-display-lg font-bold text-white transition-all duration-300">--</div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const canvas = document.getElementById('seoDetailedTrendChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartDates); ?>,
                datasets: [
                    {
                        label: 'Clicks',
                        data: <?php echo json_encode($chartClicks); ?>,
                        borderColor: '#4285F4',
                        borderWidth: 3,
                        backgroundColor: 'rgba(66, 133, 244, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#4285F4',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Impressions',
                        data: <?php echo json_encode($chartImps); ?>,
                        borderColor: '#7C3AED',
                        borderWidth: 3,
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#7C3AED',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#1E293B',
                            font: { size: 12, weight: 'bold' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748B', font: { size: 10, weight: '500' } }
                    },
                    y: {
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { color: '#64748B', font: { size: 10, weight: '500' } }
                    }
                }
            }
        });
    });

    function runPageSpeedAudit() {
        const btn = document.getElementById('run-audit-btn');
        const loader = document.getElementById('pagespeed-loading');
        const results = document.getElementById('pagespeed-results');
        const errDiv = document.getElementById('pagespeed-error');
        const strategy = document.getElementById('pagespeed-strategy').value;
        const siteUrl = '<?php echo $siteUrl; ?>';

        if (!siteUrl) {
            alert('Please connect your website property to Search Console first.');
            return;
        }

        btn.disabled = true;
        loader.classList.remove('hidden');
        results.classList.add('hidden');
        errDiv.classList.add('hidden');

        fetch(`ajax_pagespeed.php?url=${encodeURIComponent(siteUrl)}&strategy=${strategy}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const audit = data.data;
                    
                    const pScore = Math.round((audit.performance_score || 0) * 100);
                    const aScore = Math.round((audit.accessibility_score || 0) * 100);
                    const sScore = Math.round((audit.seo_score || 0) * 100);

                    // Render Performance Score
                    renderCircleScore('perf-score', pScore);
                    renderCircleScore('access-score', aScore);
                    renderCircleScore('lighthouse-seo-score', sScore);

                    results.classList.remove('hidden');
                } else {
                    errDiv.textContent = 'PageSpeed audit failed: ' + (data.error || 'Unknown API response');
                    errDiv.classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error(err);
                errDiv.textContent = 'Connection error occurred while auditing the site.';
                errDiv.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                loader.classList.add('hidden');
            });
    }

    function renderCircleScore(elementId, score) {
        const el = document.getElementById(elementId);
        el.textContent = score;
        el.className = 'w-16 h-16 rounded-full mx-auto flex items-center justify-center font-display-lg text-display-lg font-bold text-white transition-all duration-300';
        
        if (score >= 90) {
            el.classList.add('bg-green-500');
        } else if (score >= 50) {
            el.classList.add('bg-amber-500');
        } else {
            el.classList.add('bg-red-500');
        }
    }
    
    function openCustomDateModal() {
        const modal = document.getElementById('custom-date-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    }

    function closeCustomDateModal() {
        const modal = document.getElementById('custom-date-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }
    </script>

    <!-- Custom Date Picker Modal -->
    <div id="custom-date-modal" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-200" onclick="closeCustomDateModal()">
        <div class="bg-surface-bright border border-surface-variant rounded-2xl w-full max-w-md p-lg shadow-lg space-y-md transform scale-95 transition-transform duration-200" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center border-b border-surface-variant pb-sm">
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Select Custom Range</h3>
                <button onclick="closeCustomDateModal()" class="text-on-surface-variant hover:text-on-surface flex items-center justify-center">
                    <span class="material-symbols-outlined text-md">close</span>
                </button>
            </div>
            
            <form method="GET" action="" class="space-y-md">
                <input type="hidden" name="preset" value="custom">
                <div class="grid grid-cols-2 gap-md">
                    <div class="space-y-xs">
                        <label class="font-data-label text-[10px] text-on-surface-variant block uppercase tracking-wider font-bold" for="start_date">From Date</label>
                        <input type="date" id="start_date" name="start_date" required class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>

                    <div class="space-y-xs">
                        <label class="font-data-label text-[10px] text-on-surface-variant block uppercase tracking-wider font-bold" for="end_date">To Date</label>
                        <input type="date" id="end_date" name="end_date" required class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                </div>

                <div class="flex justify-end gap-sm pt-sm border-t border-surface-variant">
                    <button type="button" onclick="closeCustomDateModal()" class="h-10 px-lg bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg font-bold text-xs transition-all">Cancel</button>
                    <button type="submit" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-bold text-xs hover:opacity-90 active:scale-95 transition-all">Apply Range</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
