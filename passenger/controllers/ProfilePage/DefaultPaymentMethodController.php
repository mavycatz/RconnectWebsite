<?php

/**
 * Saved default payment method for the logged-in passenger.
 *
 * This is the account-level preference set from Profile > Settings (and by
 * the homepage "Yes, use it next time" confirmation). It only stores the
 * preference; an active trip keeps its own method until the passenger
 * confirms a change on the trip itself.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/database/hardening_schema.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code' => 'NOT_AUTHENTICATED',
        'message' => 'Not authenticated',
    ]);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'code' => 'INVALID_JSON',
        'message' => 'Invalid request.',
    ]);
    exit;
}

$method = strtolower(trim((string) ($data['payment_method'] ?? '')));

if (!in_array($method, ['cash', 'gcash', 'hybrid'], true)) {
    echo json_encode([
        'success' => false,
        'code' => 'INVALID_METHOD',
        'message' => 'Please choose a valid payment method.',
    ]);
    exit;
}

try {
    $conn = Database::get();

    // The column is lazily added; ensure it before writing.
    if (function_exists('passenger_ensure_hardening_schema')) {
        try {
            passenger_ensure_hardening_schema($conn);
        } catch (\Throwable $e) {
            // The UPDATE below reports a clear failure if the column is missing.
        }
    }

    // The homepage locks the payment method once a default exists, so this is
    // the only place to change it. Apply the change to the active unpaid trip
    // too, with the same active-QR safety check used on the trip endpoint.
    $activeTrip = null;
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
        $activeTrip = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $e) {
        $activeTrip = null;
    }

    $tripUpdated = false;

    if ($activeTrip && strtolower((string) ($activeTrip['payment_status'] ?? '')) !== 'paid') {
        $current = strtolower((string) ($activeTrip['payment_method'] ?? ''));

        if ($current !== $method) {
            // A still-active QR Ph code could be paid after switching to cash,
            // which would charge the passenger twice.
            if ($current === 'gcash' && $method === 'cash') {
                $intentId = trim((string) ($activeTrip['paymongo_intent_id'] ?? ''));
                $expiresRaw = trim((string) ($activeTrip['paymongo_qr_expires_at'] ?? ''));
                $expiresTs = $expiresRaw !== '' ? strtotime($expiresRaw) : 0;

                if ($intentId !== '' && $expiresTs > time()) {
                    echo json_encode([
                        'success' => false,
                        'code' => 'ACTIVE_QR',
                        'message' => 'Your QR Ph code is still active. Pay it with your app, or wait for it to expire before switching to cash.',
                    ]);
                    exit;
                }
            }

            $stmt = $conn->prepare("UPDATE passenger_trips SET payment_method = ? WHERE id = ?");
            $stmt->execute([$method, $activeTrip['id']]);
            $tripUpdated = true;
        }
    }

    $stmt = $conn->prepare("UPDATE passengers SET default_payment_method = ? WHERE id = ?");
    $stmt->execute([$method, $passenger_id]);

    echo json_encode([
        'success' => true,
        'payment_method' => $method,
        'trip_updated' => $tripUpdated,
    ]);
} catch (\Throwable $e) {
    error_log(
        'Default payment method update failed for passenger ' . $passenger_id
        . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine()
    );

    echo json_encode([
        'success' => false,
        'code' => 'UPDATE_FAILED',
        'message' => 'We could not update your default payment method right now. Please try again.',
    ]);
}
