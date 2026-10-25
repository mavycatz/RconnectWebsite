<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';

use App\Core\Database;
use App\Core\MailerService;

header("Content-Type: application/json");

try {

    $input = json_decode(file_get_contents("php://input"), true);

    $driver_id = trim($input['driver_id'] ?? '');

    if ($driver_id === '') {
        echo json_encode([
            "success" => false,
            "message" => "Driver ID is required."
        ]);
        exit;
    }

    $db = Database::get();

    // Get driver information
    $stmt = $db->prepare("
        SELECT
            full_name,
            email
        FROM drivers
        WHERE driver_id = ?
        LIMIT 1
    ");

    if (!$stmt->execute([$driver_id])) {
        throw new Exception("Unable to retrieve driver.");
    }

    $driver = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$driver) {
        echo json_encode([
            "success" => false,
            "message" => "Driver not found."
        ]);
        exit;
    }

    $email = trim($driver['email'] ?? '');

    if ($email === '') {
        echo json_encode([
            "success" => false,
            "message" => "No email registered for this driver."
        ]);
        exit;
    }

    // Generate secure OTP
    $otp = random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    $db->beginTransaction();

    // Remove previous OTPs for this driver
    $delete = $db->prepare("
        DELETE FROM password_resets
        WHERE driver_id = ?
    ");

    if (!$delete->execute([$driver_id])) {
        throw new Exception("Unable to clear previous OTP.");
    }

    // Save new OTP
    $insert = $db->prepare("
        INSERT INTO password_resets
        (
            driver_id,
            otp_hash,
            expires_at
        )
        VALUES
        (
            ?,
            ?,
            DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        )
    ");

    if (!$insert->execute([$driver_id, $otpHash])) {
        throw new Exception("Unable to save OTP.");
    }

    // Send OTP email
    $mailer = new MailerService();

    if (!$mailer->sendOtp($email, (string)$otp)) {
        throw new Exception("Failed to send OTP email.");
    }

    $db->commit();

    echo json_encode([
        "success" => true,
        "message" => "OTP sent to your registered email."
    ]);

} catch (Throwable $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}