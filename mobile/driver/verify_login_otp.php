<?php

if (function_exists('opcache_reset')) {
    opcache_reset();
}

error_log('[VERIFY_OTP] Request started');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;
use PDO;

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
error_log('[VERIFY_OTP] Input: ' . json_encode($input));

$driverId = trim($input['driver_id'] ?? '');
$otp = trim($input['otp'] ?? '');

if (empty($driverId) || empty($otp)) {
    echo json_encode(["success" => false, "message" => "Driver ID and OTP required"]);
    exit;
}

$db = Database::get();

$stmt = $db->prepare("SELECT * FROM password_resets WHERE driver_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$driverId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    error_log('[VERIFY_OTP] No OTP record found for: ' . $driverId);
    echo json_encode(["success" => false, "message" => "No OTP request found. Please request a new OTP."]);
    exit;
}

error_log('[VERIFY_OTP] Record found, expires_at: ' . $record['expires_at']);

if (strtotime($record['expires_at']) < time()) {
    error_log('[VERIFY_OTP] OTP expired');
    $delete = $db->prepare("DELETE FROM password_resets WHERE id = ?");
    $delete->execute([$record['id']]);
    echo json_encode(["success" => false, "message" => "OTP expired"]);
    exit;
}

if (!password_verify($otp, $record['otp_hash'])) {
    error_log('[VERIFY_OTP] OTP hash mismatch');
    echo json_encode(["success" => false, "message" => "Invalid OTP"]);
    exit;
}

$delete = $db->prepare("DELETE FROM password_resets WHERE id = ?");
$delete->execute([$record['id']]);

$driverStmt = $db->prepare("SELECT * FROM drivers WHERE driver_id = ? LIMIT 1");
$driverStmt->execute([$driverId]);
$driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

$token = bin2hex(random_bytes(32));
$tokenUpdate = $db->prepare("UPDATE drivers SET token = ? WHERE driver_id = ?");
$tokenUpdate->execute([$token, $driverId]);

error_log('[VERIFY_OTP] OTP verified, login complete for: ' . $driverId);

echo json_encode([
    "success" => true,
    "data" => [
        "token" => $token,
        "driver" => [
            "id" => $driver['id'],
            "driver_id" => $driver['driver_id'],
            "full_name" => $driver['full_name'],
            "email" => $driver['email']
        ]
    ]
]);
