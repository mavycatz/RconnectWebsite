<?php

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log uncaught fatals with their real location so a blank 500 is traceable.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log("[save_manual_passenger] FATAL {$err['message']} in {$err['file']}:{$err['line']}");
    }
});

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Default vehicle capacity. Replace with a real column/lookup when available.
|--------------------------------------------------------------------------
*/
if (!defined('VEHICLE_CAPACITY')) {
    define('VEHICLE_CAPACITY', 10);
}

/*
|--------------------------------------------------------------------------
| GET JSON INPUT
|--------------------------------------------------------------------------
*/

$data = json_decode(file_get_contents("php://input"), true);

$passenger_id    = $data['passenger_id'] ?? null;
$bus_no          = trim($data['bus_no'] ?? '');
$trip_no         = trim($data['trip_no'] ?? '');
$driver_id       = trim($data['driver_id'] ?? '');
$token           = $data['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

require_once __DIR__ . '/lib/passenger_photo.php';

$boarding_point  = trim($data['boarding_point'] ?? '');
$input_destination = trim($data['destination'] ?? '');
$passenger_type  = trim($data['passenger_type'] ?? 'regular');
$input_full_name = trim($data['full_name'] ?? '');

/* NEW: Other (baggage/cargo) support */
$record_type     = strtolower(trim($data['record_type'] ?? 'passenger'));
$details         = trim($data['details'] ?? '');
$space_occupied  = isset($data['space_occupied']) ? (int)$data['space_occupied'] : 1;

$payment_method  = trim($data['payment_method'] ?? 'Cash');
$payment_status  = trim($data['payment_status'] ?? 'Pending');

$passengerPhotoDataUri = trim($data['passenger_photo'] ?? '');

/*
|--------------------------------------------------------------------------
| VALIDATE REQUIRED INPUTS (only what the driver selects)
|--------------------------------------------------------------------------
*/

$missing = [];
if (empty($bus_no))       $missing[] = 'bus_no';
if (empty($trip_no))      $missing[] = 'trip_no';
if (empty($driver_id))    $missing[] = 'driver_id';
if (empty($boarding_point)) $missing[] = 'boarding_point';

if (!empty($missing)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(', ', $missing),
        "debug" => ["missing" => $missing, "received" => $data]
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| IDEMPOTENCY LEDGER (offline sync retries)
|--------------------------------------------------------------------------
| The driver app submits a stable `identity_key` with every queued action.
| When the optional ledger table exists, a repeated submission stops here
| with `already_recorded` instead of creating duplicate records. When the
| table is absent the endpoint behaves exactly as before.
*/
$identityKey = trim($data['identity_key'] ?? '');
$hasSyncLedger = false;
$ledgerCheck = $conn->query("SHOW TABLES LIKE 'driver_sync_ledger'");
if ($ledgerCheck && $ledgerCheck->num_rows > 0) {
    $hasSyncLedger = true;
}

if ($hasSyncLedger && $identityKey !== '') {
    $ledgerStmt = $conn->prepare("SELECT record_id FROM driver_sync_ledger WHERE sync_key = ? LIMIT 1");
    if ($ledgerStmt) {
        $ledgerStmt->bind_param("s", $identityKey);
        $ledgerStmt->execute();
        $ledgerRow = $ledgerStmt->get_result()->fetch_assoc();
        $ledgerStmt->close();
        if ($ledgerRow) {
            echo json_encode([
                "success" => true,
                "already_recorded" => true,
                "message" => "Transaction already processed",
                "record_id" => $ledgerRow['record_id'] !== null ? (int)$ledgerRow['record_id'] : null,
            ]);
            $conn->close();
            exit;
        }
    }
}

$isOther = ($record_type === 'other');

if ($isOther) {
    if ($details === '') {
        echo json_encode(["success" => false, "message" => "Item details are required for Other items"]);
        exit;
    }
    if ($space_occupied < 1) {
        echo json_encode(["success" => false, "message" => "Space occupied must be at least 1"]);
        exit;
    }
    if (empty($input_destination)) {
        echo json_encode(["success" => false, "message" => "Destination is required for Other items"]);
        exit;
    }
}

/* Other items use the regular passenger fare (boarding -> destination) */
$type = $isOther ? 'regular' : strtolower($passenger_type);

$trip_id_in      = $data['trip_id'] ?? null;
$client_route    = trim($data['route_name'] ?? '');

/*
|--------------------------------------------------------------------------
| 1. GET ACTIVE TRIP → ROUTE_NAME
|--------------------------------------------------------------------------
*/

$trip = null;
$candidateTripId = !empty($trip_id_in) ? (int)$trip_id_in : (is_numeric($trip_no) ? (int)$trip_no : 0);

// Check 1: Match by (id or trip_number) AND driver_id
if ($candidateTripId > 0) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status, from_terminal_id
        FROM bus_trips
        WHERE (id = ? OR trip_number = ?) AND driver_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("iis", $candidateTripId, $candidateTripId, $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 2: Match active trip for driver_id
if (!$trip) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status, from_terminal_id
        FROM bus_trips
        WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'On Route', 'Scheduled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 3: Match active trip for bus_no
if (!$trip && !empty($bus_no)) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status, from_terminal_id
        FROM bus_trips
        WHERE bus_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'On Route', 'Scheduled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $bus_no);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 4: Match by candidate id/trip_number alone
if (!$trip && $candidateTripId > 0) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status, from_terminal_id
        FROM bus_trips
        WHERE id = ? OR trip_number = ?
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $candidateTripId, $candidateTripId);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 5: Latest non-completed trip for this driver
if (!$trip) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status, from_terminal_id
        FROM bus_trips
        WHERE driver_id = ? AND status NOT IN ('Completed', 'Cancelled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$trip) {
    if (!empty($client_route)) {
        $route_name = $client_route;
    } else {
        echo json_encode(["success" => false, "message" => "Active trip not found for id: $trip_no / driver: $driver_id"]);
        exit;
    }
} else {
    $route_name = $trip['route_name'];
    $trip_no = (string)$trip['id'];
}

/*
|--------------------------------------------------------------------------
| 2. GET DRIVER'S CURRENT GPS
|--------------------------------------------------------------------------
*/

$latitude = null;
$longitude = null;

if (isset($data['latitude']) && isset($data['longitude']) && is_numeric($data['latitude']) && is_numeric($data['longitude']) && ((float)$data['latitude'] != 0 || (float)$data['longitude'] != 0)) {
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];
} else {
    $locStmt = $conn->prepare("SELECT latitude, longitude FROM driver_current_location WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
    if ($locStmt) {
        $locStmt->bind_param("s", $driver_id);
        $locStmt->execute();
        $locResult = $locStmt->get_result();
        if ($locResult && $locResult->num_rows > 0) {
            $loc = $locResult->fetch_assoc();
            $latitude = (float)$loc['latitude'];
            $longitude = (float)$loc['longitude'];
        }
        $locStmt->close();
    }
}

if ($latitude === null || $longitude === null) {
    if (!empty($trip['from_terminal_id'])) {
        $tStmt = $conn->prepare("SELECT latitude, longitude FROM terminals WHERE id = ? LIMIT 1");
        if ($tStmt) {
            $tStmt->bind_param("i", $trip['from_terminal_id']);
            $tStmt->execute();
            $tRow = $tStmt->get_result()->fetch_assoc();
            $tStmt->close();
            if ($tRow && $tRow['latitude'] !== null) {
                $latitude = (float)$tRow['latitude'];
                $longitude = (float)$tRow['longitude'];
            }
        }
    }
}

if ($latitude === null || $longitude === null) {
    $latitude = 15.4823;
    $longitude = 120.9750;
}

/*
|--------------------------------------------------------------------------
| 3. CAPACITY CHECK (used by Other items; harmless for passengers)
|--------------------------------------------------------------------------
*/

function getCurrentOccupied($conn, $tripNo, $busNo, $driverId) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(CASE WHEN p.space_occupied IS NOT NULL THEN p.space_occupied ELSE 1 END), 0) AS occ
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE pt.trip_no = ?
          AND pt.bus_no = ?
          AND pt.driver_id = ?
          AND (pt.dropoff_time IS NULL OR LOWER(pt.payment_status) = 'pending')
          AND pt.archived_status = 'no'
    ");
    if (!$stmt) return 0;
    $stmt->bind_param("iss", $tripNo, $busNo, $driverId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['occ'] ?? 0);
}

$capacity = VEHICLE_CAPACITY;
$capStmt = $conn->prepare("SELECT COALESCE(seats, 0) + COALESCE(standing_capacity, 0) AS cap FROM buses WHERE bus_id = ? OR plate_number = ? LIMIT 1");
if ($capStmt) {
    $capStmt->bind_param("ss", $bus_no, $bus_no);
    $capStmt->execute();
    $capRow = $capStmt->get_result()->fetch_assoc();
    $capStmt->close();
    if ($capRow && (int)$capRow['cap'] > 0) {
        $capacity = (int)$capRow['cap'];
    }
}
$currentOccupied = getCurrentOccupied($conn, $trip_no, $bus_no, $driver_id);
$remainingCapacity = $capacity - $currentOccupied;

if ($isOther && $space_occupied > $remainingCapacity) {
    $remainingWord = $remainingCapacity === 1 ? '1 space is' : $remainingCapacity . ' spaces are';
    echo json_encode([
        "success" => false,
        "message" => "Not enough available space. Only $remainingWord remaining."
    ]);
    exit;
}
/*

|--------------------------------------------------------------------------
| 4. FARE / ROUTE-STOP RESOLUTION (shared by passengers AND Other items)
|    Other items use the regular passenger fare (boarding -> destination).
|--------------------------------------------------------------------------
*/
{

    /*
    |--------------------------------------------------------------------------
    */

    $checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM fare_pricing WHERE route_name = ?");
    $checkStmt->bind_param("s", $route_name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $routeStopCount = (int)$checkResult['cnt'];
    $checkStmt->close();

    if ($routeStopCount === 0) {
        echo json_encode(["success" => false, "message" => "No fare_pricing records for route_name from bus_trips", "debug" => ["route_name" => $route_name]]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, destination, display_order, regular_fare, student_fare, special_fare FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC");
    if (!$stmt) { echo json_encode(["success" => false, "message" => "fare_pricing query failed", "debug" => $conn->error]); exit; }
    $stmt->bind_param("s", $route_name);
    $stmt->execute();
    $routeStops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$routeStops) {
        echo json_encode(["success" => false, "message" => "No fare_pricing records for route: $route_name"]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | LOCAL TRIP SHORTCUT (boarding_point = destination)
    |--------------------------------------------------------------------------
    */

    $isLocal = $input_destination !== '' && strtolower($input_destination) === strtolower($boarding_point);

    if ($isLocal) {
        $destination = $boarding_point;
        $destination_id = null;
        $destinationFare = 0;
        $fare = 15;
        $tripPath = 'local';
    } else {
        $tripPath = 'computed';
        $destination = null;
        $destination_id = null;
        $destinationFare = 0;

    /*
    |--------------------------------------------------------------------------
    | USE CLIENT DESTINATION IF VALID (trust driver's selection)
    |--------------------------------------------------------------------------
    */

    if ($input_destination !== '') {
        foreach ($routeStops as $s) {
            if (strtolower($s['destination']) === strtolower($input_destination)) {
                $destination = $s['destination'];
                $destination_id = (int)$s['id'];
                $tripPath = 'client';
                break;
            }
        }
    }

    if (!$destination) {
    /*
    |--------------------------------------------------------------------------
    | FIND NEAREST STOP TO DRIVER'S GPS → DESTINATION (fallback)
    |--------------------------------------------------------------------------
    */

    function haversine($lat1, $lng1, $lat2, $lng2) {
        $R = 6371;
        $dLat = ($lat2 - $lat1) * M_PI / 180;
        $dLng = ($lng2 - $lng1) * M_PI / 180;
        $a = sin($dLat / 2) ** 2 + cos($lat1 * M_PI / 180) * cos($lat2 * M_PI / 180) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // First try reverse geocode for address matching
    $apiKey = 'AIzaSyAxCwxIOQMp1qYB6Jo3CMT_sZOsO1rkW2U';
    $geoUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}";
    $geoResponse = @file_get_contents($geoUrl);
    $geoData = $geoResponse ? json_decode($geoResponse, true) : null;

    if ($geoData && !empty($geoData['results'][0]['address_components'])) {
        $components = $geoData['results'][0]['address_components'];
        $skipTypes = ['administrative_area_level_1', 'administrative_area_level_2', 'administrative_area_level_3', 'country', 'political', 'postal_code', 'plus_code', 'street_number'];
        $priorityGroups = [
            ['sublocality', 'sublocality_level_1', 'sublocality_level_2'],
            ['locality'],
            ['route']
        ];

        foreach ($priorityGroups as $group) {
            foreach ($components as $comp) {
                if (!array_intersect($comp['types'], $group)) continue;
                if (array_intersect($comp['types'], $skipTypes)) continue;
                $name = strtolower(trim($comp['long_name']));
                if (strlen($name) < 3) continue;
                foreach ($routeStops as $stop) {
                    $dest = strtolower($stop['destination']);
                    if (strpos($dest, $name) !== false || strpos($name, $dest) !== false) {
                        $destination = $stop['destination'];
                        $destination_id = (int)$stop['id'];
                        break 3;
                    }
                }
            }
        }
    }

    // Fallback: if no address match, use display_order proximity (last stop = default)
    if (!$destination) {
        // Find the stop with display_order closest to the boarding point's order
        $boardStop = null;
        foreach ($routeStops as $s) {
            if (strtolower($s['destination']) === strtolower($boarding_point)) {
                $boardStop = $s;
                break;
            }
        }
        $boardIdx = $boardStop ? array_search($boardStop, $routeStops) : 0;
        // Pick the next stop after boarding, or last stop if already at last
        $destIdx = min($boardIdx + 1, count($routeStops) - 1);
        $destination = $routeStops[$destIdx]['destination'];
        $destination_id = (int)$routeStops[$destIdx]['id'];
    }
    }

    /*
    |--------------------------------------------------------------------------
    | GET FARE FROM fare_pricing (difference)
    |--------------------------------------------------------------------------
    */

    $boardStop = null;
    $destStop = null;
    foreach ($routeStops as $s) {
        if (strtolower($s['destination']) === strtolower($boarding_point)) $boardStop = $s;
        if ($s['id'] === $destination_id) $destStop = $s;
    }

    if (!$boardStop) $boardStop = $routeStops[0];
    if (!$destStop) { $destStop = $routeStops[count($routeStops) - 1]; $destination = $destStop['destination']; $destination_id = (int)$destStop['id']; }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ROUTE DIRECTION (destination must be ahead of boarding)
    |--------------------------------------------------------------------------
    */

    $boardingOrder = (int)$boardStop['display_order'];
    $destinationOrder = (int)$destStop['display_order'];

    if ($destinationOrder <= $boardingOrder) {
        echo json_encode([
            "success" => false,
            "message" => "Destination must be ahead of boarding point on the route",
            "debug" => [
                "boarding" => $boardStop['destination'],
                "boarding_order" => $boardingOrder,
                "destination" => $destStop['destination'],
                "destination_order" => $destinationOrder
            ]
        ]);
        exit;
    }

    switch ($type) {
        case 'student': case 'senior': case 'pwd':
            $boardingFare = (float)$boardStop['student_fare'];
            $destinationFare = (float)$destStop['student_fare'];
            break;
        case 'special': case 'teacher': case 'nurse':
            $boardingFare = (float)$boardStop['special_fare'];
            $destinationFare = (float)$destStop['special_fare'];
            break;
        default:
            $boardingFare = (float)$boardStop['regular_fare'];
            $destinationFare = (float)$destStop['regular_fare'];
    }

    if ($boardStop['id'] == $destStop['id']) {
      $fare = 15;
    } elseif ((int)$boardStop['display_order'] === 1) {
      $fare = $destinationFare;
    } else {
      $computedFare = $destinationFare - $boardingFare;
      $fare = $computedFare < 15 ? 15 : $computedFare;
    }
    $fare = round($fare, 2);

    } // end else (non-local trip processing)
}

if ($isOther) {
    $payment_method = 'Other';
    $payment_status = 'Other';
    /* Other fare = regular boarding->destination fare x space occupied */
    $fare = round($fare * $space_occupied, 2);
}

/*
|--------------------------------------------------------------------------
| 5. WRAP IN TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | 6. CREATE PASSENGER (numbered for passengers; details for Other)
    |--------------------------------------------------------------------------
    */

    if (empty($passenger_id)) {
        if ($isOther) {
            $name = $details;
            $stmt = $conn->prepare("INSERT INTO passengers (full_name, type, space_occupied) VALUES (?, 'other', ?)");
            if (!$stmt) throw new Exception("Passenger insert prepare failed: " . $conn->error);
            $stmt->bind_param("si", $name, $space_occupied);
        } else {
            if ($input_full_name !== '') {
                // Driver-entered name (manual Add Passenger form).
                $name = $input_full_name;
            } else {
                $countResult = $conn->query("SELECT COUNT(*) AS cnt FROM passengers WHERE full_name LIKE 'Walk-in %'");
                $count = $countResult ? (int)$countResult->fetch_assoc()['cnt'] + 1 : 1;
                $name = "Walk-in #" . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $stmt = $conn->prepare("INSERT INTO passengers (full_name, type, space_occupied, registration_type) VALUES (?, 'passenger', 1, 'one_time')");
            if (!$stmt) throw new Exception("Passenger insert prepare failed: " . $conn->error);
            $stmt->bind_param("s", $name);
        }
        if (!$stmt->execute()) throw new Exception("Passenger insert failed: " . $stmt->error);
        $passenger_id = $stmt->insert_id;
        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 7. SAVE PASSENGER TRIP
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO passenger_trips
        (
            passenger_id,
            bus_no,
            trip_no,
            driver_id,
            boarding_point,
            destination,
            latitude,
            longitude,
            fare,
            payment_method,
            payment_status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) throw new Exception("passenger_trips prepare failed: " . $conn->error);

    $stmt->bind_param(
        "isssssdddss",
        $passenger_id,
        $bus_no,
        $trip_no,
        $driver_id,
        $boarding_point,
        $destination,
        $latitude,
        $longitude,
        $fare,
        $payment_method,
        $payment_status
    );

    if (!$stmt->execute()) throw new Exception("passenger_trips insert failed: " . $stmt->error);

    $record_id = $stmt->insert_id;
    $stmt->close();

    // Record the sync key with the created row so a retried action is
    // recognized as already recorded instead of creating a duplicate.
    if ($hasSyncLedger && $identityKey !== '') {
        $ledgerInsert = $conn->prepare("INSERT IGNORE INTO driver_sync_ledger (sync_key, action, record_id) VALUES (?, 'create_manual', ?)");
        if ($ledgerInsert) {
            $ledgerInsert->bind_param("si", $identityKey, $record_id);
            $ledgerInsert->execute();
            $ledgerInsert->close();
        }
    }

    $conn->commit();

    // Best-effort post-commit work (photo storage + push notification). A
    // failure here must never turn an already-committed record into a 500.
    $photoUrl = null;
    try {
        if ($passengerPhotoDataUri !== '') {
            $photoUrl = save_passenger_photo($passengerPhotoDataUri, (string)$record_id);
            if ($photoUrl !== null) {
                $phStmt = $conn->prepare("UPDATE passenger_trips SET passenger_photo = ? WHERE id = ?");
                if ($phStmt) {
                    $phStmt->bind_param("si", $photoUrl, $record_id);
                    $phStmt->execute();
                    $phStmt->close();
                }
            }
        }

        // Notify assigned inspector via push notification (skip for Other items).
        if (!$isOther) {
            $pushHelper = $_SERVER['DOCUMENT_ROOT'] . '/mobile/inspector/push_helper.php';
            if (file_exists($pushHelper)) {
                require_once $pushHelper;
                sendPushToAllInspectors(
                    'New Passenger',
                    'A new passenger has been added to bus ' . $bus_no,
                    ['type' => 'passenger', 'bus_id' => $bus_no]
                );
            }
        }
    } catch (Throwable $e) {
        error_log("[save_manual_passenger] post-commit: " . $e->getMessage());
    }

    echo json_encode([
        "success" => true,
        "message" => $isOther ? "Other item recorded successfully" : "Passenger trip recorded successfully",
        "record_id" => $record_id,
        "passenger_id" => $passenger_id,
        "passenger_photo" => $photoUrl,
        "type" => $isOther ? "other" : "passenger",
        "space_occupied" => $isOther ? $space_occupied : 1,
        "bus_no" => $bus_no,
        "trip_no" => $trip_no,
        "boarding_point" => $boarding_point,
        "destination" => $destination,
        "passenger_type" => $type,
        "fare" => $fare,
        "payment_method" => $payment_method,
        "payment_status" => $payment_status,
        "latitude" => $latitude,
        "longitude" => $longitude,
        "capacity" => $capacity,
        "occupied_capacity" => $currentOccupied + ($isOther ? $space_occupied : 0),
        "debug" => [
            "route_name" => $route_name,
            "gps" => ["lat" => $latitude, "lng" => $longitude],
            "destination_id" => $destination_id ?? null,
            "destination_fare" => $destinationFare ?? null,
            "trip_path" => $tripPath
        ]
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    error_log("[save_manual_passenger] " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "debug" => [
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]
    ]);
}

$conn->close();
