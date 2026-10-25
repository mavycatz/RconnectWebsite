<?php

/**
 * PayMongo webhook endpoint (mode-aware: test | live)
 *
 * URL: /passenger/webhooks/paymongo.php
 * Register this URL in the PayMongo Dashboard (Developer Tools -> Webhooks)
 * for BOTH test and live mode, with events: payment.paid, payment.failed,
 * payment_intent.succeeded, payment_intent.awaiting_payment_method,
 * qrph.expired.
 * Store each endpoint's signing secret in app/config/.env under
 * PAYMONGO_TEST_WEBHOOK_SECRET / PAYMONGO_LIVE_WEBHOOK_SECRET.
 *
 * IMPORTANT (PayMongo requirement): every response to a webhook delivery MUST
 * be HTTP 200. PayMongo disables webhook endpoints that return 4xx/5xx, so all
 * outcomes — processed, ignored, duplicate, skipped (livemode), invalid
 * signature, or processing failure — are acknowledged with 200 and the detail
 * is carried in the body + server log instead of the HTTP status code.
 *
 * Security:
 *  - Verifies the Paymongo-Signature header before parsing or touching the
 *    database, using the ACTIVE mode's webhook secret. Supports BOTH the
 *    current tempered format (t=<ts>,v1=<hex>, HMAC-SHA256 of "<ts>.<rawBody>"
 *    with the endpoint secret, freshness window via webhook_signature_ttl)
 *    and the legacy plain-hex format (HMAC-SHA256 of the raw body).
 *    Constant-time comparison.
 *  - Rejects events whose livemode does not match the active mode's expected
 *    livemode (test events when live is active, and vice versa).
 *  - Deduplicates by event id via paymongo_webhook_events.
 *  - Reconciles against the PayMongo API (GET /v1/payment_intents/{id})
 *    and the stored server-computed amount before marking anything paid.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoReconciler.php';

use App\Core\Database;

function pmRespond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($body);
    exit;
}

function pmAck(array $body): void
{
    pmRespond(200, $body);
}

function pmLog(string $message): void
{
    error_log('PayMongo webhook: ' . $message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pmAck(['received' => false, 'reason' => 'method_not_allowed']);
}

if (!PaymongoConfig::isConfigured()) {
    pmLog('configuration missing; event acknowledged but not processed.');
    pmAck(['received' => false, 'reason' => 'not_configured']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    pmAck(['received' => false, 'reason' => 'empty_body']);
}

// ---------------------------------------------------------------
// 1. SIGNATURE VERIFICATION (before parsing anything)
// ---------------------------------------------------------------
$signatureHeader = trim((string) ($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ''));
if ($signatureHeader === '' || !pmVerifySignature($signatureHeader, $rawBody)) {
    pmLog('rejected: invalid_signature (len=' . strlen($signatureHeader) . ')');
    pmAck(['received' => false, 'reason' => 'invalid_signature']);
}

// ---------------------------------------------------------------
// 2. PARSE + BASIC SHAPE CHECKS
// ---------------------------------------------------------------
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    pmAck(['received' => false, 'reason' => 'invalid_json']);
}

$eventId = (string) ($payload['data']['id'] ?? '');
$type = (string) ($payload['data']['attributes']['type'] ?? '');
$livemode = (bool) ($payload['data']['attributes']['livemode'] ?? null);
$resource = $payload['data']['attributes']['data'] ?? [];

if ($eventId === '' || $type === '') {
    pmAck(['received' => false, 'reason' => 'invalid_event']);
}

// ---------------------------------------------------------------
// 3. LIVEMODE GUARD — process only events matching the active mode
// ---------------------------------------------------------------
if ((bool) $livemode !== PaymongoConfig::expectedLivemode()) {
    pmAck(['received' => true, 'skipped' => 'livemode_mismatch']);
}

// ---------------------------------------------------------------
// 4. EVENT ALLOWLIST — acknowledge everything, process only known types
// ---------------------------------------------------------------
$handledTypes = [
    'payment.paid',
    'payment.failed',
    'payment_intent.succeeded',
    'payment_intent.awaiting_payment_method',
    'qrph.expired',
];

if (!in_array($type, $handledTypes, true)) {
    pmAck(['received' => true, 'ignored' => $type]);
}

$conn = Database::get();
ensurePaymongoSchema($conn);

// ---------------------------------------------------------------
// 5. DUPLICATE-EVENT PROTECTION (event id dedup)
// ---------------------------------------------------------------
try {
    $stmt = $conn->prepare("INSERT INTO paymongo_webhook_events (event_id, event_type, payload, received_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$eventId, $type, $rawBody]);
} catch (\PDOException $e) {
    // Duplicate primary key => already processed or in-flight.
    pmAck(['received' => true, 'duplicate' => $eventId]);
}

try {
    $outcome = pmProcessEvent($conn, $type, $resource);
    $conn->prepare("UPDATE paymongo_webhook_events SET processed_at = NOW() WHERE event_id = ?")->execute([$eventId]);
} catch (\Throwable $e) {
    // Acknowledge with 200 (PayMongo disables endpoints on non-2xx); keep the
    // ledger row with processed_at NULL so the failure stays visible in the
    // diagnostics, and log the reason. Settled-but-unmarked payments remain
    // recoverable via the passenger reconcile path.
    pmLog('processing failed for ' . $eventId . ' (' . $type . '): ' . $e->getMessage());
    $outcome = 'processing_failed';
}

pmAck(['received' => true, 'processed' => $type, 'outcome' => $outcome]);

// ---------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------

/**
 * Verify the Paymongo-Signature header.
 *
 * Current PayMongo format: "t=<timestamp>,v1=<hex>" where hex is
 * HMAC-SHA256(secret, "<timestamp>.<rawBody>") and the timestamp must be
 * within the configured freshness window. Legacy format: plain hex of
 * HMAC-SHA256(secret, rawBody). Constant-time comparison in both paths.
 */
function pmVerifySignature(string $signatureHeader, string $rawBody): bool
{
    $secret = PaymongoConfig::webhookSecret();
    if ($secret === '') {
        return false;
    }

    // Tempered format: look for t=<ts> and v1=<hex> fields.
    $timestamp = null;
    $digest = null;
    foreach (explode(',', $signatureHeader) as $part) {
        $part = trim($part);
        if (strpos($part, 't=') === 0) {
            $timestamp = substr($part, 2);
        } elseif (strpos($part, 'v1=') === 0) {
            $digest = substr($part, 3);
        }
    }

    if ($timestamp !== null && $digest !== null && ctype_digit($timestamp)) {
        $ttl = PaymongoConfig::webhookSignatureTtl();
        if (abs(time() - (int) $timestamp) > $ttl) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        return hash_equals($expected, $digest);
    }

    // Legacy format: plain hex HMAC of the raw body.
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signatureHeader);
}

/**
 * @return string outcome label used in the response
 */
function pmProcessEvent(\PDO $conn, string $type, array $resource): string
{
    $intentId = pmIntentIdFromEvent($type, $resource);
    if ($intentId === '') {
        return 'no_intent';
    }

    $stmt = $conn->prepare("
        SELECT id, passenger_id, payment_method, payment_status,
               fare, cash_amount, gcash_amount, verified_cash_amount, identification_source,
               paymongo_intent_id, paymongo_payment_id
        FROM passenger_trips
        WHERE paymongo_intent_id = ?
        LIMIT 1
    ");
    $stmt->execute([$intentId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        return 'unknown_trip';
    }

    if ($type === 'payment_intent.awaiting_payment_method') {
        // Passenger abandoned/cancelled before authorizing.
        pmReject($conn, $trip['id'], 'paymongo_cancelled', 'cancelled: ' . $type);
        return 'cancelled';
    }

    if ($type === 'payment.failed') {
        pmReject($conn, $trip['id'], 'paymongo_failed', 'failed: ' . $type);
        return 'failed';
    }

    if ($type === 'qrph.expired') {
        // QR code was not scanned within its expiry window. Do NOT settle or
        // reject: note the expiry so the UI offers a fresh code. The trip
        // stays payable (pending) for the passenger to retry.
        $stmt = $conn->prepare("
            UPDATE passenger_trips
            SET paymongo_history = ?
            WHERE id = ?
        ");
        $stmt->execute(['expired: ' . $type, $trip['id']]);
        return 'expired';
    }

    // payment.paid / payment_intent.succeeded — reconcile before trusting.
    return PaymongoReconciler::settleIfSettled($conn, $trip, $intentId, 'paymongo-webhook');
}

function pmIntentIdFromEvent(string $type, array $resource): string
{
    if ($type === 'payment_intent.succeeded' || $type === 'payment_intent.awaiting_payment_method') {
        return (string) ($resource['id'] ?? '');
    }
    // payment.paid / payment.failed / qrph.expired carry payment_intent_id
    // in the resource attributes; fall back to the resource id itself.
    return (string) (
        $resource['attributes']['payment_intent_id']
        ?? $resource['id']
        ?? ''
    );
}

/**
 * Mark a payment as not-paid (failed/cancelled) with an audit trail so it
 * leaves the finance pending queue and can be retried by the passenger.
 */
function pmReject(\PDO $conn, int $tripId, string $reason, string $note): void
{
    $stmt = $conn->prepare("
        UPDATE passenger_trips
        SET payment_status = 'rejected',
            rejection_reason = ?,
            paymongo_history = ?,
            verified_at = NOW(),
            verified_by = 'paymongo-webhook'
        WHERE id = ?
    ");
    $stmt->execute([$reason, $note, $tripId]);
}
