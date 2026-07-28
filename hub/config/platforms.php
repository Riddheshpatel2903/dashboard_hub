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
        'redirect_uri' => 'https://rbfitness.in/new-site/hub/auth/callback_facebook.php',
    ],
    // Google OAuth is shared between YouTube and Google Business Profile APIs
    'google' => [
        'client_id' => getenv('HUB_GOOGLE_CLIENT_ID') ?: '408603801836-5h57orfrrhtsmr95nv2f4g9mrp273h77.apps.googleusercontent.com',
        'client_secret' => getenv('HUB_GOOGLE_CLIENT_SECRET') ?: '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',
        'redirect_uri_youtube' => 'https://rbfitness.in/new-site/hub/auth/callback_youtube.php',
        'redirect_uri_business' => 'https://rbfitness.in/new-site/hub/auth/callback_google_business.php',
    ],
    'linkedin' => [
        'client_id' => getenv('HUB_LINKEDIN_CLIENT_ID') ?: '77gdy7vv3ll3py',
        'client_secret' => getenv('HUB_LINKEDIN_CLIENT_SECRET') ?: 'WPL_AP1.xSIC4Pqf8CaOLjh9.oeqQxA==',
        'redirect_uri' => 'https://rbfitness.in/new-site/hub/auth/callback_linkedin.php',
    ],
];
