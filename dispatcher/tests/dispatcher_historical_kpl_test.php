<?php declare(strict_types=1);

/**
 * Historical KPL lookup against the production fuel_logs shape: every alias
 * column present (fuel_log_id, ending_odo/ending_odometer/end_km, ...), kpl
 * and measurement evidence recorded. Pure database fixture - no app DB.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../../dispatcher/api/helpers.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE fuel_logs (
    fuel_log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_id TEXT,
    driver_id TEXT,
    trip_id INTEGER,
    liters REAL,
    cost REAL,
    price_per_liter REAL,
    fuel_proof_url TEXT,
    starting_odo REAL,
    ending_odo REAL,
    created_at TEXT,
    updated_at TEXT,
    starting_odometer REAL,
    ending_odometer REAL,
    start_km REAL,
    end_km REAL,
    distance_km REAL,
    kpl REAL,
    distance_traveled REAL,
    expected_fuel REAL,
    is_full_tank INTEGER,
    notes TEXT,
    odo_photo_url TEXT
)');

$insert = $db->prepare('INSERT INTO fuel_logs
    (fuel_log_id, bus_id, driver_id, trip_id, liters, cost, price_per_liter, fuel_proof_url,
     starting_odo, ending_odo, created_at, updated_at,
     starting_odometer, ending_odometer, start_km, end_km,
     distance_km, kpl, distance_traveled, expected_fuel, is_full_tank, notes, odo_photo_url)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$insert->execute([1692, 'BUS-2107', 'DRV-004', 1692, 5.36, 0.00, 57.95, null, 81977.60, 82015.60, '2026-01-02 10:15:36', '2026-01-02 10:15:36', 81977.60, 82015.60, 81977.60, 82015.60, 38.00, 7.09, 38.00, 5.36, 0, null, null]);
$insert->execute([1743, 'BUS-2107', 'DRV-019', 1743, 1.49, 0.00, 58.10, null, 82015.60, 82026.00, '2026-01-07 09:41:15', '2026-01-07 09:41:15', 82015.60, 82026.00, 82015.60, 82026.00, 10.40, 6.98, 10.40, 1.49, 0, null, null]);
$insert->execute([1763, 'BUS-2107', 'DRV-004', 1763, 1.34, 0.00, 58.16, null, 82026.00, 82036.40, '2026-01-09 06:20:26', '2026-01-09 06:20:26', 82026.00, 82036.40, 82026.00, 82036.40, 10.40, 7.76, 10.40, 1.34, 0, null, null]);
$insert->execute([1792, 'BUS-2107', 'DRV-004', 1792, 1.41, 0.00, 58.26, null, 82036.40, 82046.80, '2026-01-12 09:46:19', '2026-01-12 09:46:19', 82036.40, 82046.80, 82036.40, 82046.80, 10.40, 7.38, 10.40, 1.41, 0, null, null]);

$historical = dispatcher_bus_historical_kpl($db, 'BUS-2107');
check($historical !== null, 'The production-shaped rows yield a historical KPL');
check(abs($historical['kpl'] - 7.38) < 0.0001, 'The latest qualifying row wins (7.38)');
check($historical['fuel_log_id'] === 1792, 'The source fuel log id is reported');
check($historical['recorded_at'] === '2026-01-12 09:46:19', 'The source date is reported');

/* A bus with only an awaiting-arrival dispatch row stays empty. */
$insert->execute([1793, 'BUS-NONE', null, 1793, 0.0, 0.00, 58.26, null, 82000.00, null, '2026-01-13 08:00:00', '2026-01-13 08:00:00', 82000.00, null, 82000.00, null, null, null, null, null, 0, null, null]);
check(dispatcher_bus_historical_kpl($db, 'BUS-NONE') === null, 'A row without kpl or measurement evidence is not a historical KPL');

/* Zero KPL never counts. */
$insert->execute([1794, 'BUS-ZERO', 'DRV-1', 1794, 1.0, 0.0, 58.0, null, 1.0, 11.0, '2026-01-13 08:00:00', '2026-01-13 08:00:00', 1.0, 11.0, 1.0, 11.0, 10.0, 0.0, 10.0, 1.0, 0, null, null]);
check(dispatcher_bus_historical_kpl($db, 'BUS-ZERO') === null, 'A zero KPL is not a historical measurement');

/* The dispatch form must never leave a silent dash when the reference call
   fails: it recovers stale app sessions and reports the failure in the field. */
$queueJs = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/assets/js/queue.js');
check(str_contains($queueJs, 'async function loadBusFuelInfo('), 'The dispatch form loads the bus fuel reference through one helper');
check(str_contains($queueJs, "recoverStaleApp(); return null;"), 'A stale app route recovers instead of leaving a dash');
check(str_contains($queueJs, "form.kpl_load_failed"), 'A failed reference load is shown in the field');

echo "$checks dispatcher historical KPL checks passed.\n";
