<?php
/**
 * Unified Social Inbox Page (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

$activeTab = $_GET['tab'] ?? 'comments';
$validTabs = ['comments', 'reviews'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'comments';
}

// Initialize data containers
$comments = [];
$reviews = [];

// 2. Fetch Comments (Facebook/Instagram/YouTube) from Hub
if ($activeTab === 'comments') {
    $stmt = $pdo->prepare("
        SELECT id, platform, content FROM posts_cache
        WHERE client_id = :client_id AND platform IN ('facebook', 'instagram', 'youtube') AND status = 'published'
        ORDER BY published_at DESC LIMIT 1
    ");
    $stmt->execute(['client_id' => $client_id]);
    $latestPost = $stmt->fetch();

    if ($latestPost) {
        $commRes = hubGetInbox($client_id, $latestPost['platform'], 'comments', $latestPost['id']);
        if (!empty($commRes['success'])) {
            $comments = $commRes['data']['data'] ?? $commRes['data']['items'] ?? [];
        }
    }
}

// 3. Fetch Reviews (Google Business Profile) from Hub
if ($activeTab === 'reviews') {
    $revRes = hubGetInbox($client_id, 'google_business', 'reviews');
    if (!empty($revRes['success']) && isset($revRes['data']['reviews'])) {
        $reviews = $revRes['data']['reviews'];
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Unified Social Inbox | Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <style>
        .active-thread-bar {
            border-left-width: 4px;
            border-left-color: #2031a9; /* primary */
            background-color: rgba(217, 223, 248, 0.4); /* secondary-container */
        }
    </style>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 min-h-screen flex flex-col">
        <!-- Tab Navigation Bar -->
        <div class="flex gap-sm border-b border-surface-variant px-lg py-sm bg-surface-container-lowest z-30">
                <a href="?tab=comments" class="px-md py-sm font-semibold rounded-lg text-xs tracking-wide uppercase transition-all <?php echo $activeTab === 'comments' ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?>">Platform post comments</a>
            <a href="?tab=reviews" class="px-md py-sm font-semibold rounded-lg text-xs tracking-wide uppercase transition-all <?php echo $activeTab === 'reviews' ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?>">Google Reviews</a>
        </div>

        <!-- Tab Content Layout -->
        <div class="flex-1 p-lg max-w-[1440px] w-full mx-auto">

            <?php if ($activeTab === 'comments'): ?>
                <?php if (empty($comments)): ?>
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-xl text-center text-on-surface-variant shadow-sm">
                        No feedback comments found on your latest published social posts.
                    </div>
                <?php else: ?>
                    <div class="space-y-md">
                        <div class="px-md py-sm font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">
                            Latest Comments Received
                        </div>
                        <div class="space-y-sm">
                            <?php foreach ($comments as $comment): 
                                $author = $comment['author_name'] ?? 'Social User';
                                $text = $comment['comment_text'] ?? '';
                                $time = isset($comment['timestamp']) ? date('Y-m-d H:i', strtotime($comment['timestamp'])) : 'n/a';
                            ?>
                                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex gap-md shadow-sm hover:border-primary transition-all duration-200">
                                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm flex-shrink-0">
                                        <?php echo strtoupper(substr($author, 0, 2)); ?>
                                    </div>
                                    <div class="space-y-xs flex-grow">
                                        <div class="flex justify-between items-baseline">
                                            <span class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($author); ?></span>
                                            <span class="text-[10px] font-data-label text-on-surface-variant"><?php echo $time; ?></span>
                                        </div>
                                        <p class="text-sm text-on-surface-variant leading-relaxed"><?php echo htmlspecialchars($text); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeTab === 'reviews'): ?>
                <?php if (empty($reviews)): ?>
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-xl text-center text-on-surface-variant shadow-sm">
                        No customer reviews found on Google Business Profile.
                    </div>
                <?php else: ?>
                    <div class="space-y-md">
                        <div class="px-md py-sm font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">
                            Business Profile Reviews
                        </div>
                        <div class="space-y-sm">
                            <?php foreach ($reviews as $review): 
                                $reviewer = $review['reviewer_name'] ?? 'Local Guide';
                                $rating = $review['rating'] ?? 5;
                                $comment = $review['comment'] ?? '';
                                $time = isset($review['created_at']) ? date('Y-m-d H:i', strtotime($review['created_at'])) : 'n/a';
                            ?>
                                <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md flex gap-md shadow-sm hover:border-primary transition-all duration-200">
                                    <div class="w-10 h-10 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500 text-sm flex-shrink-0">
                                        <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                                    </div>
                                    <div class="space-y-xs flex-grow">
                                        <div class="flex justify-between items-baseline">
                                            <span class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($reviewer); ?></span>
                                            <span class="text-[10px] font-data-label text-on-surface-variant"><?php echo $time; ?></span>
                                        </div>
                                        <div class="flex items-center gap-xs text-amber-500 my-1">
                                            <?php for ($i=0; $i<5; $i++): ?>
                                                <span class="material-symbols-outlined text-[14px] <?php echo $i < $rating ? 'text-amber-500' : 'text-gray-200'; ?>" style="font-variation-settings: 'FILL' <?php echo $i < $rating ? '1' : '0'; ?>;">star</span>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-sm text-on-surface-variant leading-relaxed"><?php echo htmlspecialchars($comment); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="<?php echo DASHBOARD_BASE_URL; ?>/assets/js/inbox.js"></script>
</body>
</html>
