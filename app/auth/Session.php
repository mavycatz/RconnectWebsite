<?php

namespace App\Auth;

require_once __DIR__ . '/../Core/Database.php';

use App\Core\Database;
use SessionHandlerInterface;

class Session implements SessionHandlerInterface
{
    private const DEFAULT_TIMEOUT = 900;

    private static bool $booted = false;

    private $pdo;

    public static function start(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DO NOT START SESSIONS IN CLI / CRON
        |--------------------------------------------------------------------------
        */

        if (
            php_sapi_name() === 'cli'
        ) {
            return;
        }

        if (
            self::$booted
        ) {
            return;
        }

        if (
            session_status() === PHP_SESSION_ACTIVE
        ) {

            self::$booted = true;

            return;
        }

        $instance = new self();

        session_set_save_handler(
            $instance,
            true
        );

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();

        register_shutdown_function(function () {

            session_write_close();
        });

        self::$booted = true;

        $instance->checkInactivity();
    }

    public function __construct()
    {
        $this->pdo =
            Database::get();
    }

    public function open(
        $savePath,
        $sessionName
    ): bool {

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(
        $id
    ): string {

        $stmt =
            $this->pdo->prepare(
                "SELECT payload
                 FROM sessions
                 WHERE id = ?"
            );

        $stmt->execute([$id]);

        $row =
            $stmt->fetch();

        if (
            $row
            &&
            !empty($row['payload'])
        ) {

            return (string)
                $row['payload'];
        }

        return '';
    }

    public function write(
        $id,
        $data
    ): bool {

        if (
            $data === ''
        ) {

            return true;
        }

        $userId =
            $_SESSION['user']['id']
            ?? null;

        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? '';

        $ua =
            $_SERVER['HTTP_USER_AGENT']
            ?? '';

        $stmt =
            $this->pdo->prepare(

                "
                INSERT INTO sessions
                (
                    id,
                    payload,
                    last_activity,
                    user_id,
                    ip_address,
                    user_agent
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )

                ON DUPLICATE KEY UPDATE

                    payload =
                    VALUES(payload),

                    last_activity =
                    VALUES(last_activity),

                    user_id =
                    VALUES(user_id),

                    ip_address =
                    VALUES(ip_address),

                    user_agent =
                    VALUES(user_agent)
                "
            );

        return $stmt->execute([

            $id,

            $data,

            time(),

            $userId,

            $ip,

            $ua
        ]);
    }

    public function destroy(
        $id
    ): bool {

        $stmt =
            $this->pdo->prepare(
                "DELETE FROM sessions
                 WHERE id = ?"
            );

        return $stmt->execute([$id]);
    }

    public function gc(
        $max_lifetime
    ): int|false {

        $stmt =
            $this->pdo->prepare(

                "
                DELETE FROM sessions
                WHERE last_activity < ?
                "
            );

        $timeout = self::getTimeout();

        $stmt->execute([
            time() - $timeout
        ]);

        return $stmt->rowCount();
    }

    private static function getTimeout(): int
    {
        try {
            $db = Database::get();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                $custom = (int)$val;
                if ($custom > 0) {
                    return $custom;
                }
            }
        } catch (\Throwable $e) {}
        return self::DEFAULT_TIMEOUT;
    }

    private static function isIpBindingEnabled(): bool
    {
        try {
            $db = Database::get();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'session_ip_binding'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val === '1' || $val === 'true';
        } catch (\Throwable $e) {}
        return false;
    }

    private static function getLoginRedirect(): string
    {
        $keyPath = __DIR__ . '/../../public/config/portal_key.php';
        if (file_exists($keyPath)) {
            $key = require $keyPath;
            $secret = bin2hex(random_bytes(8));
            return "/$key?_=$secret&portal=$key";
        }
        return '/public/login/index.php';
    }

    private function checkInactivity(): void
    {
        if (
            !isset($_SESSION['user']) && !isset($_SESSION['passenger_id'])
        ) {
            return;
        }

        $timeout = self::getTimeout();

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                session_destroy();
                if (php_sapi_name() !== 'cli') {
                    header('Location: ' . self::getLoginRedirect());
                    exit;
                }
            }
        }

        if (self::isIpBindingEnabled() && isset($_SESSION['user'])) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $storedIp = $_SESSION['user']['ip_address'] ?? null;
            if ($storedIp !== null && $currentIp !== $storedIp) {
                session_destroy();
                if (php_sapi_name() !== 'cli') {
                    header('Location: ' . self::getLoginRedirect());
                    exit;
                }
            }
        }

        if (isset($_SESSION['user'])) {
            $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $storedUa = $_SESSION['user']['user_agent'] ?? null;
            if ($storedUa !== null && $currentUa !== $storedUa) {
                error_log('Session: User-Agent mismatch for user ' . ($_SESSION['user']['id'] ?? 'unknown'));
            }
        }

        $_SESSION['last_activity'] = time();
    }
}