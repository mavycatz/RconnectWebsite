<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$input = array_merge($_GET, $_POST);
$json = file_get_contents("php://input");
if ($json && !empty($json)) {
    $jsonData = json_decode($json, true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$report_id = isset($input['report_id']) ? (int)$input['report_id'] : 0;
$replacement_required = isset($input['replacement_required']) ? (int)$input['replacement_required'] : 0;
$notes = $input['notes'] ?? '';

if ($report_id <= 0) {
    dispatcher_json_error(400, "report_id is required");
}

// Check current status
$checkStmt = dispatcher_statement($conn, "
    SELECT bpr.id, bpr.status, bpr.trip_id, bpr.bus_id, bpr.problem_type, bpr.trip_no,
           bpr.driver_id, d.full_name AS driver_name
    FROM bus_problem_reports bpr
    LEFT JOIN drivers d ON d.driver_id = bpr.driver_id
    WHERE bpr.id = ?
", "i", [$report_id]);
if (!$checkStmt) {
    dispatcher_json_error(500, "Failed to retrieve report");
}
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows == 0) {
    $checkStmt->close();
    dispatcher_json_error(404, "Report not found");
}
$report = $checkResult->fetch_assoc();
$checkStmt->close();

if ($report['status'] !== 'acknowledged' && $report['status'] !== 'waiting') {
    dispatcher_json_error(400, "Report must be in 'waiting' or 'acknowledged' status to be resolved");
}

// Update report status to resolved
$updateStmt = dispatcher_statement($conn, "UPDATE bus_problem_reports SET status = 'resolved', updated_at = NOW() WHERE id = ?", "i", [$report_id]);
if (!$updateStmt) {
    dispatcher_json_error(500, "Failed to resolve report");
}
$updateStmt->close();

// If replacement required is requested, update the trip
if ($replacement_required == 1 && $report['trip_id']) {
    $tripStmt = dispatcher_statement($conn, "UPDATE bus_trips SET replacement_required = 1 WHERE id = ?", "i", [$report['trip_id']]);
    if ($tripStmt) {
        $tripStmt->close();
    }
}

// Existing Admin notification system: the problem is resolved, so it also leaves
// the Dashboard "Needs Attention" list (which only reads unresolved reports).
// Best effort — a notification failure must not fail the resolve.
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php";
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Models/Notification.php";

    $driverLabel = (string)($report['driver_name'] ?? '');
    if ($driverLabel === '') {
        $driverLabel = (string)($report['driver_id'] ?? '');
    }

    $notifier = new \App\Models\Notification();
    $notifier->createForAdminRoles(
        'Bus Problem Resolved',
        'BUS ' . ($report['bus_id'] ?? '') . ' · ' . ($report['problem_type'] ?? '')
            . ' · TRIP #' . ($report['trip_no'] ?? '')
            . ' · Driver ' . $driverLabel
            . ' — the problem report has been resolved.',
        'success',
        'bus_problem',
        $report_id
    );
} catch (\Throwable $e) {
    error_log('[bus_problem] admin notification skipped (resolve): ' . $e->getMessage());
}

$conn->close();

echo json_encode([
    "success" => true,
    "message" => "Report resolved successfully",
    "report_id" => $report_id,
    "status" => "resolved"
]);
