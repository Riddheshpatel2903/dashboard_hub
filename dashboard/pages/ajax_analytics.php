<?php
/**
 * AJAX endpoint for loading dashboard analytics widgets (Stitch Social Mission Control Design).
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

$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$activePlatform = !empty($platform) ? $platform : $connectedPlatforms[0];

$analyticsRes = hubGetAnalytics($client_id, $activePlatform, 0, $startDate, $endDate);
$metrics = [];
$errorMsg = null;

if (!empty($analyticsRes['success']) && is_array($analyticsRes['metrics'])) {
    $metrics = $analyticsRes['metrics'];
} else {
    $errorMsg = $analyticsRes['error'] ?? 'Unable to retrieve analytics from Hub proxy.';
}
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
                <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">Performance Timeline Trend</h4>
                <span class="text-[10px] font-bold text-primary uppercase font-data-label bg-primary-container/10 px-xs py-0.5 rounded border border-primary/20"><?php echo htmlspecialchars(strtoupper($activePlatform)); ?></span>
            </div>
            
            <div class="relative h-64 w-full rounded-xl border border-surface-variant/50 overflow-hidden bg-surface-container-lowest shadow-xs p-xs">
                <!-- SVG Area Graph Rendering (Exact Cubic Bezier Curve from Stitch) -->
                <div class="absolute inset-0 flex items-end justify-between px-md pb-xl">
                    <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 1000 100">
                        <defs>
                            <linearGradient id="ajaxChartGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2031a9" stop-opacity="0.15"></stop>
                                <stop offset="100%" stop-color="#2031a9" stop-opacity="0"></stop>
                            </linearGradient>
                        </defs>
                        <path d="M0,100 L0,70 C50,65 100,80 150,60 C200,40 250,55 300,30 C350,5 400,20 450,15 C500,10 550,45 600,35 C650,25 700,5 750,10 C800,15 850,50 900,40 C950,30 1000,10 L1000,100 Z" fill="url(#ajaxChartGrad)"></path>
                        <path d="M0,70 C50,65 100,80 150,60 C200,40 250,55 300,30 C350,5 400,20 450,15 C500,10 550,45 600,35 C650,25 700,5 750,10 C800,15 850,50 900,40 C950,30 1000,10" fill="none" stroke="#2031a9" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                    </svg>
                    
                    <!-- Vertical Grid Lines from Stitch -->
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                    <div class="w-[1px] h-full bg-surface-variant/50"></div>
                </div>

                <!-- Interactive Data Point Tooltip Sim -->
                <div class="absolute left-1/2 top-1/4 group cursor-pointer">
                    <div class="w-3 h-3 bg-primary border-2 border-on-primary rounded-full shadow-md z-10"></div>
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 bg-inverse-surface text-background px-3 py-1.5 rounded-lg text-body-sm whitespace-nowrap opacity-90 transition-opacity">
                        <p class="font-bold text-xs">Oct 14, 2023</p>
                        <p class="font-data-label text-data-label opacity-80 text-[10px]">Reach: 142,402</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-between mt-2 px-xs font-data-label text-[10px] text-on-surface-variant uppercase">
                <span>OCT 01</span>
                <span>OCT 07</span>
                <span>OCT 14</span>
                <span>OCT 21</span>
                <span>OCT 28</span>
                <span>NOV 01</span>
            </div>
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

