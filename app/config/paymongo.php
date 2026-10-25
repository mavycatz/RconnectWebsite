<?php

/**
 * PayMongo configuration (mode-aware: test | live).
 *
 * All credentials come from environment variables loaded from the .env file
 * next to this file (app/config/Env.php). Keep test and live credentials in
 * separate, non-committed environment values. Never put a real secret key or
 * webhook secret in this file or any committed source.
 *
 * The secret key must never be exposed to the frontend or returned by any API.
 *
 * When `enabled` is false or the ACTIVE mode's keys are empty the integration
 * fails closed: no checkout is created and the payment page shows "unavailable".
 */
require_once __DIR__ . '/Env.php';

use App\Config\Env;

Env::load();

$mode = (string) (getenv('PAYMONGO_MODE') ?: 'test');
if ($mode !== 'live') {
    $mode = 'test';
}

$baseUrl = (string) (getenv('PAYMONGO_BASE_URL') ?: 'https://rconnecttms.online');
$webhookUrl = (string) (getenv('PAYMONGO_WEBHOOK_URL') ?: $baseUrl . '/passenger/webhooks/paymongo.php');

return [
    'enabled' => true,

    // Active mode: 'test' or 'live'. Any value other than 'live' is treated as 'test'.
    'mode' => $mode,

    // Public HTTPS base URL used for the checkout return_url.
    'base_url' => $baseUrl,

    // Public HTTPS URL PayMongo calls for events (must be reachable, HTTPS).
    'webhook_url' => $webhookUrl,

    // Freshness window (seconds) for tempered webhook signatures (t=...,v1=...).
    'webhook_signature_ttl' => (int) (getenv('PAYMONGO_WEBHOOK_SIGNATURE_TTL') ?: 600),

    // QR Ph code lifetime in seconds (PayMongo range 60-9000). Defaults to 1800
    // (30 minutes); out-of-range env values are rejected in favor of the default.
    'qrph_expiry_seconds' => (int) (getenv('PAYMONGO_QRPH_EXPIRY_SECONDS') ?: 60),

    // Optional token for the temporary pm_diag.php endpoint (?token=...).
    // Leave empty to disable the diagnostic endpoint.
    'diag_token' => (string) (getenv('PAYMONGO_DIAG_TOKEN') ?: ''),

    'test' => [
        'public_key'     => (string) (getenv('PAYMONGO_TEST_PUBLIC_KEY') ?: ''),
        'secret_key'     => (string) (getenv('PAYMONGO_TEST_SECRET_KEY') ?: ''), // server-side only
        'webhook_secret' => (string) (getenv('PAYMONGO_TEST_WEBHOOK_SECRET') ?: ''),
    ],

    'live' => [
        'public_key'     => (string) (getenv('PAYMONGO_LIVE_PUBLIC_KEY') ?: ''),
        'secret_key'     => (string) (getenv('PAYMONGO_LIVE_SECRET_KEY') ?: ''), // server-side only
        'webhook_secret' => (string) (getenv('PAYMONGO_LIVE_WEBHOOK_SECRET') ?: ''),
    ],
];