<?php

/**
 * PaymongoReconciler
 *
 * Single source of truth for turning a PayMongo payment intent into a trip's
 * payment state. Used by BOTH the webhook endpoint (passenger/webhooks/paymongo.php)
 * and the passenger-triggered reconcile action (paymongo/reconcile) so the
 * two paths can never drift.
 *
*  Guards applied before marking paid (mirrors the webhook spec):
 *   - livemode must match the active mode (false for test mode, true for live)
 *   - remote intent status must be settled (succeeded / paid)
 *   - settled amount must equal the server-computed amount for the trip
 *
 * Nothing is marked paid based on client-supplied data; the payment intent
 * is always re-fetched from the PayMongo API.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/RegularPartyService.php';

class PaymongoReconciler
{
    /**
     * Attempt to settle the trip from the remote intent state.
     *
     * @param \PDO   $conn     Database connection.
     * @param array  $trip     passenger_trips row (must include payment_status,
     *                         payment_method, fare, cash_amount, id, paymongo_intent_id).
     * @param string $intentId PayMongo payment intent id (pi_...).
     * @param string $source   verifier label stored in verified_by.
     * @return string outcome label: paid | already_paid | livemode_mismatch |
     *                 not_settled | amount_mismatch | unknown_trip
     */
    public static function settleIfSettled(\PDO $conn, array $trip, string $intentId, string $source = 'paymongo-webhook'): string
    {
        if ($intentId === '') {
            return 'no_intent';
        }

        if (strtolower((string) ($trip['payment_status'] ?? '')) === 'paid') {
            return 'already_paid';
        }

        $client = new PaymongoClient();
        $intent = $client->retrievePaymentIntent($intentId);
        $attrs = $intent['data']['attributes'] ?? [];

        if ((bool) ($attrs['livemode'] ?? false) !== PaymongoConfig::expectedLivemode()) {
            return 'livemode_mismatch';
        }

        $remoteStatus = strtolower((string) ($attrs['status'] ?? ''));
        if (!in_array($remoteStatus, ['succeeded', 'paid'], true)) {
            // Not actually settled yet — record and leave unpaid.
            self::updateHistory($conn, $trip['id'], 'pending: remote_status=' . $remoteStatus);
            return 'not_settled';
        }

        $remoteAmount = (int) ($attrs['amount'] ?? 0);
        $expectedAmount = self::tripAmountCentavosParty($conn, $trip);
        if ($remoteAmount !== $expectedAmount) {
            self::updateHistory($conn, $trip['id'], 'amount_mismatch: remote=' . $remoteAmount . ' expected=' . $expectedAmount);
            return 'amount_mismatch';
        }

        $paymentId = self::paymentIdFromIntent($attrs);

        $set = "payment_status = 'paid', paymongo_history = 'paid', verified_at = NOW(), verified_by = :verified_by";
        $params = [':id' => $trip['id'], ':verified_by' => $source];

        if ($paymentId !== null && $paymentId !== '') {
            $set .= ', paymongo_payment_id = :payment_id';
            $params[':payment_id'] = $paymentId;
        }

        $stmt = $conn->prepare("UPDATE passenger_trips SET " . $set . " WHERE id = :id");
        $stmt->execute($params);

        // Companions travel on one payment with the primary passenger: their
        // rides settle together with the primary's online payment.
        if (strtolower((string) ($trip['identification_source'] ?? '')) === 'one_time') {
            CompanionService::markPartyPaid($conn, (int) $trip['id'], $source);
        } else {
            RegularPartyService::markTravellersPaid($conn, (int) $trip['id'], $source);
        }

        return 'paid';
    }

    /**
     * Server-authoritative amount for the whole travelling party (primary trip
     * + its companions). Mirrors the same GCash/hybrid rule but sums every
     * still-unpaid companion share so a single QR Ph checkout covers the party.
     */
    public static function tripAmountCentavosParty(\PDO $conn, array $trip): int
    {
        if (strtolower((string) ($trip['identification_source'] ?? '')) !== 'one_time') {
            $quote = RegularPartyService::storedQuote($conn, $trip,
                RegularPartyService::travellerRows($conn, (int) $trip['id']));
            return (int) ($quote['online_amount_centavos'] ?? 0);
        }
        return CompanionService::partyOnlineAmountCentavos($conn, $trip);
    }

    /**
     * Server-authoritative amount for the trip (same rule as the checkout
     * controller): GCash = final fare; Hybrid = fare - cash portion (clamped).
     */
    public static function tripAmountCentavos(array $trip): int
    {
        $fare = (float) ($trip['fare'] ?? 0);
        $method = strtolower((string) ($trip['payment_method'] ?? ''));

        if ($method === 'hybrid') {
            $cashPortion = (float) ($trip['cash_amount'] ?? 0);
            if ($cashPortion < 0) {
                $cashPortion = 0;
            }
            if ($cashPortion > $fare) {
                $cashPortion = $fare;
            }
            $amount = $fare - $cashPortion;
        } else {
            $amount = $fare;
        }

        return (int) round($amount * 100);
    }

    /**
     * Write the latest outcome note to the trip's paymongo_history.
     */
    public static function updateHistory(\PDO $conn, int $tripId, string $note): void
    {
        $stmt = $conn->prepare("UPDATE passenger_trips SET paymongo_history = ? WHERE id = ?");
        $stmt->execute([$note, $tripId]);
    }

    /**
     * Best-effort extraction of the PayMongo Payment id (pay_...) from the
     * intent attributes. The intent carries its payments under
     * attributes.payments.data[].id (or a legacy attributes.payment_id).
     */
    private static function paymentIdFromIntent(array $attrs): ?string
    {
        $payments = $attrs['payments']['data'] ?? [];
        if (is_array($payments) && !empty($payments[0]['id'])) {
            return (string) $payments[0]['id'];
        }
        $legacy = $attrs['payment_id'] ?? null;
        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }
        return null;
    }
}
