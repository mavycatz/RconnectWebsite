<?php
declare(strict_types=1);

/**
 * Rollback check for the generator's dispatch coverage writes.
 *
 * Reproduces the generated-batch transaction: trip, ride, fuel, remittance,
 * queue, and terminal-assignment rows are written inside one transaction and
 * an induced failure rolls the whole batch back, so a failed run leaves zero
 * partial rows behind. Uses the same sqlite harness pattern as the demo
 * account regression test; no live database is required.
 *
 * Run: php admin/tests/dispatch_coverage_rollback_test.php
 */

namespace App\Core {
    final class Database
    {
        public static ?\PDO $testPdo = null;

        public static function get(): \PDO
        {
            return self::$testPdo;
        }

        public static function tryGet(): ?\PDO
        {
            return self::$testPdo;
        }

        public static function reconnect(): \PDO
        {
            return self::$testPdo;
        }
    }
}

namespace {
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    putenv('APP_ENV=development');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/trip-history-generator.php';
    $_SESSION = [];
    session_save_path(sys_get_temp_dir());
    ini_set('session.use_cookies', '0');

    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('CREATE TABLE bus_dispatch_queue (
        operating_date TEXT NOT NULL,
        bus_id TEXT NOT NULL,
        queue_position INTEGER NOT NULL,
        published_at TEXT,
        published_by TEXT,
        terminal_id INTEGER,
        created_at TEXT,
        updated_at TEXT,
        PRIMARY KEY (operating_date, bus_id)
    )');
    $db->exec('CREATE TABLE driver_terminal_assignments (
        operating_date TEXT NOT NULL,
        driver_id TEXT NOT NULL,
        terminal_id INTEGER NOT NULL,
        created_at TEXT,
        updated_at TEXT,
        PRIMARY KEY (operating_date, driver_id)
    )');
    $db->exec('CREATE TABLE bus_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT)');
    $db->exec('CREATE TABLE passenger_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, fare REAL)');
    $db->exec('CREATE TABLE fuel_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, liters REAL)');
    $db->exec('CREATE TABLE remittance_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, total_collection REAL)');
    \App\Core\Database::$testPdo = $db;

    ob_start();
    require dirname(__DIR__, 2) . '/trip-history-generator.php';
    ob_end_clean();

    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    $checks = 0;
    $check = static function (bool $ok, string $message) use (&$checks): void {
        if (!$ok) {
            throw new RuntimeException($message);
        }
        $checks++;
    };

    $buses = [];
    $busByNumber = [];
    $drivers = [];
    for ($i = 1; $i <= 6; $i++) {
        $busId = 'BUS-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $row = ['bus_id' => $busId, 'status' => 'Active', 'from_terminal_id' => $i === 1 ? 7 : null];
        $buses[] = $row;
        $busByNumber[$busId] = $row;
        $drivers[] = ['driver_id' => 'DRV-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'status' => 'Active'];
    }

    $ctx = [
        'buses' => $buses,
        'busByNumber' => $busByNumber,
        'drivers' => $drivers,
        'coverageTables' => ['queue' => true, 'terminals' => true, 'missing' => ['queue' => [], 'terminals' => []]],
        'coverageTerminals' => [7, 8],
        'auditActors' => ['dispatcher' => []],
    ];
    $opts = ['fleet_scope' => false];
    $day = '2026-10-12';
    $today = '2026-10-16';
    $trips = [
        ['busNumber' => 'BUS-0001', 'drv' => ['driver_id' => 'DRV-001'], 'sec' => 18000, 'departure' => '05:00:00'],
        ['busNumber' => 'BUS-0002', 'drv' => ['driver_id' => 'DRV-002'], 'sec' => 19200, 'departure' => '05:20:00'],
    ];
    $stats = ['dispatch_queue_rows' => 0, 'terminal_assignment_rows' => 0];
    $coverageDays = [];

    $inside = ['queue' => null, 'assign' => null, 'source' => null];
    $db->beginTransaction();
    try {
        $db->exec("INSERT INTO bus_trips (status) VALUES ('Completed')");
        $db->exec('INSERT INTO passenger_trips (fare) VALUES (25.0)');
        $db->exec('INSERT INTO fuel_logs (liters) VALUES (3.5)');
        $db->exec('INSERT INTO remittance_logs (total_collection) VALUES (100.0)');
        writeDayCoverage($db, $ctx, $opts, $day, $trips, $today, $stats, $coverageDays);
        $inside['queue'] = (int)$db->query('SELECT COUNT(*) FROM bus_dispatch_queue')->fetchColumn();
        $inside['assign'] = (int)$db->query('SELECT COUNT(*) FROM driver_terminal_assignments')->fetchColumn();
        $inside['source'] = $coverageDays[$day]['queue_source'] ?? null;
        throw new RuntimeException('induced failure after the coverage write');
    } catch (RuntimeException $e) {
        $db->rollBack();
    }

    $check($inside['source'] === 'generated', 'Coverage rows were written before the induced failure');
    $check($inside['queue'] === 6 && $inside['assign'] === 6, 'The day writes its lineup and terminal assignments');
    $check($stats['dispatch_queue_rows'] === 6 && $stats['terminal_assignment_rows'] === 6, 'Coverage counters match the written rows');

    foreach (['bus_trips', 'passenger_trips', 'fuel_logs', 'remittance_logs', 'bus_dispatch_queue', 'driver_terminal_assignments'] as $table) {
        $count = (int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $check($count === 0, "Rollback leaves no partial rows in $table");
    }

    echo "$checks dispatch coverage rollback checks passed.\n";
}
