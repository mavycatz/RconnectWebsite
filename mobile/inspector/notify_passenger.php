<?php
/**
 * notify_passenger.php
 * Sends a push notification (type: 'passenger') to inspectors with a registered token.
 * The inspector app routes taps on this notification to the Passengers tab.
 *
 * Usage (POST, JSON or form):
 *   { "title": "New Passenger", "body": "Juan Dela Cruz boarded the bus." }
 *   Optional: { "bus_id": "3" } to only notify inspectors assigned to that bus.
 */

header("Content-Type: application/json");
require_once __DIR__ . '/push_helper.php';

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$title = trim($input['title'] ?? 'New Passenger');
$body = trim($input['body'] ?? 'A new passenger has been added.');
$busId = trim($input['bus_id'] ?? '');

$data = ['type' => 'passenger'];

sendPushToAllInspectors($title, $body, $data, $busId === '' ? null : $busId);

echo json_encode(["success" => true, "message" => "Notifications sent"]);
