<?php
/**
 * AJAX endpoint for recent dashboard activity.
 * Returns rendered table rows from local DB.
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    echo '<tr><td colspan="5" class="px-lg py-md text-center text-error">Session expired</td></tr>';
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
        <td colspan="5" class="px-lg py-md text-center text-error font-body-md">
            ⚠️ Failed to load recent posts: <?php echo htmlspecialchars($recentPostsError); ?>
        </td>
    </tr>
<?php elseif (empty($recentPosts)): ?>
    <tr>
        <td colspan="5" class="px-lg py-md text-center text-on-surface-variant font-body-md">
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
            <td class="px-lg py-md">
                <div class="flex items-center gap-xs">
                    <div class="w-8 h-8 rounded-full <?php echo $platformBg; ?> flex items-center justify-center text-white shadow-xs">
                        <span class="material-symbols-outlined text-sm"><?php echo $platformIcon; ?></span>
                    </div>
                    <span class="font-bold text-xs uppercase tracking-tight text-on-surface-variant ml-xs"><?php echo htmlspecialchars($post['platform']); ?></span>
                </div>
            </td>
            <td class="px-lg py-md text-on-surface font-body-md truncate max-w-xs">
                <?php echo htmlspecialchars($post['content']); ?>
            </td>
            <td class="px-lg py-md font-data-metric text-data-metric text-on-surface-variant">
                <?php echo htmlspecialchars($releaseTime); ?>
            </td>
            <td class="px-lg py-md">
                <span class="px-sm py-1 rounded-full text-xs font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($post['status']); ?>
                </span>
            </td>
            <td class="px-lg py-md text-right">
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="text-primary hover:underline font-bold text-xs">Inspect</a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
