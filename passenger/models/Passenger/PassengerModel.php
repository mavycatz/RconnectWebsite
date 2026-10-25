<?php

class Trip {

    private $conn;

    public function __construct($db){
        $this->conn = $db;
    }

    public function getByPassenger($passenger_id){

        $stmt = $this->conn->prepare("
            SELECT 
                id,
                boarding_point,
                destination,
                fare,
                payment_status,
                boarding_time,
                dropoff_time
            FROM passenger_trips
            WHERE passenger_id = ?
            ORDER BY boarding_time DESC
        ");

        $stmt->execute([$passenger_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}