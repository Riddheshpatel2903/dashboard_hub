<?php

/**
 * Dashboard Central Configuration.
 * Decoupled from the Hub's database.
 */

// Dashboard Database Credentials (separate DB from Hub)
define('DASHBOARD_DB_HOST', getenv('DASHBOARD_DB_HOST') ?: '127.0.0.1');
define('DASHBOARD_DB_PORT', getenv('DASHBOARD_DB_PORT') ?: '3306');
define('DASHBOARD_DB_NAME', getenv('DASHBOARD_DB_NAME') ?: 'dashboard_db');
define('DASHBOARD_DB_USER', getenv('DASHBOARD_DB_USER') ?: 'root');
define('DASHBOARD_DB_PASS', getenv('DASHBOARD_DB_PASS') !== false ? getenv('DASHBOARD_DB_PASS') : '');

// Hub API Access Coordinates
define('HUB_BASE_URL', rtrim(getenv('HUB_BASE_URL') ?: 'http://localhost:8080/dashboard_hub/hub', '/'));
define('HUB_ADMIN_MASTER_KEY', getenv('HUB_ADMIN_MASTER_KEY') ?: 'admin_master_secret_token_change_me');
// Dynamically compute base URL path relative to the server document root
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$dashRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));

$dashboardBaseUrl = '';
if (!empty($docRoot) && strpos($dashRoot, $docRoot) === 0) {
    $dashboardBaseUrl = substr($dashRoot, strlen($docRoot));
}
$dashboardBaseUrl = str_replace('\\', '/', $dashboardBaseUrl);
$dashboardBaseUrl = rtrim($dashboardBaseUrl, '/');

define('DASHBOARD_BASE_URL', $dashboardBaseUrl);
$cookiePath = empty($dashboardBaseUrl) ? '/' : $dashboardBaseUrl;

// Session Settings
define('SESSION_LIFETIME', 86400);  // 24 hours
define('SECURE_SESSION_COOKIES', getenv('SECURE_SESSION_COOKIES') === 'true' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'));

// Set secure session parameters
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => SECURE_SESSION_COOKIES,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
