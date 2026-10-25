<?php
declare(strict_types=1);

namespace App\Models;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../../admin/services/Bus/BusFields.php';

use PDO;
use Exception;
use App\Core\Database;
use Admin\Services\Bus\BusFields;

class Bus
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::get();
    }

    public function fetchAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM buses ORDER BY bus_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchByBusId(string $bus_id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM buses WHERE bus_id = :bus_id");
        $stmt->execute([':bus_id' => $bus_id]);
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);
        return $bus ?: null;
    }

    public function insert(array $data): bool
    {
        BusFields::ensureColumns($this->db);
        [$extraCols, $extraPlaceholders, $extraParams] = BusFields::extraInsert($this->db, $data);
        $stmt = $this->db->prepare("
    INSERT INTO buses
    (
        bus_id,
        plate_number,
        bus_type,
        seats,
        standing_capacity,
        accessible_features,
        driver,
        from_terminal_id,
        to_terminal_id,
        route_name,
        terminal,
        schedule,
        status,
        qr_code{$extraCols}
    )
    VALUES
    (
        :bus_id,
        :plate_number,
        :bus_type,
        :seats,
        :standing_capacity,
        :accessible_features,
        :driver,
        :from_terminal_id,
        :to_terminal_id,
        :route_name,
        :terminal,
        :schedule,
        :status,
        :qr_code{$extraPlaceholders}
    )
");
return $stmt->execute([
    ':bus_id' => $data['bus_id'],
    ':plate_number' => $data['plate_number'],
    ':bus_type' => $data['bus_type'] ?? null,
    ':seats' => $data['seats'] ?? 0,
    ':standing_capacity' => $data['standing_capacity'] ?? 0,
    ':accessible_features' => $data['accessible_features'] ?? 'None',
    ':driver' => $data['driver'] ?? 'Unassigned',

    ':from_terminal_id' => $data['from_terminal_id'] ?? null,
    ':to_terminal_id' => $data['to_terminal_id'] ?? null,

    ':route_name' => $data['route_name'] ?? 'TBD',
    ':terminal' => $data['terminal'] ?? 'TBD',
    ':schedule' => $data['schedule'] ?? 'TBD',
    ':status' => $data['status'] ?? 'Active',
    ':qr_code' => $data['qr_code'] ?? 'Pending'
] + $extraParams);
    }

    public function update(string $bus_id, array $data): bool
    {
        BusFields::ensureColumns($this->db);
        [$extraSet, $extraParams] = BusFields::extraUpdate($this->db, $data);
$stmt = $this->db->prepare("
    UPDATE buses SET
        plate_number = :plate_number,
        bus_type = :bus_type,
        seats = :seats,
        standing_capacity = :standing_capacity,
        accessible_features = :accessible_features,
        driver = :driver,
        from_terminal_id = :from_terminal_id,
        to_terminal_id = :to_terminal_id,
        route_name = :route_name,
        terminal = :terminal,
        schedule = :schedule,
        status = :status,
        qr_code = :qr_code{$extraSet}
    WHERE bus_id = :bus_id
");

return $stmt->execute([
    ':plate_number' => $data['plate_number'],
    ':bus_type' => $data['bus_type'] ?? null,
    ':seats' => $data['seats'] ?? 0,
    ':standing_capacity' => $data['standing_capacity'] ?? 0,
    ':accessible_features' => $data['accessible_features'] ?? 'None',
    ':driver' => $data['driver'] ?? 'Unassigned',

    ':from_terminal_id' => $data['from_terminal_id'] ?? null,
    ':to_terminal_id' => $data['to_terminal_id'] ?? null,

    ':route_name' => $data['route_name'] ?? 'TBD',
    ':terminal' => $data['terminal'] ?? 'TBD',
    ':schedule' => $data['schedule'] ?? 'TBD',
    ':status' => $data['status'] ?? 'Active',
    ':qr_code' => $data['qr_code'] ?? 'Pending',
    ':bus_id' => $bus_id
] + $extraParams);
    }

    public function generatePermanentQR(string $bus_id): ?string
    {
        $bus = $this->fetchByBusId($bus_id);
        if (!$bus) return null;

        if ($bus['qr_code'] === 'Generated') {
            return $bus['qr_data'] ?? null;
        }

        $qrData = json_encode([
            'bus_id' => $bus['bus_id'],
            'plate_number' => $bus['plate_number'],
            'seats' => (int)$bus['seats'],
            'standing_capacity' => (int)$bus['standing_capacity']
        ]);

        $stmt = $this->db->prepare("
            UPDATE buses 
            SET qr_code = 'Generated', qr_data = :qr_data 
            WHERE bus_id = :bus_id
        ");

        $stmt->execute([
            ':qr_data' => $qrData,
            ':bus_id' => $bus_id
        ]);

        return $qrData;
    }

    public function createSeatsForBus(string $bus_id): bool
    {
        $bus = $this->fetchByBusId($bus_id);
        if (!$bus) return false;

        $totalSeats = (int)$bus['seats'];
        $standingCapacity = (int)$bus['standing_capacity'];

        try {
            $this->db->beginTransaction();

            // Delete existing seats
            $deleteStmt = $this->db->prepare("
                DELETE FROM bus_seats WHERE bus_id = :bus_id
            ");
            $deleteStmt->execute([':bus_id' => $bus_id]);

            // Insert numeric seats
            $seatStmt = $this->db->prepare("
                INSERT INTO bus_seats
                (bus_id, seat_number, type, status, for_pwd, for_senior, for_pregnant, is_accessible)
                VALUES
                (:bus_id, :seat_number, 'Seat', 'Available', 0, 0, 0, 0)
            ");

            for ($i = 1; $i <= $totalSeats; $i++) {
                $seatStmt->execute([
                    ':bus_id' => $bus_id,
                    ':seat_number' => (string)$i
                ]);
            }

            // Insert single Standing row
            if ($standingCapacity > 0) {
                $standingStmt = $this->db->prepare("
                    INSERT INTO bus_seats
                    (bus_id, seat_number, type, status, for_pwd, for_senior, for_pregnant, is_accessible)
                    VALUES
                    (:bus_id, 'Standing', 'Standing', 'Available', 0, 0, 0, 0)
                ");
                $standingStmt->execute([':bus_id' => $bus_id]);
            }

            // 🔥 Assign Reserved Seats Automatically
            $reservedStmt = $this->db->prepare("
                SELECT id FROM bus_seats
                WHERE bus_id = :bus_id
                AND type = 'Seat'
                ORDER BY CAST(seat_number AS UNSIGNED) ASC
                LIMIT 3
            ");
            $reservedStmt->execute([':bus_id' => $bus_id]);
            $reservedSeats = $reservedStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($reservedSeats) >= 3) {
                $this->db->prepare("UPDATE bus_seats SET for_pwd = 1, is_accessible = 1 WHERE id = ?")
                    ->execute([$reservedSeats[0]['id']]);

                $this->db->prepare("UPDATE bus_seats SET for_senior = 1, is_accessible = 1 WHERE id = ?")
                    ->execute([$reservedSeats[1]['id']]);

                $this->db->prepare("UPDATE bus_seats SET for_pregnant = 1, is_accessible = 1 WHERE id = ?")
                    ->execute([$reservedSeats[2]['id']]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
