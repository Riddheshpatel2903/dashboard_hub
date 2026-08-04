<?php
/**
 * Cross-Client Operational Health View (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/role_check.php'; // Ensures logged-in staff/admin
require_once __DIR__ . '/../includes/hub_client.php';

$error = '';
$expiringTokens = [];
$failedPosts = [];
$quotaConsumed = 0;
$quotaLimit = 10000;

// Fetch cross-client operational metrics
$hubRes = hubGetSystemHealth();
if (!empty($hubRes['success'])) {
    $expiringTokens = $hubRes['expiring_tokens'] ?? [];
    $failedPosts = $hubRes['failed_posts'] ?? [];
    $quotaConsumed = $hubRes['youtube_quota_consumed'] ?? 0;
    $quotaLimit = $hubRes['youtube_quota_limit'] ?? 10000;
} else {
    $error = 'Failed to load system health logs: ' . ($hubRes['error'] ?? 'Unknown Connection Error');
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>System Status | Social Hub</title>
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
                <h1 class="font-display-lg text-display-lg text-on-surface">System Status</h1>
                <p class="font-body-md text-on-surface-variant">Cross-client connection status logs and API endpoint monitoring dashboard.</p>
            </div>

            <!-- Notifications -->
            <?php if ($error): ?>
                <div class="bg-error-container text-on-error-container p-md rounded-lg flex items-center gap-md border border-error/20">
                    <span class="material-symbols-outlined text-xl">error</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- YouTube API Quota consumption display -->
            <?php 
                $quotaPercent = min(100, round(($quotaConsumed / $quotaLimit) * 100));
                
                $quotaColor = 'bg-[#1F9D6B]'; // green
                $quotaTextColor = 'text-[#1F9D6B]';
                if ($quotaPercent > 80) {
                    $quotaColor = 'bg-error';
                    $quotaTextColor = 'text-error';
                } elseif ($quotaPercent > 50) {
                    $quotaColor = 'bg-primary';
                    $quotaTextColor = 'text-primary';
                }
            ?>
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md">
                <div class="flex justify-between items-center flex-wrap gap-md">
                    <span class="font-bold text-on-surface text-sm">YouTube Project API Quota Usage (Today)</span>
                    <strong class="<?php echo $quotaTextColor; ?> text-sm">
                        <?php echo number_format($quotaConsumed); ?> / <?php echo number_format($quotaLimit); ?> units (<?php echo $quotaPercent; ?>%)
                    </strong>
                </div>
                <p class="text-xs text-on-surface-variant leading-relaxed">
                    Estimated by scaling successful video uploads (1,600 units each) against standard daily Google API quotas.
                </p>
                <div class="w-full bg-surface-container-high h-3 rounded-full overflow-hidden border border-surface-variant/40">
                    <div class="h-full rounded-full transition-all duration-500 <?php echo $quotaColor; ?>" style="width: <?php echo $quotaPercent; ?>%;"></div>
                </div>
            </div>

            <!-- Health Logs Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
                <!-- Section 1: Expiring / Expired tokens -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md flex flex-col justify-between">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">⚠️ Connection Re-auth Warnings</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Active access tokens needing owner action or re-authorization.</p>
                    </div>

                    <div class="flex-grow mt-md">
                        <?php if (empty($expiringTokens)): ?>
                            <div class="p-xl text-center text-on-surface-variant text-sm font-bold">
                                All platforms connected with valid long-lived OAuth tokens.
                            </div>
                        <?php else: ?>
                            <div class="space-y-sm">
                                <?php foreach ($expiringTokens as $tok): 
                                    $isExpired = ($tok['status'] === 'expired');
                                    // Resolve badge colors
                                    $platIcon = 'face';
                                    $platColorClass = 'bg-surface-container text-on-surface-variant';
                                    if ($tok['platform'] === 'facebook') {
                                        $platIcon = 'public';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
                                    } elseif ($tok['platform'] === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platColorClass = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
                                    } elseif ($tok['platform'] === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platColorClass = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
                                    } elseif ($tok['platform'] === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platColorClass = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
                                    } elseif ($tok['platform'] === 'linkedin') {
                                        $platIcon = 'work';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
                                    } elseif ($tok['platform'] === 'google_business') {
                                        $platIcon = 'store';
                                        $platColorClass = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
                                    }
                                ?>
                                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-lg flex justify-between items-center shadow-xs">
                                        <div class="space-y-xs">
                                            <div class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($tok['client_name']); ?></div>
                                            <div class="flex items-center gap-sm">
                                                <span class="inline-flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold uppercase <?php echo $platColorClass; ?>">
                                                    <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                                                    <span><?php echo htmlspecialchars($tok['platform']); ?></span>
                                                </span>
                                                <span class="text-[10px] text-on-surface-variant font-data-label">
                                                    Expires: <?php echo $tok['expires_at'] ? date('Y-m-d H:i', strtotime($tok['expires_at'])) : 'Expired'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right space-y-xs">
                                            <span class="inline-block text-[10px] font-bold px-sm py-[2px] rounded-full uppercase <?php echo $isExpired ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'; ?>">
                                                <?php echo strtoupper($tok['status']); ?>
                                            </span>
                                            <div>
                                                <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/client_detail.php?act_as_client_id=<?php echo $tok['client_id']; ?>" 
                                                   class="h-7 px-sm bg-primary text-on-primary rounded font-body-sm font-bold text-xs hover:opacity-90 transition-all inline-flex items-center justify-center gap-xs">
                                                    <span>Reconnect</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 2: Recent failed posts -->
                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-lg shadow-sm space-y-md flex flex-col justify-between">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">❌ Recently Failed Publications</h3>
                        <p class="text-on-surface-variant text-xs mt-xs">Postings that returned an API payload error during dispatch.</p>
                    </div>

                    <div class="flex-grow mt-md max-h-[400px] overflow-y-auto pr-xs">
                        <?php if (empty($failedPosts)): ?>
                            <div class="p-xl text-center text-on-surface-variant text-sm font-bold">
                                No failed publication runs logged.
                            </div>
                        <?php else: ?>
                            <div class="space-y-sm">
                                <?php foreach ($failedPosts as $post): 
                                    // Resolve brand badge colors
                                    $platIcon = 'face';
                                    $platColorClass = 'bg-surface-container text-on-surface-variant';
                                    if ($post['platform'] === 'facebook') {
                                        $platIcon = 'public';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platColorClass = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
                                    } elseif ($post['platform'] === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platColorClass = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
                                    } elseif ($post['platform'] === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platColorClass = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
                                    } elseif ($post['platform'] === 'linkedin') {
                                        $platIcon = 'work';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'google_business') {
                                        $platIcon = 'store';
                                        $platColorClass = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
                                    }
                                ?>
                                    <div class="bg-surface-container-low border border-surface-variant p-md rounded-lg space-y-sm shadow-xs">
                                        <div class="flex justify-between items-center">
                                            <strong class="text-on-surface text-sm font-bold"><?php echo htmlspecialchars($post['client_name']); ?></strong>
                                            <span class="inline-flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold uppercase <?php echo $platColorClass; ?>">
                                                <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                                                <span><?php echo htmlspecialchars($post['platform']); ?></span>
                                            </span>
                                        </div>
                                        <p class="text-xs text-on-surface-variant truncate">
                                            <?php echo htmlspecialchars($post['content']); ?>
                                        </p>
                                        <div class="text-[11px] text-error bg-error-container p-md rounded border border-error/10 leading-relaxed font-mono">
                                            <strong>Error:</strong> <?php echo htmlspecialchars($post['response_body'] ?: 'Platform API call timeout or token invalidation.'); ?>
                                        </div>
                                        <div class="text-[9px] font-data-label text-on-surface-variant text-right">
                                            Failed at: <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
