<?php

namespace App\Passenger;

use PDO;

class PassengerSessionService
{
    public static function ensureSchema(?PDO $conn = null): void
    {
        if (!function_exists('passenger_ensure_hardening_schema')) {
            require_once dirname(__DIR__) . '/database/hardening_schema.php';
        }
        passenger_ensure_hardening_schema($conn);

        if (!function_exists('passenger_ensure_trusted_device_schema')) {
            require_once dirname(__DIR__) . '/database/trusted_device_schema.php';
        }
        passenger_ensure_trusted_device_schema($conn);
    }

    public static function trustedDevice(PDO $conn, int $passengerId): ?array
    {
        if ($passengerId <= 0) {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT selector, device_label, user_agent, created_at, last_used_at, expires
                FROM passenger_remember_tokens
                WHERE passenger_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$passengerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('Passenger trustedDevice read failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function friendlyDeviceLabel(?string $userAgent, ?string $storedLabel = null): string
    {
        $label = trim((string) $storedLabel);
        if ($label !== '' && stripos($label, 'Mozilla') !== 0) {
            return $label;
        }

        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return $label;
        }

        $browser = 'Browser';
        if (preg_match('/Edg\//i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\//i', $ua) || preg_match('/Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/Chrome\//i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\//i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\//i', $ua)) {
            $browser = 'Safari';
        }

        $os = '';
        if (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
            $os = 'Mac';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        return $os !== '' ? ($browser . ' on ' . $os) : $browser;
    }

    public static function revokeTrustedDevice(PDO $conn, int $passengerId): void
    {
        if ($passengerId <= 0) {
            return;
        }

        try {
            $conn->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ?")
                ->execute([$passengerId]);
        } catch (\Throwable $e) {
            error_log('Passenger revokeTrustedDevice failed: ' . $e->getMessage());
        }
    }

    public static function activateDevice(PDO $conn, int $passengerId, string $reason = 'login'): void
    {
        if ($passengerId <= 0) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $hash = hash('sha256', (string) session_id());

        try {
            $conn->beginTransaction();

            $conn->prepare("
                UPDATE passenger_sessions
                SET revoked_at = NOW(), revoked_reason = ?
                WHERE passenger_id = ? AND revoked_at IS NULL
            ")->execute([$reason, $passengerId]);

            $conn->prepare("DELETE FROM passenger_sessions WHERE session_hash = ?")
                ->execute([$hash]);

            $conn->prepare("
                INSERT INTO passenger_sessions (passenger_id, session_hash, device_label, ip_address)
                VALUES (?, ?, ?, ?)
            ")->execute([$passengerId, $hash, self::deviceLabel(), self::ipAddress()]);

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Passenger activateDevice failed: ' . $e->getMessage());
        }
    }

    public static function state(PDO $conn, int $passengerId): string
    {
        $hash = hash('sha256', (string) session_id());

        $stmt = $conn->prepare("
            SELECT ps.revoked_at, p.status
            FROM passenger_sessions ps
            JOIN passengers p ON p.id = ps.passenger_id
            WHERE ps.session_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (self::isDisabledStatus($row['status'] ?? null)) {
                return 'disabled';
            }
            return $row['revoked_at'] === null ? 'valid' : 'revoked';
        }

        $stmt = $conn->prepare("SELECT status FROM passengers WHERE id = ? LIMIT 1");
        $stmt->execute([$passengerId]);
        $status = $stmt->fetchColumn();

        if ($status === false) {
            return 'disabled';
        }

        if (self::isDisabledStatus($status)) {
            return 'disabled';
        }

        return 'legacy';
    }

    public static function adoptLegacy(PDO $conn, int $passengerId): void
    {
        $hash = hash('sha256', (string) session_id());

        try {
            $conn->prepare("DELETE FROM passenger_sessions WHERE session_hash = ?")->execute([$hash]);
            $conn->prepare("
                INSERT INTO passenger_sessions (passenger_id, session_hash, device_label, ip_address)
                VALUES (?, ?, ?, ?)
            ")->execute([$passengerId, $hash, self::deviceLabel(), self::ipAddress()]);
        } catch (\Throwable $e) {
            error_log('Passenger adoptLegacy failed: ' . $e->getMessage());
        }
    }

    public static function revokeAll(PDO $conn, int $passengerId, string $reason = 'revoked'): void
    {
        try {
            $conn->prepare("
                UPDATE passenger_sessions
                SET revoked_at = NOW(), revoked_reason = ?
                WHERE passenger_id = ? AND revoked_at IS NULL
            ")->execute([$reason, $passengerId]);

            $conn->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ?")
                ->execute([$passengerId]);
        } catch (\Throwable $e) {
            error_log('Passenger revokeAll failed: ' . $e->getMessage());
        }
    }

    public static function pruneRememberTokens(PDO $conn, int $passengerId, ?string $keepSelector): void
    {
        try {
            if ($keepSelector === null || $keepSelector === '') {
                $conn->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ?")
                    ->execute([$passengerId]);
                return;
            }

            $conn->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ? AND selector <> ?")
                ->execute([$passengerId, $keepSelector]);

            $conn->prepare("UPDATE passenger_remember_tokens SET expires = ? WHERE passenger_id = ? AND selector = ?")
                ->execute([date('Y-m-d H:i:s', time() + 86400 * 365), $passengerId, $keepSelector]);
        } catch (\Throwable $e) {
            error_log('Passenger pruneRememberTokens failed: ' . $e->getMessage());
        }
    }

    public static function killCurrentSession(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
            session_destroy();
        }
    }

    private static function isDisabledStatus($status): bool
    {
        // Restrictions retain authenticated notice/appeal access. Normal
        // services are fenced using the current account state in bootstrap.
        return strcasecmp((string)$status, 'Archived') === 0;
    }

    private static function deviceLabel(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return mb_substr($ua, 0, 255);
    }

    private static function ipAddress(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
