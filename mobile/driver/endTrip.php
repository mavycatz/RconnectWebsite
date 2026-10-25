<?php

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";
require_once __DIR__ . "/lib/arrival_report.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$driver_id = $data['driver_id'] ?? '';

/*
--------------------------------
MANUAL ARRIVAL REPORT
--------------------------------
*/

$trip_id = (int)($data['trip_id'] ?? 0);
$manualReported = false;
if ($trip_id > 0 && $driver_id !== '') {
    $manualReported = driver_report_arrival($conn, $trip_id, 'driver_app_manual', $driver_id);
}

/*
--------------------------------
REPORT ARRIVAL FOR EXPIRED TRIPS
--------------------------------

The stale-trip sweep reports arrival for trips whose departure is over two
hours old. It never completes them: the receiving terminal still confirms
the arrival with the actual ending odometer. Trips that already carry an
arrival report are left untouched.
*/

$reportedTrips = driver_report_expired_arrivals($conn, 120);

/*
--------------------------------
OPTIONAL: CHECK DRIVER ACTIVE TRIP
--------------------------------
*/

$activeTrip = null;

if (!empty($driver_id)) {

    $stmt = $conn->prepare("
        SELECT
            id,
            bus_id,
            route_name,
            trip_date,
            departure_time,
            status
        FROM bus_trips
        WHERE driver_id = ?
        AND status = 'Departed'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->bind_param("s", $driver_id);
    $stmt->execute();

    $activeTrip =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

/* The active trip may carry a reported arrival (this driver or the receiving
   terminal); surface the state so the app shows awaiting confirmation. */
$activeTripAwaiting = false;
if ($activeTrip) {
    $activeTripAwaiting = driver_trip_arrival_report($conn, (int)$activeTrip['id'])['reported'];
}

/*
--------------------------------
RESPONSE
--------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        ($reportedTrips > 0 || $manualReported)
        ? "Arrival reported - awaiting terminal confirmation"
        : "No expired trips found",

    "arrival_reports" =>
        $reportedTrips,

    "manual_arrival_reported" =>
        $manualReported,

    "completed_trips" =>
        $reportedTrips,

    "driver_has_active_trip" =>
        !empty($activeTrip),

    "active_trip_awaiting_confirmation" =>
        $activeTripAwaiting,

    "active_trip" =>
        $activeTrip

]);

$conn->close();

?>