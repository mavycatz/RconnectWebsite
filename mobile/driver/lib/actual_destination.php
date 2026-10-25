<?php

/*
|--------------------------------------------------------------------------
| ACTUAL DESTINATION RESOLVER (shared)
| Used by get_passengers.php and update_cash_batch.php.
|
| Resolves a passenger's ACTUAL route stop (where they really exit) from:
|   1. Second QR scan actual destination on passenger_trips (if present)
|   2. Latest valid passenger_location_history GPS -> nearest route_stops
|      on the current trip's route + direction, within a distance threshold
|
| Also computes the FINAL FARE using the existing fare rules from
| calculateFare.php (terminal full fare, non-terminal difference, min 15,
| same stop 15) against fare_pricing.
|
| Reuses ONLY existing tables: route_stops, passenger_location_history,
| passenger_trips, bus_trips, fare_pricing, driver_current_location.
|--------------------------------------------------------------------------
*/

if (!defined('GPS_MATCH_THRESHOLD_M')) {
    define('GPS_MATCH_THRESHOLD_M', 300.0);   // max meters from a route stop to accept it
}
if (!defined('GPS_ACCURACY_MAX_M')) {
    define('GPS_ACCURACY_MAX_M', 150.0);      // reject fixes with worse accuracy
}

/*
|--------------------------------------------------------------------------
| ROUTE NAME NORMALIZATION
| bus_trips.route_name ("Cabanatuan-Rizal") != route_stops.route_name
| ("Cabanatuan to Rizal Route") != fare_pricing.route_name ("Cabanatuan-Rizal")
|--------------------------------------------------------------------------
*/

function normalize_route_key($s) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $s ?? ''));
}

function route_stops_route_name_for_trip($conn, $busTripRouteName) {
    $aliases = [
        'cabanatuanrizal'            => 'Cabanatuan to Rizal Route',
        'cabanatuantorizalroute'     => 'Cabanatuan to Rizal Route',
        'cabanatuan'                 => 'Cabanatuan to Rizal Route',
        'rizalcabanatuan'            => 'Rizal to Cabanatuan Route',
        'rizaltocabanatuanroute'     => 'Rizal to Cabanatuan Route',
    ];
    $key = normalize_route_key($busTripRouteName);
    if (isset($aliases[$key])) return $aliases[$key];

    $stmt = $conn->query("SELECT DISTINCT route_name FROM route_stops");
    $best = null;
    $bestLen = 0;
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $len = similar_text($key, normalize_route_key($row['route_name']));
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = $row['route_name'];
            }
        }
        $stmt->close();
    }
    return $best;
}

/*
|--------------------------------------------------------------------------
| DISTANCE + NEAREST STOP MATCHING
|--------------------------------------------------------------------------
*/

function haversine_meters($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = ($lat2 - $lat1) * M_PI / 180;
    $dLng = ($lng2 - $lng1) * M_PI / 180;
    $a = sin($dLat / 2) ** 2
        + cos($lat1 * M_PI / 180) * cos($lat2 * M_PI / 180) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/*
| Returns the nearest route_stops row on the given route_name that is within
| $thresholdM meters, or null when nothing qualifies. Never an arbitrary stop.
*/
function find_nearest_route_stop($conn, $routeName, $lat, $lng, $thresholdM = GPS_MATCH_THRESHOLD_M) {
    if ($routeName === null || $routeName === '') return null;

    $stmt = $conn->prepare("
        SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
        FROM route_stops
        WHERE route_name = ?
    ");
    if (!$stmt) return null;
    $stmt->bind_param("s", $routeName);
    $stmt->execute();
    $result = $stmt->get_result();

    $best = null;
    $bestDist = (float)$thresholdM;
    while ($row = $result->fetch_assoc()) {
        $d = haversine_meters((float)$lat, (float)$lng, (float)$row['latitude'], (float)$row['longitude']);
        if ($d < $bestDist) {
            $bestDist = $d;
            $best = $row;
        }
    }
    $stmt->close();
    return $best;
}

/*
|--------------------------------------------------------------------------
| PASSENGER ACTUAL DESTINATION RESOLUTION
|--------------------------------------------------------------------------
*/

/*
| Resolves the ACTUAL route stop for one passenger_trips row.
| $tripRouteName = bus_trips.route_name of the active trip.
| Returns a route_stops row or null (no valid actual destination).
*/
function resolve_passenger_actual_stop($conn, $passengerTripRow, $tripRouteName) {
    $routeName = route_stops_route_name_for_trip($conn, $tripRouteName);
    if (!$routeName) return null;

    // 1. Second QR scan actual destination (preferred, if the column exists)
    $actualStopId = $passengerTripRow['actual_stop_id'] ?? null;
    if (!empty($actualStopId)) {
        $stmt = $conn->prepare("
            SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
            FROM route_stops WHERE route_name = ? AND id = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("si", $routeName, (int)$actualStopId);
            $stmt->execute();
            $stop = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($stop) return $stop;
        }
    }
    $actualDestName = $passengerTripRow['actual_destination'] ?? $passengerTripRow['actual_stop_name'] ?? null;
    if (!empty($actualDestName)) {
        $stmt = $conn->prepare("
            SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
            FROM route_stops WHERE route_name = ? AND stop_name = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ss", $routeName, $actualDestName);
            $stmt->execute();
            $stop = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($stop) return $stop;
        }
    }

    // 2. Latest valid passenger_location_history -> nearest route stop
    $passengerTripId = isset($passengerTripRow['id']) ? (int)$passengerTripRow['id'] : 0;
    if ($passengerTripId <= 0) return null;

    $stmt = $conn->prepare("
        SELECT latitude, longitude, accuracy
        FROM passenger_location_history
        WHERE passenger_trip_id = ?
        ORDER BY recorded_at DESC
        LIMIT 50
    ");
    if (!$stmt) return null;
    $stmt->bind_param("i", $passengerTripId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $accuracy = $row['accuracy'];
        if ($accuracy !== null && (float)$accuracy > GPS_ACCURACY_MAX_M) continue;
        $stop = find_nearest_route_stop($conn, $routeName, $row['latitude'], $row['longitude']);
        if ($stop) {
            $stmt->close();
            return $stop;
        }
    }
    $stmt->close();
    return null;
}

/*
| Resolves the driver's upcoming route stop from driver_current_location.
| Returns a route_stops row or null.
*/
function resolve_driver_upcoming_stop($conn, $driverId, $tripRouteName) {
    $routeName = route_stops_route_name_for_trip($conn, $tripRouteName);
    if (!$routeName) return null;

    $stmt = $conn->prepare("
        SELECT latitude, longitude FROM driver_current_location
        WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("s", $driverId);
    $stmt->execute();
    $loc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loc) return null;
    return find_nearest_route_stop($conn, $routeName, $loc['latitude'], $loc['longitude']);
}

/*
|--------------------------------------------------------------------------
| FARE PRICING MATCHING + FINAL FARE
| (business rules copied from calculateFare.php - DO NOT redesign)
|--------------------------------------------------------------------------
*/

function normalize_dest_text($s) {
    $s = strtolower((string)$s);
    $s = str_replace([', nueva ecija', ', cabanatuan city', ', cabanatuan', ' city'], '', $s);
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/* Fuzzy stop-name -> fare_pricing row matching (same approach as calculateFare.php) */
function find_fare_pricing_row($rows, $stopText) {
    $value = normalize_dest_text($stopText);
    if ($value === '') return null;

    foreach ($rows as $r) {
        if (normalize_dest_text($r['destination']) === $value) return $r;
    }
    foreach ($rows as $r) {
        $d = normalize_dest_text($r['destination']);
        if ($d !== '' && (strpos($value, $d) !== false || strpos($d, $value) !== false)) return $r;
    }
    $words = explode(' ', $value);
    foreach ($rows as $r) {
        $d = normalize_dest_text($r['destination']);
        foreach ($words as $w) {
            if (strlen($w) >= 4 && strpos($d, $w) !== false) return $r;
        }
    }
    return null;
}

function load_fare_pricing($conn, $fareRouteName) {
    $stmt = $conn->prepare("
        SELECT id, route_name, destination, display_order, regular_fare, student_fare, special_fare
        FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param("s", $fareRouteName);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function compute_fare_for_category($boardingRow, $actualRow, $column) {
    $minimumFare = 15;
    if ((int)$boardingRow['id'] === (int)$actualRow['id']) {
        return (float)$minimumFare;
    }
    if ((int)$boardingRow['display_order'] === 1) {
        return (float)$actualRow[$column];
    }
    $computed = (float)$actualRow[$column] - (float)$boardingRow[$column];
    return max($computed, (float)$minimumFare);
}

/*
| Infers the fare category (regular/student/special) by comparing the stored
| estimated fare against each category's computed fare for the boarding ->
| selected destination pair. Defaults to regular when undeterminable.
*/
function infer_fare_category($conn, $fareRouteName, $boardingText, $selectedDestText, $storedFare) {
    $rows = load_fare_pricing($conn, $fareRouteName);
    if (empty($rows)) return 'regular';
    $boardRow = find_fare_pricing_row($rows, $boardingText);
    $destRow = find_fare_pricing_row($rows, $selectedDestText);
    if (!$boardRow || !$destRow || $storedFare === null) return 'regular';

    $best = 'regular';
    $bestDiff = PHP_FLOAT_MAX;
    foreach (['regular_fare' => 'regular', 'student_fare' => 'student', 'special_fare' => 'special'] as $col => $cat) {
        $computed = compute_fare_for_category($boardRow, $destRow, $col);
        $diff = abs($computed - (float)$storedFare);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $best = $cat;
        }
    }
    return $best;
}

/*
| Computes the FINAL FARE for boarding point -> actual destination.
| $category: 'regular' | 'student' | 'special' | null (null = infer from the
| stored estimated fare vs boarding -> selected destination pair).
| Returns a rounded float or null when stops cannot be matched.
*/
function compute_final_fare($conn, $fareRouteName, $boardingText, $actualStopName, $category = null, $storedFare = null, $selectedDestText = null) {
    $rows = load_fare_pricing($conn, $fareRouteName);
    if (empty($rows)) return null;
    $boardRow = find_fare_pricing_row($rows, $boardingText);
    $actualRow = find_fare_pricing_row($rows, $actualStopName);
    if (!$boardRow || !$actualRow) return null;

    if ($category === null) {
        $category = infer_fare_category($conn, $fareRouteName, $boardingText, $selectedDestText, $storedFare);
    }

    switch (strtolower($category)) {
        case 'student':
        case 'senior':
        case 'pwd':
            $column = 'student_fare';
            break;
        case 'teacher':
        case 'nurse':
        case 'special':
            $column = 'special_fare';
            break;
        default:
            $column = 'regular_fare';
            break;
    }

    return round(compute_fare_for_category($boardRow, $actualRow, $column), 2);
}
