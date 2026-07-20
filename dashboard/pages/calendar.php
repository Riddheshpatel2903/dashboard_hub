<?php
/** Postings Calendar (Tailwind & Stitch Design System). */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// 1. Get Selected Month & Year (default to current month/year)
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Clamp values
if ($month < 1 || $month > 12)
    $month = (int) date('m');
if ($year < 2000 || $year > 2100)
    $year = (int) date('Y');

// Calculate calendar date grid
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$startDayOfWeek = date('w', $firstDayOfMonth);  // 0 (Sunday) to 6 (Saturday)

// Shift Sunday=0 to Monday=0 if desired, but let's keep Sunday as first column or Monday.
// Stitch header had MON TUE WED THU FRI SAT SUN.
// So Monday is column 0, Sunday is column 6.
// Let's adjust starting offset: Sunday (w=0) becomes 6, Monday (w=1) becomes 0, etc.
$startOffset = ($startDayOfWeek === 0) ? 6 : $startDayOfWeek - 1;

$calendarDays = [];

// Padding days from previous month
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$daysInPrevMonth = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
for ($i = $startOffset - 1; $i >= 0; $i--) {
    $d = $daysInPrevMonth - $i;
    $calendarDays[] = [
        'date' => sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $d),
        'day' => $d,
        'current_month' => false
    ];
}

// Days in current month
for ($d = 1; $d <= $daysInMonth; $d++) {
    $calendarDays[] = [
        'date' => sprintf('%04d-%02d-%02d', $year, $month, $d),
        'day' => $d,
        'current_month' => true
    ];
}

// Padding days from next month to fill grid (multiple of 7)
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
$remainingCells = 7 - (count($calendarDays) % 7);
if ($remainingCells < 7) {
    for ($d = 1; $d <= $remainingCells; $d++) {
        $calendarDays[] = [
            'date' => sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $d),
            'day' => $d,
            'current_month' => false
        ];
    }
}

$startDate = $calendarDays[0]['date'];
$endDate = end($calendarDays)['date'];

// 2. Query Cache for posts in this date range
$stmt = $pdo->prepare("
    SELECT id, hub_post_id, content, platform, status, scheduled_at, published_at, created_at 
    FROM posts_cache
    WHERE client_id = :client_id
      AND status != 'deleted'
      AND (
        (status = 'scheduled' AND DATE(scheduled_at) BETWEEN :start_sched AND :end_sched)
        OR (status = 'published' AND DATE(published_at) BETWEEN :start_pub AND :end_pub)
        OR (status = 'failed' AND DATE(created_at) BETWEEN :start_fail AND :end_fail)
      )
");
$stmt->execute([
    'client_id' => $client_id,
    'start_sched' => $startDate,
    'end_sched' => $endDate,
    'start_pub' => $startDate,
    'end_pub' => $endDate,
    'start_fail' => $startDate,
    'end_fail' => $endDate
]);
$postsList = $stmt->fetchAll();

// Group posts by release date
$postsByDate = [];
foreach ($postsList as $post) {
    $dateKey = '';
    if ($post['status'] === 'published' && $post['published_at']) {
        $dateKey = date('Y-m-d', strtotime($post['published_at']));
    } elseif ($post['status'] === 'scheduled' && $post['scheduled_at']) {
        $dateKey = date('Y-m-d', strtotime($post['scheduled_at']));
    } else {
        $dateKey = date('Y-m-d', strtotime($post['created_at']));
    }

    if (!empty($dateKey)) {
        $postsByDate[$dateKey][] = $post;
    }
}

// Format navigation links helper
function getNavLink($m, $y)
{
    return DASHBOARD_BASE_URL . "/pages/calendar.php?month={$m}&year={$y}";
}

$prevMonthNav = $month - 1;
$prevYearNav = $year;
if ($prevMonthNav < 1) {
    $prevMonthNav = 12;
    $prevYearNav--;
}

$nextMonthNav = $month + 1;
$nextYearNav = $year;
if ($nextMonthNav > 12) {
    $nextMonthNav = 1;
    $nextYearNav++;
}

$monthName = date('F Y', $firstDayOfMonth);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Post Calendar | Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
    <style>
        /* Calendar grid layout styling */
        .calendar-cells-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(120px, auto);
        }
    </style>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="p-lg">
            <!-- Calendar Navigation Header -->
            <div class="flex items-center justify-between mb-lg bg-surface-container-lowest p-md rounded-xl border border-surface-variant">
                <div class="flex items-center gap-md">
                    <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo $monthName; ?></h2>
                    <div class="flex border border-surface-variant rounded-lg overflow-hidden">
                        <a href="<?php echo getNavLink($prevMonthNav, $prevYearNav); ?>" class="p-xs hover:bg-surface-container-high border-r border-surface-variant text-on-surface-variant flex items-center"><span class="material-symbols-outlined">chevron_left</span></a>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/calendar.php" class="px-md py-xs font-body-sm hover:bg-surface-container-high border-r border-surface-variant text-on-surface flex items-center font-bold">Today</a>
                        <a href="<?php echo getNavLink($nextMonthNav, $nextYearNav); ?>" class="p-xs hover:bg-surface-container-high text-on-surface-variant flex items-center"><span class="material-symbols-outlined">chevron_right</span></a>
                    </div>
                </div>
                
                <div class="flex items-center gap-sm">
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                       class="flex items-center gap-xs px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90 transition-all font-body-sm font-bold shadow-sm active:scale-95">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>New Post</span>
                    </a>
                </div>
            </div>

            <!-- Calendar Grid Card -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl overflow-hidden shadow-sm">
                <!-- Days of week header -->
                <div class="grid grid-cols-7 border-b border-surface-variant bg-surface-container-low">
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">MON</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">TUE</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">WED</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">THU</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">FRI</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant border-r border-surface-variant">SAT</div>
                    <div class="py-sm text-center font-data-label text-data-label text-on-surface-variant">SUN</div>
                </div>

                <!-- Calendar Content Grid -->
                <div class="calendar-cells-grid divide-x divide-y divide-surface-variant">
                    <?php
                    $isFirstRow = true;
                    foreach ($calendarDays as $index => $dayInfo):
                        $dKey = $dayInfo['date'];
                        $dayPosts = $postsByDate[$dKey] ?? [];
                        $isCurrentMonth = $dayInfo['current_month'];
                        $isToday = ($dKey === date('Y-m-d'));

                        $cellClass = 'p-sm transition-colors hover:bg-surface-bright flex flex-col justify-between';
                        if (!$isCurrentMonth) {
                            $cellClass .= ' opacity-40 bg-surface-container-low';
                        }
                        if ($isToday) {
                            $cellClass .= ' bg-primary/5';
                        }
                        ?>
                        <div class="<?php echo $cellClass; ?>">
                            <div class="flex justify-between items-start mb-sm">
                                <span class="font-data-label text-data-label <?php echo $isToday ? 'text-primary font-bold' : 'text-on-surface'; ?>">
                                    <?php echo $dayInfo['day']; ?>
                                </span>
                                <?php if ($isToday): ?>
                                    <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Post Pins inside Cell -->
                            <div class="space-y-xs overflow-hidden flex-grow flex flex-col justify-end">
                                <?php
                                $renderedCount = 0;
                                foreach ($dayPosts as $post):
                                    if ($renderedCount >= 3):  // Max 3 pins, then overflow indicator
                                        $overflowCount = count($dayPosts) - 3;
                                        ?>
                                        <div class="overflow-pin text-[10px] font-bold text-on-surface-variant px-sm py-[2px] bg-surface-container-high rounded text-center cursor-pointer hover:bg-outline-variant/30" 
                                             data-date="<?php echo $dKey; ?>">
                                            +<?php echo $overflowCount; ?> more
                                        </div>
                                        <?php
                                        break;
                                    endif;

                                    // Resolve Platform Specific colors and icons
                                    $platIcon = 'face';
                                    $platformColorClass = 'bg-surface-container text-on-surface-variant border border-outline-variant/30';

                                    if ($post['platform'] === 'facebook') {
                                        $platIcon = 'public';
                                        $platformColorClass = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platformColorClass = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
                                    } elseif ($post['platform'] === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platformColorClass = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
                                    } elseif ($post['platform'] === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platformColorClass = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
                                    } elseif ($post['platform'] === 'linkedin') {
                                        $platIcon = 'work';
                                        $platformColorClass = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'google_business') {
                                        $platIcon = 'store';
                                        $platformColorClass = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
                                    }

                                    // Display status indicator inside pin text if not published
                                    $summary = htmlspecialchars(mb_strimwidth($post['content'], 0, 14, '...'));
                                    if ($post['status'] === 'failed') {
                                        $summary = '⚠️ ' . $summary;
                                    }
                                    ?>
                                    <div class="post-pin cursor-pointer flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold select-none truncate hover:opacity-80 transition-opacity <?php echo $platformColorClass; ?>" 
                                         data-id="<?php echo $post['id']; ?>" 
                                         title="<?php echo htmlspecialchars($post['platform'] . ' (' . $post['status'] . '): ' . $post['content']); ?>">
                                        <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                                        <span class="truncate"><?php echo $summary; ?></span>
                                    </div>
                                <?php
                                    $renderedCount++;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Post Detail Modal Overlay -->
    <div id="post-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" style="display: none;">
        <div class="bg-surface-container-lowest border border-surface-variant w-full max-w-[500px] rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-lg py-md border-b border-surface-variant flex justify-between items-center bg-surface-container-low">
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Today's Posts</h3>
                <button class="text-on-surface-variant hover:text-on-surface text-2xl font-bold" id="modal-close-btn">&times;</button>
            </div>
            <div class="p-lg overflow-y-auto max-h-[75vh]" id="modal-body-content">
                <!-- Dynamically loaded via AJAX -->
            </div>
        </div>
    </div>

    <script src="<?php echo DASHBOARD_BASE_URL; ?>/assets/js/calendar.js"></script>
</body>
</html>
