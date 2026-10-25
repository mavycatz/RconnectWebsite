<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);
    $driverId   = trim($input['driver_id'] ?? '');
    $boardingId = (int)($input['boarding_id'] ?? 0);
    $type       = strtolower(trim($input['passenger_type'] ?? 'regular'));

    if (empty($driverId) || empty($boardingId)) {
        echo json_encode(["success" => false, "message" => "driver_id and boarding_id required"]);
        exit;
    }

    // 1. GET ACTIVE TRIP
    $tripStmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE driver_id = ? AND status IN ('Departed','On Route') ORDER BY id DESC LIMIT 1");
    if (!$tripStmt) throw new Exception($conn->error);
    $tripStmt->bind_param("s", $driverId); $tripStmt->execute();
    $trip = $tripStmt->get_result()->fetch_assoc(); $tripStmt->close();
    if (!$trip) { echo json_encode(["success" => false, "message" => "No active trip found"]); exit; }
    $routeName = $trip['route_name'];

    // 2. GET DRIVER LIVE GPS from driver_current_location OR drivers_location
    $lat = null; $lng = null;
    $gpsStmt = $conn->prepare("SELECT latitude, longitude FROM driver_current_location WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
    if ($gpsStmt) {
        $gpsStmt->bind_param("s", $driverId); $gpsStmt->execute();
        $gps = $gpsStmt->get_result()->fetch_assoc(); $gpsStmt->close();
        if ($gps) { $lat = (float)$gps['latitude']; $lng = (float)$gps['longitude']; }
    }
    if ($lat === null) {
        $gpsStmt2 = $conn->prepare("SELECT latitude, longitude FROM driver_locations WHERE driver_id = ? ORDER BY id DESC LIMIT 1");
        if ($gpsStmt2) {
            $gpsStmt2->bind_param("s", $driverId); $gpsStmt2->execute();
            $gps2 = $gpsStmt2->get_result()->fetch_assoc(); $gpsStmt2->close();
            if ($gps2) { $lat = (float)$gps2['latitude']; $lng = (float)$gps2['longitude']; }
        }
    }
    if ($lat === null) { echo json_encode(["success" => false, "message" => "Driver GPS not found"]); exit; }

    // 3. REVERSE GEOCODE to readable components
    $locationNames = [];
    $readableLocation = "{$lat}, {$lng}";

    // Try Google Geocoding API
    $apiKey = 'AIzaSyCpfJTeAWl5cCJC1RrON5_WQS3P8nFYyYE';
    $geoUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$apiKey}&language=en";
    $geoResp = @file_get_contents($geoUrl);
    $geoData = $geoResp ? json_decode($geoResp, true) : null;
    if ($geoData && $geoData['status'] === 'OK' && !empty($geoData['results'])) {
        $readableLocation = $geoData['results'][0]['formatted_address'];
        foreach ($geoData['results'][0]['address_components'] as $comp) {
            $locationNames[] = $comp['long_name'];
            $locationNames[] = $comp['short_name'];
        }
        $locationNames[] = $readableLocation;
    }

    // Try OpenStreetMap as fallback
    if (empty($locationNames)) {
        $osmUrl = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lng}&format=json&accept-language=en";
        $opts = stream_context_create(['http' => ['header' => "User-Agent: RConnectApp/1.0\r\n"]]);
        $osmResp = @file_get_contents($osmUrl, false, $opts);
        $osmData = $osmResp ? json_decode($osmResp, true) : null;
        if ($osmData && !empty($osmData['display_name'])) {
            $readableLocation = $osmData['display_name'];
            foreach (explode(',', $osmData['display_name']) as $part) {
                $locationNames[] = trim($part);
            }
            $locationNames[] = $readableLocation;
            if (!empty($osmData['address'])) {
                foreach ($osmData['address'] as $addr) { $locationNames[] = $addr; }
            }
        }
    }

    $locationNames = array_values(array_unique(array_filter($locationNames)));
    $locationNames[] = "{$lat}, {$lng}";

    // 4. GET ALL FARE PRICING FOR THE ROUTE
    $fpStmt = $conn->prepare("SELECT id, destination, regular_fare, student_fare, special_fare, display_order FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC");
    if (!$fpStmt) throw new Exception($conn->error);
    $fpStmt->bind_param("s", $routeName); $fpStmt->execute();
    $fpResult = $fpStmt->get_result();
    $allStops = [];
    while ($row = $fpResult->fetch_assoc()) { $allStops[] = $row; }
    $fpStmt->close();
    if (empty($allStops)) { echo json_encode(["success" => false, "message" => "No fare pricing for this route"]); exit; }

    // 5. FUZZY MATCH location names against fare_pricing destinations
    $bestMatch = null; $bestScore = 0;
    foreach ($allStops as $stop) {
        $dest = strtolower($stop['destination']);
        foreach ($locationNames as $locName) {
            $loc = strtolower(trim($locName));
            if (empty($loc)) continue;
            if ($dest === $loc) { $score = 100; }
            elseif (strpos($dest, $loc) !== false || strpos($loc, $dest) !== false) { $score = 80; }
            else {
                $destWords = preg_split('/[\s\-\/\,\(\)\.]+/', $dest);
                $locWords = preg_split('/[\s\-\/\,\(\)\.]+/', $loc);
                $common = array_intersect($destWords, $locWords);
                $longCommon = array_filter($common, fn($w) => strlen($w) > 2);
                $score = count($longCommon) * 20;
                if ($score === 0) {
                    $minLen = min(strlen($dest), strlen($loc));
                    $lev = levenshtein(substr($dest, 0, 20), substr($loc, 0, 20));
                    if ($lev > 0 && $lev < $minLen * 0.4) $score = max(1, 60 - ($lev * 5));
                }
            }
            if ($score > $bestScore) { $bestScore = $score; $bestMatch = $stop; }
        }
    }

    if (!$bestMatch || $bestScore < 20) {
        echo json_encode([
            "success" => false,
            "message" => "Could not determine destination from GPS",
            "gps_coords" => "{$lat}, {$lng}",
            "gps_location" => $readableLocation,
            "location_names" => $locationNames,
            "candidates" => array_map(fn($s) => $s['destination'], $allStops)
        ]);
        exit;
    }

    // 6. FIND BOARDING STOP INDEX AND VALIDATE
    $boardingIdx = -1; $destIdx = -1;
    foreach ($allStops as $i => $s) {
        if ((int)$s['id'] === $boardingId) $boardingIdx = $i;
        if ((int)$s['id'] === (int)$bestMatch['id']) $destIdx = $i;
    }
    if ($boardingIdx === -1) { echo json_encode(["success" => false, "message" => "Boarding stop not found"]); exit; }

    // 7. EFFECTIVE DESTINATION = max of boarding and GPS
    $effectiveIdx = $boardingIdx;
    if ($destIdx >= 0 && $destIdx > $boardingIdx) $effectiveIdx = $destIdx;
    $effectiveDest = $allStops[$effectiveIdx];

    // 8. COMPUTE FARE = destinationFare - boardingFare
    $boardingFare = $allStops[$boardingIdx];
    switch ($type) {
        case 'student': case 'senior': case 'pwd':
            $boardAmt = (float)$boardingFare['student_fare'];
            $destAmt = (float)$effectiveDest['student_fare'];
            break;
        case 'special': case 'teacher': case 'nurse':
            $boardAmt = (float)$boardingFare['special_fare'];
            $destAmt = (float)$effectiveDest['special_fare'];
            break;
        default:
            $boardAmt = (float)$boardingFare['regular_fare'];
            $destAmt = (float)$effectiveDest['regular_fare'];
            break;
    }
    $fare = round(max($destAmt - $boardAmt, 15), 2);

    echo json_encode([
        "success" => true,
        "fare" => $fare,
        "boarding_stop" => $boardingFare['destination'],
        "destination" => $effectiveDest['destination'],
        "destination_id" => (int)$effectiveDest['id'],
        "passenger_type" => $type,
        "gps_coords" => "{$lat}, {$lng}",
        "gps_location" => $readableLocation,
        "match_score" => $bestScore
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
