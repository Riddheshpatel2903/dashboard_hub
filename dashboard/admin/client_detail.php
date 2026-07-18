<?php
/**
 * Admin Context Switcher (Impersonate Client).
 */

require_once __DIR__ . '/../includes/role_check.php'; // Ensures logged-in staff/admin

// Handle Stop Impersonation
if (isset($_GET['stop_acting'])) {
    unset($_SESSION['acting_client_id']);
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
    exit();
}

// Handle Start Impersonation
$targetClientId = isset($_GET['act_as_client_id']) ? (int)$_GET['act_as_client_id'] : 0;

if ($targetClientId > 0) {
    $_SESSION['acting_client_id'] = $targetClientId;
    
    // Redirect staff/admin straight to client homepage, where session_check will automatically
    // map the active client override context.
    header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
    exit();
}

// Fallback: If accessed without parameters, redirect back
header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
exit();
