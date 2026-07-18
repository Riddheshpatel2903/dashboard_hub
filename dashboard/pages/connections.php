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

// Full list of supported platforms
$platformMetadata = [
    'facebook' => [
        'name' => 'Facebook Page',
        'desc' => 'Publish posts, track page engagement, and reply to comments.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_facebook.php?client_id=' . $client_id . '&platform=facebook',
        'icon' => 'public',
        'color' => '#1877F2'
    ],
    'instagram' => [
        'name' => 'Instagram Business',
        'desc' => 'Publish images/videos directly and read media feedback analytics.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_facebook.php?client_id=' . $client_id . '&platform=instagram', // Shared OAuth
        'icon' => 'photo_camera',
        'color' => '#E1306C'
    ],
    'whatsapp' => [
        'name' => 'WhatsApp Business Cloud API',
        'desc' => 'Send text messages and templates, and run client conversations.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_whatsapp.php?client_id=' . $client_id,
        'icon' => 'chat',
        'color' => '#25D366'
    ],
    'youtube' => [
        'name' => 'YouTube Channel',
        'desc' => 'Upload video files using resumable streaming and view video analytics.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_youtube.php?client_id=' . $client_id,
        'icon' => 'play_circle',
        'color' => '#FF0000'
    ],
    'linkedin' => [
        'name' => 'LinkedIn Member Profile',
        'desc' => 'Share articles, posts, and text updates on your personal feed.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_linkedin.php?client_id=' . $client_id,
        'icon' => 'work',
        'color' => '#0A66C2'
    ],
    'google_business' => [
        'name' => 'Google Business Profile',
        'desc' => 'Create local posts, update business listings, and reply to reviews.',
        'auth_url' => HUB_BASE_URL . '/auth/connect_google_business.php?client_id=' . $client_id,
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
    <title>Platform Connections | Command Center</title>
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
                <h1 class="font-display-lg text-display-lg text-on-surface mb-xs">Connections Manager</h1>
                <p class="font-body-md text-on-surface-variant">Sync and manage your social media and business API profiles.</p>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
                <!-- Active -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex items-center gap-md shadow-sm">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-700">check_circle</span>
                    </div>
                    <div>
                        <p class="font-data-label text-on-surface-variant uppercase text-xs">Active Connected</p>
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

                            <!-- Token info if linked -->
                            <?php if ($conn && $conn['external_account_id']): ?>
                                <div class="bg-surface-container-low p-md rounded-lg text-[11px] font-data-label text-on-surface-variant space-y-xs">
                                    <div class="truncate"><strong>Linked ID:</strong> <?php echo htmlspecialchars($conn['external_account_id']); ?></div>
                                    <?php if ($conn['expires_at']): ?>
                                        <div><strong>Expires:</strong> <?php echo date('Y-m-d H:i', strtotime($conn['expires_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-lg pt-md border-t border-surface-variant">
                            <?php if ($status === 'connected' && !$expiresSoon): ?>
                                <button class="w-full h-10 bg-surface-container text-on-surface-variant font-bold rounded-lg cursor-not-allowed opacity-60 text-xs" disabled>
                                    Linked & Active
                                </button>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($meta['auth_url']); ?>" 
                                   class="w-full h-10 bg-primary text-on-primary font-bold rounded-lg shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs text-xs">
                                    <span class="material-symbols-outlined text-sm"><?php echo ($isExpired || $expiresSoon) ? 'refresh' : 'link'; ?></span>
                                    <span><?php echo ($isExpired || $expiresSoon) ? 'Reconnect Account' : 'Link Account'; ?></span>
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
