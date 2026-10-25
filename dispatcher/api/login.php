<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../bootstrap.php";
dispatcher_init_session();
require_once __DIR__ . "/../../mobile/config/database.php";
require_once __DIR__ . "/../utils/token.php";

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['dispatcher_id'])) {
        echo json_encode(["success" => true, "dispatcher" => ["id" => $_SESSION['dispatcher_id'], "name" => $_SESSION['dispatcher_name']]]);
    } else {
        echo json_encode(["success" => false, "message" => "Not logged in"]);
    }
    exit;
}

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->username) || !isset($data->password)) {
    echo json_encode(["success" => false, "message" => "Missing credentials"]);
    exit;
}

$username = trim($data->username);
$password = $data->password;

$stmt = $conn->prepare("SELECT * FROM inspector_accounts WHERE inspector_id = ? AND role = 'dispatcher' LIMIT 1");
if (!$stmt) {
    error_log("Dispatcher login prepare failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Login service unavailable"]);
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, $user['password'])) {
    $token = generateToken($user['inspector_id']);
    $_SESSION['dispatcher_id'] = $user['inspector_id'];
    $_SESSION['dispatcher_name'] = $user['inspector_id'];
    $_SESSION['dispatcher_token'] = $token;
    dispatcher_set_remember_cookie($token);
    echo json_encode(["success" => true, "token" => $token, "dispatcher" => ["id" => $user['inspector_id'], "name" => $user['inspector_id']]]);
} else {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
        \App\Audit\Audit::log('Login', 'Login', 'Failed dispatcher API login: invalid credentials', null, $username, 'dispatcher', null, 'Failed');
    } catch (\Throwable $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    echo json_encode(["success" => false, "message" => "Invalid credentials"]);
}
$conn->close();
