<?php
require_once __DIR__ . "/../bootstrap.php";
require_once __DIR__ . "/../utils/token.php";

dispatcher_init_session();

if (isset($_SESSION['inspector_id'])) {
    $user = ['id' => $_SESSION['inspector_id']];
} else {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Authorization header required"]);
        exit;
    }
    $token = str_replace("Bearer ", "", $authHeader);
    $user = verifyToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid or expired token"]);
        exit;
    }
}
