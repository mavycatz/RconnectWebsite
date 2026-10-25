<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed.");
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $inspector_id = trim($data['inspector_id'] ?? '');
    $status = trim($data['status'] ?? '');

    $validStatuses = [
        "Waiting",
        "On-Duty",
        "Break",
        "Checked-In",
        "Checked-Out"
    ];

    if ($inspector_id === '' || $status === '') {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    if (!in_array($status, $validStatuses)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid status value."
        ]);
        exit;
    }

    // Verify inspector exists
    $checkInspector = $conn->prepare("
        SELECT inspect_pk
        FROM inspectors
        WHERE inspector_identity = ?
        LIMIT 1
    ");

    if (!$checkInspector) {
        throw new Exception($conn->error);
    }

    $checkInspector->bind_param("s", $inspector_id);
    $checkInspector->execute();
    $inspectorResult = $checkInspector->get_result();

    if ($inspectorResult->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Inspector not found."
        ]);
        exit;
    }

    $checkInspector->close();

    // Get latest assignment
    $assignment = $conn->prepare("
        SELECT id
        FROM inspectors_assignments
        WHERE inspector_id = ?
        ORDER BY assigned_at DESC, id DESC
        LIMIT 1
    ");

    if (!$assignment) {
        throw new Exception($conn->error);
    }

    $assignment->bind_param("s", $inspector_id);
    $assignment->execute();
    $assignmentResult = $assignment->get_result();

    if ($assignmentResult->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "No assignment found for this inspector."
        ]);
        exit;
    }

    $assignmentRow = $assignmentResult->fetch_assoc();
    $assignmentId = $assignmentRow['id'];

    $assignment->close();

    // Update assignment status
    $update = $conn->prepare("
        UPDATE inspectors_assignments
        SET status = ?
        WHERE id = ?
    ");

    if (!$update) {
        throw new Exception($conn->error);
    }

    $update->bind_param("si", $status, $assignmentId);

    if (!$update->execute()) {
        throw new Exception($update->error);
    }

    $update->close();

    echo json_encode([
        "success" => true,
        "message" => "Inspector status updated successfully.",
        "assignment_id" => $assignmentId,
        "inspector_id" => $inspector_id,
        "status" => $status
    ]);

    $conn->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);

}