<?php
/**
 * Shared Top App Bar (Tailwind & Stitch Design System).
 */
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/hub_client.php';

$topBarClientName = 'Workspace';
if (isset($client_id) && $client_id !== null) {
    if (!isset($_SESSION['client_name'])) {
        $clientRes = hubGetClient($client_id);
        if (!empty($clientRes['success']) && !empty($clientRes['client'])) {
            $_SESSION['client_name'] = $clientRes['client']['name'];
        }
    }
    $topBarClientName = $_SESSION['client_name'] ?? 'Client #' . $client_id;
}
?>
<header class="h-16 fixed top-0 right-0 left-[240px] bg-surface-container-lowest border-b border-surface-variant flex justify-between items-center px-lg z-40">
    <!-- Left: Client Context -->
    <div class="flex items-center gap-lg">
        <div class="flex items-center gap-sm">
            <span class="text-on-surface-variant font-body-md">Managing:</span>
            <div class="flex items-center gap-sm px-sm py-xs bg-surface-container-low border border-surface-variant rounded-lg cursor-pointer hover:bg-surface-container-high transition-colors">
                <span class="font-headline-sm text-headline-sm text-primary"><?php echo htmlspecialchars($topBarClientName); ?></span>
            </div>
        </div>
        
        <?php if (($user_role === 'staff' || $user_role === 'admin') && isset($_SESSION['acting_client_id'])): ?>
            <div class="flex items-center gap-sm">
                <span class="px-sm py-1 bg-primary/10 text-primary border border-primary/20 rounded-full text-xs font-bold uppercase tracking-tight">Impersonating Client #<?php echo $client_id; ?></span>
                <a href="<?php echo DASHBOARD_BASE_URL; ?>/admin/client_detail.php?stop_acting=1" class="text-xs text-error hover:underline font-bold">Stop Impersonation</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Search & Actions -->
    <div class="flex items-center gap-xl">
        <div class="flex items-center gap-md">
            <div class="h-8 w-8 rounded-full bg-primary-fixed border border-primary overflow-hidden">
                <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDof771w53lMWkKROQCoKVO19rJQaFidy7sLK1DqFddUOejFgeSGnOGuWnyeAsZsLM4SMeRl4UopHdf26PR5rG8PJiGm5TZkuCQqpvD4xmYbxsP-EHb7O_EMUDEd7DdU33bnoD71XPL-Yz9qRMe20WrRiNgM1k0CHE9-Rn54HeRousOq_baqZH5X44B033rAhAj-aY3qdGy_EFi79E_mR7CCfPVYMf28fswyNzHyK3oRDg21y7duKrxgbVUf_7oPyyLPS7u90Dys4k" alt="Profile">
            </div>
        </div>
    </div>
</header>
