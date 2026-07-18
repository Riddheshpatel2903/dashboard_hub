<?php
/**
 * Dashboard User Logout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session on server
session_destroy();

require_once __DIR__ . '/../config/config.php';
header('Location: ' . DASHBOARD_BASE_URL . '/auth/login.php');
exit();
