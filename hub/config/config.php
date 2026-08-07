<?php
/** Central place for environment-level settings — DB credentials, base URLs, encryption key. */
date_default_timezone_set('Asia/Kolkata');

// Hub Database Credentials
define('DB_HOST', getenv('HUB_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('HUB_DB_PORT') ?: '3306');
define('DB_NAME', getenv('HUB_DB_NAME') ?: 'hub_db');
define('DB_USER', getenv('HUB_DB_USER') ?: 'root');
define('DB_PASS', getenv('HUB_DB_PASS') !== false ? getenv('HUB_DB_PASS') : '');

// Dashboard Database Credentials (same server, separate DB — credentials pulled from env vars)
define('DASHBOARD_DB_HOST', getenv('DASHBOARD_DB_HOST') ?: 'srv2216.hstgr.io');
define('DASHBOARD_DB_NAME', getenv('DASHBOARD_DB_NAME') ?: 'u689131217_dashboard_db');
define('DASHBOARD_DB_USER', getenv('DASHBOARD_DB_USER') ?: 'u689131217_dashboard_user');
define('DASHBOARD_DB_PASS', getenv('DASHBOARD_DB_PASS') !== false ? getenv('DASHBOARD_DB_PASS') : 'ao3P;k~OD+1');

// Application Settings
$httpScheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
if (empty($httpHost)) {
    $httpHost = 'rbfitness.in';
}

$isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']);
if (!$isLocal) {
    $httpScheme = 'https';
}

$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$hubRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

$hubSubpath = '';
$publicHtmlPos = strpos($hubRoot, '/public_html');
if ($publicHtmlPos !== false) {
    $hubSubpath = substr($hubRoot, $publicHtmlPos + strlen('/public_html'));
} elseif (!empty($docRoot) && !empty($hubRoot) && strpos(strtolower($hubRoot), strtolower($docRoot)) === 0) {
    $hubSubpath = substr($hubRoot, strlen($docRoot));
}
$hubSubpath = str_replace('\\', '/', $hubSubpath);
$hubSubpath = '/' . ltrim($hubSubpath, '/');

$defaultBaseUrl = "{$httpScheme}://{$httpHost}{$hubSubpath}";
// On live server (Hostinger): HUB_BASE_URL auto-detects from HTTP_HOST — no manual config needed.
// Media URLs will be: https://yourdomain.com/hub/uploads/clients/{id}/filename.jpg
// Instagram/Facebook can fetch these directly without any tunnel.
define('HUB_BASE_URL', rtrim(getenv('HUB_BASE_URL') ?: $defaultBaseUrl, '/'));
// PUBLIC_TUNNEL_URL removed — only needed for local dev with tunnels. Not required on live server.

// 256-bit key for AES-256-CBC encryption (should be 32 bytes)
define('ENCRYPTION_KEY', getenv('HUB_ENCRYPTION_KEY') ?: 'd41d8cd98f00b204e9800998ecf8427e');

// Storage Bucket Settings (Backblaze B2 + Cloudflare CDN)
define('B2_KEY_ID', getenv('HUB_B2_KEY_ID') ?: 'placeholder_b2_key_id');
define('B2_APPLICATION_KEY', getenv('HUB_B2_APPLICATION_KEY') ?: 'placeholder_b2_app_key');
define('B2_BUCKET_NAME', getenv('HUB_B2_BUCKET_NAME') ?: 'placeholder_bucket_name');
define('CF_MEDIA_DOMAIN', rtrim(getenv('HUB_CF_MEDIA_DOMAIN') ?: 'https://media.ourcompany.com', '/'));

// Queue and Worker Settings
define('QUEUE_BATCH_SIZE', (int) (getenv('HUB_QUEUE_BATCH_SIZE') ?: 10));

// Scheduler & Cron Settings
define('CRON_SECRET', getenv('HUB_CRON_SECRET') ?: 'cron_secret_token_12345!');
define('MAX_RETRIES', 0);  // Disable retries, fail immediately on error
define('RETRY_BACKOFF_FACTOR', (int) (getenv('HUB_RETRY_BACKOFF_FACTOR') ?: 1));  // in minutes
define('HTTP_TIMEOUT', (int) (getenv('HUB_HTTP_TIMEOUT') ?: 30));  // in seconds

// Webhook Handshake Verification Token
define('WHATSAPP_VERIFY_TOKEN', getenv('HUB_WHATSAPP_VERIFY_TOKEN') ?: 'my_whatsapp_webhook_verify_token');

// Shared Admin Master Key for Dashboard Client-Management Integration
define('HUB_ADMIN_MASTER_KEY', getenv('HUB_ADMIN_MASTER_KEY') ?: 'admin_master_secret_token_change_me');

// Agency main dashboard host domain (change this to your production domain, e.g. 'rbfitness.in')
define('AGENCY_DASHBOARD_DOMAIN', 'rbfitness.in');
