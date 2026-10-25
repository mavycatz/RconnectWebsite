<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$trip_id = (int)($_GET['trip_id'] ?? 0);
if ($trip_id <= 0) {
    dispatcher_json_error(400, "Missing trip_id");
}

$origStmt = dispatcher_statement($conn, "SELECT * FROM bus_trips WHERE id = ? LIMIT 1", "i", [$trip_id]);
if (!$origStmt) {
    dispatcher_json_error(500, "Failed to load trip");
}
$original = $origStmt->get_result()->fetch_assoc();
$origStmt->close();

if (!$original) {
    dispatcher_json_error(404, "Trip not found");
}

$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
$fromTerminal = (int)($original['from_terminal_id'] ?? 0);
if ($terminal_id > 0 && $fromTerminal > 0 && $terminal_id !== $fromTerminal) {
    dispatcher_json_error(403, "Trip does not belong to your terminal");
}

dispatcher_ensure_transfer_schema($conn);
$transferStmt = dispatcher_statement(
    $conn,
    "SELECT * FROM trip_bus_transfers WHERE original_trip_id = ? LIMIT 1",
    "i",
    [$trip_id]
);
$transfer = null;
if ($transferStmt) {
    $transfer = $transferStmt->get_result()->fetch_assoc();
    $transferStmt->close();
}

$paxStmt = dispatcher_statement(
    $conn,
    "SELECT pt.*, p.full_name AS passenger_name, p.passenger_code
     FROM passenger_trips pt
     LEFT JOIN passengers p ON p.passenger_id = pt.passenger_id
     WHERE pt.trip_no = ?
     ORDER BY pt.id ASC",
    "i",
    [$trip_id]
);
$passengers = [];
if ($paxStmt) {
    $res = $paxStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $passengers[] = $row;
    }
    $paxStmt->close();
}

$transferStatuses = [];
if ($transfer) {
    $transfer_id = (int)$transfer['id'];
    $tsStmt = dispatcher_statement(
        $conn,
        "SELECT passenger_trip_id, transfer_status, transferred_at, transfer_lat, transfer_lng, transfer_location
         FROM passenger_transfer_status
         WHERE transfer_id = ?",
        "i",
        [$transfer_id]
    );
    if ($tsStmt) {
        $res = $tsStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $transferStatuses[(int)$row['passenger_trip_id']] = $row;
        }
        $tsStmt->close();
    }
}

foreach ($passengers as &$p) {
    $pt_id = (int)($p['id'] ?? 0);
    $p['transfer_status'] = isset($transferStatuses[$pt_id]) ? $transferStatuses[$pt_id]['transfer_status'] : 'waiting';
    $p['transferred_at'] = isset($transferStatuses[$pt_id]) ? $transferStatuses[$pt_id]['transferred_at'] : null;
    $p['transfer_location'] = isset($transferStatuses[$pt_id]) ? $transferStatuses[$pt_id]['transfer_location'] : null;
}
unset($p);

echo json_encode([
    "success" => true,
    "data" => [
        "trip" => $original,
        "transfer" => $transfer,
        "passengers" => $passengers
    ]
]);
$conn->close();