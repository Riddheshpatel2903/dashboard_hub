<?php
/**
 * Performance Analytics Page (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// 1. Fetch connected platform accounts
$connectedPlatforms = [];
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}

$platform = $_GET['platform'] ?? (!empty($connectedPlatforms) ? $connectedPlatforms[0] : '');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$metrics = [];
$errorMsg = null;

if (!empty($platform)) {
    // 2. Fetch metrics from the Hub
    $analyticsRes = hubGetAnalytics($client_id, $platform, $startDate, $endDate);
    if (!empty($analyticsRes['success']) && is_array($analyticsRes['metrics'])) {
        $metrics = $analyticsRes['metrics'];
    } else {
        $errorMsg = $analyticsRes['error'] ?? 'Unable to retrieve analytics from Hub proxy.';
    }
}

/**
 * Render a beautiful, custom dynamic SVG bar chart.
 */
function renderSvgChart($metrics, $chartWidth = 800, $chartHeight = 350) {
    $graphableMetrics = [];
    foreach ($metrics as $m) {
        if (is_numeric($m['value'])) {
            $graphableMetrics[] = $m;
        }
    }
    
    if (empty($graphableMetrics)) {
        return "<div class=\"p-lg text-center text-on-surface-variant font-bold\">No graphable metrics found.</div>";
    }

    $maxVal = 1;
    foreach ($graphableMetrics as $m) {
        if ((int)$m['value'] > $maxVal) {
            $maxVal = (int)$m['value'];
        }
    }

    $padding = 50;
    $count = count($graphableMetrics);
    $availableWidth = $chartWidth - ($padding * 2);
    $spacing = 15;
    $barWidth = ($count > 0) ? ($availableWidth - ($spacing * ($count - 1))) / $count : $availableWidth;
    $barWidth = max(24, min(64, $barWidth));

    // Re-adjust spacing if barWidth is forced
    if ($count > 1) {
        $spacing = ($availableWidth - ($barWidth * $count)) / ($count - 1);
    }

    // Generate SVG string
    $svg = "<svg width=\"100%\" height=\"{$chartHeight}\" viewBox=\"0 0 {$chartWidth} {$chartHeight}\" preserveAspectRatio=\"xMidYMid meet\" class=\"bg-surface-container-lowest rounded-xl border border-surface-variant p-md shadow-sm\">";
    
    // Gradient definitions
    $svg .= "
    <defs>
        <linearGradient id=\"primary-grad\" x1=\"0%\" y1=\"0%\" x2=\"0%\" y2=\"100%\">
            <stop offset=\"0%\" stop-color=\"#3c4cc1\" />
            <stop offset=\"100%\" stop-color=\"#2031a9\" />
        </linearGradient>
    </defs>";

    $x = $padding;
    foreach ($graphableMetrics as $m) {
        $val = (int)$m['value'];
        $metricLabel = str_replace('_', ' ', $m['metric_name']);
        
        // Scale height
        $h = ($val / $maxVal) * ($chartHeight - ($padding * 2) - 20);
        $h = max(8, $h); // minimum visual height
        
        $y = $chartHeight - $padding - $h;
        
        // Render rect bar with animations
        $svg .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$barWidth}\" height=\"{$h}\" rx=\"6\" fill=\"url(#primary-grad)\">";
        $svg .= "  <animate attributeName=\"height\" from=\"0\" to=\"{$h}\" dur=\"0.4s\" fill=\"freeze\" />";
        $svg .= "  <animate attributeName=\"y\" from=\"" . ($chartHeight - $padding) . "\" to=\"{$y}\" dur=\"0.4s\" fill=\"freeze\" />";
        $svg .= "</rect>";

        // Value label text
        $svg .= "<text x=\"" . ($x + ($barWidth / 2)) . "\" y=\"" . ($y - 8) . "\" fill=\"#191c1e\" font-size=\"11\" font-weight=\"bold\" text-anchor=\"middle\">" . number_format($val) . "</text>";

        // Axis label text
        $svg .= "<text x=\"" . ($x + ($barWidth / 2)) . "\" y=\"" . ($chartHeight - 15) . "\" fill=\"#454653\" font-size=\"10\" font-weight=\"500\" text-anchor=\"middle\">" . htmlspecialchars(mb_strimwidth($metricLabel, 0, 16, '..')) . "</text>";
        
        $x += $barWidth + $spacing;
    }

    $svg .= "</svg>";
    return $svg;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Performance Analytics | Command Center</title>
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
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface">Performance Analytics</h1>
                <p class="font-body-md text-on-surface-variant">Track reach, impressions, engagement, and audience demographics.</p>
            </div>

            <!-- Date and Platform Selector -->
            <?php if (empty($connectedPlatforms)): ?>
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-xl text-center max-w-[600px] mx-auto shadow-sm space-y-md">
                    <span class="material-symbols-outlined text-primary text-5xl">analytics</span>
                    <h3 class="font-headline-sm text-headline-sm font-bold">No Connected Platforms</h3>
                    <p class="text-on-surface-variant font-body-sm">You must link a channel first to pull tracking statistics.</p>
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/connections.php" class="inline-flex items-center gap-xs px-md py-sm bg-primary text-on-primary rounded-lg font-bold hover:opacity-90 active:scale-95 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">link</span>
                        <span>Connect Channels Now</span>
                    </a>
                </div>
            <?php else: ?>
                <!-- Filter bar -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm">
                    <form method="GET" action="" class="flex flex-wrap items-end gap-md">
                        <!-- Platform Dropdown -->
                        <div class="flex-1 min-w-[200px] space-y-xs">
                            <label class="font-data-label text-data-label text-on-surface-variant block" for="platform">SELECT CHANNEL</label>
                            <select id="platform" name="platform" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary capitalize">
                                <?php foreach ($connectedPlatforms as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo $platform === $p ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p === 'google_business' ? 'Google Profile' : $p); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Date Pickers -->
                        <div class="w-[180px] space-y-xs">
                            <label class="font-data-label text-data-label text-on-surface-variant block" for="start_date">START DATE</label>
                            <input type="date" id="start_date" name="start_date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>

                        <div class="w-[180px] space-y-xs">
                            <label class="font-data-label text-data-label text-on-surface-variant block" for="end_date">END DATE</label>
                            <input type="date" id="end_date" name="end_date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-body-sm font-bold hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">sync</span>
                            <span>Sync Analytics</span>
                        </button>
                    </form>
                </div>

                <!-- Metrics Display -->
                <?php if ($errorMsg): ?>
                    <div class="bg-error-container text-on-error-container p-lg rounded-xl border border-error/20 text-center font-bold">
                        ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
                        <!-- Chart Display -->
                        <div class="lg:col-span-2 bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm flex flex-col justify-between">
                            <div class="mb-md">
                                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">KPI Volume Trends</h3>
                                <p class="text-on-surface-variant text-xs mt-xs">Visualizing performance values for the active period.</p>
                            </div>
                            <div class="flex-grow flex items-center justify-center">
                                <?php echo renderSvgChart($metrics); ?>
                            </div>
                        </div>

                        <!-- Numeric Normalized Metrics -->
                        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md">
                            <div>
                                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Normalized Metrics</h3>
                                <p class="text-on-surface-variant text-xs mt-xs">Tabulated performance dimensions.</p>
                            </div>
                            <div class="space-y-sm">
                                <?php foreach ($metrics as $m): ?>
                                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-lg flex justify-between items-center shadow-xs">
                                        <div>
                                            <div class="font-bold text-on-surface text-sm capitalize">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $m['metric_name'])); ?>
                                            </div>
                                            <div class="text-[10px] font-data-label text-on-surface-variant uppercase mt-xs">
                                                Period: <?php echo htmlspecialchars($m['period'] ?? 'n/a'); ?>
                                            </div>
                                        </div>
                                        <div class="font-display-sm text-display-sm text-primary font-bold">
                                            <?php 
                                                if (is_numeric($m['value'])) {
                                                    echo number_format((int)$m['value']);
                                                } else {
                                                    echo '<span class="text-xs font-normal text-on-surface-variant">' . htmlspecialchars($m['value']) . '</span>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
