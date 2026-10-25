<?php

/**
 * PaymongoConfig
 *
 * Server-side access to the PayMongo configuration
 * (app/config/paymongo.php), which is env-driven and mode-aware. The active
 * mode ('test' or 'live') selects which credential section all API calls and
 * webhook verifications use. Fail-closed: when the integration is disabled or
 * the active mode's keys are missing, isConfigured() returns false and callers
 * must NOT attempt a checkout or process webhooks. In live mode a checkout is
 * additionally refused unless the request arrives over HTTPS.
 *
 * The secret key and webhook secret are never echoed or logged in full.
 */
class PaymongoConfig
{
    /** @var array|null */
    private static $config = null;

    /** @var bool */
    private static $loaded = false;

    /**
     * Load (once) and return the config array from app/config/paymongo.php.
     */
    public static function all(): array
    {
        if (self::$loaded) {
            return self::$config;
        }

        self::$config = [];
        self::$loaded = true;

        $path = (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/app/config/paymongo.php';

        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                self::$config = $loaded;
            }
        }

        return self::$config;
    }

    public static function isEnabled(): bool
    {
        return !empty(self::all()['enabled']);
    }

    /**
     * Active mode: 'test' or 'live'. Any configured value other than 'live'
     * is treated as 'test' (safe default - never guess live).
     */
    public static function mode(): string
    {
        $mode = (string) (self::all()['mode'] ?? 'test');
        return $mode === 'live' ? 'live' : 'test';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    public static function activeSection(): string
    {
        return self::isLive() ? 'live' : 'test';
    }

    /**
     * Livemode flag PayMongo resources must carry to be accepted in the
     * active mode (true = live, false = test).
     */
    public static function expectedLivemode(): bool
    {
        return self::isLive();
    }

    /**
     * Fully configured for the ACTIVE mode: enabled + that section's
     * public key + secret key + webhook signing secret, all non-empty.
     */
    public static function isConfigured(): bool
    {
        $section = self::all()[self::activeSection()] ?? [];

        return !empty(self::all()['enabled'])
            && !empty($section['public_key'])
            && !empty($section['secret_key'])
            && !empty($section['webhook_secret']);
    }

    /**
     * Checkout availability = full configuration, plus HTTPS in live mode.
     * A checkout cannot be created without the active mode's secret key;
     * webhook processing is refused without that mode's signing secret.
     */
    public static function canCheckout(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        if (self::isLive() && !self::isHttpsRequest()) {
            return false;
        }
        return true;
    }

    public static function publicKey(): string
    {
        return (string) (self::all()[self::activeSection()]['public_key'] ?? '');
    }

    public static function secretKey(): string
    {
        return (string) (self::all()[self::activeSection()]['secret_key'] ?? '');
    }

    public static function webhookSecret(): string
    {
        return (string) (self::all()[self::activeSection()]['webhook_secret'] ?? '');
    }

    /**
     * Freshness window (seconds) accepted for tempered webhook signatures
     * (t=<timestamp>,v1=<hex>). Defaults to 600 (10 minutes).
     */
    public static function webhookSignatureTtl(): int
    {
        $ttl = (int) (self::all()['webhook_signature_ttl'] ?? 600);
        return $ttl > 0 ? $ttl : 600;
    }

    /**
     * Optional token that gates the temporary pm_diag.php endpoint.
     * Empty string disables the diagnostic endpoint entirely.
     */
    public static function diagToken(): string
    {
        return (string) (self::all()['diag_token'] ?? '');
    }

    /**
     * QR Ph code lifetime in seconds (PayMongo range 60-9000).
     * Defaults to 60 (1 minute, the PayMongo minimum); out-of-range values
     * fall back to the default.
     */
    public static function qrPhExpirySeconds(): int
    {
        $expiry = (int) (self::all()['qrph_expiry_seconds'] ?? 60);
        return ($expiry >= 60 && $expiry <= 9000) ? $expiry : 60;
    }

    public static function webhookUrl(): string
    {
        return (string) (self::all()['webhook_url'] ?? '');
    }

    /**
     * Public HTTPS base URL used for checkout return_url.
     */
    public static function baseUrl(): string
    {
        return (string) (self::all()['base_url'] ?? '');
    }

    public static function apiBaseUrl(): string
    {
        return 'https://api.paymongo.com';
    }

    /**
     * True when the current request is (or is proxied as) HTTPS.
     * Behind a reverse proxy trust HTTP_X_FORWARDED_PROTO.
     */
    public static function isHttpsRequest(): bool
    {
        $https = (string) ($_SERVER['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if (stripos($forwarded, 'https') !== false) {
            return true;
        }
        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * Human-readable active-mode label (kept for compatibility).
     */
    public static function modeLabel(): string
    {
        return self::mode();
    }
}