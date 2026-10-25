<?php
clearstatcache();
if (function_exists('opcache_reset')) opcache_reset();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $boarding_code = isset($_GET['boarding_code']) ? trim($_GET['boarding_code']) : '';
    $destination_code = isset($_GET['destination_code']) ? trim($_GET['destination_code']) : '';
    $driver_id = isset($_GET['driver_id']) ? trim($_GET['driver_id']) : '';
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'regular';

    if ($boarding_code === '') {
        echo json_encode(["success" => false, "message" => "Missing boarding_code"]);
        exit;
    }
    if ($driver_id === '') {
        echo json_encode(["success" => false, "message" => "Missing driver_id"]);
        exit;
    }

    // ── 1. GET BOARDING STOP ──

    $boardingId = (int)$boarding_code;
    $stmt = $conn->prepare("SELECT id, route_name, destination, display_order, regular_fare, student_fare, special_fare FROM fare_pricing WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $boardingId);
    $stmt->execute();
    $boardingStop = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$boardingStop) {
        echo json_encode(["success" => false, "message" => "Boarding stop not found"]);
        exit;
    }

    // ── 2. GET ALL ROUTE STOPS ──

    $routeName = $boardingStop['route_name'];
    $stmt = $conn->prepare("SELECT id, destination, display_order, regular_fare, student_fare, special_fare FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC");
    $stmt->bind_param("s", $routeName);
    $stmt->execute();
    $farePricing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$farePricing) {
        echo json_encode(["success" => false, "message" => "No stops found for route"]);
        exit;
    }

    // ── 3. GET DRIVER GPS ──

    $driverId = $driver_id;
    $locStmt = $conn->prepare("SELECT latitude, longitude FROM driver_current_location WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
    $locStmt->bind_param("s", $driverId);
    $locStmt->execute();
    $locResult = $locStmt->get_result();
    $driverGps = null;
    if ($locResult && $locResult->num_rows > 0) {
        $loc = $locResult->fetch_assoc();
        $driverGps = ["lat" => (float)$loc['latitude'], "lng" => (float)$loc['longitude']];
    }
    $locStmt->close();

    // ── 4. REVERSE GEOCODE GPS → READABLE ADDRESS ──

    $gpsAddress = null;
    $geoDebug = ["gps" => $driverGps];

    if ($driverGps) {
        $apiKey = 'AIzaSyAxCwxIOQMp1qYB6Jo3CMT_sZOsO1rkW2U';
        $geoUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$driverGps['lat']},{$driverGps['lng']}&key={$apiKey}";
        $geoResponse = @file_get_contents($geoUrl);
        $geoData = $geoResponse ? json_decode($geoResponse, true) : null;
        $geoDebug['api_status'] = $geoData['status'] ?? 'NO_RESPONSE';

        if ($geoData && !empty($geoData['results'][0])) {
            $gpsAddress = $geoData['results'][0]['formatted_address'];
            $geoDebug['formatted_address'] = $gpsAddress;
        } else {
            $geoDebug['error'] = $geoData['error_message'] ?? 'No geocode result';
        }
    }

    // ── 5. MATCH GPS ADDRESS TO FARE_PRICING (using user's logic) ──

    function normalizeDest($value) {
        $value = strtolower((string)$value);
        $value = str_replace([
            ', nueva ecija', ', cabanatuan city', ', cabanatuan', ' city'
        ], '', $value);
        $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    $findStopByText = function($location, $stops) {
        if (!$location || empty($stops)) return null;

        $originalValue = strtolower(trim((string)$location));
        $value = normalizeDest($location);

        // 1. Exact match
        foreach ($stops as $s) {
            if (normalizeDest($s['destination']) === $value) return $s;
        }

        // 2. Partial match (contains)
        foreach ($stops as $s) {
            $norm = normalizeDest($s['destination']);
            if (strpos($value, $norm) !== false || strpos($norm, $value) !== false) return $s;
        }

        // 3. Destination word match
        foreach ($stops as $s) {
            $norm = normalizeDest($s['destination']);
            $words = explode(' ', $norm);
            foreach ($words as $w) {
                if (strlen($w) < 4) continue;
                if (strpos($value, $w) !== false) return $s;
            }
        }

        // 4. Location word match
        foreach ($stops as $s) {
            $norm = normalizeDest($s['destination']);
            $words = explode(' ', $value);
            foreach ($words as $w) {
                if (strlen($w) < 4) continue;
                if (strpos($norm, $w) !== false) return $s;
            }
        }

        // 5. "Cabanatuan" fallback
        if (strpos($originalValue, 'cabanatuan') !== false) {
            foreach ($stops as $s) {
                $dest = strtolower($s['destination']);
                if (strpos($dest, 'cabanatuan central terminal') !== false) return $s;
            }
        }

        return null;
    };

    $destinationStop = null;
    $matchMethod = 'none';

    // First: try from destination_code (frontend determined)
    if ($destination_code !== '') {
        foreach ($farePricing as $s) {
            if ((int)$s['id'] === (int)$destination_code) {
                $destinationStop = $s;
                $matchMethod = 'frontend_code';
                break;
            }
        }
    }

    // Second: try GPS address matching
    if (!$destinationStop && $gpsAddress) {
        $destinationStop = $findStopByText($gpsAddress, $farePricing);
        if ($destinationStop) $matchMethod = 'gps_address';
    }

    // Third: try matching driver GPS search terms from reverse geo components
    if (!$destinationStop && $driverGps) {
        $apiKey = 'AIzaSyAxCwxIOQMp1qYB6Jo3CMT_sZOsO1rkW2U';
        $geoUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$driverGps['lat']},{$driverGps['lng']}&key={$apiKey}";
        $geoResponse = @file_get_contents($geoUrl);
        $geoData = $geoResponse ? json_decode($geoResponse, true) : null;
        if ($geoData && !empty($geoData['results'][0]['address_components'])) {
            foreach ($geoData['results'][0]['address_components'] as $comp) {
                $name = $comp['long_name'];
                $result = $findStopByText($name, $farePricing);
                if ($result) {
                    $destinationStop = $result;
                    $matchMethod = 'gps_component: ' . $name;
                    break;
                }
            }
        }
    }

    // Fourth: last stop fallback
    if (!$destinationStop) {
        $destinationStop = $farePricing[count($farePricing) - 1];
        $matchMethod = 'last_stop_fallback';
    }

    $geoDebug['match_method'] = $matchMethod;
    $geoDebug['matched_stop'] = $destinationStop['destination'];

    // ── 6. VALIDATE ROUTE DIRECTION ──

    $boardingOrder = (int)$boardingStop['display_order'];
    $destOrder = (int)$destinationStop['display_order'];

    if ($destOrder < $boardingOrder) {
        echo json_encode([
            "success" => false,
            "message" => "Destination is behind boarding point — cannot travel backwards",
            "debug" => [
                "boarding" => $boardingStop['destination'],
                "boarding_order" => $boardingOrder,
                "destination" => $destinationStop['destination'],
                "destination_order" => $destOrder,
                "geo" => $geoDebug
            ]
        ]);
        exit;
    }

// ── 7. COMPUTE FARE ──

$minimumFare = 15;

// Select fare column based on passenger type
switch ($type) {

    case 'student':
    case 'senior':
    case 'pwd':
        $fareColumn = 'student_fare';
        break;

    case 'teacher':
    case 'nurse':
    case 'special':
        $fareColumn = 'special_fare';
        break;

    default:
        $fareColumn = 'regular_fare';
        break;
}

$boardingFare = (float)$boardingStop[$fareColumn];
$destinationFare = (float)$destinationStop[$fareColumn];

// -----------------------------------------------------
// BUSINESS RULE
// -----------------------------------------------------
//
// 1. Passenger boarded at the terminal
//    → Charge the destination fare.
//
// 2. Passenger boarded after the terminal
//    → Destination Fare − Boarding Fare.
//
// 3. If computed fare is below minimum fare,
//    → Charge the minimum fare.
//
// 4. Same boarding and destination
//    → Charge the minimum fare.
// -----------------------------------------------------

if ($boardingStop['id'] == $destinationStop['id']) {

    // Same stop
    $computedFare = 0;
    $fare = $minimumFare;

} elseif ((int)$boardingStop['display_order'] === 1) {

    // Boarded at terminal
    $computedFare = $destinationFare;
    $fare = $destinationFare;

} else {

    // Boarded after terminal
    $computedFare = $destinationFare - $boardingFare;

    if ($computedFare < $minimumFare) {
        $fare = $minimumFare;
    } else {
        $fare = $computedFare;
    }
}

$fare = round($fare, 2);
$computedFare = round($computedFare, 2);

    // ── 8. RESPONSE ──

    echo json_encode([
        "success" => true,
        "route_name" => $boardingStop['route_name'],
        "boarding_stop" => $boardingStop['destination'],
        "boarding_order" => $boardingOrder,
        "destination" => $destinationStop['destination'],
        "destination_order" => $destOrder,
        "boarding_id" => (int)$boardingStop['id'],
        "destination_id" => (int)$destinationStop['id'],
        "passenger_type" => $type,
        "boarding_fare" => $boardingFare,
        "destination_fare" => $destinationFare,
        "computed_fare" => round($destinationFare - $boardingFare, 2),
        "fare" => round($fare, 2),
        "debug" => ["geo" => $geoDebug]
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "debug" => ["file" => $e->getFile(), "line" => $e->getLine()]
    ]);
}