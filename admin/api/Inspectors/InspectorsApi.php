<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Auth;
use App\Core\Database;

header('Content-Type: application/json');

if (
    !Auth::check() ||
    !in_array(Auth::user()['role'], ['admin', 'super_admin'])
) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$db = Database::get();

$action = $_GET['action'] ?? '';

/* =====================================================
   INSPECTOR MONITORING HELPERS (read-only)
   inspector_inspection_items is not defined in this repo; every read is
   schema-adaptive and never creates or alters the table.
   ===================================================== */

if (!function_exists('ii_table_exists')) {
    function ii_table_exists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ii_columns')) {
    function ii_columns(PDO $db, string $table): array
    {
        $cols = [];
        try {
            $safe = str_replace('`', '', $table);
            $stmt = $db->query("SHOW COLUMNS FROM `$safe`");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cols[strtolower((string)$row['Field'])] = (string)$row['Field'];
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }
}

if (!function_exists('ii_value')) {
    function ii_value(array $row, array $candidates)
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row) && $row[$candidate] !== null && $row[$candidate] !== '') {
                return $row[$candidate];
            }
        }
        return null;
    }
}

if (!function_exists('ii_monitoring_filters')) {
    function ii_monitoring_filters(): array
    {
        $filters = [];
        foreach (['date', 'fromDate', 'toDate', 'inspector', 'bus', 'driver', 'trip', 'status'] as $key) {
            $value = $_GET[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = trim($value);
            }
        }
        return $filters;
    }
}

if (!function_exists('ii_resolve_rows')) {
    /**
     * Fetch inspector inspection rows and resolve inspector/trip/bus/payment
     * context from the columns the table exposes, falling back to existing
     * passenger trip / inspector records. Read-only.
     *
     * @return array{available:bool,rows:list<array<string,mixed>>,message?:string}
     */
    function ii_resolve_rows(PDO $db, array $filters, int $limit = 2000): array
    {
        if (!ii_table_exists($db, 'inspector_inspection_items')) {
            return [
                'available' => false,
                'rows'      => [],
                'message'   => 'Inspector inspection records are unavailable.',
            ];
        }
        $cols = ii_columns($db, 'inspector_inspection_items');
        if (empty($cols) || !isset($cols['validation_status'])) {
            return [
                'available' => false,
                'rows'      => [],
                'message'   => 'Inspector inspection records are unavailable.',
            ];
        }

        $dateCol = $cols['verified_at'] ?? ($cols['created_at'] ?? null);
        $where = [];
        $params = [];
        if ($dateCol !== null) {
            if (!empty($filters['date'])) {
                $where[] = "DATE(`$dateCol`) = :date";
                $params[':date'] = $filters['date'];
            }
            if (!empty($filters['fromDate'])) {
                $where[] = "DATE(`$dateCol`) >= :from";
                $params[':from'] = $filters['fromDate'];
            }
            if (!empty($filters['toDate'])) {
                $where[] = "DATE(`$dateCol`) <= :to";
                $params[':to'] = $filters['toDate'];
            }
        }
        if (!empty($filters['status'])) {
            $where[] = "UPPER(TRIM(`{$cols['validation_status']}`)) = :status";
            $params[':status'] = strtoupper($filters['status']);
        }

        $sql = 'SELECT * FROM inspector_inspection_items'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . ($dateCol !== null ? "`$dateCol` DESC" : '1') . " LIMIT $limit";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('InspectorsApi inspector_monitoring query failed: ' . $e->getMessage());
            return ['available' => false, 'rows' => [], 'message' => 'Inspector inspection records are unavailable.'];
        }

        /* Passenger-trip fallback lookup. */
        $ptCols = ii_table_exists($db, 'passenger_trips') ? ii_columns($db, 'passenger_trips') : [];
        $ptByPid = [];
        $ptByCode = [];
        if (!empty($ptCols)) {
            $pids = [];
            $codes = [];
            foreach ($raw as $row) {
                $pid = ii_value($row, ['passenger_id']);
                $code = ii_value($row, ['passenger_code']);
                if ($pid !== null) { $pids[(string)$pid] = true; }
                if ($code !== null) { $codes[strtolower((string)$code)] = true; }
            }
            $wherePt = [];
            $paramsPt = [];
            if (isset($ptCols['passenger_id']) && $pids) {
                $ph = [];
                $i = 0;
                foreach (array_keys($pids) as $pid) {
                    $key = ':pt_pid' . $i++;
                    $ph[] = $key;
                    $paramsPt[$key] = $pid;
                }
                $wherePt[] = '`' . $ptCols['passenger_id'] . '` IN (' . implode(', ', $ph) . ')';
            }
            if (isset($ptCols['passenger_code']) && $codes) {
                $ph = [];
                $i = 0;
                foreach (array_keys($codes) as $code) {
                    $key = ':pt_code' . $i++;
                    $ph[] = $key;
                    $paramsPt[$key] = $code;
                }
                $wherePt[] = 'LOWER(`' . $ptCols['passenger_code'] . '`) IN (' . implode(', ', $ph) . ')';
            }
            if (!empty($wherePt)) {
                try {
                    $stmtPt = $db->prepare('SELECT * FROM passenger_trips WHERE ' . implode(' OR ', $wherePt) . ' LIMIT 5000');
                    $stmtPt->execute($paramsPt);
                    foreach ($stmtPt->fetchAll(PDO::FETCH_ASSOC) as $ptRow) {
                        if (isset($ptRow['passenger_id'])) {
                            $ptByPid[(string)$ptRow['passenger_id']] = $ptRow;
                        }
                        if (isset($ptRow['passenger_code'])) {
                            $ptByCode[strtolower((string)$ptRow['passenger_code'])] = $ptRow;
                        }
                    }
                } catch (Throwable $e) {
                    $ptByPid = [];
                    $ptByCode = [];
                }
            }
        }

        /* Inspector name lookup. */
        $inspCols = ii_table_exists($db, 'inspectors') ? ii_columns($db, 'inspectors') : [];
        $inspIdentityCol = $inspCols['inspector_identity'] ?? ($inspCols['inspector_id'] ?? null);
        $inspNameCol = $inspCols['inspector_name'] ?? null;
        $inspectorNames = [];
        $inspectorIds = [];
        foreach ($raw as $row) {
            $id = ii_value($row, ['inspector_id']);
            if ($id === null) {
                $pid = ii_value($row, ['passenger_id']);
                $code = ii_value($row, ['passenger_code']);
                $pt = ($pid !== null && isset($ptByPid[(string)$pid])) ? $ptByPid[(string)$pid]
                    : (($code !== null && isset($ptByCode[strtolower((string)$code)])) ? $ptByCode[strtolower((string)$code)] : null);
                if ($pt && isset($pt['inspector_id'])) {
                    $id = $pt['inspector_id'];
                }
            }
            if ($id !== null) {
                $inspectorIds[(string)$id] = true;
            }
        }
        if ($inspIdentityCol !== null && $inspNameCol !== null && !empty($inspectorIds)) {
            try {
                $ph = [];
                $paramsInsp = [];
                $i = 0;
                foreach (array_keys($inspectorIds) as $id) {
                    $key = ':ins' . $i++;
                    $ph[] = $key;
                    $paramsInsp[$key] = $id;
                }
                $stmtIns = $db->prepare(
                    "SELECT `$inspIdentityCol` AS identity, `$inspNameCol` AS name FROM inspectors WHERE `$inspIdentityCol` IN (" . implode(', ', $ph) . ')'
                );
                $stmtIns->execute($paramsInsp);
                foreach ($stmtIns->fetchAll(PDO::FETCH_ASSOC) as $insRow) {
                    $inspectorNames[(string)$insRow['identity']] = trim((string)$insRow['name']);
                }
            } catch (Throwable $e) {
                $inspectorNames = [];
            }
        }

        $resolved = [];
        foreach ($raw as $row) {
            $pid = ii_value($row, ['passenger_id']);
            $code = ii_value($row, ['passenger_code']);
            $pt = ($pid !== null && isset($ptByPid[(string)$pid])) ? $ptByPid[(string)$pid]
                : (($code !== null && isset($ptByCode[strtolower((string)$code)])) ? $ptByCode[strtolower((string)$code)] : null);

            $inspectorId = ii_value($row, ['inspector_id']);
            if ($inspectorId === null && $pt) {
                $inspectorId = ii_value($pt, ['inspector_id']);
            }
            $inspectorName = ii_value($row, ['inspector_name']);
            if ($inspectorName === null && $inspectorId !== null) {
                $inspectorName = $inspectorNames[(string)$inspectorId] ?? null;
            }

            $tripId = ii_value($row, ['trip_id', 'trip_no']);
            $busId = ii_value($row, ['bus_id', 'bus_no']);
            $driverId = ii_value($row, ['driver_id']);
            if ($pt) {
                $tripId = $tripId ?? ii_value($pt, ['trip_no', 'trip_id']);
                $busId = $busId ?? ii_value($pt, ['bus_no', 'bus_id']);
                $driverId = $driverId ?? ii_value($pt, ['driver_id']);
            }

            $fare = ii_value($row, ['fare']);
            if ($fare === null && $pt) {
                $fare = ii_value($pt, ['fare']);
            }
            $paymentStatus = (string)(ii_value($row, ['payment_status']) ?? '');
            $ptPaymentStatus = $pt ? (string)(ii_value($pt, ['payment_status']) ?? '') : '';
            $farePt = $pt ? ii_value($pt, ['fare']) : null;

            $validation = strtoupper(trim((string)(ii_value($row, ['validation_status']) ?? 'UNVERIFIED')));
            $reasons = trim((string)(ii_value($row, ['discrepancy_reasons']) ?? ''));

            /* Cross-validation: read-time only, nothing is written. */
            $crossReasons = [];
            $paymentDiscrepancy = false;
            $normalizedPayment = strtolower($paymentStatus);
            if ($paymentStatus !== '' && !in_array($normalizedPayment, ['paid', 'remitted'], true)) {
                $paymentDiscrepancy = true;
                $crossReasons[] = 'Payment status: ' . $paymentStatus;
            }
            if ($ptPaymentStatus !== '' && $paymentStatus !== '' && strcasecmp($ptPaymentStatus, $paymentStatus) !== 0) {
                $paymentDiscrepancy = true;
                $crossReasons[] = 'Payment mismatch (inspector ' . $paymentStatus . ' vs trip ' . $ptPaymentStatus . ')';
            }
            if ($fare !== null && $farePt !== null && is_numeric($fare) && is_numeric($farePt) && abs((float)$fare - (float)$farePt) > 0.01) {
                $crossReasons[] = 'Fare mismatch (inspector ' . $fare . ' vs trip ' . $farePt . ')';
            }

            $detailDiscrepancy = ($reasons !== '') || !empty($crossReasons);
            $displayReasons = $reasons;
            if (!empty($crossReasons)) {
                $displayReasons = trim($reasons . ($reasons !== '' ? '; ' : '') . implode('; ', $crossReasons));
            }

            $verifiedAt = (string)(ii_value($row, ['verified_at', 'created_at']) ?? '');

            $resolved[] = [
                'inspection_id'      => (string)(ii_value($row, ['inspection_id', 'id']) ?? ''),
                'inspector_id'       => $inspectorId !== null ? (string)$inspectorId : '',
                'inspector_name'     => $inspectorName !== null && $inspectorName !== '' ? $inspectorName : ($inspectorId !== null ? (string)$inspectorId : 'Unassigned inspector'),
                'trip_id'            => $tripId !== null ? (string)$tripId : '',
                'bus_id'             => $busId !== null ? (string)$busId : '',
                'driver_id'          => $driverId !== null ? (string)$driverId : '',
                'passenger_id'       => $pid !== null ? (string)$pid : '',
                'passenger_code'     => $code !== null ? (string)$code : '',
                'passenger_name'     => (string)(ii_value($row, ['passenger_name']) ?? 'Unknown passenger'),
                'passenger_type'     => (string)(ii_value($row, ['passenger_type']) ?? ''),
                'boarding_point'     => (string)(ii_value($row, ['boarding_point']) ?? ''),
                'destination'        => (string)(ii_value($row, ['destination']) ?? ''),
                'fare'               => $fare !== null && is_numeric($fare) ? (float)$fare : null,
                'payment_status'     => $paymentStatus,
                'validation_status'  => $validation,
                'discrepancy_reasons'=> $displayReasons,
                'checklist_results'  => (string)(ii_value($row, ['checklist_results']) ?? ''),
                'remarks'            => (string)(ii_value($row, ['remarks']) ?? ''),
                'verified_at'        => $verifiedAt,
                'created_at'         => (string)(ii_value($row, ['created_at']) ?? ''),
                'payment_discrepancy'=> $paymentDiscrepancy,
                'detail_discrepancy' => $detailDiscrepancy,
                'needs_review'       => ($validation === 'NEEDS REVIEW') || $paymentDiscrepancy || $detailDiscrepancy,
            ];
        }

        return ['available' => true, 'rows' => $resolved];
    }
}

if (!function_exists('ii_group_rows')) {
    /**
     * Group resolved inspection rows by inspector + trip (+ bus) and aggregate
     * the monitoring summary. Rows are never modified.
     */
    function ii_group_rows(array $rows, array $filters): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (!empty($filters['inspector'])
                && strcasecmp((string)$row['inspector_id'], (string)$filters['inspector']) !== 0
                && strcasecmp((string)$row['inspector_name'], (string)$filters['inspector']) !== 0) {
                continue;
            }
            if (!empty($filters['bus']) && strcasecmp((string)$row['bus_id'], (string)$filters['bus']) !== 0) {
                continue;
            }
            if (!empty($filters['driver']) && strcasecmp((string)$row['driver_id'], (string)$filters['driver']) !== 0) {
                continue;
            }
            if (!empty($filters['trip']) && strcasecmp((string)$row['trip_id'], (string)$filters['trip']) !== 0) {
                continue;
            }

            $tripKey = $row['trip_id'] !== '' ? $row['trip_id'] : ('day:' . substr((string)$row['verified_at'], 0, 10));
            $groupKey = $row['inspector_id'] . '|' . $tripKey . '|' . $row['bus_id'];

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key'             => $groupKey,
                    'inspector_id'          => $row['inspector_id'],
                    'inspector'             => $row['inspector_name'],
                    'trip_id'               => $row['trip_id'],
                    'trip_label'            => $row['trip_id'] !== '' ? ('TRIP-' . $row['trip_id']) : 'Trip unavailable',
                    'bus_id'                => $row['bus_id'],
                    'driver_id'             => $row['driver_id'],
                    'passenger_count'       => 0,
                    'validated_count'       => 0,
                    'needs_review_count'    => 0,
                    'unverified_count'      => 0,
                    'payment_discrepancies' => 0,
                    'detail_discrepancies'  => 0,
                    'last_verification'     => '',
                ];
            }

            $g = &$groups[$groupKey];
            $g['passenger_count']++;
            if ($row['validation_status'] === 'VALIDATED') {
                $g['validated_count']++;
            }
            if ($row['validation_status'] === 'UNVERIFIED') {
                $g['unverified_count']++;
            }
            if ($row['needs_review']) {
                $g['needs_review_count']++;
            }
            if ($row['payment_discrepancy']) {
                $g['payment_discrepancies']++;
            }
            if ($row['detail_discrepancy']) {
                $g['detail_discrepancies']++;
            }
            $at = (string)($row['verified_at'] !== '' ? $row['verified_at'] : $row['created_at']);
            if ($at !== '' && $at > $g['last_verification']) {
                $g['last_verification'] = $at;
            }
            unset($g);
        }

        $list = array_values($groups);
        foreach ($list as &$g) {
            if ($g['needs_review_count'] > 0) {
                $g['inspection_status'] = 'Needs Review';
            } elseif ($g['unverified_count'] > 0) {
                $g['inspection_status'] = 'Unverified';
            } else {
                $g['inspection_status'] = 'Validated';
            }
        }
        unset($g);

        usort($list, static fn($a, $b) => strcmp((string)$b['last_verification'], (string)$a['last_verification']));

        return $list;
    }
}

if (!function_exists('ii_monitoring_totals')) {
    function ii_monitoring_totals(array $groups): array
    {
        $totals = [
            'groups'                => count($groups),
            'passengers'            => 0,
            'validated'             => 0,
            'needs_review'          => 0,
            'payment_discrepancies' => 0,
            'detail_discrepancies'  => 0,
        ];
        foreach ($groups as $g) {
            $totals['passengers'] += (int)$g['passenger_count'];
            $totals['validated'] += (int)$g['validated_count'];
            $totals['needs_review'] += (int)$g['needs_review_count'];
            $totals['payment_discrepancies'] += (int)$g['payment_discrepancies'];
            $totals['detail_discrepancies'] += (int)$g['detail_discrepancies'];
        }
        return $totals;
    }
}

if (!function_exists('ii_unavailable_payload')) {
    function ii_unavailable_payload(string $message): array
    {
        return [
            'available'    => false,
            'message'      => $message,
            'generated_at' => date('Y-m-d H:i:s'),
            'rows'         => [],
            'totals'       => ii_monitoring_totals([]),
        ];
    }
}

try {

    switch ($action) {

        // =====================================================
        // GET ALL SCHEDULES
        // =====================================================

        case 'get_schedules':

            $stmt = $db->query("
                SELECT

                    pt.trip_no,
                    pt.bus_no,

                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,

                    b.from_terminal_id,
                    b.to_terminal_id,

                    ft.terminal_name AS from_terminal,
                    tt.terminal_name AS to_terminal,

                    GROUP_CONCAT(
                        DISTINCT i.inspector_name
                        ORDER BY i.inspector_name
                        SEPARATOR ', '
                    ) AS inspectors,

                    GROUP_CONCAT(
                        DISTINCT i.inspector_identity
                        ORDER BY i.inspector_identity
                        SEPARATOR ', '
                    ) AS inspector_ids,

                    COUNT(DISTINCT pt.passenger_id)
                        AS passenger_count

                FROM passenger_trips pt

                LEFT JOIN buses b
                    ON b.bus_id = pt.bus_no

                LEFT JOIN terminals ft
                    ON ft.id = b.from_terminal_id

                LEFT JOIN terminals tt
                    ON tt.id = b.to_terminal_id

                LEFT JOIN inspectors i
                    ON i.inspector_identity = pt.inspector_id

                WHERE
                    b.status <> 'Archived'

                GROUP BY

                    pt.trip_no,
                    pt.bus_no,
                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,
                    b.from_terminal_id,
                    b.to_terminal_id,
                    ft.terminal_name,
                    tt.terminal_name

                ORDER BY
                    pt.trip_no DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================
        // GET SINGLE SCHEDULE
        // =====================================================

        case 'get_schedule':

            $tripNo = trim(
                $_GET['trip_no'] ?? ''
            );

            if ($tripNo === '') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Trip number required.'
                ]);

                break;
            }

            $stmt = $db->prepare("
                SELECT

                    pt.trip_no,
                    pt.bus_no,

                    b.bus_id,
                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,

                    b.from_terminal_id,
                    b.to_terminal_id,

                    ft.terminal_name AS from_terminal,
                    tt.terminal_name AS to_terminal,

                    GROUP_CONCAT(
                        DISTINCT i.inspector_identity
                        ORDER BY i.inspector_identity
                        SEPARATOR ','
                    ) AS inspector_ids,

                    GROUP_CONCAT(
                        DISTINCT i.inspector_name
                        ORDER BY i.inspector_name
                        SEPARATOR ', '
                    ) AS inspectors

                FROM passenger_trips pt

                LEFT JOIN buses b
                    ON b.bus_id = pt.bus_no

                LEFT JOIN terminals ft
                    ON ft.id = b.from_terminal_id

                LEFT JOIN terminals tt
                    ON tt.id = b.to_terminal_id

                LEFT JOIN inspectors i
                    ON i.inspector_identity = pt.inspector_id

                WHERE
                    pt.trip_no = ?

                GROUP BY

                    pt.trip_no,
                    pt.bus_no,
                    b.bus_id,
                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,
                    b.from_terminal_id,
                    b.to_terminal_id,
                    ft.terminal_name,
                    tt.terminal_name
            ");

            $stmt->execute([
                $tripNo
            ]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetch(PDO::FETCH_ASSOC)
            ]);

            break;
                    // =====================================================
        // UPDATE BUS SCHEDULE
        // =====================================================

        case 'update_schedule':

            $busId = trim($_POST['bus_id'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $fromTerminal = (int)($_POST['from_terminal_id'] ?? 0);
            $toTerminal = (int)($_POST['to_terminal_id'] ?? 0);

            if (
                $busId === '' ||
                $schedule === '' ||
                !$fromTerminal ||
                !$toTerminal
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields.'
                ]);

                break;
            }

            // Get terminal names
            $stmt = $db->prepare("
                SELECT
                    id,
                    terminal_name
                FROM terminals
                WHERE id IN (?, ?)
            ");

            $stmt->execute([
                $fromTerminal,
                $toTerminal
            ]);

            $terminals = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $terminals[$row['id']] = $row['terminal_name'];
            }

            $routeName =
                ($terminals[$fromTerminal] ?? '')
                . ' - ' .
                ($terminals[$toTerminal] ?? '');

            $stmt = $db->prepare("
                UPDATE buses
                SET
                    schedule = ?,
                    from_terminal_id = ?,
                    to_terminal_id = ?,
                    route_name = ?
                WHERE bus_id = ?
            ");

            $stmt->execute([
                $schedule,
                $fromTerminal,
                $toTerminal,
                $routeName,
                $busId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully.'
            ]);

            break;



        // =====================================================
        // ASSIGN INSPECTOR
        // =====================================================

        case 'assign_inspector':

            $tripNo = trim($_POST['trip_no'] ?? '');
            $busNo = trim($_POST['bus_no'] ?? '');
            $inspectorId = trim($_POST['inspector_id'] ?? '');

            if (
                $tripNo === '' ||
                $busNo === '' ||
                $inspectorId === ''
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields.'
                ]);

                break;
            }

            // Prevent duplicate assignment
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM passenger_trips
                WHERE
                    trip_no = ?
                AND
                    inspector_id = ?
            ");

            $stmt->execute([
                $tripNo,
                $inspectorId
            ]);

            if ($stmt->fetchColumn() > 0) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Inspector already assigned.'
                ]);

                break;
            }

            // Assign inspector to every passenger
            // that doesn't yet have one
            $stmt = $db->prepare("
                UPDATE passenger_trips
                SET inspector_id = ?
                WHERE
                    trip_no = ?
                AND
                    bus_no = ?
                AND
                    (
                        inspector_id IS NULL
                        OR inspector_id = ''
                    )
            ");

            $stmt->execute([
                $inspectorId,
                $tripNo,
                $busNo
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Inspector assigned successfully.'
            ]);

            break;



        // =====================================================
        // REMOVE INSPECTOR
        // =====================================================

        case 'remove_inspector':

            $tripNo = trim($_POST['trip_no'] ?? '');
            $inspectorId = trim($_POST['inspector_id'] ?? '');

            if (
                $tripNo === '' ||
                $inspectorId === ''
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields.'
                ]);

                break;
            }

            $stmt = $db->prepare("
                UPDATE passenger_trips
                SET inspector_id = NULL
                WHERE
                    trip_no = ?
                AND
                    inspector_id = ?
            ");

            $stmt->execute([
                $tripNo,
                $inspectorId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Inspector removed successfully.'
            ]);

            break;
                    // =====================================================
        // GET ACTIVE BUSES
        // =====================================================

        case 'get_buses':

            $stmt = $db->query("
                SELECT
                    bus_id,
                    plate_number,
                    bus_type,
                    route_name,
                    schedule
                FROM buses
                WHERE status = 'Active'
                ORDER BY bus_id ASC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;



        // =====================================================
        // GET INSPECTORS
        // =====================================================

        case 'get_inspectors':

            $stmt = $db->query("
                SELECT
                    inspector_identity,
                    inspector_name,
                    department,
                    contact_number
                FROM inspectors
                ORDER BY inspector_name ASC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;



        // =====================================================
        // GET TERMINALS
        // =====================================================

        case 'get_terminals':

            $stmt = $db->query("
                SELECT
                    id,
                    terminal_name
                FROM terminals
                WHERE status='Active'
                ORDER BY terminal_name
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;



        // =====================================================
        // GET TRIPS
        // =====================================================

        case 'get_trips':

            $stmt = $db->query("
                SELECT
                    trip_no,
                    bus_no,

                    MIN(boarding_time) AS departure_time,

                    COUNT(*) AS passenger_count

                FROM passenger_trips

                GROUP BY
                    trip_no,
                    bus_no

                ORDER BY
                    departure_time DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;



        // =====================================================
        // INSPECTOR MONITORING (read-only)
        // =====================================================

        case 'inspector_monitoring':

            $filters = ii_monitoring_filters();
            $result = ii_resolve_rows($db, $filters);
            if (empty($result['available'])) {
                echo json_encode(['success' => true] + ii_unavailable_payload((string)($result['message'] ?? 'Inspector inspection records are unavailable.')));
                break;
            }

            $groups = ii_group_rows($result['rows'], $filters);
            echo json_encode([
                'success'      => true,
                'available'    => true,
                'generated_at' => date('Y-m-d H:i:s'),
                'rows'         => $groups,
                'totals'       => ii_monitoring_totals($groups),
            ]);

            break;

        case 'inspector_monitoring_detail':

            $filters = ii_monitoring_filters();
            $result = ii_resolve_rows($db, $filters);
            if (empty($result['available'])) {
                echo json_encode(['success' => true] + ii_unavailable_payload((string)($result['message'] ?? 'Inspector inspection records are unavailable.')));
                break;
            }

            $groupKey = trim((string)($_GET['group_key'] ?? ''));
            $inspectionId = trim((string)($_GET['inspection_id'] ?? ''));
            $detailRows = [];
            foreach ($result['rows'] as $row) {
                if ($inspectionId !== '' && (string)$row['inspection_id'] === $inspectionId) {
                    $detailRows[] = $row;
                    continue;
                }
                if ($inspectionId === '' && $groupKey !== '') {
                    $tripKey = $row['trip_id'] !== '' ? $row['trip_id'] : ('day:' . substr((string)$row['verified_at'], 0, 10));
                    if (($row['inspector_id'] . '|' . $tripKey . '|' . $row['bus_id']) === $groupKey) {
                        $detailRows[] = $row;
                    }
                }
            }

            /* Deep link by inspection id: return the whole group containing it. */
            if ($inspectionId !== '' && count($detailRows) === 1) {
                $match = $detailRows[0];
                $tripKey = $match['trip_id'] !== '' ? $match['trip_id'] : ('day:' . substr((string)$match['verified_at'], 0, 10));
                $resolvedKey = $match['inspector_id'] . '|' . $tripKey . '|' . $match['bus_id'];
                $detailRows = array_values(array_filter($result['rows'], static function ($row) use ($resolvedKey) {
                    $tripKey = $row['trip_id'] !== '' ? $row['trip_id'] : ('day:' . substr((string)$row['verified_at'], 0, 10));
                    return ($row['inspector_id'] . '|' . $tripKey . '|' . $row['bus_id']) === $resolvedKey;
                }));
            }

            echo json_encode([
                'success'      => true,
                'available'    => true,
                'generated_at' => date('Y-m-d H:i:s'),
                'group_key'    => $groupKey,
                'rows'         => $detailRows,
            ]);

            break;

        case 'inspector_monitoring_filters':

            $result = ii_resolve_rows($db, []);
            if (empty($result['available'])) {
                echo json_encode(['success' => true, 'available' => false, 'inspectors' => [], 'buses' => [], 'drivers' => [], 'trips' => []]);
                break;
            }

            $inspectors = [];
            $buses = [];
            $drivers = [];
            $trips = [];
            foreach ($result['rows'] as $row) {
                if ($row['inspector_id'] !== '' || $row['inspector_name'] !== '') {
                    $inspectors[$row['inspector_id'] !== '' ? $row['inspector_id'] : $row['inspector_name']] = $row['inspector_name'];
                }
                if ($row['bus_id'] !== '') { $buses[$row['bus_id']] = true; }
                if ($row['driver_id'] !== '') { $drivers[$row['driver_id']] = true; }
                if ($row['trip_id'] !== '') { $trips[$row['trip_id']] = true; }
            }
            $inspectorList = [];
            foreach ($inspectors as $id => $name) {
                $inspectorList[] = ['id' => (string)$id, 'name' => (string)$name];
            }
            usort($inspectorList, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
            $busList = array_values(array_keys($buses));
            sort($busList);
            $driverList = array_values(array_keys($drivers));
            sort($driverList);
            $tripList = array_values(array_keys($trips));
            rsort($tripList);

            echo json_encode([
                'success'    => true,
                'available'  => true,
                'inspectors' => $inspectorList,
                'buses'      => $busList,
                'drivers'    => $driverList,
                'trips'      => $tripList,
            ]);

            break;



        // =====================================================
        // DEFAULT
        // =====================================================

        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);

            break;

    }

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
