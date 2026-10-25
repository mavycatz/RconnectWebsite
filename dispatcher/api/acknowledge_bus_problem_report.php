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
if ($report_id <= 0) {
    dispatcher_json_error(400, "report_id is required");
}

// Check current status
$checkStmt = dispatcher_statement($conn, "
    SELECT bpr.id, bpr.status, bpr.bus_id, bpr.problem_type, bpr.trip_no, bpr.driver_id,
           d.full_name AS driver_name
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

if ($report['status'] !== 'waiting') {
    dispatcher_json_error(400, "Only reports in 'waiting' status can be acknowledged");
}

// Update status to acknowledged
$updateStmt = dispatcher_statement($conn, "UPDATE bus_problem_reports SET status = 'acknowledged', updated_at = NOW() WHERE id = ?", "i", [$report_id]);
if (!$updateStmt) {
    dispatcher_json_error(500, "Failed to acknowledge report");
}
$updateStmt->close();

// Existing Admin notification system: status update for this report. Best effort —
// a notification failure must never turn a successful acknowledgement into an error.
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php";
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Models/Notification.php";

    $driverLabel = (string)($report['driver_name'] ?? '');
    if ($driverLabel === '') {
        $driverLabel = (string)($report['driver_id'] ?? '');
    }

    $notifier = new \App\Models\Notification();
    $notifier->createForAdminRoles(
        'Bus Problem Acknowledged',
        'BUS ' . ($report['bus_id'] ?? '') . ' · ' . ($report['problem_type'] ?? '')
            . ' · TRIP #' . ($report['trip_no'] ?? '')
            . ' · Driver ' . $driverLabel
            . ' — the dispatcher acknowledged the report.',
        'info',
        'bus_problem',
        $report_id
    );
} catch (\Throwable $e) {
    error_log('[bus_problem] admin notification skipped (acknowledge): ' . $e->getMessage());
}

$conn->close();

echo json_encode([
    "success" => true,
    "message" => "Report acknowledged successfully",
    "report_id" => $report_id,
    "status" => "acknowledged"
]);
