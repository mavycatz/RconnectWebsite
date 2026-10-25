<?php
require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Models/Bus.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Models\Bus;
use App\Core\Database;

$bus_id = $_GET['bus_id'] ?? null;

if (!$bus_id) {
    echo json_encode(['success' => false, 'message' => 'Bus ID missing']);
    exit;
}

$busModel = new Bus();
$pdo = Database::get();

// ✅ DO NOT CAST TO INT
$bus = $busModel->fetchByBusId($bus_id);

if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'Bus not found']);
    exit;
}

/* =====================================================
   1️⃣ CREATE SEATS IF NOT YET CREATED
===================================================== */
$busModel->createSeatsForBus($bus_id);

/* =====================================================
   2️⃣ ASSIGN RESERVED SEATS (ONLY FIRST TIME)
===================================================== */

// Check if already assigned
$stmtCheck = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM bus_seats
    WHERE bus_id = :bus_id
    AND (for_pwd = 1 OR for_senior = 1 OR for_pregnant = 1)
");
$stmtCheck->execute([':bus_id' => $bus_id]);
$alreadyAssigned = (int)$stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];

if ($alreadyAssigned === 0) {

    $stmtSeats = $pdo->prepare("
        SELECT id
        FROM bus_seats
        WHERE bus_id = :bus_id
        AND type = 'Seat'
        ORDER BY CAST(seat_number AS UNSIGNED) ASC
        LIMIT 3
    ");
    $stmtSeats->execute([':bus_id' => $bus_id]);
    $seats = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);

    if (count($seats) >= 3) {

        $pdo->prepare("
            UPDATE bus_seats SET for_pwd = 1 WHERE id = ?
        ")->execute([$seats[0]['id']]);

        $pdo->prepare("
            UPDATE bus_seats SET for_senior = 1 WHERE id = ?
        ")->execute([$seats[1]['id']]);

        $pdo->prepare("
            UPDATE bus_seats SET for_pregnant = 1 WHERE id = ?
        ")->execute([$seats[2]['id']]);
    }
}

/* =====================================================
   3️⃣ IF QR ALREADY GENERATED, RETURN IT
===================================================== */
if (!empty($bus['qr_code']) && $bus['qr_code'] === 'Generated' && !empty($bus['qr_data'])) {
    echo json_encode([
        'success' => true,
        'status'  => 'Generated',
        'qr_data' => $bus['qr_data']
    ]);
    exit;
}

/* =====================================================
   4️⃣ GENERATE PERMANENT QR JSON
===================================================== */
$qrData = json_encode([
    'bus_id'            => $bus['bus_id'],
    'plate_number'      => $bus['plate_number'],
    'seats'             => (int)$bus['seats'],
    'standing_capacity' => (int)$bus['standing_capacity']
]);

$stmt = $pdo->prepare("
    UPDATE buses 
    SET qr_code = 'Generated', qr_data = :qr_data 
    WHERE bus_id = :bus_id
");

$updated = $stmt->execute([
    ':qr_data' => $qrData,
    ':bus_id'  => $bus_id
]);

if ($updated) {
    echo json_encode([
        'success' => true,
        'status'  => 'Generated',
        'qr_data' => $qrData
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save QR code'
    ]);
}