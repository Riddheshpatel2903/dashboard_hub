<?php
/**
 * Postings History Table (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/session_check.php';
$pdo = require_once __DIR__ . '/../db/connection.php';

if ($client_id === null) {
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

$platformFilter = $_GET['platform'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$sql = "WHERE client_id = :client_id AND status != 'deleted'";
$params = ['client_id' => $client_id];

if (!empty($platformFilter)) {
    $sql .= " AND platform = :platform";
    $params['platform'] = $platformFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND (DATE(published_at) = :date_pub OR DATE(scheduled_at) = :date_sched)";
    $params['date_pub'] = $dateFilter;
    $params['date_sched'] = $dateFilter;
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND (
        (status = 'published' AND DATE(published_at) BETWEEN :start_pub AND :end_pub)
        OR (status = 'scheduled' AND DATE(scheduled_at) BETWEEN :start_sched AND :end_sched)
        OR (status = 'failed' AND DATE(created_at) BETWEEN :start_fail AND :end_fail)
    )";
    $params['start_pub'] = $startDate;
    $params['end_pub'] = $endDate;
    $params['start_sched'] = $startDate;
    $params['end_sched'] = $endDate;
    $params['start_fail'] = $startDate;
    $params['end_fail'] = $endDate;
}

// Get Count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM posts_cache {$sql}");
$stmtCount->execute($params);
$totalPosts = $stmtCount->fetchColumn();
$totalPages = ceil($totalPosts / $limit);
if ($totalPages < 1) $totalPages = 1;

// Fetch posts
$stmtPosts = $pdo->prepare("
    SELECT id, hub_post_id, content, status, platform, scheduled_at, published_at, created_at
    FROM posts_cache 
    {$sql} 
    ORDER BY created_at DESC 
    LIMIT :offset, :limit
");
$stmtPosts->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtPosts->bindValue(':limit', $limit, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmtPosts->bindValue(':' . $key, $val);
}
$stmtPosts->execute();
$posts = $stmtPosts->fetchAll();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Post History | Command Center</title>
    <?php include __DIR__ . '/../includes/head_inc.php'; ?>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top App Bar -->
    <?php include __DIR__ . '/../includes/top_bar.php'; ?>

    <!-- Main Content Area -->
    <main class="ml-[240px] pt-16 min-h-screen">
        <div class="p-lg max-w-[1440px] mx-auto space-y-lg">
            <!-- Title -->
            <div class="flex items-center justify-between mb-lg">
                <div>
                    <h2 class="font-display-lg text-display-lg font-bold text-on-surface">Post History</h2>
                    <div class="flex items-center gap-xs text-on-surface-variant mt-1">
                        <span class="font-body-sm">Dashboard</span>
                        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                        <span class="font-body-sm font-bold text-primary">History</span>
                    </div>
                </div>
                <div class="flex gap-sm">
                    <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/composer.php" 
                       class="flex items-center gap-sm px-md py-sm bg-primary text-on-primary rounded-lg font-body-md font-bold hover:opacity-90 transition-opacity">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Create Post</span>
                    </a>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-md shadow-sm">
                <form method="GET" action="" class="flex flex-wrap items-end gap-md">
                    <!-- Platform Selector -->
                    <div class="flex-1 min-w-[200px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="platform">PLATFORM</label>
                        <select id="platform" name="platform" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Platforms</option>
                            <option value="facebook" <?php echo $platformFilter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="instagram" <?php echo $platformFilter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="whatsapp" <?php echo $platformFilter === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                            <option value="youtube" <?php echo $platformFilter === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                            <option value="linkedin" <?php echo $platformFilter === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="google_business" <?php echo $platformFilter === 'google_business' ? 'selected' : ''; ?>>Google Business</option>
                        </select>
                    </div>
                    
                    <!-- Date Pickers -->
                    <div class="w-[180px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="start_date">START DATE</label>
                        <input type="date" id="start_date" name="start_date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>

                    <div class="w-[180px] space-y-xs">
                        <label class="font-data-label text-data-label text-on-surface-variant block" for="end_date">END DATE</label>
                        <input type="date" id="end_date" name="end_date" class="w-full h-10 px-md bg-surface-container-low border border-surface-variant rounded-lg font-body-sm focus:outline-none" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-sm">
                        <button type="submit" class="h-10 px-lg bg-primary text-on-primary rounded-lg font-body-sm font-bold hover:opacity-90 transition-all flex items-center justify-center gap-xs">
                            <span class="material-symbols-outlined text-sm">filter_list</span>
                            <span>Filter</span>
                        </button>
                        <a href="<?php echo DASHBOARD_BASE_URL; ?>/pages/post_history.php" class="h-10 px-lg bg-surface-container text-on-surface-variant rounded-lg font-body-sm font-bold hover:bg-surface-container-high transition-all flex items-center justify-center">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Data Table Card -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden">
                <?php if (empty($posts)): ?>
                    <div class="p-xl text-center text-on-surface-variant font-body-md">
                        No matching posts found in history.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container-low border-b border-surface-variant">
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[120px]">PLATFORM</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider min-w-[300px]">CONTENT PREVIEW</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[140px]">STATUS</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[180px]">TARGET TIME</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider text-right w-[100px]">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-variant">
                                <?php foreach ($posts as $post): 
                                    // Resolve Platform Specific colors and icons
                                    $platIcon = 'face';
                                    $platColorClass = 'bg-surface-container text-on-surface-variant';
                                    
                                    if ($post['platform'] === 'facebook') {
                                        $platIcon = 'public';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#1877F2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'instagram') {
                                        $platIcon = 'photo_camera';
                                        $platColorClass = 'bg-[#FDF2F8] text-[#E1306C] border border-[#FBCFE8]';
                                    } elseif ($post['platform'] === 'youtube') {
                                        $platIcon = 'play_circle';
                                        $platColorClass = 'bg-[#FEF2F2] text-[#FF0000] border border-[#FEE2E2]';
                                    } elseif ($post['platform'] === 'whatsapp') {
                                        $platIcon = 'chat';
                                        $platColorClass = 'bg-[#F0FDF4] text-[#25D366] border border-[#DCFCE7]';
                                    } elseif ($post['platform'] === 'linkedin') {
                                        $platIcon = 'work';
                                        $platColorClass = 'bg-[#EFF6FF] text-[#0A66C2] border border-[#DBEAFE]';
                                    } elseif ($post['platform'] === 'google_business') {
                                        $platIcon = 'store';
                                        $platColorClass = 'bg-[#EEF2FF] text-[#4285F4] border border-[#E0E7FF]';
                                    }

                                    $statusClass = 'bg-surface-container text-on-surface-variant border border-outline-variant/30';
                                    if ($post['status'] === 'published') {
                                        $statusClass = 'bg-[#E4F6EE] text-[#1F9D6B] border border-green-200';
                                    } elseif ($post['status'] === 'scheduled') {
                                        $statusClass = 'bg-primary-container/20 text-primary border border-primary-fixed';
                                    } elseif ($post['status'] === 'failed') {
                                        $statusClass = 'bg-error-container text-error border border-error/20';
                                    }
                                    
                                    $targetTime = $post['status'] === 'published' ? $post['published_at'] : ($post['scheduled_at'] ?: $post['created_at']);
                                ?>
                                    <tr class="group hover:bg-secondary-container/10 transition-colors">
                                        <!-- Platform Column -->
                                        <td class="px-lg py-md">
                                            <div class="flex items-center gap-xs px-xs py-[2px] rounded text-[10px] font-bold select-none truncate <?php echo $platColorClass; ?>">
                                                <span class="material-symbols-outlined !text-[12px]"><?php echo $platIcon; ?></span>
                                                <span class="truncate capitalize"><?php echo htmlspecialchars($post['platform'] === 'google_business' ? 'Google' : $post['platform']); ?></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Content Preview Column -->
                                        <td class="px-lg py-md font-body-md text-on-surface truncate max-w-md">
                                            <?php echo htmlspecialchars($post['content']); ?>
                                        </td>
                                        
                                        <!-- Status Column -->
                                        <td class="px-lg py-md">
                                            <span class="inline-flex items-center px-sm py-xs rounded-full text-xs font-bold uppercase tracking-tight <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($post['status']); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Time Column -->
                                        <td class="px-lg py-md font-data-label text-data-label text-on-surface-variant">
                                            <?php echo date('Y-m-d H:i', strtotime($targetTime)); ?>
                                        </td>
                                        
                                        <!-- Actions Column -->
                                        <td class="px-lg py-md text-right">
                                            <button class="btn-view-detail px-sm h-8 bg-surface-container hover:bg-surface-container-high rounded text-on-surface-variant font-body-sm font-semibold transition-all inline-flex items-center gap-xs" 
                                                    data-id="<?php echo $post['id']; ?>">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                                <span>Inspect</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-lg py-md border-t border-surface-variant flex justify-between items-center bg-surface-container-low">
                        <span class="font-body-sm text-on-surface-variant">
                            Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo $totalPosts; ?> total items)
                        </span>
                        <div class="flex gap-sm">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&platform=<?php echo urlencode($platformFilter); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                                   class="h-8 px-md bg-surface-container-lowest border border-surface-variant text-on-surface-variant hover:bg-surface-container rounded font-body-sm font-bold flex items-center justify-center">&larr; Prev</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page+1; ?>&platform=<?php echo urlencode($platformFilter); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                                   class="h-8 px-md bg-surface-container-lowest border border-surface-variant text-on-surface-variant hover:bg-surface-container rounded font-body-sm font-bold flex items-center justify-center">Next &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Pinned post details Modal container -->
    <div id="post-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" style="display: none;">
        <div class="bg-surface-container-lowest border border-surface-variant w-full max-w-[500px] rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-lg py-md border-b border-surface-variant flex justify-between items-center bg-surface-container-low">
                <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Post Inspector</h3>
                <button class="text-on-surface-variant hover:text-on-surface text-2xl font-bold" id="modal-close-btn">&times;</button>
            </div>
            <div class="p-lg overflow-y-auto max-h-[75vh]" id="modal-body-content">
                <p style="color:var(--text-secondary); text-align:center;">Loading details...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('post-modal');
            const modalBody = document.getElementById('modal-body-content');
            const closeBtn = document.getElementById('modal-close-btn');

            closeBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.style.display = 'none';
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }
            });

            document.querySelectorAll('.btn-view-detail, .btn-view-detail *').forEach(el => {
                const btn = el.closest('.btn-view-detail');
                if (!btn) return;
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    
                    modal.classList.remove('hidden');
                    modal.style.display = 'flex';
                    modalBody.innerHTML = '<p class="text-on-surface-variant text-center py-lg">Loading post details...</p>';
                    
                    fetch(`post_detail.php?post_id=${id}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.text())
                    .then(html => {
                        modalBody.innerHTML = html;
                        // Bind dynamic action triggers in modal body
                        attachModalActionListeners();
                    })
                    .catch(err => {
                        console.error(err);
                        modalBody.innerHTML = '<p class="text-error text-center py-lg">Failed to retrieve details.</p>';
                    });
                });
            });

            function attachModalActionListeners() {
                const deleteBtn = modalBody.querySelector('#btn-delete-post');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function() {
                        const postId = this.getAttribute('data-id');
                        const confirmed = confirm("Are you sure you want to delete this post? This is irreversible.");
                        if (confirmed) {
                            deleteBtn.disabled = true;
                            deleteBtn.textContent = 'Deleting...';
                            
                            fetch('post_detail.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ action: 'delete', post_id: postId })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Deleted successfully.');
                                    window.location.reload();
                                } else {
                                    alert('Failed: ' + data.error);
                                    deleteBtn.disabled = false;
                                    deleteBtn.textContent = 'Delete Post';
                                }
                            });
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
