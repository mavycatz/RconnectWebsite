<?php

/*
|--------------------------------------------------------------------------
| DRIVER PAYMENT METHOD CHANGE (drop-off flow)
|
| Updates only the payment_method of one existing passenger_trips record
| owned by the authenticated driver, so a passenger who chose Online/QRPh
| can settle in CASH before drop-off. The payment status is NOT changed
| here: the existing confirm-payment flow records the paid status.
|
| No new records are created and no fare/passenger data is recomputed.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);

    $driverId      = trim($input['driver_id'] ?? '');
    $token         = trim($input['token'] ?? '');
    $recordId      = (int)($input['record_id'] ?? $input['id'] ?? 0);
    $paymentMethod = trim($input['payment_method'] ?? '');
    $tripNo        = trim($input['trip_no'] ?? '');

    require_once __DIR__ . '/lib/driver_auth.php';
    require_driver_auth($conn, $driverId, $token);

    if ($recordId <= 0 || $paymentMethod === '') {
        echo json_encode([
            "success" => false,
            "message" => "record_id and payment_method are required",
        ]);
        exit;
    }

    // Only existing payment methods are accepted; the value is normalized to
    // the canonical casing used by the passenger/payment records.
    $allowedMethods = ['Cash', 'GCash', 'QRPh', 'Online', 'Hybrid', 'Non-Cash', 'Other'];
    $canonicalMethod = null;
    foreach ($allowedMethods as $candidate) {
        if (strcasecmp($candidate, $paymentMethod) === 0) {
            $canonicalMethod = $candidate;
            break;
        }
    }
    if ($canonicalMethod === null) {
        echo json_encode([
            "success" => false,
            "message" => "Unsupported payment_method",
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT id, trip_no, payment_method, payment_status
         FROM passenger_trips
         WHERE id = ? AND driver_id = ?
         LIMIT 1"
    );
    if (!$stmt) throw new Exception("passenger_trips query failed: " . $conn->error);
    $stmt->bind_param("is", $recordId, $driverId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode([
            "success" => false,
            "message" => "Passenger record not found for this driver",
        ]);
        exit;
    }

    if ($tripNo !== '' && (string)$row['trip_no'] !== $tripNo) {
        echo json_encode([
            "success" => false,
            "message" => "Passenger record does not belong to the active trip",
        ]);
        exit;
    }

    $upd = $conn->prepare(
        "UPDATE passenger_trips
         SET payment_method = ?
         WHERE id = ? AND driver_id = ?"
    );
    if (!$upd) throw new Exception("passenger_trips update failed: " . $conn->error);
    $upd->bind_param("sis", $canonicalMethod, $recordId, $driverId);
    if (!$upd->execute()) throw new Exception("passenger_trips update failed: " . $upd->error);
    $upd->close();

    echo json_encode([
        "success" => true,
        "message" => "Payment method updated",
        "id" => $recordId,
        "payment_method" => $canonicalMethod,
        "payment_status" => $row['payment_status'],
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[update_payment_method] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code" => "SERVER_ERROR",
        "message" => "Something went wrong. Please try again.",
    ]);
}
