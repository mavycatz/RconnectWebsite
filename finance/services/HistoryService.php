<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/HistoryModel.php';
require_once __DIR__ . '/RemittanceConsolidationService.php';
require_once __DIR__ . '/RemittanceService.php';

use Finance\Models\HistoryModel;

class HistoryService
{
    /**
     * =========================
     * ALL HISTORY
     * =========================
     */
    public static function getHistory()
    {
        $rawRows = HistoryModel::getAll();

        // One driver = one record per business date. Duplicate rows (legacy
        // data, split buses/trips) are merged here so the table, totals and
        // exports always show a consolidated remittance.
        $consolidated = [];
        foreach (RemittanceConsolidationService::groupRows($rawRows) as $group) {
            $consolidated[] = RemittanceConsolidationService::summarize($group);
        }

        usort($consolidated, static function (array $a, array $b): int {
            return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
        });

        $rows = $consolidated;

        $history = [];

        foreach ($rows as $row) {

            $history[] = [

                'id' =>

                    $row['id'] ?? null,

                'driver_id' =>

                    $row['driver_id'] ?? null,

                'full_name' =>

                    $row['full_name'] ?? 'N/A',

                // =========================
                // BUS NUMBER
                // =========================
                'bus_no' =>

                    $row['bus_no'] ?? 'N/A',

                'trip_no' =>

                    $row['trip_no'] ?? 'N/A',

                'total_passengers' =>

                    (int)(
                        $row['total_passengers']
                        ?? 0
                    ),

                'total_collection' =>

                    round(
                        (float)(
                            $row['total_collection']
                            ?? 0
                        ),
                        2
                    ),

                'expected_cash' =>

                    round(
                        (float)(
                            $row['expected_cash']
                            ?? 0
                        ),
                        2
                    ),

                'actual_cash' =>

                    round(
                        (float)(
                            $row['actual_cash']
                            ?? 0
                        ),
                        2
                    ),

                'discrepancy_amount' =>

                    round(
                        (float)(
                            $row['discrepancy_amount']
                            ?? 0
                        ),
                        2
                    ),

                'status' =>

                    $row['status']
                    ?? 'Pending',

                'finance_code' =>

                    $row['finance_code']
                    ?? null,

                'confirmed_by' =>

                    $row['confirmed_by']
                    ?? 'Finance Manager',

                'submitted_by' =>

                    $row['submitted_by']
                    ?? null,

                'remitted_by' =>

                    $row['remitted_by']
                    ?? null,

                'reviewed_by' =>

                    $row['reviewed_by']
                    ?? null,

                'reviewed_at' =>

                    $row['reviewed_at']
                    ?? null,

                'business_date' =>

                    $row['business_date']
                    ?? null,

                'consolidated_count' =>

                    (int) (
                        $row['consolidated_count']
                        ?? 1
                    ),

                'created_at' =>

                    $row['created_at']
                    ?? null
            ];
        }

        return $history;
    }

    /**
     * =========================
     * DASHBOARD TOTALS
     * =========================
     */
    public static function getTotals()
    {
        $rows =
            self::getHistory();

        $totalRemittances = 0;

        $totalCash = 0;

        $totalDiscrepancies = 0;

        $completed = 0;

        $discrepancyCount = 0;

        foreach ($rows as $row) {

            $totalRemittances++;

            // =========================
            // CASH REMITTED
            // =========================
            $totalCash +=

                round(
                    (float)(
                        $row['actual_cash']
                        ?? 0
                    ),
                    2
                );

            // =========================
            // DISCREPANCY
            // =========================
            $discrepancy =

                round(
                    (float)(
                        $row['discrepancy_amount']
                        ?? 0
                    ),
                    2
                );

            $totalDiscrepancies +=
                $discrepancy;

            // =========================
            // STATUS COUNTS
            // =========================
            if (

                strtolower(
                    $row['status']
                    ?? ''
                )

                ===

                'completed'
            ) {

                $completed++;
            }

            if ($discrepancy > 0) {

                $discrepancyCount++;
            }
        }

        return [

            'total_remittances' =>

                $totalRemittances,

            'total_cash' =>

                round(
                    $totalCash,
                    2
                ),

            'total_discrepancies' =>

                round(
                    $totalDiscrepancies,
                    2
                ),

            'completed' =>

                $completed,

            'discrepancy_count' =>

                $discrepancyCount
        ];
    }

    /**
     * =========================
     * SINGLE RECORD
     * =========================
     */
    public static function getById(
        int $id
    ) {

        $row =
            HistoryModel::getById($id);

        if (!$row) {

            return null;
        }

        $businessDate = $row['business_date']
            ?? (!empty($row['created_at'])
                ? date('Y-m-d', strtotime((string) $row['created_at']))
                : '');

        $trips = [];
        if (!empty($row['driver_id']) && $businessDate !== '') {
            try {
                $day = RemittanceService::computeDriverDay((string) $row['driver_id'], $businessDate);
                $trips = $day['trips'];
            } catch (\Throwable $e) {
                $trips = [];
            }
        }

        return [

            'id' =>

                $row['id'] ?? null,

            'driver_id' =>

                $row['driver_id'] ?? null,

            'full_name' =>

                $row['full_name'] ?? 'N/A',

            // =========================
            // BUS NUMBER
            // =========================
            'bus_no' =>

                $row['bus_no'] ?? 'N/A',

            'trip_no' =>

                $row['trip_no'] ?? 'N/A',

            'total_passengers' =>

                (int)(
                    $row['total_passengers']
                    ?? 0
                ),

            'total_collection' =>

                round(
                    (float)(
                        $row['total_collection']
                        ?? 0
                    ),
                    2
                ),

            'expected_cash' =>

                round(
                    (float)(
                        $row['expected_cash']
                        ?? 0
                    ),
                    2
                ),

            'actual_cash' =>

                round(
                    (float)(
                        $row['actual_cash']
                        ?? 0
                    ),
                    2
                ),

            'discrepancy_amount' =>

                round(
                    (float)(
                        $row['discrepancy_amount']
                        ?? 0
                    ),
                    2
                ),

            'status' =>

                $row['status']
                ?? 'Pending',

            'finance_code' =>

                $row['finance_code']
                ?? null,

            'confirmed_by' =>

                $row['confirmed_by']
                ?? 'Finance Manager',

            'submitted_by' =>

                $row['submitted_by']
                ?? null,

            'remitted_by' =>

                $row['remitted_by']
                ?? null,

            'reviewed_by' =>

                $row['reviewed_by']
                ?? null,

            'reviewed_at' =>

                $row['reviewed_at']
                ?? null,

            'business_date' =>

                $businessDate,

            'trips' =>

                $trips,

            'created_at' =>

                $row['created_at']
                ?? null
        ];
    }
}
?>