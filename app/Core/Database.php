<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/* The class may be included through more than one path form (DOCUMENT_ROOT
   vs __DIR__). On hosts with a symlinked document root those do not dedupe,
   so guard the declaration instead of letting a second include fatal. */
if (class_exists('App\\Core\\Database', false)) {
    return;
}

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    private static function loadConfig(): array
    {
        if (empty(self::$config)) {
            self::$config = require __DIR__ . '/../config/database.php';
        }
        return self::$config;
    }

    public static function get(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    public static function reconnect(): PDO
    {
        self::$instance = self::createConnection();
        return self::$instance;
    }

    /**
     * Non-fatal connection accessor for read/analytics paths.
     *
     * Returns a connection or null instead of calling die() so callers can
     * degrade gracefully when the database is unreachable. get()/reconnect()
     * keep the historical hard-fail behavior for the rest of the app.
     */
    public static function tryGet(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        try {
            return self::$instance = self::connect();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function createConnection(): PDO
    {
        $config = self::loadConfig();
        try {
            return self::connect();
        } catch (PDOException $e) {
            if (isset($config['debug']) && $config['debug'] === true) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Database connection failed.');
        }
    }

    private static function connect(): PDO
    {
        $config = self::loadConfig();

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['db'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);

        // Align the connection collation with the collation the account tables
        // actually use. The DSN's charset pins utf8mb4 but leaves the collation
        // at the server default; on hosts where that differs from the legacy
        // tables (for example unicode_ci vs general_ci) every string literal
        // compared against a column raises error 1267 "Illegal mix of
        // collations", breaking list and lookup queries.
        self::applySessionCollation($pdo);

        // PDO::MYSQL_ATTR_INIT_COMMAND is deprecated since PHP 8.5 and its
        // notice can leak into API JSON output — set the session timeouts
        // with an explicit statement instead.
        try {
            $pdo->exec("SET SESSION wait_timeout = 28800, interactive_timeout = 28800");
        } catch (PDOException $e) {
            // Non-fatal: session timeouts are a nicety, not a requirement.
        }

        // Pin the session clock to Asia/Manila before any query runs.
        //
        // The driver GPS writer (mobile/driver/updateLocation.php) sets
        // +08:00 on its own connection, so driver_location_history.created_at
        // holds Philippine wall-clock time. Without this statement this
        // connection inherits the server's global time_zone, which is SYSTEM,
        // i.e. whatever the host OS is set to - commonly UTC. A Manila-written
        // row then gets differenced against a UTC NOW() and every live bus
        // reads exactly 8 hours old, which shows up as a spurious STALE and a
        // blocked destination alert.
        //
        // Setting the session clock is the fix rather than shifting stored
        // values or subtracting hours during display: the timestamp in the
        // column and the clock it is compared against then come from the same
        // basis, so the difference really is elapsed time.
        self::applySessionTimezone($pdo);

        return $pdo;
    }

    /**
     * Pin the connection collation to the one the account tables use.
     *
     * Detected from a few core columns (all legacy tables share one
     * collation). Falls back to utf8mb4_general_ci, then unicode_ci. Failure
     * is non-fatal: the connection keeps the server default, exactly the
     * behavior before this method existed.
     */
    private static function applySessionCollation(PDO $pdo): void
    {
        $targets = [
            ['users', 'role'],
            ['users', 'username'],
            ['drivers', 'driver_id'],
            ['inspector_accounts', 'inspector_id'],
            ['drivers', 'status'],
        ];

        $collation = '';
        foreach ($targets as $target) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT COLLATION_NAME FROM information_schema.COLUMNS'
                    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
                );
                $stmt->execute([$target[0], $target[1]]);
                $found = (string)($stmt->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $found = '';
            }
            if ($found !== '') {
                $collation = $found;
                break;
            }
        }

        $candidates = [];
        if ($collation !== '' && preg_match('/^utf8mb4_[A-Za-z0-9_]+$/', $collation)) {
            $candidates[] = $collation;
        }
        $candidates[] = 'utf8mb4_general_ci';
        $candidates[] = 'utf8mb4_unicode_ci';

        foreach (array_unique($candidates) as $candidate) {
            try {
                $pdo->exec('SET NAMES utf8mb4 COLLATE ' . $candidate);
                return;
            } catch (\Throwable $e) {
                continue;
            }
        }

        error_log(
            '[App\Core\Database] Could not align the session collation;'
            . ' mixed-collation comparisons may fail.'
        );
    }

    /**
     * Apply the application timezone to a connection and align PHP's default
     * timezone with it, so date()/time()/strtotime() and SQL NOW() agree.
     *
     * The named zone is tried first so the offset keeps following the
     * Philippines' rules if they ever change, but it is not relied on: many
     * MySQL installs - including a stock XAMPP - ship without the timezone
     * tables loaded, and `SET time_zone = 'Asia/Manila'` then fails with
     * error 1298 "Unknown or incorrect time zone". The numeric offset always
     * works and is exactly equivalent for the Philippines, so it is the
     * guaranteed path. Both are attempted independently, because a throwing
     * statement must not skip the fallback.
     */
    private static function applySessionTimezone(PDO $pdo): void
    {
        $config = self::loadConfig();
        $timezone = $config['timezone'] ?? (defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
        $offset = defined('APP_UTC_OFFSET') ? APP_UTC_OFFSET : '+08:00';

        // PHP-side first: every later date() call in the request must read
        // Philippine time even if both session statements below fail.
        if (date_default_timezone_get() !== $timezone) {
            date_default_timezone_set($timezone);
        }

        $applied = null;

        foreach ([$timezone, $offset] as $candidate) {
            try {
                $stmt = $pdo->prepare("SET time_zone = ?");
                $stmt->execute([$candidate]);
                $applied = $candidate;
                break;
            } catch (\Throwable $e) {
                // Unknown zone on a server without the timezone tables, or no
                // permission to set it. Try the next candidate.
                continue;
            }
        }

        if ($applied === null) {
            // Neither worked. PHP's default timezone is still correct above, so
            // the PHP-side age computation stays on Philippine time, but the
            // session clock is the server's and must not be trusted for
            // comparisons. Surfaced so it can be seen rather than guessed at.
            error_log(
                '[App\Core\Database] Could not pin the MySQL session to ' . $timezone
                . ' or ' . $offset . '; the session clock is the server default.'
            );
        }
    }
}
