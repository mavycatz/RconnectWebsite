<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/OneTime/OneTimeSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/OneTime/OneTimeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/TripSchemaHelper.php';

use App\Core\Database;

/**
 * Public, QR-driven One-Time Passenger flow.
 *
 * These routes are reachable without a passenger session: the Bus QR opens
 * the landing page, and the passenger either continues to the existing
 * Regular login flow or registers a one-time trip.
 */
class OneTimeController
{
    /**
     * GET ?route=one-time/landing — "Current Trip" + user type choice.
     */
    public function landing()
    {
        $this->applyEntrySessionRouting();

        $this->redirectUnvalidatedScanHint();

        $bus = $this->scannedBus();

        $hasCurrentTripReceipt = false;
        $passengerId = (int) ($_SESSION['passenger_id'] ?? ($_COOKIE['ot_passenger_id'] ?? 0));
        if ($passengerId > 0 && !empty($_SESSION['is_one_time']) && $bus !== '') {
            try {
                $conn = Database::get();
                ensureOneTimeSchema($conn);
                $activeTrip = OneTimeService::resolveActiveTrip($conn, $bus);
                if ($activeTrip) {
                    $existing = OneTimeService::tripForPassengerAndTrip(
                        $conn,
                        $passengerId,
                        (int) $activeTrip['id'],
                        $activeTrip['trip_number'] ?? null
                    );
                    $hasCurrentTripReceipt = ($existing !== null);
                }
            } catch (\Throwable $e) {
                $hasCurrentTripReceipt = false;
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        $this->sendNoCacheHeaders();

        require $_SERVER['DOCUMENT_ROOT'] . '/passenger/views/OneTime/landing.php';
    }

    /**
     * GET ?route=one-time/trip — resolves the QR bus's current active trip.
     */
    public function trip()
    {
        header('Content-Type: application/json');
        /* State-bearing response: never served from a browser/PWA cache. */
        $this->sendNoCacheHeaders();

        // The one-time flow must start from a scanned QR: without the scan
        // session there is no bus this request is allowed to report.
        if (empty($_SESSION['qr_bus'])) {
            echo json_encode([
                'success' => false,
                'code' => 'SCAN_REQUIRED',
                'message' => 'Please scan the Bus QR again to continue.',
            ]);
            return;
        }

        $bus = $this->scannedBus();
        $lock = $this->boardingLock();

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            $result = OneTimeService::tripInfo($conn, $bus, $lock);

            // A scan that could not register the boarding point (for example a
            // momentarily unusable position) registers it on the first
            // successful resolution so later refreshes stay fixed. A stale
            // lock from an older trip is replaced the same way.
            if (empty($result['data']['boarding_locked']) && !empty($result['data']['boarding'])) {
                $this->storeBoardingLock($bus, $result['data']);
            }
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ];
        }

        echo json_encode($result);
    }

    /**
     * GET ?route=one-time/register — quick registration view.
     * POST ?route=one-time/register — validates and attaches the passenger
     * to the active trip.
     */
    public function register()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->applyEntrySessionRouting();

            $this->redirectUnvalidatedScanHint();

            $bus = $this->scannedBus();

            // A one-time passenger who already has a LIVE trip record for the
            // CURRENT active trip goes straight to their receipt/payment
            // screen. Completed, cancelled, or archived records (or rows whose
            // bus trip has ended) no longer force the receipt: the form shows
            // and a new trip can be booked.
            $resumeTrip = null;
            $passengerId = (int) ($_SESSION['passenger_id'] ?? ($_COOKIE['ot_passenger_id'] ?? 0));
            if ($passengerId > 0 && (!empty($_SESSION['is_one_time']) || isset($_COOKIE['ot_passenger_id']))) {
                try {
                    $conn = Database::get();
                    ensureOneTimeSchema($conn);

                    if ($bus !== '') {
                        $activeTrip = OneTimeService::resolveActiveTrip($conn, $bus);
                        if ($activeTrip) {
                            $resumeTrip = OneTimeService::liveTripForPassengerAndTrip(
                                $conn,
                                $passengerId,
                                (int) $activeTrip['id'],
                                $activeTrip['trip_number'] ?? null
                            );
                        }
                    } else {
                        // User opened or refreshed receipt directly without a bus in query/session
                        if (!empty($_SESSION['is_one_time'])) {
                            $resumeTrip = OneTimeService::latestTripForPassenger($conn, $passengerId);
                        }
                    }
                } catch (\Throwable $e) {
                    $resumeTrip = null;
                }
            }

            /**
             * The receipt must carry the fare of the whole party. The
             * primary trip row's own fare covers only the registered
             * passenger, so the resumed receipt rebuilds the same
             * companion-aware breakdown the registration saved (primary +
             * companion trip rows). A party that cannot be rebuilt keeps the
             * primary fare so the receipt always renders.
             */
            $oneTimeResumePartyFare = null;
            if (is_array($resumeTrip) && isset($conn) && $conn instanceof \PDO) {
                $oneTimeResumePartyFare = (float) ($resumeTrip['fare'] ?? 0);

                try {
                    require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionService.php';

                    $party = CompanionService::partyBreakdown(
                        $conn,
                        [
                            'id' => (int) ($resumeTrip['id'] ?? 0),
                            'passenger_id' => (int) ($resumeTrip['passenger_id'] ?? 0),
                            'passenger_name' => (string) ($resumeTrip['passenger_name'] ?? ''),
                            'discount_type' => (string) ($resumeTrip['passenger_discount_type'] ?? ($resumeTrip['passenger_type'] ?? 'Regular')),
                            'boarding_point' => (string) ($resumeTrip['boarding_point'] ?? ''),
                            'destination' => (string) ($resumeTrip['destination'] ?? ''),
                            'fare' => (float) ($resumeTrip['fare'] ?? 0),
                            'base_fare' => (float) ($resumeTrip['base_fare'] ?? 0),
                            'discount_amount' => (float) ($resumeTrip['discount_amount'] ?? 0),
                        ],
                        CompanionService::companionRows($conn, (int) ($resumeTrip['id'] ?? 0)),
                        []
                    );

                    if (((float) ($party['party_total'] ?? 0)) > 0) {
                        $oneTimeResumePartyFare = (float) $party['party_total'];
                    }
                } catch (\Throwable $e) {
                    // Keep the primary fare; the receipt must still render.
                }
            }

            header('Content-Type: text/html; charset=UTF-8');
            $this->sendNoCacheHeaders();

            require $_SERVER['DOCUMENT_ROOT'] . '/passenger/views/OneTime/register.php';
            return;
        }

        header('Content-Type: application/json');

        if ($this->isRegularSession()) {
            echo json_encode([
                'success' => false,
                'code' => 'ALREADY_LOGGED_IN',
                'message' => 'You are already logged in. Please board using the homepage scanner.',
            ]);
            return;
        }

        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);

        if (!is_array($input)) {
            echo json_encode([
                'success' => false,
                'code' => 'INVALID_JSON',
                'message' => 'Invalid request. Please try again.',
            ]);
            return;
        }

        // The registration must belong to the bus scanned in this session, so
        // the public endpoint cannot be used to board an arbitrary bus. An
        // existing one-time session is allowed through so a retried submission
        // still reaches the idempotent booking path.
        $sessionBus = trim((string) ($_SESSION['qr_bus'] ?? ''));
        $hasOneTimeSession = !empty($_SESSION['is_one_time']) && !empty($_SESSION['passenger_id']);
        if (
            !$hasOneTimeSession
            && ($sessionBus === '' || strcasecmp($sessionBus, (string) ($input['bus_no'] ?? '')) !== 0)
        ) {
            echo json_encode([
                'success' => false,
                'code' => 'SCAN_REQUIRED',
                'message' => 'Please scan the Bus QR again to continue.',
            ]);
            return;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            $result = OneTimeService::registerBooking($conn, $input, $this->boardingLock());
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ];
        }

        $result = $this->applySession($result);
        $this->notifyBoarding($result);

        echo json_encode($result);
    }

    /**
     * Best-effort boarding notification for the crew (the existing driver
     * manual-add pushes to inspectors). Short and detailed: who, where to
     * where, and how much/how they will pay. Never blocks the boarding.
     */
    private function notifyBoarding(array $result): void
    {
        if (empty($result['success'])) {
            return;
        }

        $helper = $_SERVER['DOCUMENT_ROOT'] . '/mobile/inspector/push_helper.php';
        $dbConfig = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

        // The push helper needs its own config; skip silently when absent.
        if (!is_file($helper) || !is_file($dbConfig)) {
            return;
        }

        try {
            require_once $helper;

            if (!function_exists('sendPushToAllInspectors')) {
                return;
            }

            $data = $result['data'] ?? [];

            $fare = isset($data['fare']) ? '₱' . number_format((float) $data['fare'], 2) : '';
            $method = strtoupper((string) ($data['payment_method'] ?? ''));

            $body = ($data['passenger_name'] ?? 'Passenger')
                . ' (' . ($data['discount_type'] ?? 'Regular') . ')'
                . ' • ' . ($data['boarding_point'] ?? '—') . ' → ' . ($data['destination'] ?? '—')
                . ' • ' . trim($fare . ' ' . $method);

            sendPushToAllInspectors(
                'New One-Time Passenger',
                $body,
                [
                    'type' => 'one_time_passenger',
                    'trip_id' => $data['trip_no'] ?? null,
                    'passenger_trip_id' => $data['trip_id'] ?? null,
                    'bus_id' => $data['bus_no'] ?? null,
                    'payment_method' => $data['payment_method'] ?? null,
                ],
                (string) ($data['bus_no'] ?? '')
            );
        } catch (\Throwable $e) {
            // Notification is a bonus; the boarding already succeeded.
        }
    }

    /**
     * GET/POST ?route=one-time/continuation — missed-destination state for the
     * one-time passenger's active journey (session or receipt-token recovery).
     */
    public function continuation()
    {
        header('Content-Type: application/json');

        $input = [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $passengerId = $this->continuationPassengerId($input);
        if ($passengerId <= 0) {
            echo json_encode([
                'success' => false,
                'code' => 'NOT_AUTHORIZED',
                'message' => 'Your session expired. Please scan the Bus QR again.',
            ]);
            return;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            try {
                ensureTripFlowSchema($conn);
            } catch (\Throwable $e) {
                // Additive schema; the service degrades on its own.
            }

            $trip = ContinuationJourneyService::activeTripForPassenger($conn, $passengerId);
            if (!$trip) {
                echo json_encode([
                    'success' => false,
                    'code' => 'NO_ACTIVE_TRIP',
                    'message' => 'No in-progress trip found.',
                ]);
                return;
            }

            echo json_encode(ContinuationJourneyService::statusPayload($conn, $trip));
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ]);
        }
    }

    /**
     * POST ?route=one-time/continue — create the continuation journey for a
     * missed destination. Idempotent through the shared service.
     */
    public function continueJourney()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);

        if (!is_array($input)) {
            echo json_encode([
                'success' => false,
                'code' => 'INVALID_JSON',
                'message' => 'Invalid request. Please try again.',
            ]);
            return;
        }

        $passengerId = $this->continuationPassengerId($input);
        if ($passengerId <= 0) {
            echo json_encode([
                'success' => false,
                'code' => 'NOT_AUTHORIZED',
                'message' => 'Your session expired. Please scan the Bus QR again.',
            ]);
            return;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            try {
                ensureTripFlowSchema($conn);
            } catch (\Throwable $e) {
                // Additive schema; the service degrades on its own.
            }

            $trip = ContinuationJourneyService::activeTripForPassenger($conn, $passengerId);
            if (!$trip) {
                echo json_encode([
                    'success' => false,
                    'code' => 'NO_ACTIVE_TRIP',
                    'message' => 'No in-progress trip found.',
                ]);
                return;
            }

            if (empty($trip['destination_passed_alerted'])) {
                echo json_encode([
                    'success' => false,
                    'code' => 'NO_MISSED_DESTINATION',
                    'message' => 'Your destination has not been passed, so there is nothing to continue.',
                ]);
                return;
            }

            $result = ContinuationJourneyService::createContinuation(
                $conn,
                $trip,
                (int) ($input['new_destination_id'] ?? 0),
                (string) ($input['continuation_token'] ?? '')
            );

            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ]);
        }
    }

    /**
     * The one-time passenger id from the lightweight session, recovered from
     * the receipt trip id + submission token when the session is gone (the
     * same recovery the payment-method endpoint uses).
     */
    private function continuationPassengerId(array $input = [])
    {
        if (!empty($_SESSION['is_one_time']) && !empty($_SESSION['passenger_id'])) {
            return (int) $_SESSION['passenger_id'];
        }

        $tripId = (int) ($input['trip_id'] ?? ($_GET['trip_id'] ?? 0));
        $token = trim((string) ($input['token'] ?? ($_GET['token'] ?? '')));

        if ($tripId <= 0 || $token === '') {
            return 0;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            $recovered = OneTimeService::tripBySubmissionToken($conn, $tripId, $token);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!$recovered || empty($recovered['passenger_id'])) {
            return 0;
        }

        $_SESSION['passenger_id'] = (int) $recovered['passenger_id'];
        $_SESSION['passenger_name'] = (string) ($recovered['passenger_name'] ?? '');
        $_SESSION['is_one_time'] = true;

        return (int) $recovered['passenger_id'];
    }

    /**
     * POST ?route=one-time/fare — fare quote (base + passenger-type discount)
     * using the same engine as the booking.
     */
    public function fare()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);

        if (!is_array($input)) {
            echo json_encode([
                'success' => false,
                'code' => 'INVALID_JSON',
                'message' => 'Invalid request. Please try again.',
            ]);
            return;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);
            $result = OneTimeService::quoteFare($conn, $input);
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ];
        }

        echo json_encode($result);
    }

    /**
     * POST ?route=one-time/payment-method — switch the receipt's payment
     * method (cash <-> gcash) while the one-time trip is unpaid.
     */
    public function paymentMethod()
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $input = json_decode((string) $raw, true);

        if (!is_array($input)) {
            echo json_encode([
                'success' => false,
                'code' => 'INVALID_JSON',
                'message' => 'Invalid request. Please try again.',
            ]);
            return;
        }

        try {
            $conn = Database::get();
            ensureOneTimeSchema($conn);

            // Recover the one-time receipt when the lightweight session is gone
            // (same trip id + the client's submission token).
            if (empty($_SESSION['is_one_time']) || empty($_SESSION['passenger_id'])) {
                $recovered = OneTimeService::tripBySubmissionToken(
                    $conn,
                    (int) ($input['trip_id'] ?? 0),
                    (string) ($input['token'] ?? '')
                );

                if (!$recovered || empty($recovered['passenger_id'])) {
                    echo json_encode([
                        'success' => false,
                        'code' => 'NOT_AUTHORIZED',
                        'message' => 'Your session expired. Please scan the Bus QR again.',
                    ]);
                    return;
                }

                $_SESSION['passenger_id'] = (int) $recovered['passenger_id'];
                $_SESSION['passenger_name'] = (string) ($recovered['passenger_name'] ?? '');
                $_SESSION['is_one_time'] = true;
            }

            $result = OneTimeService::updatePaymentMethod(
                $conn,
                (int) $_SESSION['passenger_id'],
                (int) ($input['trip_id'] ?? 0),
                (string) ($input['payment_method'] ?? '')
            );
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ];
        }

        echo json_encode($result);
    }

    /**
     * The one-time pages change with the code and must not be served from the
     * browser/PWA cache, or a stale layout keeps showing after an update.
     */
    private function sendNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * A logged-in passenger with a real account keeps the existing homepage
     * experience; only anonymous or one-time sessions use this flow.
     */
    private function isRegularSession(): bool
    {
        return isset($_SESSION['passenger_id']) && empty($_SESSION['is_one_time']);
    }

    /**
     * Route the login-free One-Time entry surfaces with the shared session
     * classification: only a positively usable regular session keeps the
     * homepage shortcut, a restricted account keeps its fence, and an invalid
     * session is demoted to a visitor with the scanned bus/trip context
     * preserved. Unverifiable states are served as visitors for this request.
     */
    private function applyEntrySessionRouting(): void
    {
        $entryState = (string) (passenger_entry_session_state()['state'] ?? 'visitor');

        if ($entryState === 'regular') {
            header('Location: /passenger/home');
            exit;
        }

        if ($entryState === 'restricted') {
            header('Location: /passenger/account/restriction');
            exit;
        }

        if ($entryState === 'invalid') {
            passenger_clear_entry_identity();
        }
    }

    /**
     * The bus identifier from the scanned QR. The value validated and stored
     * by scan.php is authoritative: a URL/body hint must never override the QR
     * the passenger actually scanned.
     */
    private function scannedBus(): string
    {
        $sessionBus = trim((string) ($_SESSION['qr_bus'] ?? ''));
        if ($sessionBus !== '') {
            return $sessionBus;
        }

        $bus = '';

        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['bus'])) {
                $bus = (string) $decoded['bus'];
            }
        }

        if ($bus === '') {
            $bus = (string) ($_GET['bus'] ?? '');
        }

        return trim($bus);
    }

    /**
     * The boarding point registered when the passenger entered the One-Time
     * flow. It stays fixed for the session: refreshes and live bus movement
     * cannot change it. A new QR scan replaces it.
     *
     * @return array<string,mixed>|null
     */
    private function boardingLock(): ?array
    {
        $lock = $_SESSION['ot_boarding_lock'] ?? null;

        return is_array($lock) ? $lock : null;
    }

    /**
     * Register the resolved boarding point in the session so it survives
     * refreshes and later trip-info calls.
     *
     * @param array<string,mixed> $data tripInfo() payload data
     */
    private function storeBoardingLock(string $bus, array $data): void
    {
        $boarding = $data['boarding'] ?? null;
        if (!is_array($boarding) || (int) ($boarding['id'] ?? 0) <= 0) {
            return;
        }

        $position = is_array($data['bus_position'] ?? null) ? $data['bus_position'] : [];

        $_SESSION['ot_boarding_lock'] = [
            'bus' => $bus,
            'trip_id' => (int) ($data['trip']['id'] ?? 0),
            'boarding_id' => (int) $boarding['id'],
            'source' => $position['source'] ?? null,
            'freshness' => $position['freshness'] ?? null,
            'latitude' => $position['latitude'] ?? null,
            'longitude' => $position['longitude'] ?? null,
            'recorded_at' => $position['updated_at'] ?? null,
            'locked_at' => time(),
        ];
    }

    /**
     * A bus hint arriving directly in the URL (never through scan.php) is sent
     * through the validating entry point instead of being trusted. That keeps
     * the flow bound to a scanned QR and keeps the bus id out of the address.
     */
    private function redirectUnvalidatedScanHint(): void
    {
        if (!empty($_SESSION['qr_bus'])) {
            return;
        }

        $bus = trim((string) ($_GET['bus'] ?? ''));
        if ($bus === '') {
            return;
        }

        header(
            'Location: /passenger/scan.php?bus=' . rawurlencode($bus)
            . '&trip=' . rawurlencode(trim((string) ($_GET['trip'] ?? '')))
        );
        exit;
    }

    /**
     * Every one-time trip gets the lightweight one-time passenger session so
     * the receipt can use the existing PayMongo endpoints, and so a cash
     * passenger can still switch to GCash (or come back to their receipt)
     * without a full account. No credentials are created.
     */
    private function applySession(array $result): array
    {
        if (empty($result['success'])) {
            return $result;
        }

        $data = $result['data'] ?? [];
        $passengerId = (int) ($data['passenger_id'] ?? 0);

        $_SESSION['passenger_id'] = $passengerId;
        $_SESSION['passenger_name'] = (string) ($data['passenger_name'] ?? '');
        $_SESSION['is_one_time'] = true;
        $_SESSION['current_one_time_trip_id'] = (int) ($data['trip_id'] ?? 0);
        $_SESSION['current_one_time_trip_no'] = (string) ($data['trip_no'] ?? '');
        $result['session_started'] = true;

        if ($passengerId > 0) {
            try {
                @setcookie('ot_passenger_id', (string) $passengerId, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/passenger',
                    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } catch (\Throwable $e) {
            }
        }

        if (!empty($data['bus_no'])) {
            $_SESSION['qr_bus'] = (string) $data['bus_no'];
        }
        if (!empty($data['trip_no'])) {
            $_SESSION['qr_trip'] = (string) $data['trip_no'];
        }

        return $result;
    }
}
