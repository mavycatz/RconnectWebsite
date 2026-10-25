<?php
declare(strict_types=1);

/**
 * Dashboard "Most active route" counting checks.
 *
 * A physical trip must be counted once per route, not once per
 * boarding/destination stop pair: a trip whose riders board and alight at
 * several segment pairs previously multiplied the route's trip count (a
 * 127-trip period reported a 790-trip route). Uses a sqlite harness; no live
 * database is required.
 *
 * Run: php admin/tests/dashboard_route_trips_test.php
 */

require_once __DIR__ . '/../models/DashboardModel.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec('CREATE TABLE fare_pricing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    route_name TEXT NOT NULL,
    destination TEXT NOT NULL,
    regular_fare REAL,
    student_fare REAL,
    special_fare REAL,
    display_order INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE passenger_trips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    passenger_id INTEGER,
    boarding_point TEXT,
    destination TEXT,
    trip_no TEXT,
    bus_no TEXT,
    boarding_time TEXT
)');
$db->exec('CREATE TABLE passenger_trip_history (
    history_id INTEGER PRIMARY KEY AUTOINCREMENT,
    boarding_point TEXT,
    destination TEXT,
    trip_id TEXT,
    trip_date TEXT,
    bus_number TEXT
)');

$fareRows = [
    ['Cabanatuan-Rizal', 'Cabanatuan Terminal', 1],
    ['Cabanatuan-Rizal', 'Talavera', 2],
    ['Cabanatuan-Rizal', 'Rizal Terminal', 3],
    ['Rizal-Baguio', 'Rizal Terminal', 1],
    ['Rizal-Baguio', 'Baguio Terminal', 2],
];
$insertFare = $db->prepare('INSERT INTO fare_pricing (route_name, destination, regular_fare, student_fare, special_fare, display_order) VALUES (?, ?, 25, 20, 20, ?)');
foreach ($fareRows as $row) {
    $insertFare->execute($row);
}

/* Trip 1001 rides three segment pairs and trip 1002 two: the route must
   count two trips, not five. */
$insertRide = $db->prepare('INSERT INTO passenger_trips (boarding_point, destination, trip_no, bus_no, boarding_time) VALUES (?, ?, ?, ?, ?)');
foreach ([
    ['Cabanatuan Terminal', 'Talavera', '1001'],
    ['Cabanatuan Terminal', 'Rizal Terminal', '1001'],
    ['Talavera', 'Rizal Terminal', '1001'],
    ['Cabanatuan Terminal', 'Talavera', '1002'],
    ['Talavera', 'Rizal Terminal', '1002'],
] as $ride) {
    $insertRide->execute([$ride[0], $ride[1], $ride[2], 'BUS-0001', '2026-09-05 08:00:00']);
}
/* A second route with one archived trip. */
$db->exec("INSERT INTO passenger_trip_history (boarding_point, destination, trip_id, trip_date, bus_number)
    VALUES ('Rizal Terminal', 'Baguio Terminal', 'ARCV-9001', '2026-09-06', 'BUS-0002')");
$db->exec("INSERT INTO passenger_trip_history (boarding_point, destination, trip_id, trip_date, bus_number)
    VALUES ('Rizal Terminal', 'Baguio Terminal', 'ARCV-9001', '2026-09-06', 'BUS-0002')");

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$model = new \Admin\Models\DashboardModel($db);
$mostActiveRoute = new ReflectionMethod($model, 'mostActiveRoute');

$result = $mostActiveRoute->invoke($model, ['fromDate' => '2026-09-01', 'toDate' => '2026-09-30']);
$check(is_array($result), 'A most active route is returned for the filtered period');
$check(($result['label'] ?? '') === 'Cabanatuan-Rizal', 'The route with the most distinct trips wins');
$check((int)($result['trips'] ?? 0) === 2, 'Each trip counts once per route regardless of segment pairs');
$check((int)($result['trips'] ?? 0) !== 5, 'Segment-pair multiplication no longer inflates the route count');

$outside = $mostActiveRoute->invoke($model, ['fromDate' => '2026-08-01', 'toDate' => '2026-08-31']);
$check($outside === null, 'A period without rides reports no route');

$monthFilter = $mostActiveRoute->invoke($model, ['month' => '9', 'year' => '2026']);
$check(($monthFilter['label'] ?? '') === 'Cabanatuan-Rizal', 'Month/year filters apply to the route count');
$check((int)($monthFilter['trips'] ?? 0) === 2, 'Month/year filtering keeps the distinct-trip count');

echo "$checks dashboard route trip checks passed.\n";
