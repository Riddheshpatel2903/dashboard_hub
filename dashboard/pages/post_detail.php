<?php
/**
 * Post Detail & CRUD Proxy Handler (Tailwind & Stitch Design System).
 * Deployed at: /dashboard/pages/post_detail.php
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

// Check if request is POST (Delete action)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $action = $input['action'] ?? '';
    $hubPostId = isset($input['hub_post_id']) ? (int)$input['hub_post_id'] : 0;
    $platform = $input['platform'] ?? null;
    $externalPostId = $input['external_post_id'] ?? null;
    $mediaPath = $input['media_path'] ?? '';
    
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing action.']);
        exit();
    }
    
    try {
        if ($action === 'delete') {
            // Delete post on Hub
            $res = hubDelete($client_id, $hubPostId, $platform, $externalPostId);
            if (empty($res['success'])) {
                throw new Exception("Platform Deletion Failure: " . ($res['error'] ?? 'Unknown error'));
            }
            
            // Delete media file from all upload folders if exists
            if (!empty($mediaPath) && !preg_match('/^https?:\/\//i', $mediaPath)) {
                require_once __DIR__ . '/../../hub/storage/StorageService.php';
                StorageService::deletePostMedia($mediaPath, $client_id);
            }
            
            $msg = 'Post and associated media deleted successfully.';
            if (!empty($res['warning'])) {
                $msg .= ' Note: ' . $res['warning'];
            }
            echo json_encode(['success' => true, 'message' => $msg]);
            exit();
        } else {
            throw new Exception("Invalid action.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// GET Request: Render details view (for modal inclusion)
$hubPostId = isset($_GET['hub_post_id']) ? (int)$_GET['hub_post_id'] : 0;
$platform = $_GET['platform'] ?? '';
$externalPostId = $_GET['external_post_id'] ?? '';

$post = null;

// 1. Try to load local post (scheduled/failed/queued) if hubPostId > 0
if ($hubPostId > 0) {
    $localRes = hubGetLocalPostDetails($client_id, $hubPostId);
    if (!empty($localRes['success']) && !empty($localRes['post'])) {
        $post = $localRes['post'];
    }
}

// 2. If not a local post, it must be a published live post. Search live posts.
if (!$post && !empty($platform) && !empty($externalPostId)) {
    $allLivePosts = loadPlatformPosts($client_id);
    foreach ($allLivePosts as $p) {
        if ($p['platform'] === $platform && $p['external_post_id'] === $externalPostId) {
            $post = $p;
            break;
        }
    }
}

if (!$post) {
    echo '<p class="text-error text-center font-bold">Post not found or access denied.</p>';
    exit();
}

$status = $post['status'];
$platform = $post['platform'];
$hubPostId = $post['hub_post_id'] ?: 0;
$externalPostId = $post['external_post_id'];

// Fetch live metrics from the Hub if post is published
$metrics = [];
if ($status === 'published' && $platform !== 'google_business') {
    if (isset($post['views_count'])) {
        $metrics[] = ['metric_name' => 'views', 'value' => $post['views_count']];
    }
    if (isset($post['likes_count'])) {
        $metrics[] = ['metric_name' => 'likes', 'value' => $post['likes_count']];
    }
    if (isset($post['comments_count'])) {
        $metrics[] = ['metric_name' => 'comments', 'value' => $post['comments_count']];
    }
}

// Resolve Platform badge details
$platIcon = 'face';
$platColorClass = 'bg-surface-container text-on-surface-variant';
if ($platform === 'facebook') {
    $platIcon = 'public';
    $platColorClass = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
} elseif ($platform === 'instagram') {
    $platIcon = 'photo_camera';
    $platColorClass = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
} elseif ($platform === 'youtube') {
    $platIcon = 'play_circle';
    $platColorClass = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
} elseif ($platform === 'linkedin') {
    $platIcon = 'work';
    $platColorClass = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
} elseif ($platform === 'google_business') {
    $platIcon = 'store';
    $platColorClass = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
}

$statusClass = 'bg-surface-container text-on-surface-variant border border-outline-variant/30';
if ($status === 'published') {
    $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B] border border-green-200';
} elseif ($status === 'scheduled') {
    $statusClass = 'bg-primary-container/20 text-primary border border-primary-fixed';
} elseif ($status === 'failed') {
    $statusClass = 'bg-error-container text-error border border-error/20';
}
?>

<!-- Render Mockup Post Detail Layout -->
<div class="space-y-lg text-on-surface">
    <!-- Header Badge Info -->
    <div class="flex justify-between items-center border-b border-surface-variant pb-md">
        <div class="flex items-center gap-xs px-md py-sm rounded-lg text-xs font-bold uppercase tracking-tight <?php echo $platColorClass; ?>">
            <span class="material-symbols-outlined !text-[14px]"><?php echo $platIcon; ?></span>
            <span class="capitalize"><?php echo htmlspecialchars($platform === 'google_business' ? 'Google Profile' : $platform); ?></span>
        </div>
        <span class="px-sm py-[2px] rounded-full text-[10px] font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
            <?php echo strtoupper($status); ?>
        </span>
    </div>

    <!-- Media Attachment Preview -->
    <?php if (!empty($post['media_path'])): 
        // Build media path
        if (preg_match('/^https?:\/\//i', $post['media_path'])) {
            $mediaUrl = $post['media_path'];
        } else {
            $mediaUrl = HUB_BASE_URL . '/uploads/' . ltrim($post['media_path'], '/');
        }
        $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi']);
    ?>
        <div class="bg-surface-container border border-surface-variant rounded-xl overflow-hidden max-h-[300px] flex items-center justify-center shadow-xs">
            <?php if ($isVideo): ?>
                <video src="<?php echo htmlspecialchars($mediaUrl); ?>" controls class="max-w-full max-h-[300px]"></video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($mediaUrl); ?>" alt="Attachment" class="max-w-full max-h-[300px] object-contain">
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Caption / Text Body -->
    <div class="space-y-xs">
        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block">Content / Caption</label>
        <div id="detail-caption-display" class="bg-surface-container-low border border-surface-variant p-md rounded-lg text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($post['content']); ?></div>
    </div>

    <!-- Live Performance Metrics from Hub -->
    <?php if (!empty($metrics)): ?>
        <div class="bg-surface-container-low border border-surface-variant p-md rounded-xl space-y-sm">
            <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">Performance Analytics (Live)</h4>
            <div class="grid grid-cols-2 gap-sm">
                <?php foreach ($metrics as $metric): ?>
                    <div class="bg-surface-container-lowest border border-surface-variant/40 p-sm rounded-lg flex justify-between items-center text-xs shadow-xs">
                        <span class="text-on-surface-variant capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $metric['metric_name'])); ?>:</span>
                        <strong class="text-primary font-bold"><?php echo htmlspecialchars($metric['value']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Metadata Details -->
    <div class="text-[11px] font-data-label text-on-surface-variant grid grid-cols-2 gap-md border-t border-surface-variant pt-md leading-relaxed">
        <div><strong>Hub Post ID:</strong> #<?php echo $hubPostId; ?></div>
        <div><strong>External ID:</strong> <?php echo htmlspecialchars($post['external_post_id'] ?? 'n/a'); ?></div>
        <div><strong>Created At:</strong> <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
        <div>
            <strong><?php echo $status === 'published' ? 'Published' : 'Scheduled'; ?> At:</strong> 
            <?php echo $status === 'published' ? date('Y-m-d H:i', strtotime($post['published_at'])) : (!empty($post['scheduled_at']) ? date('Y-m-d H:i', strtotime($post['scheduled_at'])) : 'n/a'); ?>
        </div>
    </div>

    <!-- Actions Row -->
    <div class="flex justify-between items-center border-t border-surface-variant pt-md">
        <div></div>
        
        <?php if ($status !== 'deleted'): ?>
            <button class="px-md py-sm bg-error-container text-error hover:opacity-90 font-bold rounded-lg text-xs transition-all flex items-center gap-xs" 
                    id="btn-delete-post" 
                    data-hub-id="<?php echo $hubPostId; ?>"
                    data-platform="<?php echo htmlspecialchars($platform); ?>"
                    data-external-id="<?php echo htmlspecialchars($externalPostId); ?>"
                    data-media-path="<?php echo htmlspecialchars($post['media_path'] ?? ''); ?>">
                <span class="material-symbols-outlined text-sm">delete</span>
                <span>Delete Post</span>
            </button>
        <?php else: ?>
            <span class="text-on-surface-variant font-bold italic text-xs">This post was deleted.</span>
        <?php endif; ?>
    </div>
</div>
