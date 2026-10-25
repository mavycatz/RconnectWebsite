<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/RemittanceModel.php';

use App\Core\Database;

class DiscrepancyModel
{
    private static ?bool $hasResolutionCols = null;

    /**
     * =========================
     * RESOLUTION COLUMN CHECK
     * Graceful when the migration has not been run yet.
     * =========================
     */
    private static function hasResolutionColumns(): bool
    {
        if (self::$hasResolutionCols !== null) {
            return self::$hasResolutionCols;
        }

        try {
            $db = Database::get();
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'remittance_logs'
                  AND COLUMN_NAME = 'resolution_type'
            ");
            $stmt->execute();
            self::$hasResolutionCols = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            self::$hasResolutionCols = false;
        }

        return self::$hasResolutionCols;
    }

    /**
     * =========================
     * GET ALL DISCREPANCIES
     * =========================
     */
    public static function getAll()
    {
        $db =
            Database::get();

        \Finance\Models\RemittanceModel::ensureBusinessDateColumn($db);

        $resolutionSelect =
            self::hasResolutionColumns()
                ? ", rl.resolution_type, rl.resolution_notes, rl.resolved_by, rl.resolved_at, rl.supporting_document"
                : '';

        $sql = "

            SELECT

                rl.id,

                rl.driver_id,

                d.full_name,

                COALESCE(
                    NULLIF(TRIM(rl.trip_no), ''),
                    (
                        SELECT pth.trip_id
                        FROM passenger_trip_history pth
                        WHERE pth.driver_id = rl.driver_id
                          AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = COALESCE(rl.business_date, DATE(rl.created_at))
                        ORDER BY pth.date_booked DESC, pth.trip_id DESC
                        LIMIT 1
                    )
                ) AS trip_no,

                dba.bus_id AS bus_no,

                rl.expected_cash,

                rl.actual_cash,

                rl.discrepancy_amount,

                rl.status,

                rl.created_at,

                COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                rl.total_collection,

                CASE
                    WHEN COALESCE(rl.total_passengers, 0) > 0 THEN rl.total_passengers
                    ELSE (
                        SELECT COUNT(*)
                        FROM passenger_trip_history pth
                        WHERE pth.driver_id = rl.driver_id
                          AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = COALESCE(rl.business_date, DATE(rl.created_at))
                    )
                END AS total_passengers,

                rl.submitted_by,

                rl.verified_at,

                rl.confirmed_by

                $resolutionSelect

            FROM remittance_logs rl

            LEFT JOIN drivers d
                ON rl.driver_id = d.driver_id

            LEFT JOIN driver_bus_assignment dba
                ON rl.driver_id = dba.driver_id

            WHERE rl.discrepancy_amount > 0

            ORDER BY rl.created_at DESC

        ";

        $stmt =
            $db->query($sql);

        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }

    /**
     * =========================
     * GET SINGLE RECORD
     * =========================
     */
    public static function getById(
        int $id
    ) {

        $db =
            Database::get();

        \Finance\Models\RemittanceModel::ensureBusinessDateColumn($db);

        $resolutionSelect =
            self::hasResolutionColumns()
                ? ", rl.resolution_type, rl.resolution_notes, rl.resolved_by, rl.resolved_at, rl.supporting_document"
                : '';

        $stmt =
            $db->prepare("

                SELECT

                    rl.id,

                    rl.driver_id,

                    d.full_name,

                    COALESCE(
                        NULLIF(TRIM(rl.trip_no), ''),
                        (
                            SELECT pth.trip_id
                            FROM passenger_trip_history pth
                            WHERE pth.driver_id = rl.driver_id
                              AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = COALESCE(rl.business_date, DATE(rl.created_at))
                            ORDER BY pth.date_booked DESC, pth.trip_id DESC
                            LIMIT 1
                        )
                    ) AS trip_no,

                    dba.bus_id AS bus_no,

                    rl.expected_cash,

                    rl.actual_cash,

                    rl.discrepancy_amount,

                    rl.status,

                    rl.created_at,

                    COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                    rl.total_collection,

                    CASE
                        WHEN COALESCE(rl.total_passengers, 0) > 0 THEN rl.total_passengers
                        ELSE (
                            SELECT COUNT(*)
                            FROM passenger_trip_history pth
                            WHERE pth.driver_id = rl.driver_id
                              AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = COALESCE(rl.business_date, DATE(rl.created_at))
                        )
                    END AS total_passengers,

                    rl.submitted_by,

                    rl.verified_at,

                    rl.confirmed_by

                    $resolutionSelect

                FROM remittance_logs rl

                LEFT JOIN drivers d
                    ON rl.driver_id = d.driver_id

                LEFT JOIN driver_bus_assignment dba
                    ON rl.driver_id = dba.driver_id

                WHERE rl.id = ?

                LIMIT 1

            ");

        $stmt->execute([
            $id
        ]);

        return $stmt->fetch(
            \PDO::FETCH_ASSOC
        );
    }

    /**
     * =========================
     * RESOLVE CASE
     * Stores resolution audit data when the migration columns exist;
     * otherwise falls back to a plain status update.
     * Original Expected/Actual/Shortage amounts are NEVER modified.
     * =========================
     */
    public static function resolve(
        int $id,
        string $type,
        string $notes,
        string $resolvedBy,
        ?string $document = null
    ) {

        $db =
            Database::get();

        if (self::hasResolutionColumns()) {

            $stmt =
                $db->prepare("

                    UPDATE remittance_logs

                    SET status = 'Resolved',

                        resolution_type = ?,

                        resolution_notes = ?,

                        resolved_by = ?,

                        resolved_at = NOW(),

                        supporting_document = ?

                    WHERE id = ?

                ");

            return $stmt->execute([
                $type,
                $notes,
                $resolvedBy,
                $document,
                $id
            ]);
        }

        $stmt =
            $db->prepare("

                UPDATE remittance_logs

                SET status = 'Resolved'

                WHERE id = ?

            ");

        return $stmt->execute([
            $id
        ]);
    }
}
