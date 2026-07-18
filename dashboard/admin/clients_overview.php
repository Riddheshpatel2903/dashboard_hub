<?php
/**
 * Admin Clients Overview (Tailwind & Stitch Design System).
 */

require_once __DIR__ . '/../includes/role_check.php'; // Ensures logged-in staff/admin
require_once __DIR__ . '/../includes/hub_client.php';

$error = '';
$clients = [];

// Fetch list of clients from the Hub
$hubRes = hubListClients();
if (!empty($hubRes['success']) && is_array($hubRes['clients'])) {
    $clients = $hubRes['clients'];
} else {
    $error = 'Failed to load clients list from the Hub: ' . ($hubRes['error'] ?? 'Unknown Connection Error');
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <title>Clients Overview | Command Center</title>
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
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider">WEBSITE</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[180px]">LINKED PLATFORMS</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider w-[180px]">JOINED DATE</th>
                                    <th class="px-lg py-md font-data-label text-data-label text-on-surface-variant uppercase tracking-wider text-right w-[180px]">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-variant">
                                <?php foreach ($clients as $client): ?>
                                    <tr class="group hover:bg-secondary-container/10 transition-colors">
                                        <td class="px-lg py-md font-data-label text-data-label text-on-surface-variant">
                                            #<?php echo (int)$client['id']; ?>
                                        </td>
                                        <td class="px-lg py-md font-body-md text-on-surface font-bold">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </td>
                                        <td class="px-lg py-md font-body-md">
                                            <a href="<?php echo htmlspecialchars($client['website_url']); ?>" target="_blank" class="text-primary hover:underline">
                                                <?php echo htmlspecialchars($client['website_url']); ?>
                                            </a>
                                        </td>
                                        <td class="px-lg py-md">
                                            <span class="inline-flex items-center px-sm py-[2px] rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                                <?php echo (int)$client['connection_count']; ?> platform(s)
                                            </span>
                                        </td>
                                        <td class="px-lg py-md font-data-label text-data-label text-on-surface-variant">
                                            <?php echo date('Y-m-d H:i', strtotime($client['created_at'])); ?>
                                        </td>
                                        <td class="px-lg py-md text-right">
                                            <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/client_detail.php?act_as_client_id=<?php echo $client['id']; ?>" 
                                               class="h-8 px-md bg-surface-container hover:bg-surface-container-high text-on-surface font-body-sm font-semibold rounded transition-all inline-flex items-center justify-center gap-xs">
                                                <span class="material-symbols-outlined text-sm">support_agent</span>
                                                <span>Act as Client</span>
                                            </a>
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
