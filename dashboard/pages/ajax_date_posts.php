<?php
/**
 * AJAX endpoint to retrieve all posts for a specific date.
 */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require __DIR__ . '/../db/connection.php';

header('Content-Type: text/html; charset=utf-8');

if ($client_id === null) {
    echo '<p class="text-error text-center font-bold">No client selected.</p>';
    exit();
}

$date = $_GET['date'] ?? '';
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo '<p class="text-error text-center font-bold">Invalid date parameter.</p>';
    exit();
}

// Fetch all posts for this date
$stmt = $pdo->prepare("
    SELECT id, hub_post_id, content, platform, status, scheduled_at, published_at, created_at 
    FROM posts_cache
    WHERE client_id = :client_id
      AND status != 'deleted'
      AND (
        (status = 'scheduled' AND DATE(scheduled_at) = :d1)
        OR (status = 'published' AND DATE(published_at) = :d2)
        OR (status = 'failed' AND DATE(created_at) = :d3)
      )
    ORDER BY created_at DESC
");
$stmt->execute([
    'client_id' => $client_id,
    'd1'        => $date,
    'd2'        => $date,
    'd3'        => $date
]);
$posts = $stmt->fetchAll();

if (empty($posts)) {
    echo '<p class="text-on-surface-variant text-center py-md font-body-md">No posts scheduled or published on this day.</p>';
    exit();
}
?>

<div class="space-y-sm">
    <div class="border-b border-surface-variant pb-xs mb-sm">
        <h4 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">Posts on <?php echo date('l, M j, Y', strtotime($date)); ?></h4>
    </div>
    <div class="divide-y divide-surface-variant/40 max-h-[400px] overflow-y-auto pr-xs">
        <?php foreach ($posts as $post): 
            $platIcon = 'face';
            $platColor = 'bg-surface-container text-on-surface-variant';
            if ($post['platform'] === 'facebook') {
                $platIcon = 'public';
                $platColor = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
            } elseif ($post['platform'] === 'instagram') {
                $platIcon = 'photo_camera';
                $platColor = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
            } elseif ($post['platform'] === 'youtube') {
                $platIcon = 'play_circle';
                $platColor = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
            } elseif ($post['platform'] === 'whatsapp') {
                $platIcon = 'chat';
                $platColor = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
            } elseif ($post['platform'] === 'linkedin') {
                $platIcon = 'work';
                $platColor = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
            } elseif ($post['platform'] === 'google_business') {
                $platIcon = 'store';
                $platColor = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
            }

            $statusClass = 'bg-surface-container text-on-surface-variant border border-outline-variant/30';
            if ($post['status'] === 'published') {
                $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B] border border-green-200';
            } elseif ($post['status'] === 'scheduled') {
                $statusClass = 'bg-primary-container/20 text-primary border border-primary-fixed';
            } elseif ($post['status'] === 'failed') {
                $statusClass = 'bg-error-container text-error border border-error/20';
            }

            $time = $post['status'] === 'published' ? $post['published_at'] : ($post['scheduled_at'] ?: $post['created_at']);
        ?>
            <div class="modal-list-post-item p-sm hover:bg-secondary-container/10 transition-colors rounded-lg cursor-pointer flex items-center justify-between gap-md group" data-id="<?php echo $post['id']; ?>">
                <div class="flex items-center gap-sm min-w-0">
                    <div class="flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold select-none shrink-0 <?php echo $platColor; ?>">
                        <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                        <span class="capitalize"><?php echo htmlspecialchars($post['platform'] === 'google_business' ? 'Google' : $post['platform']); ?></span>
                    </div>
                    <span class="font-body-sm text-on-surface truncate group-hover:text-primary transition-colors"><?php echo htmlspecialchars($post['content']); ?></span>
                </div>
                <div class="flex items-center gap-sm shrink-0">
                    <span class="px-sm py-[2px] rounded-full text-[10px] font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($post['status']); ?>
                    </span>
                    <span class="text-[10px] font-data-label text-on-surface-variant">
                        <?php echo date('H:i', strtotime($time)); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
