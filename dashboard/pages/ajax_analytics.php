<?php
/**
 * AJAX endpoint for loading dashboard analytics widgets.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

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
        if ($conn['status'] === 'connected') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}

if (empty($connectedPlatforms)) {
    echo '<div class="p-lg text-center space-y-sm text-on-surface-variant w-full">
        <span class="material-symbols-outlined text-4xl text-outline-variant">analytics</span>
        <p class="font-bold text-sm">No connected channels found.</p>
        <p class="text-xs">Link your accounts in Connections to track performance analytics.</p>
    </div>';
    exit();
}

$platform = $_GET['platform'] ?? $connectedPlatforms[0];
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Ensure the selected platform is connected
if (!in_array($platform, $connectedPlatforms)) {
    $platform = $connectedPlatforms[0];
}

$analyticsRes = hubGetAnalytics($client_id, $platform, 0, $startDate, $endDate);
$metrics = [];
$errorMsg = null;

if (!empty($analyticsRes['success']) && is_array($analyticsRes['metrics'])) {
    $metrics = $analyticsRes['metrics'];
} else {
    $errorMsg = $analyticsRes['error'] ?? 'Unable to retrieve analytics from Hub proxy.';
}

/**
 * Render a beautiful, custom dynamic SVG bar chart.
 */
function renderSvgChartLocal($metrics, $chartWidth = 800, $chartHeight = 300) {
    $graphableMetrics = [];
    foreach ($metrics as $m) {
        if (is_numeric($m['value'])) {
            $graphableMetrics[] = $m;
        }
    }
    
    if (empty($graphableMetrics)) {
        return "<div class=\"p-lg text-center text-on-surface-variant font-bold text-xs bg-surface-container-low rounded-xl border border-surface-variant\">No graphable numerical metrics found for this channel.</div>";
    }

    $maxVal = 1;
    foreach ($graphableMetrics as $m) {
        if ((int)$m['value'] > $maxVal) {
            $maxVal = (int)$m['value'];
        }
    }

    $padding = 40;
    $count = count($graphableMetrics);
    $availableWidth = $chartWidth - ($padding * 2);
    $spacing = 15;
    $barWidth = ($count > 0) ? ($availableWidth - ($spacing * ($count - 1))) / $count : $availableWidth;
    $barWidth = max(24, min(64, $barWidth));

    if ($count > 1) {
        $spacing = ($availableWidth - ($barWidth * $count)) / ($count - 1);
    }

    $svg = "<svg width=\"100%\" height=\"{$chartHeight}\" viewBox=\"0 0 {$chartWidth} {$chartHeight}\" preserveAspectRatio=\"xMidYMid meet\" class=\"bg-surface-container-lowest rounded-xl border border-surface-variant p-md shadow-sm\">";
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
        
        $h = ($val / $maxVal) * ($chartHeight - ($padding * 2) - 20);
        $h = max(8, $h);
        
        $y = $chartHeight - $padding - $h;
        
        $svg .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$barWidth}\" height=\"{$h}\" rx=\"6\" fill=\"url(#primary-grad)\">";
        $svg .= "  <animate attributeName=\"height\" from=\"0\" to=\"{$h}\" dur=\"0.4s\" fill=\"freeze\" />";
        $svg .= "  <animate attributeName=\"y\" from=\"" . ($chartHeight - $padding) . "\" to=\"{$y}\" dur=\"0.4s\" fill=\"freeze\" />";
        $svg .= "</rect>";

        $svg .= "<text x=\"" . ($x + ($barWidth / 2)) . "\" y=\"" . ($y - 8) . "\" fill=\"#191c1e\" font-size=\"10\" font-weight=\"bold\" text-anchor=\"middle\">" . number_format($val) . "</text>";
        $svg .= "<text x=\"" . ($x + ($barWidth / 2)) . "\" y=\"" . ($chartHeight - 15) . "\" fill=\"#454653\" font-size=\"9\" font-weight=\"500\" text-anchor=\"middle\">" . htmlspecialchars(mb_strimwidth($metricLabel, 0, 15, '..')) . "</text>";
        
        $x += $barWidth + $spacing;
    }

    $svg .= "</svg>";
    return $svg;
}
?>

<?php if ($errorMsg): ?>
    <div class="bg-error-container text-on-error-container p-md rounded-xl border border-error/20 text-center text-xs font-bold w-full">
        ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter w-full">
        <!-- Dynamic Chart -->
        <div class="lg:col-span-2 flex flex-col justify-center">
            <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider mb-xs">KPI Volume Trends</h4>
            <?php echo renderSvgChartLocal($metrics); ?>
        </div>

        <!-- Metric Cards -->
        <div class="space-y-xs">
            <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider mb-xs">Normalized Metrics</h4>
            <div class="grid grid-cols-1 gap-xs max-h-[280px] overflow-y-auto pr-xs">
                <?php foreach ($metrics as $m): ?>
                    <div class="bg-surface-container-low border border-surface-variant p-sm rounded-lg flex justify-between items-center shadow-xs">
                        <div class="min-w-0">
                            <div class="font-bold text-on-surface text-xs capitalize truncate">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $m['metric_name'])); ?>
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
