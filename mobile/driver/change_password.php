<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

header("Content-Type: application/json");

try {

    $input = json_decode(file_get_contents("php://input"), true);

    $driver_id = trim($input['driver_id'] ?? '');
    $otp = trim($input['otp'] ?? '');
    $new_password = $input['new_password'] ?? '';

    if ($driver_id === '' || $otp === '' || $new_password === '') {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode([
            "success" => false,
            "message" => "New password must be at least 6 characters."
        ]);
        exit;
    }

    $db = Database::get();

    // Get latest OTP
    $stmt = $db->prepare("
        SELECT
            id,
            otp_hash,
            attempts,
            expires_at
        FROM password_resets
        WHERE driver_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$driver_id]);

    $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode([
            "success" => false,
            "message" => "No OTP found. Please request a new OTP."
        ]);
        exit;
    }

    // Check expiration
    $now = $db->query("SELECT NOW() AS now")->fetch(\PDO::FETCH_ASSOC);

    if (strtotime($reset['expires_at']) < strtotime($now['now'])) {
        echo json_encode([
            "success" => false,
            "message" => "OTP has expired."
        ]);
        exit;
    }

    // Check attempts
    if ((int)$reset['attempts'] >= 5) {
        echo json_encode([
            "success" => false,
            "message" => "Too many failed attempts. Request a new OTP."
        ]);
        exit;
    }

    // Verify OTP
    if (!password_verify($otp, $reset['otp_hash'])) {

        $attempt = $db->prepare("
            UPDATE password_resets
            SET attempts = attempts + 1
            WHERE id = ?
        ");

        $attempt->execute([$reset['id']]);

        echo json_encode([
            "success" => false,
            "message" => "Invalid OTP."
        ]);
        exit;
    }

    // Hash new password
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password in driver_accounts
    $update = $db->prepare("
        UPDATE driver_accounts
        SET password = ?
        WHERE driver_id = ?
    ");

    if (!$update->execute([$hashedPassword, $driver_id])) {
        throw new Exception("Failed to update password.");
    }

    if ($update->rowCount() === 0) {
        throw new Exception("Driver account not found.");
    }

    // Delete OTP after successful reset
    $delete = $db->prepare("
        DELETE FROM password_resets
        WHERE id = ?
    ");

    $delete->execute([$reset['id']]);

    echo json_encode([
        "success" => true,
        "message" => "Password changed successfully."
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}