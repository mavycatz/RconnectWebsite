<?php
namespace Models;

use PDO;

class Fare {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Fetch all fares with location names
    public function getAll() {
        $stmt = $this->conn->prepare("
            SELECT 
                f.id,
                f.route_from,
                f.route_to,
                l.location_name AS location,
                f.fare_regular,
                f.fare_student,
                f.fare_special
            FROM fares f
            LEFT JOIN locations l ON f.location_id = l.id
            ORDER BY f.route_from, f.route_to
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
