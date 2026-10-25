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

    error_log("[get_fare_pricing] route_name param: '" . $route_name . "'");

    if ($route_name !== '') {
        $stmt = $conn->prepare("
            SELECT id, route_name, destination, regular_fare, student_fare, special_fare, display_order
            FROM fare_pricing
            WHERE route_name = ?
            ORDER BY display_order ASC
        ");
        $stmt->bind_param("s", $route_name);
    } else {
        error_log("[get_fare_pricing] No route_name provided, returning empty");
        echo json_encode([
            "success" => true,
            "count" => 0,
            "data" => []
        ]);
        exit;
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
        $row['display_order'] = (int)$row['display_order'];
        $row['id'] = (int)$row['id'];
        $row['regular_fare'] = (float)$row['regular_fare'];
        $row['student_fare'] = $row['student_fare'] !== null ? (float)$row['student_fare'] : null;
        $row['special_fare'] = $row['special_fare'] !== null ? (float)$row['special_fare'] : null;
        $destinations[] = $row;
    }

    error_log("[get_fare_pricing] Found " . count($destinations) . " rows for route: " . $route_name);

    echo json_encode([
        "success" => true,
        "route_name" => $route_name,
        "count" => count($destinations),
        "data" => $destinations
    ]);

}catch(Exception $e){

    error_log("[get_fare_pricing] Exception: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);

}
