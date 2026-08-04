<?php
/**
 * Manage Connections Page (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// 1. Fetch connection statuses from the Hub
$connections = [];
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['connections_status_' . $client_id]);
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    foreach ($hubRes['connections'] as $conn) {
        $connections[$conn['platform']] = [
            'status'              => $conn['status'],
            'external_account_id' => $conn['external_account_id'],
            'expires_at'          => $conn['expires_at'],
            'expires_soon'        => $conn['expires_soon']
        ];
    }
}

// Compute absolute Dashboard URL to pass to the Hub for OAuth callback redirection
$httpScheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$absoluteDashboardUrl = "{$httpScheme}://{$httpHost}" . DASHBOARD_BASE_URL;

// Full list of supported platforms
$platformMetadata = [
    'facebook' => [
        'name' => 'Facebook',
        'desc' => 'Connect to publish posts and track page analytics.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_facebook.php?client_id=' . $client_id . '&platform=facebook&dashboard_url=' . urlencode($absoluteDashboardUrl),
        'icon' => 'public',
        'color' => '#1877F2'
    ],
    'instagram' => [
        'name' => 'Instagram',
        'desc' => 'Connect to share images and videos.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_facebook.php?client_id=' . $client_id . '&platform=instagram&dashboard_url=' . urlencode($absoluteDashboardUrl), // Shared OAuth
        'icon' => 'photo_camera',
        'color' => '#E1306C'
    ],
    'youtube' => [
        'name' => 'YouTube',
        'desc' => 'Connect to publish video content.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_youtube.php?client_id=' . $client_id . '&dashboard_url=' . urlencode($absoluteDashboardUrl),
        'icon' => 'play_circle',
        'color' => '#FF0000'
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'desc' => 'Connect to share updates and posts.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_linkedin.php?client_id=' . $client_id . '&dashboard_url=' . urlencode($absoluteDashboardUrl),
        'icon' => 'work',
        'color' => '#0A66C2'
    ],
    'google_business' => [
        'name' => 'Google Business Profile',
        'desc' => 'Connect to manage your local business profile.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_google_business.php?client_id=' . $client_id . '&dashboard_url=' . urlencode($absoluteDashboardUrl),
        'icon' => 'store',
        'color' => '#4285F4'
    ]
];

// Calculate counts
$activeCount = 0;
$expiringCount = 0;
$disconnectedCount = 0;
foreach ($platformMetadata as $key => $meta) {
    $conn = $connections[$key] ?? null;
    $status = $conn ? $conn['status'] : 'disconnected';
    if ($status === 'connected') {
        if ($conn && $conn['expires_soon']) {
            $expiringCount++;
        } else {
            $activeCount++;
        }
    } elseif ($status === 'expired') {
        $expiringCount++;
    } else {
        $disconnectedCount++;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Connected Accounts | Social Hub</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="max-w-[1440px] mx-auto p-lg space-y-lg">
            <!-- Page Header -->
            <div>
                <h1 class="font-display-lg text-display-lg text-on-surface mb-xs">Connected Accounts</h1>
                <p class="font-body-md text-on-surface-variant">Connect your social media accounts to start posting and viewing analytics.</p>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
                <!-- Active -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex items-center gap-md shadow-sm">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-700">check_circle</span>
                    </div>
                    <div>
                        <p class="font-data-label text-on-surface-variant uppercase text-xs">Connected</p>
                        <p class="font-headline-sm text-headline-sm font-bold"><?php echo $activeCount; ?></p>
                    </div>
                </div>
                
                <!-- Expiring / Expired -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex items-center gap-md shadow-sm">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-amber-700">warning</span>
                    </div>
                    <div>
                        <p class="font-data-label text-on-surface-variant uppercase text-xs">Tokens Expiring</p>
                        <p class="font-headline-sm text-headline-sm font-bold"><?php echo $expiringCount; ?></p>
                    </div>
                </div>

                <!-- Disconnected -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex items-center gap-md shadow-sm">
                    <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-on-surface-variant">link_off</span>
                    </div>
                    <div>
                        <p class="font-data-label text-on-surface-variant uppercase text-xs">Not Configured</p>
                        <p class="font-headline-sm text-headline-sm font-bold"><?php echo $disconnectedCount; ?></p>
                    </div>
                </div>
            </div>

            <!-- Connections Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-gutter">
                <?php foreach ($platformMetadata as $key => $meta): 
                    $conn = $connections[$key] ?? null;
                    $status = $conn ? $conn['status'] : 'disconnected';
                    $isExpired = ($status === 'expired');
                    $expiresSoon = $conn ? $conn['expires_soon'] : false;
                    
                    // Style attributes
                    $cardBg = 'bg-surface-container-lowest';
                    $statusBg = 'bg-surface-container text-on-surface-variant';
                    $statusText = strtoupper($status);
                    
                    if ($status === 'connected') {
                        if ($expiresSoon) {
                            $cardBg = 'bg-amber-50/40 border-amber-200';
                            $statusBg = 'bg-amber-100 text-amber-700';
                            $statusText = 'EXPIRING';
                        } else {
                            $statusBg = 'bg-green-100 text-green-700';
                        }
                    } elseif ($isExpired) {
                        $cardBg = 'bg-red-50/40 border-red-200';
                        $statusBg = 'bg-red-100 text-red-700';
                    }
                ?>
                    <div class="connection-card border border-surface-variant rounded-xl p-lg flex flex-col justify-between shadow-sm hover:border-primary transition-all duration-200 <?php echo $cardBg; ?>">
                        <div class="space-y-md">
                            <!-- Card Header -->
                            <div class="flex justify-between items-start">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background-color: <?php echo $meta['color']; ?>15; color: <?php echo $meta['color']; ?>;">
                                    <span class="material-symbols-outlined text-[28px]"><?php echo $meta['icon']; ?></span>
                                </div>
                                <span class="px-sm py-1 rounded-full text-[10px] font-bold uppercase tracking-tight <?php echo $statusBg; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            
                            <!-- Brand & Description -->
                            <div>
                                <h3 class="font-body-lg text-body-lg font-bold text-on-surface capitalize"><?php echo htmlspecialchars($meta['name']); ?></h3>
                                <p class="font-body-sm text-on-surface-variant mt-xs text-xs leading-relaxed"><?php echo htmlspecialchars($meta['desc']); ?></p>
                            </div>


                        </div>

                        <!-- Action Button -->
                        <div class="mt-lg pt-md border-t border-surface-variant">
                            <?php if ($status === 'connected' && !$expiresSoon): ?>
                                <div class="flex gap-sm">
                                    <button class="flex-grow h-10 bg-surface-container text-on-surface-variant font-bold rounded-lg cursor-not-allowed opacity-60 text-xs" disabled>
                                        Linked & Active
                                    </button>
                                    <button onclick="unlinkPlatform('<?php echo $key; ?>')" 
                                            class="px-md h-10 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold rounded-lg transition-all flex items-center justify-center gap-xs text-xs">
                                        <span class="material-symbols-outlined text-sm">link_off</span>
                                        <span>Unlink</span>
                                    </button>
                                </div>
                            <?php elseif ($isExpired || $expiresSoon): ?>
                                <div class="flex flex-col gap-xs">
                                    <a href="<?php echo htmlspecialchars($meta['auth_url']); ?>" 
                                       class="w-full h-10 bg-primary text-on-primary font-bold rounded-lg shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs text-xs">
                                        <span class="material-symbols-outlined text-sm">refresh</span>
                                        <span>Reconnect Account</span>
                                    </a>
                                    <button onclick="unlinkPlatform('<?php echo $key; ?>')" 
                                            class="w-full h-10 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold rounded-lg transition-all flex items-center justify-center gap-xs text-xs">
                                        <span class="material-symbols-outlined text-sm">link_off</span>
                                        <span>Unlink Channel</span>
                                    </button>
                                </div>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($meta['auth_url']); ?>" 
                                   class="w-full h-10 bg-primary text-on-primary font-bold rounded-lg shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs text-xs">
                                    <span class="material-symbols-outlined text-sm">link</span>
                                    <span>Link Account</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="<?php echo DASHBOARD_BASE_URL; ?>/assets/js/connections.js"></script>
</body>
</html>
