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
STEP 1: GET DRIVER BUS
*/

$stmt = $conn->prepare("
SELECT bus_id 
FROM driver_bus_assignment
WHERE driver_id=?
LIMIT 1
");

$stmt->bind_param("s",$driver_id);
$stmt->execute();
$res = $stmt->get_result();
$bus = $res->fetch_assoc();

if(!$bus){
    echo json_encode([
        "success"=>false,
        "message"=>"No bus assigned"
    ]);
    exit;
}

$bus_id = $bus['bus_id'];

/*
STEP 2: GET ACTIVE TRIP (DEPARTED)
*/

$stmt = $conn->prepare("
SELECT *
FROM bus_trips
WHERE driver_id=?
AND bus_id=?
AND status='Departed'
ORDER BY created_at DESC
LIMIT 1
");

$stmt->bind_param("ss",$driver_id,$bus_id);
$stmt->execute();
$res = $stmt->get_result();
$trip = $res->fetch_assoc();

if(!$trip){
    echo json_encode([
        "success"=>true,
        "bus_id"=>$bus_id,
        "trip"=>null,
        "stats"=>[
            "passengers"=>0,
            "revenue"=>0
        ]
    ]);
    exit;
}

$trip_id = $trip['id'];

/*
STEP 3: GET PASSENGERS + REVENUE (THIS TRIP)
*/

$stmt = $conn->prepare("
SELECT 
COUNT(*) as total_passengers,
SUM(fare) as total_revenue
FROM passenger_trips
WHERE bus_no=?
AND DATE(boarding_time)=CURDATE()
");

$stmt->bind_param("s",$bus_id);
$stmt->execute();
$res = $stmt->get_result();
$stats = $res->fetch_assoc();

/*
FINAL RESPONSE
*/

echo json_encode([
    "success"=>true,
    "bus_id"=>$bus_id,
    "trip"=>[
        "trip_id"=>$trip['id'],
        "route_name"=>$trip['route_name'],
        "status"=>$trip['status'],
        "departure_time"=>$trip['departure_time'],
        "trip_type"=>$trip['trip_type']
    ],
    "stats"=>[
        "passengers"=>$stats['total_passengers'] ?? 0,
        "revenue"=>$stats['total_revenue'] ?? 0
    ]
]);

$conn->close();