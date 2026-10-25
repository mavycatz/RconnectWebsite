<?php

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

    $tripNo =
        isset($_GET['trip_no'])
            ? trim($_GET['trip_no'])
            : '';

    $boarding =
        isset($_GET['boarding'])
            ? trim($_GET['boarding'])
            : '';

    $destination =
        isset($_GET['destination'])
            ? trim($_GET['destination'])
            : '';

    $boardingId =
        isset($_GET['boarding_id'])
            ? (int)$_GET['boarding_id']
            : null;

    $destinationId =
        isset($_GET['destination_id'])
            ? (int)$_GET['destination_id']
            : null;

    $type =
        isset($_GET['type'])
            ? strtolower(trim($_GET['type']))
            : 'regular';

    $minimumFare = 15;

    /*
    |--------------------------------------------------------------------------
    | ROUTE RESOLUTION
    |--------------------------------------------------------------------------
    */

    $routeName = '';

    error_log("[calculate_fare] trip_no: '" . $tripNo . "'");

    if ($tripNo !== '') {
        $stmt = $conn->prepare("
            SELECT route_name
            FROM bus_trips
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Route prepare failed: " . $conn->error);
        }
        $tripNoInt = (int)$tripNo;
        $stmt->bind_param("i", $tripNoInt);
        if (!$stmt->execute()) {
            throw new Exception("Route execute failed: " . $stmt->error);
        }
        $row = $stmt->get_result()->fetch_assoc();
        $routeName = $row ? trim($row['route_name']) : '';
        $stmt->close();
    }

    error_log("[calculate_fare] resolved route_name: '" . $routeName . "'");

    // If only trip_no provided, return resolved route name
    if ($boarding === '' && $destination === '' && $boardingId === null && $destinationId === null) {
        echo json_encode([
            "success" => true,
            "route_name" => $routeName,
            "message" => $routeName !== '' ? 'route_resolved' : 'no_route_found'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD FARE PRICING
    |--------------------------------------------------------------------------
    */

    $farePricing = [];

    if ($routeName !== '') {
        $stmt = $conn->prepare("
            SELECT id, destination, display_order, regular_fare, student_fare, special_fare
            FROM fare_pricing
            WHERE route_name = ?
            ORDER BY display_order ASC
        ");
        if (!$stmt) {
            throw new Exception("Fare pricing prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $routeName);
        if (!$stmt->execute()) {
            throw new Exception("Fare pricing execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $farePricing = [];
        while ($row = $result->fetch_assoc()) {
            $farePricing[] = $row;
        }
        $stmt->close();
    }

    // Fallback: load all fare_pricing if no route resolved
    if (empty($farePricing)) {
        $stmt = $conn->prepare("
            SELECT id, destination, display_order, regular_fare, student_fare, special_fare, route_name
            FROM fare_pricing
            ORDER BY display_order ASC
        ");
        if (!$stmt) {
            throw new Exception("Fallback fare prepare failed: " . $conn->error);
        }
        if (!$stmt->execute()) {
            throw new Exception("Fallback fare execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $farePricing = [];
        while ($row = $result->fetch_assoc()) {
            $farePricing[] = $row;
        }
        $stmt->close();
    }

    if (empty($farePricing)) {
        echo json_encode([
            "success" => false,
            "fare" => 0,
            "_debug_why" => "no_fare_pricing_found",
            "_debug_routeName" => $routeName ?: 'NULL'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DESTINATION MATCHING (NORMALIZE + FUZZY)
    |--------------------------------------------------------------------------
    */

    $normalize = function($value) {
        $value = strtolower((string)$value);
        $value = str_replace([
            ', nueva ecija',
            ', cabanatuan city',
            ', cabanatuan',
            ' city'
        ], '', $value);
        $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    };

    $findStopByName = function($location) use ($farePricing, $normalize) {
        $originalValue = strtolower(trim((string)$location));
        $value = $normalize($location);

        // Exact match
        foreach ($farePricing as $fp) {
            if ($normalize($fp['destination']) === $value) {
                return $fp;
            }
        }

        // Partial match
        foreach ($farePricing as $fp) {
            $fpNorm = $normalize($fp['destination']);
            if (strpos($value, $fpNorm) !== false || strpos($fpNorm, $value) !== false) {
                return $fp;
            }
        }

        // Destination word match
        foreach ($farePricing as $fp) {
            $fpNorm = $normalize($fp['destination']);
            $words = explode(' ', $fpNorm);
            foreach ($words as $word) {
                if (strlen($word) < 4) continue;
                if (strpos($value, $word) !== false) {
                    return $fp;
                }
            }
        }

        // Location word match
        foreach ($farePricing as $fp) {
            $fpNorm = $normalize($fp['destination']);
            $words = explode(' ', $value);
            foreach ($words as $word) {
                if (strlen($word) < 4) continue;
                if (strpos($fpNorm, $word) !== false) {
                    return $fp;
                }
            }
        }

        // Cabanatuan terminal fallback
        if (strpos($originalValue, 'cabanatuan') !== false) {
            foreach ($farePricing as $fp) {
                $dest = strtolower($fp['destination']);
                if (strpos($dest, 'cabanatuan central terminal') !== false) {
                    return $fp;
                }
            }
        }

        return null;
    };

    /*
    |--------------------------------------------------------------------------
    | FIND BOARDING & DESTINATION STOPS
    |--------------------------------------------------------------------------
    */

    $boardingStop = $boarding !== '' ? $findStopByName($boarding) : null;
    $destinationStop = $destination !== '' ? $findStopByName($destination) : null;

    // ID-based fallback if name matching failed
    if (!$boardingStop && $boardingId !== null) {
        foreach ($farePricing as $fp) {
            if ((int)$fp['id'] === $boardingId) {
                $boardingStop = $fp;
                break;
            }
        }
    }
    if (!$destinationStop && $destinationId !== null) {
        foreach ($farePricing as $fp) {
            if ((int)$fp['id'] === $destinationId) {
                $destinationStop = $fp;
                break;
            }
        }
    }

    // Fallback: if one stop isn't found but names overlap, treat as same stop
    $fromNorm = $normalize($boarding);
    $toNorm = $normalize($destination);
    if (
        !$boardingStop && $destinationStop &&
        (strpos($fromNorm, $toNorm) !== false || strpos($toNorm, $fromNorm) !== false)
    ) {
        $boardingStop = $destinationStop;
    } elseif (
        $boardingStop && !$destinationStop &&
        (strpos($fromNorm, $toNorm) !== false || strpos($toNorm, $fromNorm) !== false)
    ) {
        $destinationStop = $boardingStop;
    }

    // Same stop detection
    if (
        $boardingStop &&
        $destinationStop &&
        (int)$boardingStop['id'] === (int)$destinationStop['id']
    ) {
        echo json_encode([
            "success" => true,
            "fare" => $minimumFare,
            "boarding_fare" => $minimumFare,
            "destination_fare" => $minimumFare,
            "boarding_stop" => $boardingStop['destination'],
            "destination_stop" => $destinationStop['destination'],
            "route_name" => $routeName ?: ($boardingStop['route_name'] ?? ''),
            "same_stop" => true
        ]);
        exit;
    }

    if (!$boardingStop || !$destinationStop) {
        echo json_encode([
            "success" => false,
            "fare" => 0,
            "_debug_why" => "stop_not_found",
            "_debug_boarding_found" => $boardingStop ? $boardingStop['destination'] : 'NULL',
            "_debug_dest_found" => $destinationStop ? $destinationStop['destination'] : 'NULL',
            "_debug_farePricing_count" => count($farePricing),
            "_debug_boardingId" => $boardingId,
            "_debug_destinationId" => $destinationId
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FARE COMPUTATION
    |--------------------------------------------------------------------------
    */

    $fareColumn = match (strtolower($type)) {
        'student', 'senior', 'senior citizen', 'pwd' => 'student_fare',
        'teacher', 'nurse', 'special' => 'special_fare',
        default => 'regular_fare'
    };

    $boardingFare = (float)($boardingStop[$fareColumn] ?? $minimumFare);
    $destinationFare = (float)($destinationStop[$fareColumn] ?? $minimumFare);

    // Terminal rule: display_order === 1 → fare = destination fare
    if ((int)$boardingStop['display_order'] === 1) {
        $fare = $destinationFare;
    } else {
        $fare = $destinationFare - $boardingFare;
    }

    // Minimum fare enforcement
    $fare = max($fare, $minimumFare);
    $fare = round($fare, 2);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "fare" => $fare,
        "boarding_fare" => $boardingFare,
        "destination_fare" => $destinationFare,
        "boarding_stop" => $boardingStop['destination'],
        "destination_stop" => $destinationStop['destination'],
        "boarding_id" => (int)$boardingStop['id'],
        "destination_id" => (int)$destinationStop['id'],
        "route_name" => $routeName ?: ($boardingStop['route_name'] ?? ''),
        "passenger_type" => $type,
        "fare_column" => $fareColumn
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
