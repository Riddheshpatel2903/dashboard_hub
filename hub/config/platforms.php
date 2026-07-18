<?php

/**
 * Platform API credentials, secrets, and configurations.
 * Each entry points back to this Hub's own domain redirect URIs.
 */
require_once __DIR__ . '/config.php';

return [
    'facebook' => [
        'app_id' => getenv('HUB_FACEBOOK_APP_ID') ?: '2167118710745191',
        'app_secret' => getenv('HUB_FACEBOOK_APP_SECRET') ?: '1d8014e67eafffade7fd7bd01708a1a5',
        'graph_api_version' => getenv('HUB_FACEBOOK_API_VERSION') ?: 'v25.0',
        'redirect_uri' => HUB_BASE_URL . '/auth/callback_facebook.php',
    ],
    // Google OAuth is shared between YouTube and Google Business Profile APIs
    'google' => [
        'client_id' => getenv('HUB_GOOGLE_CLIENT_ID') ?: 'placeholder_google_client_id',
        'client_secret' => getenv('HUB_GOOGLE_CLIENT_SECRET') ?: 'placeholder_google_client_secret',
        'redirect_uri_youtube' => HUB_BASE_URL . '/auth/callback_youtube.php',
        'redirect_uri_business' => HUB_BASE_URL . '/auth/callback_google_business.php',
    ],
    'linkedin' => [
        'client_id' => getenv('HUB_LINKEDIN_CLIENT_ID') ?: 'placeholder_linkedin_client_id',
        'client_secret' => getenv('HUB_LINKEDIN_CLIENT_SECRET') ?: 'placeholder_linkedin_client_secret',
        'redirect_uri' => HUB_BASE_URL . '/auth/callback_linkedin.php',
    ],
];
