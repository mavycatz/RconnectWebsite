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

    $boarding_code =
        isset($_GET['boarding_code'])
            ? trim($_GET['boarding_code'])
            : '';

    $destination_code =
        isset($_GET['destination_code'])
            ? trim($_GET['destination_code'])
            : '';

    $type =
        isset($_GET['type'])
            ? strtolower(trim($_GET['type']))
            : 'regular';

    if ($boarding_code === '') {

        echo json_encode([
            "success" => false,
            "message" => "Missing boarding_code"
        ]);

        exit;
    }

    if ($destination_code === '') {

        echo json_encode([
            "success" => false,
            "message" => "Missing destination_code"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET BOARDING STOP
    |--------------------------------------------------------------------------
    */

    $boardingId = (int)$boarding_code;

    $stmt = $conn->prepare("
        SELECT
            id,
            route_name,
            destination,
            regular_fare,
            student_fare,
            special_fare
        FROM fare_pricing
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "i",
        $boardingId
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Execute failed: " . $stmt->error
        );
    }

    $boardingStop =
        $stmt->get_result()->fetch_assoc();

    if (!$boardingStop) {

        echo json_encode([
            "success" => false,
            "message" => "Boarding stop not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET DESTINATION STOP
    |--------------------------------------------------------------------------
    */

    $destinationId = (int)$destination_code;

    $stmt = $conn->prepare("
        SELECT
            id,
            route_name,
            destination,
            regular_fare,
            student_fare,
            special_fare
        FROM fare_pricing
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "i",
        $destinationId
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Execute failed: " . $stmt->error
        );
    }

    $destinationStop =
        $stmt->get_result()->fetch_assoc();

    if (!$destinationStop) {

        echo json_encode([
            "success" => false,
            "message" => "Destination stop not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ROUTE
    |--------------------------------------------------------------------------
    */

    if (
        $boardingStop['route_name']
        !==
        $destinationStop['route_name']
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Boarding and destination belong to different routes."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE DESTINATION ORDER
    |--------------------------------------------------------------------------
    */

    if (
        (int)$destinationStop['id']
        <=
        (int)$boardingStop['id']
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid destination. Destination must be after boarding stop."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPUTE FARE
    |--------------------------------------------------------------------------
    */

    switch ($type) {

        case 'student':

            $boardingFare =
                (float)$boardingStop['student_fare'];

            $destinationFare =
                (float)$destinationStop['student_fare'];

            break;

        case 'special':

            $boardingFare =
                (float)$boardingStop['special_fare'];

            $destinationFare =
                (float)$destinationStop['special_fare'];

            break;

        default:

            $boardingFare =
                (float)$boardingStop['regular_fare'];

            $destinationFare =
                (float)$destinationStop['regular_fare'];

            break;
    }

    $fare =
        $destinationFare -
        $boardingFare;

    /*
    |--------------------------------------------------------------------------
    | MINIMUM FARE
    |--------------------------------------------------------------------------
    */

    if ($fare < 15) {
        $fare = 15;
    }

    $fare = round($fare, 2);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "route_name" =>
            $boardingStop['route_name'],

        "boarding_stop" =>
            $boardingStop['destination'],

        "destination" =>
            $destinationStop['destination'],

        "boarding_id" =>
            (int)$boardingStop['id'],

        "destination_id" =>
            (int)$destinationStop['id'],

        "passenger_type" =>
            $type,

        "boarding_fare" =>
            $boardingFare,

        "destination_fare" =>
            $destinationFare,

        "computed_fare" =>
            $destinationFare - $boardingFare,

        "fare" =>
            $fare

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
