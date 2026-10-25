<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_id = $_GET['bus_id'] ?? '';

if(!$bus_id){
echo json_encode([
"success"=>false,
"message"=>"bus_id required"
]);
exit;
}

/*
GET INSPECTOR ASSIGNED TO BUS
*/

$assignQuery = $conn->query("
SELECT inspector_id
FROM inspector_assignments
WHERE bus_id='$bus_id'
ORDER BY assigned_at DESC
LIMIT 1
");

if(!$assignQuery){
echo json_encode([
"success"=>false,
"error"=>$conn->error
]);
exit;
}

$assign = $assignQuery->fetch_assoc();

if(!$assign){
echo json_encode([
"success"=>false,
"message"=>"No inspector assigned"
]);
exit;
}

$inspector_id = $assign['inspector_id'];

/*
GET LATEST INSPECTOR LOCATION
*/

$locQuery = $conn->query("
SELECT latitude, longitude
FROM inspectors_location
WHERE inspector_id='$inspector_id'
ORDER BY recorded_at DESC
LIMIT 1
");

if(!$locQuery){
echo json_encode([
"success"=>false,
"error"=>$conn->error
]);
exit;
}

$loc = $locQuery->fetch_assoc();

if(!$loc){
echo json_encode([
"success"=>false,
"message"=>"Inspector location not found"
]);
exit;
}

echo json_encode([
"success"=>true,
"inspector_id"=>$inspector_id,
"latitude"=>$loc['latitude'],
"longitude"=>$loc['longitude']
]);

$conn->close();