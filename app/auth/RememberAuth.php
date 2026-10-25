<?php

namespace App\Auth;

require_once __DIR__ . '/../Core/Database.php';
require_once dirname(__DIR__, 2) . '/passenger/services/PassengerSessionService.php';

use App\Core\Database;
use PDO;

class RememberAuth
{
    public static function loginFromCookie(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // already logged in
        if (Auth::check()) {
            return true;
        }

        // no cookie
        if (empty($_COOKIE['remember'])) {
            return false;
        }

        $parts = explode(':', $_COOKIE['remember']);

        // invalid format
        if (count($parts) !== 2) {
            return false;
        }

        [$selector, $token] = $parts;

        $pdo = Database::get();

        // find token
        $stmt = $pdo->prepare("
            SELECT *
            FROM remember_tokens
            WHERE selector = ?
            AND expires > NOW()
            LIMIT 1
        ");

        $stmt->execute([$selector]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        // token mismatch
        if (
            !hash_equals(
                $record['token_hash'],
                $tokenHash
            )
        ) {
            return false;
        }

        // get user
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $record['user_id']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // regenerate session
        session_regenerate_id(true);

        // IMPORTANT
        Auth::login(
            $user['id'],
            $user['role'],
            $user['username']
        );

        return true;
    }

    private static function passengerTokenColumns(PDO $pdo): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];

        try {
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'passenger_remember_tokens'
            ");
            $stmt->execute();
            while ($name = $stmt->fetchColumn()) {
                $cache[strtolower((string) $name)] = true;
            }
        } catch (\Throwable $e) {
        }

        return $cache;
    }

    private static function deviceUserAgent(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return function_exists('mb_substr') ? mb_substr($ua, 0, 255) : substr($ua, 0, 255);
    }

    private static function deviceLabel(): string
    {
        if (class_exists('App\\Passenger\\PassengerSessionService')) {
            $friendly = \App\Passenger\PassengerSessionService::friendlyDeviceLabel((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if ($friendly !== '') {
                return $friendly;
            }
        }
        return self::deviceUserAgent();
    }

    private static function passengerRow(PDO $pdo, int $passengerId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM passengers WHERE id = ? LIMIT 1");
        $stmt->execute([$passengerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function writePassengerCookie(string $token): void
    {
        $secure = function_exists('passenger_is_https')
            ? passenger_is_https()
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_passenger', $token, time() + 86400 * 365, '/', '', $secure, true);
    }

    private static function passengerIdForSelector(string $tokenString): ?int
    {
        $parts = explode(':', $tokenString);
        if (count($parts) !== 2 || $parts[0] === '') {
            return null;
        }

        try {
            $pdo = Database::get();
            $stmt = $pdo->prepare("SELECT passenger_id FROM passenger_remember_tokens WHERE selector = ? LIMIT 1");
            $stmt->execute([$parts[0]]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            error_log('Passenger selector lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function renewPassengerToken(PDO $pdo, int $passengerId): void
    {
        try {
            $cols = self::passengerTokenColumns($pdo);
            $sets = ["expires = ?"];
            $params = [date('Y-m-d H:i:s', time() + 86400 * 365)];

            if (isset($cols['last_used_at'])) {
                $sets[] = "last_used_at = ?";
                $params[] = date('Y-m-d H:i:s');
            }

            $params[] = $passengerId;
            $pdo->prepare("
                UPDATE passenger_remember_tokens
                SET " . implode(', ', $sets) . "
                WHERE passenger_id = ?
            ")->execute($params);
        } catch (\Throwable $e) {
            error_log('Passenger token renewal failed: ' . $e->getMessage());
        }
    }

    public static function loginFromPassengerCookie(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('passenger_session_start')) {
                passenger_session_start();
            } else {
                $sessionPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/sessions';
                if (!is_dir($sessionPath)) {
                    @mkdir($sessionPath, 0777, true);
                }
                ini_set('session.save_path', $sessionPath);
                session_start();
            }
        }

        if (isset($_SESSION['passenger_id'])) {
            return true;
        }

        if (empty($_COOKIE['remember_passenger'])) {
            return false;
        }

        $tokenString = (string) $_COOKIE['remember_passenger'];
        $passengerId = self::passengerIdForSelector($tokenString);
        if ($passengerId === null) {
            return false;
        }

        $record = self::validatePassengerToken($tokenString, $passengerId);
        if ($record === null) {
            return false;
        }

        $pdo = Database::get();
        $passenger = self::passengerRow($pdo, $passengerId);
        if ($passenger === null) {
            return false;
        }

        $_SESSION['passenger_id'] = $passenger['id'];
        $_SESSION['passenger_name'] = $passenger['full_name'];
        unset($_SESSION['is_one_time']);

        \App\Passenger\PassengerSessionService::activateDevice($pdo, (int) $passenger['id'], 'remember_login');

        /* Re-issuing the remember cookie is a convenience: a token-storage
           failure must not fail the login that is otherwise complete. */
        try {
            self::writePassengerCookie(self::createPassengerToken((int) $passenger['id']));
        } catch (\Throwable $e) {
            error_log('Passenger remember-token reissue failed: ' . $e->getMessage());
        }

        return true;
    }

    public static function createPassengerToken(int $passengerId): string
    {
        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + 86400 * 365);

        $pdo = Database::get();
        $pdo->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ?")->execute([$passengerId]);

        $cols = self::passengerTokenColumns($pdo);
        $fields = ['passenger_id', 'selector', 'token_hash', 'expires'];
        $values = [$passengerId, $selector, $tokenHash, $expires];

        if (isset($cols['device_label'])) {
            $fields[] = 'device_label';
            $values[] = self::deviceLabel();
        }
        if (isset($cols['user_agent'])) {
            $fields[] = 'user_agent';
            $values[] = self::deviceUserAgent();
        }
        if (isset($cols['created_at'])) {
            $fields[] = 'created_at';
            $values[] = $now;
        }
        if (isset($cols['last_used_at'])) {
            $fields[] = 'last_used_at';
            $values[] = $now;
        }
        if (isset($cols['rotated_at'])) {
            $fields[] = 'rotated_at';
            $values[] = $now;
        }

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $pdo->prepare("
            INSERT INTO passenger_remember_tokens (" . implode(', ', $fields) . ")
            VALUES ($placeholders)
        ");
        $stmt->execute($values);

        return "$selector:$token";
    }

    public static function validatePassengerToken(string $tokenString, int $passengerId): ?array
    {
        if ($tokenString === '' || $passengerId <= 0) {
            return null;
        }

        $parts = explode(':', $tokenString);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$selector, $token] = $parts;

        try {
            $pdo = Database::get();
            $cols = self::passengerTokenColumns($pdo);

            $where = "selector = ? AND expires > NOW()";
            if (isset($cols['revoked_at'])) {
                $where .= " AND revoked_at IS NULL";
            }

            $stmt = $pdo->prepare("
                SELECT * FROM passenger_remember_tokens
                WHERE $where
                LIMIT 1
            ");
            $stmt->execute([$selector]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return null;
            }

            if ((int) $record['passenger_id'] !== $passengerId) {
                return null;
            }

            if (!hash_equals((string) $record['token_hash'], hash('sha256', $token))) {
                return null;
            }

            $statusStmt = $pdo->prepare("SELECT status FROM passengers WHERE id = ? LIMIT 1");
            $statusStmt->execute([$passengerId]);
            $status = $statusStmt->fetchColumn();

            if ($status === false || in_array((string) $status, ['Blocked', 'Suspended', 'Archived'], true)) {
                return null;
            }

            return $record;
        } catch (\Throwable $e) {
            error_log('Passenger trusted token validation failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function deletePassengerToken(int $passengerId): void
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare("DELETE FROM passenger_remember_tokens WHERE passenger_id = ?");
        $stmt->execute([$passengerId]);
    }

    public static function loginFromTokenString(string $tokenString): bool
    {
        $passengerId = self::passengerIdForSelector($tokenString);
        if ($passengerId === null) {
            return false;
        }

        $record = self::validatePassengerToken($tokenString, $passengerId);
        if ($record === null) {
            return false;
        }

        $pdo = Database::get();
        $passenger = self::passengerRow($pdo, $passengerId);
        if ($passenger === null) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('passenger_session_start')) {
                passenger_session_start();
            } else {
                session_start();
            }
        }

        $_SESSION['passenger_id'] = $passenger['id'];
        $_SESSION['passenger_name'] = $passenger['full_name'];
        unset($_SESSION['is_one_time']);

        \App\Passenger\PassengerSessionService::activateDevice($pdo, (int) $passenger['id'], 'token_login');
        self::renewPassengerToken($pdo, $passengerId);

        return true;
    }
}