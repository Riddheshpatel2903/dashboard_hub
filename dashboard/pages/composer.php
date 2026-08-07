<?php
/** Create Post Composer (Tailwind & Stitch Design System). */
require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/hub_client.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// Fetch connected platform accounts from the Hub
$connectedPlatforms = [];
$hubRes = hubGetConnectionsStatus($client_id);
if (!empty($hubRes['success']) && is_array($hubRes['connections'])) {
    $allowedPostingPlatforms = ['facebook', 'instagram', 'youtube', 'linkedin', 'google_business'];
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected' && in_array($conn['platform'], $allowedPostingPlatforms, true)) {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}
$connectedPlatforms = array_unique($connectedPlatforms);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>New Post | Social Hub</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content -->
    <main class="ml-[240px] pt-16 p-lg min-h-screen">
        <div class="max-w-[1440px] mx-auto space-y-lg">
            <!-- Page Header -->
            <div>
                <h2 class="font-display-lg text-display-lg text-on-surface">Create Post</h2>
                <p class="font-body-md text-on-surface-variant">Draft, schedule, and publish posts to your connected channels.</p>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-12 gap-gutter">
                <!-- Left Column: Form Inputs -->
                <form id="composer-form" class="col-span-12 lg:col-span-7 bg-surface-container-lowest border border-surface-variant rounded-xl p-lg space-y-lg shadow-sm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" id="schedule-type" name="schedule_type" value="now">

                    <!-- Post Type Selection -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block">Post Type</label>
                        <div class="flex flex-wrap gap-sm">
                            <label class="cursor-pointer">
                                <input class="sr-only peer" type="radio" name="post_type" value="image" checked />
                                <div class="flex items-center gap-xs px-md py-sm rounded-full border border-surface-variant bg-surface-container-low transition-all peer-checked:bg-primary peer-checked:text-on-primary peer-checked:border-primary">
                                    <span class="material-symbols-outlined text-[18px]">image</span>
                                    <span class="font-body-md font-semibold">Image Post</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input class="sr-only peer" type="radio" name="post_type" value="video" />
                                <div class="flex items-center gap-xs px-md py-sm rounded-full border border-surface-variant bg-surface-container-low transition-all peer-checked:bg-primary peer-checked:text-on-primary peer-checked:border-primary">
                                    <span class="material-symbols-outlined text-[18px]">videocam</span>
                                    <span class="font-body-md font-semibold">Reel Posting</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Platform Selection -->
                    <div class="space-y-sm">
                        <div class="flex items-center justify-between mb-xs">
                            <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block">Select Platforms</label>
                            <?php if (!empty($connectedPlatforms)): ?>
                                <button type="button" id="btn-select-all" class="text-xs text-primary font-bold hover:underline">Select All</button>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($connectedPlatforms)): ?>
                            <div class="bg-error-container text-on-error-container p-md rounded-lg border border-error/20 text-body-sm">
                                ⚠️ No active connections found. Please <a href="connections.php" class="underline font-bold">link your channels</a> first.
                            </div>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-sm">
                                <?php
                                foreach ($connectedPlatforms as $plat):
                                    $platIcon = 'face';
                                    $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#1D4ED8] peer-checked:text-[#1D4ED8]';
                                    $allowedTypes = 'image,video';
                                    if ($plat === 'facebook') {
                                        $platIcon = 'public';
                                        $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#1877F2] peer-checked:text-[#1877F2]';
                                    } elseif ($plat === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platColor = 'peer-checked:bg-[#FDF2F8] peer-checked:border-[#E1306C] peer-checked:text-[#E1306C]';
                                    } elseif ($plat === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platColor = 'peer-checked:bg-[#FEF2F2] peer-checked:border-[#FF0000] peer-checked:text-[#FF0000]';
                                        $allowedTypes = 'video';
                                    } elseif ($plat === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platColor = 'peer-checked:bg-[#F0FDF4] peer-checked:border-[#25D366] peer-checked:text-[#25D366]';
                                    } elseif ($plat === 'linkedin') {
                                        $platIcon = 'work';
                                        $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#0A66C2] peer-checked:text-[#0A66C2]';
                                        $allowedTypes = 'image';
                                    } elseif ($plat === 'google_business') {
                                        $platIcon = 'store';
                                        $platColor = 'peer-checked:bg-[#EEF2FF] peer-checked:border-[#4285F4] peer-checked:text-[#4285F4]';
                                    } elseif ($plat === 'search_console') {
                                        $platIcon = 'search';
                                        $platColor = 'peer-checked:bg-[#EEF2FF] peer-checked:border-[#4285F4] peer-checked:text-[#4285F4]';
                                    } elseif ($plat === 'blog' || $plat === 'website') {
                                        $platIcon = 'rss_feed';
                                        $platColor = 'peer-checked:bg-primary/10 peer-checked:border-primary peer-checked:text-primary';
                                    }
                                    ?>
                                    <label class="platform-checkbox-label cursor-pointer" id="label-platform-<?php echo $plat; ?>" data-allowed-types="<?php echo htmlspecialchars($allowedTypes); ?>">
                                        <input class="hidden peer" type="checkbox" name="platforms[]" value="<?php echo htmlspecialchars($plat); ?>" id="platform-<?php echo $plat === 'instagram' ? 'ig' : $plat; ?>" checked />
                                        <div class="flex items-center gap-sm px-md py-sm rounded-lg border border-surface-variant bg-surface-container-low transition-all <?php echo $platColor; ?>">
                                            <?php
                                            $customIcon = getBrandIconUrl($plat);
                                            if ($customIcon !== ''):
                                                ?>
                                                <img src="<?php echo $customIcon; ?>" class="w-5 h-5 object-contain" alt="<?php echo htmlspecialchars($plat); ?>">
                                            <?php else: ?>
                                                <span class="material-symbols-outlined text-[20px]"><?php echo $platIcon; ?></span>
                                            <?php endif; ?>
                                            <span class="font-body-md font-semibold capitalize"><?php echo htmlspecialchars($plat === 'google_business' ? 'Google Business Profile' : $plat); ?></span>
                                            <?php if ($plat === 'youtube'): ?>
                                                <span class="text-[10px] bg-red-100 text-red-700 font-bold px-1.5 py-0.5 rounded border border-red-200 uppercase ml-1 yt-tag">(Video Only)</span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- YouTube title group -->
                    <div id="youtube-title-group" class="hidden space-y-sm animate-in fade-in slide-in-from-top-2">
                        <div class="flex justify-between items-center">
                            <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="title">YouTube Video Title</label>
                            <span id="title-char-count" class="text-[10px] text-on-surface-variant">0 / 100 characters</span>
                        </div>
                        <input type="text" id="title" name="title" placeholder="Enter video title" 
                               class="w-full bg-surface-container-low border border-surface-variant rounded-lg px-md py-sm font-body-md focus:ring-2 focus:ring-primary-container focus:border-primary focus:outline-none transition-all" value="New Dashboard Upload" maxlength="100" />
                        <div id="yt-info" class="text-xs text-on-surface-variant opacity-75">YouTube video dispatch requires an attachment and title.</div>
                    </div>

                    <!-- Content Textarea -->
                    <div class="space-y-sm">
                        <div class="flex justify-between items-center">
                            <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="content">Post Content</label>
                            <span id="content-char-count" class="text-[10px] text-on-surface-variant">0 / 2200 characters</span>
                        </div>
                        <div class="relative">
                            <textarea class="w-full bg-surface-container-low border border-surface-variant rounded-lg p-md font-body-md focus:ring-2 focus:ring-primary-container focus:border-primary focus:outline-none transition-all resize-none" 
                                      id="content" name="content" placeholder="What would you like to share? Use @mentions and #hashtags..." rows="6"></textarea>
                        </div>
                    </div>

                    <!-- Keyword Entry -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="keywords">Keywords</label>
                        <input type="text" id="keywords" name="keywords" placeholder="Enter keywords separated by commas (e.g. fitness, diet, motivation)" 
                               class="w-full bg-surface-container-low border border-surface-variant rounded-lg px-md py-sm font-body-md focus:ring-2 focus:ring-primary-container focus:border-primary focus:outline-none transition-all" />
                        <p class="text-[10px] text-on-surface-variant/75">Keywords will be automatically formatted as hashtags at the end of the description.</p>
                    </div>

                    <!-- Media Upload -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="media">Media Assets</label>
                        <div class="border-2 border-dashed border-outline-variant rounded-xl p-lg flex flex-col items-center justify-center bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group relative">
                            <div class="w-12 h-12 bg-primary-container/10 rounded-full flex items-center justify-center mb-md group-hover:scale-110 transition-transform pointer-events-none">
                                <span class="material-symbols-outlined text-primary">cloud_upload</span>
                            </div>
                            <p class="font-body-lg font-bold pointer-events-none">Click to browse or drag file here</p>
                            <p class="font-body-sm text-on-surface-variant mt-xs pointer-events-none">Images (Max 8MB) or Videos (Max 70MB)</p>
                            <input type="file" id="media" name="media" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept="image/*" />
                        </div>
                        <div id="file-error" class="hidden text-error text-body-sm font-semibold mt-xs"></div>
                    </div>

                    <!-- Scheduling Toggle -->
                    <div class="pt-lg border-t border-surface-variant space-y-md">
                        <div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="toggle-schedule" name="toggle_schedule" class="sr-only peer">
                                <div class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                <span class="ml-3 font-body-md font-semibold text-on-surface">Schedule for later release</span>
                            </label>
                        </div>
                        
                        <div id="schedule-container" class="hidden animate-in fade-in slide-in-from-top-4">
                            <div class="grid grid-cols-3 gap-sm bg-surface-container-low p-md rounded-lg border border-surface-variant">
                                <div class="space-y-xs">
                                    <label class="text-[10px] font-bold text-on-surface-variant uppercase block">Release Date</label>
                                    <input type="date" id="sched-date" class="w-full h-10 px-md bg-surface-container-lowest border border-surface-variant rounded-lg text-xs focus:ring-1 focus:ring-primary focus:outline-none" />
                                </div>
                                <div class="space-y-xs">
                                    <label class="text-[10px] font-bold text-on-surface-variant uppercase block">Hour</label>
                                    <select id="sched-hour" class="w-full h-10 px-md bg-surface-container-lowest border border-surface-variant rounded-lg text-xs focus:ring-1 focus:ring-primary focus:outline-none">
                                        <?php for ($h = 0; $h < 24; $h++): ?>
                                            <option value="<?php echo sprintf('%02d', $h); ?>"><?php echo sprintf('%02d', $h); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="space-y-xs">
                                    <label class="text-[10px] font-bold text-on-surface-variant uppercase block">Minute (5-min Interval)</label>
                                    <select id="sched-minute" class="w-full h-10 px-md bg-surface-container-lowest border border-surface-variant rounded-lg text-xs focus:ring-1 focus:ring-primary focus:outline-none">
                                        <?php for ($m = 0; $m < 60; $m += 5): ?>
                                            <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" id="scheduled-at" name="scheduled_at" />
                        </div>
                    </div>

                    <!-- Uneditable warning notice -->
                    <div class="mt-md bg-amber-50 text-amber-800 p-md rounded-lg flex items-start gap-sm border border-amber-200 text-xs">
                        <span class="material-symbols-outlined text-sm">info</span>
                        <div>
                            <strong>Important Notice:</strong> Once this post is published or scheduled, it cannot be edited. Please review your media and caption carefully before submitting.
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-md pt-lg border-t border-surface-variant">
                        <a href="dashboard_home.php" class="px-lg py-md text-on-surface-variant font-semibold hover:bg-surface-container-high rounded-lg transition-colors">Cancel</a>
                        <button id="btn-publish" type="submit" class="px-xl py-md bg-primary text-on-primary font-bold rounded-lg shadow-lg hover:brightness-110 active:scale-95 transition-all">
                            🚀 Publish Post
                        </button>
                    </div>
                </form>

                <!-- Right Column: Preview Column -->
                <div class="col-span-12 lg:col-span-5 space-y-md">
                    <div class="flex justify-between items-center px-md">
                        <h3 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">Live Preview</h3>
                    </div>

                    <!-- Platform Tab Selector Bar -->
                    <div id="preview-tab-bar" class="flex flex-wrap gap-xs justify-center bg-surface-container-low p-xs rounded-lg border border-surface-variant hidden">
                        <!-- Dynamic buttons will render here -->
                    </div>

                    <!-- Mobile Preview Container -->
                    <div class="bg-[#E2E8F0] p-md rounded-[3rem] border-[8px] border-[#1E293B] aspect-[9/18.5] max-w-[340px] mx-auto shadow-2xl relative overflow-hidden">
                        <!-- Phone Notch -->
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-24 h-6 bg-[#1E293B] rounded-b-2xl z-20"></div>
                        <!-- Screen Content -->
                        <div id="phone-screen-content" class="h-full bg-white rounded-[2rem] overflow-y-auto no-scrollbar pt-8">
                            <div class="h-full bg-white flex flex-col items-center justify-center text-gray-400 text-xs">
                                <span class="material-symbols-outlined text-4xl">mobile_screen_share</span>
                                <span class="mt-2">Select a platform to preview</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- Full-screen Loading Overlay -->
    <div id="publishing-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-xs flex flex-col items-center justify-center z-[200] hidden">
        <div class="bg-surface-container-lowest rounded-2xl p-xl max-w-sm w-full mx-md flex flex-col items-center justify-center space-y-md border border-surface-variant shadow-2xl text-center">
            <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
            <div>
                <h3 class="font-display-md text-display-md font-bold text-on-surface">Publishing Post</h3>
                <p class="font-body-md text-on-surface-variant mt-xs">Connecting to social platforms. Please wait...</p>
            </div>
        </div>
    </div>

    <script src="<?php echo DASHBOARD_BASE_URL; ?>/assets/js/composer.js"></script>
</body>
</html>
