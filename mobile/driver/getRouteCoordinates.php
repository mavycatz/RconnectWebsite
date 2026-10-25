<?php

header("Content-Type: application/json");

/* 🔥 DEBUG (REMOVE IN PRODUCTION IF YOU WANT) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';

if(!$driver_id){
    echo json_encode([
        "success"=>false,
        "message"=>"Driver ID required"
    ]);
    exit;
}

/*
🔥 SIMULATION OVERRIDE (READ-ONLY, ADDITIVE)

While a session is active for this driver the simulated position is the
authoritative bus position here, even when the device holds no real row.
*/

$simulationActive = false;
$simulationOverride = array('active' => false);
$simulationEngineCandidates = array(
    __DIR__ . '/../simulation/lib/simulation_engine.php',
    __DIR__ . '/simulation/lib/simulation_engine.php',
);

foreach ($simulationEngineCandidates as $simulationEngine) {
    if (file_exists($simulationEngine)) {
        require_once $simulationEngine;
        break;
    }
}

if (function_exists('simulation_override_for')) {
    $simulationOverride = simulation_override_for($conn, null, (string)$driver_id);
}

/*
🔥 GET DRIVER CURRENT LOCATION (FIXED TABLE)
*/

$driverStmt = $conn->prepare("
SELECT latitude, longitude 
FROM driver_current_location
WHERE driver_id = ?
ORDER BY updated_at DESC
LIMIT 1
");

if(!$driverStmt){
    if(($simulationOverride['active'] ?? false) !== true){
        echo json_encode([
            "success"=>false,
            "message"=>"Driver location query failed"
        ]);
        exit;
    }

    $driverResult = null;
}else{
    $driverStmt->bind_param("s",$driver_id);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
}

if(($simulationOverride['active'] ?? false) !== true && (!$driverResult || $driverResult->num_rows === 0)){
    echo json_encode([
        "success"=>false,
        "message"=>"Driver location not found"
    ]);
    exit;
}

$driverLoc = ($driverResult && $driverResult->num_rows > 0)
    ? $driverResult->fetch_assoc()
    : null;

$driverLat = isset($driverLoc['latitude']) ? floatval($driverLoc['latitude']) : null;
$driverLng = isset($driverLoc['longitude']) ? floatval($driverLoc['longitude']) : null;

if(($simulationOverride['active'] ?? false) === true){
    $driverLat = (float)$simulationOverride['latitude'];
    $driverLng = (float)$simulationOverride['longitude'];
    $simulationActive = true;
}

/*
🔥 GET TERMINALS
*/

$stmt = $conn->prepare("
SELECT 
b.bus_id,
b.route_name,

t1.latitude AS t1_lat,
t1.longitude AS t1_lng,

t2.latitude AS t2_lat,
t2.longitude AS t2_lng

FROM driver_bus_assignment dba

JOIN buses b ON b.bus_id = dba.bus_id

LEFT JOIN terminals t1 ON t1.id = b.from_terminal_id
LEFT JOIN terminals t2 ON t2.id = b.to_terminal_id

WHERE dba.driver_id = ?
LIMIT 1
");

if(!$stmt){
    echo json_encode([
        "success"=>false,
        "message"=>"Route query failed"
    ]);
    exit;
}

$stmt->bind_param("s",$driver_id);
$stmt->execute();
$result = $stmt->get_result();

if(!$result || $result->num_rows === 0){
    echo json_encode([
        "success"=>false,
        "message"=>"No route found for driver"
    ]);
    exit;
}

$data = $result->fetch_assoc();

/*
🔥 SAFE VALUES
*/

$t1_lat = floatval($data['t1_lat']);
$t1_lng = floatval($data['t1_lng']);
$t2_lat = floatval($data['t2_lat']);
$t2_lng = floatval($data['t2_lng']);

/*
🔥 DISTANCE FUNCTION
*/

function distance($lat1,$lng1,$lat2,$lng2){
    return pow($lat1 - $lat2,2) + pow($lng1 - $lng2,2);
}

/*
🔥 DIRECTION LOGIC (SAFE)
*/

if($driverLat === null || $driverLng === null){

    // 🔥 FALLBACK (NO GPS)
    $start = [
        "lat"=>$t1_lat,
        "lng"=>$t1_lng
    ];

    $destination = [
        "lat"=>$t2_lat,
        "lng"=>$t2_lng
    ];

    $direction = "DEFAULT";

}else{

    $distToT1 = distance($driverLat,$driverLng,$t1_lat,$t1_lng);
    $distToT2 = distance($driverLat,$driverLng,$t2_lat,$t2_lng);

    if($distToT1 < $distToT2){

        // 🔥 DRIVER NEAR TERMINAL 1
        $start = [
            "lat"=>$t1_lat,
            "lng"=>$t1_lng
        ];

        $destination = [
            "lat"=>$t2_lat,
            "lng"=>$t2_lng
        ];

        $direction = "FORWARD";

    }else{

        // 🔥 DRIVER NEAR TERMINAL 2
        $start = [
            "lat"=>$t2_lat,
            "lng"=>$t2_lng
        ];

        $destination = [
            "lat"=>$t1_lat,
            "lng"=>$t1_lng
        ];

        $direction = "REVERSE";
    }
}

/*
🔥 RESPONSE
*/

echo json_encode([
    "success"=>true,
    "start"=>$start,
    "destination"=>$destination,
    "route_name"=>$data['route_name'],
    "direction"=>$direction,
    "simulation"=>$simulationActive,
    "driver_location"=>[
        "lat"=>$driverLat,
        "lng"=>$driverLng
    ]
]);

$conn->close();