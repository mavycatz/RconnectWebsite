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

require_once __DIR__ . '/../../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use App\Core\Database;

$data = json_decode(file_get_contents('php://input'), true);
$bus_id = $data['bus_id'] ?? '';
$seat_number = $data['seat_number'] ?? '';

if (!$bus_id || !$seat_number) {
    echo json_encode(['status' => 'error', 'message' => 'Missing bus_id or seat_number']);
    exit;
}

$conn = Database::get();

/* =====================================================
   ASSIGN RESERVED SEATS (ONLY FIRST TIME PER BUS)
===================================================== */

// Check if reserved already exists
$check = $conn->prepare("
    SELECT COUNT(*) 
    FROM bus_seats 
    WHERE bus_id = ?
    AND (for_pwd = 1 OR for_senior = 1 OR for_pregnant = 1)
");
$check->execute([$bus_id]);
$alreadyAssigned = $check->fetchColumn();

if ($alreadyAssigned == 0) {

    // Get first 3 normal seats only
    $stmt = $conn->prepare("
        SELECT id 
        FROM bus_seats 
        WHERE bus_id = ?
        AND type = 'Seat'
        ORDER BY CAST(seat_number AS UNSIGNED) ASC
        LIMIT 3
    ");
    $stmt->execute([$bus_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($seats[0])) {
        $conn->prepare("UPDATE bus_seats SET for_pwd = 1 WHERE id = ?")
             ->execute([$seats[0]['id']]);
    }

    if (isset($seats[1])) {
        $conn->prepare("UPDATE bus_seats SET for_senior = 1 WHERE id = ?")
             ->execute([$seats[1]['id']]);
    }

    if (isset($seats[2])) {
        $conn->prepare("UPDATE bus_seats SET for_pregnant = 1 WHERE id = ?")
             ->execute([$seats[2]['id']]);
    }
}

/* =====================================================
   GENERATE QR IMAGE
===================================================== */

$qrText = json_encode([
    'bus_id' => $bus_id,
    'seat'   => $seat_number
]);

$qr = new QrCode($qrText);
$writer = new PngWriter();
$result = $writer->write($qr);

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/qrcodes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$qrFileName = "{$bus_id}_{$seat_number}.png";
$qrPath = $uploadDir . $qrFileName;
file_put_contents($qrPath, $result->getString());

$qrUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://")
         . $_SERVER['HTTP_HOST'] . "/uploads/qrcodes/" . $qrFileName;

echo json_encode([
    'status' => 'success',
    'qr_code' => $qrUrl
]);