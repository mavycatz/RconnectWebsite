<?php

/**
 * Dispatcher OTP helpers.
 *
 * Pure server-side helpers shared by the dispatcher login 2FA and the
 * forgot-password reset flow:
 *   - cryptographically secure OTP generation;
 *   - input normalization/validation;
 *   - expiration + attempt-limit verification (brute-force protection);
 *   - per-session send rate limiting and resend cooldown.
 *
 * These functions never touch the database and never echo output.
 */

if (!function_exists('dispatcher_otp_generate')) {
    /**
     * Cryptographically secure 6-digit OTP.
     */
    function dispatcher_otp_generate(): string
    {
        return (string) random_int(100000, 999999);
    }
}

if (!function_exists('dispatcher_otp_normalize')) {
    /**
     * Digits only (drops spaces, dashes, and any non-numeric input).
     */
    function dispatcher_otp_normalize($raw): string
    {
        return (string) preg_replace('/\D/', '', (string) $raw);
    }
}

if (!function_exists('dispatcher_otp_verify')) {
    /**
     * Verify a submitted OTP against the stored one.
     *
     * Every submission counts as an attempt. Once the attempts exceed
     * $maxAttempts the caller must invalidate the OTP.
     *
     * @return array{status:string,attempts:int}
     *   status: missing|expired|format|invalid|too_many|ok
     */
    function dispatcher_otp_verify(
        $enteredRaw,
        $stored,
        int $createdAt,
        int $attemptsSoFar,
        int $ttlSeconds = 300,
        int $maxAttempts = 5
    ): array {
        if ($stored === null || $stored === '') {
            return ['status' => 'missing', 'attempts' => $attemptsSoFar];
        }

        if ($createdAt > 0 && (time() - $createdAt) > $ttlSeconds) {
            return ['status' => 'expired', 'attempts' => $attemptsSoFar];
        }

        $attempts = $attemptsSoFar + 1;

        $entered = dispatcher_otp_normalize($enteredRaw);

        if (strlen($entered) !== 6) {
            if ($attempts > $maxAttempts) {
                return ['status' => 'too_many', 'attempts' => $attempts];
            }
            return ['status' => 'format', 'attempts' => $attempts];
        }

        if ($attempts > $maxAttempts) {
            return ['status' => 'too_many', 'attempts' => $attempts];
        }

        if (hash_equals((string) $stored, $entered)) {
            return ['status' => 'ok', 'attempts' => $attempts];
        }

        return ['status' => 'invalid', 'attempts' => $attempts];
    }
}

if (!function_exists('dispatcher_otp_verify_remaining')) {
    /**
     * Attempts left for a given flow (never negative).
     */
    function dispatcher_otp_verify_remaining(int $attempts, int $maxAttempts = 5): int
    {
        return max(0, $maxAttempts - $attempts);
    }
}

if (!function_exists('dispatcher_otp_throttle_check')) {
    /**
     * Whether a new OTP may be sent right now.
     *
     * @return string|null Null when allowed, otherwise a user-facing message.
     */
    function dispatcher_otp_throttle_check(
        string $bucket,
        int $max = 5,
        int $windowSeconds = 900,
        int $cooldownSeconds = 60
    ): ?string {
        $now = time();

        $lastKey = 'otp_last_' . $bucket;
        $last = (int) ($_SESSION[$lastKey] ?? 0);
        if ($last > 0 && ($now - $last) < $cooldownSeconds) {
            $wait = $cooldownSeconds - ($now - $last);
            return 'Please wait ' . $wait . 's before requesting another code.';
        }

        $listKey = 'otp_hits_' . $bucket;
        $hits = $_SESSION[$listKey] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }

        $hits = array_values(array_filter($hits, function ($t) use ($now, $windowSeconds) {
            return ($now - (int) $t) < $windowSeconds;
        }));

        $_SESSION[$listKey] = $hits;

        if (count($hits) >= $max) {
            return 'Too many OTP requests. Please try again later.';
        }

        return null;
    }
}

if (!function_exists('dispatcher_otp_throttle_note')) {
    /**
     * Record a send attempt (successful or not) for rate limiting.
     */
    function dispatcher_otp_throttle_note(string $bucket): void
    {
        $now = time();

        $_SESSION['otp_last_' . $bucket] = $now;

        $listKey = 'otp_hits_' . $bucket;
        $hits = $_SESSION[$listKey] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }
        $hits[] = $now;
        $_SESSION[$listKey] = $hits;
    }
}
