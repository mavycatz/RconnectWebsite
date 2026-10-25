<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusFields.php';

class BusModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    private function hasProblemReportsTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $stmt =
                    $this->conn->query(
                        "SHOW TABLES LIKE 'bus_problem_reports'"
                    );

                $exists = (bool)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    public function getAllBuses(): array
    {
        $problemSelect = '';
        $problemJoin = '';

        if ($this->hasProblemReportsTable()) {
            $problemSelect =
                ",
                    op.report_id AS open_problem_id,
                    op.problem_type AS open_problem_type,
                    op.report_status AS open_problem_status";

            $problemJoin =
                "
                LEFT JOIN (
                    SELECT
                        bpr.id AS report_id,
                        bpr.bus_id,
                        bpr.problem_type,
                        bpr.status AS report_status
                    FROM bus_problem_reports bpr
                    INNER JOIN (
                        SELECT
                            bus_id,
                            MAX(id) AS latest_id
                        FROM bus_problem_reports
                        WHERE status IN ('waiting', 'acknowledged')
                        GROUP BY bus_id
                    ) latest
                        ON latest.latest_id = bpr.id
                ) op
                    ON op.bus_id = b.bus_id";
        }

        $stmt =
            $this->conn->prepare(
                "
                SELECT
                    b.bus_id,
                    b.plate_number,
                    b.bus_type,
                    b.seats,
                    b.standing_capacity,
                    b.accessible_features,
                    b.driver,
                    b.route_name,
                    b.terminal,
                    b.schedule,
                    b.status,
                    b.qr_code
                    {$problemSelect}
                FROM buses b
                {$problemJoin}
                ORDER BY b.bus_id ASC
                "
            );

        $stmt->execute();

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    public function getAssignableBuses(): array
    {
        $buses =
            $this->getAllBuses();

        if (!$this->hasProblemReportsTable()) {
            return $buses;
        }

        return
            array_values(
                array_filter(
                    $buses,
                    static function (array $bus): bool {
                        return empty($bus['open_problem_id']);
                    }
                )
            );
    }

    public function getBus(
        string $bus_id
    ): ?array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT
                    bus_id,
                    plate_number,
                    bus_type,
                    seats,
                    standing_capacity,
                    accessible_features,
                    driver,
                    route_name,
                    terminal,
                    schedule,
                    status,
                    qr_code
                FROM buses
                WHERE bus_id = :bus_id
                "
            );

        $stmt->execute([
            ':bus_id' => $bus_id
        ]);

        $bus =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return $bus ?: null;
    }

    public function addBus(
        array $data
    ): bool
    {
        \Admin\Services\Bus\BusFields::ensureColumns($this->conn);
        [$extraCols, $extraPlaceholders, $extraParams] =
            \Admin\Services\Bus\BusFields::extraInsert($this->conn, $data);

        $stmt =
            $this->conn->prepare(
                "
                INSERT INTO buses
                (bus_id, plate_number, bus_type, seats, standing_capacity,
                 accessible_features, driver, route_name, terminal,
                 schedule, status, qr_code{$extraCols})
                VALUES
                (:bus_id, :plate_number, :bus_type, :seats, :standing_capacity,
                 :accessible_features, :driver, :route_name, :terminal,
                 :schedule, :status, :qr_code{$extraPlaceholders})
                "
            );

        return $stmt->execute([
            ':bus_id' => $data['bus_id'],
            ':plate_number' => $data['plate_number'],
            ':bus_type' => $data['bus_type'] ?? null,
            ':seats' => $data['seats'] ?? 0,
            ':standing_capacity' => $data['standing_capacity'] ?? 0,
            ':accessible_features' => $data['accessible_features'] ?? 'None',
            ':driver' => $data['driver'] ?? 'Unassigned',
            ':route_name' => $data['route_name'] ?? 'TBD',
            ':terminal' => $data['terminal'] ?? 'TBD',
            ':schedule' => $data['schedule'] ?? 'TBD',
            ':status' => $data['status'] ?? 'Active',
            ':qr_code' => $data['qr_code'] ?? 'Pending'
        ] + $extraParams);
    }

    public function editBus(
        string $bus_id,
        array $data
    ): bool
    {
        \Admin\Services\Bus\BusFields::ensureColumns($this->conn);
        [$extraSet, $extraParams] =
            \Admin\Services\Bus\BusFields::extraUpdate($this->conn, $data);

        $stmt =
            $this->conn->prepare(
                "
                UPDATE buses SET
                    plate_number = :plate_number,
                    bus_type = :bus_type,
                    seats = :seats,
                    standing_capacity = :standing_capacity,
                    accessible_features = :accessible_features,
                    driver = :driver,
                    route_name = :route_name,
                    terminal = :terminal,
                    schedule = :schedule,
                    status = :status,
                    qr_code = :qr_code{$extraSet}
                WHERE bus_id = :bus_id
                "
            );

        return $stmt->execute([
            ':plate_number' => $data['plate_number'],
            ':bus_type' => $data['bus_type'] ?? null,
            ':seats' => $data['seats'] ?? 0,
            ':standing_capacity' => $data['standing_capacity'] ?? 0,
            ':accessible_features' => $data['accessible_features'] ?? 'None',
            ':driver' => $data['driver'] ?? 'Unassigned',
            ':route_name' => $data['route_name'] ?? 'TBD',
            ':terminal' => $data['terminal'] ?? 'TBD',
            ':schedule' => $data['schedule'] ?? 'TBD',
            ':status' => $data['status'] ?? 'Active',
            ':qr_code' => $data['qr_code'] ?? 'Pending',
            ':bus_id' => $bus_id
        ] + $extraParams);
    }

    public function getStats(): array
    {
        $total =
            $this->conn
                ->query(
                    "SELECT COUNT(*) FROM buses"
                )
                ->fetchColumn();

        $withQr =
            $this->conn
                ->query(
                    "SELECT COUNT(*) FROM buses WHERE qr_code = 'Generated'"
                )
                ->fetchColumn();

        $withoutQr =
            $this->conn
                ->query(
                    "SELECT COUNT(*) FROM buses WHERE qr_code IS NULL OR qr_code = ''"
                )
                ->fetchColumn();

        $pendingQr =
            $this->conn
                ->query(
                    "SELECT COUNT(*) FROM buses WHERE qr_code = 'Pending'"
                )
                ->fetchColumn();

        return [
            'total' => (int)$total,
            'with_qr' => (int)$withQr,
            'without_qr' => (int)$withoutQr,
            'pending_qr' => (int)$pendingQr
        ];
    }
}
