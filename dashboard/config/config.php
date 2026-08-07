<?php

date_default_timezone_set('Asia/Kolkata');

/**
 * Dashboard Central Configuration.
 * Decoupled from the Hub's database.
 */

// Dashboard Database Credentials (separate DB from Hub)
define('DASHBOARD_DB_HOST', getenv('DASHBOARD_DB_HOST') ?: 'srv2216.hstgr.io');
define('DASHBOARD_DB_PORT', getenv('DASHBOARD_DB_PORT') ?: '3306');
define('DASHBOARD_DB_NAME', getenv('DASHBOARD_DB_NAME') ?: 'u689131217_dashboard_db');
define('DASHBOARD_DB_USER', getenv('DASHBOARD_DB_USER') ?: 'u689131217_dashboard_user');
define('DASHBOARD_DB_PASS', getenv('DASHBOARD_DB_PASS') !== false ? getenv('DASHBOARD_DB_PASS') : 'ao3P;k~OD+1');

// Dynamically compute base URL path relative to the server document root.
// Robust against symlinked hosting paths by comparing script path to URL path.
$scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$dashRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

$dashboardBaseUrl = '';
if (!empty($scriptFilename) && !empty($scriptName)) {
    $pos = stripos($scriptFilename, $scriptName);
    if ($pos !== false) {
        $webRoot = substr($scriptFilename, 0, $pos);
        if (stripos($dashRoot, $webRoot) === 0) {
            $dashboardBaseUrl = substr($dashRoot, strlen($webRoot));
        }
    }
}

// Fallback method
if (empty($dashboardBaseUrl)) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    if (!empty($docRoot) && stripos($dashRoot, $docRoot) === 0) {
        $dashboardBaseUrl = substr($dashRoot, strlen($docRoot));
    }
}

$dashboardBaseUrl = str_replace('\\', '/', $dashboardBaseUrl);
$dashboardBaseUrl = rtrim($dashboardBaseUrl, '/');

// Define custom override for the dashboard base URL path if dynamic resolution doesn't match your production subfolder setup.
define('CUSTOM_DASHBOARD_BASE_URL', '/new-site/dashboard');

if (defined('CUSTOM_DASHBOARD_BASE_URL') && CUSTOM_DASHBOARD_BASE_URL !== '') {
    $dashboardBaseUrl = CUSTOM_DASHBOARD_BASE_URL;
}

define('DASHBOARD_BASE_URL', $dashboardBaseUrl);

// Agency main dashboard host domain (change this to your production domain, e.g. 'rbfitness.in')
define('AGENCY_DASHBOARD_DOMAIN', 'rbfitness.in');

// Hub API Access Coordinates
// Dashboard always targets the production hub regardless of dev/prod environment.
// Run /dashboard/admin/sync_hub.php once to register local clients in the production hub DB.
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) || (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1');
$defaultHubUrl = $isLocalhost ? 'https://rbfitness.in/new-site/hub' : 'https://rbfitness.in/new-site/hub';

// Hub API URL — override via HUB_BASE_URL env var to use a local hub during development.
define('HUB_BASE_URL', rtrim(getenv('HUB_BASE_URL') ?: $defaultHubUrl, '/'));
define('HUB_ADMIN_MASTER_KEY', getenv('HUB_ADMIN_MASTER_KEY') ?: 'admin_master_secret_token_change_me');
define('CRON_SECRET', getenv('HUB_CRON_SECRET') ?: 'cron_secret_token_12345!');
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

/**
 * Returns the URL to custom client brand SVG icons stored in assets.
 */
function getBrandIconUrl($platform) {
    $baseUrl = DASHBOARD_BASE_URL . '/assets/icons/';
    if ($platform === 'facebook') {
        return $baseUrl . 'facebook.svg';
    } elseif ($platform === 'instagram') {
        return $baseUrl . 'instagram.svg';
    } elseif ($platform === 'youtube') {
        return $baseUrl . 'youtube.svg';
    } elseif ($platform === 'google_business') {
        return $baseUrl . 'google.svg';
    } elseif ($platform === 'search_console') {
        return $baseUrl . 'google_search.svg';
    } elseif ($platform === 'linkedin') {
        return $baseUrl . 'linkedin.svg';
    } elseif ($platform === 'blog' || $platform === 'website') {
        return $baseUrl . 'blog.svg';
    }
    return ''; // Returns empty string for custom fallback logic
}
