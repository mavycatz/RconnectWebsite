<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"));

if(!$data || !isset($data->inspectorId) || !isset($data->password)){
    echo json_encode([
        "status" => "error",
        "message" => "Missing credentials"
    ]);
    exit;
}

$inspectorId = $data->inspectorId;
$password = $data->password;

$query = "SELECT * FROM inspector_accounts WHERE inspector_id = ?";

$stmt = $conn->prepare($query);

if(!$stmt){
    echo json_encode([
        "status"=>"error",
        "message"=>"Query preparation failed"
    ]);
    exit;
}

$stmt->bind_param("s", $inspectorId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if($user){

    if(password_verify($password, $user['password'])){

        echo json_encode([
            "status"=>"success",
            "inspector_id"=>$user['inspector_id'],
            "role"=>$user['role']
        ]);

    } else {

        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid password"
        ]);

    }

}else{

    echo json_encode([
        "status"=>"error",
        "message"=>"Inspector not found"
    ]);

}

$stmt->close();
$conn->close();