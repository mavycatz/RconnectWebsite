<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';

if(!$driver_id){
 echo json_encode(["success"=>false,"message"=>"Driver ID required"]);
 exit;
}

/*
--------------------------------
HAVERSINE DISTANCE FUNCTION
--------------------------------
*/

function haversineMeters($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/*
--------------------------------
GET ACTIVE TRIP
--------------------------------
*/

$stmt = $conn->prepare("
SELECT 
 t.id,
 t.departure_time,
 t.trip_date,
 t.bus_id,
 t.from_terminal_id,
 t.to_terminal_id
FROM bus_trips t
WHERE t.driver_id = ?
AND t.status IN ('Departed', 'Started', 'Active', 'Ongoing')
ORDER BY t.id DESC
LIMIT 1
");

$stmt->bind_param("s",$driver_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if(!$data){
 echo json_encode([
  "success"=>true,
  "trip_end"=>null,
  "stops"=>[]
 ]);
 exit;
}

$trip_no = $data['id'];
$bus_id = $data['bus_id'];

/*
--------------------------------
GET DRIVER CURRENT LOCATION + SPEED
--------------------------------
*/

$driverLat = null;
$driverLng = null;
$speed = 11.1;

$locStmt = $conn->prepare("
    SELECT latitude, longitude, speed
    FROM driver_current_location
    WHERE driver_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
");
$locStmt->bind_param("s", $driver_id);
$locStmt->execute();
$locResult = $locStmt->get_result()->fetch_assoc();
$locStmt->close();

if ($locResult) {
    $driverLat = (float)$locResult['latitude'];
    $driverLng = (float)$locResult['longitude'];
    if ($locResult['speed'] !== null && (float)$locResult['speed'] > 0) {
        $speed = (float)$locResult['speed'];
    }
}

/*
--------------------------------
LOAD ALL TERMINALS FOR COORDINATE LOOKUP
--------------------------------
*/

$terminals = [];
$terminalCoords = [];
$termResult = $conn->query("SELECT terminal_code, terminal_name, latitude, longitude FROM terminals WHERE status = 'Active'");
while ($tRow = $termResult->fetch_assoc()) {
    $code = strtoupper(trim($tRow['terminal_code']));
    $name = strtoupper(trim($tRow['terminal_name']));
    $terminals[$code] = $tRow;
    $terminals[$name] = $tRow;
    $terminalCoords[] = $tRow;
}

/*
--------------------------------
FIND TERMINAL COORDINATES BY PARTIAL NAME MATCH
--------------------------------
*/

function findTerminalCoords($destination, $terminals, $terminalCoords) {
    $destKey = strtoupper(trim($destination));
    if (isset($terminals[$destKey])) {
        return $terminals[$destKey];
    }
    foreach ($terminalCoords as $t) {
        $code = strtoupper(trim($t['terminal_code']));
        $name = strtoupper(trim($t['terminal_name']));
        if (strpos($destKey, $code) !== false || strpos($code, $destKey) !== false) {
            return $t;
        }
        if (strpos($destKey, $name) !== false || strpos($name, $destKey) !== false) {
            return $t;
        }
    }
    return null;
}

/*
--------------------------------
AGGREGATE PASSENGER DESTINATIONS
--------------------------------
*/

$stops = [];
$stopEta = "";
$stopEtaSeconds = null;

$passStmt = $conn->prepare("
 SELECT 
  pt.destination,
  SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 0 ELSE 1 END) AS passenger_count,
  SUM(CASE WHEN COALESCE(p.type, 'passenger') = 'other' THEN 1 ELSE 0 END) AS other_count
 FROM passenger_trips pt
 LEFT JOIN passengers p ON pt.passenger_id = p.id
 WHERE pt.trip_no = ?
   AND pt.bus_no = ?
   AND pt.dropoff_time IS NULL
   AND pt.archived_status = 'no'
   AND (pt.payment_status IS NULL OR pt.payment_status != 'Paid')
   AND pt.destination IS NOT NULL
   AND pt.destination != ''
 GROUP BY pt.destination
 ORDER BY MIN(pt.id) ASC
");

if(!$passStmt){
 echo json_encode(["success"=>false,"message"=>"Prepare failed: " . $conn->error]);
 exit;
}

$passStmt->bind_param("is", $trip_no, $bus_id);
$passStmt->execute();
$result = $passStmt->get_result();
while($row = $result->fetch_assoc()){
 $destination = $row['destination'];
 $stopEta = "";
 $stopEtaSeconds = null;

  if ($driverLat !== null && $driverLng !== null) {
   $term = findTerminalCoords($destination, $terminals, $terminalCoords);
   if ($term) {
    $termLat = (float)$term['latitude'];
    $termLng = (float)$term['longitude'];
    $distM = haversineMeters($driverLat, $driverLng, $termLat, $termLng);
    $stopEtaSeconds = $distM / $speed;
    $mins = floor($stopEtaSeconds / 60);
    $hrs = floor($mins / 60);
    $mins = $mins % 60;
    if ($mins < 1) $mins = 1;
    if ($hrs > 0) {
     $stopEta = $hrs . 'h ' . $mins . 'min';
    } else {
     $stopEta = $mins . 'min';
    }
   }
  }

 $stops[] = [
  "station" => $destination,
  "eta" => $stopEta,
  "passengers" => (int)$row['passenger_count'],
  "others" => (int)$row['other_count']
 ];
}
$passStmt->close();

/*
--------------------------------
CALCULATE TRIP END TIME
--------------------------------
*/

$trip_start = $data['trip_date'] . ' ' . $data['departure_time'];
$trip_end = null;

if ($stopEtaSeconds !== null && $driverLat !== null) {
    $trip_end = date("Y-m-d H:i:s", time() + (int)$stopEtaSeconds);
} else {
    $stopCount = count($stops);
    $minutes_per_stop = 15;
    $trip_duration_minutes = max(($stopCount - 1) * $minutes_per_stop, 30);
    $trip_end = date("Y-m-d H:i:s", strtotime($trip_start . " +{$trip_duration_minutes} minutes"));
}

/*
--------------------------------
RESPONSE
--------------------------------
*/

echo json_encode([
 "success"=>true,
 "trip_end"=>$trip_end,
 "stops"=>$stops
]);

$conn->close();
