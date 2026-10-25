<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class BusPassenger {
    public static function getTotalPassengers() {
        $db = Database::get();
        $stmt = $db->query("SELECT COUNT(*) as total FROM bus_passengers");
        return (int)$stmt->fetch()['total'];
    }

    public static function getPassengerDistribution() {
        $db = Database::get();
        $stmt = $db->query("SELECT passenger_type, COUNT(*) as total 
                            FROM bus_passengers 
                            GROUP BY passenger_type");
        return $stmt->fetchAll();
    }
}