<?php declare(strict_types=1);

namespace Admin\Models;

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Database.php';

use App\Core\Database;
use PDO;
use Exception;

class GenerateReportModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    /**
     * Fetch the records for a report type, applying the normalized filters
     * server-side. Filter keys: period (mode/start/end), driver_id, bus_no,
     * status, search, route. Types without a date column ignore period.
     */
    public function generate(string $reportType, array $filters = []): array
    {
        $allowedReports = [
            'drivers',
            'driver_accounts',
            'driver_bus_assignments',
            'fares',
            'inspectors',
            'ai_trip_operations_reports',
            'passengers',
            'passenger_trips',
            'passenger_trip_history',
            'remittance_logs',
            'route_stops',
            'trip_operations_reports',
            'trip_operation_evidences',
        ];

        if (!in_array($reportType, $allowedReports, true)) {
            throw new Exception('Invalid report type.');
        }

        $period = $filters['period'] ?? null;
        $periodStart = is_array($period) ? (string)($period['start'] ?? '') : '';
        $periodEnd = is_array($period) ? (string)($period['end'] ?? '') : '';
        $driverId = (string)($filters['driver_id'] ?? '');
        $busNo = (string)($filters['bus_no'] ?? '');
        $status = (string)($filters['status'] ?? '');
        $search = (string)($filters['search'] ?? '');
        $route = (string)($filters['route'] ?? '');

        $like = static fn (string $term): string => '%' . $term . '%';

        $caseSql = '';
        $caseWhere = [];
        $caseTail = '';
        $params = [];

        switch ($reportType) {
            case 'drivers':
                $caseSql = "
                    SELECT
                        d.id,
                        d.full_name,
                        d.status,
                        d.license_number,
                        d.license_type,
                        d.license_expiry,
                        d.experience_years,
                        d.assigned_buses,
                        d.routes,
                        d.email,
                        d.contact_number,
                        d.created_at,
                        da.username,
                        COUNT(dba.bus_id) AS total_bus_assignments
                    FROM drivers d
                    LEFT JOIN driver_accounts da
                        ON d.driver_id = da.driver_id
                    LEFT JOIN driver_bus_assignment dba
                        ON d.driver_id = dba.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'd.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(d.status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(d.full_name LIKE ? OR d.license_number LIKE ? OR d.email LIKE ? OR d.contact_number LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search), $like($search));
                }
                $caseTail = ' GROUP BY d.id ORDER BY d.created_at DESC';
                break;

            case 'driver_accounts':
                $caseSql = "
                    SELECT
                        da.id,
                        da.driver_id,
                        da.username,
                        da.created_at,
                        d.full_name,
                        d.status,
                        d.license_number,
                        d.contact_number,
                        d.email
                    FROM driver_accounts da
                    LEFT JOIN drivers d
                        ON da.driver_id = d.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'da.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(d.status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(d.full_name LIKE ? OR da.username LIKE ? OR d.license_number LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY da.created_at DESC';
                break;

            case 'driver_bus_assignments':
                $caseSql = "
                    SELECT
                        dba.id,
                        dba.driver_id,
                        dba.bus_id,
                        dba.created_at,
                        d.full_name,
                        d.status,
                        d.license_number,
                        d.assigned_buses,
                        d.routes,
                        da.username
                    FROM driver_bus_assignment dba
                    LEFT JOIN drivers d
                        ON dba.driver_id = d.driver_id
                    LEFT JOIN driver_accounts da
                        ON d.driver_id = da.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'dba.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($busNo !== '') {
                    $caseWhere[] = 'dba.bus_id = ?';
                    $params[] = $busNo;
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(d.status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(d.full_name LIKE ? OR dba.bus_id LIKE ? OR da.username LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY dba.id DESC';
                break;

            case 'fares':
                $caseSql = 'SELECT f.* FROM fares f';
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'f.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($search !== '') {
                    $caseWhere[] = '(f.route_from LIKE ? OR f.route_to LIKE ?)';
                    array_push($params, $like($search), $like($search));
                }
                $caseTail = ' ORDER BY f.created_at DESC';
                break;

            case 'inspectors':
                $caseSql = "
                    SELECT
                        i.inspector_pk,
                        i.account_id,
                        i.inspector_identity,
                        i.inspector_name,
                        i.department,
                        i.experience_years,
                        i.operational_assignment,
                        i.assigned_routes,
                        i.contact_number,
                        i.email,
                        i.profile_picture,
                        i.additional_notes,
                        ia.role,
                        ia.status,
                        ia.created_at AS account_created_at
                    FROM inspectors i
                    LEFT JOIN inspector_accounts ia
                        ON i.account_id = ia.account_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'ia.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(ia.status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(i.inspector_name LIKE ? OR i.email LIKE ? OR i.department LIKE ? OR i.operational_assignment LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY ia.created_at DESC';
                break;

            case 'ai_trip_operations_reports':
                $caseSql = "
                    SELECT
                        id,
                        report_title,
                        report_category,
                        generated_at
                    FROM ai_trip_operations_reports
                ";
                $caseWhere[] = 'archived = 0';
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'generated_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($search !== '') {
                    $caseWhere[] = '(report_title LIKE ? OR report_category LIKE ?)';
                    array_push($params, $like($search), $like($search));
                }
                $caseTail = ' ORDER BY generated_at DESC';
                break;

            case 'passengers':
                $caseSql = "
                    SELECT
                        p.id,
                        p.full_name,
                        p.email,
                        p.phone,
                        p.discount_type,
                        p.student_id,
                        p.discount_id,
                        p.discount_status,
                        p.latitude,
                        p.longitude,
                        p.created_at,
                        COUNT(pth.history_id) AS total_trip_history,
                        COALESCE(SUM(pth.fare), 0) AS total_fare_paid
                    FROM passengers p
                    LEFT JOIN passenger_trip_history pth
                        ON p.id = pth.passenger_name
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'p.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($search !== '') {
                    $caseWhere[] = '(p.full_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search));
                }
                $caseTail = ' GROUP BY p.id ORDER BY p.created_at DESC';
                break;

            case 'passenger_trips':
                $caseSql = "
                    SELECT
                        pt.id,
                        pt.passenger_id,
                        pt.driver_id,
                        pt.trip_no,
                        pt.bus_no,
                        pt.boarding_point,
                        pt.destination,
                        pt.fare,
                        pt.payment_method,
                        pt.payment_status,
                        pt.cash_amount,
                        pt.boarding_time,
                        pt.dropoff_time,
                        pt.archived_status,
                        pt.remittance_status,
                        pt.expected_cash,
                        pt.actual_cash,
                        pt.discrepancy_amount,
                        pt.finance_note,
                        p.full_name,
                        p.discount_type,
                        d.full_name AS driver_name,
                        CASE
                            WHEN pt.discrepancy_amount > 0
                            THEN 'With Discrepancy'
                            ELSE 'Balanced'
                        END AS financial_status
                    FROM passenger_trips pt
                    LEFT JOIN passengers p
                        ON pt.passenger_id = p.id
                    LEFT JOIN drivers d
                        ON pt.driver_id = d.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'pt.boarding_time BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($driverId !== '') {
                    $caseWhere[] = 'pt.driver_id = ?';
                    $params[] = $driverId;
                }
                if ($busNo !== '') {
                    $caseWhere[] = 'pt.bus_no = ?';
                    $params[] = $busNo;
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(pt.payment_status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(p.full_name LIKE ? OR pt.boarding_point LIKE ? OR pt.destination LIKE ? OR pt.trip_no LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY pt.boarding_time DESC';
                break;

            case 'passenger_trip_history':
                $caseSql = "
                    SELECT
                        pth.history_id,
                        pth.trip_id,
                        pth.passenger_name,
                        pth.passenger_type,
                        pth.student_id,
                        pth.boarding_point,
                        pth.destination,
                        pth.bus_number,
                        pth.fare,
                        pth.payment_method,
                        pth.date_booked,
                        pth.trip_date,
                        pth.archived_at,
                        pth.remarks,
                        pth.senior_pwd_id,
                        pth.beneficiary_id,
                        pth.coop_disc_id,
                        pth.driver_id,
                        d.full_name AS driver_name,
                        p.full_name,
                        p.discount_type,
                        p.discount_status
                    FROM passenger_trip_history pth
                    LEFT JOIN passengers p
                        ON pth.passenger_name = p.full_name
                    LEFT JOIN drivers d
                        ON pth.driver_id = d.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'pth.trip_date BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($driverId !== '') {
                    $caseWhere[] = 'pth.driver_id = ?';
                    $params[] = $driverId;
                }
                if ($busNo !== '') {
                    $caseWhere[] = 'pth.bus_number = ?';
                    $params[] = $busNo;
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(pth.passenger_type) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(pth.passenger_name LIKE ? OR pth.boarding_point LIKE ? OR pth.destination LIKE ? OR pth.bus_number LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY pth.trip_date DESC';
                break;

            case 'remittance_logs':
                $caseSql = "
                    SELECT
                        rl.id,
                        rl.driver_id,
                        rl.bus_no,
                        rl.total_collection,
                        rl.expected_cash,
                        rl.actual_cash,
                        rl.status,
                        rl.submitted_by,
                        rl.verified_at,
                        rl.created_at,
                        rl.discrepancy_amount,
                        rl.finance_code,
                        rl.confirmed_by,
                        rl.trip_no,
                        rl.total_passengers,
                        d.full_name,
                        d.contact_number,
                        d.assigned_buses,
                        CASE
                            WHEN rl.discrepancy_amount > 0
                            THEN 'With Financial Discrepancy'
                            ELSE 'Financially Balanced'
                        END AS financial_condition
                    FROM remittance_logs rl
                    LEFT JOIN drivers d
                        ON rl.driver_id = d.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'rl.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($driverId !== '') {
                    $caseWhere[] = 'rl.driver_id = ?';
                    $params[] = $driverId;
                }
                if ($busNo !== '') {
                    $caseWhere[] = 'rl.bus_no = ?';
                    $params[] = $busNo;
                }
                if ($status !== '') {
                    $caseWhere[] = 'LOWER(rl.status) = LOWER(?)';
                    $params[] = $status;
                }
                if ($search !== '') {
                    $caseWhere[] = '(rl.finance_code LIKE ? OR rl.trip_no LIKE ? OR d.full_name LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY rl.created_at DESC';
                break;

            case 'route_stops':
                $caseSql = "
                    SELECT
                        rs.id,
                        rs.route_name,
                        rs.stop_name,
                        rs.latitude,
                        rs.longitude,
                        rs.sequence_order,
                        rs.created_at,
                        CASE
                            WHEN rs.sequence_order <= 3
                            THEN 'Primary Stop'
                            ELSE 'Secondary Stop'
                        END AS stop_category
                    FROM route_stops rs
                ";
                if ($route !== '') {
                    $caseWhere[] = 'rs.route_name = ?';
                    $params[] = $route;
                }
                if ($search !== '') {
                    $caseWhere[] = '(rs.stop_name LIKE ? OR rs.route_name LIKE ?)';
                    array_push($params, $like($search), $like($search));
                }
                $caseTail = ' ORDER BY rs.route_name ASC, rs.sequence_order ASC';
                break;

            case 'trip_operations_reports':
                $caseSql = "
                    SELECT
                        tor.id,
                        tor.trip_group_code,
                        tor.route_id,
                        tor.bus_id,
                        tor.driver_id,
                        tor.inspector_id,
                        tor.trip_date,
                        tor.passenger_count,
                        tor.total_revenue,
                        tor.fuel_cost,
                        tor.other_expenses,
                        tor.odometer,
                        tor.notes,
                        tor.ai_analysis_notes,
                        tor.evidence_files,
                        tor.created_at,
                        tor.net_income,
                        d.full_name AS driver_name,
                        i.inspector_name,
                        CASE
                            WHEN tor.net_income > 0
                            THEN 'Profitable Operation'
                            ELSE 'Low Revenue Operation'
                        END AS operation_status
                    FROM trip_operations_reports tor
                    LEFT JOIN drivers d
                        ON tor.driver_id = d.driver_id
                    LEFT JOIN inspectors i
                        ON tor.inspector_id = i.inspector_pk
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'tor.trip_date BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($driverId !== '') {
                    $caseWhere[] = 'tor.driver_id = ?';
                    $params[] = $driverId;
                }
                if ($busNo !== '') {
                    $caseWhere[] = 'tor.bus_id = ?';
                    $params[] = $busNo;
                }
                if ($status !== '') {
                    if (strcasecmp($status, 'Profitable Operation') === 0) {
                        $caseWhere[] = 'tor.net_income > 0';
                    } else {
                        $caseWhere[] = 'tor.net_income <= 0';
                    }
                }
                if ($search !== '') {
                    $caseWhere[] = '(tor.trip_group_code LIKE ? OR d.full_name LIKE ? OR tor.route_id LIKE ? OR tor.notes LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY tor.trip_date DESC';
                break;

            case 'trip_operation_evidences':
                $caseSql = "
                    SELECT
                        toe.id,
                        toe.trip_id,
                        toe.evidence_file,
                        toe.evidence_type,
                        toe.notes,
                        toe.created_at,
                        tor.trip_group_code,
                        tor.trip_date,
                        tor.passenger_count,
                        tor.total_revenue,
                        tor.net_income,
                        d.full_name AS driver_name,
                        CASE
                            WHEN toe.evidence_file IS NOT NULL
                            THEN 'Evidence Available'
                            ELSE 'No Evidence Uploaded'
                        END AS evidence_status
                    FROM trip_operation_evidences toe
                    LEFT JOIN trip_operations_reports tor
                        ON toe.trip_id = tor.id
                    LEFT JOIN drivers d
                        ON tor.driver_id = d.driver_id
                ";
                if ($periodStart !== '' && $periodEnd !== '') {
                    $caseWhere[] = 'toe.created_at BETWEEN ? AND ?';
                    array_push($params, $periodStart, $periodEnd);
                }
                if ($driverId !== '') {
                    $caseWhere[] = 'tor.driver_id = ?';
                    $params[] = $driverId;
                }
                if ($status !== '') {
                    if (strcasecmp($status, 'Evidence Available') === 0) {
                        $caseWhere[] = 'toe.evidence_file IS NOT NULL';
                    } else {
                        $caseWhere[] = 'toe.evidence_file IS NULL';
                    }
                }
                if ($search !== '') {
                    $caseWhere[] = '(tor.trip_group_code LIKE ? OR toe.evidence_file LIKE ? OR d.full_name LIKE ?)';
                    array_push($params, $like($search), $like($search), $like($search));
                }
                $caseTail = ' ORDER BY toe.created_at DESC';
                break;

            default:
                throw new Exception('Unsupported report.');
        }

        $sql = $caseSql
            . (count($caseWhere) ? ' WHERE ' . implode(' AND ', $caseWhere) : '')
            . $caseTail;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'report_type' => $reportType,
            'total_records' => count($rows),
            'generated_at' => date('Y-m-d H:i:s'),
            'records' => $rows,
        ];
    }

    /**
     * Distinct values for a column, used by the filter option lists.
     */
    public function distinctValues(string $table, string $column, int $limit = 100): array
    {
        $allowed = [
            'drivers' => ['status'],
            'driver_accounts' => ['status'],
            'driver_bus_assignment' => ['bus_id'],
            'passenger_trips' => ['payment_status', 'bus_no'],
            'passenger_trip_history' => ['passenger_type', 'bus_number'],
            'remittance_logs' => ['status', 'bus_no'],
            'inspector_accounts' => ['status'],
            'route_stops' => ['route_name'],
            'buses' => ['bus_id'],
        ];

        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return [];
        }

        $sql = "SELECT DISTINCT `$column` AS v FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> '' ORDER BY `$column` ASC LIMIT " . (int)$limit;
        try {
            $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
        return array_map('strval', $rows);
    }

    public function driverOptions(): array
    {
        try {
            $rows = $this->db->query("
                SELECT d.driver_id AS id, MAX(d.full_name) AS name
                FROM drivers d
                GROUP BY d.driver_id
                ORDER BY name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(static fn ($r) => ['id' => (string)$r['id'], 'name' => (string)($r['name'] ?? $r['id'])], $rows);
    }

    public function busOptions(): array
    {
        try {
            $rows = $this->db->query("
                SELECT b.bus_id AS id, b.plate_number AS plate
                FROM buses b
                ORDER BY b.bus_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(static function ($r) {
            $id = (string)$r['id'];
            $plate = trim((string)($r['plate'] ?? ''));
            return ['id' => $id, 'name' => $plate !== '' ? ($id . ' — ' . $plate) : $id];
        }, $rows);
    }
}
