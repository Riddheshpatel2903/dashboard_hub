<?php
/** Admin Clients Overview (Tailwind & Stitch Design System). */
require_once __DIR__ . '/../includes/role_check.php';  // Ensures logged-in staff/admin
require_once __DIR__ . '/../includes/hub_client.php';
$pdoDash = require __DIR__ . '/../db/connection.php';

$error = '';
$success_msg = '';
$clients = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if ($clientId > 0) {
        if ($action === 'delete') {
            $hubRes = hubDeleteClient($clientId);
            if (!empty($hubRes['success'])) {
                // Delete from Dashboard DB
                $pdoDash->prepare("DELETE FROM users WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
                $pdoDash->prepare("DELETE FROM client_hub_keys WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
                $pdoDash->prepare("DELETE FROM posts_cache WHERE client_id = :client_id")->execute(['client_id' => $clientId]);
                
                $success_msg = 'Client account and all associated data deleted successfully.';
            } else {
                $error = 'Failed to delete client from Hub: ' . ($hubRes['error'] ?? 'Unknown Error');
            }
        } elseif ($action === 'extend') {
            $hubRes = hubExtendClient($clientId);
            if (!empty($hubRes['success'])) {
                $success_msg = 'Subscription plan extended by 1 year successfully.';
            } else {
                $error = 'Failed to extend subscription: ' . ($hubRes['error'] ?? 'Unknown Error');
            }
        } elseif ($action === 'status') {
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, ['active', 'inactive'], true)) {
                $hubRes = hubToggleClientStatus($clientId, $newStatus);
                if (!empty($hubRes['success'])) {
                    $success_msg = 'Client status updated to ' . $newStatus . ' successfully.';
                } else {
                    $error = 'Failed to update client status: ' . ($hubRes['error'] ?? 'Unknown Error');
                }
            }
        }
    }
}

// Fetch list of clients from the Hub
$hubRes = hubListClients();
if (!empty($hubRes['success']) && is_array($hubRes['clients'])) {
    $clients = $hubRes['clients'];
    
    // Self-healing: Clean up any orphaned local records in Dashboard DB that no longer exist on the Hub
    $hubClientIds = array_column($clients, 'id');
    if (!empty($hubClientIds)) {
        $placeholders = implode(',', array_fill(0, count($hubClientIds), '?'));
        
        $stmt = $pdoDash->prepare("DELETE FROM users WHERE role = 'client' AND client_id NOT IN ($placeholders)");
        $stmt->execute($hubClientIds);
        
        $stmt = $pdoDash->prepare("DELETE FROM client_hub_keys WHERE client_id NOT IN ($placeholders)");
        $stmt->execute($hubClientIds);
        
        $stmt = $pdoDash->prepare("DELETE FROM posts_cache WHERE client_id NOT IN ($placeholders)");
        $stmt->execute($hubClientIds);
    }
} else {
    $error = 'Failed to load clients list from the Hub: ' . ($hubRes['error'] ?? 'Unknown Connection Error');
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Client Accounts | Social Hub</title>
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
            <!-- Header -->
            <div class="flex items-center justify-between mb-lg">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface">Client Accounts</h1>
                    <p class="font-body-md text-on-surface-variant">Overview of all agency onboarded client profiles.</p>
                </div>
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/auth/register_client.php" 
                   class="flex items-center gap-xs px-md py-sm bg-primary text-on-primary rounded-lg font-body-sm font-bold hover:opacity-90 active:scale-95 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-sm">add</span>
                    <span>Onboard New Client</span>
                </a>
            </div>

            <!-- Notifications -->
            <?php if ($error): ?>
                <div class="bg-error-container text-on-error-container p-md rounded-lg flex items-center gap-md border border-error/20">
                    <span class="material-symbols-outlined text-xl">error</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="bg-[#E4F6EE] text-[#1F9D6B] p-md rounded-lg flex items-center gap-md border border-green-200">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    <span class="font-body-md font-semibold"><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Table Card -->
            <div class="bg-surface-container-lowest border border-surface-variant rounded-xl shadow-sm overflow-hidden">
                <?php if (empty($clients)): ?>
                    <div class="p-xl text-center text-on-surface-variant font-body-md">
                        No client profiles found. Start by onboarding your first client account.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container-low border-b border-surface-variant">
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[100px]">CLIENT ID</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider min-w-[200px]">COMPANY NAME</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[110px]">STATUS</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[130px]">EXPIRY DATE</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider text-right w-[200px]">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-variant">
                                <?php foreach ($clients as $client): ?>
                                    <tr class="group hover:bg-secondary-container/10 transition-colors">
                                        <td class="px-lg py-md font-data-label text-data-label text-on-surface-variant">
                                            #<?php echo (int) $client['id']; ?>
                                        </td>
                                        <td class="px-lg py-md font-body-md text-on-surface font-bold">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </td>
                                        <td class="px-lg py-md">
                                            <?php if (($client['status'] ?? 'active') === 'active'): ?>
                                                <span class="inline-flex items-center px-sm py-[2px] rounded-full text-[10px] font-bold uppercase bg-[#E4F6EE] text-[#1F9D6B] border border-green-200">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-sm py-[2px] rounded-full text-[10px] font-bold uppercase bg-error-container text-error border border-error/20">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-lg py-md font-data-label text-data-label text-on-surface-variant">
                                            <?php echo !empty($client['expiry_date']) ? date('Y-m-d', strtotime($client['expiry_date'])) : 'n/a'; ?>
                                        </td>
                                        <td class="px-lg py-md text-right">
                                            <div class="flex items-center justify-end gap-sm flex-wrap">
                                                <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/client_detail.php?act_as_client_id=<?php echo $client['id']; ?>" 
                                                   class="w-8 h-8 bg-surface-container hover:bg-surface-container-high text-on-surface rounded-lg transition-all inline-flex items-center justify-center" 
                                                   title="Act as Client / Manage">
                                                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                                                </a>
                                                
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                    <input type="hidden" name="action" value="extend">
                                                    <button type="submit" class="w-8 h-8 bg-primary/10 hover:bg-primary/20 text-primary rounded-lg transition-all inline-flex items-center justify-center" title="Continue Plan (Renew 1 Year)">
                                                        <span class="material-symbols-outlined text-[18px]">autorenew</span>
                                                    </button>
                                                </form>



                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this client and all associated data? This action CANNOT be undone.');">
                                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="w-8 h-8 bg-error-container hover:bg-error-container-high text-error rounded-lg transition-all inline-flex items-center justify-center" title="Delete Client Account">
                                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
