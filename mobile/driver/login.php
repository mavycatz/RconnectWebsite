<?php

header("Content-Type: application/json");

require_once "../config/database.php";
require_once "../utils/response.php";
require_once "../utils/token.php";

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if(!$username || !$password){
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
    \App\Audit\Audit::log('Login', 'Login', 'Failed driver login: missing credentials', null, $username, 'driver', null, 'Failed');
    jsonResponse(false,"Username and password required");
}

$stmt = $conn->prepare(
"SELECT 
    a.id,
    a.driver_id,
    a.username,
    a.password,
    d.full_name,
    d.status
FROM driver_accounts a
JOIN drivers d ON a.driver_id = d.driver_id
WHERE a.username = ?
LIMIT 1"
);

$stmt->bind_param("s",$username);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){

    if(!password_verify($password,$row['password'])){
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
        \App\Audit\Audit::log('Login', 'Login', 'Failed driver login: invalid password', $row['id'], $row['username'], 'driver', null, 'Failed');
        jsonResponse(false,"Invalid password");
    }

    if($row['status'] !== "Active"){
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
        \App\Audit\Audit::log('Login', 'Login', 'Failed driver login: account inactive', $row['id'], $row['username'], 'driver', null, 'Failed');
        jsonResponse(false,"Driver account inactive");
    }

    $token = generateToken($row['driver_id']);

    jsonResponse(true,"Login successful",[
        "token"=>$token,
        "driver"=>[
            "driver_id"=>$row['driver_id'],
            "full_name"=>$row['full_name'],
            "username"=>$row['username'],
            "status"=>$row['status']
        ]
    ]);

}else{

    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
    \App\Audit\Audit::log('Login', 'Login', 'Failed driver login: driver not found', null, $username, 'driver', null, 'Failed');
    jsonResponse(false,"Driver not found");

}