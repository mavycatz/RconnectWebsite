<?php

// ✅ IMPORT THE NAMESPACE
use App\Core\Database;

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/models/TripDashboard/Trip.php';

class TripController {

    private $db;

    public function __construct(){
        // ✅ USE CORRECT METHOD NAME (get, not getInstance)
        $this->db = Database::get();
    }

    public function getTrips($passenger_id){
        $model = new Trip($this->db);
        return $model->getByPassenger($passenger_id);
    }

    public function getRejectedTrips($passenger_id){
        $model = new Trip($this->db);
        return $model->getRejectedByPassenger($passenger_id);
    }
}