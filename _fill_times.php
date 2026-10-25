<?php
/** 
 * Run once to fill random departure/arrival times (6 AM – 7 PM)
 * for passenger_trip_history AND passenger_trips where times 
 * are NULL or outside the 6 AM – 7 PM range.
 * Delete this file after running.
 */

require_once __DIR__ . '/app/init.php';
use App\Core\Database;

$db = Database::get();

function randomTimePair(): array {
    $depH = rand(6, 18);
    $depM = rand(0, 59);
    $depS = rand(0, 59);
    $travelMin = rand(30, 180);
    $depTotal = $depH * 3600 + $depM * 60 + $depS;
    $arrTotal = $depTotal + $travelMin * 60;
    if ($arrTotal > 68400) $arrTotal = 68400; // cap 19:00:00
    $dep = sprintf('%02d:%02d:%02d', $depH, $depM, $depS);
    $arr = sprintf('%02d:%02d:%02d', intdiv($arrTotal, 3600), intdiv($arrTotal % 3600, 60), $arrTotal % 60);
    return [$dep, $arr];
}

// ─── passenger_trip_history ──────────────────────────────
$count = $db->query(
    "SELECT COUNT(*) FROM passenger_trip_history 
     WHERE time_of_departure IS NULL 
        OR HOUR(time_of_departure) < 6
        OR HOUR(time_of_departure) >= 19"
)->fetchColumn();
echo "passenger_trip_history rows to fix: $count\n";

if ($count > 0) {
    $rows = $db->query(
        "SELECT history_id FROM passenger_trip_history 
         WHERE time_of_departure IS NULL 
            OR HOUR(time_of_departure) < 6
            OR HOUR(time_of_departure) >= 19"
    )->fetchAll(PDO::FETCH_ASSOC);

    $hStmt = $db->prepare(
        "UPDATE passenger_trip_history SET time_of_departure = :dep, time_of_arrival = :arr WHERE history_id = :id"
    );

    $done = 0;
    foreach ($rows as $r) {
        [$dep, $arr] = randomTimePair();
        $hStmt->execute([':dep' => $dep, ':arr' => $arr, ':id' => $r['history_id']]);
        $done++;
    }
    echo "  → $done updated\n";
} else {
    echo "  → nothing to do\n";
}

// ─── passenger_trips ─────────────────────────────────────
$count = $db->query(
    "SELECT COUNT(*) FROM passenger_trips 
     WHERE HOUR(boarding_time) < 6
        OR HOUR(boarding_time) >= 19"
)->fetchColumn();
echo "passenger_trips rows to fix: $count\n";

if ($count > 0) {
    $rows = $db->query(
        "SELECT id, DATE(boarding_time) AS dt FROM passenger_trips 
         WHERE HOUR(boarding_time) < 6
            OR HOUR(boarding_time) >= 19"
    )->fetchAll(PDO::FETCH_ASSOC);

    $tStmt = $db->prepare(
        "UPDATE passenger_trips SET boarding_time = CONCAT(:dt, ' ', :time) WHERE id = :id"
    );

    $done = 0;
    foreach ($rows as $r) {
        [$dep, $_arr] = randomTimePair();
        $tStmt->execute([':dt' => $r['dt'], ':time' => $dep, ':id' => $r['id']]);
        $done++;
    }
    echo "  → $done updated\n";
} else {
    echo "  → nothing to do\n";
}

echo "Done.\n";
