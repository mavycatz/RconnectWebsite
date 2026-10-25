<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try{

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if(!$conn){
        throw new Exception("Database connection failed");
    }

    $route_name = isset($_GET['route_name']) ? trim($_GET['route_name']) : '';

    if ($route_name !== '') {
        $stmt = $conn->prepare("
            SELECT id, route_name, destination, regular_fare, student_fare, special_fare, display_order
            FROM fare_pricing
            WHERE route_name = ?
            ORDER BY display_order ASC
        ");
        $stmt->bind_param("s", $route_name);
    } else {
        $stmt = $conn->prepare("
            SELECT id, route_name, destination, regular_fare, student_fare, special_fare, display_order
            FROM fare_pricing
            ORDER BY display_order ASC
        ");
    }

    if(!$stmt){
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if(!$stmt->execute()){
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $destinations = [];

    while($row = $result->fetch_assoc()){
        $destinations[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($destinations),
        "data" => $destinations
    ]);

}catch(Exception $e){

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);

}
