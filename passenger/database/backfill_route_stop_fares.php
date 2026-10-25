<?php

/**
 * One-time backfill CLI/web entry point: populate route_stops.fare_pricing_id.
 *
 * Usage (CLI):  php passenger/database/backfill_route_stop_fares.php [--dry-run]
 * Usage (web):  /passenger/database/backfill_route_stop_fares.php
 */

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

if ($docRoot === '') {
    $docRoot = dirname(__DIR__, 2);
}

require_once $docRoot . '/app/Core/Database.php';
require_once __DIR__ . '/backfill_core.php';

use App\Core\Database;

header('Content-Type: text/plain');

$dryRun = in_array('--dry-run', $argv ?? [], true);

$conn = Database::get();

$stmt = $conn->query("SELECT id, route_name, stop_name, km_marker, sequence_order, fare_pricing_id FROM route_stops ORDER BY id ASC");
$stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fareStmt = $conn->query("SELECT id, route_name, destination, regular_fare, display_order FROM fare_pricing ORDER BY display_order ASC");
$fares = $fareStmt->fetchAll(PDO::FETCH_ASSOC);

$updateFn = null;
if (!$dryRun) {
    $update = $conn->prepare("UPDATE route_stops SET fare_pricing_id = ? WHERE id = ?");
    $updateFn = function ($fareId, $stopId) use ($update) {
        $update->execute([$fareId, $stopId]);
    };
}

[$matched, $unmatched, $lines] = backfillRouteStopFares($stops, $fares, $updateFn);

foreach ($lines as $line) {
    echo $line . "\n";
}

if ($dryRun) {
    echo "\nDRY RUN — no rows were updated.\n";
}

echo "\nDone. Matched: $matched, Unmatched: $unmatched\n";
