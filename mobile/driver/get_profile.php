<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';

if(!$driver_id){
 echo json_encode([
  "success"=>false,
  "message"=>"driver_id required"
 ]);
 exit;
}

/*
STEP 1: DRIVER INFO
*/

$stmt = $conn->prepare("
SELECT 
 full_name,
 driver_id,
 photo
FROM drivers
WHERE driver_id=?
");

$stmt->bind_param("s",$driver_id);
$stmt->execute();

$driver = $stmt->get_result()->fetch_assoc();

if(!$driver){
 echo json_encode([
  "success"=>false,
  "message"=>"Driver not found"
 ]);
 exit;
}

/*
🔥 FIX: CONVERT PHOTO TO FULL URL
*/

$base_url = "https://rconnecttms.online";

$photo = null;

if(!empty($driver['photo'])){
  $photo = $base_url . $driver['photo'];
}

/*
STEP 2: GET ASSIGNED BUS
*/

$stmt = $conn->prepare("
SELECT bus_id
FROM driver_bus_assignment
WHERE driver_id=?
LIMIT 1
");

$stmt->bind_param("s",$driver_id);
$stmt->execute();

$bus = $stmt->get_result()->fetch_assoc();
$assigned_bus = $bus['bus_id'] ?? null;

/*
STEP 3: GET CURRENT TRIP (ROUTE + STATUS)
*/

$route_name = null;
$status = "Scheduled";

if($assigned_bus){

 $stmt = $conn->prepare("
 SELECT route_name, status
 FROM bus_trips
 WHERE driver_id=?
 AND bus_id=?
 ORDER BY created_at DESC
 LIMIT 1
 ");

 $stmt->bind_param("ss",$driver_id,$assigned_bus);
 $stmt->execute();

 $trip = $stmt->get_result()->fetch_assoc();

 if($trip){
   $route_name = $trip['route_name'];
   $status = $trip['status'] ?? "Scheduled";
 }
}

/*
STEP 4: STATS
*/

$total_passengers = 0;
$total_revenue = 0;

if($assigned_bus){

 $stmt = $conn->prepare("
 SELECT 
 COUNT(*) as total_passengers,
 SUM(fare) as total_revenue
 FROM passenger_trips
 WHERE bus_no=?
 AND DATE(boarding_time)=CURDATE()
 ");

 $stmt->bind_param("s",$assigned_bus);
 $stmt->execute();

 $stats = $stmt->get_result()->fetch_assoc();

 $total_passengers = $stats['total_passengers'] ?? 0;
 $total_revenue = $stats['total_revenue'] ?? 0;
}

/*
FINAL RESPONSE
*/

echo json_encode([
 "success"=>true,
 "driver"=>[
   "full_name"=>$driver['full_name'],
   "driver_id"=>$driver['driver_id'],
   "assigned_buses"=>$assigned_bus,
   "routes"=>$route_name,
   "photo"=>$photo, // ✅ FIXED
   "status"=>$status
 ],
 "stats"=>[
   "total_passengers"=>$total_passengers,
   "total_revenue"=>$total_revenue
 ]
]);

$conn->close();