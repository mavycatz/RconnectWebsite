<?php

header('Content-Type: application/json');
/* State-bearing response: never served from a browser/PWA cache. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = [];
$rawInput = file_get_contents('php://input');
if (is_string($rawInput) && $rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$busParam = trim((string) ($input['bus_no'] ?? ($_GET['bus_no'] ?? '')));

if ($busParam !== '') {
    $driverName = null;
    $clean = (string) preg_replace('/^bus[\s\-#:]*/i', '', $busParam);
    $candidates = array_values(array_unique(array_filter([
        $busParam,
        $clean,
        $clean !== '' ? 'Bus-' . $clean : '',
    ])));

    try {
        $conn = Database::get();
        $in = implode(', ', array_fill(0, count($candidates), 'LOWER(TRIM(?))'));

        $stmt = $conn->prepare("
            SELECT COALESCE(d.full_name, dba.driver_id) AS driver_name
            FROM driver_bus_assignment dba
            LEFT JOIN drivers d ON d.driver_id = dba.driver_id
            WHERE LOWER(TRIM(dba.bus_id)) IN ($in)
            LIMIT 1
        ");
        $stmt->execute($candidates);
        $driverName = $stmt->fetchColumn() ?: null;

        if (!$driverName) {
            $stmt = $conn->prepare("
                SELECT COALESCE(d.full_name, bt.driver_id) AS driver_name
                FROM bus_trips bt
                LEFT JOIN drivers d ON d.driver_id = bt.driver_id
                WHERE LOWER(TRIM(bt.bus_id)) IN ($in)
                ORDER BY bt.id DESC
                LIMIT 1
            ");
            $stmt->execute($candidates);
            $driverName = $stmt->fetchColumn() ?: null;
        }

        if (!$driverName) {
            $stmt = $conn->prepare("
                SELECT COALESCE(d.full_name, b.driver) AS driver_name
                FROM buses b
                LEFT JOIN drivers d ON d.driver_id = b.driver
                WHERE LOWER(TRIM(b.bus_id)) IN ($in)
                LIMIT 1
            ");
            $stmt->execute($candidates);
            $driverName = $stmt->fetchColumn() ?: null;
        }
    } catch (\Throwable $e) {
        $driverName = null;
    }

    echo json_encode([
        'success' => true,
        'bus_no' => $busParam,
        'driver_name' => $driverName,
    ]);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

$conn = Database::get();

try {
    $stmt = $conn->prepare("
        SELECT
            pt.id, pt.trip_no, pt.bus_no, pt.boarding_point,
            pt.payment_method, pt.destination, pt.destination_id,
            pt.boarding_stop_id, pt.boarding_km_marker,
            pt.selected_destination, pt.selected_destination_id, pt.selected_km_marker,
            pt.estimated_fare, pt.trip_status, pt.payment_status, pt.latitude, pt.longitude,
            bt.route_name,
            p.discount_type,
            p.registration_type,
            COALESCE(
                d.full_name,
                (
                    SELECT dr.full_name
                    FROM driver_bus_assignment dba2
                    JOIN drivers dr ON dba2.driver_id = dr.driver_id
                    WHERE LOWER(TRIM(dba2.bus_id)) = LOWER(TRIM(pt.bus_no))
                    LIMIT 1
                )
            ) AS driver_name
        FROM passenger_trips pt
        LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
        LEFT JOIN drivers d ON pt.driver_id = d.driver_id
        LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.passenger_id = ?
        AND pt.dropoff_time IS NULL
        AND (
            pt.trip_status IS NULL
            OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled')
        )
        AND (
            bt.id IS NULL
            OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
        )
        ORDER BY pt.id DESC
        LIMIT 1
    ");

    $stmt->execute([$passenger_id]);
    $activeTrip = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    error_log('Passenger trip/active failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load trip right now.']);
    exit;
}

if ($activeTrip) {
    $party = strtolower((string) ($activeTrip['registration_type'] ?? '')) === 'one_time'
        ? null : RegularPartyService::read($conn, $passenger_id, (int) $activeTrip['id']);
    echo json_encode([
        'party' => $party,
        'hasActiveTrip' => true,
        'trip_no' => $activeTrip['trip_no'],
        'bus_no' => $activeTrip['bus_no'],
        'boarding_point' => $activeTrip['boarding_point'],
        'boarding_stop_id' => $activeTrip['boarding_stop_id'] ?? null,
        'boarding_km_marker' => $activeTrip['boarding_km_marker'] ?? null,
        'route_name' => $activeTrip['route_name'],
        'driver_name' => $activeTrip['driver_name'],
        'payment_method' => $activeTrip['payment_method'] ?? null,
        'destination' => $activeTrip['destination'] ?? null,
        'destination_id' => $activeTrip['destination_id'] ?? null,
        'selected_destination' => $activeTrip['selected_destination'] ?? null,
        'selected_destination_id' => $activeTrip['selected_destination_id'] ?? null,
        'selected_km_marker' => $activeTrip['selected_km_marker'] ?? null,
        'estimated_fare' => isset($activeTrip['estimated_fare']) ? (float) $activeTrip['estimated_fare'] : null,
        'trip_status' => $activeTrip['trip_status'] ?? 'In Progress',
        'payment_status' => $activeTrip['payment_status'] ?? 'pending',
        'trip_id' => (int) $activeTrip['id'],
        'discount_type' => $activeTrip['discount_type'] ?? 'regular',
        'destination_locked' => !empty($activeTrip['destination_id']) && ($party === null || empty($party['editable']))
    ]);
} else {
    // Finished trips that are still unpaid make up the passenger's balance.
    // Boarding stays available until the balance reaches the trip limit. The
    // shared helper folds a continuation chain into one amount so a carried
    // balance is never counted twice.
    $outstanding = null;
    try {
        $rows = ContinuationJourneyService::outstandingTrips($conn, $passenger_id);

        if ($rows) {
            $trips = [];
            $total = 0.0;

            foreach ($rows as $row) {
                $totalDue = isset($row['total_due']) ? (float) $row['total_due'] : 0.0;
                $total += $totalDue;

                $trips[] = [
                    'trip_id' => (int) $row['id'],
                    'payment_method' => $row['payment_method'] ?? null,
                    'payment_status' => $row['payment_status'] ?? 'pending',
                    'fare' => isset($row['fare']) ? (float) $row['fare'] : 0.0,
                    'total_due' => $totalDue,
                    'destination' => $row['destination'] ?? null,
                ];
            }

            $outstanding = [
                'count' => count($trips),
                'total' => round($total, 2),
                'limit' => 2,
                'trips' => $trips,
                'oldest_trip_id' => $trips[0]['trip_id'],
                'oldest_payment_method' => $trips[0]['payment_method'],
            ];
        }
    } catch (\Throwable $e) {
        $outstanding = null;
    }

    echo json_encode([
        'hasActiveTrip' => false,
        'outstanding_payment' => $outstanding,
    ]);
}
