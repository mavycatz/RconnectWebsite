<?php
namespace Models;

use App\Core\Database;
use PDO;

class BusPassenger {

    private PDO $conn;

    public $bus_id;
    public $seat_number;
    public $passenger_type;
    public $student_id;
    public $license_number;
    public $name;
    public $boarding_point;
    public $destination;
    public $fare;
    public $payment_method;
    public $hybrid_payment_details;

    public function __construct(array $data = []) {
        $this->conn = Database::get();

        $this->bus_id = $data['bus_id'] ?? '';
        $this->seat_number = $data['seat_number'] ?? '';
        $this->passenger_type = ucfirst($data['passenger_type'] ?? '');
        $this->student_id = $data['student_id'] ?? null;
        $this->license_number = $data['license_number'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->boarding_point = $data['boarding_point'] ?? '';
        $this->destination = $data['destination'] ?? '';
        $this->fare = (float)($data['fare'] ?? 0.0);
        $this->payment_method = ucfirst($data['payment_method'] ?? '');
        $this->hybrid_payment_details = $data['hybrid_payment_details'] ?? null;
    }

    public function save(): int {
        $stmt = $this->conn->prepare("
            INSERT INTO bus_passengers 
            (bus_id, seat_number, passenger_type, student_id, license_number, name, boarding_point, destination, fare, payment_method, hybrid_payment_details)
            VALUES 
            (:bus_id, :seat_number, :passenger_type, :student_id, :license_number, :name, :boarding_point, :destination, :fare, :payment_method, :hybrid_payment_details)
        ");

        $success = $stmt->execute([
            ':bus_id' => $this->bus_id,
            ':seat_number' => $this->seat_number,
            ':passenger_type' => $this->passenger_type,
            ':student_id' => $this->student_id,
            ':license_number' => $this->license_number,
            ':name' => $this->name,
            ':boarding_point' => $this->boarding_point,
            ':destination' => $this->destination,
            ':fare' => $this->fare,
            ':payment_method' => $this->payment_method,
            ':hybrid_payment_details' => $this->hybrid_payment_details
        ]);

        if (!$success) {
            $err = $stmt->errorInfo();
            throw new \Exception("Failed to insert passenger: {$err[2]}");
        }

        return (int)$this->conn->lastInsertId();
    }

    public static function getById($id): ?array {
        $conn = Database::get();
        $stmt = $conn->prepare("SELECT * FROM bus_passengers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
