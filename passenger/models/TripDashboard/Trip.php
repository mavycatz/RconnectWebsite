<?php

// ✅ IMPORT NAMESPACE
use App\Core\Database;

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/Companion/RegularPartyService.php';

class Trip {

    private $db;

    public function __construct(?PDO $db = null){
        // ✅ CORRECT METHOD
        $this->db = $db ?? Database::get();
    }

    public function getByPassenger($passenger_id, $status = null){

        $sql = "
            SELECT
                pt.*, p.full_name AS passenger_name, p.discount_type, p.registration_type,
                bt.route_name,
                pt.boarding_point,
                pt.destination,
                pt.fare,
                pt.payment_status,
                pt.boarding_time,
                pt.dropoff_time,
                d.full_name AS driver_name,
                (SELECT COALESCE(SUM(c.fare), 0) FROM passenger_trips c WHERE c.companion_of = pt.id) AS companions_fare,
                (SELECT COUNT(*) FROM passenger_trips c WHERE c.companion_of = pt.id) AS companion_count
            FROM passenger_trips pt
            JOIN passengers p ON p.id = pt.passenger_id
            LEFT JOIN bus_trips bt
                ON pt.trip_no = bt.id
            LEFT JOIN drivers d
                ON pt.driver_id = d.driver_id
            WHERE pt.passenger_id = ? AND pt.companion_of IS NULL
        ";

        $params = [$passenger_id];

        if ($status) {
            $sql .= " AND pt.payment_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY pt.boarding_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($trips as &$trip) {
            if (strtolower((string) ($trip['registration_type'] ?? '')) === 'one_time') {
                $rows = CompanionService::companionRows($this->db, (int) $trip['id']);
                $breakdown = CompanionService::partyBreakdown($this->db, $trip, $rows);
            } else {
                $rows = RegularPartyService::travellerRows($this->db, (int) $trip['id']);
                $breakdown = RegularPartyService::storedQuote($this->db, $trip, $rows)['breakdown'];
            }
            $trip['companions'] = $rows;
            $trip['breakdown'] = $breakdown;
            $trip['party_total'] = $breakdown['party_total'] ?? null;
            $trip['companion_count'] = count($rows);
            $trip['companions_fare'] = array_sum(array_column($rows, 'fare'));
        }
        unset($trip);
        return $trips;
    }

    public function getRejectedByPassenger($passenger_id){
        return $this->getByPassenger($passenger_id, 'rejected');
    }

}
