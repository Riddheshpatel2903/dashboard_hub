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

$activeTab = $_GET['tab'] ?? 'whatsapp';

// Initialize data containers
$whatsappThreads = [];
$comments = [];
$reviews = [];

// 1. Fetch WhatsApp message list from the Hub
if ($activeTab === 'whatsapp') {
    $hubRes = hubGetInbox($client_id, 'whatsapp', 'messages');
    $inboundMessages = [];
    if (!empty($hubRes['success']) && is_array($hubRes['data'])) {
        $inboundMessages = $hubRes['data'];
    }

    // Fetch outbound replies we sent to display in the conversation timeline
    $stmt = $pdo->prepare("
        SELECT content as message_text, 'text' as message_type, published_at as timestamp, 'outbound' as direction
        FROM posts_cache
        WHERE client_id = :client_id AND platform = 'whatsapp' AND status = 'published'
        ORDER BY published_at ASC
    ");
    $stmt->execute(['client_id' => $client_id]);
    $outboundMessages = $stmt->fetchAll() ?: [];

    // Group inbound messages by sender phone number and mix in outbound replies
    foreach ($inboundMessages as $msg) {
        $sender = $msg['sender_number'];
        $msg['direction'] = 'inbound';
        $whatsappThreads[$sender]['messages'][] = $msg;
    }

    foreach ($whatsappThreads as $sender => &$thread) {
        foreach ($outboundMessages as $out) {
            $thread['messages'][] = $out;
        }
        // Sort conversation chronologically
        usort($thread['messages'], function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        
        // Resolve last inbound message time to check 24h window
        $lastInboundTime = null;
        foreach (array_reverse($thread['messages']) as $m) {
            if ($m['direction'] === 'inbound') {
                $lastInboundTime = $m['timestamp'];
                break;
            }
        }
        $thread['last_inbound_time'] = $lastInboundTime;
        $thread['session_open'] = false;
        if ($lastInboundTime) {
            $elapsed = time() - strtotime($lastInboundTime);
            $thread['session_open'] = ($elapsed <= 86400); // 24 hours
            $thread['session_time_left'] = round((86400 - $elapsed) / 3600, 1);
        }
    }
    unset($thread);
}

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
            <a href="?tab=whatsapp" class="px-md py-sm font-semibold rounded-lg text-xs tracking-wide uppercase transition-all <?php echo $activeTab === 'whatsapp' ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?>">WhatsApp Conversations</a>
            <a href="?tab=comments" class="px-md py-sm font-semibold rounded-lg text-xs tracking-wide uppercase transition-all <?php echo $activeTab === 'comments' ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?>">Post Feedback Comments</a>
            <a href="?tab=reviews" class="px-md py-sm font-semibold rounded-lg text-xs tracking-wide uppercase transition-all <?php echo $activeTab === 'reviews' ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container-high'; ?>">Google Reviews</a>
        </div>

        <!-- Tab Content Layout -->
        <div class="flex-1 p-lg max-w-[1440px] w-full mx-auto">
            <?php if ($activeTab === 'whatsapp'): ?>
                <?php if (empty($whatsappThreads)): ?>
                    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-xl text-center text-on-surface-variant">
                        No WhatsApp customer conversations found in cache or on Hub.
                    </div>
                <?php else: ?>
                    <!-- WhatsApp Chat Layout -->
                    <div class="flex h-[calc(100vh-14rem)] border border-surface-variant rounded-xl overflow-hidden shadow-sm bg-surface-container-lowest">
                        <!-- Sidebar Contacts -->
                        <div class="w-80 border-r border-surface-variant bg-surface-container-low overflow-y-auto divide-y divide-surface-variant/40 flex-shrink-0">
                            <div class="p-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider bg-surface-container-lowest border-b border-surface-variant">
                                Threads List (<?php echo count($whatsappThreads); ?>)
                            </div>
                            <?php 
                            $first = true;
                            foreach ($whatsappThreads as $number => $thread): 
                                $lastMsg = end($thread['messages']);
                                $excerpt = htmlspecialchars(mb_strimwidth($lastMsg['message_text'], 0, 40, '...'));
                                $timeStr = date('H:i', strtotime($lastMsg['timestamp']));
                            ?>
                                <div class="p-md cursor-pointer hover:bg-surface-container-high transition-colors thread-item <?php echo $first ? 'active active-thread-bar font-bold' : ''; ?>" 
                                     data-number="<?php echo $number; ?>"
                                     onclick="switchActiveThread('<?php echo $number; ?>')">
                                    <div class="flex justify-between items-start mb-xs">
                                        <span class="font-bold text-on-surface text-sm flex items-center gap-xs">
                                            <span class="material-symbols-outlined text-[#25D366] text-sm" style="font-variation-settings: 'FILL' 1;">chat</span>
                                            <?php echo htmlspecialchars($number); ?>
                                        </span>
                                        <span class="text-[10px] font-data-label text-on-surface-variant"><?php echo $timeStr; ?></span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant truncate"><?php echo $excerpt; ?></p>
                                </div>
                            <?php 
                                $first = false;
                            endforeach; 
                            ?>
                        </div>

                        <!-- Conversation Window -->
                        <div class="flex-grow flex flex-col justify-between bg-surface-container-lowest">
                            <?php 
                            $first = true;
                            foreach ($whatsappThreads as $number => $thread): 
                            ?>
                                <div id="chat-<?php echo $number; ?>" class="thread-chat-view flex-col flex-1 h-full" style="display: <?php echo $first ? 'flex' : 'none'; ?>;">
                                    <!-- Header -->
                                    <div class="px-md py-sm border-b border-surface-variant bg-surface-container-low flex justify-between items-center">
                                        <div>
                                            <h3 class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($number); ?></h3>
                                            <p class="text-xs text-on-surface-variant">WhatsApp Contact Chat</p>
                                        </div>
                                        <?php if ($thread['session_open']): ?>
                                            <span class="bg-[#E4F6EE] text-[#1F9D6B] text-[10px] font-bold px-sm py-[2px] rounded-full border border-green-200 flex items-center gap-xs">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#1F9D6B] animate-pulse"></span>
                                                Reply window open (<?php echo $thread['session_time_left']; ?>h left)
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-sm py-[2px] rounded-full border border-amber-200 flex items-center gap-xs">
                                                <span class="material-symbols-outlined text-[12px]">lock</span>
                                                Closed window (Requires template)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Chat Timeline -->
                                    <div class="flex-grow overflow-y-auto p-lg space-y-md bg-surface-container-low/20 chat-timeline flex flex-col" id="timeline-<?php echo $number; ?>">
                                        <?php foreach ($thread['messages'] as $msg): 
                                            $direction = $msg['direction'];
                                            $msgText = $msg['message_text'];
                                            $timeStr = date('H:i', strtotime($msg['timestamp']));
                                            
                                            if ($direction === 'inbound') {
                                                $bubbleClass = 'bg-surface-container-lowest border border-surface-variant text-on-surface rounded-tl-none self-start';
                                                $metaClass = 'text-left';
                                            } else {
                                                $bubbleClass = 'bg-primary text-on-primary rounded-tr-none self-end';
                                                $metaClass = 'text-right';
                                            }
                                        ?>
                                            <div class="max-w-[70%] flex flex-col <?php echo ($direction === 'inbound') ? 'self-start' : 'self-end'; ?>">
                                                <div class="p-md rounded-xl shadow-sm <?php echo $bubbleClass; ?>">
                                                    <p class="text-sm leading-relaxed"><?php echo htmlspecialchars($msgText); ?></p>
                                                </div>
                                                <span class="text-[9px] font-data-label text-on-surface-variant mt-1 px-xs <?php echo $metaClass; ?>"><?php echo $timeStr; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Reply Footer / Form -->
                                    <div class="p-md border-t border-surface-variant bg-surface-container-low flex flex-col gap-sm">
                                        <div class="flex gap-md items-end">
                                            <textarea id="input-<?php echo $number; ?>" class="flex-grow h-12 p-md bg-surface-container-lowest border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary resize-none" placeholder="Type your WhatsApp message reply..."></textarea>
                                            <button onclick="submitChatReply('<?php echo $number; ?>')" class="h-12 px-lg bg-primary text-on-primary rounded-lg font-bold hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-xs text-xs">
                                                <span>Send Reply</span>
                                                <span class="material-symbols-outlined text-[16px]">send</span>
                                            </button>
                                        </div>

                                        <!-- Templates selection -->
                                        <div class="flex flex-wrap justify-between items-center text-xs pt-xs border-t border-surface-variant/40">
                                            <div class="flex items-center gap-xs text-on-surface-variant">
                                                <label for="template-<?php echo $number; ?>" class="font-bold">Template Reply:</label>
                                                <select id="template-<?php echo $number; ?>" class="h-8 px-xs bg-surface-container-lowest border border-surface-variant rounded focus:outline-none text-[11px]">
                                                    <option value="">-- Choose WhatsApp Template --</option>
                                                    <option value="welcome_message">welcome_message (Standard Greeting)</option>
                                                    <option value="appointment_update">appointment_update (Appointment status notification)</option>
                                                    <option value="feedback_survey">feedback_survey (Customer satisfaction survey)</option>
                                                </select>
                                            </div>
                                            <span class="text-[10px] text-on-surface-variant opacity-75">Send templates to connect past the 24-hour customer window.</span>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                $first = false;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

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
