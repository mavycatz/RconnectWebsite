<?php
declare(strict_types=1);

/**
 * Historical driver check-in checks for the generator.
 *
 * Verifies the attendance trail the history path writes: one `Driver Checked
 * In` accountability record per operating driver per generated past date,
 * timed before that driver's first departure; non-operating drivers and the
 * current date get none; a second emission in the same process is deduped;
 * and a rolled-back batch leaves no audit rows behind. Uses the same sqlite
 * harness pattern as the demo account test; no live database is required.
 *
 * Run: php admin/tests/driver_checkin_audit_test.php
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
    $db->exec('CREATE TABLE audit_trail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, username TEXT, user_role TEXT, module TEXT, action TEXT,
        record_id TEXT, description TEXT, previous_values TEXT, new_values TEXT,
        ip_address TEXT, user_agent TEXT, status TEXT, is_read INTEGER, created_at TEXT
    )');
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

    $today = date('Y-m-d');
    $day = date('Y-m-d', strtotime('-3 days'));

    $buses = [];
    $busByNumber = [];
    for ($i = 1; $i <= 4; $i++) {
        $busId = 'BUS-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $row = ['bus_id' => $busId, 'status' => 'Active', 'from_terminal_id' => null];
        $buses[] = $row;
        $busByNumber[$busId] = $row;
    }
    $drivers = [
        ['driver_id' => 'DRV-001', 'full_name' => 'Ana Reyes', 'status' => 'Active'],
        ['driver_id' => 'DRV-002', 'full_name' => 'Ben Cruz', 'status' => 'Active'],
        ['driver_id' => 'DRV-003', 'full_name' => 'Cara Diaz', 'status' => 'Active'],
    ];

    $ctx = [
        'buses' => $buses,
        'busByNumber' => $busByNumber,
        'drivers' => $drivers,
        'coverageTables' => ['queue' => true, 'terminals' => true, 'missing' => ['queue' => [], 'terminals' => []]],
        'coverageTerminals' => [7],
        'auditActors' => [
            'dispatcher' => [
                ['id' => 7, 'username' => 'disp_ana', 'role' => 'dispatcher'],
            ],
        ],
    ];
    $opts = ['fleet_scope' => false];
    $trips = [
        ['id' => 'TRIP-9001', 'busNumber' => 'BUS-0001', 'drv' => ['driver_id' => 'DRV-001'], 'sec' => 18000, 'departure' => '05:00:00'],
        ['id' => 'TRIP-9002', 'busNumber' => 'BUS-0001', 'drv' => ['driver_id' => 'DRV-001'], 'sec' => 19500, 'departure' => '05:25:00'],
        ['id' => 'TRIP-9003', 'busNumber' => 'BUS-0002', 'drv' => ['driver_id' => 'DRV-002'], 'sec' => 18300, 'departure' => '05:05:00'],
    ];
    $stats = ['dispatch_queue_rows' => 0, 'terminal_assignment_rows' => 0];
    $coverageDays = [];

    $db->beginTransaction();

    writeDayCoverage($db, $ctx, $opts, $day, $trips, $today, $stats, $coverageDays);
    writeDayCoverage($db, $ctx, $opts, $today, $trips, $today, $stats, $coverageDays);

    $checkins = (array)($coverageDays[$day]['checkins'] ?? []);
    $queueRowsInside = (int)$db->query('SELECT COUNT(*) FROM bus_dispatch_queue')->fetchColumn();

    $emittedFirst = emitDriverCheckinEvents($db, $ctx, $coverageDays);
    $rows = $db->query("SELECT username, module, action, record_id, new_values, user_agent, created_at FROM audit_trail WHERE action = 'Driver Checked In' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $emittedSecond = emitDriverCheckinEvents($db, $ctx, $coverageDays);
    $rowsAfterSecond = (int)$db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Driver Checked In'")->fetchColumn();

    $db->rollBack();

    $rowsAfterRollback = (int)$db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Driver Checked In'")->fetchColumn();
    $queueRowsAfterRollback = (int)$db->query('SELECT COUNT(*) FROM bus_dispatch_queue')->fetchColumn();

    $check($queueRowsInside > 0, 'The past date writes its dispatch lineup before the rollback');
    $check($queueRowsAfterRollback === 0, 'Rollback removes the dispatch coverage rows');

    /* Collection: one check-in per operating driver, none for idle drivers,
       none for the current date, all before the driver's first departure. */
    $check(count($checkins) === 2, 'One check-in per operating driver');
    $check(isset($checkins['DRV-001']) && isset($checkins['DRV-002']), 'Operating drivers are collected');
    $check(!isset($checkins['DRV-003']), 'A driver without a trip gets no check-in');
    $check(empty($coverageDays[$today]['checkins'] ?? []), 'The current date gets no historical check-ins');
    $check(($coverageDays[$today]['queue_source'] ?? '') === 'interactive', 'The current date stays with the interactive workflow');
    $check($checkins['DRV-001']['bus'] === 'BUS-0001', 'The check-in carries the driver\'s bus');
    $check($checkins['DRV-001']['trip_id'] === 'TRIP-9001', 'The check-in carries the first trip of the day');
    $check($checkins['DRV-001']['at'] < $day . ' 05:00:00', 'DRV-001 checks in before the first departure');
    $check($checkins['DRV-001']['at'] >= $day . ' 04:30:00', 'Check-ins never precede 04:30');
    $check($checkins['DRV-002']['at'] < $day . ' 05:05:00', 'DRV-002 checks in before the first departure');
    $check(
        $checkins['DRV-001']['at'] !== $checkins['DRV-002']['at'],
        'Check-in times vary by driver'
    );

    /* Emission: attendance-trail records with the dispatcher payload shape. */
    $check($emittedFirst === 2, 'Both operating drivers produce a check-in event');
    $check(count($rows) === 2, 'Two Driver Checked In rows land in the audit trail');
    $check($rowsAfterSecond === 2 && $emittedSecond === 0, 'A second emission in the same process is deduped');

    $byDriver = [];
    foreach ($rows as $row) {
        $payload = json_decode((string)$row['new_values'], true);
        $check(is_array($payload), 'Check-in payloads are JSON');
        $check($row['module'] === 'Dispatcher' && $row['action'] === 'Driver Checked In', 'Check-ins use the dispatcher accountability trail');
        $check($row['user_agent'] === 'trip-history-generator', 'Check-ins carry the generator user agent');
        $check($row['username'] === 'disp_ana', 'Check-ins carry a real dispatcher account');
        $check(($payload['checked_in_by'] ?? '') === 'dispatcher', 'Payload marks the dispatcher check-in source');
        $check(($payload['checked_in_by_name'] ?? '') === 'disp_ana', 'Payload names the dispatcher who checked the driver in');
        $check(($payload['trip_date'] ?? '') === $day, 'Payload carries the trip date attendance reporting reads');
        $check(($payload['demo_generated'] ?? false) === true, 'Check-ins are marked as demo output');
        $check(($payload['checked_in_at'] ?? '') === $row['created_at'], 'Check-in time equals the audit row time');
        $byDriver[(string)($payload['driver_id'] ?? '')] = $payload;
    }
    $check(isset($byDriver['DRV-001']) && isset($byDriver['DRV-002']), 'Payloads carry the operating driver ids');
    $check(($byDriver['DRV-001']['bus_id'] ?? '') === 'BUS-0001', 'Payload carries the bus id');
    $check(($byDriver['DRV-001']['checked_in_at'] ?? '') < $day . ' 05:00:00', 'Emitted check-in times precede the first departure');
    $check(isset($byDriver['DRV-001']['driver_name']) && $byDriver['DRV-001']['driver_name'] === 'Ana Reyes', 'Payload carries the driver name');

    /* Rollback: the events disappear with the batch. */
    $check($rowsAfterRollback === 0, 'Rollback removes the emitted check-in events');

    echo "$checks driver check-in audit checks passed.\n";
}
