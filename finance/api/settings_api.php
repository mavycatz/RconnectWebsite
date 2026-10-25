<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once __DIR__ . '/../middleware/validate_request.php';

use App\Auth\Auth;
use App\Core\Database;
use App\Audit\Audit;

validateFinanceRequest('standard', true, true);

$user = Auth::user();
$userId = $user['id'] ?? 0;
if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$key = trim($_POST['key'] ?? '');
$value = trim($_POST['value'] ?? '');

if (empty($key)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Key is required']);
    exit;
}

$allowedKeys = ['finance_theme', 'finance_period'];
if (!in_array($key, $allowedKeys, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid setting key']);
    exit;
}

try {
    $db = Database::get();
    $stmt = $db->prepare("
        INSERT INTO user_preferences (user_id, pref_key, pref_value)
        VALUES (:user_id, :key, :value)
        ON DUPLICATE KEY UPDATE pref_value = :value2, updated_at = NOW()
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':key' => $key,
        ':value' => $value,
        ':value2' => $value,
    ]);

    Audit::log('Finance', 'SettingsUpdate', "Setting '$key' changed to '$value'", $userId, $user['username'] ?? null, $user['role'] ?? null, null, 'Success');

    echo json_encode(['success' => true, 'message' => 'Setting saved']);
} catch (\Throwable $e) {
    Audit::log('Finance', 'SettingsUpdate', "Failed to update setting '$key'", $userId, $user['username'] ?? null, $user['role'] ?? null, null, 'Failed');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
}
