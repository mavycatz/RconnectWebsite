<?php
class PassengerModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function savePassenger($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO bus_passengers
            (bus_id, license_number, seat_number, passenger_type, student_id, name, boarding_point, destination, payment_method, hybrid_payment_details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssssss",
            $data['bus_id'],
            $data['license_number'],
            $data['seat_number'],
            $data['passenger_type'],
            $data['student_id'],
            $data['name'],
            $data['boarding_point'],
            $data['destination'],
            $data['payment_method'],
            $data['hybrid_payment_details']
        );
        $stmt->execute();
        return $stmt->insert_id;
    }
}
