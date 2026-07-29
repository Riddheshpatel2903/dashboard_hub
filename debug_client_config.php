<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/dashboard/config/config.php';

echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET') . "\n";
echo "DASHBOARD_BASE_URL: " . DASHBOARD_BASE_URL . "\n";
echo "HUB_BASE_URL: " . HUB_BASE_URL . "\n";
