<?php
declare(strict_types=1);

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
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

    // Check if bus exists
    $stmt = $conn->prepare("SELECT * FROM buses WHERE bus_id = :bus_id LIMIT 1");
    $stmt->execute([':bus_id' => $busId]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bus) {
        echo json_encode(['error' => 'Bus not found']);
        exit;
    }

    // Check if seat exists, create if missing
    $stmt = $conn->prepare("SELECT id FROM bus_seats WHERE bus_id = :bus_id AND seat_number = :seat_number LIMIT 1");
    $stmt->execute([':bus_id' => $busId, ':seat_number' => $seatNumber]);
    $seat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seat) {
        $type = is_numeric($seatNumber) ? 'Regular' : 'Standing';
        $stmtInsert = $conn->prepare("INSERT INTO bus_seats (bus_id, seat_number, type) VALUES (:bus_id, :seat_number, :type)");
        $stmtInsert->execute([':bus_id' => $busId, ':seat_number' => $seatNumber, ':type' => $type]);
        $seatId = $conn->lastInsertId();
    } else {
        $seatId = $seat['id'];
    }

    // Check if token exists
    $stmt = $conn->prepare("SELECT token FROM bus_seat_tokens WHERE bus_seat_id = :seat_id LIMIT 1");
    $stmt->execute([':seat_id' => $seatId]);
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tokenRow) {
        echo json_encode(['token' => $tokenRow['token']]);
        exit;
    }

    // Generate new token
    $token = bin2hex(random_bytes(16));

    // Insert token
    $stmt = $conn->prepare("INSERT INTO bus_seat_tokens (bus_seat_id, token) VALUES (:seat_id, :token)");
    $stmt->execute([':seat_id' => $seatId, ':token' => $token]);

    echo json_encode(['token' => $token]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: '.$e->getMessage()]);
}
