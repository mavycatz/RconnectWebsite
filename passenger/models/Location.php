<?php
namespace Models;

class Location {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Get all locations ordered by name
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM locations ORDER BY location_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Optional: search locations for autocomplete
    public function search($query) {
        $stmt = $this->db->prepare("SELECT * FROM locations WHERE location_name LIKE :q ORDER BY location_name ASC LIMIT 10");
        $stmt->execute(['q' => "%$query%"]);
        return $stmt->fetchAll();
    }
}
