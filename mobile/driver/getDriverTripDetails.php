<?php

header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$driver_id = $_GET['driver_id'] ?? '';

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID required"]);
    exit;
}

$today = date('Y-m-d');
$day_start = $today . ' 00:00:00';
$day_end = $today . ' 23:59:59';

/*
|--------------------------------------------------------------------------
| TRIP SEQUENCE FOR TODAY (bus_trips.trip_number is not maintained,
| so the order of the day's trips determines the displayed trip number)
|--------------------------------------------------------------------------
*/

$trip_sequences = [];

$seqStmt = $conn->prepare("
    SELECT id
    FROM bus_trips
    WHERE driver_id = ?
    AND trip_date = ?
    AND status != 'Cancelled'
    ORDER BY COALESCE(departure_time, '00:00:00') ASC, id ASC
");

if ($seqStmt) {
    $seqStmt->bind_param("ss", $driver_id, $today);
    $seqStmt->execute();
    $seqResult = $seqStmt->get_result();
    $seq = 0;
    while ($seqRow = $seqResult->fetch_assoc()) {
        $seq++;
        $trip_sequences[(int)$seqRow['id']] = $seq;
    }
    $seqStmt->close();
}

/*
|--------------------------------------------------------------------------
| COMPLETED TRIPS TODAY (auto-resets daily via trip_date)
|--------------------------------------------------------------------------
*/

$completed_trips = [];

$stmt = $conn->prepare("
    SELECT
        bt.id,
        bt.bus_id,
        bt.route_name,
        bt.trip_number,
        bt.departure_time,
        bt.arrival_time,
        bt.total_passengers,
        bt.total_revenue,
        bt.status,
        ft.terminal_name AS from_terminal,
        tt.terminal_name AS to_terminal,
        d.full_name AS driver_name
    FROM bus_trips bt
    LEFT JOIN terminals ft ON ft.id = bt.from_terminal_id
    LEFT JOIN terminals tt ON tt.id = bt.to_terminal_id
    LEFT JOIN drivers d ON d.driver_id = bt.driver_id
    WHERE bt.driver_id = ?
    AND bt.trip_date = ?
    AND bt.status = 'Completed'
    ORDER BY bt.id DESC
");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Query preparation failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $driver_id, $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['total_passengers'] = (int)$row['total_passengers'];
    $row['total_revenue'] = round((float)$row['total_revenue'], 2);
    $row['trip_sequence'] = $trip_sequences[$row['id']] ?? null;

    // Split the trip's records into passengers and Others (baggage/cargo) so
    // the card can show the baggage count separately.
    $row['passenger_count'] = $row['total_passengers'];
    $row['other_count'] = 0;
    $splitStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 0 ELSE 1 END) AS passenger_count,
            SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 1 ELSE 0 END) AS other_count
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE pt.trip_no = ?
    ");
    if ($splitStmt) {
        $completedTripId = (int)$row['id'];
        $splitStmt->bind_param("i", $completedTripId);
        $splitStmt->execute();
        $splitRow = $splitStmt->get_result()->fetch_assoc();
        $splitStmt->close();
        if ($splitRow && $splitRow['passenger_count'] !== null) {
            $row['passenger_count'] = (int)$splitRow['passenger_count'];
            $row['other_count'] = (int)($splitRow['other_count'] ?? 0);
        }
    }

    $completed_trips[] = $row;
}

$stmt->close();

$trips_today = count($completed_trips);
$is_first_trip_today = $trips_today === 0;

/*
|--------------------------------------------------------------------------
| ACTIVE TRIP (same active status set as getPassengerStops.php)
|--------------------------------------------------------------------------
*/

$trip_active = false;
$active_trip_id = null;
$active_trip_data = null;

$stmt = $conn->prepare("
    SELECT
        bt.id,
        bt.bus_id,
        bt.route_name,
        bt.trip_number,
        bt.departure_time,
        bt.status,
        bt.arrival_reported_at,
        bt.arrival_report_source,
        ft.terminal_name AS from_terminal,
        tt.terminal_name AS to_terminal,
        d.full_name AS driver_name
    FROM bus_trips bt
    LEFT JOIN terminals ft ON ft.id = bt.from_terminal_id
    LEFT JOIN terminals tt ON tt.id = bt.to_terminal_id
    LEFT JOIN drivers d ON d.driver_id = bt.driver_id
    WHERE bt.driver_id = ?
    AND bt.status IN ('Departed', 'Started', 'Active', 'Ongoing', 'Interrupted')
    ORDER BY bt.id DESC
    LIMIT 1
");

/* Schema-tolerant fallback for installs without the arrival report columns. */
if (!$stmt) {
    $stmt = $conn->prepare("
        SELECT
            bt.id,
            bt.bus_id,
            bt.route_name,
            bt.trip_number,
            bt.departure_time,
            bt.status,
            ft.terminal_name AS from_terminal,
            tt.terminal_name AS to_terminal,
            d.full_name AS driver_name
        FROM bus_trips bt
        LEFT JOIN terminals ft ON ft.id = bt.from_terminal_id
        LEFT JOIN terminals tt ON tt.id = bt.to_terminal_id
        LEFT JOIN drivers d ON d.driver_id = bt.driver_id
        WHERE bt.driver_id = ?
        AND bt.status IN ('Departed', 'Started', 'Active', 'Ongoing', 'Interrupted')
        ORDER BY bt.id DESC
        LIMIT 1
    ");
}

if ($stmt) {
    $stmt->bind_param("s", $driver_id);
    $stmt->execute();
    $active_trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($active_trip) {
        $trip_active = true;
        $active_trip_id = (int)$active_trip['id'];

        $active_passengers = 0;
        $active_other_count = 0;
        // Onboard records only: dropped-off (or archived) rows no longer
        // occupy the trip. Others (baggage/cargo) are counted separately.
        $countStmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 0 ELSE 1 END) AS passenger_count,
                SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 1 ELSE 0 END) AS other_count
            FROM passenger_trips pt
            LEFT JOIN passengers p ON pt.passenger_id = p.id
            WHERE pt.trip_no = ?
              AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '')
              AND pt.archived_status = 'no'
        ");
        if ($countStmt) {
            $countStmt->bind_param("i", $active_trip_id);
            $countStmt->execute();
            $countRow = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
            if ($countRow) {
                $active_passengers = (int)($countRow['passenger_count'] ?? 0);
                $active_other_count = (int)($countRow['other_count'] ?? 0);
            }
        }

        $active_trip_data = [
            "id" => $active_trip_id,
            "bus_id" => $active_trip['bus_id'],
            "route_name" => $active_trip['route_name'],
            "trip_number" => $active_trip['trip_number'] !== null ? (int)$active_trip['trip_number'] : null,
            "trip_sequence" => $trip_sequences[$active_trip_id] ?? null,
            "departure_time" => $active_trip['departure_time'],
            "status" => $active_trip['status'],
            "from_terminal" => $active_trip['from_terminal'],
            "to_terminal" => $active_trip['to_terminal'],
            "driver_name" => $active_trip['driver_name'],
            "total_passengers" => $active_passengers + $active_other_count,
            "passenger_count" => $active_passengers,
            "other_count" => $active_other_count,
            "arrival_reported" => !empty($active_trip['arrival_reported_at']),
            "awaiting_confirmation" => !empty($active_trip['arrival_reported_at'])
        ];
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT-TRIP INSPECTION (read-only marker for the result button)
|
| Only an inspection submitted for the active trip counts. A new trip has
| none, so the driver app must not offer a previous trip's result.
|--------------------------------------------------------------------------
*/

$active_trip_inspection = null;
if ($active_trip_id !== null) {
    $inspectionTable = $conn->query("SHOW TABLES LIKE 'inspector_inspections'");
    if ($inspectionTable && $inspectionTable->num_rows > 0) {
        $activeTripIdStr = (string)$active_trip_id;
        $activeTripNumberStr = ($active_trip_data['trip_number'] !== null)
            ? (string)$active_trip_data['trip_number']
            : $activeTripIdStr;
        $inspStmt = $conn->prepare(
            "SELECT inspection_id, submitted_at, needs_review_count, inspector_name
             FROM inspector_inspections
             WHERE trip_no IN (?, ?)
             ORDER BY submitted_at DESC, id DESC
             LIMIT 1"
        );
        if ($inspStmt) {
            $inspStmt->bind_param("ss", $activeTripIdStr, $activeTripNumberStr);
            $inspStmt->execute();
            $inspRow = $inspStmt->get_result()->fetch_assoc();
            $inspStmt->close();
            if ($inspRow) {
                $active_trip_inspection = [
                    "inspection_id" => $inspRow['inspection_id'],
                    "submitted_at" => $inspRow['submitted_at'],
                    "needs_review_count" => (int)$inspRow['needs_review_count'],
                    "inspector_name" => $inspRow['inspector_name'],
                ];
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| TODAY'S REMITTANCE TOTALS (same formulas as get_remittance_summary.php)
|--------------------------------------------------------------------------
*/

$total_fare = 0;
$cash_collected = 0;
$total_expected_cash = 0;
$total_actual_cash = 0;
$passenger_trips_count = 0;

$stmt = $conn->prepare("
    SELECT
        fare,
        payment_method,
        payment_status,
        cash_amount,
        expected_cash,
        actual_cash
    FROM passenger_trips
    WHERE driver_id = ?
    AND boarding_time >= ?
    AND boarding_time <= ?
");

if ($stmt) {
    $stmt->bind_param("sss", $driver_id, $day_start, $day_end);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $passenger_trips_count++;

        $fare = (float)$row['fare'];
        $payment_method = strtolower($row['payment_method'] ?? 'cash');
        $payment_status = strtolower($row['payment_status'] ?? 'pending');

        if ($payment_status === 'paid') {
            $total_fare += $fare;
            if ($payment_method === 'cash') {
                $cash_collected += $fare;
            } elseif ($payment_method === 'hybrid') {
                $cash_collected += (float)$row['cash_amount'];
            }
        }

        $total_expected_cash += (float)$row['expected_cash'];
        $total_actual_cash += (float)$row['actual_cash'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "date" => $today,
    "trips_today" => $trips_today,
    "is_first_trip_today" => $is_first_trip_today,
    "trip_active" => $trip_active,
    "active_trip_id" => $active_trip_id,
    "active_trip" => $active_trip_data,
    "active_trip_inspection" => $active_trip_inspection,
    "remittance" => [
        "trips" => $passenger_trips_count,
        "cash_collected" => round($cash_collected, 2),
        "expected_cash" => round($total_expected_cash, 2),
        "actual_cash" => round($total_actual_cash, 2),
        "discrepancy" => round($total_expected_cash - $total_actual_cash, 2),
        "total_fare" => round($total_fare, 2)
    ],
    "completed_trips" => $completed_trips
]);

$conn->close();
