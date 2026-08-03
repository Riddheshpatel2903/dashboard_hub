<?php
/**
 * AJAX Posts Refresh Endpoint.
 * Returns rendered HTML fragment of the posts grid for AJAX swap in post_history.php.
 * Endpoint: GET /dashboard/pages/ajax_posts_refresh.php
 */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

// Only allow XHR requests
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: post_history.php');
    exit();
}

if ($client_id === null) {
    http_response_code(403);
    echo '<div class="p-xl text-center text-error">Access denied.</div>';
    exit();
}

require_once __DIR__ . '/../includes/hub_client.php';
$hubRes = hubGetConnectionsStatus($client_id);

$platformFilter = $_GET['platform'] ?? '';
$dateFilter     = $_GET['date'] ?? '';
$startDate      = $_GET['start_date'] ?? '';
$endDate        = $_GET['end_date'] ?? '';
$page           = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit          = 9;
$offset         = ($page - 1) * $limit;

$forceSync   = isset($_GET['force_sync']) && in_array(strtolower($_GET['force_sync']), ['1', 'true', 'yes'], true);
$allLivePosts = [];
$postsError   = null;

try {
    $allLivePosts = loadPlatformPosts($client_id, $forceSync);
} catch (Exception $e) {
    $postsError = $e->getMessage();
}

$filteredPosts = [];
foreach ($allLivePosts as $post) {
    if ($post['platform'] === 'whatsapp') continue;
    if (!empty($platformFilter) && $post['platform'] !== $platformFilter) continue;
    if (!empty($dateFilter)) {
        $postDate = $post['published_at'] ?: ($post['scheduled_at'] ?: $post['created_at']);
        if (date('Y-m-d', strtotime($postDate)) !== $dateFilter) continue;
    }
    if (!empty($startDate) && !empty($endDate)) {
        $postDate = $post['published_at'] ?: ($post['scheduled_at'] ?: $post['created_at']);
        $postDay  = date('Y-m-d', strtotime($postDate));
        if ($postDay < $startDate || $postDay > $endDate) continue;
    }
    $filteredPosts[] = $post;
}

$totalPosts = count($filteredPosts);
$totalPages = max(1, ceil($totalPosts / $limit));
$posts      = array_slice($filteredPosts, $offset, $limit);

// Gather platform errors from global if set by loadPlatformPosts
$platformErrors = $GLOBALS['platform_errors'] ?? [];
?>
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
    <?php if (!empty($platformErrors)): ?>
        <div class="px-md py-sm bg-warning-container/20 border-b border-warning/20 text-warning text-xs font-semibold flex flex-wrap gap-sm items-center">
            <span class="material-symbols-outlined text-sm">warning</span>
            <span>Partial sync — some platforms could not be refreshed:</span>
            <?php foreach ($platformErrors as $plt => $err): ?>
                <span class="bg-surface-container px-xs py-0.5 rounded text-on-surface-variant"><?php echo htmlspecialchars(ucfirst($plt)); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
                    <!-- Interaction metrics -->
                    <div class="flex gap-sm text-[10px] font-bold text-on-surface-variant">
                        <?php if ($post['status'] === 'published'): ?>
                            <div class="flex items-center gap-0.5" title="Views"><span class="material-symbols-outlined text-[13px]">visibility</span> <?php echo number_format($post['views_count'] ?? 0); ?></div>
                            <div class="flex items-center gap-0.5" title="Likes"><span class="material-symbols-outlined text-[13px]">favorite</span> <?php echo number_format($post['likes_count'] ?? 0); ?></div>
                            <div class="flex items-center gap-0.5" title="Comments"><span class="material-symbols-outlined text-[13px]">chat</span> <?php echo number_format($post['comments_count'] ?? 0); ?></div>
                        <?php else: ?>
                            <span class="text-[9px] text-on-surface-variant/60 uppercase font-data-label flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[11px]">calendar_today</span>
                                <?php echo date('M d, H:i', strtotime($targetTime)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
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

    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between px-md py-sm border-t border-surface-variant">
        <span class="font-body-sm text-on-surface-variant">
            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $limit, $totalPosts); ?> of <?php echo $totalPosts; ?> posts
        </span>
        <div class="flex gap-xs">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="post_history.php?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
               class="h-8 w-8 flex items-center justify-center rounded-lg text-xs font-bold <?php echo $p === $page ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'; ?>">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
        $maxSynced = getOverallLastSyncedTime($hubRes['connections'] ?? []);
        $lastSyncedStr = getRelativeTimeString($maxSynced);
    ?>
    <div id="posts-grid-data" class="hidden" data-last-synced="<?php echo htmlspecialchars($lastSyncedStr); ?>"></div>
<?php endif; ?>
