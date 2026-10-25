<?php
require_once __DIR__ . '/../config/database.php';

class BusSeat {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function getByBus($bus_id) {
        $stmt = $this->db->prepare("
            SELECT id, seat_number, qr_code, status, type, is_accessible, for_pwd, for_senior
            FROM bus_seats
            WHERE bus_id = ?
            ORDER BY seat_number ASC
        ");
        $stmt->execute([$bus_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
