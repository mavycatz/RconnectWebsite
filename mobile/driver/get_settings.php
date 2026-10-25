<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/lib/short_distance.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    // Public, driver-readable settings. The threshold always has a safe
    // fallback (1.0 km) so the app works before the settings row exists.
    echo json_encode([
        "success" => true,
        "data" => [
            "short_distance_early_dropoff_km" => short_distance_get_threshold_km($conn),
        ],
    ]);

} catch (Exception $e) {

    error_log("[get_settings] " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "data" => [
            "short_distance_early_dropoff_km" => 1.0,
        ],
        "message" => "Settings unavailable; defaults applied",
    ]);

}
