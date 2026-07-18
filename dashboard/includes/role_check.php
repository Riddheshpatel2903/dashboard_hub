<?php
/**
 * Role verification middleware.
 * Guarantees that only staff or admin accounts can access matching pages.
 */

require_once __DIR__ . '/session_check.php';

if ($user_role !== 'staff' && $user_role !== 'admin') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
              
    if ($isAjax) {
        header('Content-Type: application/json', true, 403);
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: Admin access required.'
        ]);
        exit();
    }
    
    // Redirect standard client users back to client home page
    header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
    exit();
}
