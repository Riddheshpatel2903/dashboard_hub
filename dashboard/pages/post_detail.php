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
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    
    if ($postId <= 0 || empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing post_id or action.']);
        exit();
    }
    
    try {
        // Resolve local cache post to Hub post ID and verify ownership
        $stmt = $pdo->prepare("
            SELECT hub_post_id, platform 
            FROM posts_cache 
            WHERE id = :post_id AND client_id = :client_id 
            LIMIT 1
        ");
        $stmt->execute([
            'post_id'   => $postId,
            'client_id' => $client_id
        ]);
        $post = $stmt->fetch();
        
        if (!$post) {
            throw new Exception("Post not found or unauthorized.");
        }
        
        $hubPostId = (int)$post['hub_post_id'];
        $platform = $post['platform'];
        
        if ($action === 'delete') {
            if ($hubPostId > 0) {
                // Delete post on Hub
                $res = hubDelete($client_id, $hubPostId);
                if (empty($res['success'])) {
                    throw new Exception($res['error'] ?? 'Hub failed to delete post.');
                }
            }
            
            // Mark as deleted locally
            $stmt = $pdo->prepare("UPDATE posts_cache SET status = 'deleted' WHERE id = :post_id");
            $stmt->execute(['post_id' => $postId]);
            
            echo json_encode(['success' => true, 'message' => 'Post deleted successfully.']);
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
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($postId <= 0) {
    echo '<p class="text-error text-center font-bold">Invalid Post ID.</p>';
    exit();
}

// Fetch cached post metadata
$stmt = $pdo->prepare("
    SELECT id, hub_post_id, content, status, platform, media_path, scheduled_at, published_at, external_post_id, created_at
    FROM posts_cache 
    WHERE id = :post_id AND client_id = :client_id
    LIMIT 1
");
$stmt->execute([
    'post_id'   => $postId,
    'client_id' => $client_id
]);
$post = $stmt->fetch();

if (!$post) {
    echo '<p class="text-error text-center font-bold">Post not found or access denied.</p>';
    exit();
}

$hubPostId = (int)$post['hub_post_id'];
$platform = $post['platform'];
$status = $post['status'];

// Fetch live metrics from the Hub if post is published and has an external post ID
$metrics = [];
if ($status === 'published' && !empty($post['external_post_id'])) {
    $analyticsRes = hubGetAnalytics($client_id, $platform, $hubPostId);
    if (!empty($analyticsRes['success'])) {
        if (!empty($analyticsRes['metrics'])) {
            $metrics = $analyticsRes['metrics'];
        }
    } else {
        $err = $analyticsRes['error'] ?? '';
        
        // Check if the error indicates that the post was deleted on the platform
        $isDeletedOnPlatform = false;
        $deletedPhrases = [
            'does not exist',
            'do not exist',
            'unsupported get request',
            'invalid object',
            'not found',
            'object identifier'
        ];
        foreach ($deletedPhrases as $phrase) {
            if (stripos($err, $phrase) !== false) {
                $isDeletedOnPlatform = true;
                break;
            }
        }
        
        if ($isDeletedOnPlatform) {
            // Mark as deleted locally
            $stmt = $pdo->prepare("UPDATE posts_cache SET status = 'deleted' WHERE id = :post_id");
            $stmt->execute(['post_id' => $postId]);
            
            // Also notify the Hub to mark it deleted
            hubDelete($client_id, $hubPostId);
            
            echo '<div class="p-lg text-center space-y-md text-on-surface">
                <span class="material-symbols-outlined text-4xl text-error">warning</span>
                <p class="font-bold text-sm">This post was deleted manually on the social media platform.</p>
                <p class="text-xs text-on-surface-variant">It has been removed from your dashboard database.</p>
                <button class="px-md py-sm bg-surface-container hover:bg-surface-container-high rounded-lg font-bold text-xs" onclick="window.location.reload();">Close</button>
            </div>';
            exit();
        }
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
} elseif ($platform === 'whatsapp') {
    $platIcon = 'chat';
    $platColorClass = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
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
    <?php if ($post['media_path']): 
        // Build media path
        $mediaUrl = HUB_BASE_URL . '/uploads/' . ltrim($post['media_path'], '/');
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
        <div><strong>Post ID:</strong> #<?php echo $post['id']; ?> (Hub #<?php echo $hubPostId; ?>)</div>
        <div><strong>External ID:</strong> <?php echo htmlspecialchars($post['external_post_id'] ?? 'n/a'); ?></div>
        <div><strong>Created At:</strong> <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
        <div>
            <strong><?php echo $status === 'published' ? 'Published' : 'Scheduled'; ?> At:</strong> 
            <?php echo $status === 'published' ? date('Y-m-d H:i', strtotime($post['published_at'])) : ($post['scheduled_at'] ? date('Y-m-d H:i', strtotime($post['scheduled_at'])) : 'n/a'); ?>
        </div>
    </div>

    <!-- Actions Row -->
    <div class="flex justify-between items-center border-t border-surface-variant pt-md">
        <div></div>
        
        <?php if ($status !== 'deleted'): ?>
            <button class="px-md py-sm bg-error-container text-error hover:opacity-90 font-bold rounded-lg text-xs transition-all flex items-center gap-xs" id="btn-delete-post" data-id="<?php echo $post['id']; ?>">
                <span class="material-symbols-outlined text-sm">delete</span>
                <span>Delete Post</span>
            </button>
        <?php else: ?>
            <span class="text-on-surface-variant font-bold italic text-xs">This post was deleted.</span>
        <?php endif; ?>
    </div>
</div>
