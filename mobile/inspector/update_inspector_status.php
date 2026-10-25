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

    /*
    |--------------------------------------------------------------------------
    | VERIFY INSPECTOR ACCOUNT EXISTS
    |--------------------------------------------------------------------------
    */

    $check = $conn->prepare("
        SELECT account_id
        FROM inspector_accounts
        WHERE inspector_id = ?
        LIMIT 1
    ");

    if (!$check) {
        throw new Exception($conn->error);
    }

    $check->bind_param("s", $inspector_id);
    $check->execute();

    $exists = $check->get_result();

    if ($exists->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Inspector account not found."
        ]);

        $check->close();
        $conn->close();
        exit;
    }

    $check->close();

    /*
    |--------------------------------------------------------------------------
    | UPDATE LATEST INSPECTOR ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE inspector_assignments
        SET status = ?
        WHERE inspector_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("ss", $status, $inspector_id);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "No active assignment found for this inspector."
        ]);

        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" => "Inspector status updated successfully.",
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