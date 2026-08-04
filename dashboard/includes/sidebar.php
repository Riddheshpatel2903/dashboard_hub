<?php
/**
 * Shared Sidebar Layout (Stitch Social Mission Control Design System).
 */
require_once __DIR__ . '/session_check.php';

$current_script = basename($_SERVER['SCRIPT_NAME']);
$isAdminSection = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;

function getLinkClass($pageName, $currentScript, $isAdminSec = false, $checkAdmin = false) {
    $isActive = false;
    if ($checkAdmin && $isAdminSec && $currentScript === $pageName) {
        $isActive = true;
    } elseif (!$checkAdmin && !$isAdminSec && $currentScript === $pageName) {
        $isActive = true;
    }
    return $isActive 
        ? 'bg-secondary-container text-on-secondary-container font-bold scale-95 shadow-xs' 
        : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface';
}
?>
<aside class="w-[240px] h-screen fixed left-0 top-0 bg-surface-container-lowest border-r border-surface-variant flex flex-col py-lg px-md z-50">
    <!-- Brand / Logo -->
    <div class="mb-xl px-sm">
        <h1 class="font-display-md text-display-md font-bold text-primary tracking-tight">Social Hub</h1>
        <p class="font-body-md text-body-md text-on-surface-variant opacity-70">Manage your social media</p>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-grow space-y-1 overflow-y-auto pr-xs">
        <!-- Analytics -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('analytics.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/analytics.php">
            <span class="material-symbols-outlined text-xl">insights</span>
            <span class="font-body-md text-body-md">Analytics</span>
        </a>

        <!-- Dashboard -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('dashboard_home.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/dashboard_home.php">
            <span class="material-symbols-outlined text-xl">dashboard</span>
            <span class="font-body-md text-body-md">Dashboard</span>
        </a>

        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('connections.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/connections.php">
            <span class="material-symbols-outlined text-xl">sync_alt</span>
            <span class="font-body-md text-body-md">Accounts</span>
        </a>

        <!-- Create Post -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('composer.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php">
            <span class="material-symbols-outlined text-xl">edit_note</span>
            <span class="font-body-md text-body-md">Create Post</span>
        </a>

        <!-- Calendar -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('calendar.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/calendar.php">
            <span class="material-symbols-outlined text-xl">calendar_month</span>
            <span class="font-body-md text-body-md">Calendar</span>
        </a>

        <!-- My Posts -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('post_history.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php">
            <span class="material-symbols-outlined text-xl">history</span>
            <span class="font-body-md text-body-md">My Posts</span>
        </a>

        <!-- Messages -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('inbox.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/inbox.php">
            <span class="material-symbols-outlined text-xl">inbox</span>
            <span class="font-body-md text-body-md">Messages</span>
        </a>

        <!-- Settings -->
        <a class="flex items-center gap-3 px-sm py-2.5 rounded-lg transition-all duration-200 <?php echo getLinkClass('settings.php', $current_script); ?>" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/pages/settings.php">
            <span class="material-symbols-outlined text-xl">settings</span>
            <span class="font-body-md text-body-md">Settings</span>
        </a>

        <!-- Admin-only Links -->
        <?php if ($user_role === 'staff' || $user_role === 'admin'): ?>
            <div class="pt-sm my-sm border-t border-surface-variant">
                <span class="font-data-label text-data-label text-on-surface-variant/70 block px-sm py-xs uppercase tracking-wider text-[11px]">Admin</span>
            </div>

            <!-- Client Accounts -->
            <a class="flex items-center gap-3 px-sm py-2 rounded-lg transition-all duration-200 <?php echo getLinkClass('clients_overview.php', $current_script, $isAdminSection, true); ?>" 
               href="<?php echo DASHBOARD_BASE_URL; ?>/admin/clients_overview.php">
                <span class="material-symbols-outlined text-xl">group</span>
                <span class="font-body-md text-body-md">Client Accounts</span>
            </a>

            <!-- System Status -->
            <a class="flex items-center gap-3 px-sm py-2 rounded-lg transition-all duration-200 <?php echo getLinkClass('system_health.php', $current_script, $isAdminSection, true); ?>" 
               href="<?php echo DASHBOARD_BASE_URL; ?>/admin/system_health.php">
                <span class="material-symbols-outlined text-xl">health_metrics</span>
                <span class="font-body-md text-body-md">System Status</span>
            </a>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Bottom Footer (User Identity & Log Out) -->
    <div class="mt-auto border-t border-surface-variant/50 pt-md space-y-md">
        <div class="flex flex-col px-sm gap-xs">
            <span class="font-body-md font-bold text-on-surface">
                <?php echo $user_role === 'admin' ? 'Administrator' : ($user_role === 'staff' ? 'Staff' : 'Client'); ?>
            </span>
            <span class="font-data-label text-data-label text-on-surface-variant/70 overflow-hidden text-ellipsis whitespace-nowrap max-w-[200px]">
                Account #<?php echo $user_id; ?>
            </span>
        </div>
        <a class="w-full flex items-center justify-center gap-sm bg-primary text-on-primary py-sm rounded-lg font-bold hover:opacity-90 transition-all active:scale-95" 
           href="<?php echo DASHBOARD_BASE_URL; ?>/auth/logout.php">
            <span class="material-symbols-outlined" data-icon="logout">logout</span>
            <span>Log Out</span>
        </a>
    </div>
</aside>

<script>
// Background cron simulation for local dev
(function() {
    if (window.addEventListener) {
        window.addEventListener('load', function() {
            fetch('<?php echo DASHBOARD_BASE_URL; ?>/pages/cron_trigger.php')
                .catch(function(err) {
                    console.warn('Cron trigger failed:', err);
                });
        });
    }
})();
</script>
