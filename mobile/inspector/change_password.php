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
    $new_password = $data['new_password'] ?? '';

    if ($inspector_id === '' || $otp === '' || $new_password === '') {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode([
            "success" => false,
            "message" => "Password must be at least 6 characters."
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

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update = $conn->prepare("
        UPDATE inspector_accounts
        SET password = ?
        WHERE inspector_id = ?
        LIMIT 1
    ");

    if (!$update) {
        throw new Exception($conn->error);
    }

    $update->bind_param("ss", $hashed_password, $inspector_id);
    $update->execute();
    $update->close();

    $delete = $conn->prepare("
        DELETE FROM inspector_password_resets
        WHERE id = ?
    ");

    $delete->bind_param("i", $row['id']);
    $delete->execute();
    $delete->close();

    echo json_encode([
        "success" => true,
        "message" => "Password changed successfully."
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
