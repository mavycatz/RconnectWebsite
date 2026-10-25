<?php
namespace Models;

use PDO;

class PassengerModel {
    private PDO $db;
    private string $table = 'bus_passengers';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // Fetch passenger by ID
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);
        return $passenger ?: null;
    }

    // Fetch passenger by bus_id + seat_number
    public function getByBusSeat(string $bus_id, string $seat_number): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE bus_id = :bus_id AND seat_number = :seat_number LIMIT 1");
        $stmt->execute([':bus_id' => $bus_id, ':seat_number' => $seat_number]);
        $passenger = $stmt->fetch(PDO::FETCH_ASSOC);
        return $passenger ?: null;
    }
}
