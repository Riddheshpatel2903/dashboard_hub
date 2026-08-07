<?php
/**
 * AJAX endpoint for recent dashboard activity.
 * Returns rendered table rows from local DB.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    echo '<tr><td colspan="3" class="px-lg py-md text-center text-error">Session expired</td></tr>';
    exit();
}

$recentPosts = [];
$recentPostsError = null;

try {
    $allLivePosts = loadPlatformPosts($client_id);
    $filteredLivePosts = [];
    if (is_array($allLivePosts)) {
        foreach ($allLivePosts as $p) {
            $pStatus = strtolower($p['status'] ?? '');
            if ($pStatus === 'deleted' || $pStatus === 'failed') {
                continue;
            }
            $filteredLivePosts[] = $p;
        }
    }
    $recentPosts = array_slice($filteredLivePosts, 0, 5);
} catch (Exception $e) {
    $recentPostsError = $e->getMessage();
}

if (!empty($recentPostsError)): ?>
    <tr>
        <td colspan="3" class="px-lg py-md text-center text-error font-body-md">
            ⚠️ Failed to load recent posts: <?php echo htmlspecialchars($recentPostsError); ?>
        </td>
    </tr>
<?php elseif (empty($recentPosts)): ?>
    <tr>
        <td colspan="3" class="px-lg py-md text-center text-on-surface-variant font-body-md">
            No posts recorded in history yet. Start by creating a campaign post!
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($recentPosts as $post): 
        $platformIcon = 'face';
        $platformBg = 'bg-primary';
        if ($post['platform'] === 'facebook') {
            $platformIcon = 'public';
            $platformBg = 'bg-[#1877F2]';
        } elseif ($post['platform'] === 'instagram') {
            $platformIcon = 'photo_camera';
            $platformBg = 'bg-[#cc2366]';
        } elseif ($post['platform'] === 'youtube') {
            $platformIcon = 'play_circle';
            $platformBg = 'bg-[#FF0000]';
        } elseif ($post['platform'] === 'linkedin') {
            $platformIcon = 'work';
            $platformBg = 'bg-[#0077B5]';
        } elseif ($post['platform'] === 'google_business') {
            $platformIcon = 'store';
            $platformBg = 'bg-[#4285F4]';
        } elseif ($post['platform'] === 'search_console') {
            $platformIcon = 'search';
            $platformBg = 'bg-[#4285F4]';
        } elseif ($post['platform'] === 'blog' || $post['platform'] === 'website') {
            $platformIcon = 'rss_feed';
            $platformBg = 'bg-primary';
        }
        
        $statusClass = 'bg-surface-container text-on-surface-variant';
        if ($post['status'] === 'published') {
            $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B]';
        } elseif ($post['status'] === 'scheduled') {
            $statusClass = 'bg-primary-container/20 text-primary';
        } elseif ($post['status'] === 'failed') {
            $statusClass = 'bg-error-container text-error';
        }
        
        $releaseTime = 'n/a';
        if ($post['status'] === 'published' && $post['published_at']) {
            $releaseTime = date('M d, H:i', strtotime($post['published_at']));
        } elseif ($post['status'] === 'scheduled' && $post['scheduled_at']) {
            $releaseTime = date('M d, H:i', strtotime($post['scheduled_at']));
        } else {
            $releaseTime = date('M d, H:i', strtotime($post['created_at']));
        }
    ?>
        <tr class="hover:bg-secondary-container/10 transition-colors">
            <td class="py-sm px-xs">
                <div class="w-7 h-7 rounded-full bg-surface-container-low flex items-center justify-center border border-surface-variant/30 shadow-xs p-[2px]">
                    <?php 
                    $customIcon = getBrandIconUrl($post['platform']);
                    if ($customIcon !== ''): ?>
                        <img src="<?php echo $customIcon; ?>" class="w-5 h-5 object-contain" alt="<?php echo htmlspecialchars($post['platform']); ?>">
                    <?php else: ?>
                        <div class="w-full h-full rounded-full <?php echo $platformBg; ?> flex items-center justify-center text-white">
                            <span class="material-symbols-outlined text-xs"><?php echo $platformIcon; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <?php
            $content = trim($post['content'] ?? '');
            $words = preg_split('/\s+/', $content);
            $shortTitle = implode(' ', array_slice($words, 0, 3));
            if (count($words) > 3) {
                $shortTitle .= '...';
            }
            ?>
            <td class="py-sm px-xs text-on-surface font-bold text-xs truncate max-w-[160px]" title="<?php echo htmlspecialchars($content); ?>">
                <?php echo htmlspecialchars($shortTitle); ?>
            </td>
            <td class="py-sm px-xs font-data-metric text-[11px] text-on-surface-variant">
                <?php echo htmlspecialchars($releaseTime); ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
