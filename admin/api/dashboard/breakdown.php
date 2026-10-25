<?php

require_once __DIR__ . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
use App\Core\Database;

header('Content-Type: application/json');

$db = Database::get();
$type = $_GET['type'] ?? '';

$data = [];
$insights = [];

try{

/* GROSS INCOME */
if($type === "gross"){

$sql = "
SELECT driver, SUM(total) as total FROM (
SELECT d.full_name as driver, SUM(pt.fare) as total
FROM passenger_trips pt
INNER JOIN drivers d ON pt.driver_id = d.driver_id
WHERE pt.payment_status = 'Paid'
GROUP BY d.full_name
UNION ALL
SELECT pth.drivers_full_name as driver, SUM(pth.fare) as total
FROM passenger_trip_history pth
WHERE pth.drivers_full_name IS NOT NULL AND TRIM(pth.drivers_full_name) != ''
GROUP BY pth.drivers_full_name
) combined
WHERE driver IS NOT NULL AND TRIM(driver) != ''
GROUP BY driver
ORDER BY total DESC
";

$stmt = $db->query($sql);

$totalAll = 0;
$rows = $stmt->fetchAll();

foreach($rows as $r){ $totalAll += $r['total']; }

foreach($rows as $row){
$data[$row['driver']] = (float)$row['total'];
}

if(count($rows)){
$top = $rows[0];
$percent = $totalAll > 0 ? round(($top['total']/$totalAll)*100,2) : 0;
$insights[] = "{$top['driver']} is the top earning driver contributing {$percent}% of total revenue.";
$insights[] = "Total system revenue is ₱".number_format($totalAll,2).".";
}

}

/* EXPENSES */
else if($type === "expenses"){

$sql = "
SELECT
SUM(tor.fuel_cost) as fuel,
SUM(tor.other_expenses) as other
FROM trip_operations_reports tor
";

$row = $db->query($sql)->fetch();

$data = [
"Fuel" => (float)$row['fuel'],
"Other Expenses" => (float)$row['other']
];

$total = array_sum($data);

if($total > 0){
foreach($data as $k=>$v){
$percent = round(($v/$total)*100,2);
if($percent > 20){
$insights[] = "$k accounts for {$percent}% of total expenses.";
}
}
$insights[] = "Total expenses amount to ₱".number_format($total,2).".";
}

}

/* PROFIT */
else if($type === "profit"){

$sql = "
SELECT route_name, SUM(fare) as profit FROM (
SELECT
CONCAT(pt.boarding_point, ' → ', pt.destination) as route_name,
pt.fare as fare
FROM passenger_trips pt
WHERE pt.payment_status = 'Paid'
AND pt.boarding_point IS NOT NULL
AND pt.destination IS NOT NULL
UNION ALL
SELECT
CONCAT(pth.boarding_point, ' → ', pth.destination) as route_name,
pth.fare as fare
FROM passenger_trip_history pth
WHERE pth.boarding_point IS NOT NULL AND TRIM(pth.boarding_point) != ''
AND pth.destination IS NOT NULL AND TRIM(pth.destination) != ''
) combined
GROUP BY route_name
ORDER BY profit DESC
";

$stmt = $db->query($sql);
$rows = $stmt->fetchAll();

foreach($rows as $row){
$data[$row['route_name']] = (float)$row['profit'];
}

if(count($rows)){
$top = $rows[0];
$insights[] = "Most profitable route is {$top['route_name']} generating ₱".number_format($top['profit'],2).".";
}

}

/* TRIPS */
else if($type === "trips"){

$sql = "
SELECT day, SUM(total) as total FROM (
SELECT DATE(tor.trip_date) as day, COUNT(*) as total
FROM trip_operations_reports tor
GROUP BY DATE(tor.trip_date)
UNION ALL
SELECT DATE(pth.trip_date) as day, COUNT(*) as total
FROM passenger_trip_history pth
WHERE pth.trip_date IS NOT NULL
GROUP BY DATE(pth.trip_date)
) combined
GROUP BY day
ORDER BY day ASC
";

$stmt = $db->query($sql);
$rows = $stmt->fetchAll();

foreach($rows as $row){
$data[$row['day']] = (int)$row['total'];
}

if(count($rows) >= 2){
$first = $rows[0]['total'];
$last = end($rows)['total'];

if($last > $first){
$insights[] = "Trip volume is increasing over time.";
}else if($last < $first){
$insights[] = "Trip volume shows a declining trend.";
}else{
$insights[] = "Trip volume is stable.";
}
}

}

/* BUSES */
else if($type === "buses"){

$sql = "SELECT status, COUNT(*) as total FROM buses GROUP BY status";
$stmt = $db->query($sql);
$rows = $stmt->fetchAll();

$total = 0;

foreach($rows as $row){
$data[$row['status']] = (int)$row['total'];
$total += $row['total'];
}

if($total > 0){
$active = $data['Active'] ?? 0;
$percent = round(($active/$total)*100,2);
$insights[] = "{$percent}% of buses are currently active.";
}

}

echo json_encode([
"success"=>true,
"data"=>$data,
"insights"=>$insights
]);

}catch(Exception $e){

echo json_encode([
"success"=>false,
"message"=>$e->getMessage()
]);

}
