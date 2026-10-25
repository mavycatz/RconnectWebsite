<?php

require_once __DIR__ . '/bootstrap.php';

passenger_install_error_handler();
passenger_enforce_https();
passenger_mobile_gate();
passenger_send_security_headers();
passenger_session_start();

// The QR entry stays login-free. The session is classified before routing so
// a leftover account session can never bounce the visitor past the One-Time /
// Regular choice: a usable regular session keeps its homepage shortcut, a
// restricted account keeps its fence, an invalid session (dead identity,
// revoked or disabled device) is demoted to a visitor with the scanned
// bus/trip context preserved, and an unverifiable state is served as a
// visitor for this request without logging the user out.
$entryState = (string) (passenger_entry_session_state()['state'] ?? 'visitor');

if ($entryState === 'regular' || $entryState === 'restricted') {
    // Reuses the exact protected-route checks: valid/legacy sessions return,
    // restricted accounts are redirected to the restriction notice.
    passenger_require_auth(false);
} elseif ($entryState === 'invalid') {
    passenger_clear_entry_identity();
    $entryState = 'visitor';
} elseif ($entryState === 'unverifiable') {
    $entryState = 'visitor';
}

$bus = trim((string) ($_GET['bus'] ?? ''));
$trip = trim((string) ($_GET['trip'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));

$bus = (string) preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $bus);
$bus = mb_substr($bus, 0, 50);
$trip = (string) preg_replace('/\D/', '', $trip);
$trip = mb_substr($trip, 0, 12);
$token = (string) preg_replace('/[^A-Fa-f0-9]/', '', $token);
$token = mb_substr($token, 0, 64);

$qrDeepLinkLog = function (string $code, string $busNo, string $tripNo, string $message = '') {
    passenger_qr_scan_log([
        'route' => '/passenger/scan',
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'type' => 'deep_link',
        'step' => 'deep_link',
        'code' => $code,
        'http_status' => 302,
        'message' => $message,
        'request' => [
            'bus_no' => $busNo !== '' ? $busNo : null,
            'trip_no' => $tripNo !== '' ? $tripNo : null,
            'passenger_id' => isset($_SESSION['passenger_id']) ? (int) $_SESSION['passenger_id'] : null,
            'origin' => (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        ],
    ]);
};

if ($bus === '') {
    $qrDeepLinkLog('bus_missing', '', $trip, 'Deep link carried no bus value');
    header('Location: ' . passenger_url('login'));
    exit;
}

$validBus = false;
$deepLinkFailureCode = null;

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    require_once __DIR__ . '/services/OneTime/OneTimeSchema.php';
    require_once __DIR__ . '/services/OneTime/OneTimeService.php';

    $conn = \App\Core\Database::tryGet();

    if (!$conn) {
        // Database::get() would die with a plain-text body and no redirect;
        // degrade to the safe entry page and record why.
        $qrDeepLinkLog('database_error', $bus, $trip, 'Database is unavailable while validating the deep link');
        $deepLinkFailureCode = 'database_error';
        $validBus = false;
    } else {
        ensureOneTimeSchema($conn);

        $busRow = OneTimeService::resolveBus($conn, $bus);
        $validBus = $busRow !== null;

        // A supplied credential token must be an active QR credential that
        // belongs to this bus; revoked or unknown tokens are refused.
        // Token-less legacy prints keep resolving by bus id for compatibility.
        if ($validBus && $token !== '') {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrService.php';
            $qrService = new \Admin\Services\Bus\BusQrService($conn);
            if ($qrService->resolveForBus($token, (int) ($busRow['id'] ?? 0)) === null) {
                $validBus = false;
                $deepLinkFailureCode = 'qr_revoked';
                $qrDeepLinkLog('qr_revoked', $bus, $trip, 'QR credential is revoked, unknown, or belongs to another bus');
            }
        }

        $activeTrip = OneTimeService::resolveActiveTrip($conn, $bus);
        $currentTripId = $activeTrip ? (int) $activeTrip['id'] : null;
        $currentTripNumber = $activeTrip ? ($activeTrip['trip_number'] ?? null) : null;

        if ($validBus && $trip !== '') {
            // The QR trip must belong to the scanned bus AND still be active;
            // otherwise it is dropped so the app resolves the bus's current trip.
            $submittedTrip = $trip;
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM bus_trips
                WHERE id = ?
                  AND LOWER(TRIM(bus_id)) = LOWER(TRIM(?))
                  AND status IN ('Scheduled','Departed','On Route','Started','Active','Ongoing')
                  AND trip_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ");
            $stmt->execute([(int) $trip, $bus]);
            if ((int) $stmt->fetchColumn() === 0) {
                $trip = $currentTripId !== null ? (string) $currentTripId : '';
                $qrDeepLinkLog(
                    'trip_stale',
                    $bus,
                    $submittedTrip,
                    'Trip is not part of this bus or is no longer active; using current trip instead'
                );
            }
        } elseif ($validBus && $trip === '' && $currentTripId !== null) {
            $trip = (string) $currentTripId;
        }
    }
} catch (\Throwable $e) {
    error_log('Passenger scan validation failed: ' . $e->getMessage());
    $qrDeepLinkLog('schema_error', $bus, $trip, $e->getMessage());
    $deepLinkFailureCode = 'schema_error';
    $validBus = false;
}

if (!$validBus) {
    if ($deepLinkFailureCode === null) {
        $qrDeepLinkLog('bus_invalid', $bus, $trip, 'Bus QR is not recognized or cannot be resolved');
    }
    header('Location: ' . passenger_url('login'));
    exit;
}

$_SESSION['qr_bus'] = $bus;
$_SESSION['qr_trip'] = $trip;
$_SESSION['qr_scanned_at'] = time();

// -----------------------------------------------------------------
// GPS-driven boarding: record where the scanned bus was when the QR was
// validated. The One-Time form derives the boarding point from this
// snapshot; an unavailable or stale position is recorded with its reason so
// the boarding step can block selection and offer a retry. The scan itself
// still succeeds - the location gate lives on the boarding step.
// -----------------------------------------------------------------
require_once __DIR__ . '/services/BusPositionService.php';
require_once __DIR__ . '/services/BusBoardingService.php';

$locationSnapshot = [
    'bus_id' => $bus,
    'trip_id' => $currentTripId,
    'available' => false,
    'usable' => false,
    'reason' => 'no_active_trip',
    'source' => null,
    'latitude' => null,
    'longitude' => null,
    'recorded_at' => null,
    'freshness' => null,
    'matched_stop' => null,
    'matched_stop_id' => null,
    'matched_fare_id' => null,
    'direction' => null,
    'captured_at' => time(),
];

if (!empty($activeTrip) && !empty($conn)) {
    try {
        $position = passengerBusPositionForTrip($conn, $activeTrip);
        $directionKey = passengerBusDirectionKey((string) ($activeTrip['route_name'] ?? ''));
        $matchedStop = null;
        $matchedStopId = null;
        $matchedFareId = null;

        if ($directionKey !== null && !empty($position['usable']) && $position['latitude'] !== null) {
            $routeStops = OneTimeService::resolveRouteStops($conn, (string) $activeTrip['route_name']);
            $fallbacks = passengerBusRouteStopFallbacks($conn, (string) $activeTrip['route_name']);
            $orderedStops = passengerBusOrderedStopsWithFallbacks($routeStops, $fallbacks);
            $match = passengerMatchBusPositionToOrderedStops(
                $orderedStops,
                $directionKey,
                $position['latitude'],
                $position['longitude']
            );

            if ($match !== null) {
                $matchedStop = $orderedStops[$match['index']]['name'] ?? null;
                $matchedStopId = $orderedStops[$match['index']]['route_stop_id'] ?? null;
                $matchedFareId = $orderedStops[$match['index']]['fare_id'] ?? null;
            }
        }

        $locationSnapshot = [
            'bus_id' => $bus,
            'trip_id' => $currentTripId,
            'available' => (bool) $position['available'],
            'usable' => (bool) $position['usable'],
            'reason' => $position['reason'],
            'source' => $position['source'],
            'latitude' => $position['latitude'],
            'longitude' => $position['longitude'],
            'recorded_at' => $position['updated_at'],
            'freshness' => $position['freshness'],
            'matched_stop' => $matchedStop,
            'matched_stop_id' => $matchedStopId,
            'matched_fare_id' => $matchedFareId,
            'direction' => $directionKey,
            'captured_at' => time(),
        ];
    } catch (\Throwable $e) {
        $locationSnapshot['reason'] = 'snapshot_error';
        error_log('Passenger scan location snapshot failed: ' . $e->getMessage());
    }
}

$_SESSION['qr_location_snapshot'] = $locationSnapshot;

// The entry registers the boarding point once: the stop the bus is at when
// the passenger scans is locked for the session, so refreshes, live bus
// movement and re-opened forms cannot change it. A new scan replaces it; a
// scan that resolves no stop clears it so the register page can register the
// point on the first successful resolution instead.
unset($_SESSION['ot_boarding_lock']);
if (!empty($locationSnapshot['usable']) && !empty($locationSnapshot['matched_fare_id'])) {
    $_SESSION['ot_boarding_lock'] = [
        'bus' => $bus,
        'trip_id' => $locationSnapshot['trip_id'] !== null ? (int) $locationSnapshot['trip_id'] : null,
        'boarding_id' => (int) $locationSnapshot['matched_fare_id'],
        'source' => $locationSnapshot['source'],
        'freshness' => $locationSnapshot['freshness'],
        'latitude' => $locationSnapshot['latitude'],
        'longitude' => $locationSnapshot['longitude'],
        'recorded_at' => $locationSnapshot['recorded_at'],
        'locked_at' => time(),
    ];
}

passenger_qr_scan_log([
    'route' => '/passenger/scan',
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
    'type' => 'bus_location_snapshot',
    'step' => 'location_snapshot',
    'code' => (string) ($locationSnapshot['reason'] ?? ($locationSnapshot['usable'] ? 'located' : 'unusable')),
    'message' => !empty($locationSnapshot['usable'])
        ? 'Bus location snapshot captured'
        : 'Bus location snapshot unavailable',
    'snapshot' => $locationSnapshot,
]);

// A usable regular passenger keeps the account homepage shortcut
if ($entryState === 'regular') {
    header('Location: ' . passenger_url('home'));
    exit;
}

// -----------------------------------------------------------------
// One-Time passenger scanning flow:
// 1. Current bus & trip_id resolved
// 2. Identify One-Time passenger (session or recovery cookie)
// 3. Search for existing trip record for passenger + CURRENT trip_id
// 4. Same trip_id -> open existing receipt/payment screen
// 5. Different trip_id -> start new trip flow (landing/form)
// -----------------------------------------------------------------
$passengerId = (int) ($_SESSION['passenger_id'] ?? ($_COOKIE['ot_passenger_id'] ?? 0));
$existingSameTrip = null;
$lookupFailed = false;

if ($passengerId > 0 && !empty($conn)) {
    if ($currentTripId !== null) {
        try {
            /* Live-filtered: a completed/cancelled/archived row or an ended
               bus trip no longer forces the receipt; the passenger reaches
               the new-trip flow. */
            $existingSameTrip = OneTimeService::liveTripForPassengerAndTrip(
                $conn,
                $passengerId,
                $currentTripId,
                $currentTripNumber
            );
        } catch (\Throwable $e) {
            $existingSameTrip = null;
            $lookupFailed = true;
        }
    } else {
        /* The scanned bus reports no resolvable active trip. A live row left
           on this bus means the previous trip's status is unresolved: keep it
           open for recovery. */
        try {
            $existingSameTrip = OneTimeService::liveTripForPassengerAndBus($conn, $passengerId, $bus);
        } catch (\Throwable $e) {
            $existingSameTrip = null;
            $lookupFailed = true;
        }
    }
}

if ($existingSameTrip) {
    // EXISTING LIVE DATA WITH THE SAME trip_id:
    // Continue directly to the existing trip's receipt/payment screen.
    $_SESSION['passenger_id'] = $passengerId;
    $_SESSION['is_one_time'] = true;
    header('Location: ' . passenger_url('one-time/register'));
    exit;
}

if ($lookupFailed) {
    // The previous trip's status could not be confirmed: keep the trip open
    // for recovery instead of starting a fresh booking. A duplicate booking
    // for the same trip is still refused server-side either way.
    header('Location: ' . passenger_url('one-time/register'));
    exit;
}

// NEW TRIP (no record or only completed/ended records for this trip_id):
// Allow the passenger to continue normally to the landing / new trip form.
header('Location: ' . passenger_url('one-time/landing'));
exit;
