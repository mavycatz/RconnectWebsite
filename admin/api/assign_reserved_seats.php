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

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;

$bus_id = $_GET['bus_id'] ?? null;
if (!$bus_id) {
    echo json_encode(['success' => false, 'message' => 'Bus ID missing']);
    exit;
}

$conn = Database::get();

/* -------------------------------------------------
   CHECK IF RESERVED ALREADY EXISTS
--------------------------------------------------*/
$check = $conn->prepare("
    SELECT COUNT(*) 
    FROM bus_seats 
    WHERE bus_id = ?
    AND (for_pwd = 1 OR for_senior = 1 OR for_pregnant = 1)
");
$check->execute([$bus_id]);
$alreadyAssigned = $check->fetchColumn();

if ($alreadyAssigned > 0) {
    echo json_encode(['success' => true, 'message' => 'Reserved seats already assigned']);
    exit;
}

/* -------------------------------------------------
   GET FIRST 3 NORMAL SEATS
--------------------------------------------------*/
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

/* -------------------------------------------------
   ASSIGN FLAGS
--------------------------------------------------*/
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

echo json_encode(['success' => true, 'message' => 'Reserved seats assigned']);