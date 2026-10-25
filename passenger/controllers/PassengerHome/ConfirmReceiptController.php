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

$tripId = (int) ($data['trip_id'] ?? 0);

if ($tripId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trip ID.']);
    exit;
}

$conn = Database::get();

ensureTripFlowSchema($conn);

$stmt = $conn->prepare("
    SELECT id, trip_no, payment_status, dropoff_time, trip_status
    FROM passenger_trips
    WHERE id = ? AND passenger_id = ?
    LIMIT 1
");
$stmt->execute([$tripId, $passenger_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    echo json_encode(['success' => false, 'message' => 'Trip not found.']);
    exit;
}

// The passenger may pay online while the trip is still running, so a receipt
// confirmation must not end an in-progress trip.
$busTripFinished = false;
if (!empty($trip['trip_no'])) {
    try {
        $stmt = $conn->prepare("SELECT status FROM bus_trips WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $trip['trip_no']]);
        $busStatus = strtolower(trim((string) $stmt->fetchColumn()));
        $busTripFinished = in_array($busStatus, ['completed', 'cancelled'], true);
    } catch (\PDOException $e) {
        $busTripFinished = false;
    }
}

$tripFinished = (
    $trip['dropoff_time'] !== null ||
    in_array(strtolower((string)($trip['trip_status'] ?? '')), ['completed', 'cancelled'], true) ||
    $busTripFinished
);

if (!$tripFinished) {
    echo json_encode([
        'success' => true,
        'completed' => false,
        'code' => 'TRIP_IN_PROGRESS',
        'message' => 'Trip is still in progress.'
    ]);
    exit;
}

// Only a paid trip can be closed by the passenger's receipt confirmation.
// An unpaid trip stays open so the driver can still collect the fare.
if (strtolower((string)($trip['payment_status'] ?? '')) !== 'paid') {
    echo json_encode([
        'success' => true,
        'completed' => false,
        'code' => 'PAYMENT_PENDING',
        'message' => 'Trip is not paid yet.'
    ]);
    exit;
}

$alreadyCompleted = (
    $trip['dropoff_time'] !== null ||
    in_array(strtolower((string)($trip['trip_status'] ?? '')), ['completed', 'cancelled'], true)
);

if ($alreadyCompleted) {
    echo json_encode([
        'success' => true,
        'completed' => false,
        'message' => 'Trip already completed.'
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE passenger_trips
    SET dropoff_time = COALESCE(dropoff_time, NOW()),
        trip_status = 'Completed'
    WHERE id = ? AND passenger_id = ?
");
$stmt->execute([$tripId, $passenger_id]);

echo json_encode([
    'success' => true,
    'completed' => true
]);
