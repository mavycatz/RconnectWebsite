<?php

namespace Finance\Middleware;

class Csrf
{
    private const CSRF_SECRET_KEY = 'FINANCE_CSRF_SECRET';

    public static function generate(): string
    {
        $secret = self::getSecret();
        $token = hash_hmac('sha256', session_id(), $secret);
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function validate(): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        $token = '';
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);

        if (!empty($headers['x-csrf-token'])) {
            $token = $headers['x-csrf-token'];
        } elseif (!empty($_POST['_csrf_token'])) {
            $token = $_POST['_csrf_token'];
        }

        if (empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function validateOrFail(): void
    {
        if (self::validate()) {
            return;
        }

        $migrationMode = defined('CSRF_MIGRATION_MODE') && CSRF_MIGRATION_MODE;

        if ($migrationMode) {
            error_log('CSRF: Missing or invalid token from session ' . session_id() . ' — allowing during migration');
            return;
        }

        self::logCsrfFailure();

        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }

    private static function logCsrfFailure(): void
    {
        if (interface_exists('App\\Audit\\Audit', false) || class_exists('App\\Audit\\Audit')) {
            try {
                require_once __DIR__ . '/../../app/audit/Audit.php';
                $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
                \App\Audit\Audit::log(
                    'Finance',
                    'CSRFValidationFailed',
                    "CSRF validation failed for $uri",
                    $_SESSION['user']['id'] ?? null,
                    $_SESSION['user']['username'] ?? null,
                    $_SESSION['user']['role'] ?? null,
                    null,
                    'Failed'
                );
            } catch (\Throwable $e) {}
        }
    }

    private static function getSecret(): string
    {
        $secret = getenv(self::CSRF_SECRET_KEY);
        if (!$secret) {
            $secret = 'csrf_default_secret_change_me';
        }
        return $secret;
    }
}
