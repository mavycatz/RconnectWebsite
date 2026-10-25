<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Models/Notification.php';

use App\Core\Database;

class RemittanceModel
{
    public function getConnection(): \PDO
    {
        return Database::get();
    }

    /**
     * =========================
     * HISTORY REMITTANCE COLUMNS
     * =========================
     * `passenger_trip_history` only carries payment/remittance settlement columns
     * after the trip-history-generator runs its idempotent ALTER. The remittance
     * panel reads them when present (so today+ generated rows show as Pending) and
     * falls back to the pre-column behavior (history = already submitted) otherwise.
     */
private static function historyHasRemittanceColumns(\PDO $db): bool
{
    $cols = $db->query("SHOW COLUMNS FROM passenger_trip_history LIKE 'remittance_status'");
    return $cols !== false && $cols->fetchColumn() !== false;
}

private static function historySubmittedSql(\PDO $db): string
{
    if (!self::historyHasRemittanceColumns($db)) {
        return "1 AS submitted, NULL AS submitted_by";
    }

    return "CASE
                WHEN COUNT(pth.history_id) = COALESCE(SUM(CASE WHEN LOWER(COALESCE(pth.remittance_status,'Pending')) IN ('remitted','discrepancy') THEN 1 ELSE 0 END), 0)
                     AND COUNT(pth.history_id) > 0 THEN 1
                ELSE 0
            END AS submitted,
            MAX(pth.remitted_by) AS submitted_by";
}

    /**
     * =========================
     * BUSINESS DATE COLUMN
     * =========================
     * `remittance_logs.business_date` records the service day a remittance
     * settles (normal, overdue, or late). Added idempotently so older
     * databases pick it up without a manual migration.
     */
    private static bool $businessDateEnsured = false;

    public static function ensureBusinessDateColumn(\PDO $db): void
    {
        if (self::$businessDateEnsured) {
            return;
        }
        self::$businessDateEnsured = true;

        try {
            $stmt = $db->query("SHOW COLUMNS FROM remittance_logs LIKE 'business_date'");
            if ($stmt !== false && $stmt->fetchColumn() === false) {
                $db->exec("ALTER TABLE remittance_logs ADD COLUMN business_date DATE NULL AFTER driver_id");
            }
        } catch (\Throwable $e) {
            error_log('RemittanceModel: could not ensure business_date: ' . $e->getMessage());
        }
    }

    /**
     * The remittance log for a driver and service day (business_date, with a
     * created_at fallback for legacy rows).
     */
    public static function findLogForDate(\PDO $db, string $driverId, string $businessDate): ?array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM remittance_logs
            WHERE driver_id = ?
              AND (
                    business_date = ?
                    OR (business_date IS NULL AND DATE(created_at) = ?)
                  )
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$driverId, $businessDate, $businessDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * =========================
     * GET TODAY TRIPS
     * =========================
     */
public static function getTodayTrips($phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();

    $stmt = $db->prepare("
        SELECT
            bt.id AS trip_no,
            bt.bus_id AS bus_no,

            d.driver_id,
            d.full_name,

            b.plate_number,

            COUNT(pt.id) AS total_passengers,

            COALESCE(SUM(pt.fare), 0) AS total_collection,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'cash' THEN pt.fare ELSE 0 END), 0) AS cash_total,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'gcash' THEN pt.fare ELSE 0 END), 0) AS gcash_total,

            bt.submitted,

            (
                SELECT rl.submitted_by
                FROM remittance_logs rl
                WHERE rl.driver_id = d.driver_id
                ORDER BY rl.created_at DESC
                LIMIT 1
            ) AS submitted_by

        FROM bus_trips bt

        LEFT JOIN passenger_trips pt
            ON pt.trip_no = bt.id
            AND LOWER(TRIM(pt.payment_status)) = 'paid'

        LEFT JOIN buses b
            ON bt.bus_id = b.bus_id

        LEFT JOIN drivers d
            ON bt.driver_id = d.driver_id

        WHERE bt.trip_date = ?

        GROUP BY
            bt.id,
            bt.bus_id,
            d.driver_id,
            d.full_name,
            b.plate_number,
            bt.submitted

        ORDER BY
            bt.id DESC
    ");

    $stmt->execute([$phDate]);

    return $stmt->fetchAll();
}

    /**
     * =========================
     * DRIVER SUMMARY
     * =========================
     */
public static function getDriverSummaryToday($phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();

    $stmt = $db->prepare("
        SELECT
            d.driver_id,
            d.full_name,

            COALESCE(SUM(pt.fare), 0) AS total_collection,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'cash' THEN pt.fare ELSE 0 END), 0) AS expected_cash,

            CASE
                WHEN COUNT(CASE WHEN bt.submitted = 0 THEN 1 END) = 0 AND COUNT(bt.id) > 0 THEN 'Completed'
                WHEN COUNT(pt.id) > 0 THEN 'Pending'
                ELSE 'No Trip'
            END AS status,

            CASE WHEN COUNT(bt.id) = SUM(bt.submitted) AND COUNT(bt.id) > 0 THEN 1 ELSE 0 END AS submitted,

            (
                SELECT rl.submitted_by
                FROM remittance_logs rl
                WHERE rl.driver_id = d.driver_id
                ORDER BY rl.created_at DESC
                LIMIT 1
            ) AS submitted_by

        FROM bus_trips bt

        INNER JOIN drivers d
            ON bt.driver_id = d.driver_id

        LEFT JOIN passenger_trips pt
            ON pt.trip_no = bt.id
            AND LOWER(TRIM(pt.payment_status)) = 'paid'

        WHERE bt.trip_date = ?

        GROUP BY
            d.driver_id,
            d.full_name

        HAVING COUNT(pt.id) > 0

        ORDER BY
            (CASE WHEN COUNT(bt.id) = SUM(bt.submitted) AND COUNT(bt.id) > 0 THEN 1 ELSE 0 END) ASC,
            d.full_name ASC
    ");

    $stmt->execute([$phDate]);

    return $stmt->fetchAll();
}

    /**
     * =========================
     * ALREADY SUBMITTED
     * =========================
     */
    public static function alreadySubmitted(
        $driver_id,
        $phDate = null
    ) {
        if (!$phDate) {
            $phDate = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
        }

        $db = Database::get();

        $stmt = $db->prepare("

            SELECT id

            FROM remittance_logs

            WHERE driver_id = ?

            AND COALESCE(business_date, DATE(created_at)) = ?

            AND LOWER(TRIM(COALESCE(status, ''))) <> 'overdue'

        ");

        $stmt->execute([
            $driver_id,
            $phDate
        ]);

        return $stmt->fetch()

            ? true

            : false;
    }

    /**
     * =========================
     * DRIVER TRIPS
     * =========================
     */
public static function getDriverTrips(
    $driver,
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

            COUNT(pt.id)
                AS total_passengers,

            -- =========================
            -- CASH ONLY
            -- =========================
            COALESCE(
                SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'cash' THEN pt.fare ELSE 0 END),
                0
            ) AS cash_total,

            -- =========================
            -- GCASH DISPLAY ONLY
            -- =========================
            COALESCE(
                SUM(CASE WHEN LOWER(TRIM(pt.payment_method)) = 'gcash' THEN pt.fare ELSE 0 END),
                0
            ) AS gcash_total,

            -- =========================
            -- TOTAL COLLECTION
            -- =========================
            COALESCE(
                SUM(pt.fare),
                0
            ) AS total_collection

        FROM bus_trips bt

        LEFT JOIN passenger_trips pt
            ON bt.id = pt.trip_no

        WHERE bt.driver_id = ?

        AND LOWER(TRIM(pt.payment_status)) = 'paid'

        AND DATE(pt.boarding_time)
            = ?

        GROUP BY bt.id

        ORDER BY bt.id DESC

    ");

    $stmt->execute([
        $driver,
        $phDate
    ]);

    return $stmt->fetchAll(
        \PDO::FETCH_ASSOC
    );
}
    /**
     * =========================
     * TODAY HISTORY TRIPS
     * =========================
     */
public static function getTodayHistoryTrips($phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();
        $submittedSql = self::historySubmittedSql($db);

        $stmt = $db->prepare("
        SELECT
            pth.trip_id AS trip_no,
            pth.bus_number AS bus_no,
            pth.driver_id,

            COALESCE(d.full_name, pth.drivers_full_name) AS full_name,

            b.plate_number,

            COUNT(pth.history_id) AS total_passengers,

            COALESCE(SUM(pth.fare), 0) AS total_collection,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pth.payment_method)) IN ('cash','hybrid') THEN pth.fare ELSE 0 END), 0) AS cash_total,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pth.payment_method)) = 'gcash' THEN pth.fare ELSE 0 END), 0) AS gcash_total,

            $submittedSql

        FROM passenger_trip_history pth

        LEFT JOIN drivers d
            ON d.driver_id = pth.driver_id

        LEFT JOIN buses b
            ON b.bus_id = pth.bus_number

        WHERE pth.trip_date >= ?
          AND pth.driver_id IS NOT NULL
          AND TRIM(pth.driver_id) <> ''

        GROUP BY
            pth.trip_id,
            pth.bus_number,
            pth.driver_id,
            d.full_name,
            b.plate_number

        ORDER BY
            pth.trip_id DESC
    ");

    $stmt->execute([$phDate]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

    /**
     * =========================
     * DRIVER HISTORY SUMMARY
     * =========================
     */
public static function getDriverHistorySummary($phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();
        $submittedSql = self::historySubmittedSql($db);

        $stmt = $db->prepare("
        SELECT
            pth.driver_id,

            COALESCE(d.full_name, MAX(pth.drivers_full_name)) AS full_name,

            COALESCE(SUM(pth.fare), 0) AS total_collection,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pth.payment_method)) IN ('cash','hybrid') THEN pth.fare ELSE 0 END), 0) AS expected_cash,

            $submittedSql

        FROM passenger_trip_history pth

        LEFT JOIN drivers d
            ON d.driver_id = pth.driver_id

        WHERE pth.trip_date >= ?
          AND pth.driver_id IS NOT NULL
          AND TRIM(pth.driver_id) <> ''

        GROUP BY
            pth.driver_id,
            d.full_name

        ORDER BY
            d.full_name ASC
    ");

    $stmt->execute([$phDate]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

    /**
     * =========================
     * DRIVER HISTORY TRIPS
     * =========================
     */
public static function getDriverHistoryTrips($driver, $phDate = null)
{
    if (!$phDate) {
        date_default_timezone_set('Asia/Manila');
        $phDate = date('Y-m-d');
    }

    $db = Database::get();
        $submittedSql = self::historySubmittedSql($db);

        $stmt = $db->prepare("
        SELECT
            pth.trip_id AS trip_no,
            pth.bus_number AS bus_no,
            pth.driver_id,

            COALESCE(d.full_name, pth.drivers_full_name) AS driver_name,

            b.plate_number,

            COUNT(pth.history_id) AS total_passengers,

            COALESCE(SUM(pth.fare), 0) AS total_collection,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pth.payment_method)) IN ('cash','hybrid') THEN pth.fare ELSE 0 END), 0) AS cash_total,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pth.payment_method)) = 'gcash' THEN pth.fare ELSE 0 END), 0) AS gcash_total,

            $submittedSql

        FROM passenger_trip_history pth

        LEFT JOIN drivers d
            ON d.driver_id = pth.driver_id

        LEFT JOIN buses b
            ON b.bus_id = pth.bus_number

        WHERE pth.driver_id = ?
          AND pth.trip_date = ?

        GROUP BY
            pth.trip_id,
            pth.bus_number,
            pth.driver_id,
            d.full_name,
            b.plate_number

        ORDER BY
            pth.trip_id DESC
    ");

    $stmt->execute([$driver, $phDate]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

    /**
     * =========================
     * FINALIZE REMITTANCE
     * =========================
     */
    public static function finalize(
        array $data
    ) {

        $db = Database::get();
        self::ensureBusinessDateColumn($db);

        $driverId = (string) ($data['driver_id'] ?? '');
        $businessDate = (string) ($data['business_date'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d'));

        $existing = self::findLogForDate($db, $driverId, $businessDate);

        $params = [
            'driver_id' => $driverId,
            'business_date' => $businessDate,
            'trip_no' => (string) ($data['trip_no'] ?? ''),
            'total_passengers' => (int) ($data['total_passengers'] ?? 0),
            'total_collection' => (float) ($data['total_collection'] ?? $data['expected_cash']),
            'expected_cash' => $data['expected_cash'],
            'actual_cash' => $data['actual_cash'],
            'discrepancy_amount' => $data['discrepancy_amount'],
            'status' => $data['status'],
            'finance_code' => 'verified',
            'confirmed_by' => $data['confirmed_by'] ?? null,
            'submitted_by' => $data['submitted_by'] ?? $data['confirmed_by'] ?? null,
            'remitted_by' => $data['remitted_by'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? $data['confirmed_by'] ?? null,
            'reviewed_at' => ($data['reviewed_at'] ?? date('Y-m-d H:i:s')) ?: date('Y-m-d H:i:s'),
        ];

        if ($existing) {

            /**
             * A record already exists for this driver and service day
             * (an Overdue flag or a re-run) — settle it in place so one
             * remittance never becomes two records.
             */
            $params['id'] = (int) $existing['id'];

            /**
             * The UPDATE below has no :driver_id placeholder. Native
             * (non-emulated) MySQL prepared statements reject any bound
             * key without a matching placeholder with
             * SQLSTATE[HY093] "parameter was not defined", so the
             * insert-shaped key must not be passed to the update. This
             * is the late-processing path for Overdue rows.
             */
            unset($params['driver_id']);

            $stmt = $db->prepare("

                UPDATE remittance_logs SET

                    business_date = :business_date,
                    trip_no = :trip_no,
                    total_passengers = :total_passengers,
                    total_collection = :total_collection,
                    expected_cash = :expected_cash,
                    actual_cash = :actual_cash,
                    discrepancy_amount = :discrepancy_amount,
                    status = :status,
                    finance_code = :finance_code,
                    confirmed_by = :confirmed_by,
                    submitted_by = :submitted_by,
                    remitted_by = :remitted_by,
                    reviewed_by = :reviewed_by,
                    reviewed_at = :reviewed_at

                WHERE id = :id

            ");

            $stmt->execute($params);

            $remittanceLogId = (int) $existing['id'];

        } else {

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
                    finance_code,
                    confirmed_by,
                    submitted_by,
                    remitted_by,
                    reviewed_by,
                    reviewed_at,
                    created_at

                )

                VALUES (

                    :driver_id,
                    :business_date,
                    :trip_no,
                    :total_passengers,
                    :total_collection,
                    :expected_cash,
                    :actual_cash,
                    :discrepancy_amount,
                    :status,
                    :finance_code,
                    :confirmed_by,
                    :submitted_by,
                    :remitted_by,
                    :reviewed_by,
                    :reviewed_at,
                    NOW()

                )

            ");

            $stmt->execute($params);

            $remittanceLogId = (int) $db->lastInsertId();
        }

        /**
         * =========================
         * NOTIFY ADMINS
         * =========================
         */
        try {
            $notif = new \App\Models\Notification();
            $actorUserId = (int) ($data['actor_user_id'] ?? 0);
            $status = $data['status'] ?? 'Completed';
            $notif->createForAdminRoles(
                'Remittance Finalized',
                'Remittance log #' . $remittanceLogId . ' was finalized (' . $status . ')',
                'info',
                'remittance',
                $remittanceLogId,
                $actorUserId ?: null,
                'You finalized a remittance',
                'You finalized remittance log #' . $remittanceLogId . ' (' . $status . ')'
            );
        } catch (\Throwable $e) {
            error_log("Remittance notification error: " . $e->getMessage());
        }

        /**
         * =========================
         * MARK ALL DRIVER TRIPS SUBMITTED
         * =========================
         */
        $updateTrip = $db->prepare("

                UPDATE bus_trips

                SET submitted = 1

                WHERE driver_id = :driver_id

                AND trip_date = :business_date

            ");

        $updateTrip->execute([
            'business_date' => $businessDate,
            'driver_id' => $driverId,
        ]);

        /**
         * =========================
         * MARK ALL PASSENGERS REMITTED
         * =========================
         */
        $updatePassengers =
            $db->prepare("

                UPDATE passenger_trips pt

                INNER JOIN bus_trips bt
                    ON pt.trip_no = bt.id

                SET
                    pt.remittance_status = CASE
                        WHEN :status IN ('Discrepancy' COLLATE utf8mb4_unicode_ci, 'No Remit' COLLATE utf8mb4_unicode_ci) THEN 'Discrepancy'
                        ELSE 'Remitted'
                    END,
                    pt.remitted_at = NOW(),
                    pt.remitted_by = :remitted_by,
                    pt.reviewed_by = :reviewed_by,
                    pt.reviewed_at = NOW()

                WHERE bt.driver_id = :driver_id

                AND bt.trip_date = :business_date

                AND LOWER(TRIM(pt.payment_status)) = 'paid'

            ");

        $updatePassengers->execute([
            'status' => $data['status'],
            'remitted_by' => $data['remitted_by'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? $data['confirmed_by'],
            'driver_id' => $data['driver_id'],
            'business_date' => $businessDate,
        ]);

        /**
         * =========================
         * MARK HISTORY ROWS REMITTED
         * =========================
         * Generated/archived trips live in passenger_trip_history (no bus_trips),
         * so mark that driver's rows for today as settled too. Unlike live
         * passenger_trips (whose collections are already 'paid' before remit),
         * generated today rows start as 'pending', so payment_status is advanced
         * to 'paid' together with the remittance flip.
         */
        $updateHistory =
            $db->prepare("

                UPDATE passenger_trip_history pth

                SET
                    pth.payment_status = 'paid',
                    pth.remittance_status = CASE
                        WHEN :status IN ('Discrepancy' COLLATE utf8mb4_unicode_ci, 'No Remit' COLLATE utf8mb4_unicode_ci) THEN 'Discrepancy'
                        ELSE 'Remitted'
                    END,
                    pth.actual_cash = CASE
                        WHEN LOWER(TRIM(pth.payment_method)) IN ('cash','hybrid') THEN COALESCE(pth.fare, 0)
                        ELSE 0
                    END,
                    pth.discrepancy_amount = 0,
                    pth.remitted_at = NOW(),
                    pth.remitted_by = :remitted_by

                WHERE pth.driver_id = :driver_id

                AND pth.trip_date = :business_date

            ");

        $updateHistory->execute([
            'status' => $data['status'],
            'remitted_by' => $data['remitted_by'] ?? null,
            'driver_id' => $data['driver_id'],
            'business_date' => $businessDate,
        ]);

        return $remittanceLogId;
    }

    /**
     * =========================
     * FINANCE AUTHORIZATION CODE
     * =========================
     * Stored only as a password_hash (never plaintext), expires 30 days after
     * creation or renewal, and is verified server-side. Legacy plaintext
     * values in system_settings.finance_code are migrated on first
     * verification with a fresh 30-day window and are not written again.
     */
    public const FINANCE_CODE_TTL_DAYS = 30;
    public const FINANCE_CODE_NOTIFY_DAYS = [7, 1];
    public const FINANCE_CODE_RATE_WINDOW = 900;
    public const FINANCE_CODE_RATE_MAX = 5;

    private static function settingGet(\PDO $db, string $key): ?string
    {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private static function settingSet(\PDO $db, string $key, ?string $value): void
    {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Finance authorization code state used by the UI and verification.
     */
    public static function getFinanceCodeMeta(): array
    {
        $meta = [
            'has_code' => false,
            'hash' => null,
            'expires_at' => null,
            'expires_at_label' => null,
            'days_left' => null,
            'expired' => false,
            'legacy_pending' => false,
            'owner_email' => null,
            'set_by' => null,
            'notified' => [7 => false, 1 => false],
        ];

        try {
            $db = Database::get();

            $hash = self::settingGet($db, 'finance_code_hash');
            $expires = self::settingGet($db, 'finance_code_expires_at');
            $legacy = self::settingGet($db, 'finance_code');

            $meta['owner_email'] = self::settingGet($db, 'finance_code_owner_email');
            $meta['set_by'] = self::settingGet($db, 'finance_code_set_by');
            $meta['notified'][7] = self::settingGet($db, 'finance_code_notified_7d') === '1';
            $meta['notified'][1] = self::settingGet($db, 'finance_code_notified_1d') === '1';

            if ($hash !== null && trim($hash) !== '') {
                $meta['has_code'] = true;
                $meta['hash'] = $hash;
                $meta['expires_at'] = $expires;

                if ($expires !== null && trim($expires) !== '') {
                    $expiryTs = strtotime($expires);
                    if ($expiryTs !== false) {
                        $meta['expires_at_label'] = date('F j, Y g:i A', $expiryTs);
                        $meta['expired'] = time() >= $expiryTs;
                        $meta['days_left'] = (int) ceil(($expiryTs - time()) / 86400);
                    }
                } else {
                    $meta['expired'] = true;
                }
            } else {
                $meta['legacy_pending'] = $legacy !== null && trim($legacy) !== '';
            }
        } catch (\Throwable $e) {
            error_log('RemittanceModel: finance code meta failed: ' . $e->getMessage());
        }

        return $meta;
    }

    /**
     * Store or renew the code: fresh 30-day expiry, reminder markers reset.
     */
    public static function setFinanceCodeHash(string $code, ?int $userId = null, ?string $ownerEmail = null): array
    {
        $db = Database::get();

        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
            ->modify('+' . self::FINANCE_CODE_TTL_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        self::settingSet($db, 'finance_code_hash', password_hash($code, PASSWORD_DEFAULT));
        self::settingSet($db, 'finance_code_expires_at', $expires);
        self::settingSet($db, 'finance_code', '');
        self::settingSet($db, 'finance_code_set_by', $userId !== null ? (string) $userId : '');
        self::settingSet($db, 'finance_code_owner_email', $ownerEmail);
        self::settingSet($db, 'finance_code_notified_7d', '0');
        self::settingSet($db, 'finance_code_notified_1d', '0');

        return self::getFinanceCodeMeta();
    }

    /**
     * Verify a submitted code server-side.
     *
     * @return array{status:string,meta:array} status: ok|invalid|expired|missing
     */
    public static function verifyFinanceCode(string $input): array
    {
        $meta = self::getFinanceCodeMeta();

        if (!$meta['has_code'] && $meta['legacy_pending']) {
            $db = Database::get();
            $legacy = trim((string) self::settingGet($db, 'finance_code'));
            if ($legacy !== '') {
                $meta = self::setFinanceCodeHash($legacy);
            }
        }

        if (!$meta['has_code']) {
            return ['status' => 'missing', 'meta' => $meta];
        }

        if ($meta['expired']) {
            return ['status' => 'expired', 'meta' => $meta];
        }

        if (password_verify($input, (string) $meta['hash'])) {
            return ['status' => 'ok', 'meta' => $meta];
        }

        return ['status' => 'invalid', 'meta' => $meta];
    }

    public static function markFinanceCodeNotified(int $days): void
    {
        if (!in_array($days, self::FINANCE_CODE_NOTIFY_DAYS, true)) {
            return;
        }

        try {
            $db = Database::get();
            self::settingSet($db, 'finance_code_notified_' . $days . 'd', '1');
        } catch (\Throwable $e) {
        }
    }

    /**
     * =========================
     * FINANCE CODE RATE LIMITING
     * =========================
     */
    private static function financeCodeRatePath(string $scope, int $subjectId): ?string
    {
        $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($root === '') {
            return null;
        }

        $dir = $root . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $scope = preg_replace('/[^a-zA-Z0-9_-]/', '', $scope) ?: 'default';

        return $dir . '/finance-code-' . $scope . '-' . $subjectId . '.json';
    }

    /**
     * @return array{blocked:bool,retry_after:int,remaining:int}
     */
    public static function financeCodeAttemptStatus(string $scope, int $subjectId): array
    {
        $result = ['blocked' => false, 'retry_after' => 0, 'remaining' => self::FINANCE_CODE_RATE_MAX];
        $path = self::financeCodeRatePath($scope, $subjectId);
        if ($path === null || !is_file($path)) {
            return $result;
        }

        $attempts = json_decode((string) @file_get_contents($path), true);
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $now = time();
        $windowStart = $now - self::FINANCE_CODE_RATE_WINDOW;
        $attempts = array_values(array_filter($attempts, fn($ts) => (int) $ts > $windowStart));
        $count = count($attempts);

        if ($count >= self::FINANCE_CODE_RATE_MAX) {
            $result['blocked'] = true;
            $result['retry_after'] = max(1, ((int) $attempts[0] + self::FINANCE_CODE_RATE_WINDOW) - $now);
            $result['remaining'] = 0;
            return $result;
        }

        $result['remaining'] = self::FINANCE_CODE_RATE_MAX - $count;

        return $result;
    }

    public static function recordFinanceCodeFailure(string $scope, int $subjectId): void
    {
        $path = self::financeCodeRatePath($scope, $subjectId);
        if ($path === null) {
            return;
        }

        $attempts = [];
        if (is_file($path)) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (is_array($decoded)) {
                $attempts = $decoded;
            }
        }

        $now = time();
        $windowStart = $now - self::FINANCE_CODE_RATE_WINDOW;
        $attempts = array_values(array_filter($attempts, fn($ts) => (int) $ts > $windowStart));
        $attempts[] = $now;

        @file_put_contents($path, json_encode($attempts), LOCK_EX);
    }

    public static function clearFinanceCodeFailures(string $scope, int $subjectId): void
    {
        $path = self::financeCodeRatePath($scope, $subjectId);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
?>