<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoReconciler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/OneTime/OneTimeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';

use App\Core\Database;

/**
 * CheckoutController
 *
 * paymongo/checkout — creates a PayMongo QR Ph checkout for the passenger's
 * own completed trip (test or live mode per configuration) and returns the
 * scannable Base64 QR code image (no redirect).
 * paymongo/status  — lightweight poll returning the trip's payment status
 *                    and stored PayMongo identifiers.
 * paymongo/reconcile — passenger-triggered safety net that re-fetches the
 *                      PaymentIntent and applies the webhook's guards.
 *
 * The amount is ALWAYS derived server-side from the stored final fare; the
 * client amount is never read. The secret key is never returned. In live mode
 * checkout is refused unless the request arrives over HTTPS.
 */
class CheckoutController
{
    /** @var \PDO */
    private $conn;

    /** @var int */
    private $passengerId;

    public function __construct()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $this->conn = Database::get();
        $this->passengerId = (int) ($_SESSION['passenger_id'] ?? 0);
    }

    /**
     * POST paymongo/checkout  body: { "trip_id": 123 }
     */
    public function checkout(): void
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->fail('Invalid request.');
        }

        $tripId = (int) ($data['trip_id'] ?? 0);
        if ($tripId <= 0) {
            $this->fail('Invalid trip id.');
        }

        $this->recoverOneTimeSession($tripId, (string) ($data['token'] ?? ''));

        if ($this->passengerId <= 0) {
            $this->fail('Not logged in.', 401);
        }

        if (!passenger_rate_limit('paymongo_checkout', $this->passengerId . '|' . passenger_client_ip(), 30, 3600, 0)) {
            $this->fail('Too many payment attempts. Please try again later.');
        }

        if (!PaymongoConfig::canCheckout()) {
            $this->fail('Online payment is temporarily unavailable.');
        }

        if (PaymongoConfig::isLive() && !PaymongoConfig::isHttpsRequest()) {
            $this->fail('Online payment requires a secure connection. Please try again over HTTPS.');
        }

        ensurePaymongoSchema($this->conn);

        $trip = $this->loadOwnedTrip($tripId);
        if ($trip === null) {
            $this->fail('Trip not found.', 404);
        }

        // Regular passengers may pay online as soon as their destination (and
        // therefore the fare) is set, even while the trip is still running.
        // One-time passengers have their destination and fare locked at
        // boarding, so their in-flow receipt screen can generate the QR Ph
        // code immediately.
        $isOneTimeTrip = strtolower((string) ($trip['identification_source'] ?? '')) === 'one_time';
        if ($trip['dropoff_time'] === null && !$isOneTimeTrip) {
            $hasDestination = trim((string) ($trip['destination'] ?? '')) !== '';
            $hasFare = (float) ($trip['fare'] ?? 0) > 0;

            if (!$hasDestination || !$hasFare) {
                $this->fail('Please select your destination before paying online.');
            }
        }

        $method = strtolower((string) ($trip['payment_method'] ?? ''));
        if (!in_array($method, ['gcash', 'hybrid'], true)) {
            $this->fail('This trip does not use a GCash payment.');
        }

        if (strtolower((string) ($trip['payment_status'] ?? 'pending')) === 'paid') {
            $this->fail('This trip is already paid.');
        }

        $amountCentavos = $this->amountCentavos($trip);
        if ($amountCentavos < 100) {
            $this->fail('The online payment amount must be at least ₱1.00.');
        }

        $existingQr = (string) ($trip['paymongo_qr_image'] ?? '');
        $existingExpires = (string) ($trip['paymongo_qr_expires_at'] ?? '');
        if ($existingQr !== '' && $existingExpires !== '' && strtotime($existingExpires) > time()) {
            echo json_encode([
                'success' => true,
                'intent_id' => $trip['paymongo_intent_id'] ?? null,
                'qr_image' => $existingQr,
                'qr_expires_at' => date('c', strtotime($existingExpires)),
                'expiry_seconds' => max(1, strtotime($existingExpires) - time()),
                'reused' => true,
            ]);
            exit;
        }

        try {
            $client = new PaymongoClient();
            $checkout = $client->createQrPhCheckout([
                'amount_centavos' => $amountCentavos,
                'expiry_seconds' => PaymongoConfig::qrPhExpirySeconds(),
                'description' => 'R-CONNECT trip #' . $tripId,
                'metadata' => [
                    'trip_id' => (string) $tripId,
                    'passenger_id' => (string) $this->passengerId,
                ],
                'idempotency_key' => $isOneTimeTrip ? 'rconnect-trip-' . $tripId . '-qrph'
                    : 'rconnect-trip-' . $tripId . '-qrph-' . substr(hash('sha256', $amountCentavos . '|' . ($trip['paymongo_intent_id'] ?? '')), 0, 24),
            ]);
        } catch (PaymongoApiException $e) {
            error_log('PayMongo checkout failed (trip ' . $tripId . '): ' . $e->getErrorCode() . ' - ' . $e->getMessage());
            $this->fail('We could not start the payment. Please try again. (code: ' . $e->getErrorCode() . ')');
        } catch (\Throwable $e) {
            error_log('PayMongo checkout unexpected error (trip ' . $tripId . '): ' . $e->getMessage());
            $this->fail('We could not start the payment. Please try again. (unexpected error)');
        }

        // Stored as a plain DATETIME (server timezone); the JSON response carries an
// ISO-8601 timestamp so the browser countdown parses it reliably.
$storedExpiresAt = date('Y-m-d H:i:s', time() + $checkout['expiry_seconds']);
$qrExpiresAt = date('c', time() + $checkout['expiry_seconds']);

        $stmt = $this->conn->prepare("
            UPDATE passenger_trips
            SET paymongo_intent_id = ?,
                paymongo_client_key = ?,
                paymongo_qr_image = ?,
                paymongo_qr_expires_at = ?,
                paymongo_history = ?
            WHERE id = ? AND passenger_id = ?
              AND (paymongo_qr_expires_at IS NULL OR paymongo_qr_expires_at <= NOW())
        ");
        $stmt->execute([
            $checkout['intent_id'],
            $checkout['client_key'],
            $checkout['qr_image'],
            $storedExpiresAt,
            'pending: qr_code_generated',
            $tripId,
            $this->passengerId,
        ]);

        if ($stmt->rowCount() === 0) {
            $fresh = $this->loadOwnedTrip($tripId);
            $freshQr = (string) ($fresh['paymongo_qr_image'] ?? '');
            $freshExpires = (string) ($fresh['paymongo_qr_expires_at'] ?? '');
            if ($freshQr !== '' && $freshExpires !== '' && strtotime($freshExpires) > time()) {
                echo json_encode([
                    'success' => true,
                    'intent_id' => $fresh['paymongo_intent_id'] ?? null,
                    'qr_image' => $freshQr,
                    'qr_expires_at' => date('c', strtotime($freshExpires)),
                    'expiry_seconds' => max(1, strtotime($freshExpires) - time()),
                    'reused' => true,
                ]);
                exit;
            }
            $this->fail('We could not start the payment. Please try again.');
        }

        echo json_encode([
            'success' => true,
            'intent_id' => $checkout['intent_id'],
            'qr_image' => $checkout['qr_image'],
            'qr_expires_at' => $qrExpiresAt,
            'expiry_seconds' => $checkout['expiry_seconds'],
        ]);
        exit;
    }

    /**
     * POST paymongo/reconcile  body: { "trip_id": 123 }
     *
     * Safety net for payments that settled at PayMongo but whose webhook
     * event never reached us (lost delivery, disabled endpoint, etc.).
     * Re-fetches the payment intent from the PayMongo API and applies the
     * exact same guards as the webhook; never trusts client data.
     */
    public function reconcile(): void
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->fail('Invalid request.');
        }

        $tripId = (int) ($data['trip_id'] ?? 0);
        if ($tripId <= 0) {
            $this->fail('Invalid trip id.');
        }

        $this->recoverOneTimeSession($tripId, (string) ($data['token'] ?? ''));

        if ($this->passengerId <= 0) {
            $this->fail('Not logged in.', 401);
        }

        if (!passenger_rate_limit('paymongo_checkout', $this->passengerId . '|' . passenger_client_ip(), 30, 3600, 0)) {
            $this->fail('Too many payment attempts. Please try again later.');
        }

        if (!PaymongoConfig::canCheckout()) {
            $this->fail('Online payment is temporarily unavailable.');
        }

        ensurePaymongoSchema($this->conn);

        $trip = $this->loadOwnedTrip($tripId);
        if ($trip === null) {
            $this->fail('Trip not found.', 404);
        }

        if (strtolower((string) ($trip['payment_status'] ?? 'pending')) === 'paid') {
            echo json_encode([
                'success' => true,
                'payment_status' => 'paid',
                'paymongo_history' => $trip['paymongo_history'] ?? null,
                'outcome' => 'already_paid',
            ]);
            exit;
        }

        $intentId = (string) ($trip['paymongo_intent_id'] ?? '');
        if ($intentId === '') {
            echo json_encode([
                'success' => true,
                'payment_status' => (string) ($trip['payment_status'] ?? 'pending'),
                'paymongo_history' => $trip['paymongo_history'] ?? null,
                'outcome' => 'no_intent',
            ]);
            exit;
        }

        try {
            $outcome = PaymongoReconciler::settleIfSettled($this->conn, $trip, $intentId, 'paymongo-reconcile');
        } catch (\Throwable $e) {
            error_log('PayMongo reconcile failed (trip ' . $tripId . '): ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'payment_status' => (string) ($trip['payment_status'] ?? 'pending'),
                'paymongo_history' => $trip['paymongo_history'] ?? null,
                'outcome' => 'reconcile_error',
            ]);
            exit;
        }

        $fresh = $this->loadOwnedTrip($tripId);

        echo json_encode([
            'success' => true,
            'payment_status' => (string) ($fresh['payment_status'] ?? 'pending'),
            'paymongo_history' => $fresh['paymongo_history'] ?? null,
            'outcome' => $outcome,
        ]);
        exit;
    }

    /**
     * GET paymongo/qr-image?trip_id=123&token=...
     *
     * Serves the stored QR Ph image from our own origin so the receipt can
     * compose a branded download on a canvas without cross-origin tainting
     * (PayMongo's image_url is remote, which would block canvas export).
     */
    public function qrImage(): void
    {
        $tripId = (int) ($_GET['trip_id'] ?? 0);
        $token = (string) ($_GET['token'] ?? '');

        $this->recoverOneTimeSession($tripId, $token);

        if ($this->passengerId <= 0 || $tripId <= 0) {
            $this->fail('Not logged in.', 401);
        }

        $trip = $this->loadOwnedTrip($tripId);
        if ($trip === null) {
            $this->fail('Trip not found.', 404);
        }

        $image = trim((string) ($trip['paymongo_qr_image'] ?? ''));
        if ($image === '') {
            $this->fail('No QR code for this trip.', 404);
        }

        $bytes = null;
        $mime = 'image/png';

        if (strpos($image, 'data:') === 0) {
            if (preg_match('#^data:([^;,]+)(;base64)?,(.*)$#s', $image, $match) === 1) {
                $mime = $match[1] !== '' ? $match[1] : $mime;
                $bytes = !empty($match[2]) ? base64_decode($match[3], true) : rawurldecode($match[3]);
            }
        } elseif (filter_var($image, FILTER_VALIDATE_URL)) {
            $bytes = $this->fetchRemoteImage($image, $mime);
        }

        if (!is_string($bytes) || $bytes === '') {
            $this->fail('Could not load the QR code.', 404);
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=300');
        echo $bytes;
        exit;
    }

    /**
     * Fetch a remote image server-side with a short timeout.
     */
    private function fetchRemoteImage(string $url, string &$mime): ?string
    {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) {
                    if ($type !== '') {
                        $mime = $type;
                    }
                    return $body;
                }
            }

            $context = stream_context_create([
                'http' => ['timeout' => 8],
                'https' => ['timeout' => 8],
            ]);
            $body = @file_get_contents($url, false, $context);

            return is_string($body) && $body !== '' ? $body : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * GET paymongo/status?trip_id=123
     */
    public function status(): void
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->fail('Invalid request.');
        }

        $tripId = (int) ($data['trip_id'] ?? 0);
        if ($tripId <= 0) {
            $this->fail('Invalid trip id.');
        }

        $this->recoverOneTimeSession($tripId, (string) ($data['token'] ?? ''));

        if ($this->passengerId <= 0) {
            $this->fail('Not logged in.', 401);
        }

        $trip = $this->loadOwnedTrip($tripId);
        if ($trip === null) {
            $this->fail('Trip not found.', 404);
        }

        echo json_encode([
            'success' => true,
            'payment_status' => (string) ($trip['payment_status'] ?? 'pending'),
            'paymongo_intent_id' => $trip['paymongo_intent_id'] ?? null,
            'paymongo_payment_id' => $trip['paymongo_payment_id'] ?? null,
            'paymongo_history' => $trip['paymongo_history'] ?? null,
        ]);
        exit;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Recover a one-time receipt when the lightweight session was lost: the
     * trip id must match the client's submission token.
     */
    private function recoverOneTimeSession(int $tripId, string $token): void
    {
        if ($this->passengerId > 0) {
            return;
        }

        $token = trim($token);
        if ($tripId <= 0 || $token === '') {
            return;
        }

        $row = OneTimeService::tripBySubmissionToken($this->conn, $tripId, $token);
        if (!$row || empty($row['passenger_id'])) {
            return;
        }

        $this->passengerId = (int) $row['passenger_id'];
        $_SESSION['passenger_id'] = $this->passengerId;
        $_SESSION['passenger_name'] = (string) ($row['passenger_name'] ?? '');
        $_SESSION['is_one_time'] = true;
    }

    /**
     * Load the trip only when it belongs to the logged-in passenger.
     */
    private function loadOwnedTrip(int $tripId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, passenger_id, payment_method, payment_status,
                   fare, cash_amount, gcash_amount, verified_cash_amount, dropoff_time,
                   destination,
                   identification_source,
                   paymongo_intent_id, paymongo_payment_id, paymongo_history,
                   paymongo_qr_image, paymongo_qr_expires_at
            FROM passenger_trips
            WHERE id = ? AND passenger_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tripId, $this->passengerId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
        return $trip ?: null;
    }

    /**
     * Server-authoritative amount in centavos for the online balance.
     * GCash/hybrid trips qualify for QR Ph checkout: full fare, or fare minus
     * the cash portion (clamped) for hybrid. Companion shares are included so
     * a single QR Ph code covers the whole travelling party.
     */
    private function amountCentavos(array $trip): int
    {
        if (strtolower((string) ($trip['identification_source'] ?? '')) === 'one_time') {
            return CompanionService::partyOnlineAmountCentavos($this->conn, $trip);
        }
        $quote = RegularPartyService::storedQuote($this->conn, $trip,
            RegularPartyService::travellerRows($this->conn, (int) $trip['id']));
        if (!$quote['quote_available']) {
            $this->fail('The travelling-party fare is not ready. Please refresh your trip before paying.');
        }
        return (int) $quote['online_amount_centavos'];
    }

    private function fail(string $message, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
