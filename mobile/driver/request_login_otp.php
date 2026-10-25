<?php

if (function_exists('opcache_reset')) {
    opcache_reset();
}

error_log('[LOGIN_OTP] Request started');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/MailerService.php';

use App\Core\MailerService;
use App\Core\Database;
use PDO;

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
error_log('[LOGIN_OTP] Input: ' . json_encode($input));

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Username and password required"]);
    exit;
}

$db = Database::get();

$stmt = $db->prepare("SELECT * FROM driver_accounts WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    error_log('[LOGIN_DIAG] Account lookup failed for username: ' . $username);

    $fallbackStmt = $db->prepare("
        SELECT da.* FROM driver_accounts da
        INNER JOIN drivers d ON d.driver_id = da.driver_id
        WHERE d.driver_id = ? LIMIT 1
    ");
    $fallbackStmt->execute([$username]);
    $account = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        error_log('[LOGIN_DIAG] Fallback lookup succeeded for username: ' . $username . ' (matched by driver_id)');
    } else {
        error_log('[LOGIN_OTP] Account not found for username: ' . $username);
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }
}

if (!password_verify($password, $account['password'])) {
    error_log('[LOGIN_DIAG] Password verify failed for username: ' . $username);
    error_log('[LOGIN_OTP] Password mismatch for: ' . $username);
    echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    exit;
}

$driverId = $account['driver_id'];

$driverStmt = $db->prepare("SELECT * FROM drivers WHERE driver_id = ? LIMIT 1");
$driverStmt->execute([$driverId]);
$driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    error_log('[LOGIN_OTP] Driver profile not found for driver_id: ' . $driverId);
    echo json_encode(["success" => false, "message" => "Driver profile not found"]);
    exit;
}

$otp = rand(100000, 999999);

$invalidate = $db->prepare("DELETE FROM password_resets WHERE driver_id = ?");
$invalidate->execute([$driverId]);

$otpHash = password_hash($otp, PASSWORD_DEFAULT);
$insert = $db->prepare("INSERT INTO password_resets (driver_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$insert->execute([$driverId, $otpHash]);

$mailError = null;
try {
    $mailer = new MailerService();
    $sent = $mailer->sendOtp($driver['email'], $otp);
    if (!$sent) {
        $mailError = "Mailer returned false";
    }
} catch (\Throwable $e) {
    $mailError = $e->getMessage();
}

error_log('[LOGIN_DIAG] Login fully successful for username: ' . $username . ', driver_id: ' . $driverId);

echo json_encode([
    "success" => true,
    "otp_required" => true,
    "message" => $mailError ? "OTP generated (email: $mailError)" : "OTP sent to your registered email",
    "driver_id" => $driverId,
    "debug_otp" => $otp
]);
