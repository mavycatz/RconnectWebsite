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

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Only GET requests are allowed");
    }

    $inspector_id = trim($_GET['inspector_id'] ?? '');

    if (empty($inspector_id)) {
        throw new Exception("Missing inspector_id parameter");
    }

    /*
    =====================================
    GET INSPECTOR PROFILE
    =====================================
    */

    $stmt = $conn->prepare("
        SELECT
            ia.inspector_id,
            i.inspector_name,
            i.operational_assignment,
            i.assigned_routes,
            ia.status
        FROM inspector_accounts ia
        LEFT JOIN inspectors i ON i.account_id = ia.account_id
        WHERE ia.inspector_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Profile query failed: " . $conn->error);
    }

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        echo json_encode(["success" => false, "message" => "Inspector not found"]);
        exit;
    }

    /*
    =====================================
    GET TODAY'S STATS
    =====================================
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total
        FROM passenger_trips
        WHERE inspector_id = ?
    ");

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $passengersResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total
        FROM passenger_trips
        WHERE inspector_id = ?
          AND (passenger_id IS NULL
               OR passenger_id IN (
                   SELECT id FROM passengers WHERE full_name = 'Walk-in Passenger'
               ))
    ");

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $walkInsResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT trip_no) AS total
        FROM passenger_trips
        WHERE inspector_id = ?
    ");

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $tripsResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total
        FROM inspector_assignments
        WHERE inspector_id = ?
    ");

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $checkInsResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stats = [
        "passengers_assisted_today" => (int)($passengersResult['total'] ?? 0),
        "walk_in_passengers_today" => (int)($walkInsResult['total'] ?? 0),
        "trips_inspected_today" => (int)($tripsResult['total'] ?? 0),
        "check_ins_today" => (int)($checkInsResult['total'] ?? 0)
    ];

    echo json_encode([
        "success" => true,
        "profile" => $profile,
        "stats" => $stats
    ]);

    $conn->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);

}
