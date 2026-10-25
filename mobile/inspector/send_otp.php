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

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/MailerService.php';

use App\Core\MailerService;

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

    if ($inspector_id === '') {
        echo json_encode([
            "success" => false,
            "message" => "Inspector ID is required."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT i.inspector_pk, i.email
        FROM inspector_accounts ia
        INNER JOIN inspectors i ON i.account_id = ia.account_id
        WHERE ia.inspector_id = ?
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
            "message" => "Inspector ID not found."
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $row = $result->fetch_assoc();
    $email = trim($row['email'] ?? '');

    if ($email === '') {
        echo json_encode([
            "success" => false,
            "message" => "No email address found for this inspector."
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $invalidate = $conn->prepare("
        DELETE FROM inspector_password_resets
        WHERE inspector_id = ?
    ");

    if (!$invalidate) {
        throw new Exception($conn->error);
    }

    $invalidate->bind_param("s", $inspector_id);
    $invalidate->execute();
    $invalidate->close();

    $insert = $conn->prepare("
        INSERT INTO inspector_password_resets (inspector_id, otp_hash, expires_at)
        VALUES (?, ?, ?)
    ");

    if (!$insert) {
        throw new Exception($conn->error);
    }

    $insert->bind_param("sss", $inspector_id, $otpHash, $expires_at);

    if (!$insert->execute()) {
        throw new Exception("Failed to store OTP: " . $insert->error);
    }

    $insert->close();

    $mailer = new MailerService();
    $sent = $mailer->sendOtp($email, $otp);

    if (!$sent) {
        echo json_encode([
            "success" => false,
            "message" => "Failed to send OTP email. Please try again later."
        ]);
        $conn->close();
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "OTP sent to your registered email."
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
