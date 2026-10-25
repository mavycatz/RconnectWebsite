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
    $otp = trim($data['otp'] ?? '');

    if ($inspector_id === '' || $otp === '') {
        echo json_encode([
            "success" => false,
            "message" => "Inspector ID and OTP are required."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, otp_hash, expires_at
        FROM inspector_password_resets
        WHERE inspector_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "No OTP request found. Please request a new OTP."
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($otp, $row['otp_hash'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid OTP code."
        ]);
        $conn->close();
        exit;
    }

    if (strtotime($row['expires_at']) < time()) {
        echo json_encode([
            "success" => false,
            "message" => "OTP has expired. Please request a new one."
        ]);
        $conn->close();
        exit;
    }

    $delete = $conn->prepare("
        DELETE FROM password_resets
        WHERE id = ?
    ");

    $delete->bind_param("i", $row['id']);
    $delete->execute();
    $delete->close();

    echo json_encode([
        "success" => true,
        "message" => "OTP verified successfully."
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
