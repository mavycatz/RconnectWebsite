<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$method = strtolower(trim((string) ($data['payment_method'] ?? '')));

if (!in_array($method, ['cash', 'gcash', 'hybrid'], true)) {
    echo json_encode([
        'success' => false,
        'code' => 'INVALID_METHOD',
        'message' => 'Please choose a valid payment method.'
    ]);
    exit;
}

// The passenger chose "Yes, use it next time" in the confirmation prompt.
$saveAsDefault = !empty($data['save_as_default']);

try {
    $conn = Database::get();

    ensureTripFlowSchema($conn);

    // The saved-default column is lazily added; ensure it before writing.
    require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/database/hardening_schema.php';
    if (function_exists('passenger_ensure_hardening_schema')) {
        try {
            passenger_ensure_hardening_schema($conn);
        } catch (\Throwable $e) {
            // Degrade to per-trip updates when the column cannot be added.
        }
    }

    $saveDefaultMethod = static function ($method) use ($conn, $passenger_id) {
        try {
            $stmt = $conn->prepare("UPDATE passengers SET default_payment_method = ? WHERE id = ?");
            $stmt->execute([$method, $passenger_id]);
            return true;
        } catch (\Throwable $e) {
            error_log('Default payment method save failed for passenger ' . $passenger_id . ': ' . $e->getMessage());
            return false;
        }
    };

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

    // No active trip: the selection is kept for the next boarding, and a
    // "use it next time" choice is saved on the passenger account.
    if (!$activeTrip) {
        $savedDefault = $saveAsDefault ? $saveDefaultMethod($method) : false;

        echo json_encode([
            'success' => true,
            'changed' => false,
            'no_active_trip' => true,
            'payment_method' => $method,
            'saved_default' => $savedDefault,
        ]);
        exit;
    }

    if (strtolower((string) ($activeTrip['payment_status'] ?? '')) === 'paid') {
        echo json_encode([
            'success' => false,
            'code' => 'ALREADY_PAID',
            'message' => 'This trip is already paid. The payment method can no longer be changed.'
        ]);
        exit;
    }

    $current = strtolower((string) ($activeTrip['payment_method'] ?? ''));

    if ($current === $method) {
        $savedDefault = $saveAsDefault ? $saveDefaultMethod($method) : false;

        echo json_encode([
            'success' => true,
            'changed' => false,
            'payment_method' => $method,
            'saved_default' => $savedDefault,
        ]);
        exit;
    }

    // A still-active QR Ph code could be paid after switching to cash, which
    // would charge the passenger twice.
    if ($current === 'gcash' && $method === 'cash') {
        $intentId = trim((string) ($activeTrip['paymongo_intent_id'] ?? ''));
        $expiresRaw = trim((string) ($activeTrip['paymongo_qr_expires_at'] ?? ''));
        $expiresTs = $expiresRaw !== '' ? strtotime($expiresRaw) : 0;

        if ($intentId !== '' && $expiresTs > time()) {
            echo json_encode([
                'success' => false,
                'code' => 'ACTIVE_QR',
                'message' => 'Your QR Ph code is still active. Pay it with your app, or wait for it to expire before switching to cash.'
            ]);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE passenger_trips SET payment_method = ? WHERE id = ?");
    $stmt->execute([$method, $activeTrip['id']]);

    $savedDefault = $saveAsDefault ? $saveDefaultMethod($method) : false;

    echo json_encode([
        'success' => true,
        'changed' => true,
        'payment_method' => $method,
        'saved_default' => $savedDefault,
    ]);
} catch (\Throwable $e) {
    error_log(
        'Payment method update failed for passenger ' . $passenger_id
        . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine()
    );

    echo json_encode([
        'success' => false,
        'code' => 'UPDATE_FAILED',
        'message' => 'Unable to update your payment method right now. Please try again.'
    ]);
}
