<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$driver_id = trim($_GET['driver_id'] ?? '');
$bus_no = trim($_GET['bus_id'] ?? $_GET['bus_no'] ?? '');

if ($trip_id <= 0 && (empty($driver_id) || empty($bus_no))) {
    echo json_encode([
        "success" => false,
        "message" => "Provide trip_id OR driver_id + bus_id"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. RESOLVE ACTIVE TRIP IF ONLY DRIVER + BUS GIVEN
|--------------------------------------------------------------------------
*/

if ($trip_id <= 0) {
    $tripStmt = $conn->prepare("
        SELECT id, route_name FROM bus_trips
        WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing')
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$tripStmt) {
        echo json_encode(["success" => false, "message" => "bus_trips query failed: " . $conn->error]);
        exit;
    }
    $tripStmt->bind_param("s", $driver_id);
    $tripStmt->execute();
    $activeTrip = $tripStmt->get_result()->fetch_assoc();
    $tripStmt->close();

    if (!$activeTrip) {
        echo json_encode(["success" => true, "count" => 0, "batches" => [], "trip_id" => null]);
        exit;
    }
    $trip_id = (int)$activeTrip['id'];
}

require_once __DIR__ . "/lib/actual_destination.php";

// Route name for actual-destination resolution (only needed when trip_id was passed directly)
$tripRouteName = '';
if (isset($activeTrip) && !empty($activeTrip['route_name'])) {
    $tripRouteName = $activeTrip['route_name'];
} else {
    $rtStmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE id = ? LIMIT 1");
    if ($rtStmt) {
        $rtStmt->bind_param("i", $trip_id);
        $rtStmt->execute();
        $rtRow = $rtStmt->get_result()->fetch_assoc();
        $rtStmt->close();
        if ($rtRow) $tripRouteName = $rtRow['route_name'] ?? '';
    }
}

/*
|--------------------------------------------------------------------------
| 2. FETCH ALL BATCHES FOR THE TRIP WITH THEIR PASSENGERS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("SELECT * FROM cash_batches WHERE trip_id = ? ORDER BY batch_id ASC");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "cash_batches query failed: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $batches[$row['batch_id']] = [
        "batch_id" => (int)$row['batch_id'],
        "trip_id" => (int)$row['trip_id'],
        "driver_id" => $row['driver_id'],
        "stop_name" => $row['stop_name'],
        "passenger_count" => (int)$row['passenger_count'],
        "expected_amount" => (float)$row['expected_amount'],
        "collected_amount" => (float)$row['collected_amount'],
        "remaining_amount" => round(max((float)$row['expected_amount'] - (float)$row['collected_amount'], 0), 2),
        "status" => $row['status'],
        "collected_at" => $row['collected_at'],
        "created_at" => $row['created_at'],
        "passengers" => []
    ];
}

$stmt->close();

if (!empty($batches)) {
    $batchIds = array_keys($batches);
    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
    $types = str_repeat('i', count($batchIds));

    $passStmt = $conn->prepare("
        SELECT pt.id, pt.batch_id, pt.passenger_id, pt.destination, pt.fare,
               pt.payment_method, pt.payment_status, pt.boarding_point, p.full_name
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE pt.batch_id IN ($placeholders)
        ORDER BY pt.id ASC
    ");
    if (!$passStmt) {
        echo json_encode(["success" => false, "message" => "passenger_trips query failed: " . $conn->error]);
        exit;
    }
    $passStmt->bind_param($types, ...$batchIds);
    $passStmt->execute();
    $passResult = $passStmt->get_result();

    while ($row = $passResult->fetch_assoc()) {
        $bid = (int)$row['batch_id'];
        if (!isset($batches[$bid])) continue;

        $actualStop = resolve_passenger_actual_stop($conn, $row, $tripRouteName);
        $finalFare = null;
        if ($actualStop) {
            $finalFare = compute_final_fare(
                $conn,
                $tripRouteName,
                $row['boarding_point'] ?? '',
                $actualStop['stop_name'],
                null,
                isset($row['fare']) ? $row['fare'] : null,
                $row['destination'] ?? null
            );
        }

        $batches[$bid]['passengers'][] = [
            "id" => (int)$row['id'],
            "passenger_id" => $row['passenger_id'],
            "full_name" => $row['full_name'],
            "destination" => $row['destination'],
            "fare" => (float)$row['fare'],
            "actual_stop_id" => $actualStop ? (int)$actualStop['id'] : null,
            "actual_stop_name" => $actualStop ? $actualStop['stop_name'] : null,
            "actual_km_marker" => $actualStop ? (int)$actualStop['km_marker'] : null,
            "final_fare" => $finalFare !== null ? (float)$finalFare : null,
            "payment_method" => $row['payment_method'],
            "payment_status" => $row['payment_status']
        ];
    }
    $passStmt->close();
}

echo json_encode([
    "success" => true,
    "count" => count($batches),
    "trip_id" => $trip_id,
    "batches" => array_values($batches)
]);

$conn->close();
