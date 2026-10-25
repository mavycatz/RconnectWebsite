<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Models/QR_Bus_Passenger.php';

$data = json_decode(file_get_contents('php://input'), true);

$required = ['bus_id','seat_number','passenger_type','name','boarding_point','destination','payment_method'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['error' => "$field is required"]);
        exit;
    }
}

$bus_id = $data['bus_id'];
$seat_number = $data['seat_number'];
$passenger_type = $data['passenger_type'];
$name = $data['name'];
$boarding_point = $data['boarding_point'];
$destination = $data['destination'];
$payment_method = $data['payment_method'];
$student_id = $data['student_id'] ?? null;
$hybrid_details = $data['hybrid_details'] ?? null;

$model = new QR_Bus_Passenger();
$success = $model->addPassenger($bus_id, $seat_number, $passenger_type, $name, $boarding_point, $destination, $payment_method, $student_id, $hybrid_details);

echo json_encode($success ? ['success'=>true] : ['error'=>'Failed to add passenger']);
