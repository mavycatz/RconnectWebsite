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
$bus_no = $_GET['bus_no'] ?? '';
$bus_id = $_GET['bus_id'] ?? '';
$token = $_GET['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID required"]);
    exit;
}

$phtTz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phtTz);
$today = $now->format('Y-m-d');
$hour = (int)$now->format('G');

/*
|--------------------------------------------------------------------------
| ACTIVE TRIP
|--------------------------------------------------------------------------
*/
$trip = null;
$tripRouteName = '';
$activeTripIds = [];
$tripStmt = $conn->prepare("
    SELECT id, trip_number, status, bus_id, trip_date, departure_time, route_name
    FROM bus_trips
    WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'Interrupted')
    ORDER BY id DESC
    LIMIT 1
");

if ($tripStmt) {
    $tripStmt->bind_param("s", $driver_id);
    $tripStmt->execute();
    $tripRow = $tripStmt->get_result()->fetch_assoc();
    $tripStmt->close();

    if ($tripRow) {
        $trip = [
            "id" => (int)$tripRow['id'],
            "status" => $tripRow['status'],
            "started_at" => ($tripRow['trip_date'] && $tripRow['departure_time'])
                ? $tripRow['trip_date'] . ' ' . $tripRow['departure_time']
                : null
        ];
        $tripRouteName = (string)($tripRow['route_name'] ?? '');
        // The inspector stores the trip as bus_trips.id, but older rows may
        // carry the human-readable trip_number; accept either.
        $activeTripIds[] = (string)$tripRow['id'];
        $tripNumberValue = trim((string)($tripRow['trip_number'] ?? ''));
        if ($tripNumberValue !== '' && $tripNumberValue !== (string)$tripRow['id']) {
            $activeTripIds[] = $tripNumberValue;
        }
        if (!$bus_id) {
            $bus_id = $tripRow['bus_id'];
        }
    }
}

/*
|--------------------------------------------------------------------------
| NEAR-DESTINATION ALERT FIELDS (read-only, additive)
|
| Deployed the resolver is a sibling in lib/; in the repository it lives
| under api/driver/lib/. Optional columns keep the snapshot working when the
| passenger trip flow columns are not present yet.
|--------------------------------------------------------------------------
*/
$nearStopLib = __DIR__ . '/lib/near_stop.php';
if (!is_file($nearStopLib)) {
    $nearStopLib = __DIR__ . '/../api/driver/lib/near_stop.php';
}
require_once $nearStopLib;

$passengerTripColumns = [];
$chkCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
if ($chkCols) {
    while ($c = $chkCols->fetch_assoc()) {
        $passengerTripColumns[$c['Field']] = true;
    }
}
$optionalColumn = function ($name) use ($passengerTripColumns) {
    return isset($passengerTripColumns[$name]) ? "pt.$name," : "NULL AS $name,";
};
$colDestinationId = $optionalColumn('destination_id');
$colSelectedDestinationId = $optionalColumn('selected_destination_id');
$colSelectedDestination = $optionalColumn('selected_destination');
$colActualDestination = $optionalColumn('actual_destination');
$colActualStopId = $optionalColumn('actual_stop_id');
$colActualStopName = $optionalColumn('actual_stop_name');

$nearStopCtx = $trip ? near_stop_prepare_context($conn, $tripRouteName, $driver_id) : null;

/*
|--------------------------------------------------------------------------
| PASSENGERS + PAID IDS (active trip only)
|--------------------------------------------------------------------------
*/
$passengers = [];
$paid_ids = [];

if ($trip) {
    $pSql = "
        SELECT
            pt.id,
            pt.passenger_id,
            pt.bus_no,
            pt.trip_no,
            pt.boarding_point,
            pt.destination,
            $colDestinationId
            $colSelectedDestinationId
            $colSelectedDestination
            $colActualDestination
            $colActualStopId
            $colActualStopName
            pt.fare,
            pt.payment_method,
            pt.payment_status,
            pt.boarding_time,
            p.full_name,
            COALESCE(p.type, 'passenger') AS type
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE
            pt.trip_no = ?
            AND pt.driver_id = ?
            AND pt.dropoff_time IS NULL
            AND pt.archived_status = 'no'
    ";
    $pParams = [$trip['id'], $driver_id];
    $pTypes = "is";

    if (!empty($bus_no)) {
        $pSql .= " AND pt.bus_no = ?";
        $pParams[] = $bus_no;
        $pTypes .= "s";
    }

    $pSql .= " ORDER BY pt.boarding_time DESC";

    $pStmt = $conn->prepare($pSql);
    if ($pStmt) {
        $pStmt->bind_param($pTypes, ...$pParams);
        $pStmt->execute();
        $pResult = $pStmt->get_result();
        while ($row = $pResult->fetch_assoc()) {
            if ($nearStopCtx) {
                // Near-destination alert fields (read-only; defaults mean "not near").
                $row = array_merge($row, near_stop_row_fields($nearStopCtx, $row));
            }
            $passengers[] = $row;
            if (strtolower((string)$row['payment_status']) === 'paid') {
                $paid_ids[] = (int)$row['id'];
            }
        }
        $pStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
*/
$alerts = [];
$aStmt = $conn->prepare("
    SELECT id, type, title, message, priority, created_at
    FROM announcements
    WHERE status = 'Active'
    ORDER BY created_at DESC
    LIMIT 20
");

if ($aStmt) {
    $aStmt->execute();
    $aResult = $aStmt->get_result();
    while ($row = $aResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $alerts[] = $row;
    }
    $aStmt->close();
}

/*
|--------------------------------------------------------------------------
| FUEL (today, for the driver's bus)
|--------------------------------------------------------------------------
*/
$fuel = [
    "today_count" => 0,
    "today_liters" => 0,
    "today_cost" => 0,
    "latest" => null,
    "fuel_summary_due" => false
];

if (!empty($bus_id)) {
    $fStmt = $conn->prepare("
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(liters), 0) AS liters,
            COALESCE(SUM(cost), 0) AS cost,
            MAX(id) AS latest_id,
            MAX(created_at) AS latest_created_at
        FROM fuel_receipts
        WHERE bus_id = ? AND DATE(created_at) = ?
    ");

    if ($fStmt) {
        $fStmt->bind_param("ss", $bus_id, $today);
        $fStmt->execute();
        $fRow = $fStmt->get_result()->fetch_assoc();
        $fStmt->close();

        $fuel['today_count'] = (int)$fRow['cnt'];
        $fuel['today_liters'] = round((float)$fRow['liters'], 2);
        $fuel['today_cost'] = round((float)$fRow['cost'], 2);
        if ($fRow['latest_id']) {
            $fuel['latest'] = [
                "id" => (int)$fRow['latest_id'],
                "created_at" => $fRow['latest_created_at']
            ];
        }
    }
}

$tripStartedLongAgo = false;
if ($trip && !empty($trip['started_at'])) {
    $started = DateTime::createFromFormat('Y-m-d H:i:s', $trip['started_at'], $phtTz);
    if ($started) {
        $tripStartedLongAgo = $now->getTimestamp() - $started->getTimestamp() > 6 * 3600;
    }
}

$fuel['fuel_summary_due'] = $fuel['today_count'] === 0 && ($hour >= 17 || $tripStartedLongAgo);

/*
|--------------------------------------------------------------------------
| REMITTANCE (today's Remitted batches)
|--------------------------------------------------------------------------
*/
$remittance = [
    "today_count" => 0,
    "today_total" => 0,
    "latest_remitted_at" => null
];

$rStmt = $conn->prepare("
    SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(pt.fare), 0) AS total_fare,
        MAX(COALESCE(pt.remitted_at, pt.boarding_time)) AS latest_remitted_at
    FROM passenger_trips pt
    WHERE
        pt.driver_id = ?
        AND pt.remittance_status = 'Remitted'
        AND DATE(COALESCE(pt.remitted_at, pt.boarding_time)) = CURDATE()
");

if ($rStmt) {
    $rStmt->bind_param("s", $driver_id);
    $rStmt->execute();
    $rRow = $rStmt->get_result()->fetch_assoc();
    $rStmt->close();

    $remittance['today_count'] = (int)$rRow['cnt'];
    $remittance['today_total'] = round((float)$rRow['total_fare'], 2);
    $remittance['latest_remitted_at'] = $rRow['latest_remitted_at'];
}

/*
|--------------------------------------------------------------------------
| LATEST INSPECTION RESULT (drives the "Inspection Completed" notification
| and the read-only result modal)
|--------------------------------------------------------------------------
*/
$inspection = null;

// An inspection is only surfaced for the trip the driver is currently on.
// A new trip legitimately has none, and must never inherit a previous trip's
// result (nor fire a stale "Inspection Completed" notification).
$activeTripIdA = $activeTripIds[0] ?? '';
$activeTripIdB = $activeTripIds[1] ?? $activeTripIdA;
$hasActiveTrip = $trip !== null && $activeTripIdA !== '';

$inspectionTable = $conn->query("SHOW TABLES LIKE 'inspector_inspections'");
if ($hasActiveTrip && $inspectionTable && $inspectionTable->num_rows > 0) {
    $hasDriverColumn = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM inspector_inspections");
    if ($colCheck) {
        while ($c = $colCheck->fetch_assoc()) {
            if ($c['Field'] === 'driver_id') $hasDriverColumn = true;
        }
    }

    $ackTable = $conn->query("SHOW TABLES LIKE 'inspection_driver_acknowledgements'");
    $hasAckTable = $ackTable && $ackTable->num_rows > 0;

    $ackSelect = $hasAckTable
        ? ", a.driver_name AS acknowledged_by, a.acknowledged_at"
        : ", NULL AS acknowledged_by, NULL AS acknowledged_at";
    $ackJoin = $hasAckTable
        ? "LEFT JOIN inspection_driver_acknowledgements a ON a.inspection_id = i.inspection_id"
        : "";
    $driverWhere = $hasDriverColumn
        ? "i.driver_id = ?"
        : "JSON_UNQUOTE(JSON_EXTRACT(i.audit_summary, '$.validation.driver_id')) = ?";

    $iStmt = $conn->prepare("
        SELECT i.inspection_id, i.bus_no, i.trip_no, i.route_name, i.inspector_id, i.inspector_name,
               i.submitted_at, i.needs_review_count, i.total_passengers
               $ackSelect
        FROM inspector_inspections i
        $ackJoin
        WHERE $driverWhere AND i.trip_no IN (?, ?)
        ORDER BY i.submitted_at DESC, i.id DESC
        LIMIT 1
    ");
    if ($iStmt) {
        $iStmt->bind_param("sss", $driver_id, $activeTripIdA, $activeTripIdB);
        $iStmt->execute();
        $iRow = $iStmt->get_result()->fetch_assoc();
        $iStmt->close();

        // Legacy fallback: rows without a stored driver link, still scoped to
        // the current trip only.
        if (!$iRow && !empty($bus_id)) {
            $busInspectionStmt = $conn->prepare("
                SELECT i.inspection_id, i.bus_no, i.trip_no, i.route_name, i.inspector_id, i.inspector_name,
                       i.submitted_at, i.needs_review_count, i.total_passengers
                       $ackSelect
                FROM inspector_inspections i
                $ackJoin
                WHERE i.bus_no = ? AND i.trip_no IN (?, ?)
                ORDER BY i.submitted_at DESC, i.id DESC
                LIMIT 1
            ");
            if ($busInspectionStmt) {
                $busInspectionStmt->bind_param("sss", $bus_id, $activeTripIdA, $activeTripIdB);
                $busInspectionStmt->execute();
                $iRow = $busInspectionStmt->get_result()->fetch_assoc();
                $busInspectionStmt->close();
            }
        }

        if ($iRow) {
            // Accountability: show the registered inspector name. The
            // Inspector app falls back to the inspector id when no name was
            // stored, so a value equal to the id is treated as missing.
            $inspectorIdValue = trim((string)($iRow['inspector_id'] ?? ''));
            $inspectorName = trim((string)($iRow['inspector_name'] ?? ''));
            $nameMissing = $inspectorName === ''
                || ($inspectorIdValue !== '' && strcasecmp($inspectorName, $inspectorIdValue) === 0);
            if ($nameMissing && $inspectorIdValue !== '') {
                try {
                    $iNameStmt = $conn->prepare("
                        SELECT i.inspector_name
                        FROM inspector_accounts ia
                        LEFT JOIN inspectors i ON i.account_id = ia.account_id
                        WHERE LOWER(ia.inspector_id) = LOWER(?)
                        LIMIT 1
                    ");
                    if ($iNameStmt) {
                        $iNameStmt->bind_param("s", $inspectorIdValue);
                        $iNameStmt->execute();
                        $iNameRow = $iNameStmt->get_result()->fetch_assoc();
                        $iNameStmt->close();
                        if ($iNameRow && trim((string)($iNameRow['inspector_name'] ?? '')) !== '') {
                            $inspectorName = trim((string)$iNameRow['inspector_name']);
                        }
                    }
                } catch (Exception $e) {
                    // Inspector tables may not exist yet; fall back below.
                }
            }
            if ($inspectorName === '') {
                $inspectorName = $inspectorIdValue;
            }

            $inspection = [
                "inspection_id" => $iRow['inspection_id'],
                "bus_no" => $iRow['bus_no'],
                "trip_no" => $iRow['trip_no'],
                "route_name" => $iRow['route_name'],
                "inspector_id" => $iRow['inspector_id'],
                "inspector_name" => $inspectorName,
                "submitted_at" => $iRow['submitted_at'],
                "needs_review_count" => (int)$iRow['needs_review_count'],
                "total_passengers" => (int)$iRow['total_passengers'],
                "acknowledged" => !empty($iRow['acknowledged_at']),
                "acknowledged_by" => $iRow['acknowledged_by'] ?? null,
                "acknowledged_at" => $iRow['acknowledged_at'] ?? null,
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
    "success" => true,
    "server_time" => $now->format('Y-m-d H:i:s'),
    "trip" => $trip,
    "passengers" => $passengers,
    "paid_ids" => $paid_ids,
    "alerts" => $alerts,
    "inspection" => $inspection,
    "fuel" => $fuel,
    "remittance" => $remittance,
    "near_destination_threshold_m" => near_stop_get_threshold_m(),
    "cutoffs" => [
        "fuel_hour" => 17,
        "remittance_hour" => 16
    ]
]);

$conn->close();
