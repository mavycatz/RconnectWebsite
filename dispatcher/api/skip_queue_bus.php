<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = [];
}
dispatcher_assert_identity($data, $dispatcher_user);

$bus_id = trim((string)($data['bus_id'] ?? ''));
$action = trim((string)($data['action'] ?? 'skip'));
$reason_category = trim((string)($data['reason_category'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$date = date('Y-m-d');

$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
if ($terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}
if ($bus_id === '') {
    dispatcher_json_error(400, "Missing bus_id");
}

/* The Admin-published master order is official at every terminal: queue
   skips are an override the dispatcher may not perform while it is published. */
if (dispatcher_queue_admin_lock($conn, $terminal_id, $date) !== null) {
    dispatcher_json_error(409, "The Admin dispatch order is official. Queue skips are disabled for this operating date.");
}

$actionMap = [
    'skip' => 'Queue Skipped',
    'clear' => 'Queue Skip Cleared',
];
if (!isset($actionMap[$action])) {
    dispatcher_json_error(400, "Invalid action");
}

$reasons = dispatcher_queue_skip_reasons();
if ($action === 'skip') {
    if ($reason_category === '') {
        dispatcher_json_error(400, "Please select a reason before skipping this trip.");
    }
    if (!isset($reasons[$reason_category])) {
        dispatcher_json_error(400, "Please select a valid reason.");
    }
}
if ($notes !== '') {
    $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 500) : substr($notes, 0, 500);
}

$positionMap = dispatcher_queue_entry_position_map($conn, $terminal_id, $date);
if (!array_key_exists($bus_id, $positionMap)) {
    dispatcher_json_error(403, "This bus is not part of your terminal queue");
}
$position = $positionMap[$bus_id] ?? 0;

$recorded = dispatcher_queue_record_skip(
    $conn,
    $dispatcher_user,
    $actionMap[$action],
    $bus_id,
    $position,
    $date,
    $action === 'skip' ? $reason_category : '',
    $notes
);
if (!$recorded) {
    error_log("Failed to record queue skip decision for bus $bus_id");
    dispatcher_json_error(500, "We couldn't save this decision. Please try again.");
}

echo json_encode([
    "success" => true,
    "message" => $action === 'skip' ? "Bus skipped" : "Skip cleared",
    "data" => [
        "bus_id" => $bus_id,
        "queue_position" => $position,
        "skipped" => $action === 'skip',
        "reason_category" => $action === 'skip' ? $reason_category : null,
        "reason_label" => $action === 'skip' ? ($reasons[$reason_category] ?? null) : null,
        "notes" => $notes !== '' ? $notes : null,
        "date" => $date,
    ],
]);
$conn->close();
