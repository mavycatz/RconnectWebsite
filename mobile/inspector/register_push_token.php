<?php
/**
 * register_push_token.php
 * Called by the inspector app:
 *   - Register:   { "inspector_id": "5", "expo_push_token": "ExponentPushToken[...]" }
 *   - Unregister: { "inspector_id": "5", "expo_push_token": "" }
 * Best-effort on the app side; never blocks login.
 */

header("Content-Type: application/json");
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

$db = new Database();
$conn = $db->connect();

$input = json_decode(file_get_contents("php://input"), true);

$inspectorId = trim($input['inspector_id'] ?? '');
$token = trim($input['expo_push_token'] ?? '');

if (empty($inspectorId)) {
    echo json_encode(["success" => false, "message" => "inspector_id is required"]);
    exit;
}

try {

    if ($token === '') {
        // Unregister (logout)
        $stmt = $conn->prepare("DELETE FROM push_tokens WHERE inspector_id = ?");
        $stmt->bind_param("s", $inspectorId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["success" => true, "message" => "Token removed"]);
        exit;
    }

    if (strpos($token, 'ExponentPushToken') !== 0 && strpos($token, 'ExpoPushToken') !== 0) {
        echo json_encode(["success" => false, "message" => "Invalid expo push token"]);
        exit;
    }

    // Upsert so a changed token replaces the old registration
    $stmt = $conn->prepare(
        "INSERT INTO push_tokens (inspector_id, expo_push_token)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE expo_push_token = VALUES(expo_push_token)"
    );
    $stmt->bind_param("ss", $inspectorId, $token);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Token registered"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
