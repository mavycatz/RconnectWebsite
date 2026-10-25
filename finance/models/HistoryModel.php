<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/RemittanceModel.php';

use App\Core\Database;

class HistoryModel
{
    private static function ensureBusinessDate(): void
    {
        try {
            \Finance\Models\RemittanceModel::ensureBusinessDateColumn(Database::get());
        } catch (\Throwable $e) {
        }
    }

    /**
     * =========================
     * GET ALL HISTORY
     * =========================
     */
    public static function getAll()
    {
        self::ensureBusinessDate();

        $db =
            Database::get();

        $sql = "

            SELECT

                rl.id,

                rl.driver_id,

                d.full_name,

                rl.trip_no,

                dba.bus_id AS bus_no,

                rl.total_passengers,

                rl.total_collection,

                rl.expected_cash,

                rl.actual_cash,

                rl.discrepancy_amount,

                rl.status,

                rl.finance_code,

                rl.confirmed_by,

                rl.submitted_by,

                rl.remitted_by,

                rl.reviewed_by,

                rl.reviewed_at,

                COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                rl.created_at

            FROM remittance_logs rl

            LEFT JOIN drivers d
                ON rl.driver_id = d.driver_id

            LEFT JOIN driver_bus_assignment dba
                ON rl.driver_id = dba.driver_id

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

        $stmt =
            $db->prepare("

                SELECT

                    rl.id,

                    rl.driver_id,

                    d.full_name,

                    rl.trip_no,

                    dba.bus_id AS bus_no,

                    rl.total_passengers,

                    rl.total_collection,

                    rl.expected_cash,

                    rl.actual_cash,

                    rl.discrepancy_amount,

                    rl.status,

                    rl.finance_code,

                    rl.confirmed_by,

                    rl.submitted_by,

                    rl.remitted_by,

                    rl.reviewed_by,

                    rl.reviewed_at,

                    COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                    rl.created_at

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
     * GET DISCREPANCIES ONLY
     * =========================
     */
    public static function getDiscrepancies()
    {
        $db =
            Database::get();

        $stmt =
            $db->query("

                SELECT

                    rl.id,

                    rl.driver_id,

                    d.full_name,

                    rl.trip_no,

                    dba.bus_id AS bus_no,

                    rl.expected_cash,

                    rl.actual_cash,

                    rl.discrepancy_amount,

                    rl.status,

                    COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                    rl.created_at

                FROM remittance_logs rl

                LEFT JOIN drivers d
                    ON rl.driver_id = d.driver_id

                LEFT JOIN driver_bus_assignment dba
                    ON rl.driver_id = dba.driver_id

                WHERE rl.discrepancy_amount > 0

                ORDER BY rl.created_at DESC

            ");

        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }

    /**
     * =========================
     * SEARCH HISTORY
     * =========================
     */
    public static function search(
        string $keyword
    ) {

        $db =
            Database::get();

        $stmt =
            $db->prepare("

                SELECT

                    rl.id,

                    rl.driver_id,

                    d.full_name,

                    rl.trip_no,

                    dba.bus_id AS bus_no,

                    rl.total_collection,

                    rl.expected_cash,

                    rl.actual_cash,

                    rl.discrepancy_amount,

                    rl.status,

                    rl.submitted_by,

                    rl.remitted_by,

                    rl.reviewed_by,

                    rl.reviewed_at,

                    COALESCE(rl.business_date, DATE(rl.created_at)) AS business_date,

                    rl.created_at

                FROM remittance_logs rl

                LEFT JOIN drivers d
                    ON rl.driver_id = d.driver_id

                LEFT JOIN driver_bus_assignment dba
                    ON rl.driver_id = dba.driver_id

                WHERE

                    d.full_name LIKE ?

                    OR rl.driver_id LIKE ?

                    OR dba.bus_id LIKE ?

                    OR rl.status LIKE ?

                    OR rl.trip_no LIKE ?

                ORDER BY rl.created_at DESC

            ");

        $search =
            '%' . $keyword . '%';

        $stmt->execute([

            $search,
            $search,
            $search,
            $search,
            $search

        ]);

        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }
}
