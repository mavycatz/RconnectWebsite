<?php

/**
 * Dispatcher accountability + monitoring model (read-only over operational
 * records; accountability events are written to audit_trail only).
 *
 * Used by the admin Dispatcher Monitoring section and by the dispatcher
 * arrival flow to cross-validate passenger counts at trip completion.
 * Every query is schema-adaptive: missing tables/columns degrade instead of
 * fataling.
 */
class DispatcherMonitoringModel
{
    private PDO $conn;

    /** @var array<string,bool> */
    private array $tableCache = [];

    /** @var array<string,array<string,string>> */
    private array $columnCache = [];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /* ---------------------------------------------------------------------
       Schema helpers
       --------------------------------------------------------------------- */

    public function tableExists(string $table): bool
    {
        $key = strtolower($table);
        if (array_key_exists($key, $this->tableCache)) {
            return $this->tableCache[$key];
        }
        $exists = false;
        try {
            $stmt = $this->conn->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            $exists = (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $exists = false;
        }
        $this->tableCache[$key] = $exists;
        return $exists;
    }

    public function columns(string $table): array
    {
        $key = strtolower($table);
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }
        $cols = [];
        try {
            $safe = str_replace('`', '', $table);
            $stmt = $this->conn->query("SHOW COLUMNS FROM `$safe`");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cols[strtolower((string)$row['Field'])] = (string)$row['Field'];
                }
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        $this->columnCache[$key] = $cols;
        return $cols;
    }

    /* ---------------------------------------------------------------------
       Accountability trail
       --------------------------------------------------------------------- */

    public function audit(array $actor, string $action, $recordId = null, ?string $description = null, array $previous = [], array $new = [], string $status = 'Success'): bool
    {
        if (!$this->tableExists('audit_trail')) {
            return false;
        }
        $cols = $this->columns('audit_trail');
        if (empty($cols)) {
            return false;
        }

        $actorId = $actor['id'] ?? null;
        $userId = is_numeric($actorId) ? (int)$actorId : null;
        $username = (string)($actor['name'] ?? ($actorId !== null ? (string)$actorId : ''));
        $role = (string)($actor['role'] ?? 'admin');

        $values = [
            'user_id'         => $userId,
            'username'        => $username !== '' ? $username : null,
            'user_role'       => $role !== '' ? $role : null,
            'module'          => 'Dispatcher',
            'action'          => $action,
            'record_id'       => ($recordId !== null && $recordId !== '') ? (string)$recordId : null,
            'description'     => $description,
            'previous_values' => !empty($previous) ? json_encode($previous, JSON_UNESCAPED_UNICODE) : null,
            'new_values'      => !empty($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'      => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'status'          => in_array($status, ['Success', 'Failed'], true) ? $status : 'Success',
            'is_read'         => 0,
        ];

        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach ($values as $col => $val) {
            if (!isset($cols[strtolower($col)])) {
                continue;
            }
            $insertCols[] = '`' . $cols[strtolower($col)] . '`';
            $placeholders[] = ':' . $col;
            $params[':' . $col] = $val;
        }
        if (empty($insertCols)) {
            return false;
        }

        try {
            $sql = 'INSERT INTO `audit_trail` (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->conn->prepare($sql);
            return (bool)$stmt->execute($params);
        } catch (\Throwable $e) {
            error_log('DispatcherMonitoringModel audit failed: ' . $e->getMessage());
            return false;
        }
    }

    private function latestAuditByAction(string $action, int $tripId): ?array
    {
        if (!$this->tableExists('audit_trail')) {
            return null;
        }
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM audit_trail
                 WHERE module = 'Dispatcher' AND action = :a AND record_id = :r
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':a' => $action, ':r' => (string)$tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function hasOpenDiscrepancy(int $tripId): bool
    {
        if (!$this->tableExists('audit_trail')) {
            return false;
        }
        try {
            $stmt = $this->conn->prepare(
                "SELECT action FROM audit_trail
                 WHERE module = 'Dispatcher' AND record_id = :r
                   AND action IN ('Passenger Count Discrepancy', 'Passenger Count Resolved')
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':r' => (string)$tripId]);
            $action = (string)$stmt->fetchColumn();
            return $action === 'Passenger Count Discrepancy';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function auditIdToTripId(int $auditId): ?int
    {
        if ($auditId <= 0 || !$this->tableExists('audit_trail')) {
            return null;
        }
        try {
            $stmt = $this->conn->prepare(
                "SELECT record_id FROM audit_trail WHERE id = :id AND module = 'Dispatcher' LIMIT 1"
            );
            $stmt->execute([':id' => $auditId]);
            $recordId = $stmt->fetchColumn();
            return is_numeric($recordId) ? (int)$recordId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ---------------------------------------------------------------------
       Passenger-count resolution and discrepancy detection
       --------------------------------------------------------------------- */

    public function tripRow(int $tripId): ?array
    {
        if ($tripId <= 0 || !$this->tableExists('bus_trips')) {
            return null;
        }
        try {
            $stmt = $this->conn->prepare('SELECT * FROM bus_trips WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function passengerCount(int $tripId, ?string $start = null, ?string $end = null): int
    {
        if (!$this->tableExists('passenger_trips')) {
            return 0;
        }
        $cols = $this->columns('passenger_trips');
        if (!isset($cols['trip_no'])) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM passenger_trips WHERE trip_no = :t';
        $params = [':t' => $tripId];
        if ($start !== null && $end !== null && isset($cols['boarding_time'])) {
            $sql .= ' AND boarding_time BETWEEN :s AND :e';
            $params[':s'] = $start;
            $params[':e'] = $end;
        }
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Inspector monitoring window for a trip (checked-in through checked-out),
     * or null when no assignment window exists.
     */
    private function inspectorWindow(int $tripId, string $busId): ?array
    {
        if (!$this->tableExists('inspector_assignments')) {
            return null;
        }
        $cols = $this->columns('inspector_assignments');
        if (!isset($cols['assigned_at'])) {
            return null;
        }
        $where = [];
        $params = [];
        if (isset($cols['trip_id'])) {
            $where[] = 'trip_id = :t';
            $params[':t'] = $tripId;
        }
        if (isset($cols['bus_id']) && $busId !== '') {
            $where[] = 'bus_id = :b';
            $params[':b'] = $busId;
        }
        if (empty($where)) {
            return null;
        }
        try {
            $stmt = $this->conn->prepare(
                'SELECT assigned_at, ' . (isset($cols['completed_at']) ? 'completed_at' : 'NULL AS completed_at') . '
                 FROM inspector_assignments WHERE ' . implode(' AND ', $where) . '
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['assigned_at'])) {
                return null;
            }
            return [
                'start' => (string)$row['assigned_at'],
                'end'   => !empty($row['completed_at']) ? (string)$row['completed_at'] : date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the dispatcher, driver, and inspector counts for a trip from
     * existing records. Nothing is corrected.
     */
    public function resolveCounts(int $tripId): array
    {
        $trip = $this->tripRow($tripId) ?? [];
        $dispatcherCount = (int)($trip['total_passengers'] ?? 0);

        /* Prefer the dispatch-time snapshot so later reconciliation cannot
           change what the dispatcher originally recorded. */
        $snapshot = $this->latestAuditByAction('Passenger Count Recorded', $tripId);
        if ($snapshot && !empty($snapshot['new_values'])) {
            $decoded = json_decode((string)$snapshot['new_values'], true);
            if (is_array($decoded) && isset($decoded['dispatcher_count'])) {
                $dispatcherCount = (int)$decoded['dispatcher_count'];
            }
        }

        $driverCount = $this->passengerCount($tripId);

        $inspectorCount = null;
        $source = 'no_inspector_window';
        $window = $this->inspectorWindow($tripId, (string)($trip['bus_id'] ?? ''));
        if ($window !== null) {
            $inspectorCount = $this->passengerCount($tripId, $window['start'], $window['end']);
            $source = 'inspector_window';
        }

        $available = [];
        foreach ([$dispatcherCount, $driverCount, $inspectorCount] as $value) {
            if ($value !== null) {
                $available[] = (int)$value;
            }
        }
        $difference = count($available) > 1 ? (max($available) - min($available)) : 0;

        return [
            'trip_id'          => $tripId,
            'bus_id'           => (string)($trip['bus_id'] ?? ''),
            'driver_id'        => (string)($trip['driver_id'] ?? ''),
            'dispatcher_id'    => (string)($trip['dispatcher_id'] ?? ''),
            'trip_date'        => (string)($trip['trip_date'] ?? ''),
            'dispatcher_count' => $dispatcherCount,
            'driver_count'     => $driverCount,
            'inspector_count'  => $inspectorCount,
            'difference'       => $difference,
            'source'           => $source,
            'mismatch'         => $difference > 0,
        ];
    }

    /**
     * Evaluate a trip's counts once it is terminal and create one open
     * Requires Review discrepancy per trip when values differ. Idempotent.
     */
    public function evaluateTripCounts(int $tripId, array $actor = []): ?array
    {
        $trip = $this->tripRow($tripId);
        if (!$trip) {
            return null;
        }
        if (strcasecmp((string)($trip['status'] ?? ''), 'Completed') !== 0) {
            return null;
        }

        $counts = $this->resolveCounts($tripId);
        if (!$counts['mismatch'] || $this->hasOpenDiscrepancy($tripId)) {
            return $counts;
        }

        $description = sprintf(
            'Passenger-count discrepancy on trip #%d (Bus %s): dispatcher %d, driver %d, inspector %s (difference %d)',
            $tripId,
            $counts['bus_id'] !== '' ? $counts['bus_id'] : 'unknown',
            $counts['dispatcher_count'],
            $counts['driver_count'],
            $counts['inspector_count'] === null ? 'n/a' : (string)$counts['inspector_count'],
            $counts['difference']
        );

        $this->audit(
            $actor,
            'Passenger Count Discrepancy',
            $tripId,
            $description,
            [],
            $counts + [
                'review_status' => 'Requires Review',
                'difference'    => $counts['difference'],
            ],
            'Warning'
        );

        return $counts;
    }

    public function resolveDiscrepancy(int $tripId, array $actor, string $reason): bool
    {
        if (!$this->hasOpenDiscrepancy($tripId)) {
            return false;
        }
        $counts = $this->resolveCounts($tripId);
        return $this->audit(
            $actor,
            'Passenger Count Resolved',
            $tripId,
            'Passenger-count discrepancy resolved: ' . $reason,
            $counts,
            [
                'review_status' => 'Resolved',
                'reason'        => $reason,
                'resolved_by'   => (string)($actor['name'] ?? $actor['id'] ?? 'Admin'),
            ],
            'Success'
        );
    }

    /* ---------------------------------------------------------------------
       Reads for the admin monitoring section
       --------------------------------------------------------------------- */

    private function tripSelectExpressions(array $cols): array
    {
        $map = [
            'bus_id'           => 'bt.bus_id',
            'driver_id'        => 'bt.driver_id',
            'route_name'       => 'bt.route_name',
            'trip_number'      => 'bt.trip_number',
            'trip_date'        => 'bt.trip_date',
            'departure_time'   => 'bt.departure_time',
            'arrival_time'     => 'bt.arrival_time',
            'status'           => 'bt.status',
            'total_passengers' => 'bt.total_passengers',
            'dispatcher_id'    => 'bt.dispatcher_id',
            'fuel_cost'        => 'bt.fuel_cost',
            'duration_minutes' => 'bt.duration_minutes',
        ];
        $select = ['bt.id AS trip_id'];
        foreach ($map as $key => $expr) {
            if (isset($cols[$key])) {
                $select[] = $expr . ' AS ' . $key;
            }
        }
        return $select;
    }

    private function busLabel(string $busId): string
    {
        if ($busId === '' || !$this->tableExists('buses')) {
            return $busId;
        }
        try {
            $stmt = $this->conn->prepare('SELECT plate_number FROM buses WHERE bus_id = :b LIMIT 1');
            $stmt->execute([':b' => $busId]);
            $plate = trim((string)$stmt->fetchColumn());
            return $plate !== '' ? $plate : $busId;
        } catch (\Throwable $e) {
            return $busId;
        }
    }

    private function driverName(string $driverId): string
    {
        if ($driverId === '' || !$this->tableExists('drivers')) {
            return $driverId;
        }
        try {
            $stmt = $this->conn->prepare('SELECT full_name FROM drivers WHERE driver_id = :d LIMIT 1');
            $stmt->execute([':d' => $driverId]);
            $name = trim((string)$stmt->fetchColumn());
            return $name !== '' ? $name : $driverId;
        } catch (\Throwable $e) {
            return $driverId;
        }
    }

    private function dispatcherName(string $dispatcherId): string
    {
        if ($dispatcherId === '') {
            return '';
        }
        if ($this->tableExists('inspectors') && $this->tableExists('inspector_accounts')) {
            try {
                $stmt = $this->conn->prepare(
                    'SELECT i.inspector_name FROM inspectors i
                     INNER JOIN inspector_accounts ia ON ia.account_id = i.account_id
                     WHERE ia.inspector_id = :d LIMIT 1'
                );
                $stmt->execute([':d' => $dispatcherId]);
                $name = trim((string)$stmt->fetchColumn());
                if ($name !== '') {
                    return $name;
                }
            } catch (\Throwable $e) {
            }
        }
        return $dispatcherId;
    }

    private function inspectionStatus(string $busId, string $date): array
    {
        if ($busId === '') {
            return ['status' => 'Not recorded', 'passed' => false, 'available' => false];
        }
        if ($this->tableExists('bus_inspections')) {
            try {
                $stmt = $this->conn->prepare(
                    'SELECT result FROM bus_inspections WHERE bus_id = :b AND DATE(inspection_date) = :d ORDER BY id DESC LIMIT 1'
                );
                $stmt->execute([':b' => $busId, ':d' => $date]);
                $result = $stmt->fetchColumn();
                if ($result !== false && $result !== null && $result !== '') {
                    $passed = strcasecmp((string)$result, 'Passed') === 0;
                    return ['status' => $passed ? 'Passed' : (string)$result, 'passed' => $passed, 'available' => true];
                }
            } catch (\Throwable $e) {
            }
        }
        if ($this->tableExists('inspector_assignments')) {
            try {
                $stmt = $this->conn->prepare(
                    'SELECT status FROM inspector_assignments WHERE bus_id = :b AND DATE(assigned_at) = :d ORDER BY id DESC LIMIT 1'
                );
                $stmt->execute([':b' => $busId, ':d' => $date]);
                $status = $stmt->fetchColumn();
                if ($status !== false && $status !== null && $status !== '') {
                    $passed = strcasecmp((string)$status, 'Completed') === 0;
                    return ['status' => (string)$status, 'passed' => $passed, 'available' => true];
                }
            } catch (\Throwable $e) {
            }
        }
        return ['status' => 'Not recorded', 'passed' => false, 'available' => false];
    }

    private function fuelActivity(int $tripId): array
    {
        if (!$this->tableExists('fuel_logs')) {
            return ['liters' => 0.0, 'cost' => 0.0, 'odo' => null, 'at' => ''];
        }
        $cols = $this->columns('fuel_logs');
        if (!isset($cols['trip_id'])) {
            return ['liters' => 0.0, 'cost' => 0.0, 'odo' => null, 'at' => ''];
        }
        $select = ['COALESCE(liters, 0) AS liters', 'COALESCE(cost, 0) AS cost'];
        $select[] = isset($cols['starting_odo']) ? 'starting_odo' : 'NULL AS starting_odo';
        $select[] = isset($cols['ending_odo']) ? 'ending_odo' : 'NULL AS ending_odo';
        $select[] = isset($cols['created_at']) ? 'created_at' : 'NULL AS created_at';
        try {
            $stmt = $this->conn->prepare(
                'SELECT ' . implode(', ', $select) . ' FROM fuel_logs WHERE trip_id = :t ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([':t' => $tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['liters' => 0.0, 'cost' => 0.0, 'odo' => null, 'at' => ''];
            }
            return [
                'liters' => (float)$row['liters'],
                'cost'   => (float)$row['cost'],
                'odo'    => $row['ending_odo'] !== null ? (float)$row['ending_odo'] : ($row['starting_odo'] !== null ? (float)$row['starting_odo'] : null),
                'at'     => (string)($row['created_at'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return ['liters' => 0.0, 'cost' => 0.0, 'odo' => null, 'at' => ''];
        }
    }

    /**
     * Live dispatcher activity for a date, with counts, inspection status,
     * fuel activity, and discrepancy state.
     */
    public function liveActivity(array $filters = []): array
    {
        $date = trim((string)($filters['date'] ?? ''));
        if ($date === '') {
            $date = date('Y-m-d');
        }

        $cols = $this->columns('bus_trips');
        if (empty($cols)) {
            return [];
        }

        $select = $this->tripSelectExpressions($cols);
        $joins = '';
        if (isset($cols['bus_id']) && $this->tableExists('buses')) {
            $busCols = $this->columns('buses');
            if (isset($busCols['bus_id'])) {
                $joins .= ' LEFT JOIN buses b ON b.bus_id = bt.bus_id';
            }
        }
        if (isset($cols['driver_id']) && $this->tableExists('drivers')) {
            $driverCols = $this->columns('drivers');
            if (isset($driverCols['driver_id'])) {
                $joins .= ' LEFT JOIN drivers d ON d.driver_id = bt.driver_id';
            }
        }

        $where = '';
        $params = [];
        if (isset($cols['trip_date'])) {
            $where = 'WHERE bt.trip_date = :d';
            $params[':d'] = $date;
        } elseif (isset($cols['created_at'])) {
            $where = 'WHERE DATE(bt.created_at) = :d';
            $params[':d'] = $date;
        }

        $order = [];
        if (isset($cols['status'])) {
            $order[] = "CASE WHEN bt.status = 'Departed' THEN 0 WHEN bt.status = 'Scheduled' THEN 1 ELSE 2 END";
        }
        if (isset($cols['trip_number'])) {
            $order[] = 'bt.trip_number ASC';
        }
        $order[] = 'bt.id DESC';

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM bus_trips bt' . $joins . ' ' . $where . ' ORDER BY ' . implode(', ', $order) . ' LIMIT 200';

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('DispatcherMonitoringModel liveActivity failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tripId = (int)($row['trip_id'] ?? 0);
            $busId = (string)($row['bus_id'] ?? '');
            $driverId = (string)($row['driver_id'] ?? '');
            $dispatcherId = (string)($row['dispatcher_id'] ?? '');
            $status = (string)($row['status'] ?? '');
            $counts = $this->resolveCounts($tripId);
            $inspection = $this->inspectionStatus($busId, (string)($row['trip_date'] ?? $date));
            $fuel = $this->fuelActivity($tripId);
            $open = $this->hasOpenDiscrepancy($tripId);
            $isTerminal = strcasecmp($status, 'Completed') === 0 || strcasecmp($status, 'Cancelled') === 0;

            $out[] = [
                'trip_id'            => $tripId,
                'trip_number'        => $row['trip_number'] ?? null,
                'dispatcher_id'      => $dispatcherId,
                'dispatcher'         => $this->dispatcherName($dispatcherId),
                'bus_id'             => $busId,
                'bus'                => $this->busLabel($busId),
                'driver_id'          => $driverId,
                'driver'             => $this->driverName($driverId),
                'route_name'         => (string)($row['route_name'] ?? ''),
                'trip_date'          => (string)($row['trip_date'] ?? $date),
                'dispatch_time'      => (string)($row['departure_time'] ?? ''),
                'arrival_time'       => (string)($row['arrival_time'] ?? ''),
                'status'             => $status,
                'passenger_count'    => $counts['dispatcher_count'],
                'driver_count'       => $counts['driver_count'],
                'inspector_count'    => $counts['inspector_count'],
                'count_source'       => $counts['source'],
                'inspection_status'  => $inspection['status'],
                'fuel_liters'        => $fuel['liters'],
                'fuel_cost'          => $fuel['cost'],
                'fuel_odo'           => $fuel['odo'],
                'discrepancy_status' => $open ? 'Requires Review' : ($isTerminal ? 'None' : 'Pending'),
                'difference'         => $counts['difference'],
                'last_update'        => (string)(($row['arrival_time'] ?? '') ?: (($row['departure_time'] ?? '') ?: ($fuel['at'] ?: ($row['trip_date'] ?? '')))),
            ];
        }

        return $out;
    }

    /**
     * Bus problems reported by drivers. bus_problem_reports is the single source
     * of truth for the Reported → Acknowledged → Replacement Assigned →
     * Passengers Transferred → Resolved lifecycle. Read-only here: the dispatcher
     * owns the transitions; this section only reflects them.
     */
    public function busProblems(array $filters = []): array
    {
        if (!$this->tableExists('bus_problem_reports')) {
            return [];
        }

        $cols = $this->columns('bus_problem_reports');
        if (empty($cols)) {
            return [];
        }

        $joins = '';
        $extra = [];

        if (isset($cols['bus_id']) && $this->tableExists('buses')) {
            $busCols = $this->columns('buses');
            if (isset($busCols['bus_id']) && isset($busCols['plate_number'])) {
                $joins .= ' LEFT JOIN buses b ON b.bus_id = bpr.bus_id';
                $extra[] = 'b.plate_number AS plate_number';
            }
        }

        if (isset($cols['driver_id']) && $this->tableExists('drivers')) {
            $driverCols = $this->columns('drivers');
            if (isset($driverCols['driver_id']) && isset($driverCols['full_name'])) {
                $joins .= ' LEFT JOIN drivers d ON d.driver_id = bpr.driver_id';
                $extra[] = 'd.full_name AS driver_name';
            }
        }

        $where = [];
        $params = [];

        $date = trim((string)($filters['date'] ?? ''));
        if ($date !== '' && isset($cols['created_at'])) {
            $where[] = 'DATE(bpr.created_at) = :bp_date';
            $params[':bp_date'] = $date;
        } else {
            $from = trim((string)($filters['fromDate'] ?? ''));
            if ($from !== '' && isset($cols['created_at'])) {
                $where[] = 'DATE(bpr.created_at) >= :bp_from';
                $params[':bp_from'] = $from;
            }
            $to = trim((string)($filters['toDate'] ?? ''));
            if ($to !== '' && isset($cols['created_at'])) {
                $where[] = 'DATE(bpr.created_at) <= :bp_to';
                $params[':bp_to'] = $to;
            }
        }

        $bus = trim((string)($filters['bus'] ?? ''));
        if ($bus !== '' && isset($cols['bus_id'])) {
            $where[] = 'bpr.bus_id = :bp_bus';
            $params[':bp_bus'] = $bus;
        }

        $driver = trim((string)($filters['driver'] ?? ''));
        if ($driver !== '' && isset($cols['driver_id'])) {
            $where[] = 'bpr.driver_id = :bp_driver';
            $params[':bp_driver'] = $driver;
        }

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if (in_array($status, ['waiting', 'acknowledged', 'resolved'], true) && isset($cols['status'])) {
            $where[] = 'bpr.status = :bp_status';
            $params[':bp_status'] = $status;
        }

        $sql = 'SELECT bpr.*'
            . (!empty($extra) ? ', ' . implode(', ', $extra) : '')
            . ' FROM bus_problem_reports bpr'
            . $joins
            . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY '
            . (isset($cols['created_at']) ? 'bpr.created_at DESC, ' : '')
            . 'bpr.id DESC LIMIT 200';

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('DispatcherMonitoringModel busProblems failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $status = strtolower((string)($row['status'] ?? 'waiting'));
            $replacementBus = (string)($row['replacement_bus_id'] ?? '');

            // Lifecycle stage. The assignment endpoint transfers the active
            // passengers atomically with the assignment, so a stored replacement
            // bus means the passengers were transferred.
            if ($status === 'resolved') {
                $stage = 'Resolved';
            } elseif ($replacementBus !== '') {
                $stage = 'Passengers Transferred';
            } elseif ($status === 'acknowledged') {
                $stage = 'Acknowledged';
            } else {
                $stage = 'Reported';
            }

            $out[] = [
                'report_id'               => (int)($row['id'] ?? 0),
                'trip_id'                 => (int)($row['trip_id'] ?? 0),
                'trip_no'                 => (string)($row['trip_no'] ?? ''),
                'bus_id'                  => (string)($row['bus_id'] ?? ''),
                'plate_number'            => (string)($row['plate_number'] ?? ''),
                'driver_id'               => (string)($row['driver_id'] ?? ''),
                'driver'                  => (string)($row['driver_name'] ?? ''),
                'problem_type'            => (string)($row['problem_type'] ?? ''),
                'problem_detail'          => (string)($row['problem_detail'] ?? ''),
                'passenger_count'         => (int)($row['passenger_count'] ?? 0),
                'status'                  => $status,
                'stage'                   => $stage,
                'reported_at'             => (string)($row['created_at'] ?? ''),
                'updated_at'              => (string)($row['updated_at'] ?? ''),
                'replacement_bus_id'      => $replacementBus,
                'replacement_driver_id'   => (string)($row['replacement_driver_id'] ?? ''),
                'replacement_assigned_by' => (string)($row['replacement_assigned_by'] ?? ''),
                'replacement_assigned_at' => (string)($row['replacement_assigned_at'] ?? ''),
                'transferred_count'       => $this->transferredPassengerCount(
                    (string)($row['trip_no'] ?? ''),
                    $replacementBus,
                    (string)($row['replacement_driver_id'] ?? '')
                ),
            ];
        }

        return $out;
    }

    /**
     * Active passengers currently riding the replacement bus for a trip.
     */
    private function transferredPassengerCount(string $tripNo, string $replacementBus, string $replacementDriver): int
    {
        if ($tripNo === '' || $replacementBus === '' || !$this->tableExists('passenger_trips')) {
            return 0;
        }

        try {
            $sql = 'SELECT COUNT(*) FROM passenger_trips
                    WHERE trip_no = :trip AND bus_no = :bus
                      AND dropoff_time IS NULL AND archived_status = "no"';
            $params = [':trip' => $tripNo, ':bus' => $replacementBus];

            if ($replacementDriver !== '') {
                $sql .= ' AND driver_id = :drv';
                $params[':drv'] = $replacementDriver;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Accountability list with filters: date range, dispatcher, bus, driver,
     * trip status, discrepancy status.
     */
    public function activityList(array $filters = []): array
    {
        $cols = $this->columns('bus_trips');
        if (empty($cols)) {
            return [];
        }

        $select = $this->tripSelectExpressions($cols);
        $joins = '';
        $where = [];
        $params = [];

        if (isset($cols['bus_id']) && $this->tableExists('buses')) {
            $busCols = $this->columns('buses');
            if (isset($busCols['bus_id'])) {
                $joins .= ' LEFT JOIN buses b ON b.bus_id = bt.bus_id';
            }
        }
        if (isset($cols['driver_id']) && $this->tableExists('drivers')) {
            $driverCols = $this->columns('drivers');
            if (isset($driverCols['driver_id'])) {
                $joins .= ' LEFT JOIN drivers d ON d.driver_id = bt.driver_id';
            }
        }

        if (!empty($filters['fromDate']) && isset($cols['trip_date'])) {
            $where[] = 'bt.trip_date >= :from';
            $params[':from'] = (string)$filters['fromDate'];
        }
        if (!empty($filters['toDate']) && isset($cols['trip_date'])) {
            $where[] = 'bt.trip_date <= :to';
            $params[':to'] = (string)$filters['toDate'];
        }
        if (!empty($filters['dispatcher']) && isset($cols['dispatcher_id'])) {
            $where[] = 'bt.dispatcher_id = :disp';
            $params[':disp'] = (string)$filters['dispatcher'];
        }
        if (!empty($filters['bus']) && isset($cols['bus_id'])) {
            $where[] = 'bt.bus_id = :bus';
            $params[':bus'] = (string)$filters['bus'];
        }
        if (!empty($filters['driver']) && isset($cols['driver_id'])) {
            $where[] = 'bt.driver_id = :drv';
            $params[':drv'] = (string)$filters['driver'];
        }
        if (!empty($filters['status']) && isset($cols['status'])) {
            $where[] = 'bt.status = :st';
            $params[':st'] = (string)$filters['status'];
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM bus_trips bt' . $joins
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY bt.id DESC LIMIT 500';

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('DispatcherMonitoringModel activityList failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tripId = (int)($row['trip_id'] ?? 0);
            $counts = $this->resolveCounts($tripId);
            $open = $this->hasOpenDiscrepancy($tripId);
            $discrepancyStatus = $open ? 'Requires Review' : 'None';

            if (!empty($filters['discrepancy']) && strcasecmp((string)$filters['discrepancy'], $discrepancyStatus) !== 0) {
                continue;
            }

            $out[] = [
                'trip_id'            => $tripId,
                'trip_number'        => $row['trip_number'] ?? null,
                'dispatcher_id'      => (string)($row['dispatcher_id'] ?? ''),
                'dispatcher'         => $this->dispatcherName((string)($row['dispatcher_id'] ?? '')),
                'bus_id'             => (string)($row['bus_id'] ?? ''),
                'bus'                => $this->busLabel((string)($row['bus_id'] ?? '')),
                'driver_id'          => (string)($row['driver_id'] ?? ''),
                'driver'             => $this->driverName((string)($row['driver_id'] ?? '')),
                'route_name'         => (string)($row['route_name'] ?? ''),
                'trip_date'          => (string)($row['trip_date'] ?? ''),
                'dispatch_time'      => (string)($row['departure_time'] ?? ''),
                'arrival_time'       => (string)($row['arrival_time'] ?? ''),
                'status'             => (string)($row['status'] ?? ''),
                'passenger_count'    => $counts['dispatcher_count'],
                'driver_count'       => $counts['driver_count'],
                'inspector_count'    => $counts['inspector_count'],
                'difference'         => $counts['difference'],
                'discrepancy_status' => $discrepancyStatus,
            ];
        }

        return $out;
    }

    /**
     * Full audit history for one dispatcher (accountability view).
     */
    public function dispatcherHistory(array $filters = []): array
    {
        if (!$this->tableExists('audit_trail')) {
            return ['dispatcher_id' => (string)($filters['dispatcher'] ?? ''), 'dispatcher' => '', 'entries' => [], 'counts' => []];
        }
        $dispatcherId = (string)($filters['dispatcher'] ?? '');

        $where = ["module = 'Dispatcher'"];
        $params = [];
        if ($dispatcherId !== '') {
            $where[] = "(user_id = :uid OR username = :uname OR new_values LIKE :needle)";
            $params[':uid'] = is_numeric($dispatcherId) ? (int)$dispatcherId : -1;
            $params[':uname'] = $dispatcherId;
            $params[':needle'] = '%"dispatcher_id":"' . $dispatcherId . '"%';
        }
        if (!empty($filters['fromDate'])) {
            $where[] = 'DATE(created_at) >= :from';
            $params[':from'] = (string)$filters['fromDate'];
        }
        if (!empty($filters['toDate'])) {
            $where[] = 'DATE(created_at) <= :to';
            $params[':to'] = (string)$filters['toDate'];
        }

        try {
            $stmt = $this->conn->prepare(
                'SELECT id, user_id, username, user_role, action, record_id, description, previous_values, new_values, status, created_at
                 FROM audit_trail WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500'
            );
            $stmt->execute($params);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $entries = [];
        }

        $counts = [];
        foreach ($entries as $entry) {
            $action = (string)($entry['action'] ?? '');
            $counts[$action] = ($counts[$action] ?? 0) + 1;
        }

        return [
            'dispatcher_id' => $dispatcherId,
            'dispatcher'    => $this->dispatcherName($dispatcherId),
            'entries'       => $entries,
            'counts'        => $counts,
        ];
    }

    /**
     * Complete detail for one trip: trip, counts, discrepancy state, and the
     * full Dispatcher audit history for that trip.
     */
    public function detail(int $tripId): ?array
    {
        $trip = $this->tripRow($tripId);
        if (!$trip) {
            return null;
        }
        $counts = $this->resolveCounts($tripId);
        $open = $this->hasOpenDiscrepancy($tripId);

        $history = [];
        if ($this->tableExists('audit_trail')) {
            try {
                $stmt = $this->conn->prepare(
                    "SELECT id, action, description, previous_values, new_values, status, username, created_at
                     FROM audit_trail WHERE module = 'Dispatcher' AND record_id = :r ORDER BY id DESC LIMIT 200"
                );
                $stmt->execute([':r' => (string)$tripId]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $history = [];
            }
        }

        return [
            'trip_id'            => $tripId,
            'trip_number'        => $trip['trip_number'] ?? null,
            'bus_id'             => (string)($trip['bus_id'] ?? ''),
            'bus'                => $this->busLabel((string)($trip['bus_id'] ?? '')),
            'driver_id'          => (string)($trip['driver_id'] ?? ''),
            'driver'             => $this->driverName((string)($trip['driver_id'] ?? '')),
            'dispatcher_id'      => (string)($trip['dispatcher_id'] ?? ''),
            'dispatcher'         => $this->dispatcherName((string)($trip['dispatcher_id'] ?? '')),
            'route_name'         => (string)($trip['route_name'] ?? ''),
            'trip_date'          => (string)($trip['trip_date'] ?? ''),
            'status'             => (string)($trip['status'] ?? ''),
            'dispatch_time'      => (string)($trip['departure_time'] ?? ''),
            'arrival_time'       => (string)($trip['arrival_time'] ?? ''),
            'passenger_count'    => $counts['dispatcher_count'],
            'driver_count'       => $counts['driver_count'],
            'inspector_count'    => $counts['inspector_count'],
            'difference'         => $counts['difference'],
            'count_source'       => $counts['source'],
            'discrepancy_status' => $open ? 'Requires Review' : 'Resolved',
            'history'            => $history,
        ];
    }

    /**
     * Dispatcher/bus/driver options for the accountability filters.
     */
    public function filterOptions(): array
    {
        $dispatchers = [];
        if ($this->tableExists('inspectors') && $this->tableExists('inspector_accounts')) {
            try {
                $stmt = $this->conn->query(
                    "SELECT ia.inspector_id, i.inspector_name
                     FROM inspector_accounts ia
                     LEFT JOIN inspectors i ON i.account_id = ia.account_id
                     WHERE LOWER(ia.role) = 'dispatcher'
                     ORDER BY i.inspector_name ASC"
                );
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $dispatchers[] = [
                        'id'   => (string)$row['inspector_id'],
                        'name' => trim((string)($row['inspector_name'] ?? '')) !== '' ? $row['inspector_name'] : (string)$row['inspector_id'],
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        $buses = [];
        if ($this->tableExists('buses')) {
            try {
                $stmt = $this->conn->query('SELECT bus_id FROM buses ORDER BY bus_id ASC');
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $busId) {
                    $buses[] = (string)$busId;
                }
            } catch (\Throwable $e) {
            }
        }

        $drivers = [];
        if ($this->tableExists('drivers')) {
            try {
                $stmt = $this->conn->query('SELECT driver_id, full_name FROM drivers ORDER BY full_name ASC');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $drivers[] = [
                        'id'   => (string)$row['driver_id'],
                        'name' => trim((string)($row['full_name'] ?? '')) !== '' ? $row['full_name'] : (string)$row['driver_id'],
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        return ['dispatchers' => $dispatchers, 'buses' => $buses, 'drivers' => $drivers];
    }

    /**
     * Summary counters for the monitoring header.
     */
    public function summary(array $filters = []): array
    {
        $live = $this->liveActivity($filters);
        $summary = [
            'total'             => count($live),
            'active'            => 0,
            'completed'         => 0,
            'cancelled'         => 0,
            'requires_review'   => 0,
            'inspection_passed' => 0,
            'fuel_records'      => 0,
        ];
        foreach ($live as $row) {
            $status = strtolower((string)$row['status']);
            if ($status === 'departed' || $status === 'scheduled') {
                $summary['active']++;
            } elseif ($status === 'completed') {
                $summary['completed']++;
            } elseif ($status === 'cancelled') {
                $summary['cancelled']++;
            }
            if ($row['discrepancy_status'] === 'Requires Review') {
                $summary['requires_review']++;
            }
            if ($row['inspection_status'] === 'Passed') {
                $summary['inspection_passed']++;
            }
            if ((float)$row['fuel_liters'] > 0 || (float)$row['fuel_cost'] > 0) {
                $summary['fuel_records']++;
            }
        }
        return $summary;
    }
}
