<?php
/**
 * Dashboard Entry Point Router.
 */

require_once __DIR__ . '/config/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . DASHBOARD_BASE_URL . '/auth/login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'client';

if ($role === 'client') {
    header('Location: ' . DASHBOARD_BASE_URL . '/pages/dashboard_home.php');
} else {
    // Staff / Admin roles go directly to clients list
    header('Location: ' . DASHBOARD_BASE_URL . '/admin/clients_overview.php');
}
exit();
