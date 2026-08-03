<?php

/**
 * Performance Analytics Command Center (Stitch Social Mission Control Design System).
 * Simplified Overview, Channel Portal Filtering, Detailed Content Ledger & Post Drill-down Inspector.
 * Optimized with a complete AJAX content loading system for instant feel.
 */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// Fetch connected platform accounts to build UI skeletons
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

$selectedHubPostId = isset($_GET['hub_post_id']) ? (int) $_GET['hub_post_id'] : 0;
$selectedPlatform = $_GET['platform_inspect'] ?? '';
$selectedExternalPostId = $_GET['external_post_id'] ?? '';

if ($selectedHubPostId > 0 || (!empty($selectedPlatform) && !empty($selectedExternalPostId))) {
    $activeTab = 'inspect';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Analytics Command Center | Stitch Social Mission Control</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <style>
        .sparkline-container svg {
            filter: drop-shadow(0 2px 4px rgba(66, 82, 199, 0.1));
        }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md antialiased overflow-x-hidden">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content Wrapper (100% Width Canvas) -->
    <main class="ml-[240px] pt-16 flex flex-col min-h-screen">
        <div class="p-lg space-y-xl w-full">
            
            <!-- Page Title Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface font-bold">Analytics Command Center</h1>
                    <p class="font-body-md text-on-surface-variant">Real-time performance analytics, views, reach, and detailed post inspection.</p>
                </div>
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                   class="px-lg h-10 bg-primary text-on-primary rounded-lg font-bold flex items-center gap-xs hover:opacity-90 transition-all shadow-sm active:scale-95">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    <span>New Post</span>
                </a>
            </div>

            <?php if (!empty($GLOBALS['platform_errors'])): ?>
                <?php foreach ($GLOBALS['platform_errors'] as $plat => $err): ?>
                    <div class="bg-red-50 border border-red-200 text-red-800 p-md rounded-xl shadow-xs flex items-start gap-md">
                        <span class="material-symbols-outlined text-red-600 text-xl flex-shrink-0">warning</span>
                        <div class="space-y-1">
                            <h4 class="font-bold text-sm capitalize"><?php echo htmlspecialchars($plat === 'google_business' ? 'Google Business Profile' : $plat); ?> Integration Alert</h4>
                            <p class="text-xs text-red-700">
                                <?php
                                if (strpos($err, 'pages_read_engagement') !== false || strpos($err, 'permission') !== false || strpos($err, 'OAuth') !== false || strpos($err, 'Code: 10') !== false) {
                                    echo 'The Facebook connection is missing the required permissions (e.g., <code>pages_read_engagement</code>). Please disconnect and reconnect your Facebook account in settings, making sure to approve all requested Page permission checkboxes.';
                                } else {
                                    echo htmlspecialchars($err);
                                }
                                ?>
                            </p>
                            <div class="pt-1">
                                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/connections.php" class="text-xs font-bold text-primary hover:underline">Go to Connections &rarr;</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Global Channel Filter Pills & Date Selector Bar -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-surface-container-lowest border border-surface-variant p-md rounded-xl shadow-sm gap-md">
                <div class="flex flex-wrap items-center gap-md">
                    <span class="font-data-label text-data-label text-on-surface-variant uppercase font-bold text-xs">PORTAL FILTER:</span>
                    <div class="flex flex-wrap items-center gap-2" id="platform-filters-container">
                        <button data-platform="" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo empty($platform) ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            All Channels
                        </button>
                        <button data-platform="instagram" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo $platform === 'instagram' ? 'bg-[#cc2366] text-white shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            Instagram
                        </button>
                        <button data-platform="facebook" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo $platform === 'facebook' ? 'bg-[#1877F2] text-white shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            Facebook
                        </button>
                        <button data-platform="youtube" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo $platform === 'youtube' ? 'bg-[#FF0000] text-white shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            YouTube
                        </button>
                        <button data-platform="linkedin" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo $platform === 'linkedin' ? 'bg-[#0077B5] text-white shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            LinkedIn
                        </button>
                        <button data-platform="google_business" 
                                class="btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all <?php echo $platform === 'google_business' ? 'bg-[#4285F4] text-white shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                            Google Business Profile
                        </button>
                    </div>
                </div>
                
                <!-- Date Selector & Sync Bar -->
                <div class="flex items-center gap-sm">
                    <!-- Sync Button -->
                    <button id="btn-sync-analytics" 
                            class="flex items-center gap-1.5 border border-primary/20 rounded-lg px-md py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-on-primary font-bold text-xs shadow-xs transition-all active:scale-95 group">
                        <span id="sync-icon" class="material-symbols-outlined text-sm group-hover:animate-spin">sync</span>
                        <span id="sync-label">Sync Now</span>
                    </button>

                    <!-- Interactive Premium Date Picker -->
                    <div class="flex items-center gap-xs bg-surface-container-low border border-surface-variant rounded-lg px-sm py-1 shadow-xs">
                        <span class="material-symbols-outlined text-sm text-primary">calendar_today</span>
                        <input type="date" id="start-date" value="<?php echo htmlspecialchars($startDate); ?>" class="bg-transparent border-0 text-xs font-bold text-on-surface focus:ring-0 p-0 w-24">
                        <span class="text-xs text-on-surface-variant font-bold font-data-label">&rarr;</span>
                        <input type="date" id="end-date" value="<?php echo htmlspecialchars($endDate); ?>" class="bg-transparent border-0 text-xs font-bold text-on-surface focus:ring-0 p-0 w-24">
                    </div>
                </div>
            </div>

            <!-- Area where KPI Channel Badges are injected dynamically -->
            <div id="analytics-channel-kpi-area"></div>

            <!-- Navigation Tabs Bar -->
            <div class="flex items-center gap-2 border-b border-surface-variant pb-xs" id="analytics-tabs-container">
                <button data-tab="overview" 
                        class="btn-tab-link px-md py-2 rounded-lg font-bold text-body-sm flex items-center gap-xs transition-all <?php echo $activeTab === 'overview' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                    <span class="material-symbols-outlined text-sm">insights</span>
                    <span>Overview</span>
                </button>
                <button data-tab="content" 
                        class="btn-tab-link px-md py-2 rounded-lg font-bold text-body-sm flex items-center gap-xs transition-all <?php echo $activeTab === 'content' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                    <span class="material-symbols-outlined text-sm">movie</span>
                    <span>Content performance table</span>
                </button>
                <button data-tab="gbp" id="tab-btn-gbp"
                        class="btn-tab-link px-md py-2 rounded-lg font-bold text-body-sm flex items-center gap-xs transition-all <?php echo $activeTab === 'gbp' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                    <span class="material-symbols-outlined text-sm">store</span>
                    <span>Google Business Profile</span>
                </button>
                <button data-tab="inspect" id="tab-btn-inspect" 
                        class="btn-tab-link px-md py-2 rounded-lg font-bold text-body-sm flex items-center gap-xs transition-all bg-primary-container/30 text-primary border border-primary/20 <?php echo $activeTab === 'inspect' ? '' : 'hidden'; ?>">
                    <span class="material-symbols-outlined text-sm">analytics</span>
                    <span id="inspect-tab-label">Inspecting Post</span>
                </button>
            </div>

            <!-- AJAX Loaded Content Container -->
            <div id="analytics-tab-content-area" class="w-full relative min-h-[400px]">
                <!-- Beautiful loading skeleton or spinner -->
                <div id="analytics-loader" class="absolute inset-0 bg-background/50 flex items-center justify-center z-10 hidden">
                    <div class="flex flex-col items-center gap-sm bg-surface-container-lowest border border-surface-variant px-xl py-lg rounded-xl shadow-lg">
                        <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">this might take few minutes please wait...</p>
                    </div>
                </div>
                
                <div id="analytics-dynamic-content" class="w-full">
                    <div class="py-xl text-center text-on-surface-variant">
                        <div class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block mr-2"></div>
                        this might take few minutes please wait...
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let currentPlatform = "<?php echo htmlspecialchars($platform); ?>";
        let currentTab = "<?php echo htmlspecialchars($activeTab); ?>";
        let selectedHubPostId = <?php echo $selectedHubPostId; ?>;
        let selectedPlatformInspect = "<?php echo htmlspecialchars($selectedPlatform); ?>";
        let selectedExternalPostId = "<?php echo htmlspecialchars($selectedExternalPostId); ?>";

        function updateURL() {
            const params = new URLSearchParams();
            if (currentPlatform) params.set('platform', currentPlatform);
            params.set('tab', currentTab);
            
            const startInput = document.getElementById('start-date');
            const endInput = document.getElementById('end-date');
            if (startInput && startInput.value) params.set('start_date', startInput.value);
            if (endInput && endInput.value) params.set('end_date', endInput.value);
            
            if (currentTab === 'inspect') {
                if (selectedHubPostId) params.set('hub_post_id', selectedHubPostId);
                if (selectedPlatformInspect) params.set('platform_inspect', selectedPlatformInspect);
                if (selectedExternalPostId) params.set('external_post_id', selectedExternalPostId);
            }
            
            history.replaceState({}, '', '?' + params.toString());
        }

        function updatePlatformFilterUI() {
            document.querySelectorAll('.btn-platform-filter').forEach(btn => {
                const plat = btn.getAttribute('data-platform');
                btn.className = 'btn-platform-filter px-md py-1.5 rounded-full text-xs font-bold transition-all';
                
                if (plat === currentPlatform) {
                    if (plat === '') btn.classList.add('bg-primary', 'text-on-primary', 'shadow-sm');
                    else if (plat === 'instagram') btn.classList.add('bg-[#cc2366]', 'text-white', 'shadow-sm');
                    else if (plat === 'facebook') btn.classList.add('bg-[#1877F2]', 'text-white', 'shadow-sm');
                    else if (plat === 'youtube') btn.classList.add('bg-[#FF0000]', 'text-white', 'shadow-sm');
                    else if (plat === 'linkedin') btn.classList.add('bg-[#0077B5]', 'text-white', 'shadow-sm');
                    else if (plat === 'google_business') btn.classList.add('bg-[#4285F4]', 'text-white', 'shadow-sm');
                } else {
                    btn.classList.add('bg-surface-container', 'text-on-surface-variant', 'hover:bg-surface-container-high');
                }
            });

            // Auto show/hide Google Business Profile tab button
            const gbpBtn = document.getElementById('tab-btn-gbp');
            if (gbpBtn) {
                if (currentPlatform === '' || currentPlatform === 'google_business') {
                    gbpBtn.classList.remove('hidden');
                } else {
                    gbpBtn.classList.add('hidden');
                    if (currentTab === 'gbp') {
                        currentTab = 'overview';
                    }
                }
            }
        }

        function updateTabButtonsUI() {
            document.querySelectorAll('.btn-tab-link').forEach(btn => {
                const tab = btn.getAttribute('data-tab');
                if (tab === 'inspect') return; // handled separately
                
                btn.className = 'btn-tab-link px-md py-2 rounded-lg font-bold text-body-sm flex items-center gap-xs transition-all';
                if (tab === currentTab) {
                    btn.classList.add('bg-primary', 'text-on-primary', 'shadow-sm');
                } else {
                    btn.classList.add('bg-surface-container', 'text-on-surface-variant', 'hover:bg-surface-container-high');
                }
            });

            const inspectBtn = document.getElementById('tab-btn-inspect');
            if (inspectBtn) {
                if (currentTab === 'inspect') {
                    inspectBtn.classList.remove('hidden');
                    inspectBtn.classList.add('bg-primary', 'text-on-primary', 'shadow-sm');
                } else {
                    inspectBtn.classList.add('hidden');
                }
            }
        }

        function loadAnalyticsContent(forceSync = false) {
            const loader = document.getElementById('analytics-loader');
            const contentArea = document.getElementById('analytics-dynamic-content');
            const kpiArea = document.getElementById('analytics-channel-kpi-area');
            const syncIcon = document.getElementById('sync-icon');
            const syncLabel = document.getElementById('sync-label');

            if (loader) loader.classList.remove('hidden');
            if (forceSync) {
                if (syncIcon) syncIcon.classList.add('animate-spin');
                if (syncLabel) syncLabel.textContent = 'Syncing...';
            }

            const params = new URLSearchParams();
            if (currentPlatform) params.set('platform', currentPlatform);
            params.set('tab', currentTab);
            
            const startInput = document.getElementById('start-date');
            const endInput = document.getElementById('end-date');
            if (startInput && startInput.value) params.set('start_date', startInput.value);
            if (endInput && endInput.value) params.set('end_date', endInput.value);
            
            if (currentTab === 'inspect') {
                if (selectedHubPostId) params.set('hub_post_id', selectedHubPostId);
                if (selectedPlatformInspect) params.set('platform_inspect', selectedPlatformInspect);
                if (selectedExternalPostId) params.set('external_post_id', selectedExternalPostId);
            }

            if (forceSync) {
                params.set('force_sync', '1');
            }

            fetch('ajax_analytics_content.php?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(html => {
                // Parse out the channel KPI bar if present, and insert it at the top level
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const channelBar = doc.getElementById('channel-kpi-subbar');
                
                if (kpiArea) {
                    if (channelBar) {
                        kpiArea.innerHTML = channelBar.outerHTML;
                        channelBar.remove(); // remove from the fragment body
                    } else {
                        kpiArea.innerHTML = '';
                    }
                }

                // Inject the remaining body content
                if (contentArea) {
                    contentArea.innerHTML = doc.body.innerHTML;
                    
                    // Re-run any chart setup scripts that were returned in the html fragment
                    const scripts = contentArea.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement('script');
                        newScript.text = oldScript.text;
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                }
            })
            .catch(err => {
                console.error(err);
                if (contentArea) {
                    contentArea.innerHTML = `
                        <div class="bg-error-container text-on-error-container p-lg border border-error/20 rounded-xl text-center space-y-sm">
                            <span class="material-symbols-outlined text-3xl">error</span>
                            <p class="font-bold text-sm">Failed to load analytics data.</p>
                            <p class="text-xs">Error: ${err.message}. Please check database connections and try again.</p>
                        </div>
                    `;
                }
            })
            .finally(() => {
                if (loader) loader.classList.add('hidden');
                if (syncIcon) syncIcon.classList.remove('animate-spin');
                if (syncLabel) syncLabel.textContent = 'Sync Now';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            updatePlatformFilterUI();
            updateTabButtonsUI();
            loadAnalyticsContent(true);
            
            // Handle platform filter pill clicks
            document.querySelectorAll('.btn-platform-filter').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentPlatform = btn.getAttribute('data-platform');
                    selectedHubPostId = 0;
                    selectedPlatformInspect = '';
                    selectedExternalPostId = '';
                    if (currentTab === 'inspect') {
                        currentTab = 'overview';
                    }
                    
                    updatePlatformFilterUI();
                    updateTabButtonsUI();
                    updateURL();
                    loadAnalyticsContent();
                });
            });

            // Handle tab link clicks
            document.querySelectorAll('.btn-tab-link').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentTab = btn.getAttribute('data-tab');
                    if (currentTab !== 'inspect') {
                        selectedHubPostId = 0;
                        selectedPlatformInspect = '';
                        selectedExternalPostId = '';
                    }
                    updateTabButtonsUI();
                    updateURL();
                    loadAnalyticsContent(true);
                });
            });

            // Handle date inputs change
            const startInput = document.getElementById('start-date');
            const endInput = document.getElementById('end-date');
            if (startInput && endInput) {
                [startInput, endInput].forEach(input => {
                    input.addEventListener('change', () => {
                        updateURL();
                        loadAnalyticsContent(true);
                    });
                });
            }

            // Handle Sync Now button click
            const syncBtn = document.getElementById('btn-sync-analytics');
            if (syncBtn) {
                syncBtn.addEventListener('click', () => {
                    loadAnalyticsContent(true);
                });
            }

            // Handle dynamic click events (event delegation) for ledger inspect links
            document.addEventListener('click', (e) => {
                const inspectBtn = e.target.closest('.btn-inspect-ledger-post');
                if (inspectBtn) {
                    e.preventDefault();
                    selectedHubPostId = parseInt(inspectBtn.getAttribute('data-hub-post-id')) || 0;
                    selectedPlatformInspect = inspectBtn.getAttribute('data-platform-inspect') || '';
                    selectedExternalPostId = inspectBtn.getAttribute('data-external-post-id') || '';
                    currentTab = 'inspect';
                    
                    updateTabButtonsUI();
                    updateURL();
                    loadAnalyticsContent(true);
                }

                const backBtn = e.target.closest('#btn-inspect-back-to-ledger');
                if (backBtn) {
                    e.preventDefault();
                    selectedHubPostId = 0;
                    selectedPlatformInspect = '';
                    selectedExternalPostId = '';
                    currentTab = 'content';
                    
                    updateTabButtonsUI();
                    updateURL();
                    loadAnalyticsContent(true);
                }
            });
        });
    </script>
</body>
</html>
