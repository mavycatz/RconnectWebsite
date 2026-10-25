<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Database;

class Driver
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get(); // Use singleton PDO from Core
    }

    // Fetch all available drivers (not assigned to any bus and active)
    public function getAvailableDrivers(): array
    {
        $stmt = $this->db->prepare("
            SELECT driver_id, full_name 
            FROM drivers 
            WHERE (assigned_buses IS NULL OR assigned_buses = '') 
              AND status = 'Active'
            ORDER BY full_name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
