<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/RemittanceModel.php';
require_once __DIR__ . '/../validators/RemittanceValidator.php';
require_once __DIR__ . '/DiscrepancyCaseService.php';

use Finance\Models\RemittanceModel;
use Finance\Validators\RemittanceValidator;
use Finance\Services\DiscrepancyCaseService;
use App\Core\Database;

class RemittanceService
{
    public const CUTOFF_HOUR = 23;
    public const CUTOFF_MINUTE = 59;
    public const OPEN_HOUR = 5;
    public const LOOKBACK_DAYS = 60;

    /**
     * =========================
     * MANILA BUSINESS DATE
     * =========================
     */
    public static function manilaNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
    }

    public static function manilaDate(): string
    {
        return self::manilaNow()->format('Y-m-d');
    }

    /**
     * Whether the 11:59 PM Manila cutoff for a business date has passed.
     */
    public static function cutoffPassed($businessDate = null): bool
    {
        $now = self::manilaNow();
        $today = $now->format('Y-m-d');
        $date = $businessDate ?: $today;

        if ($date < $today) {
            return true;
        }
        if ($date > $today) {
            return false;
        }

        $minutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');
        return $minutes >= self::CUTOFF_HOUR * 60 + self::CUTOFF_MINUTE;
    }

    /**
     * Whether normal processing for the current business date is open
     * (5:00 AM through 11:59 PM Manila).
     */
    public static function isBusinessDateOpen(): bool
    {
        $now = self::manilaNow();
        $minutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');
        return $minutes >= self::OPEN_HOUR * 60;
    }

    public static function normalizeBusinessDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    /**
     * =========================
     * ALL TRIPS
     * =========================
     */
    public static function getTrips($phDate = null)
    {
        try {
            self::flagOverdueRemittances();
        } catch (\Throwable $e) {
            error_log('Remittance overdue flagging failed: ' . $e->getMessage());
        }

        $rows =
            RemittanceModel::getTodayTrips($phDate);

        $historyRows =
            RemittanceModel::getTodayHistoryTrips($phDate);

        $seen = [];

        foreach ($rows as $row) {
            $seen[($row['driver_id'] ?? '') . '|' . ($row['trip_no'] ?? '')] = true;
        }

        foreach ($historyRows as $row) {

            $key = ($row['driver_id'] ?? '') . '|' . ($row['trip_no'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $row['source'] = 'history';
            $row['submitted'] = (int)(bool)($row['submitted'] ?? 0);

            $rows[] = $row;
        }

        $data = [];

        foreach ($rows as $row) {

            $data[] = [

                'trip_no' =>
                    $row['trip_no'],

                'bus_no' =>
                    $row['bus_no'],

                'driver_id' =>
                    $row['driver_id']
                    ?? null,

                'driver_name' =>
                    $row['full_name']
                    ?? 'N/A',

                'plate_number' =>
                    $row['plate_number']
                    ?? 'N/A',

                'total_collection' =>
                    (float)(
                        $row['total_collection']
                        ?? 0
                    ),

                'cash_total' =>
                    (float)(
                        $row['cash_total']
                        ?? 0
                    ),

                'gcash_total' =>
                    (float)(
                        $row['gcash_total']
                        ?? 0
                    ),

                'expected_cash' =>
                    (float)(
                        $row['cash_total']
                        ?? 0
                    ),

                'submitted' =>
                    $row['submitted']
                    ?? 0,

                'submitted_by' =>
                    $row['submitted_by']
                    ?? null,

                'source' =>
                    $row['source']
                    ?? null
            ];
        }

        return $data;
    }

    /**
     * =========================
     * DRIVER SIDEBAR
     * =========================
     */
    public static function getDrivers($phDate = null)
    {
        try {
            self::flagOverdueRemittances();
        } catch (\Throwable $e) {
            error_log('Remittance overdue flagging failed: ' . $e->getMessage());
        }

        $rows =
            RemittanceModel
                ::getDriverSummaryToday($phDate);

        $historyRows =
            RemittanceModel
                ::getDriverHistorySummary($phDate);

        $byDriver = [];

        foreach ($rows as $row) {
            $byDriver[$row['driver_id']] = $row;
        }

        foreach ($historyRows as $row) {

            $id = $row['driver_id'];

            if (isset($byDriver[$id])) {

                $byDriver[$id]['total_collection'] =
                    (float)($byDriver[$id]['total_collection'] ?? 0)
                    + (float)($row['total_collection'] ?? 0);

                $byDriver[$id]['expected_cash'] =
                    (float)($byDriver[$id]['expected_cash'] ?? 0)
                    + (float)($row['expected_cash'] ?? 0);

            } else {

                $row['submitted'] = (int)(bool)($row['submitted'] ?? 0);

                $byDriver[$id] = $row;
            }
        }

        $rows = array_values($byDriver);

        $drivers = [];

        foreach ($rows as $row) {

            $drivers[] = [

                'driver_id' =>
                    $row['driver_id'],

                'driver_name' =>
                    $row['full_name'],

                'total_collection' =>
                    (float)(
                        $row['total_collection']
                        ?? 0
                    ),

                'expected_cash' =>
                    (float)(
                        $row['expected_cash']
                        ?? 0
                    ),

                'submitted' =>

                    $row['submitted']

                    ? true

                    : false,

                'submitted_by' =>

                    $row['submitted_by']
                    ?? null
            ];
        }

        return $drivers;
    }

    /**
     * =========================
     * GROUP BY DRIVER
     * =========================
     */
    public static function groupByDriver(
        $trips
    ) {

        $grouped = [];

        foreach ($trips as $trip) {

            $grouped[
                $trip['driver_name']
            ][] = $trip;
        }

        return $grouped;
    }

    /**
     * =========================
     * COMPUTE TOTALS
     * =========================
     */
    public static function computeTotals(
        $trips
    ) {

        $totalCollection = 0;

        $totalCash = 0;

        $totalGcash = 0;

        foreach ($trips as $t) {

            $totalCollection +=
                $t['total_collection'];

            $totalCash +=
                $t['cash_total'];

            $totalGcash +=
                $t['gcash_total'];
        }

        return [

            'total_collection' =>
                $totalCollection,

            'cash_total' =>
                $totalCash,

            'gcash_total' =>
                $totalGcash,

            'expected_cash' =>
                $totalCash
        ];
    }

    /**
     * =========================
     * MATCH / DISCREPANCY
     * =========================
     */
    public static function evaluate(
        $expected,
        $actual
    ) {

        $expected =
            round(
                (float)$expected,
                2
            );

        $actual =
            round(
                (float)$actual,
                2
            );

        return (

            $expected == $actual

        )

            ? 'MATCH'

            : 'DISCREPANCY';
    }

    /**
     * =========================
     * RESOLVE DRIVER FULL NAME
     * =========================
     */
    public static function getDriverName(
        $driverId
    ) {
        $db = Database::get();

        $stmt = $db->prepare("

            SELECT full_name

            FROM drivers

            WHERE driver_id = ?

            LIMIT 1

        ");

        $stmt->execute([$driverId]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['full_name'] ?? null;
    }

    /**
     * =========================
     * TODAY'S REMITTANCE REF
     * =========================
     */
    public static function getTodayRemittanceRef($driverId, $phDate)
    {
        $log = self::getRemittanceLog($driverId, $phDate);
        return $log ? 'RM-' . str_pad((string) $log['id'], 5, '0', STR_PAD_LEFT) : null;
    }

    /**
     * The remittance log for a driver and business date (Overdue included).
     */
    public static function getRemittanceLog(string $driverId, string $businessDate): ?array
    {
        $db = Database::get();
        RemittanceModel::ensureBusinessDateColumn($db);
        return RemittanceModel::findLogForDate($db, $driverId, $businessDate);
    }

    /**
     * =========================
     * ALREADY SUBMITTED
     * =========================
     */
    public static function alreadySubmitted(
        $driver,
        $phDate = null
    ) {

        return RemittanceModel
            ::alreadySubmitted(
                $driver,
                $phDate
            );
    }

    /**
     * =========================
     * GET DRIVER TRIPS
     * =========================
     */
public static function getDriverTrips(
    $driver_id,
    $phDate = null
) {
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();

    $stmt = $db->prepare("

        SELECT
            bt.id AS trip_no,
            bt.bus_id AS bus_no,
            bt.driver_id,

            COUNT(pt.id) AS total_passengers,

            COALESCE(SUM(pt.fare), 0) AS total_collection,

            COALESCE(
                SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'cash' THEN pt.fare ELSE 0 END),
                0
            ) AS cash_total,

            COALESCE(
                SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'gcash' THEN pt.fare ELSE 0 END),
                0
            ) AS gcash_total

        FROM bus_trips bt

        LEFT JOIN passenger_trips pt
            ON bt.id = pt.trip_no
            AND LOWER(TRIM(pt.payment_status)) = 'paid'

        WHERE bt.driver_id = ?

        AND bt.trip_date = ?

        GROUP BY bt.id, bt.bus_id, bt.driver_id

        ORDER BY bt.id DESC

    ");

    $stmt->execute([$driver_id, $phDate]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
    /**
     * =========================
     * DOWNLOAD REPORT (PDF)
     * =========================
     * Builds the printable per-driver remittance report (no account/record
     * IDs) and returns the HTML plus a PDF filename for the controller.
     */
    public static function downloadReport($driverId, $phDate = null)
    {
        if (!$phDate) {
            $phDate = self::manilaDate();
        }

        $trips = RemittanceModel::getDriverTrips($driverId, $phDate);
        $historyTrips = RemittanceModel::getDriverHistoryTrips($driverId, $phDate);
        $trips = array_merge($trips, $historyTrips);

        if (empty($trips)) {
            return null;
        }

        $driverName = self::getDriverName($driverId) ?? $driverId;

        $totalCollection = 0.0;
        $totalCash = 0.0;
        $totalGcash = 0.0;
        $rows = '';

        foreach ($trips as $trip) {
            $collection = (float) ($trip['total_collection'] ?? 0);
            $cash = (float) ($trip['cash_total'] ?? 0);
            $gcash = (float) ($trip['gcash_total'] ?? 0);

            $totalCollection += $collection;
            $totalCash += $cash;
            $totalGcash += $gcash;

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars((string) ($trip['trip_no'] ?? ''), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) ($trip['bus_no'] ?? ''), ENT_QUOTES) . '</td>'
                . '<td class="num">&#8369;' . number_format($collection, 2) . '</td>'
                . '<td class="num">&#8369;' . number_format($cash, 2) . '</td>'
                . '<td class="num">&#8369;' . number_format($gcash, 2) . '</td>'
                . '<td class="num">&#8369;' . number_format($cash, 2) . '</td>'
                . '</tr>';
        }

        $log = self::getRemittanceLog($driverId, $phDate);
        $statusLabel = $log ? ucwords(strtolower(trim((string) ($log['status'] ?? 'Pending')))) : 'Pending';

        $actualText = '—';
        $differenceText = '—';

        if ($log && $log['actual_cash'] !== null) {
            $actual = (float) $log['actual_cash'];
            $difference = round($actual - $totalCash, 2);
            $actualText = '&#8369;' . number_format($actual, 2);
            $differenceText = ($difference < 0 ? '-&#8369;' : '&#8369;') . number_format(abs($difference), 2);
        }

        $businessLabel = date('F j, Y', strtotime($phDate));
        $generatedAt = self::manilaNow()->format('F j, Y g:i A');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;color:#33402f;font-size:10.5px;}'
            . 'h1{color:#295738;font-size:18px;margin:0 0 2px;}'
            . '.sub{color:#7a8a7a;font-size:10px;margin:0 0 14px;}'
            . '.meta{width:100%;margin-bottom:12px;}'
            . '.meta td{padding:2px 0;font-size:10.5px;}'
            . '.meta .right{text-align:right;}'
            . 'table.report{width:100%;border-collapse:collapse;margin-bottom:12px;}'
            . 'table.report th{background:#eef4e7;color:#295738;text-align:left;padding:7px 8px;border:1px solid #d7e3cd;font-size:9.5px;}'
            . 'table.report td{padding:6px 8px;border:1px solid #e4e7d6;font-size:10px;}'
            . 'table.report .num{text-align:right;}'
            . 'table.report tfoot td{background:#fbf9f0;font-weight:bold;}'
            . '.summary{background:#fbf9f0;border:1px solid #e8e4c9;border-radius:8px;padding:10px 12px;}'
            . '.summary table{width:100%;}'
            . '.summary td{padding:3px 0;font-size:10.5px;}'
            . '.summary .right{text-align:right;font-weight:bold;color:#295738;}'
            . '.footer{margin-top:16px;color:#93a093;font-size:8.5px;text-align:center;}'
            . '</style></head><body>'
            . '<h1>Driver Remittance Report</h1>'
            . '<div class="sub">R-Connect Transportation Management System</div>'
            . '<table class="meta"><tr>'
            . '<td><strong>Driver:</strong> ' . htmlspecialchars((string) $driverName, ENT_QUOTES) . '</td>'
            . '<td class="right"><strong>Business Date:</strong> ' . htmlspecialchars($businessLabel, ENT_QUOTES) . '</td>'
            . '</tr><tr>'
            . '<td><strong>Status:</strong> ' . htmlspecialchars($statusLabel, ENT_QUOTES)
            . ' &middot; ' . count($trips) . ' trip' . (count($trips) === 1 ? '' : 's') . '</td>'
            . '<td class="right"><strong>Generated:</strong> ' . htmlspecialchars($generatedAt, ENT_QUOTES) . '</td>'
            . '</tr></table>'
            . '<table class="report">'
            . '<thead><tr>'
            . '<th>Trip #</th><th>Bus</th>'
            . '<th class="num">Collection</th><th class="num">Cash Collected</th>'
            . '<th class="num">QR Ph Collected</th><th class="num">Expected Cash</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot><tr><td colspan="2">TOTAL</td>'
            . '<td class="num">&#8369;' . number_format($totalCollection, 2) . '</td>'
            . '<td class="num">&#8369;' . number_format($totalCash, 2) . '</td>'
            . '<td class="num">&#8369;' . number_format($totalGcash, 2) . '</td>'
            . '<td class="num">&#8369;' . number_format($totalCash, 2) . '</td>'
            . '</tr></tfoot>'
            . '</table>'
            . '<div class="summary"><table>'
            . '<tr><td>Expected Cash to Submit</td><td class="right">&#8369;' . number_format($totalCash, 2) . '</td></tr>'
            . '<tr><td>Actual Cash Remitted</td><td class="right">' . $actualText . '</td></tr>'
            . '<tr><td>Amount Difference</td><td class="right">' . $differenceText . '</td></tr>'
            . '</table></div>'
            . '<div class="footer">Generated by R-Connect &middot; Keep this report for auditing. Never share authorization codes.</div>'
            . '</body></html>';

        $safeName = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $driverName), '_');
        if ($safeName === '') {
            $safeName = 'Driver';
        }

        return [
            'html' => $html,
            'filename' => 'Remittance_' . $safeName . '_' . $phDate . '.pdf',
            'driver_name' => $driverName,
        ];
    }

    /**
     * =========================
     * FINALIZE REMITTANCE
     * =========================
     */
    public static function finalize(
        array $data
    ) {

        return RemittanceModel
            ::finalize($data);
    }
    
    public static function alreadySubmittedToday($driver, $phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();

    $stmt = $db->prepare("

        SELECT COUNT(*) AS total_trips,
               SUM(bt.submitted) AS submitted_trips

        FROM bus_trips bt

        WHERE bt.driver_id = ?

        AND bt.trip_date = ?

    ");

    $stmt->execute([$driver, $phDate]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row || $row['total_trips'] == 0) {
        return false;
    }

    return (int)$row['total_trips'] === (int)$row['submitted_trips'];
}

    /**
     * =========================
     * OVERDUE FLAGGING
     * =========================
     */

    /**
     * Business dates that still have unremitted activity inside the lookback
     * window (live trips not claimed, or history rows still Pending).
     *
     * @return string[]
     */
    public static function outstandingBusinessDates(\PDO $db): array
    {
        $today = self::manilaDate();
        $from = self::manilaNow()->modify('-' . self::LOOKBACK_DAYS . ' days')->format('Y-m-d');

        $dates = [];

        try {
            $stmt = $db->prepare("
                SELECT DISTINCT bt.trip_date AS d
                FROM bus_trips bt
                WHERE COALESCE(bt.submitted, 0) = 0
                  AND bt.trip_date BETWEEN ? AND ?
            ");
            $stmt->execute([$from, $today]);
            while ($d = $stmt->fetchColumn()) {
                $d = (string) $d;
                if ($d !== '') {
                    $dates[$d] = true;
                }
            }
        } catch (\Throwable $e) {
            error_log('Remittance outstanding dates (live) failed: ' . $e->getMessage());
        }

        try {
            $stmt = $db->prepare("
                SELECT DISTINCT pth.trip_date AS d
                FROM passenger_trip_history pth
                WHERE pth.trip_date BETWEEN ? AND ?
                  AND pth.driver_id IS NOT NULL
                  AND TRIM(pth.driver_id) <> ''
                  AND (
                        pth.remittance_status IS NULL
                        OR LOWER(TRIM(pth.remittance_status)) = 'pending'
                      )
            ");
            $stmt->execute([$from, $today]);
            while ($d = $stmt->fetchColumn()) {
                $d = (string) $d;
                if ($d !== '') {
                    $dates[$d] = true;
                }
            }
        } catch (\Throwable $e) {
            // passenger_trip_history may not carry remittance columns yet.
        }

        $list = array_keys($dates);
        sort($list);

        return $list;
    }

    /**
     * Idempotently flag every eligible driver for a closed business date as
     * Overdue. Returns the number of Overdue records created.
     */
    public static function flagOverdueRemittances($businessDate = null): int
    {
        $db = Database::get();
        RemittanceModel::ensureBusinessDateColumn($db);

        $dates = [];

        if ($businessDate !== null) {
            $normalized = self::normalizeBusinessDate($businessDate);
            if ($normalized !== null) {
                $dates[] = $normalized;
            }
        } else {
            $dates = self::outstandingBusinessDates($db);
        }

        $created = 0;

        foreach ($dates as $date) {
            if (!self::cutoffPassed($date)) {
                continue;
            }
            $created += self::flagOverdueForDate($db, $date);
        }

        return $created;
    }

    private static function flagOverdueForDate(\PDO $db, string $businessDate): int
    {
        $created = 0;

        foreach (self::eligibleOverdueDrivers($db, $businessDate) as $driverId) {
            $day = self::computeDriverDay($driverId, $businessDate);

            if ($day['trip_count'] < 1) {
                continue;
            }

            try {
                $stmt = $db->prepare("
                    INSERT INTO remittance_logs (
                        driver_id,
                        business_date,
                        trip_no,
                        total_passengers,
                        total_collection,
                        expected_cash,
                        actual_cash,
                        discrepancy_amount,
                        status,
                        submitted_by,
                        created_at
                    )
                    SELECT ?, ?, ?, ?, ?, ?, NULL, 0, 'Overdue', NULL, NOW()
                    FROM DUAL
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM remittance_logs
                        WHERE driver_id = ?
                          AND (
                                business_date = ?
                                OR (business_date IS NULL AND DATE(created_at) = ?)
                              )
                    )
                ");
                $stmt->execute([
                    $driverId,
                    $businessDate,
                    (string) ($day['trips'][0]['trip_no'] ?? ''),
                    (int) $day['total_passengers'],
                    (float) $day['total_collection'],
                    (float) $day['expected_cash'],
                    $driverId,
                    $businessDate,
                    $businessDate,
                ]);

                if ($stmt->rowCount() > 0) {
                    $created++;

                    /**
                     * Overdue Remittance case (discrepancy investigation
                     * workflow). Failures here are logged rather than thrown:
                     * flagging runs lazily from page loads and the idempotent
                     * backfill tool can create any missing case later.
                     */
                    try {
                        $logId = (int) $db->lastInsertId();
                        if ($logId > 0) {
                            (new DiscrepancyCaseService($db))->syncFromRemittance($logId, [
                                'driver_id' => $driverId,
                                'business_date' => $businessDate,
                                'expected_amount' => (float) $day['expected_cash'],
                                'actual_amount' => null,
                                'opened_by' => 'System',
                            ]);
                        }
                    } catch (\Throwable $caseError) {
                        error_log('Overdue discrepancy case creation failed for ' . $driverId . ' ' . $businessDate . ': ' . $caseError->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                error_log('Remittance overdue insert failed for ' . $driverId . ' ' . $businessDate . ': ' . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Drivers with at least one paid trip on the date, no active/unfinished
     * trip on that date, and no finalized remittance for it.
     *
     * @return string[]
     */
    private static function eligibleOverdueDrivers(\PDO $db, string $businessDate): array
    {
        $drivers = [];

        try {
            $stmt = $db->prepare("
                SELECT DISTINCT bt.driver_id
                FROM bus_trips bt
                INNER JOIN passenger_trips pt
                    ON pt.trip_no = bt.id
                    AND LOWER(TRIM(pt.payment_status)) = 'paid'
                WHERE bt.trip_date = ?
                  AND bt.driver_id IS NOT NULL
                  AND TRIM(bt.driver_id) <> ''
            ");
            $stmt->execute([$businessDate]);
            while ($id = $stmt->fetchColumn()) {
                $drivers[trim((string) $id)] = true;
            }
        } catch (\Throwable $e) {
            error_log('Remittance overdue drivers (live) failed: ' . $e->getMessage());
        }

        try {
            $stmt = $db->prepare("
                SELECT DISTINCT pth.driver_id
                FROM passenger_trip_history pth
                WHERE pth.trip_date = ?
                  AND pth.driver_id IS NOT NULL
                  AND TRIM(pth.driver_id) <> ''
                  AND LOWER(TRIM(COALESCE(pth.payment_status, ''))) <> 'rejected'
            ");
            $stmt->execute([$businessDate]);
            while ($id = $stmt->fetchColumn()) {
                $drivers[trim((string) $id)] = true;
            }
        } catch (\Throwable $e) {
            // History columns may be missing on older databases.
        }

        try {
            $stmt = $db->prepare("
                SELECT DISTINCT bt.driver_id
                FROM bus_trips bt
                WHERE bt.trip_date = ?
                  AND bt.driver_id IS NOT NULL
                  AND LOWER(TRIM(COALESCE(bt.status, ''))) IN (
                        'scheduled', 'departed', 'started', 'active', 'ongoing', 'interrupted'
                      )
            ");
            $stmt->execute([$businessDate]);
            while ($id = $stmt->fetchColumn()) {
                unset($drivers[trim((string) $id)]);
            }
        } catch (\Throwable $e) {
            error_log('Remittance overdue active-trip check failed: ' . $e->getMessage());
        }

        foreach (array_keys($drivers) as $driverId) {
            $log = RemittanceModel::findLogForDate($db, (string) $driverId, $businessDate);
            if ($log !== null && strtolower(trim((string) ($log['status'] ?? ''))) !== 'overdue') {
                unset($drivers[$driverId]);
            }
        }

        return array_map('strval', array_keys($drivers));
    }

    /**
     * =========================
     * DRIVER DAY COMPUTATION
     * =========================
     */
    public static function computeDriverDay(string $driverId, string $businessDate): array
    {
        $trips = RemittanceModel::getDriverTrips($driverId, $businessDate);
        $historyTrips = RemittanceModel::getDriverHistoryTrips($driverId, $businessDate);

        $seen = [];
        foreach ($trips as $trip) {
            $seen[(string) ($trip['trip_no'] ?? '')] = true;
        }
        foreach ($historyTrips as $trip) {
            $key = (string) ($trip['trip_no'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $trip['source'] = 'history';
            $trips[] = $trip;
        }

        $totalPassengers = 0;
        $totalCollection = 0.0;
        $cashTotal = 0.0;
        $gcashTotal = 0.0;

        foreach ($trips as $trip) {
            $totalPassengers += (int) ($trip['total_passengers'] ?? 0);
            $totalCollection += (float) ($trip['total_collection'] ?? 0);
            $cashTotal += (float) ($trip['cash_total'] ?? 0);
            $gcashTotal += (float) ($trip['gcash_total'] ?? 0);
        }

        return [
            'driver_id' => $driverId,
            'business_date' => $businessDate,
            'trips' => $trips,
            'trip_count' => count($trips),
            'total_passengers' => $totalPassengers,
            'total_collection' => round($totalCollection, 2),
            'cash_total' => round($cashTotal, 2),
            'gcash_total' => round($gcashTotal, 2),
            'expected_cash' => round($cashTotal, 2),
        ];
    }

    /**
     * Whether the driver still has trips that are active or unfinished on the
     * business date. Such drivers never qualify for remittance/overdue.
     */
    public static function hasUnfinishedTrips(string $driverId, string $businessDate): bool
    {
        $db = Database::get();

        try {
            $stmt = $db->prepare("
                SELECT 1
                FROM bus_trips
                WHERE driver_id = ?
                  AND trip_date = ?
                  AND LOWER(TRIM(COALESCE(status, ''))) IN (
                        'scheduled', 'departed', 'started', 'active', 'ongoing', 'interrupted'
                      )
                LIMIT 1
            ");
            $stmt->execute([$driverId, $businessDate]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('Remittance unfinished-trip check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Overdue remittance records for the Remittance page filter.
     */
    public static function getOverdueDrivers(): array
    {
        $db = Database::get();
        RemittanceModel::ensureBusinessDateColumn($db);

        try {
            $stmt = $db->query("
                SELECT
                    rl.id,
                    rl.driver_id,
                    COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,
                    rl.expected_cash,
                    rl.total_collection,
                    rl.total_passengers,
                    COALESCE(d.full_name, rl.driver_id) AS full_name
                FROM remittance_logs rl
                LEFT JOIN drivers d
                    ON d.driver_id = rl.driver_id
                WHERE LOWER(TRIM(COALESCE(rl.status, ''))) = 'overdue'
                ORDER BY business_date ASC, full_name ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Remittance overdue list failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * =========================
     * CLASSIFY ACTUAL AGAINST EXPECTED
     * =========================
     * Balanced -> Completed. Above expected -> Discrepancy with an excess
     * amount (an Excess Remittance case is created). Below expected ->
     * Discrepancy with a shortage. Shortage and excess are mutually exclusive.
     */
    public static function classifyAmounts($expected, $actual): array
    {
        $expected = round((float) $expected, 2);
        $actual = round((float) $actual, 2);

        if (number_format($expected, 2, '.', '') === number_format($actual, 2, '.', '')) {
            return [
                'status' => 'Completed',
                'discrepancy_amount' => 0.0,
                'excess_amount' => 0.0,
            ];
        }

        if ($actual > $expected) {
            return [
                'status' => 'Discrepancy',
                'discrepancy_amount' => 0.0,
                'excess_amount' => round($actual - $expected, 2),
            ];
        }

        return [
            'status' => 'Discrepancy',
            'discrepancy_amount' => round($expected - $actual, 2),
            'excess_amount' => 0.0,
        ];
    }

    /**
     * =========================
     * SUBMIT REMITTANCE (NORMAL OR LATE)
     * =========================
     * Server-authoritative: recomputes the expected cash, revalidates the day,
     * atomically claims the trips, and upserts exactly one remittance_logs row
     * per driver + business date.
     */
    public static function submitRemittance(array $input): array
    {
        $db = Database::get();
        RemittanceModel::ensureBusinessDateColumn($db);

        $driverId = trim((string) ($input['driver_id'] ?? ''));
        $businessDate = self::normalizeBusinessDate($input['business_date'] ?? null);
        $actualCash = round((float) ($input['actual_cash'] ?? 0), 2);
        $code = (string) ($input['code'] ?? '');
        $reviewer = trim((string) ($input['reviewer'] ?? 'Finance Manager'));
        $actorUserId = (int) ($input['actor_user_id'] ?? 0);

        if ($driverId === '') {
            throw new \Exception('Invalid driver');
        }
        if ($businessDate === null) {
            throw new \Exception('Invalid business date');
        }
        if ($businessDate > self::manilaDate()) {
            throw new \Exception('Business date cannot be in the future');
        }

        RemittanceValidator::validateCash($actualCash);

        $day = self::computeDriverDay($driverId, $businessDate);

        if ($day['trip_count'] < 1) {
            throw new \Exception('No eligible trips found for this driver on ' . $businessDate . '.');
        }

        if (self::hasUnfinishedTrips($driverId, $businessDate)) {
            throw new \Exception('This driver still has active or unfinished trips. Process the remittance after all trips are completed.');
        }

        $isLate = $businessDate < self::manilaDate();

        $db->beginTransaction();

        try {
            $logBefore = RemittanceModel::findLogForDate($db, $driverId, $businessDate);

            $liveTrips = 0;
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM bus_trips WHERE driver_id = ? AND trip_date = ?");
                $stmt->execute([$driverId, $businessDate]);
                $liveTrips = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                $liveTrips = 0;
            }

            $claimed = 0;
            if ($liveTrips > 0) {
                $claim = $db->prepare("
                    UPDATE bus_trips
                    SET submitted = 1
                    WHERE driver_id = ?
                      AND trip_date = ?
                      AND COALESCE(submitted, 0) = 0
                ");
                $claim->execute([$driverId, $businessDate]);
                $claimed = $claim->rowCount();
            }

            $existingFinalized = $logBefore !== null
                && strtolower(trim((string) ($logBefore['status'] ?? ''))) !== 'overdue';

            if ($existingFinalized) {
                throw new \Exception('Remittance already submitted');
            }

            if ($liveTrips > 0 && $claimed === 0 && $logBefore === null) {
                // Another submission claimed the trips between our read and claim.
                throw new \Exception('Remittance already submitted');
            }

            $expected = (float) $day['expected_cash'];
            $classification = self::classifyAmounts($expected, $actualCash);
            $status = $classification['status'];
            $discrepancy = $classification['discrepancy_amount'];
            $excess = $classification['excess_amount'];

            $remitId = RemittanceModel::finalize([
                'driver_id' => $driverId,
                'business_date' => $businessDate,
                'trip_no' => (string) ($day['trips'][0]['trip_no'] ?? ''),
                'bus_no' => (string) ($day['trips'][0]['bus_no'] ?? ''),
                'total_passengers' => (int) $day['total_passengers'],
                'total_collection' => (float) $day['total_collection'],
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'discrepancy_amount' => $discrepancy,
                'status' => $status,
                'finance_code' => $code,
                'confirmed_by' => $reviewer,
                'submitted_by' => $reviewer,
                'remitted_by' => self::getDriverName($driverId) ?? $driverId,
                'reviewed_by' => $reviewer,
                'reviewed_at' => self::manilaNow()->format('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
            ]);

            /**
             * =========================
             * DISCREPANCY CASE SYNC
             * =========================
             * Shortage and excess remittances create exactly one linked case
             * in the same transaction; a late remittance reclassifies the
             * existing overdue case. Case creation failures roll the whole
             * submission back so a discrepancy can never be lost silently.
             */
            $caseId = null;
            if ($status === 'Discrepancy') {
                $caseSync = (new DiscrepancyCaseService($db))->syncFromRemittance($remitId, [
                    'driver_id' => $driverId,
                    'business_date' => $businessDate,
                    'expected_amount' => $expected,
                    'actual_amount' => $actualCash,
                    'opened_by' => $reviewer,
                ]);
                $caseId = $caseSync['case_id'] ?? null;
            }

            $db->commit();

            return [
                'remittance_id' => $remitId,
                'case_id' => $caseId,
                'driver_id' => $driverId,
                'business_date' => $businessDate,
                'is_late' => $isLate,
                'trip_count' => (int) $day['trip_count'],
                'total_passengers' => (int) $day['total_passengers'],
                'total_collection' => (float) $day['total_collection'],
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => $excess,
                'status' => $status,
                'previous_status' => $logBefore['status'] ?? null,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
?>