<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;

class ExportAuditModel
{
    public function initTables(): void
    {
        try {
            $db = Database::get();
            $db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    audit_type VARCHAR(100) NOT NULL,
                    performed_by VARCHAR(255) NOT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    financial_snapshot LONGTEXT NULL,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_generated_at (generated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $db->exec("
                CREATE TABLE IF NOT EXISTS report_export_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    report_title VARCHAR(255) NULL,
                    report_type VARCHAR(100) NULL,
                    coverage_period VARCHAR(100) NULL,
                    format VARCHAR(20) NOT NULL DEFAULT 'Excel',
                    user_id INT NULL,
                    username VARCHAR(100) NULL,
                    user_role VARCHAR(100) NULL,
                    device VARCHAR(500) NULL,
                    ip_address VARCHAR(45) NULL,
                    file_name VARCHAR(255) NULL,
                    file_size INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at),
                    INDEX idx_report_type (report_type),
                    INDEX idx_format (format)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch(\Exception $e) {
            // silently pass — table may already exist
        }
    }

    public function logExport(
        ?string $reportTitle,
        ?string $reportType,
        ?string $coveragePeriod,
        string $format,
        ?string $username,
        ?string $userRole,
        ?string $device,
        ?string $fileName = null
    ): int {
        $db = Database::get();
        $sql = "INSERT INTO report_export_log (report_title, report_type, coverage_period, format, user_id, username, user_role, device, ip_address, file_name)
                VALUES (:title, :type, :period, :format, :user_id, :username, :role, :device, :ip, :file)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':title' => $reportTitle,
            ':type' => $reportType,
            ':period' => $coveragePeriod,
            ':format' => $format,
            ':user_id' => $_SESSION['user']['id'] ?? null,
            ':username' => $username,
            ':role' => $userRole,
            ':device' => $device,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':file' => $fileName,
        ]);
        return (int)$db->lastInsertId();
    }

    public function getExportLogs(
        ?string $search = null,
        ?string $format = null,
        ?string $reportType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sort = 'DESC',
        int $page = 1,
        int $perPage = 25
    ): array {
        $db = Database::get();
        $conditions = [];
        $params = [];
        if ($search) { $conditions[] = "(report_title LIKE :s OR username LIKE :s2)"; $params[':s'] = '%'.$search.'%'; $params[':s2'] = '%'.$search.'%'; }
        if ($format) { $conditions[] = "format = :format"; $params[':format'] = $format; }
        if ($reportType) { $conditions[] = "report_type = :rtype"; $params[':rtype'] = $reportType; }
        if ($dateFrom) { $conditions[] = "DATE(created_at) >= :df"; $params[':df'] = $dateFrom; }
        if ($dateTo) { $conditions[] = "DATE(created_at) <= :dt"; $params[':dt'] = $dateTo; }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sortDir = strtoupper($sort) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM report_export_log $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $sql = "SELECT * FROM report_export_log $where ORDER BY created_at $sortDir LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return ['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => max(1, (int)ceil($total / $perPage))];
    }

    public function listAudits(?string $status = null, ?string $period = null, int $limit = 50, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $db = Database::get();
        $conditions = [];
        $params = [];

        if ($status && $status !== 'All Status') {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }

        if ($dateFrom !== null && $dateFrom !== '' && $dateTo !== null && $dateTo !== '') {
            $conditions[] = "DATE(generated_at) BETWEEN :audit_from AND :audit_to";
            $params[':audit_from'] = $dateFrom;
            $params[':audit_to'] = $dateTo;
        } elseif ($period) {
            switch ($period) {
                case 'Day':
                    $conditions[] = "DATE(generated_at) = CURDATE()";
                    break;
                case 'Month':
                    $conditions[] = "YEAR(generated_at) = YEAR(CURDATE()) AND MONTH(generated_at) = MONTH(CURDATE())";
                    break;
                case 'Year':
                    $conditions[] = "YEAR(generated_at) = YEAR(CURDATE())";
                    break;
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM audit_logs $where ORDER BY generated_at DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAuditById(int $id): ?array
    {
        $db = Database::get();
        $stmt = $db->prepare("SELECT * FROM audit_logs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function insertAudit(string $type, string $performedBy, string $snapshot): int
    {
        $db = Database::get();
        $sql = "INSERT INTO audit_logs (audit_type, performed_by, status, financial_snapshot)
                VALUES (:audit_type, :performed_by, 'pending', :snapshot)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':audit_type' => $type,
            ':performed_by' => $performedBy,
            ':snapshot' => $snapshot,
        ]);
        return (int)$db->lastInsertId();
    }

    public function updateAuditStatus(int $id, string $status): bool
    {
        $db = Database::get();
        $stmt = $db->prepare("UPDATE audit_logs SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function getRevenueData(?string $from = null, ?string $to = null): array
    {
        $db = Database::get();
        /* The finance analytics pages read remittance_logs; the legacy
           remittances table is only a fallback for older deployments. */
        $table = $this->firstExistingTable(['remittance_logs', 'remittances']);
        if ($table === null) {
            return [];
        }
        $cols = $this->columnsOf($table);
        $select = implode(', ', [
            $this->colExpr($cols, 'id', 'id'),
            $this->colExpr($cols, 'bus_no', 'bus_no', "''"),
            $this->colExpr($cols, 'driver_id', 'driver_id', "''"),
            $this->colExpr($cols, 'total_passengers', 'total_passengers', '0'),
            $this->colExpr($cols, 'expected_cash', 'expected_cash', '0'),
            $this->colExpr($cols, 'actual_cash', 'actual_cash', '0'),
            $this->colExpr($cols, 'discrepancy_amount', 'discrepancy_amount', '0'),
            $this->colExpr($cols, 'status', 'status', "''"),
            $this->colExpr($cols, 'created_at', 'created_at', 'NULL'),
        ]);
        $dateCol = isset($cols['business_date']) ? 'business_date' : 'created_at';
        $conditions = [];
        $params = [];
        if ($from) { $conditions[] = "DATE(r.`$dateCol`) >= :from"; $params[':from'] = $from; }
        if ($to) { $conditions[] = "DATE(r.`$dateCol`) <= :to"; $params[':to'] = $to; }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $driverJoin = $this->columnsOf('drivers') ? 'LEFT JOIN drivers d ON d.driver_id = r.driver_id' : '';
        try {
            $stmt = $db->prepare("SELECT $select, d.full_name AS driver_name FROM `$table` r $driverJoin $where ORDER BY r.id DESC LIMIT 5000");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getExpenseData(?string $from = null, ?string $to = null): array
    {
        $db = Database::get();
        $table = $this->firstExistingTable(['expenses', 'financial_expenses']);
        if ($table === null) {
            return [];
        }
        $cols = $this->columnsOf($table);
        $dateCol = isset($cols['expense_date']) ? 'expense_date' : (isset($cols['created_at']) ? 'created_at' : null);
        if ($dateCol === null) {
            return [];
        }
        $select = implode(', ', [
            $this->colExpr($cols, 'id', 'id'),
            $this->colExpr($cols, 'category', 'category', "'Other'"),
            $this->colExpr($cols, 'amount', 'amount', '0'),
            $this->colExpr($cols, 'bus_id', 'bus_id', "''"),
            $this->colExpr($cols, 'trip_id', 'trip_id', 'NULL'),
            $this->colExpr($cols, 'description', 'description', "''"),
            $this->colExpr($cols, 'created_at', 'created_at', 'NULL'),
        ]);
        $conditions = [];
        $params = [];
        if ($from) { $conditions[] = "DATE(e.`$dateCol`) >= :from"; $params[':from'] = $from; }
        if ($to) { $conditions[] = "DATE(e.`$dateCol`) <= :to"; $params[':to'] = $to; }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        try {
            $stmt = $db->prepare("SELECT $select, NULL AS bus_number, NULL AS plate_number, NULL AS driver_name, NULL AS drv_id FROM `$table` e $where ORDER BY e.id DESC LIMIT 5000");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getRemittanceData(?string $from = null, ?string $to = null): array
    {
        $db = Database::get();
        $table = $this->firstExistingTable(['remittance_logs', 'remittances']);
        if ($table === null) {
            return [];
        }
        $cols = $this->columnsOf($table);
        $dateCol = isset($cols['business_date']) ? 'business_date' : 'created_at';
        $select = implode(', ', [
            $this->colExpr($cols, 'id', 'id'),
            $this->colExpr($cols, 'bus_no', 'bus_no', "''"),
            $this->colExpr($cols, 'driver_id', 'driver_id', "''"),
            $this->colExpr($cols, 'total_passengers', 'total_passengers', '0'),
            $this->colExpr($cols, 'expected_cash', 'expected_cash', '0'),
            $this->colExpr($cols, 'actual_cash', 'actual_cash', '0'),
            $this->colExpr($cols, 'discrepancy_amount', 'discrepancy_amount', '0'),
            $this->colExpr($cols, 'status', 'status', "''"),
            $this->colExpr($cols, 'confirmed_by', 'confirmed_by', "''"),
            $this->colExpr($cols, 'created_at', 'created_at', 'NULL'),
        ]);
        $conditions = [];
        $params = [];
        if ($from) { $conditions[] = "DATE(r.`$dateCol`) >= :from"; $params[':from'] = $from; }
        if ($to) { $conditions[] = "DATE(r.`$dateCol`) <= :to"; $params[':to'] = $to; }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        try {
            $stmt = $db->prepare("SELECT $select FROM `$table` r $where ORDER BY r.id DESC LIMIT 5000");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getFinancialSnapshot(): array
    {
        $db = Database::get();

        $revenue = 0.0;
        $collection = 0.0;
        $remitTable = $this->firstExistingTable(['remittance_logs', 'remittances']);
        if ($remitTable !== null) {
            $rc = $this->columnsOf($remitTable);
            $cashCol = isset($rc['actual_cash']) ? 'actual_cash' : (isset($rc['total_collection']) ? 'total_collection' : 'expected_cash');
            try {
                $revenue = (float)$db->query("SELECT COALESCE(SUM(`$cashCol`),0) FROM `$remitTable`")->fetchColumn();
                $collection = $revenue;
            } catch (\Throwable $e) {
            }
        }

        $expenses = 0.0;
        $catBreakdown = [];
        $perBus = [];
        $expTable = $this->firstExistingTable(['expenses', 'financial_expenses']);
        if ($expTable !== null) {
            $ec = $this->columnsOf($expTable);
            try {
                $expenses = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM `$expTable`")->fetchColumn();
            } catch (\Throwable $e) {
            }
            if (isset($ec['category'])) {
                try {
                    $catBreakdown = $db->query(
                        "SELECT category, SUM(amount) AS total,
                                ROUND(SUM(amount)*100.0/NULLIF((SELECT SUM(amount) FROM `$expTable`),0), 2) AS percentage
                         FROM `$expTable` GROUP BY category ORDER BY total DESC"
                    )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $catBreakdown = [];
                }
            }
            if (isset($ec['bus_id'])) {
                try {
                    $perBus = $db->query(
                        "SELECT bus_id, COALESCE(SUM(amount),0) AS total_expense
                         FROM `$expTable` GROUP BY bus_id ORDER BY total_expense DESC"
                    )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $perBus = [];
                }
            }
        }

        $tripCount = 0;
        $passengerCount = 0;
        try {
            $tripCount = (int)$db->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn();
        } catch (\Throwable $e) {
        }
        try {
            $passengerCount = (int)$db->query("SELECT COUNT(*) FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid'")->fetchColumn();
        } catch (\Throwable $e) {
        }

        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'total_collection' => $collection,
            'net_profit' => $revenue - $expenses,
            'trip_count' => $tripCount,
            'passenger_count' => $passengerCount,
            'expense_breakdown' => $catBreakdown,
            'cost_per_bus' => $perBus,
            'snapshot_date' => date('Y-m-d H:i:s'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SCHEMA HELPERS
    |--------------------------------------------------------------------------
    */

    /** Column names of an existing table; empty when the table is absent. */
    private array $tableColumns = [];

    private function columnsOf(string $table): array
    {
        if (array_key_exists($table, $this->tableColumns)) {
            return $this->tableColumns[$table];
        }
        $cols = [];
        try {
            foreach (Database::get()->query("SHOW COLUMNS FROM `$table`") as $row) {
                $cols[(string)$row['Field']] = true;
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        return $this->tableColumns[$table] = $cols;
    }

    private function firstExistingTable(array $candidates): ?string
    {
        foreach ($candidates as $table) {
            if ($this->columnsOf($table)) {
                return $table;
            }
        }
        return null;
    }

    /** SELECT-list entry for a column that may not exist in this schema. */
    private function colExpr(array $cols, string $column, string $alias, string $fallback = 'NULL'): string
    {
        return isset($cols[$column]) ? "`$column` AS `$alias`" : "$fallback AS `$alias`";
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE ACTIVITY FEED (Audits section)
    |--------------------------------------------------------------------------
    | Verifiable financial and administrative actions from the records the
    | system actually saved: remittance submissions/verifications, discrepancy
    | workflow events, report exports, financial audit snapshots, and recorded
    | audit-trail actions. Actors and timestamps are shown exactly as recorded;
    | nothing is inferred from a record's current status.
    */

    private function driverNames(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        try {
            foreach (Database::get()->query('SELECT driver_id, full_name FROM drivers') as $row) {
                $map[(string)$row['driver_id']] = (string)($row['full_name'] ?? '');
            }
        } catch (\Throwable $e) {
        }
        return $map;
    }

    private function financeEvent(
        string $type,
        string $when,
        string $action,
        string $actor,
        string $actorRole,
        string $record,
        string $detail,
        string $status,
        float $amount,
        string $source,
        bool $demo
    ): array {
        return [
            'type' => $type,
            'when' => $when,
            'action' => $action,
            'actor' => $actor !== '' ? $actor : 'Not recorded',
            'actor_role' => $actorRole,
            'record' => $record,
            'detail' => $detail,
            'status' => $status,
            'amount' => round($amount, 2),
            'source' => $source,
            'demo' => $demo,
        ];
    }

    public function getFinanceAuditFeed(
        string $from,
        string $to,
        string $type = '',
        string $search = '',
        int $page = 1,
        int $perPage = 25
    ): array {
        $db = Database::get();
        $events = [];

        /* --- Remittance submissions and verifications (actual records). --- */
        if ($type === '' || $type === 'remittance') {
            $table = $this->firstExistingTable(['remittance_logs', 'remittances']);
            if ($table !== null) {
                $cols = $this->columnsOf($table);
                $whenCol = isset($cols['created_at']) ? 'created_at' : (isset($cols['business_date']) ? 'business_date' : null);
                if ($whenCol !== null) {
                    $select = [];
                    foreach (['id', 'driver_id', 'expected_cash', 'actual_cash', 'total_collection', 'discrepancy_amount', 'status', 'submitted_by', 'confirmed_by', 'reviewed_by', 'verified_at', 'reviewed_at', 'created_at', 'business_date'] as $c) {
                        $select[] = $this->colExpr($cols, $c, $c);
                    }
                    try {
                        $stmt = $db->prepare(
                            'SELECT ' . implode(', ', $select) . " FROM `$table`
                             WHERE DATE(`$whenCol`) >= ? AND DATE(`$whenCol`) <= ?
                             ORDER BY id DESC LIMIT 3000"
                        );
                        $stmt->execute([$from, $to]);
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $e) {
                        $rows = [];
                    }
                    /* One remittance per driver-day: legacy duplicate demo rows
                       are collapsed to the latest record for that day. */
                    $seen = [];
                    $names = $this->driverNames();
                    foreach ($rows as $r) {
                        $bizDate = trim((string)($r['business_date'] ?? ''));
                        if ($bizDate === '') {
                            $bizDate = substr((string)($r['created_at'] ?? ''), 0, 10);
                        }
                        $key = (string)($r['driver_id'] ?? '') . '|' . $bizDate;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $driverId = (string)($r['driver_id'] ?? '');
                        $driverName = $names[$driverId] ?? '';
                        $who = $driverName !== '' ? "$driverName ($driverId)" : $driverId;
                        $expected = (float)($r['expected_cash'] ?? 0);
                        $collected = (float)($r['actual_cash'] ?? ($r['total_collection'] ?? 0));
                        $discrepancy = (float)($r['discrepancy_amount'] ?? 0);
                        $status = (string)($r['status'] ?? '');
                        $submittedBy = trim((string)($r['submitted_by'] ?? ''));
                        $demo = strcasecmp($submittedBy, 'Data Simulator') === 0;
                        /* The submission belongs to the driver whose remittance
                           record this is; the simulator marker is a data-source
                           tag, never a person, so it is never shown as actor. */
                        if ($submittedBy === '' || $demo) {
                            $submittedBy = $driverName !== '' ? $driverName : $driverId;
                            $submittedRole = 'Driver';
                        } else {
                            $submittedRole = '';
                        }
                        $recordRef = 'Remittance #' . (string)($r['id'] ?? '');

                        $detail = 'Business date ' . ($bizDate !== '' ? $bizDate : 'not recorded')
                            . '; expected cash PHP ' . number_format($expected, 2)
                            . '; collected PHP ' . number_format($collected, 2);
                        if ($discrepancy > 0) {
                            $detail .= '; shortage PHP ' . number_format($discrepancy, 2);
                        }
                        $events[] = $this->financeEvent(
                            'remittance',
                            (string)($r['created_at'] ?? $whenCol),
                            'Remittance Submitted',
                            $submittedBy,
                            $submittedRole,
                            $recordRef,
                            $detail,
                            $status,
                            $expected,
                            $table,
                            $demo
                        );

                        $verifiedAt = trim((string)($r['verified_at'] ?? ''));
                        $reviewedAt = trim((string)($r['reviewed_at'] ?? ''));
                        $verifiedBy = trim((string)($r['confirmed_by'] ?? ''));
                        if ($verifiedBy === '') {
                            $verifiedBy = trim((string)($r['reviewed_by'] ?? ''));
                        }
                        $verificationWhen = $verifiedAt !== '' ? $verifiedAt : $reviewedAt;
                        if ($verificationWhen !== '' && $verifiedBy !== '') {
                            $events[] = $this->financeEvent(
                                'remittance',
                                $verificationWhen,
                                'Remittance Verified',
                                $verifiedBy,
                                'finance_officer',
                                $recordRef,
                                'Verified for driver ' . $who . ' — business date ' . ($bizDate !== '' ? $bizDate : 'not recorded')
                                    . '; expected cash PHP ' . number_format($expected, 2),
                                $status,
                                $expected - $discrepancy,
                                $table,
                                $demo
                            );
                        }
                    }
                }
            }
        }

        /* --- Discrepancy workflow events (recorded status history). --- */
        if (($type === '' || $type === 'discrepancy') && $this->columnsOf('discrepancy_status_history')) {
            $hasCases = (bool)$this->columnsOf('discrepancy_cases');
            try {
                $sql = 'SELECT h.id, h.case_id, h.event_type, h.to_status, h.description, h.actor_username, h.actor_role, h.created_at'
                    . ($hasCases ? ', c.driver_id, c.business_date, c.discrepancy_amount, c.status AS case_status, c.opened_by' : '')
                    . ' FROM discrepancy_status_history h'
                    . ($hasCases ? ' LEFT JOIN discrepancy_cases c ON c.id = h.case_id' : '')
                    . ' WHERE DATE(h.created_at) >= ? AND DATE(h.created_at) <= ?'
                    . ' ORDER BY h.id DESC LIMIT 3000';
                $stmt = $db->prepare($sql);
                $stmt->execute([$from, $to]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            $names = $this->driverNames();
            foreach ($rows as $h) {
                $eventType = strtolower(trim((string)($h['event_type'] ?? '')));
                $toStatus = trim((string)($h['to_status'] ?? ''));
                $actionName = match ($eventType) {
                    'case_opened' => 'Discrepancy Case Opened',
                    'status_change' => 'Discrepancy Case ' . ($toStatus !== '' ? $toStatus : 'Updated'),
                    '' => 'Discrepancy Updated',
                    default => 'Discrepancy ' . ucwords(str_replace('_', ' ', $eventType)),
                };
                $actor = trim((string)($h['actor_username'] ?? ''));
                $actorRole = trim((string)($h['actor_role'] ?? ''));
                $demo = strcasecmp($actor, 'Data Simulator') === 0;
                /* The simulator marker is the data source, not a person; fall
                   back to the case's recorded opener/assignee instead. */
                if ($actor === '' || $demo) {
                    $fallbackActor = trim((string)($h['opened_by'] ?? ''));
                    $actor = $fallbackActor !== '' ? $fallbackActor : '';
                    $actorRole = $actor !== '' ? 'finance_officer' : '';
                }
                $driverId = (string)($h['driver_id'] ?? '');
                $driverName = $names[$driverId] ?? '';
                $amount = (float)($h['discrepancy_amount'] ?? 0);
                $detail = trim((string)($h['description'] ?? ''));
                if ($driverId !== '') {
                    $detail .= ($detail !== '' ? ' ' : '') . 'Driver ' . ($driverName !== '' ? "$driverName ($driverId)" : $driverId);
                }
                $bizDate = trim((string)($h['business_date'] ?? ''));
                if ($bizDate !== '') {
                    $detail .= '; business date ' . $bizDate;
                }
                $events[] = $this->financeEvent(
                    'discrepancy',
                    (string)($h['created_at'] ?? ''),
                    $actionName,
                    $actor,
                    $actorRole,
                    'Case #' . (string)($h['case_id'] ?? ''),
                    trim($detail),
                    $toStatus !== '' ? $toStatus : (string)($h['case_status'] ?? ''),
                    $amount,
                    'discrepancy_status_history',
                    $demo
                );
            }
        }

        /* --- Recorded report exports (report_export_log is written only
               after an export actually completed). --- */
        if (($type === '' || $type === 'export') && $this->columnsOf('report_export_log')) {
            try {
                $stmt = $db->prepare(
                    "SELECT * FROM report_export_log
                     WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
                     ORDER BY id DESC LIMIT 1000"
                );
                $stmt->execute([$from, $to]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $r) {
                $period = trim((string)($r['coverage_period'] ?? ''));
                $detail = trim((string)($r['report_title'] ?? '')) !== ''
                    ? (string)$r['report_title']
                    : (string)($r['report_type'] ?? 'Report');
                if ($period !== '') {
                    $detail .= ' — period ' . $period;
                }
                if (!empty($r['format'])) {
                    $detail .= ' (' . $r['format'] . ')';
                }
                if (!empty($r['file_name'])) {
                    $detail .= ' — ' . $r['file_name'];
                }
                $events[] = $this->financeEvent(
                    'export',
                    (string)($r['created_at'] ?? ''),
                    'Report Exported',
                    trim((string)($r['username'] ?? '')),
                    trim((string)($r['user_role'] ?? '')),
                    (string)($r['report_type'] ?? 'Report'),
                    $detail,
                    'Success',
                    0.0,
                    'report_export_log',
                    false
                );
            }
        }

        /* --- Financial audit snapshots generated on this page. --- */
        if (($type === '' || $type === 'audit') && $this->columnsOf('audit_logs')) {
            try {
                $stmt = $db->prepare(
                    "SELECT * FROM audit_logs
                     WHERE DATE(generated_at) >= ? AND DATE(generated_at) <= ?
                     ORDER BY id DESC LIMIT 500"
                );
                $stmt->execute([$from, $to]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $r) {
                $events[] = $this->financeEvent(
                    'audit',
                    (string)($r['generated_at'] ?? ''),
                    'Financial Audit Generated',
                    trim((string)($r['performed_by'] ?? '')),
                    'finance_officer',
                    'AUD-' . str_pad((string)($r['id'] ?? ''), 3, '0', STR_PAD_LEFT),
                    (string)($r['audit_type'] ?? 'Financial Audit'),
                    (string)($r['status'] ?? ''),
                    0.0,
                    'audit_logs',
                    false
                );
            }
        }

        /* --- Recorded audit-trail actions not already covered above
               (finance administrative actions and manifest imports). --- */
        if ($type === '' || $type === 'admin') {
            if ($this->columnsOf('audit_trail')) {
                try {
                    $stmt = $db->prepare(
                        "SELECT id, user_id, username, user_role, module, action, record_id, description, status, user_agent, created_at
                         FROM audit_trail
                         WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
                           AND action <> 'History Demo Account'
                           AND action <> 'Export'
                           AND action NOT LIKE 'Remittance%'
                           AND action NOT LIKE 'Discrepancy%'
                           AND (module = 'Finance' OR action IN ('Generate', 'Import'))
                         ORDER BY id DESC LIMIT 1000"
                    );
                    $stmt->execute([$from, $to]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $rows = [];
                }
                $userRoles = [];
                foreach ($rows as $r) {
                    if (empty($r['username']) && !empty($r['user_id'])) {
                        $uid = (int)$r['user_id'];
                        if (!isset($userRoles[$uid])) {
                            $userRoles[$uid] = '';
                            try {
                                $us = $db->prepare('SELECT username, role FROM users WHERE id = ?');
                                $us->execute([$uid]);
                                $u = $us->fetch(\PDO::FETCH_ASSOC);
                                $userRoles[$uid] = $u ? ((string)($u['username'] ?? '') . '|' . (string)($u['role'] ?? '')) : '';
                            } catch (\Throwable $e) {
                            }
                        }
                        if ($userRoles[$uid] !== '' && empty($r['username'])) {
                            [$r['username'], $r['user_role']] = array_pad(explode('|', $userRoles[$uid], 2), 2, '');
                        }
                    }
                    $demo = (($r['user_agent'] ?? '') === 'trip-history-generator');
                    /* The simulator marker is a data source, never an actor. */
                    if (strcasecmp(trim((string)($r['username'] ?? '')), 'Data Simulator') === 0) {
                        $r['username'] = '';
                        $r['user_role'] = '';
                    }
                    $events[] = $this->financeEvent(
                        'admin',
                        (string)($r['created_at'] ?? ''),
                        (string)($r['action'] ?? 'Action'),
                        trim((string)($r['username'] ?? '')),
                        trim((string)($r['user_role'] ?? '')),
                        trim((string)($r['record_id'] ?? '')),
                        trim((string)($r['description'] ?? '')),
                        (string)($r['status'] ?? ''),
                        0.0,
                        'audit_trail',
                        $demo
                    );
                }
            }
        }

        /* --- Search filter. --- */
        $needle = mb_strtolower(trim($search));
        if ($needle !== '') {
            $events = array_values(array_filter($events, static function (array $e) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $e['action'], $e['actor'], $e['actor_role'], $e['record'], $e['detail'], $e['status'], $e['source'],
                ]));
                return mb_strpos($haystack, $needle) !== false;
            }));
        }

        /* --- Coverage of the selected period (actual recorded months). --- */
        $presentMonths = [];
        $first = null;
        $last = null;
        foreach ($events as $e) {
            $when = (string)($e['when'] ?? '');
            if ($when === '') {
                continue;
            }
            $month = substr($when, 0, 7);
            if ($month !== '') {
                $presentMonths[$month] = true;
            }
            if ($first === null || $when < $first) {
                $first = $when;
            }
            if ($last === null || $when > $last) {
                $last = $when;
            }
        }
        ksort($presentMonths);
        $expectedMonths = [];
        $cursor = strtotime(date('Y-m-01', strtotime($from)));
        $endTs = strtotime(date('Y-m-01', strtotime($to)));
        while ($cursor !== false && $cursor <= $endTs) {
            $expectedMonths[] = date('Y-m', $cursor);
            $cursor = strtotime('+1 month', $cursor);
        }
        $coverage = [
            'first' => $first !== null ? substr($first, 0, 10) : null,
            'last' => $last !== null ? substr($last, 0, 10) : null,
            'months' => array_keys($presentMonths),
            'expected_months' => $expectedMonths,
            'missing_months' => array_values(array_diff($expectedMonths, array_keys($presentMonths))),
        ];

        /* --- Newest first, then paginate. --- */
        usort($events, static function (array $a, array $b): int {
            return strcmp((string)$b['when'], (string)$a['when']);
        });
        $total = count($events);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $slice = array_slice($events, ($page - 1) * $perPage, $perPage);

        return [
            'events' => $slice,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'coverage' => $coverage,
        ];
    }

    /**
     * Period summary for the export cards, using the same sources as the
     * analytics pages: remittance_logs for collections, fuel_refuels for fuel
     * cost, and the recorded expense tables for other expenses.
     */
    public function getExportSummary(string $from, string $to): array
    {
        $db = Database::get();

        $revenue = 0.0;
        $remitCount = 0;
        $remitTable = $this->firstExistingTable(['remittance_logs', 'remittances']);
        if ($remitTable !== null) {
            $rc = $this->columnsOf($remitTable);
            $dateCol = isset($rc['business_date']) ? 'business_date' : 'created_at';
            $cashCol = isset($rc['actual_cash']) ? 'actual_cash' : (isset($rc['total_collection']) ? 'total_collection' : 'expected_cash');
            $distinct = isset($rc['driver_id'])
                ? "COUNT(DISTINCT CONCAT(driver_id, '|', DATE(`$dateCol`)))"
                : 'COUNT(*)';
            try {
                $stmt = $db->prepare(
                    "SELECT COALESCE(SUM(`$cashCol`),0), $distinct FROM `$remitTable`
                     WHERE DATE(`$dateCol`) >= ? AND DATE(`$dateCol`) <= ?"
                );
                $stmt->execute([$from, $to]);
                $row = $stmt->fetch(\PDO::FETCH_NUM);
                $revenue = (float)($row[0] ?? 0);
                $remitCount = (int)($row[1] ?? 0);
            } catch (\Throwable $e) {
            }
        }

        $fuelCost = 0.0;
        if ($this->columnsOf('fuel_refuels')) {
            try {
                $stmt = $db->prepare('SELECT COALESCE(SUM(total_cost),0) FROM fuel_refuels WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?');
                $stmt->execute([$from, $to]);
                $fuelCost = (float)$stmt->fetchColumn();
            } catch (\Throwable $e) {
            }
        }

        $other = 0.0;
        $expenseCount = 0;
        $expTable = $this->firstExistingTable(['expenses', 'financial_expenses']);
        if ($expTable !== null) {
            $ec = $this->columnsOf($expTable);
            $dateCol = isset($ec['expense_date']) ? 'expense_date' : 'created_at';
            $fuelFilter = isset($ec['category']) ? "AND LOWER(COALESCE(category,'')) <> 'fuel'" : '';
            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0), COUNT(*) FROM `$expTable` WHERE DATE(`$dateCol`) >= ? AND DATE(`$dateCol`) <= ? $fuelFilter");
                $stmt->execute([$from, $to]);
                $row = $stmt->fetch(\PDO::FETCH_NUM);
                $other = (float)($row[0] ?? 0);
                $expenseCount = (int)($row[1] ?? 0);
            } catch (\Throwable $e) {
            }
        }

        $tripCount = 0;
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM bus_trips WHERE DATE(trip_date) >= ? AND DATE(trip_date) <= ?');
            $stmt->execute([$from, $to]);
            $tripCount = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
        }

        $passCount = 0;
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid' AND DATE(boarding_time) >= ? AND DATE(boarding_time) <= ?");
            $stmt->execute([$from, $to]);
            $passCount = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
        }

        $expenses = $fuelCost + $other;
        return [
            'total_revenue' => round($revenue, 2),
            'total_expenses' => round($expenses, 2),
            'fuel_expense' => round($fuelCost, 2),
            'other_expenses' => round($other, 2),
            'net_profit' => round($revenue - $expenses, 2),
            'remittance_count' => $remitCount,
            'expense_count' => $expenseCount,
            'trip_count' => $tripCount,
            'passenger_count' => $passCount,
        ];
    }
}
