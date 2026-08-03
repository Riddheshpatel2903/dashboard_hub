<?php
/** Postings History Table (Tailwind & Stitch Design System). */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

require_once __DIR__ . '/../includes/hub_client.php';
$hubRes = hubGetConnectionsStatus($client_id);

$platformFilter = $_GET['platform'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$limit = 9; // Grid has 3 columns, 9 posts per page looks better
$offset = ($page - 1) * $limit;

// Load all live posts dynamically
$forceSync = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);
$allLivePosts = [];
try {
    $allLivePosts = loadPlatformPosts($client_id, $forceSync);
} catch (Exception $e) {
    $postsError = $e->getMessage();
}

// Apply filters in PHP
$filteredPosts = [];
foreach ($allLivePosts as $post) {
    if ($post['platform'] === 'whatsapp') {
        continue;
    }
    if (!empty($platformFilter) && $post['platform'] !== $platformFilter) {
        continue;
    }
    if (!empty($dateFilter)) {
        $postDate = $post['published_at'] ?: ($post['scheduled_at'] ?: $post['created_at']);
        if (date('Y-m-d', strtotime($postDate)) !== $dateFilter) {
            continue;
        }
    }
    if (!empty($startDate) && !empty($endDate)) {
        $postDate = $post['published_at'] ?: ($post['scheduled_at'] ?: $post['created_at']);
        $postDay = date('Y-m-d', strtotime($postDate));
        if ($postDay < $startDate || $postDay > $endDate) {
            continue;
        }
    }
    $filteredPosts[] = $post;
}

$totalPosts = count($filteredPosts);
$totalPages = ceil($totalPosts / $limit);
if ($totalPages < 1) {
    $totalPages = 1;
}

$posts = array_slice($filteredPosts, $offset, $limit);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Post History | Command Center</title>
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
            <!-- Title -->
            <div class="flex items-center justify-between mb-lg">
                <div>
                    <h2 class="font-display-lg text-display-lg font-bold text-on-surface">Post History</h2>
                    <div class="flex items-center gap-xs text-on-surface-variant mt-1">
                        <span class="font-body-sm">Dashboard</span>
                        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                        <span class="font-body-sm font-bold text-primary">History</span>
                    </div>
                </div>
                <div class="flex items-center gap-md">
                    <?php
                        $maxSynced = getOverallLastSyncedTime($hubRes['connections'] ?? []);
                        $lastSyncedStr = getRelativeTimeString($maxSynced);
                    ?>
                    <span id="last-synced-label" class="text-xs text-on-surface-variant font-medium">Last synced: <?php echo $lastSyncedStr; ?></span>
                    
                    <button id="btn-refresh-posts" type="button"
                       onclick="triggerPostRefresh(this)"
                       class="flex items-center gap-sm px-md py-sm bg-surface-container hover:bg-surface-container-high text-on-surface-variant rounded-lg font-body-md font-bold transition-all active:scale-95">
                        <span class="material-symbols-outlined text-sm" id="refresh-icon">sync</span>
                        <span id="refresh-label">Refresh Data</span>
                    </button>
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                       class="flex items-center gap-sm px-md py-sm bg-primary text-on-primary rounded-lg font-body-md font-bold hover:opacity-90 transition-opacity">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Create Post</span>
                    </a>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm">
                <form method="GET" action="" class="flex flex-wrap items-end gap-md">
                    <!-- Platform Selector -->
                    <div class="flex-1 min-w-[200px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="platform">PLATFORM</label>
                        <select id="platform" name="platform" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Platforms</option>
                            <option value="facebook" <?php echo $platformFilter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platformFilter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="youtube" <?php echo $platformFilter === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                            <option value="linkedin" <?php echo $platformFilter === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="google_business" <?php echo $platformFilter === 'google_business' ? 'selected' : ''; ?>>Google Business</option>
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

                    <!-- Action Buttons -->
                    <div class="flex gap-sm">
                        <button type="submit" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-body-sm font-bold hover:opacity-90 transition-all flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">filter_list</span>
                            <span>Filter</span>
                        </button>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="h-10 px-lg bg-surface-container text-on-surface-variant rounded-lg font-body-sm font-bold hover:bg-surface-container-high transition-all flex items-center justify-center">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Card Grid & Pagination Section (AJAX target) -->
            <div id="posts-grid-section" class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden">
                <!-- Sync error banner (hidden by default, shown via JS) -->
                <div id="sync-error-banner" class="hidden px-md py-sm bg-error-container/20 border-b border-error/20 text-error text-xs font-semibold flex items-center gap-xs">
                    <span class="material-symbols-outlined text-sm">warning</span>
                    <span id="sync-error-msg"></span>
                </div>
                <?php if (!empty($postsError)): ?>
                    <div class="p-xl text-center text-error font-body-md space-y-sm">
                        <span class="material-symbols-outlined text-3xl">error</span>
                        <p class="font-bold">Failed to load posts from API</p>
                        <p class="text-xs text-on-surface-variant bg-error-container/20 p-sm rounded-lg border border-error/10 max-w-lg mx-auto"><?php echo htmlspecialchars($postsError); ?></p>
                    </div>
                <?php elseif (empty($posts)): ?>
                    <div class="p-xl text-center text-on-surface-variant font-body-md">
                        No matching posts found in history.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-gutter p-md">
                        <?php foreach ($posts as $post):
                            // Resolve Platform Icon/Color
                            $platIcon = 'face';
                            $platColorClass = 'bg-surface-container text-on-surface-variant';
                            $platLabel = $post['platform'];

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
                                $platLabel = 'Google Business Profile';
                            }

                            // Status style
                            $statusClass = 'bg-surface-container text-on-surface-variant border border-outline-variant/30';
                            if ($post['status'] === 'published') {
                                $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B] border border-green-200';
                            } elseif ($post['status'] === 'scheduled') {
                                $statusClass = 'bg-primary-container/20 text-primary border border-primary-fixed';
                            } elseif ($post['status'] === 'failed') {
                                $statusClass = 'bg-error-container text-error border border-error/20';
                            }

                            $targetTime = $post['status'] === 'published' ? $post['published_at'] : ($post['scheduled_at'] ?: $post['created_at']);

                            // Media thumbnail
                            $hasMedia = !empty($post['media_path']);
                            $mediaUrl = '';
                            $isVideo = false;
                            if ($hasMedia) {
                                if (preg_match('/^https?:\/\//i', $post['media_path'])) {
                                    $mediaUrl = $post['media_path'];
                                } else {
                                    $mediaUrl = HUB_BASE_URL . '/uploads/' . ltrim($post['media_path'], '/');
                                }
                                $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                                $isVideo = in_array($ext, ['mp4', 'mov', 'avi']);
                            }
                        ?>
                            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm hover:border-primary transition-all duration-200 flex flex-col justify-between space-y-md">
                                <div class="space-y-sm">
                                    <!-- Card Header -->
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold select-none border <?php echo $platColorClass; ?>">
                                            <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                                            <span class="capitalize"><?php echo htmlspecialchars($platLabel); ?></span>
                                        </div>
                                        <span class="px-sm py-0.5 rounded-full text-[9px] font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($post['status']); ?>
                                        </span>
                                    </div>

                                    <!-- Card Content (with media thumbnail side-by-side if exists) -->
                                    <div class="flex gap-sm items-start">
                                        <p class="text-xs text-on-surface line-clamp-4 flex-grow leading-relaxed text-left">
                                            <?php echo htmlspecialchars($post['content']); ?>
                                        </p>
                                        <?php if ($hasMedia): ?>
                                            <div class="w-16 h-16 rounded-lg bg-surface-container overflow-hidden flex-shrink-0 flex items-center justify-center border border-surface-variant relative">
                                                <?php if ($isVideo): ?>
                                                    <span class="material-symbols-outlined text-on-surface-variant text-xl">play_circle</span>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($mediaUrl); ?>" class="w-full h-full object-cover" />
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="pt-sm border-t border-surface-variant/60 flex justify-between items-center">
                                    <span class="text-[9px] text-on-surface-variant/60 uppercase font-data-label flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[11px]">calendar_today</span>
                                        <?php echo date('M d, H:i', strtotime($targetTime)); ?>
                                    </span>
                                    <button class="btn-view-detail px-sm h-8 bg-surface-container hover:bg-surface-container-high rounded text-on-surface-variant font-body-sm font-semibold transition-all inline-flex items-center gap-xs text-xs" 
                                            data-id="<?php echo $post['id']; ?>"
                                            data-hub-id="<?php echo $post['hub_post_id'] ?? ''; ?>"
                                            data-platform="<?php echo htmlspecialchars($post['platform']); ?>"
                                            data-external-id="<?php echo htmlspecialchars($post['external_post_id'] ?? ''); ?>">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                        <span>Inspect</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="px-lg py-md border-t border-surface-variant flex justify-between items-center bg-surface-container-low">
                        <span class="font-body-sm text-on-surface-variant">
                            Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo $totalPosts; ?> total items)
                        </span>
                        <div class="flex gap-sm">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&platform=<?php echo urlencode($platformFilter); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                                   class="h-8 px-md bg-surface-container-lowest border border-surface-variant text-on-surface-variant hover:bg-surface-container rounded font-body-sm font-bold flex items-center justify-center">&larr; Prev</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&platform=<?php echo urlencode($platformFilter); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                                   class="h-8 px-md bg-surface-container-lowest border border-surface-variant text-on-surface-variant hover:bg-surface-container rounded font-body-sm font-bold flex items-center justify-center">Next &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Pinned post details Modal container -->
    <div id="post-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" style="display: none;">
        <div class="bg-surface-container-lowest border border-surface-variant w-full max-w-[500px] rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-lg py-md border-b border-surface-variant flex justify-between items-center bg-surface-container-low">
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Today's Posts</h3>
                <button class="text-on-surface-variant hover:text-on-surface text-2xl font-bold" id="modal-close-btn">&times;</button>
            </div>
            <div class="p-lg overflow-y-auto max-h-[75vh]" id="modal-body-content">
                <p style="color:var(--text-secondary); text-align:center;">Loading details...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('post-modal');
            const modalBody = document.getElementById('modal-body-content');
            const closeBtn = document.getElementById('modal-close-btn');

            closeBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }
            });

            // E: Event delegation to handle view details clicks even on dynamically reloaded cards
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-view-detail');
                if (!btn) return;
                
                e.preventDefault();
                const id = btn.getAttribute('data-id');
                const hubId = btn.getAttribute('data-hub-id');
                const platform = btn.getAttribute('data-platform');
                const extId = btn.getAttribute('data-external-id');
                
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
                modalBody.innerHTML = '<p class="text-on-surface-variant text-center py-lg">Loading post details...</p>';
                
                const queryParams = new URLSearchParams({
                    post_id: id,
                    hub_post_id: hubId,
                    platform: platform,
                    external_post_id: extId
                }).toString();
                
                fetch(`post_detail.php?${queryParams}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(html => {
                    modalBody.innerHTML = html;
                    attachModalActionListeners();
                })
                .catch(err => {
                    console.error(err);
                    modalBody.innerHTML = '<p class="text-error text-center py-lg">Failed to retrieve details.</p>';
                });
            });

            function attachModalActionListeners() {
                const deleteBtn = modalBody.querySelector('#btn-delete-post');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function() {
                        const hubId = this.getAttribute('data-hub-id');
                        const platform = this.getAttribute('data-platform');
                        const extId = this.getAttribute('data-external-id');
                        const mediaPath = this.getAttribute('data-media-path');
                        
                        const confirmed = confirm("Are you sure you want to delete this post? This is irreversible.");
                        if (confirmed) {
                            deleteBtn.disabled = true;
                            deleteBtn.textContent = 'Deleting...';
                            
                            fetch('post_detail.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ 
                                    action: 'delete', 
                                    hub_post_id: hubId,
                                    platform: platform,
                                    external_post_id: extId,
                                    media_path: mediaPath
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    // E: Close details modal and update posts list via AJAX
                                    modal.classList.add('hidden');
                                    modal.style.display = 'none';
                                    reloadPostsGrid();
                                } else {
                                    alert('Failed: ' + data.error);
                                    deleteBtn.disabled = false;
                                    deleteBtn.textContent = 'Delete Post';
                                }
                            });
                        }
                    });
                }
            }
        });
    </script>
    <script>
    /**
     * E: Reusable AJAX posts grid reloader.
     */
    function reloadPostsGrid() {
        const grid  = document.getElementById('posts-grid-section');
        const errBanner = document.getElementById('sync-error-banner');
        const errMsg    = document.getElementById('sync-error-msg');
        const btn = document.getElementById('btn-refresh-posts');
        const icon  = document.getElementById('refresh-icon');
        const label = document.getElementById('refresh-label');

        if (btn) btn.disabled = true;
        if (icon)  { icon.classList.add('animate-spin'); }
        if (label) { label.textContent = 'Syncing...'; }
        if (errBanner) { errBanner.classList.add('hidden'); }

        const params = new URLSearchParams(window.location.search);

        return fetch('ajax_posts_refresh.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        })
        .then(html => {
            if (grid) {
                grid.innerHTML = html;
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const dataEl = doc.getElementById('posts-grid-data');
                if (dataEl) {
                    const lastSynced = dataEl.getAttribute('data-last-synced');
                    const syncLabel = document.getElementById('last-synced-label');
                    if (syncLabel) syncLabel.textContent = 'Last synced: ' + lastSynced;
                }
            }
        })
        .catch(err => {
            if (errBanner && errMsg) {
                errMsg.textContent = 'Refresh failed: ' + err.message + '. Your cached data is still shown.';
                errBanner.classList.remove('hidden');
            }
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon)  { icon.classList.remove('animate-spin'); }
            if (label) { label.textContent = 'Refresh Data'; }
        });
    }

    /**
     * E: Force sync triggers platform API fetch, then updates cache and swaps HTML grid.
     */
    function triggerPostRefresh(btn) {
        // Set force_sync=1 for this specific reload call
        const params = new URLSearchParams(window.location.search);
        params.set('force_sync', '1');
        
        const oldQuery = window.location.search;
        history.replaceState({}, '', '?' + params.toString());
        
        reloadPostsGrid().finally(() => {
            // Restore URL query back to original
            history.replaceState({}, '', oldQuery || '?');
        });
    }

    // Platform filter dropdown: auto-trigger AJAX refresh on change
    document.addEventListener('DOMContentLoaded', function() {
        const platformSelect = document.getElementById('platform');
        if (platformSelect) {
            platformSelect.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('platform', this.value);
                // Update URL without reload, then trigger refresh
                history.replaceState({}, '', '?' + params.toString());
                reloadPostsGrid();
            });
        }
    });
    </script>
</body>
</html>
