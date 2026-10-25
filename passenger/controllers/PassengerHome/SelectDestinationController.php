<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';
require_once __DIR__ . '/../../services/BoardingStopOrder.php';
require_once __DIR__ . '/TripSchemaHelper.php';
require_once __DIR__ . '/../../services/Companion/CompanionSchema.php';
require_once __DIR__ . '/../../services/Companion/CompanionService.php';

use App\Core\Database;

// =========================
// DIAGNOSTICS
// =========================
// Per-step tracing for the destination save flow. The debug section in
// failure responses is exposed only when PASSENGER_QR_SCAN_DEBUG is enabled;
// the structured log line is always written so failures remain reviewable.
$destDebug = function_exists('passenger_qr_scan_debug_enabled')
    ? passenger_qr_scan_debug_enabled()
    : false;
$destStep = 'input';
$destContext = [
    'passenger_id' => isset($_SESSION['passenger_id']) ? (int) $_SESSION['passenger_id'] : null,
    'destination_id' => null,
    'trip_id' => null,
    'trip_no' => null,
];
$destErrorDetail = [];

$destLogAttempt = function (array $entry) use (&$destStep, &$destContext) {
    if (!function_exists('passenger_qr_scan_log')) {
        return;
    }

    passenger_qr_scan_log(array_merge([
        'route' => '/passenger/trip/select-destination',
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

$destSendSuccess = function (array $body) use ($destLogAttempt) {
    $destLogAttempt([
        'type' => 'success',
        'code' => 'OK',
        'http_status' => 200,
        'trip_id' => $body['trip_id'] ?? null,
        'persist_mode' => $body['persist_mode'] ?? null,
    ]);

    echo json_encode($body);
    exit;
};

try {

// =========================
// INPUT
// =========================
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    $destSendFailure('INVALID_JSON', 'Invalid request.', 400);
}

$destStep = 'auth';

if (!isset($_SESSION['passenger_id'])) {
    $destSendFailure('AUTH_REQUIRED', 'Not logged in', 401);
}

$passenger_id = (int) $_SESSION['passenger_id'];
$destContext['passenger_id'] = $passenger_id;

$destStep = 'input';
$destinationId = (int) ($data['destination_id'] ?? 0);
$destContext['destination_id'] = $destinationId;

if ($destinationId <= 0) {
    $destSendFailure('INVALID_DESTINATION_ID', 'destination_id is required', 400);
}

// =========================
// DATABASE
// =========================
$destStep = 'active_trip_lookup';
$conn = Database::tryGet();

if ($conn === null) {
    $destSendFailure('DB_UNAVAILABLE', 'We could not reach the service right now. Please try again in a moment.', 503);
}

ensureTripFlowSchema($conn);

// =========================
// ACTIVE TRIP
// =========================
// The trip_status column is one of the lazily-added optional columns. When it
// is unavailable (no ALTER privilege / locked table), look the trip up without
// it instead of failing the whole selection; the missing status simply means
// there is no completed/cancelled flag to filter on.
try {
    $stmt = $conn->prepare("
        SELECT pt.* FROM passenger_trips pt
        LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
        WHERE pt.passenger_id = ?
        AND pt.dropoff_time IS NULL
        AND (
            pt.trip_status IS NULL
            OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled')
        )
        AND (
            bt.id IS NULL
            OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
        )
        ORDER BY pt.id DESC LIMIT 1
    ");
    $stmt->execute([$passenger_id]);
    $activeTrip = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $destLogAttempt([
        'type' => 'degraded_lookup',
        'code' => 'TRIP_LOOKUP_DEGRADED',
        'http_status' => 200,
        'message' => 'Active trip lookup retried without the optional trip_status column.',
        'error' => $e->getMessage(),
        'sql_state' => $e->getCode(),
    ]);

    $stmt = $conn->prepare("
        SELECT pt.* FROM passenger_trips pt
        LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
        WHERE pt.passenger_id = ?
        AND pt.dropoff_time IS NULL
        AND (
            bt.id IS NULL
            OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
        )
        ORDER BY pt.id DESC LIMIT 1
    ");
    $stmt->execute([$passenger_id]);
    $activeTrip = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$activeTrip) {
    $destSendFailure('NO_ACTIVE_TRIP', 'No in-progress trip found. Please scan the bus QR first.', 409);
}

$destContext['trip_id'] = (int) $activeTrip['id'];
$destContext['trip_no'] = (int) $activeTrip['trip_no'];

$tripStatus = strtolower($activeTrip['trip_status'] ?? '');
if ($tripStatus === 'completed' || $tripStatus === 'cancelled') {
    $destSendFailure('TRIP_NOT_IN_PROGRESS', 'This trip is no longer in progress.', 409);
}

// =========================
// VALIDATE DESTINATION BELONGS TO TRIP ROUTE
// =========================
$destStep = 'route_resolution';

$stmt = $conn->prepare("
    SELECT id, destination, display_order, regular_fare, student_fare, special_fare, route_name
    FROM fare_pricing
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$destinationId]);
$destStop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$destStop) {
    $destSendFailure('INVALID_DESTINATION', 'Invalid destination.', 404);
}

// The trip's authoritative route is the bus trip's route (the same source the
// destination list and the fare engine use). Fall back to the stored trip
// route only when the bus trip lookup yields nothing.
$stmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE id = ? LIMIT 1");
$stmt->execute([(int) $activeTrip['trip_no']]);
$tripRoute = trim((string) $stmt->fetchColumn());
if ($tripRoute === '') {
    $tripRoute = trim((string) ($activeTrip['route_name'] ?? ''));
}

// Without a trip route the boarding position and the destination cannot be
// compared on one direction, so refuse the selection with a clear message
// instead of validating against a possibly wrong route's ordering.
if ($tripRoute === '') {
    $destSendFailure('TRIP_ROUTE_MISSING', 'This trip has no route assigned yet. Please ask the driver or try again later.', 422);
}

$destRoute = trim((string) $destStop['route_name']);
if ($destRoute !== '') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';
    // Skip the direction comparison when the deployed fare-engine copy does
    // not provide the canonicalizer, instead of failing the selection.
    if (function_exists('canonicalRouteName') && canonicalRouteName($destRoute) !== canonicalRouteName($tripRoute)) {
        $destSendFailure('ROUTE_MISMATCH', 'Destination does not belong to this bus trip route.', 422);
    }
}

// The destination is locked once saved: a different destination cannot
// replace it. Re-submitting the same destination is allowed so the stored
// estimate can be restored idempotently.
$lockedDestinationId = (int) ($activeTrip['destination_id'] ?? 0);
$regularPassenger = empty($_SESSION['is_one_time'])
    && strtolower((string) ($activeTrip['identification_source'] ?? '')) !== 'one_time';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';
if ($lockedDestinationId > 0 && $lockedDestinationId !== $destinationId) {
    if ($regularPassenger) {
        try {
            RegularPartyService::assertEditable(RegularPartyService::ownedTrip($conn, $passenger_id, (int) $activeTrip['id']),
                RegularPartyService::travellerRows($conn, (int) $activeTrip['id']));
        } catch (RegularPartyException $e) {
            $destSendFailure($e->errorCode, $e->getMessage(), $e->httpStatus, ['selected_destination_id' => $lockedDestinationId]);
        }
        $destSendFailure('DESTINATION_LOCKED', 'Your destination is already set and cannot be changed.', 409, [
            'selected_destination_id' => $lockedDestinationId,
            'selected_destination' => $activeTrip['selected_destination'] ?? $activeTrip['destination'] ?? null,
        ]);
    } else {
    $destSendFailure('DESTINATION_LOCKED', 'Your destination is already set and cannot be changed.', 409, [
        'selected_destination_id' => $lockedDestinationId,
        'selected_destination' => $activeTrip['selected_destination'] ?? ($activeTrip['destination'] ?? null),
    ]);
    }
}

// Restoring the same destination must not reprice an already-paid party or an
// issued QR against subsequently edited fare tables.
if ($regularPassenger && $lockedDestinationId === $destinationId) {
    $fixedParty = strtolower((string) ($activeTrip['payment_status'] ?? '')) === 'paid'
        || (float) ($activeTrip['verified_cash_amount'] ?? 0) > 0
        || (!empty($activeTrip['paymongo_intent_id']) && !empty($activeTrip['paymongo_qr_expires_at'])
            && strtotime((string) $activeTrip['paymongo_qr_expires_at']) > time());
    foreach (RegularPartyService::travellerRows($conn, (int) $activeTrip['id']) as $row) {
        if (strtolower((string) ($row['payment_status'] ?? '')) === 'paid' || (float) ($row['verified_cash_amount'] ?? 0) > 0) { $fixedParty = true; }
    }
    if ($fixedParty) {
        $party = RegularPartyService::read($conn, $passenger_id, (int) $activeTrip['id']);
        $destSendSuccess([
            'success' => true, 'trip_id' => (int) $activeTrip['id'], 'party' => $party,
            'selected_destination' => $activeTrip['destination'], 'selected_destination_id' => $destinationId,
            'estimated_fare' => (float) ($activeTrip['fare'] ?? 0), 'fare' => (float) ($activeTrip['fare'] ?? 0),
            'base_fare' => (float) ($activeTrip['base_fare'] ?? 0), 'discount_amount' => (float) ($activeTrip['discount_amount'] ?? 0),
            'boarding_point' => $activeTrip['boarding_point'], 'boarding_stop_id' => $activeTrip['boarding_stop_id'] ?? null,
            'destination' => $activeTrip['destination'], 'trip_status' => $activeTrip['trip_status'],
        ]);
    }
}

// =========================
// KM MARKER FROM ROUTE STOPS
// =========================
$selectedKm = null;
try {
    $stmt = $conn->prepare("
        SELECT km_marker
        FROM route_stops
        WHERE fare_pricing_id = ?
        LIMIT 1
    ");
    $stmt->execute([$destinationId]);
    $selectedKm = $stmt->fetchColumn();
    if ($selectedKm === null || $selectedKm === false) {
        $stmt = $conn->prepare("
            SELECT km_marker
            FROM route_stops
            WHERE LOWER(TRIM(stop_name)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$destStop['destination']]);
        $selectedKm = $stmt->fetchColumn();
    }
} catch (\Throwable $e) {
    $selectedKm = null;
}

// =========================
// BOARDING FARE ZONE (the engine's own GPS stop -> fare mapping)
// =========================
$destStep = 'boarding_zone';

$boarding = trim((string) $activeTrip['boarding_point']);
$boardingStopId = (int) ($activeTrip['boarding_stop_id'] ?? 0);

// Authoritative mapping first: the GPS-matched route_stops row's
// fare_pricing_id (same bridge calculateFare() uses). Name matching is only a
// fallback for trips without a route_stops id.
try {
    $boardingFareStop = passengerResolveBoardingFareZone($conn, $tripRoute, $boarding, $boardingStopId);
} catch (\Throwable $e) {
    $destErrorDetail['exception'] = get_class($e) . ': ' . $e->getMessage();
    if ($e instanceof \PDOException) {
        $destErrorDetail['sql'] = ['state' => $e->getCode(), 'message' => $e->getMessage()];
    }
    $destSendFailure('BOARDING_ZONE_UNAVAILABLE', 'We could not determine your boarding point right now. Please try again.', 500);
}

$boardingFareName = $boardingFareStop !== null ? (string) $boardingFareStop['destination'] : $boarding;
$boardingFareId = $boardingFareStop !== null ? (int) $boardingFareStop['id'] : null;

// Destinations must not be strictly behind the boarding point. The boarding
// stop itself — or any fare row that names the same location as the boarding
// point — is allowed as a local trip and settles at the base (minimum) fare
// the engine returns for the same stop.
$boardingOrder = $boardingFareStop !== null ? (int) $boardingFareStop['display_order'] : null;
$destinationOrder = (int) $destStop['display_order'];

$boardingKm = isset($activeTrip['boarding_km_marker']) && $activeTrip['boarding_km_marker'] !== null
    ? (int) $activeTrip['boarding_km_marker']
    : null;
$destinationKm = ($selectedKm !== null && $selectedKm !== false) ? (int) $selectedKm : null;

$sameLocation = (
    $boardingFareId !== null && $destinationId === $boardingFareId
) || (
    $boardingKm !== null && $destinationKm !== null && $boardingKm === $destinationKm
) || passengerIsSameBoardingLocation(
    $conn,
    $tripRoute,
    [$boarding, $boardingFareName],
    $boardingFareId,
    (string) $destStop['destination']
);

if ($boardingOrder !== null && $destinationOrder < $boardingOrder && !$sameLocation) {
    $destSendFailure(
        'DESTINATION_BEHIND_BOARDING',
        'That stop is behind your boarding point on this trip. Please choose a stop ahead of your boarding point.',
        400,
        [
            'fare_details' => [
                'route_name' => $tripRoute,
                'boarding_fare_id' => $boardingFareId,
                'boarding_fare_name' => $boardingFareName,
                'boarding_order' => $boardingOrder,
                'boarding_km_marker' => $boardingKm,
                'destination_fare_id' => $destinationId,
                'destination_fare_name' => $destStop['destination'],
                'destination_order' => $destinationOrder,
                'destination_km_marker' => $destinationKm,
                'same_location' => $sameLocation,
            ],
        ]
    );
}

// =========================
// BUS PROGRESS (PASSED STOPS)
// =========================
// Stops the bus has already passed are not selectable, even when they are
// still ahead of the recorded boarding point. The bus position is converted
// to its stop on the trip's own route_stops/display_order sequence and the
// destination must be further along that sequence; a raw proximity guess is
// never used. Re-confirming an already saved destination stays allowed so a
// reopened receipt can restore its stored fare.
$destStep = 'bus_progress';
$destinationAlreadySaved = $lockedDestinationId > 0 && $lockedDestinationId === $destinationId;

if (!$destinationAlreadySaved) {
    try {
        $progressStops = [];
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';
            $fareRoute = function_exists('resolveFareRouteName')
                ? resolveFareRouteName($conn, $tripRoute)
                : $tripRoute;
            $stmt = $conn->prepare("
                SELECT id, destination, display_order
                FROM fare_pricing
                WHERE route_name = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$fareRoute]);
            $progressStops = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $progressStops = [];
        }

        if ($progressStops !== []) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/BusBoardingService.php';

            $busColumns = [];
            try {
                $stmt = $conn->prepare("SELECT bus_id, driver_id FROM bus_trips WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $activeTrip['trip_no']]);
                $busColumns = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e) {
                $busColumns = [];
            }

            $busBoarding = passengerResolveBusBoarding($conn, [
                'id' => (int) $activeTrip['trip_no'],
                'route_name' => $tripRoute,
                'bus_id' => (string) ($busColumns['bus_id'] ?? ''),
                'driver_id' => (string) ($busColumns['driver_id'] ?? ''),
            ], $progressStops);

            if ($busBoarding['error'] === null && !empty($busBoarding['boarding'])) {
                $busOrder = (int) $busBoarding['boarding']['display_order'];
                $progressOrder = $boardingOrder !== null && $boardingOrder > $busOrder ? $boardingOrder : $busOrder;

                if ($destinationOrder < $progressOrder) {
                    $destSendFailure(
                        'DESTINATION_PASSED',
                        'That stop has already been passed by the bus. Please choose a destination from the remaining stops.',
                        409,
                        [
                            'fare_details' => [
                                'route_name' => $tripRoute,
                                'boarding_order' => $boardingOrder,
                                'current_stop_order' => $busOrder,
                                'progress_order' => $progressOrder,
                                'destination_order' => $destinationOrder,
                            ],
                        ]
                    );
                }
            }
        }
    } catch (\Throwable $e) {
        // A progress check that cannot run must not block a valid selection:
        // the stored boarding order still fences stops behind it.
    }
}

// =========================
// ESTIMATED FARE (shared engine, same rules as final fare)
// =========================
$destStep = 'fare_calc';

$stmt = $conn->prepare("SELECT discount_type FROM passengers WHERE id = ? LIMIT 1");
$stmt->execute([$passenger_id]);
$discount = strtolower(trim((string) $stmt->fetchColumn())) ?: 'regular';

try {
    $baseFareResult = calculateFare(
        $activeTrip['trip_no'],
        $boardingFareName,
        $destStop['destination'],
        'regular',
        $boardingFareId,
        $destinationId,
        null,
        null
    );

    $estimateResult = calculateFare(
        $activeTrip['trip_no'],
        $boardingFareName,
        $destStop['destination'],
        $discount,
        $boardingFareId,
        $destinationId,
        null,
        null
    );
} catch (\Throwable $e) {
    $destErrorDetail['exception'] = get_class($e) . ': ' . $e->getMessage();
    if ($e instanceof \PDOException) {
        $destErrorDetail['sql'] = ['state' => $e->getCode(), 'message' => $e->getMessage()];
    }
    $destSendFailure('FARE_CALC_FAILED', 'We could not calculate your fare right now. Please try again.', 500);
}

$estimatedFare = (float) ($estimateResult['fare'] ?? 0);

if ($estimatedFare <= 0) {
    $destSendFailure('FARE_UNAVAILABLE', 'Could not estimate fare for the selected destination.', 422, [
        'fare_details' => [
            'route_name' => $tripRoute,
            'fare_resolution' => $estimateResult,
        ],
    ]);
}

$baseFare = (float) ($baseFareResult['fare'] ?? 0);
if ($baseFare <= 0) {
    $baseFare = $estimatedFare;
}

$discountAmount = $baseFare - $estimatedFare;
if ($discountAmount < 0) {
    $discountAmount = 0.0;
}

// =========================
// STORE SELECTED DESTINATION
// =========================
$destStep = 'persist';
$persistMode = 'full';
$persistError = null;
$hasRegularParty = $regularPassenger;
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';
if ($hasRegularParty) {
    RegularPartyService::ensureState($conn);
    $conn->beginTransaction();
    $lockedTrip = RegularPartyService::ownedTrip($conn, $passenger_id, (int) $activeTrip['id'], true);
    RegularPartyService::assertEditable($lockedTrip, RegularPartyService::travellerRows($conn, (int) $activeTrip['id']));
}

try {
    $stmt = $conn->prepare("
        UPDATE passenger_trips
        SET selected_destination = ?,
            selected_destination_id = ?,
            selected_km_marker = ?,
            estimated_fare = ?,
            destination = ?,
            destination_id = ?,
            fare = ?,
            base_fare = ?,
            discount_amount = ?,
            trip_status = 'In Progress'
        WHERE id = ?
    ");
    $stmt->execute([
        $destStop['destination'],
        $destinationId,
        $selectedKm !== null && $selectedKm !== false ? (int) $selectedKm : null,
        $estimatedFare,
        $destStop['destination'],
        $destinationId,
        $estimatedFare,
        $baseFare,
        $discountAmount,
        $activeTrip['id']
    ]);
} catch (\PDOException $e) {
    if ($hasRegularParty) {
        $conn->rollBack();
        $destSendFailure('PERSIST_FAILED', 'The travelling party could not be updated. Please try again.', 500);
    }
    // Degraded save: the driver feed consumes destination/fare, so keep those
    // (and trip_status when the column exists) even when an optional estimate
    // column is unavailable. Every skipped field is recorded in the diagnostics.
    $persistError = $e;

    $degradedAttempts = [
        'required_with_status' => [
            'sql' => "UPDATE passenger_trips
                SET destination = ?, destination_id = ?, fare = ?, trip_status = 'In Progress'
                WHERE id = ?",
            'params' => [$destStop['destination'], $destinationId, $estimatedFare, $activeTrip['id']],
            'skipped' => ['selected_destination', 'selected_destination_id', 'selected_km_marker', 'estimated_fare', 'base_fare', 'discount_amount'],
        ],
        'required_only' => [
            'sql' => "UPDATE passenger_trips
                SET destination = ?, destination_id = ?, fare = ?
                WHERE id = ?",
            'params' => [$destStop['destination'], $destinationId, $estimatedFare, $activeTrip['id']],
            'skipped' => ['selected_destination', 'selected_destination_id', 'selected_km_marker', 'estimated_fare', 'base_fare', 'discount_amount', 'trip_status'],
        ],
    ];

    foreach ($degradedAttempts as $attemptMode => $attempt) {
        try {
            $degraded = $conn->prepare($attempt['sql']);
            $degraded->execute($attempt['params']);

            $persistError = null;
            $persistMode = 'degraded';

            $destLogAttempt([
                'type' => 'degraded_save',
                'code' => 'PERSIST_DEGRADED',
                'http_status' => 200,
                'message' => 'Full destination update failed; the required destination and fare were still saved.',
                'degraded_mode' => $attemptMode,
                'skipped_fields' => $attempt['skipped'],
                'error' => $e->getMessage(),
                'sql_state' => $e->getCode(),
            ]);
            break;
        } catch (\Throwable $retryError) {
            $persistError = $retryError;
        }
    }
}

if ($persistError !== null) {
    $destErrorDetail['exception'] = get_class($persistError) . ': ' . $persistError->getMessage();
    if ($persistError instanceof \PDOException) {
        $destErrorDetail['sql'] = ['state' => $persistError->getCode(), 'message' => $persistError->getMessage()];
    }
    $destSendFailure('PERSIST_FAILED', 'We could not save your destination right now. Please try again.', 500);
}

// =========================
// COMPANIONS (shared destination + fare)
// =========================
// Companions boarded with this passenger ride to the same destination. Their
// rows are updated with the same locked destination and the fare the companion
// is entitled to; the primary's own discount is never reused for them.
$companionsUpdated = 0;
try {
    if ($hasRegularParty) {
        $party = RegularPartyService::repriceTrip($conn, $passenger_id, (int) $activeTrip['id']);
        $companionsUpdated = count($party['companions']);
        $conn->commit();
    } else {
    $mirror = CompanionService::mirrorDestination(
        $conn,
        (int) $activeTrip['id'],
        (int) $activeTrip['trip_no'],
        $boardingFareName,
        $boardingFareId,
        $destStop['destination'],
        $destinationId,
        $selectedKm !== null && $selectedKm !== false ? (int) $selectedKm : null
    );
    $companionsUpdated = $mirror['updated'];
    $party = $regularPassenger ? RegularPartyService::read($conn, $passenger_id, (int) $activeTrip['id']) : null;
    }
} catch (\Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    if ($hasRegularParty) {
        $destSendFailure('PARTY_FARE_FAILED', 'The travelling party fares could not be calculated. Please try again.', 500);
    }
    error_log('Companion destination mirror failed: ' . $e->getMessage());
    $companionsUpdated = 0;
}

// =========================
// RESPONSE
// =========================
$destStep = 'response';

$destSendSuccess([
    'success' => true,
    'trip_id' => (int) $activeTrip['id'],
    'companions_updated' => $companionsUpdated,
    'party' => $party ?? null,
    'destination_locked' => $regularPassenger ? empty($party['editable']) : true,
    'selected_destination' => $destStop['destination'],
    'selected_destination_id' => $destinationId,
    'selected_km_marker' => $selectedKm !== null && $selectedKm !== false ? (int) $selectedKm : null,
    'estimated_fare' => $estimatedFare,
    'destination' => $destStop['destination'],
    'fare' => $estimatedFare,
    'base_fare' => $baseFare,
    'discount_amount' => $discountAmount,
    'boarding_point' => $boarding,
    'boarding_stop_id' => $boardingStopId,
    'discount_type' => $discount,
    'trip_status' => 'In Progress',
    'persist_mode' => $persistMode,
    // Fare resolution details (authoritative values used by the estimate).
    'fare_details' => [
        'route_name' => $tripRoute,
        'boarding_fare_id' => $boardingFareId,
        'boarding_fare_name' => $boardingFareName,
        'boarding_order' => $boardingOrder,
        'destination_order' => $destinationOrder,
        'fare_rule' => $estimateResult['_fare_rule'] ?? null,
        'fare_column' => $estimateResult['_fare_column'] ?? null,
        'boarding_fare_value' => $estimateResult['_boarding_fare'] ?? null,
        'destination_fare_value' => $estimateResult['_destination_fare'] ?? null,
    ],
]);

} catch (RegularPartyException $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $destSendFailure($e->errorCode, $e->getMessage(), $e->httpStatus);
} catch (\Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $destErrorDetail['exception'] = get_class($e) . ': ' . $e->getMessage();
    if ($e instanceof \PDOException) {
        $destErrorDetail['sql'] = ['state' => $e->getCode(), 'message' => $e->getMessage()];
    }
    $destSendFailure('SERVER_ERROR', 'We could not complete your destination selection right now. Please try again.', 500);
}
