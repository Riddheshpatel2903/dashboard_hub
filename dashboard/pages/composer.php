<?php
/**
 * Create Post Composer (Tailwind & Stitch Design System).
 */

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
    foreach ($hubRes['connections'] as $conn) {
        if ($conn['status'] === 'connected') {
            $connectedPlatforms[] = $conn['platform'];
        }
    }
}
$connectedPlatforms = array_unique($connectedPlatforms);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Post Composer | Command Center</title>
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
                <h2 class="font-display-lg text-display-lg text-on-surface">Post Composer</h2>
                <p class="font-body-md text-on-surface-variant">Draft, schedule, and publish posts to your connected channels.</p>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-12 gap-gutter">
                <!-- Left Column: Form Inputs -->
                <form id="composer-form" class="col-span-12 lg:col-span-7 bg-surface-container-lowest border border-surface-variant rounded-xl p-lg space-y-lg shadow-sm" enctype="multipart/form-data">
                    <input type="hidden" id="schedule-type" name="schedule_type" value="now">

                    <!-- Platform Selection -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block">Select Platforms</label>
                        <?php if (empty($connectedPlatforms)): ?>
                            <div class="bg-error-container text-on-error-container p-md rounded-lg border border-error/20 text-body-sm">
                                ⚠️ No active connections found. Please <a href="connections.php" class="underline font-bold">link your channels</a> first.
                            </div>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-sm">
                                <?php foreach ($connectedPlatforms as $plat): 
                                    $platIcon = 'face';
                                    $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#1D4ED8] peer-checked:text-[#1D4ED8]';
                                    if ($plat === 'facebook') {
                                        $platIcon = 'public';
                                        $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#1877F2] peer-checked:text-[#1877F2]';
                                    } elseif ($plat === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platColor = 'peer-checked:bg-[#FDF2F8] peer-checked:border-[#E1306C] peer-checked:text-[#E1306C]';
                                    } elseif ($plat === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platColor = 'peer-checked:bg-[#FEF2F2] peer-checked:border-[#FF0000] peer-checked:text-[#FF0000]';
                                    } elseif ($plat === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platColor = 'peer-checked:bg-[#F0FDF4] peer-checked:border-[#25D366] peer-checked:text-[#25D366]';
                                    } elseif ($plat === 'linkedin') {
                                        $platIcon = 'work';
                                        $platColor = 'peer-checked:bg-[#EFF6FF] peer-checked:border-[#0A66C2] peer-checked:text-[#0A66C2]';
                                    } elseif ($plat === 'google_business') {
                                        $platIcon = 'store';
                                        $platColor = 'peer-checked:bg-[#EEF2FF] peer-checked:border-[#4285F4] peer-checked:text-[#4285F4]';
                                    }
                                ?>
                                    <label class="platform-checkbox-label cursor-pointer" id="label-platform-<?php echo $plat; ?>">
                                        <input class="hidden peer" type="checkbox" name="platforms[]" value="<?php echo htmlspecialchars($plat); ?>" id="platform-<?php echo $plat === 'instagram' ? 'ig' : $plat; ?>" />
                                        <div class="flex items-center gap-sm px-md py-sm rounded-lg border border-surface-variant bg-surface-container-low transition-all <?php echo $platColor; ?>">
                                            <span class="material-symbols-outlined text-[20px]"><?php echo $platIcon; ?></span>
                                            <span class="font-body-md font-semibold capitalize"><?php echo htmlspecialchars($plat === 'google_business' ? 'Google Profile' : $plat); ?></span>
                                            <?php if ($plat === 'youtube'): ?>
                                                <span class="text-[10px] bg-red-100 text-red-700 font-bold px-1.5 py-0.5 rounded border border-red-200 uppercase ml-1 yt-tag">(Video Only)</span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Instagram Notice -->
                    <div id="ig-warning" class="hidden animate-in fade-in slide-in-from-top-2 duration-300">
                        <div class="bg-error-container text-on-error-container p-md rounded-lg flex items-start gap-md border border-error/20">
                            <span class="material-symbols-outlined text-xl">warning</span>
                            <div>
                                <p class="font-body-md font-semibold">Instagram Limitations</p>
                                <p class="font-body-sm opacity-90">A photo or video media attachment is required for Instagram Business posts.</p>
                            </div>
                        </div>
                    </div>

                    <!-- YouTube title group -->
                    <div id="youtube-title-group" class="hidden space-y-sm animate-in fade-in slide-in-from-top-2">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="title">YouTube Video Title</label>
                        <input type="text" id="title" name="title" placeholder="Enter video title" 
                               class="w-full bg-surface-container-low border border-surface-variant rounded-lg px-md py-sm font-body-md focus:ring-2 focus:ring-primary-container focus:border-primary focus:outline-none transition-all" value="New Dashboard Upload" />
                        <div id="yt-info" class="text-xs text-on-surface-variant opacity-75">YouTube video dispatch requires an attachment and title.</div>
                    </div>

                    <!-- Content Textarea -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="content">Post Content</label>
                        <div class="relative">
                            <textarea class="w-full bg-surface-container-low border border-surface-variant rounded-lg p-md font-body-md focus:ring-2 focus:ring-primary-container focus:border-primary focus:outline-none transition-all resize-none" 
                                      id="content" name="content" placeholder="What would you like to share? Use @mentions and #hashtags..." rows="6"></textarea>
                        </div>
                    </div>

                    <!-- Media Upload -->
                    <div class="space-y-sm">
                        <label class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider block" for="media">Media Assets</label>
                        <div class="border-2 border-dashed border-outline-variant rounded-xl p-lg flex flex-col items-center justify-center bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer group relative">
                            <input type="file" id="media" name="media" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            <div class="w-12 h-12 bg-primary-container/10 rounded-full flex items-center justify-center mb-md group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-primary">cloud_upload</span>
                            </div>
                            <p class="font-body-lg font-bold">Click to browse or drag file here</p>
                            <p class="font-body-sm text-on-surface-variant mt-xs">Images (Max 8MB) or Videos (Max 70MB)</p>
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
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-md bg-surface-container-low p-md rounded-lg border border-surface-variant">
                                <div class="space-y-xs">
                                    <label class="font-data-label text-data-label text-on-surface-variant block" for="scheduled-at">Select Date & Time</label>
                                    <input class="w-full bg-surface-container-lowest border border-surface-variant rounded-md px-md py-sm focus:ring-1 focus:ring-primary focus:outline-none" 
                                           id="scheduled-at" name="scheduled_at" type="datetime-local" step="300"/>
                                </div>
                            </div>
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
                        <span id="submit-loading" class="hidden text-xs text-on-surface-variant italic">Dispatching payloads...</span>
                        <a href="dashboard_home.php" class="px-lg py-md text-on-surface-variant font-semibold hover:bg-surface-container-high rounded-lg transition-colors">Cancel</a>
                        <button id="btn-publish" type="submit" class="px-xl py-md bg-primary text-on-primary font-bold rounded-lg shadow-lg hover:brightness-110 active:scale-95 transition-all">
                            🚀 Publish Post
                        </button>
                    </div>
                </form>

                <!-- Right Column: Preview Column -->
                <div class="col-span-12 lg:col-span-5 space-y-lg">
                    <h3 class="font-data-label text-data-label text-on-surface-variant uppercase tracking-wider px-md">Live Preview</h3>
                    <!-- Mobile Preview Container -->
                    <div class="bg-[#E2E8F0] p-md rounded-[3rem] border-[8px] border-[#1E293B] aspect-[9/18.5] max-w-[340px] mx-auto shadow-2xl relative overflow-hidden">
                        <!-- Phone Notch -->
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-24 h-6 bg-[#1E293B] rounded-b-2xl z-20"></div>
                        <!-- Screen Content -->
                        <div class="h-full bg-white rounded-[2rem] overflow-y-auto no-scrollbar pt-8">
                            <div class="px-md pb-md flex items-center justify-between border-b border-gray-100">
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 p-[2px]">
                                        <div class="w-full h-full rounded-full bg-white p-[2px]">
                                            <div class="w-full h-full rounded-full bg-gray-200"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-body-sm font-bold text-xs">acmecorporate</p>
                                        <p class="text-[10px] text-gray-500" id="preview-platform-label">No Platform Selected</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-gray-400">more_horiz</span>
                            </div>

                            <!-- Post Image Placeholder / Attachment Preview -->
                            <div class="w-full aspect-square bg-gray-100 relative overflow-hidden flex items-center justify-center">
                                <div id="preview-media" class="w-full h-full hidden items-center justify-center [&>img]:w-full [&>img]:h-full [&>img]:object-cover [&>video]:w-full [&>video]:h-full [&>video]:object-cover"></div>
                                <div id="preview-placeholder-icon" class="absolute inset-0 bg-black/5 flex flex-col items-center justify-center text-outline">
                                    <span class="material-symbols-outlined text-4xl">add_a_photo</span>
                                    <span class="text-xs mt-xs">No media attached</span>
                                </div>
                            </div>

                            <!-- Caption -->
                            <div class="px-md space-y-xs py-md">
                                <p class="text-[12px] font-bold">1,248 likes</p>
                                <div class="text-[12px] leading-snug">
                                    <span class="font-bold">acmecorporate</span>
                                    <span class="text-gray-800" id="preview-text-content">Post caption preview will render here...</span>
                                </div>
                                <p class="text-[10px] text-gray-400 uppercase mt-xs">Just now</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo DASHBOARD_BASE_URL; ?>/assets/js/composer.js"></script>
</body>
</html>
