<?php

/**
 * TEMPORARY diagnostic endpoint for PayMongo debugging. REMOVE AFTER USE.
 * Reads trip state, tests the server's outbound PayMongo API call, and
 * reports webhook configuration state.
 *
 * ACCESS GATE: requires ?token=<diag_token> from app/config/.env
 * (PAYMONGO_DIAG_TOKEN). When diag_token is empty the endpoint refuses all
 * requests. Never print secret key material in the output.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoReconciler.php';

use App\Core\Database;

function diagOut(array $body): void
{
    echo json_encode($body);
    exit;
}

$diagEnabled = strtolower((string) (getenv('PAYMONGO_DIAG_ENABLED') ?: ($_ENV['PAYMONGO_DIAG_ENABLED'] ?? '')));
if (!in_array($diagEnabled, ['1', 'true', 'yes', 'on'], true)) {
    http_response_code(404);
    diagOut(['error' => 'not_found']);
}

$token = (string) ($_GET['token'] ?? '');
$expected = PaymongoConfig::diagToken();
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    diagOut(['error' => 'unauthorized']);
}

$cfg = PaymongoConfig::all();
$active = $cfg[PaymongoConfig::activeSection()] ?? [];
$out = [
    'configured' => PaymongoConfig::isConfigured(),
    'mode' => PaymongoConfig::modeLabel(),
    'expected_livemode' => PaymongoConfig::expectedLivemode(),
    'config' => [
        'enabled' => PaymongoConfig::isEnabled(),
        'active_section' => PaymongoConfig::activeSection(),
        'public_key_present' => !empty($active['public_key']),
        'secret_key_present' => !empty($active['secret_key']),
        'webhook_secret_present' => !empty($active['webhook_secret']),
        'webhook_url' => PaymongoConfig::webhookUrl(),
        'base_url' => PaymongoConfig::baseUrl(),
        'webhook_signature_ttl' => PaymongoConfig::webhookSignatureTtl(),
        'qrph_expiry_seconds' => PaymongoConfig::qrPhExpirySeconds(),
        'https_request' => PaymongoConfig::isHttpsRequest(),
    ],
];

$tripId = (int) ($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    $out['error'] = 'pass trip_id';
    diagOut($out);
}

try {
    $conn = Database::get();
    ensurePaymongoSchema($conn);
    $stmt = $conn->prepare("
        SELECT id, payment_status, payment_method, fare, cash_amount, dropoff_time,
               paymongo_intent_id, paymongo_payment_id, paymongo_history
        FROM passenger_trips WHERE id = ?
    ");
    $stmt->execute([$tripId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    $out['trip'] = $trip;

    if ($trip) {
        $out['expected_centavos'] = PaymongoReconciler::tripAmountCentavos($trip);
    }

    if ($trip && !empty($trip['paymongo_intent_id'])) {
        try {
            $client = new PaymongoClient();
            $intent = $client->retrievePaymentIntent($trip['paymongo_intent_id']);
            $a = $intent['data']['attributes'] ?? [];
            $out['intent'] = [
                'id' => $trip['paymongo_intent_id'],
                'status' => $a['status'] ?? null,
                'amount' => $a['amount'] ?? null,
                'currency' => $a['currency'] ?? null,
                'livemode' => $a['livemode'] ?? null,
            ];
            $remoteAmount = (int) ($a['amount'] ?? 0);
            $out['would_settle'] = (
                (bool) ($a['livemode'] ?? false) !== PaymongoConfig::expectedLivemode() ? false
                : in_array(strtolower((string) ($a['status'] ?? '')), ['succeeded', 'paid'], true)
                    && $remoteAmount === PaymongoReconciler::tripAmountCentavos($trip)
            );
        } catch (Throwable $e) {
            $out['intent_error'] = get_class($e)
                . (method_exists($e, 'getErrorCode') ? ' [' . $e->getErrorCode() . ']' : '')
                . ': ' . $e->getMessage();
        }
    } else {
        $out['intent_note'] = 'NO paymongo_intent_id stored on this trip';
    }

    // Show every webhook delivery ever received (dedup ledger).
    try {
        $evts = $conn->query("SELECT event_id, event_type, received_at, processed_at FROM paymongo_webhook_events ORDER BY received_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        $out['webhook_events_received'] = $evts;
        $out['webhook_events_count'] = (int) $conn->query("SELECT COUNT(*) FROM paymongo_webhook_events")->fetchColumn();
    } catch (Throwable $e) {
        $out['webhook_events_error'] = $e->getMessage();
    }
} catch (Throwable $e) {
    $out['db_error'] = $e->getMessage();
}

diagOut($out);