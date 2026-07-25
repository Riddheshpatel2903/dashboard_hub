<?php

/**
 * Dashboard Hub Root Router / Entry Point.
 * Automatically routes root server requests (e.g. http://localhost:8080/)
 * to the dashboard application interface.
 */
require_once __DIR__ . '/dashboard/config/config.php';

$loginUrl = (defined('DASHBOARD_BASE_URL') ? DASHBOARD_BASE_URL : '/dashboard') . '/index.php';
header('Location: ' . $loginUrl);
exit();
