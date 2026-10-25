<?php

header('Content-Type: application/json');

// =========================
// REQUIRE
// =========================
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/fare-engine/utils/calculateFare.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/fare-engine/utils/matchRouteStop.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/ContinuationJourneyService.php';
require_once __DIR__ . '/TripSchemaHelper.php';
require_once __DIR__ . '/../../services/Companion/CompanionSchema.php';
require_once __DIR__ . '/../../services/Companion/CompanionService.php';
require_once __DIR__ . '/../../services/Companion/RegularPartyService.php';

use App\Core\Database;

// =========================
// DIAGNOSTICS
// =========================
// Per-step tracing for the complete scan flow. The debug section in failure
// responses is exposed only when PASSENGER_QR_SCAN_DEBUG is enabled; the
// structured log line is always written so failures remain reviewable.
$scanDebug = passenger_qr_scan_debug_enabled();
$scanStep = 'input';
$scanContext = [
    'passenger_id' => isset($_SESSION['passenger_id']) ? (int) $_SESSION['passenger_id'] : null,
    'bus_no' => null,
    'trip_no' => null,
    'origin' => (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
];
$scanErrorDetail = [];

$scanLogAttempt = function (array $entry) use (&$scanStep, &$scanContext) {
    passenger_qr_scan_log(array_merge([
        'route' => '/passenger/scan/store',
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'step' => $scanStep,
        'request' => $scanContext,
    ], $entry));
};

$scanSendFailure = function (string $code, string $message, int $status = 400, array $extra = []) use (&$scanStep, &$scanContext, &$scanErrorDetail, $scanDebug, $scanLogAttempt) {
    $body = array_merge([
        'success' => false,
        'code' => $code,
        'step' => $scanStep,
        'message' => $message,
    ], $extra);

    if ($scanDebug) {
        $debug = [
            'step' => $scanStep,
            'http_status' => $status,
            'request' => $scanContext,
        ];
        if (!empty($scanErrorDetail['exception'])) {
            $debug['exception'] = $scanErrorDetail['exception'];
        }
        if (!empty($scanErrorDetail['sql'])) {
            $debug['sql'] = $scanErrorDetail['sql'];
        }
        $body['debug'] = $debug;
    }

    $scanLogAttempt(array_merge([
        'type' => 'failure',
        'code' => $code,
        'http_status' => $status,
        'message' => $message,
    ], $scanErrorDetail));

    http_response_code($status);
    echo json_encode($body);
    exit;
};

$scanSendSuccess = function (array $body) use ($scanLogAttempt) {
    $scanLogAttempt([
        'type' => 'success',
        'code' => 'OK',
        'http_status' => 200,
        'trip_id' => $body['trip_id'] ?? null,
        'trip_type' => $body['type'] ?? null,
    ]);

    echo json_encode($body);
    exit;
};

try {

// =========================
// INPUT
// =========================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {

    $scanSendFailure('INVALID_JSON', 'Invalid request.', 400);

}

// =========================
// LOGIN CHECK
// =========================
if (!isset($_SESSION['passenger_id'])) {
    $scanSendFailure('AUTH_REQUIRED', 'Not logged in', 401);
}

$passenger_id = $_SESSION['passenger_id'];
$scanContext['passenger_id'] = (int) $passenger_id;

$scanStep = 'rate_limit';

if (!passenger_rate_limit('boarding_scan', (string) $passenger_id . '|' . passenger_client_ip(), 60, 300, 0)) {
    $scanSendFailure('RATE_LIMITED', 'Too many scan attempts. Please wait a moment and try again.', 429);
}

// =========================
// INPUT VALUES
// =========================
$scanStep = 'input';
$bus_no = $data['bus_no'] ?? null;
$trip_no = $data['trip_no'] ?? null;
$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;
$accuracy = $data['accuracy'] ?? null;

$scanContext['bus_no'] = $bus_no !== null ? (string) $bus_no : null;
$scanContext['trip_no'] = $trip_no !== null ? (string) $trip_no : null;

// Second-scan input: the active passenger trip id (optional — derived when absent)
$requestedTripId = $data['passenger_trip_id'] ?? null;

if (!$bus_no) {
    $scanSendFailure('MISSING_BUS_NO', 'Missing bus number', 400);
}

// =========================
// DB
// =========================
$conn = Database::tryGet();

if (!$conn) {
    $scanSendFailure('DB_UNAVAILABLE', 'We could not reach the service right now. Please try again in a moment.', 503);
}

// Serialize concurrent scans for this passenger so a double-tap cannot
// create two active trips.
$scanLockName = 'passenger_scan_' . (int) $passenger_id;
try {
    $conn->prepare("SELECT GET_LOCK(?, 5)")->execute([$scanLockName]);
} catch (\Throwable $e) {
    $scanLockName = '';
}
if ($scanLockName !== '') {
    register_shutdown_function(function () use ($conn, $scanLockName) {
        try {
            $conn->prepare("SELECT RELEASE_LOCK(?)")->execute([$scanLockName]);
        } catch (\Throwable $e) {
        }
    });
}

// =========================
// SCHEMA MIGRATION (additive, idempotent)
// =========================
$scanStep = 'schema';
try {
    ensureTripFlowSchema($conn);
    ensureLocationHistorySchema($conn);
} catch (\Throwable $e) {
    // These helpers only add columns; a locked table or missing privilege
    // must not fail a scan. Record it so the cause stays visible.
    passenger_qr_scan_log([
        'route' => '/passenger/scan/store',
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'type' => 'warning',
        'step' => 'schema',
        'code' => 'SCHEMA_SKIPPED',
        'http_status' => 200,
        'message' => $e->getMessage(),
        'request' => $scanContext,
    ]);
}

// =========================
// BUS VALIDATION
// =========================
// Catch a genuinely invalid or unavailable bus here so the passenger gets a
// precise message instead of the generic "no active trip" error.
$scanStep = 'bus_lookup';

$hasOperationalStatus = false;
try {
    $conn->query('SELECT operational_status FROM buses LIMIT 1');
    $hasOperationalStatus = true;
} catch (\Throwable $e) {
    $hasOperationalStatus = false;
}

try {
    $busStmt = $conn->prepare(
        'SELECT status' . ($hasOperationalStatus ? ', operational_status' : '')
        . ' FROM buses WHERE LOWER(TRIM(bus_id)) = LOWER(TRIM(?)) LIMIT 1'
    );
    $busStmt->execute([$bus_no]);
    $busRow = $busStmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $busRow = null;
    $scanErrorDetail['sql'] = ['state' => (string) $e->getCode(), 'message' => $e->getMessage()];
}

if (!$busRow) {
    $scanSendFailure(
        'BUS_NOT_FOUND',
        'This bus QR is not recognized. Please scan the QR posted on the bus again.',
        404
    );
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/BusAvailability.php';
$busStatus = (string)($busRow['status'] ?? '');
$busOperational = (string)($busRow['operational_status'] ?? '');
if (!BusAvailability::boardingAllowed($busStatus, $busOperational)) {
    $scanSendFailure(
        'BUS_UNAVAILABLE',
        BusAvailability::reason($busStatus, $busOperational),
        409
    );
}

// =========================
// PASSENGER
// =========================
$scanStep = 'passenger_lookup';

$discount = 'regular';
$passengerTypeLabel = 'Regular';
try {
    $stmt = $conn->prepare("SELECT discount_type FROM passengers WHERE id = ?");
    $stmt->execute([$passenger_id]);
    $passenger = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($passenger) {
        $passengerTypeLabel = trim((string)($passenger['discount_type'] ?? ''));
        if ($passengerTypeLabel === '') {
            $passengerTypeLabel = 'Regular';
        }
        $discount = strtolower($passengerTypeLabel);
    }
} catch (\PDOException $e) {
    error_log('Passenger discount lookup failed: ' . $e->getMessage());
}

// =========================
// DRIVER RESOLUTION
// =========================
$scanStep = 'driver_lookup';

$driverId = null;
$driverName = null;
try {
    $stmt = $conn->prepare("
        SELECT dba.driver_id, d.full_name AS driver_name
        FROM driver_bus_assignment dba
        LEFT JOIN drivers d ON dba.driver_id = d.driver_id
        WHERE LOWER(TRIM(dba.bus_id)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmt->execute([$bus_no]);
    $driverRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($driverRow) {
        $driverId = $driverRow['driver_id'];
        $driverName = $driverRow['driver_name'];
    }
} catch (\PDOException $e) {
    error_log('Passenger scan driver lookup failed: ' . $e->getMessage());
}

// =========================
// BUS TRIP / ROUTE RESOLUTION
// =========================
$scanStep = 'trip_resolution';

// Active statuses accepted for a scanned/entered trip. A QR may embed a trip
// id that is stale, belongs to another bus, or was already completed; such a
// trip is discarded so the bus's current active trip can take over instead of
// failing the scan.
$activeTripStatuses = ['Scheduled', 'Departed', 'On Route', 'Started', 'Active', 'Ongoing'];

$routeName = null;
if ($trip_no) {
    try {
        $stmt = $conn->prepare("SELECT id, bus_id, route_name, status, trip_date FROM bus_trips WHERE id = ? LIMIT 1");
        $stmt->execute([$trip_no]);
        $busTrip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $busTrip = null;
    }

    $tripMatchesBus = $busTrip
        && strcasecmp(trim((string)($busTrip['bus_id'] ?? '')), trim((string)$bus_no)) === 0;
    $tripIsActive = $busTrip
        && in_array((string)($busTrip['status'] ?? ''), $activeTripStatuses, true);
    $tripIsRecent = $busTrip
        && !empty($busTrip['trip_date'])
        && abs(strtotime((string)$busTrip['trip_date']) - strtotime(date('Y-m-d'))) <= 86400;

    if ($tripMatchesBus && $tripIsActive && $tripIsRecent) {
        $trip_no = (string)$busTrip['id'];
        $routeName = $busTrip['route_name'];
    } else {
        $trip_no = null;
    }
}

// Manual-entry and stale-QR fallback: resolve the active trip running on this
// bus, so a passenger can board by entering just the bus number. Uses the same
// status set and +/-1 day window as the one-time flow's resolver.
if (!$routeName) {
    $statusPlaceholders = implode(', ', array_fill(0, count($activeTripStatuses), '?'));
    $stmt = $conn->prepare("
        SELECT id, route_name FROM bus_trips
        WHERE LOWER(TRIM(bus_id)) = LOWER(TRIM(?))
        AND status IN ($statusPlaceholders)
        AND trip_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ORDER BY CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END ASC, id DESC
        LIMIT 1
    ");
    $stmt->execute(array_merge([$bus_no], $activeTripStatuses));
    $activeBusTrip = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($activeBusTrip) {
        $trip_no = (string)$activeBusTrip['id'];
        $routeName = $activeBusTrip['route_name'];
    }
}

$scanContext['trip_no'] = $trip_no !== null ? (string) $trip_no : null;

if (!$routeName) {
    $scanSendFailure(
        'NO_ACTIVE_TRIP',
        'This bus has no active trip right now. Please wait for the dispatcher to dispatch it, then scan again.',
        409
    );
}

// =========================
// ACTIVE TRIP
// =========================
$scanStep = 'active_trip_lookup';

if ($requestedTripId) {
    $stmt = $conn->prepare("
        SELECT * FROM passenger_trips
        WHERE id = ? AND passenger_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$requestedTripId, $passenger_id]);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM passenger_trips
        WHERE passenger_id = ?
        AND dropoff_time IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$passenger_id]);
}
$activeTrip = $stmt->fetch(PDO::FETCH_ASSOC);

// =========================
// ROUTE FOR MATCHING: for dropoff, scope GPS matching to the active trip's
// route (the bus scanned at dropoff may differ from the boarding bus).
// =========================
$scanStep = 'trip_resolution';

if ($activeTrip) {
    $stmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$activeTrip['trip_no']]);
    $activeTripRoute = trim((string)$stmt->fetchColumn());
    if ($activeTripRoute !== '') {
        $routeName = $activeTripRoute;
    }
}

// =========================
// STALE TRIP GUARD
// =========================
$scanStep = 'stale_trip_guard';
// A completed/cancelled trip is no longer active. If the client still
// references one (for example a stale arrival scan), do not reject the scan —
// fall through to the boarding branch so a new trip starts immediately.
// The bus trip ending (driver-side trip completion) also ends the passenger's
// ability to be "on board" it, even when the driver flow never set a dropoff
// time on the passenger row.
if ($activeTrip) {
    $staleStatus = strtolower((string)($activeTrip['trip_status'] ?? ''));
    $isStaleTrip = (
        $staleStatus === 'completed' ||
        $staleStatus === 'cancelled' ||
        strtolower((string)($activeTrip['payment_status'] ?? '')) === 'paid' ||
        $activeTrip['dropoff_time'] !== null
    );

    if (!$isStaleTrip && !empty($activeTrip['trip_no'])) {
        try {
            $stmt = $conn->prepare("SELECT status FROM bus_trips WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$activeTrip['trip_no']]);
            $busTripStatus = strtolower(trim((string)$stmt->fetchColumn()));
            if (in_array($busTripStatus, ['completed', 'cancelled'], true)) {
                $isStaleTrip = true;
            }
        } catch (\PDOException $e) {
            // bus_trips unavailable — keep the passenger trip active
        }
    }

    if ($isStaleTrip) {
        $activeTrip = null;
        $requestedTripId = null;
    }
}

// =========================
// ALREADY ON BOARD (no arrival intent)
// =========================
// Drop-off is driver-driven now: a scan without an explicit arrival signal
// must never complete an in-progress trip. Report the active trip back so the
// passenger can continue with destination selection.
if ($activeTrip && !$requestedTripId) {
    $scanSendSuccess([
        'success' => true,
        'hasActiveTrip' => true,
        'type' => 'active',
        'message' => 'You are already on board. Please select your destination to continue.',
        'trip_id' => (int)$activeTrip['id'],
        'bus_no' => $activeTrip['bus_no'] ?? null,
        'trip_no' => $activeTrip['trip_no'] ?? null,
        'driver_id' => $driverId,
        'driver_name' => $driverName,
        'boarding_point' => $activeTrip['boarding_point'] ?? null,
        'destination' => $activeTrip['destination'] ?? null,
        'selected_destination' => $activeTrip['selected_destination'] ?? null,
        'selected_destination_id' => $activeTrip['selected_destination_id'] ?? null,
        'selected_km_marker' => $activeTrip['selected_km_marker'] ?? null,
        'estimated_fare' => isset($activeTrip['estimated_fare']) ? (float)$activeTrip['estimated_fare'] : null,
        'payment_method' => $activeTrip['payment_method'] ?? null,
        'discount_type' => $discount,
        'trip_status' => $activeTrip['trip_status'] ?? 'In Progress'
    ]);
}

// =========================
// 🟢 BOARDING (FIRST QR SCAN)
// =========================
if (!$activeTrip) {

    $scanStep = 'unpaid_check';

    // Boarding stays available while the unpaid balance is below the trip
    // limit; once the limit is reached the passenger must settle first. The
    // shared helper counts a continuation chain once (its carried balance is
    // folded into the continuation row), so a missed destination cannot
    // consume two slots or double the owed amount.
    $unpaidTripCount = ContinuationJourneyService::outstandingCount($conn, $passenger_id);

    if ($unpaidTripCount >= 2) {
        $scanSendFailure(
            'PAYMENT_REQUIRED',
            'You have ' . $unpaidTripCount . ' unpaid trips. Please settle your balance before boarding again.',
            402,
            ['unpaid_count' => $unpaidTripCount]
        );
    }

    $paymentMethod =
        strtolower(trim((string) ($data['payment_method'] ?? 'cash')));
    if (!in_array($paymentMethod, ['cash', 'gcash', 'hybrid'], true)) {
        $paymentMethod = 'cash';
    }

    $cashAmount =
        max(0.0, (float)(
            $data['cash_amount']
            ?? 0
        ));

    $gcashAmount =
        max(0.0, (float)(
            $data['gcash_amount']
            ?? 0
        ));

    // =========================
    // BOARDING POINT → BUS CURRENT POSITION
    // =========================
    // Like the One-Time flow, the recorded boarding point is where the bus
    // actually is (simulated or live GPS) when the passenger enters, not where
    // the passenger's phone happens to be. The passenger GPS match below stays
    // as the fallback for trips with no usable bus position.
    $scanStep = 'bus_boarding';
    $boardingFromBus = null;

    try {
        require_once __DIR__ . '/../../services/OneTime/OneTimeService.php';
        require_once __DIR__ . '/../../services/BusBoardingService.php';

        $tripFareStops = \OneTimeService::resolveRouteStops($conn, (string) $routeName);

        if ($tripFareStops) {
            $busTripRow = [
                'id' => (int) $trip_no,
                'route_name' => (string) $routeName,
                'bus_id' => (string) $bus_no,
                'driver_id' => (string) ($driverId ?? ''),
            ];
            $busBoarding = passengerResolveBusBoarding($conn, $busTripRow, $tripFareStops);

            if ($busBoarding['error'] === null && !empty($busBoarding['boarding'])) {
                $boardingFromBus = $busBoarding['boarding'];
            }
        }
    } catch (\Throwable $e) {
        $boardingFromBus = null;
    }

    if ($boardingFromBus !== null) {
        $boardingPoint = trim((string) ($boardingFromBus['destination'] ?? ''));
        $boardingStopId = (int) ($boardingFromBus['stop_id'] ?? 0);
        $boardingKm = $boardingFromBus['km_marker'] !== null ? (int) $boardingFromBus['km_marker'] : null;

        if ($boardingPoint === '') {
            $boardingFromBus = null;
        }
    }

    // =========================
    // GPS → ROUTE STOP MATCHING (fallback)
    // =========================
    if ($boardingFromBus === null) {
        $scanStep = 'gps_match';

        $boardingStop = matchRouteStop($lat, $lng, $routeName, $conn);

        if (
            !$boardingStop ||
            !isRouteStopMatchAcceptable($boardingStop['distance_km'], $accuracy)
        ) {

            $scanSendFailure(
                'GPS_NO_MATCH',
                'Could not determine a reliable boarding stop from your location. Please move closer to a stop and try again.',
                422,
                [
                    'boardingStop' => $boardingStop ? [
                        'stop_name' => $boardingStop['stop_name'],
                        'distance_m' => $boardingStop['distance_m']
                    ] : null
                ]
            );

        }

        $boardingPoint = trim($boardingStop['stop_name'] ?? '');
        $boardingStopId = (int)$boardingStop['id'];
        $boardingKm = $boardingStop['km_marker'] !== null ? (int)$boardingStop['km_marker'] : null;
    }

    $scanStep = 'boarding_insert';

    try {

        ensureCompanionSchema($conn);
        RegularPartyService::ensureState($conn);
        $conn->beginTransaction();
        $existingBoardingId = RegularPartyService::lockBoardingOwner($conn, $passenger_id);
        if ($existingBoardingId) {
            $existingBoarding = RegularPartyService::ownedTrip($conn, $passenger_id, $existingBoardingId);
            $existingParty = RegularPartyService::read($conn, $passenger_id, $existingBoardingId);
            $conn->commit();
            $scanSendSuccess([
                'success' => true, 'type' => 'boarding', 'already_boarded' => true,
                'trip_id' => $existingBoardingId, 'party' => $existingParty,
                'bus_no' => $existingBoarding['bus_no'], 'trip_no' => $existingBoarding['trip_no'],
                'boarding_point' => $existingBoarding['boarding_point'],
                'boarding_stop_id' => $existingBoarding['boarding_stop_id'] ?? null,
                'destination' => $existingBoarding['destination'] ?? null,
                'selected_destination_id' => $existingBoarding['selected_destination_id'] ?? null,
                'estimated_fare' => $existingBoarding['estimated_fare'] ?? null,
                'payment_method' => $existingBoarding['payment_method'],
                'discount_type' => $existingBoarding['discount_type'], 'trip_status' => $existingBoarding['trip_status'],
            ]);
        }

        // Record the boarding passenger type from the account, so fare
        // analytics do not read every live boarding as Regular. The column is
        // optional in older deployments.
        $hasPassengerTypeColumn = false;
        try {
            $conn->query('SELECT passenger_type FROM passenger_trips LIMIT 1');
            $hasPassengerTypeColumn = true;
        } catch (\Throwable $e) {
            $hasPassengerTypeColumn = false;
        }

        $insertColumns = [
            'passenger_id',
            'bus_no',
            'trip_no',
            'driver_id',
            'boarding_point',
            'boarding_stop_id',
            'boarding_km_marker',
            'latitude',
            'longitude',
            'payment_method',
            'cash_amount',
            'gcash_amount',
            'trip_status',
        ];
        $insertValues = [
            $passenger_id,
            $bus_no,
            $trip_no,
            $driverId,
            $boardingPoint,
            $boardingStopId,
            $boardingKm,
            $lat,
            $lng,
            $paymentMethod,
            $cashAmount,
            $gcashAmount,
            'In Progress',
        ];

        if ($hasPassengerTypeColumn) {
            $insertColumns[] = 'passenger_type';
            $insertValues[] = $passengerTypeLabel;
        }

        $stmt = $conn->prepare(
            'INSERT INTO passenger_trips (' . implode(', ', $insertColumns) . ') VALUES ('
            . implode(', ', array_fill(0, count($insertColumns), '?')) . ')'
        );

        $stmt->execute($insertValues);

        $tripId = (int)$conn->lastInsertId();
        $boardingParty = RegularPartyService::boardDraft($conn, $passenger_id, $tripId);

        // Fresh boarding session: wipe any draft UI state left over from a
        // previous trip so it can never be restored into this one.
        try {
            $cleanup = $conn->prepare("DELETE FROM passenger_trip_state WHERE passenger_id = ? AND state_key <> ?");
            $cleanup->execute([$passenger_id, 'regular_party_' . $tripId]);
        } catch (\PDOException $e) {
            // Non-critical cleanup failure
        }

        // Null out every trip-scoped field on the new row (destination, fare,
        // actuals). New rows are already NULL by default — this is a
        // guarantee against column defaults or reused ids.
        try {
            $clearFlow = $conn->prepare("
                UPDATE passenger_trips
                SET destination = NULL,
                    destination_id = NULL,
                    selected_destination = NULL,
                    selected_destination_id = NULL,
                    selected_km_marker = NULL,
                    estimated_fare = NULL,
                    actual_stop_id = NULL,
                    actual_km_marker = NULL,
                    base_fare = NULL,
                    discount_amount = NULL
                WHERE id = ?
            ");
            $clearFlow->execute([$tripId]);
        } catch (\PDOException $e) {
            // Additive schema — ignore environments missing one of the columns
        }

        // Record the initial location (first = last) for the trip
        $stmt = $conn->prepare("
            INSERT INTO passenger_location_history
            (
                passenger_trip_id,
                first_latitude,
                first_longitude,
                first_recorded_at,
                latitude,
                longitude,
                accuracy,
                recorded_at
            )
            VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW())
        ");
        $stmt->execute([$tripId, $lat, $lng, $lat, $lng, $accuracy]);
        $conn->commit();

    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }

        error_log('Passenger boarding insert failed: ' . $e->getMessage());

        $scanErrorDetail['sql'] = ['state' => (string) $e->getCode(), 'message' => $e->getMessage()];

        $scanSendFailure(
            'BOARDING_FAILED',
            'We could not start your trip right now. Please try again.',
            500
        );

    }

    // The scanned QR has been consumed — clear it so the homepage does not
    // show the Trip Detected card again on a later direct visit.
    unset($_SESSION['qr_bus'], $_SESSION['qr_trip']);

    $scanSendSuccess([

        'success' => true,

        'type' => 'boarding',
        'party' => $boardingParty,

        'trip_id' =>
            $tripId,

        'bus_no' =>
            $bus_no,

        'trip_no' =>
            $trip_no,

        'route_name' =>
            $routeName,

        'driver_id' =>
            $driverId,

        'driver_name' =>
            $driverName,

        'boarding_point' =>
            $boardingPoint,

        'boarding_stop_id' =>
            $boardingStopId,

        'boarding_km_marker' =>
            $boardingKm,

        'discount_type' =>
            $discount,

        'payment_method' =>
            $paymentMethod,

        'cash_amount' =>
            $cashAmount,

        'gcash_amount' =>
            $gcashAmount,

        'trip_status' =>
            'In Progress'

    ]);

}

// =========================
// 🔴 DROPOFF / SECOND QR SCAN
// =========================

// =========================
// 🛑 STATE VALIDATION
// =========================
$scanStep = 'stale_trip_guard';

$tripStatus = strtolower($activeTrip['trip_status'] ?? '');
if ($tripStatus === 'completed' || $tripStatus === 'cancelled' || $activeTrip['dropoff_time'] !== null) {
    $scanSendFailure('TRIP_ALREADY_COMPLETED', 'This trip has already been completed.', 409);
}

$boarding = $activeTrip['boarding_point'];
$boardingStopId = (int)($activeTrip['boarding_stop_id'] ?? 0);

// =========================
// DESTINATION MATCHING
// Prefer the destination the passenger already selected for this trip; only
// fall back to GPS matching when no destination was chosen.
// =========================
$scanStep = 'dropoff_match';

$plannedStop = null;
$plannedName = trim((string) ($activeTrip['selected_destination'] ?? ($activeTrip['destination'] ?? '')));
$plannedId = (int) ($activeTrip['selected_destination_id'] ?? ($activeTrip['destination_id'] ?? 0));

if ($plannedName !== '' || $plannedId > 0) {
    try {
        if ($plannedId > 0) {
            $stmt = $conn->prepare("
                SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
                FROM route_stops
                WHERE fare_pricing_id = ?
                LIMIT 1
            ");
            $stmt->execute([$plannedId]);
            $plannedStop = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$plannedStop && $plannedName !== '') {
            $stmt = $conn->prepare("
                SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
                FROM route_stops
                WHERE LOWER(TRIM(stop_name)) = LOWER(TRIM(?))
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$plannedName]);
            $plannedStop = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (\PDOException $e) {
        $plannedStop = null;
    }

    if ($plannedStop) {
        $plannedStop['distance_km'] = 0.0;
        $plannedStop['distance_m'] = 0;
    }
}

$actualStop = $plannedStop;

if ($actualStop === null) {
    $actualStop = matchRouteStop($lat, $lng, $routeName, $conn);
}

if (
    !$actualStop ||
    !isRouteStopMatchAcceptable($actualStop['distance_km'], $accuracy)
) {

    // Fallback: most recent valid GPS record from the active trip's history
    $fallbackMatch = null;

    try {
        $stmt = $conn->prepare("
            SELECT latitude, longitude, accuracy, recorded_at
            FROM passenger_location_history
            WHERE passenger_trip_id = ?
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL
            ORDER BY recorded_at DESC, location_id DESC
            LIMIT 1
        ");
        $stmt->execute([(int)$activeTrip['id']]);
        $lastGps = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $lastGps = null;
    }

    if ($lastGps) {
        $fallbackStop = matchRouteStop($lastGps['latitude'], $lastGps['longitude'], $routeName, $conn);
        if (
            $fallbackStop &&
            isRouteStopMatchAcceptable($fallbackStop['distance_km'], $lastGps['accuracy'])
        ) {
            $fallbackMatch = $fallbackStop;
        }
    }

    if ($fallbackMatch) {
        $actualStop = $fallbackMatch;
    } else {

        $scanSendFailure(
            'DROPOFF_NO_MATCH',
            'Could not determine a reliable drop-off location from your GPS. Please move closer to a stop and scan again.',
            422,
            [
                'actualStop' => $actualStop ? [
                    'stop_name' => $actualStop['stop_name'],
                    'distance_m' => $actualStop['distance_m']
                ] : null
            ]
        );

    }

}

$actualDestination = trim($actualStop['stop_name'] ?? '');
$actualStopId = (int)$actualStop['id'];
$actualKm = $actualStop['km_marker'] !== null ? (int)$actualStop['km_marker'] : null;

// =========================
// 💰 FINAL FARE CALCULATION (server-side, authoritative)
// =========================
$scanStep = 'fare_calc';

$baseFareResult =
    calculateFare(
        $activeTrip['trip_no'],
        $boarding,
        $actualDestination,
        "regular",
        null,
        null,
        $boardingStopId > 0 ? $boardingStopId : null,
        $actualStopId
    );

$totalFareResult =
    calculateFare(
        $activeTrip['trip_no'],
        $boarding,
        $actualDestination,
        $discount,
        null,
        null,
        $boardingStopId > 0 ? $boardingStopId : null,
        $actualStopId
    );

$baseFare =
    $baseFareResult['fare'] ?? 0;

$totalFare =
    $totalFareResult['fare'] ?? 0;

// Safety net: if both fares are 0 but the stop names likely match, charge minimum
if ($baseFare == 0 && $totalFare == 0) {
    $normBoarding = strtolower(preg_replace('/[^a-z0-9 ]/', ' ', $boarding));
    $normDest = strtolower(preg_replace('/[^a-z0-9 ]/', ' ', $actualDestination));
    $normBoarding = trim(preg_replace('/\s+/', ' ', $normBoarding));
    $normDest = trim(preg_replace('/\s+/', ' ', $normDest));
    if (
        $normBoarding === $normDest ||
        strpos($normBoarding, $normDest) !== false ||
        strpos($normDest, $normBoarding) !== false
    ) {
        $baseFare = 15.00;
        $totalFare = 15.00;
    }
}

$discountAmount =
    $baseFare -
    $totalFare;

$paymentMethod =
    strtolower(trim((string) (
        $data['payment_method']
        ?? $activeTrip['payment_method']
        ?? 'cash'
    )));
if (!in_array($paymentMethod, ['cash', 'gcash', 'hybrid'], true)) {
    $paymentMethod = 'cash';
}

$cashAmount =
    (float)(
        $data['cash_amount']
        ?? $activeTrip['cash_amount']
        ?? 0
    );
if ($cashAmount < 0) {
    $cashAmount = 0.0;
}
if ($totalFare > 0 && $cashAmount > $totalFare) {
    $cashAmount = $totalFare;
}

$gcashAmount =
    (float)(
        $data['gcash_amount']
        ?? $activeTrip['gcash_amount']
        ?? 0
    );
if ($gcashAmount < 0) {
    $gcashAmount = 0.0;
}
if ($totalFare > 0 && $gcashAmount > $totalFare) {
    $gcashAmount = $totalFare;
}

// =========================
// 💾 UPDATE TRIP (COMPLETE)
// =========================
$scanStep = 'trip_complete';

$conn->beginTransaction();

$companionFinalCount = 0;

try {

    $stmt =
        $conn->prepare("

            UPDATE passenger_trips
            SET
                destination = ?,
                actual_stop_id = ?,
                actual_km_marker = ?,
                fare = ?,
                base_fare = ?,
                discount_amount = ?,
                payment_method = ?,
                cash_amount = ?,
                gcash_amount = ?,
                latitude = ?,
                longitude = ?,
                dropoff_time = NOW(),
                payment_status = 'pending',
                trip_status = 'Completed'
            WHERE id = ?

        ");

    $stmt->execute([

        $actualDestination,
        $actualStopId,
        $actualKm,
        $totalFare,
        $baseFare,
        $discountAmount,

        $paymentMethod,
        $cashAmount,
        $gcashAmount,

        $lat,
        $lng,

        $activeTrip['id']

    ]);

    // Update the trip's last known location to the dropoff GPS
    try {
        $stmt = $conn->prepare("
            UPDATE passenger_location_history
            SET latitude = ?, longitude = ?, accuracy = ?, recorded_at = NOW(), route_point_id = ?, route_km = ?
            WHERE passenger_trip_id = ?
        ");
        $stmt->execute([$lat, $lng, $accuracy, $actualStopId, $actualKm, $activeTrip['id']]);
    } catch (\PDOException $e) {
        // Best-effort: location history update failure should not fail dropoff
    }

    // =========================
    // 👥 COMPANIONS SHARE THE DROPOFF + FARE
    // =========================
    // Companions that boarded with this passenger are dropped off at the same
    // stop with their own fare (regular/senior). Each companion row is
    // completed and archived exactly like the primary.
    $companionMirror = CompanionService::mirrorDropoff(
        $conn,
        (int) $activeTrip['id'],
        (int) $activeTrip['trip_no'],
        $boarding,
        $actualDestination,
        $boardingStopId,
        $actualStopId,
        $paymentMethod,
        $cashAmount,
        $gcashAmount
    );
    $companionFinalCount = count($companionMirror['rows']);

    foreach ($companionMirror['rows'] as $companionRow) {
        archiveCompletedTrip($conn, $companionRow, [
            'actualDestination' => $actualDestination,
            'actualStopId' => $actualStopId,
            'actualKm' => $actualKm,
            'totalFare' => (float) $companionRow['fare'],
            'baseFare' => (float) $companionRow['base_fare'],
            'discountAmount' => (float) $companionRow['discount_amount'],
            'paymentMethod' => $paymentMethod,
            'cashAmount' => $cashAmount,
            'gcashAmount' => $gcashAmount,
        ]);
    }

    // =========================
    // 📚 ARCHIVE INTO passenger_trip_history
    // =========================
    archiveCompletedTrip($conn, $activeTrip, [
        'actualDestination' => $actualDestination,
        'actualStopId' => $actualStopId,
        'actualKm' => $actualKm,
        'totalFare' => $totalFare,
        'baseFare' => $baseFare,
        'discountAmount' => $discountAmount,
        'paymentMethod' => $paymentMethod,
        'cashAmount' => $cashAmount,
        'gcashAmount' => $gcashAmount
    ]);

    $conn->commit();

} catch (\Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Passenger trip completion failed: ' . $e->getMessage());

    $scanErrorDetail['exception'] = [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    $scanSendFailure(
        'TRIP_COMPLETE_FAILED',
        'We could not complete your trip right now. Please try again.',
        500
    );

}

// =========================
// CLEANUP: Remove persisted UI state on trip completion
// =========================
try {
    $cleanupStmt = $conn->prepare("DELETE FROM passenger_trip_state WHERE passenger_id = ?");
    $cleanupStmt->execute([$passenger_id]);
} catch (\PDOException $e) {
    // Non-critical cleanup failure
}

// =========================
// RESPONSE
// =========================

// The trip is finished — drop the scanned QR so a later direct visit cannot
// rebuild the Trip Detected card.
unset($_SESSION['qr_bus'], $_SESSION['qr_trip']);

$scanSendSuccess([

    'success' => true,

    'type' => 'dropoff',

    'bus_no' =>
        $activeTrip['bus_no'],

    'trip_no' =>
        $activeTrip['trip_no'],

    'driver_id' =>
        $activeTrip['driver_id'],

    'boarding_point' =>
        $boarding,

    'selected_destination' =>
        $activeTrip['selected_destination'] ?? null,

    'estimated_fare' =>
        isset($activeTrip['estimated_fare'])
            ? (float)$activeTrip['estimated_fare']
            : null,

    'actual_destination' =>
        $actualDestination,

    'actual_stop_id' =>
        $actualStopId,

    'actual_km_marker' =>
        $actualKm,

    'destination' =>
        $actualDestination,

    'discount_type' =>
        $discount,

    'payment_method' =>
        $paymentMethod,

    'cash_amount' =>
        $cashAmount,

    'gcash_amount' =>
        $gcashAmount,

    'payment_status' =>
        'pending',

    'trip_id' =>
        $activeTrip['id'],

    'base_fare' =>
        $baseFare,

    'discount_amount' =>
        $discountAmount,

    'fare' =>
        $totalFare,

    'final_fare' =>
        $totalFare,

    'companions_updated' =>
        $companionFinalCount,

    'trip_status' =>
        'Completed',

]);

} catch (\PDOException $e) {

    error_log('Passenger scan database error [' . $scanStep . ']: ' . $e->getMessage());

    $scanErrorDetail['sql'] = ['state' => (string) $e->getCode(), 'message' => $e->getMessage()];

    $scanSendFailure('DB_ERROR', 'Something went wrong. Please try again.', 500);

} catch (\Throwable $e) {

    $scanErrorDetail['exception'] = [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    error_log('Passenger scan unhandled error [' . $scanStep . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $scanSendFailure('SERVER_ERROR', 'Something went wrong. Please try again.', 500);

}

// =========================
// ARCHIVE HELPER
// =========================
function archiveCompletedTrip($conn, $activeTrip, $final)
{
    // Duplicate guard: never archive the same trip twice.
    $check = $conn->prepare("SELECT COUNT(*) FROM passenger_trip_history WHERE trip_id = ?");
    $check->execute([(string)$activeTrip['id']]);
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    // Passenger details (passengers table has student_id + discount_id;
    // senior_pwd_id/beneficiary_id/coop_disc_id live only in trip history)
    $pStmt = $conn->prepare("
        SELECT full_name, discount_type, student_id, discount_id
        FROM passengers WHERE id = ? LIMIT 1
    ");
    $pStmt->execute([(int)$activeTrip['passenger_id']]);
    $pax = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Driver name
    $dStmt = $conn->prepare("SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1");
    $dStmt->execute([$activeTrip['driver_id']]);
    $driverFullName = $dStmt->fetchColumn() ?: null;

    $remarks = 'selected_destination: ' . ($activeTrip['selected_destination'] ?? 'none')
        . '; estimated_fare: ' . ($activeTrip['estimated_fare'] ?? 'none')
        . '; final_fare: ' . $final['totalFare'];

    $stmt = $conn->prepare("
        INSERT INTO passenger_trip_history
        (
            passenger_name,
            passenger_type,
            student_id,
            boarding_point,
            destination,
            drivers_full_name,
            driver_id,
            trip_id,
            fare,
            payment_method,
            date_booked,
            trip_date,
            senior_pwd_id,
            beneficiary_id,
            coop_disc_id,
            remarks,
            archived
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $pax['full_name'] ?? 'Unknown',
        $pax['discount_type'] ?? 'regular',
        $pax['student_id'] ?? null,
        $activeTrip['boarding_point'] ?? '',
        $final['actualDestination'],
        $driverFullName,
        $activeTrip['driver_id'],
        (string)$activeTrip['id'],
        $final['totalFare'],
        $final['paymentMethod'],
        $activeTrip['boarding_time'] ?? null,
        $activeTrip['boarding_time'] ? date('Y-m-d', strtotime($activeTrip['boarding_time'])) : null,
        $pax['discount_id'] ?? null,
        null,
        null,
        $remarks
    ]);
}
