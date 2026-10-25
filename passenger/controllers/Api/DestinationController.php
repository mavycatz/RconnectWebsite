<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/FareCalculator.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code' => 'AUTH_REQUIRED',
        'step' => 'auth',
        'message' => 'Not authenticated'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

$passengerInput = [];
$rawInput = file_get_contents('php://input');
if (is_string($rawInput) && $rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $passengerInput = $decoded;
    }
}
$GLOBALS['passengerInput'] = $passengerInput;

function passenger_input_value(string $key, $default = null)
{
    if (isset($GLOBALS['passengerInput'][$key]) && $GLOBALS['passengerInput'][$key] !== '') {
        return $GLOBALS['passengerInput'][$key];
    }
    return $_GET[$key] ?? $default;
}

// Declared before the switch: conditional declarations are not hoisted, so the
// handler would not see this helper if it were declared lower in the file.
if (!function_exists('resolveDestinationFareRouteName')) {
    /**
     * Resolve the fare_pricing route name for a trip's route.
     *
     * Passenger-local equivalent of the fare-engine's resolveFareRouteName():
     * the deployed fare-engine copy may predate that function, and the list
     * endpoint must not depend on it. It only matches existing fare_pricing
     * names by canonical comparison; it never changes fare values or ordering.
     */
    function resolveDestinationFareRouteName($conn, $routeName)
    {
        $routeName = trim((string) $routeName);

        if (
            $routeName === '' ||
            !function_exists('canonicalRouteName') ||
            !($conn instanceof PDO)
        ) {
            return $routeName;
        }

        $canonical = canonicalRouteName($routeName);
        if ($canonical === '') {
            return $routeName;
        }

        try {
            $stmt = $conn->query("
                SELECT DISTINCT route_name
                FROM fare_pricing
                WHERE route_name IS NOT NULL AND route_name <> ''
            ");
            $candidates = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (\Throwable $e) {
            return $routeName;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && canonicalRouteName($candidate) === $canonical) {
                return $candidate;
            }
        }

        return $routeName;
    }
}

switch ($action) {

    case 'destinations':
        handleDestinations();
        break;

    case 'resolve-stop':
        handleResolveStop();
        break;

    case 'calculate-fare':
        handleCalculateFare();
        break;

    case 'active-trips':
        handleActiveTrips();
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action'
        ]);
        break;
}

function handleActiveTrips()
{
    try {
        $conn = Database::tryGet();
        if (!$conn) {
            throw new \RuntimeException('Database unavailable');
        }

        $stmt = $conn->prepare("
            SELECT id, trip_number AS trip_no, bus_id AS bus_no, route_name, status
            FROM bus_trips
            WHERE status IN ('Scheduled', 'Departed', 'On Route')
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute();
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'trips' => $trips
        ]);
    } catch (\Throwable $e) {
        error_log('Passenger api/active-trips failed: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load trips right now.'
        ]);
    }
}

function handleDestinations()
{
    // Per-step tracing for the destination list flow. The debug section in
    // failure responses is exposed only when PASSENGER_QR_SCAN_DEBUG is on;
    // the structured log line is always written so failures remain reviewable.
    $destDebug = function_exists('passenger_qr_scan_debug_enabled')
        ? passenger_qr_scan_debug_enabled()
        : false;
    $destStep = 'input';
    $destContext = [
        'passenger_id' => isset($_SESSION['passenger_id']) ? (int) $_SESSION['passenger_id'] : null,
        'trip_no' => null,
        'boarding_stop_id' => null,
    ];
    $destErrorDetail = [];

    $destLogAttempt = function (array $entry) use (&$destStep, &$destContext) {
        if (!function_exists('passenger_qr_scan_log')) {
            return;
        }

        passenger_qr_scan_log(array_merge([
            'route' => '/passenger/api/destinations',
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'step' => $destStep,
            'request' => $destContext,
        ], $entry));
    };

    $destSendFailure = function (string $code, string $message, int $status = 400, array $extra = []) use (&$destStep, &$destErrorDetail, $destDebug, $destLogAttempt) {
        $body = array_merge([
            'success' => false,
            'code' => $code,
            'step' => $destStep,
            'message' => $message,
        ], $extra);

        if ($destDebug) {
            $debug = [
                'step' => $destStep,
                'http_status' => $status,
            ];
            if (!empty($destErrorDetail['exception'])) {
                $debug['exception'] = $destErrorDetail['exception'];
            }
            if (!empty($destErrorDetail['sql'])) {
                $debug['sql'] = $destErrorDetail['sql'];
            }
            $body['debug'] = $debug;
        }

        $destLogAttempt(array_merge([
            'type' => 'failure',
            'code' => $code,
            'http_status' => $status,
            'message' => $message,
        ], $destErrorDetail));

        http_response_code($status);
        echo json_encode($body);
        exit;
    };

    try {

    $tripId = (int)(passenger_input_value('trip_no', 0));
    $boardingStopId = (int)(passenger_input_value('boarding_stop_id', 0));
    $boardingPoint = trim((string)(passenger_input_value('boarding_point', '')));
    $destContext['trip_no'] = $tripId;
    $destContext['boarding_stop_id'] = $boardingStopId;

    if ($tripId <= 0) {
        $destSendFailure('TRIP_ID_REQUIRED', 'Trip ID is required.', 400);
    }

    $destStep = 'active_trip_lookup';
    $conn = Database::tryGet();

    if ($conn === null) {
        $destSendFailure('DB_UNAVAILABLE', 'We could not reach the service right now. Please try again in a moment.', 503);
    }

    $destStep = 'route_resolution';

    $stmt = $conn->prepare("
        SELECT route_name
        FROM bus_trips
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$tripId]);

    $routeName = $stmt->fetchColumn();

    if (!$routeName) {
        $destSendFailure('TRIP_ROUTE_MISSING', 'This trip has no route assigned yet. Please ask the driver or try again later.', 422);
    }

    // Route names differ across tables ("Cabanatuan-Rizal" vs
    // "Cabanatuan to Rizal Route"); resolve the canonical fare_pricing name so
    // an active trip never reports an empty stop list because of naming.
    $fareRouteName = resolveDestinationFareRouteName($conn, (string)$routeName);

    $destStep = 'list_query';

    // Destination options are offered from the route's highest display_order
    // down (e.g. 61 → 60 → ... → boarding+1); the filter below keeps only the
    // stops ahead of the boarding point.
    $stmt = $conn->prepare("
        SELECT
            id,
            destination,
            display_order,
            regular_fare,
            student_fare,
            special_fare
        FROM fare_pricing
        WHERE route_name = ?
        ORDER BY display_order DESC
    ");

    $stmt->execute([$fareRouteName]);

    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // When the caller supplies the boarding stop, offer the boarding stop
    // itself (the "local" trip, settled at the same-stop minimum fare) and
    // every stop ahead of it on the route's own display_order; stops behind
    // the boarding point are not offered. The query returns them highest-first
    // and the boarding stop is flagged so the client can label it.
    $boardingOrder = null;
    if ($boardingStopId > 0 || $boardingPoint !== '') {
        require_once __DIR__ . '/../../services/BoardingStopOrder.php';
        $boardingZone = passengerResolveBoardingFareZone($conn, $routeName, $boardingPoint, $boardingStopId);

        if ($boardingZone !== null) {
            $boardingOrder = (int)$boardingZone['display_order'];
        }
    }

    // Adaptive progress: stops the bus has already left are not offered any
    // more. The bus's current (simulated or live) position decides the
    // effective threshold; the recorded boarding order stays the floor and
    // the fallback when the trip has no usable bus position.
    $busOrder = null;
    $progressOrder = $boardingOrder;

    try {
        $busColumns = [];
        try {
            $busStmt = $conn->prepare("SELECT bus_id, driver_id FROM bus_trips WHERE id = ? LIMIT 1");
            $busStmt->execute([$tripId]);
            $busColumns = $busStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $busColumns = [];
        }

        require_once __DIR__ . '/../../services/BusBoardingService.php';

        $orderedFareStops = $destinations;
        usort($orderedFareStops, static function (array $a, array $b): int {
            return (int)($a['display_order'] ?? 0) <=> (int)($b['display_order'] ?? 0);
        });

        $busBoardingState = passengerResolveBusBoarding($conn, [
            'id' => $tripId,
            'route_name' => (string)$routeName,
            'bus_id' => (string)($busColumns['bus_id'] ?? ''),
            'driver_id' => (string)($busColumns['driver_id'] ?? ''),
        ], $orderedFareStops);

        if ($busBoardingState['error'] === null && !empty($busBoardingState['boarding'])) {
            $busOrder = (int)$busBoardingState['boarding']['display_order'];
            $progressOrder = $progressOrder === null ? $busOrder : max($progressOrder, $busOrder);
        }
    } catch (\Throwable $e) {
        $busOrder = null;
    }

    if ($progressOrder !== null) {
        $destinations = array_values(array_filter(
            $destinations,
            function ($destination) use ($progressOrder) {
                return (int)($destination['display_order'] ?? 0) >= $progressOrder;
            }
        ));

        foreach ($destinations as &$destination) {
            $order = (int)($destination['display_order'] ?? 0);
            $destination['is_boarding'] = $boardingOrder !== null && $order === $boardingOrder;
            $destination['is_current'] = $busOrder !== null && $order === $busOrder;
        }
        unset($destination);
    }

    $destStep = 'response';

    $destLogAttempt([
        'type' => 'success',
        'code' => 'OK',
        'http_status' => 200,
        'destinations_count' => count($destinations),
    ]);

    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'route_name' => $routeName,
        'boarding_order' => $boardingOrder,
        'progress_order' => $progressOrder,
        'current_stop_order' => $busOrder,
        'destinations' => $destinations
    ]);

    } catch (\Throwable $e) {
        $destErrorDetail['exception'] = get_class($e) . ': ' . $e->getMessage();
        if ($e instanceof \PDOException) {
            $destErrorDetail['sql'] = ['state' => $e->getCode(), 'message' => $e->getMessage()];
        }
        $destSendFailure('SERVER_ERROR', 'We could not load destinations right now. Please try again.', 500);
    }
}

function handleResolveStop()
{
    $lat = passenger_input_value('lat');
    $lng = passenger_input_value('lng');

    if ($lat === null || $lng === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Latitude and longitude are required.'
        ]);
        return;
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    $conn = Database::tryGet();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
        return;
    }

    $stmt = $conn->query("
        SELECT
            fp.id,
            fp.destination,
            rs.latitude,
            rs.longitude
        FROM fare_pricing fp
        JOIN route_stops rs
            ON (rs.stop_name = fp.destination OR rs.sequence_order = fp.display_order)
        WHERE rs.latitude IS NOT NULL AND rs.longitude IS NOT NULL
    ");

    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nearest = null;
    $nearestDistance = null;

    foreach ($stops as $stop) {
        $stopLat = (float) $stop['latitude'];
        $stopLng = (float) $stop['longitude'];

        $distance = haversine($lat, $lng, $stopLat, $stopLng);

        if ($nearest === null || $distance < $nearestDistance) {
            $nearest = $stop;
            $nearestDistance = $distance;
        }
    }

    if ($nearest && $nearestDistance <= 2.0) {
        echo json_encode([
            'success' => true,
            'stop' => [
                'id' => (int) $nearest['id'],
                'destination' => $nearest['destination'],
                'distance_m' => round($nearestDistance * 1000)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No nearby stop found. Please select a destination or move closer to a stop.'
        ]);
    }
}

function handleCalculateFare()
{
    $input = $GLOBALS['passengerInput'];
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);
    }

    if (!is_array($input)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        return;
    }

    $boardingId = $input['boarding_id'] ?? null;
    $destinationId = $input['destination_id'] ?? null;

    if (!$boardingId && !empty($input['from'])) {
        $boardingId = resolveStopNameToId($input['from']);
    }
    if (!$destinationId && !empty($input['to'])) {
        $destinationId = resolveStopNameToId($input['to']);
    }

    if (!$boardingId || !$destinationId) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid stop ID or name.'
        ]);
        return;
    }

    $boardingId = (int) $boardingId;
    $destinationId = (int) $destinationId;

    $conn = Database::tryGet();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
        return;
    }

    $discountType = 'regular';
    $stmt = $conn->prepare("SELECT discount_type FROM passengers WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['passenger_id']]);
    $discount = $stmt->fetchColumn();
    if (!empty($discount)) {
        $discountType = strtolower(trim((string) $discount));
    }

    $fareType = $discountType;

    $stmt = $conn->prepare("
        SELECT
            id,
            destination,
            display_order,
            regular_fare,
            student_fare,
            special_fare
        FROM fare_pricing
        WHERE id IN (?, ?)
    ");
    $stmt->execute([$boardingId, $destinationId]);
    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stops)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid stop ID.'
        ]);
        return;
    }

    $boardingStop = null;
    $destinationStop = null;

    foreach ($stops as $stop) {
        if ((int) $stop['id'] === $boardingId) {
            $boardingStop = $stop;
        }
        if ((int) $stop['id'] === $destinationId) {
            $destinationStop = $stop;
        }
    }

    if ($boardingId === $destinationId) {
        $destinationStop = $boardingStop;
    }

    if (!$boardingStop || !$destinationStop) {
        echo json_encode([
            'success' => false,
            'message' => 'Stop not found.'
        ]);
        return;
    }

    if ((int) $boardingId === (int) $destinationId) {
        echo json_encode([
            'success' => true,
            'fare' => 15.00,
            'base_fare' => 15.00,
            'boarding_stop' => $boardingStop['destination'],
            'destination_stop' => $destinationStop['destination'],
            'boarding_display_order' => (int) $boardingStop['display_order'],
            'passenger_type' => $fareType,
            'discount_type' => $discountType
        ]);
        return;
    }

    $fareColumn = match (strtolower($fareType)) {
        'student', 'senior', 'senior citizen', 'pwd' => 'student_fare',
        'teacher', 'nurse', 'special' => 'special_fare',
        default => 'regular_fare'
    };

    $fare = FareCalculator::segmentFare($boardingStop, $destinationStop, $fareColumn);
    $baseFare = FareCalculator::segmentFare($boardingStop, $destinationStop, 'regular_fare');

    echo json_encode([
        'success' => true,
        'fare' => $fare,
        'base_fare' => $baseFare,
        'boarding_stop' => $boardingStop['destination'],
        'destination_stop' => $destinationStop['destination'],
        'boarding_display_order' => (int) $boardingStop['display_order'],
        'passenger_type' => $fareType,
        'discount_type' => $discountType
    ]);
}

function resolveStopNameToId($name)
{
    $conn = Database::tryGet();
    if (!$conn) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM fare_pricing WHERE LOWER(destination) = LOWER(?) LIMIT 1");
    $stmt->execute([trim((string) $name)]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function haversine($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
