<?php
/**
 * Session verification middleware.
 * Included at the top of protected pages.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    // If request is made via AJAX (XHR), return 401 JSON instead of redirecting
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
              
    if ($isAjax) {
        header('Content-Type: application/json', true, 401);
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized session. Please log in.'
        ]);
        exit();
    }
    
    header('Location: ' . DASHBOARD_BASE_URL . '/auth/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$client_id = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : null;

// Track active context override if staff/admin is acting as a client
if (($user_role === 'staff' || $user_role === 'admin') && isset($_SESSION['acting_client_id'])) {
    $client_id = (int)$_SESSION['acting_client_id'];
}
