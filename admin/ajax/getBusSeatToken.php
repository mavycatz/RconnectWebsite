<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;
use App\Models\Bus;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || Auth::user()['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$busId = filter_input(INPUT_GET, 'bus_id', FILTER_SANITIZE_STRING);
$seatNumber = filter_input(INPUT_GET, 'seat_number', FILTER_SANITIZE_STRING);

if (!$busId || !$seatNumber) {
    echo json_encode(['error' => 'Missing bus_id or seat_number']);
    exit;
}

try {
    $conn = Database::get();
    $busModel = new Bus();

    // --- Ensure bus exists ---
    $bus = $busModel->fetchByBusId((int)$busId);
    if (!$bus) {
        echo json_encode(['error' => 'Bus not found']);
        exit;
    }

    // --- Lookup the seat ---
    $stmt = $conn->prepare("SELECT id, qr_code FROM bus_seats WHERE bus_id = :bus_id AND seat_number = :seat_number LIMIT 1");
    $stmt->execute(['bus_id' => $busId, 'seat_number' => $seatNumber]);
    $seat = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Auto-create seats if missing ---
    if (!$seat) {
        $busModel->createSeatsForBus((int)$busId);

        // Try fetching the seat again
        $stmt->execute(['bus_id' => $busId, 'seat_number' => $seatNumber]);
        $seat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$seat) {
            echo json_encode(['error' => 'Seat creation failed']);
            exit;
        }
    }

    $seatId = $seat['id'];

    // --- Ensure bus QR is generated ---
    $busModel->generatePermanentQR((int)$busId);

    // --- If token already exists, return it ---
    if ($seat['qr_code']) {
        echo json_encode(['token' => $seat['qr_code']]);
        exit;
    }

    // --- Otherwise, create a new token ---
    $token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO bus_seat_tokens (bus_seat_id, token) VALUES (:seat_id, :token)");
    $stmt->execute(['seat_id' => $seatId, 'token' => $token]);

    echo json_encode(['token' => $token]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: '.$e->getMessage()]);
}
