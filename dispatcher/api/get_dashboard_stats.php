<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/../../mobile/config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

$db = new Database();
$conn = $db->connect();

$today = date('Y-m-d');

$stats = [];
$stats['total_trips'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today'")->fetch_assoc()['c'];
$stats['completed'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today' AND status = 'Completed'")->fetch_assoc()['c'];
$stats['departed'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today' AND status = 'Departed'")->fetch_assoc()['c'];
$stats['scheduled'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today' AND status = 'Scheduled'")->fetch_assoc()['c'];
$stats['cancelled'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today' AND status = 'Cancelled'")->fetch_assoc()['c'];
$stats['delayed'] = (int)$conn->query("SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = '$today' AND status = 'Delayed'")->fetch_assoc()['c'];
$stats['active_buses'] = (int)$conn->query("SELECT COUNT(*) as c FROM buses WHERE status = 'Active'")->fetch_assoc()['c'];
$stats['active_inspectors'] = (int)$conn->query("SELECT COUNT(*) as c FROM inspector_assignments WHERE DATE(assigned_at) = '$today' AND status = 'Checked-In'")->fetch_assoc()['c'];

echo json_encode(["success" => true, "data" => $stats]);
$conn->close();
