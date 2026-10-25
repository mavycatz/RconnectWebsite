<?php
/**
 * notify_alert.php
 * Sends a push notification (type: 'alert') to all inspectors with a registered token.
 * The inspector app routes taps on this notification to the Alerts tab.
 *
 * Usage (POST, JSON or form):
 *   { "title": "New Announcement", "body": "Route 5 trip is delayed." }
 *
 * Automatically includes the announcement id when called with { "alert_id": 12 }.
 */

header("Content-Type: application/json");
require_once __DIR__ . '/push_helper.php';

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$title = trim($input['title'] ?? 'New Alert');
$body = trim($input['body'] ?? 'A new alert or announcement was posted.');
$alertId = trim($input['alert_id'] ?? '');

$data = ['type' => 'alert'];
if ($alertId !== '') {
    $data['alert_id'] = $alertId;
}

sendPushToAllInspectors($title, $body, $data);

echo json_encode(["success" => true, "message" => "Notifications sent"]);
