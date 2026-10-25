<?php
require_once __DIR__ . '/../config/database.php';

class QR_Bus_Passenger
{
    protected $table = 'bus_passengers';
    private $db;

    public function __construct()
    {
        $this->db = Database::connect(); // PDO connection
    }

    // Get all passengers for a specific bus
    public function getPassengersByBus($bus_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE bus_id = :bus_id ORDER BY seat_number ASC");
        $stmt->execute(['bus_id' => $bus_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single passenger by bus and seat
    public function getPassenger($bus_id, $seat_number)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE bus_id = :bus_id AND seat_number = :seat_number LIMIT 1");
        $stmt->execute(['bus_id' => $bus_id, 'seat_number' => $seat_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Increment scan count for a passenger
    public function incrementScan($bus_id, $seat_number)
    {
        $passenger = $this->getPassenger($bus_id, $seat_number);
        if (!$passenger) return ['error' => 'Passenger not found'];

        if ($passenger['scan_count'] >= $passenger['max_scans']) {
            return ['error' => 'QR scan limit reached'];
        }

        $stmt = $this->db->prepare("UPDATE {$this->table} SET scan_count = scan_count + 1 WHERE id = :id");
        $stmt->execute(['id' => $passenger['id']]);

        $passenger['scan_count']++;
        return ['success' => true, 'passenger' => $passenger];
    }

    // Get the standing seat for a bus
    public function getStandingSeat($bus_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE bus_id = :bus_id AND seat_number = 'Standing' LIMIT 1");
        $stmt->execute(['bus_id' => $bus_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Increment scan for standing seat
    public function incrementStandingScan($bus_id)
    {
        $standing = $this->getStandingSeat($bus_id);
        if (!$standing) return ['error' => 'No standing seat'];

        if ($standing['scan_count'] >= $standing['max_scans']) {
            return ['error' => 'Standing QR limit reached'];
        }

        $stmt = $this->db->prepare("UPDATE {$this->table} SET scan_count = scan_count + 1 WHERE id = :id");
        $stmt->execute(['id' => $standing['id']]);

        $standing['scan_count']++;
        return ['success' => true, 'scan_count' => $standing['scan_count']];
    }

    // Add a new passenger
    public function addPassenger($bus_id, $seat_number, $passenger_type, $name, $boarding_point, $destination, $payment_method, $student_id = null, $hybrid_details = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} 
            (bus_id, seat_number, passenger_type, student_id, name, boarding_point, destination, payment_method, hybrid_payment_details) 
            VALUES (:bus_id, :seat_number, :passenger_type, :student_id, :name, :boarding_point, :destination, :payment_method, :hybrid_details)
        ");

        return $stmt->execute([
            'bus_id' => $bus_id,
            'seat_number' => $seat_number,
            'passenger_type' => $passenger_type,
            'student_id' => $student_id,
            'name' => $name,
            'boarding_point' => $boarding_point,
            'destination' => $destination,
            'payment_method' => $payment_method,
            'hybrid_details' => $hybrid_details
        ]);
    }
}
