<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/DiscrepancyModel.php';

use Finance\Models\DiscrepancyModel;

class DiscrepancyService
{
    /**
     * =========================
     * NORMALIZE STATUS
     * =========================
     */
    private static function normalizeStatus($status): string
    {
        $s = strtolower((string) ($status ?? ''));

        if ($s === 'resolved' || $s === 'completed') {
            return 'Resolved';
        }

        if ($s === 'no remit') {
            return 'No Remit';
        }

        if ($s === 'pending') {
            return 'Pending';
        }

        return 'Discrepancy';
    }

    /**
     * =========================
     * GET ALL DISCREPANCIES
     * =========================
     */
    public static function getAll()
    {
        $rows =
            DiscrepancyModel
                ::getAll();

        $records = [];

        foreach ($rows as $row) {

            $records[] = [

                'id' => $row['id'] ?? null,

                'driver_id' => $row['driver_id'] ?? 'N/A',

                'full_name' => $row['full_name'] ?? 'N/A',

                'trip_no' => $row['trip_no'] ?? 'N/A',

                'bus_no' => $row['bus_no'] ?? 'N/A',

                'expected_cash' =>
                    round(
                        (float) ($row['expected_cash'] ?? 0),
                        2
                    ),

                'actual_cash' =>
                    round(
                        (float) ($row['actual_cash'] ?? 0),
                        2
                    ),

                'discrepancy_amount' =>
                    round(
                        (float) ($row['discrepancy_amount'] ?? 0),
                        2
                    ),

                'status' =>
                    self::normalizeStatus($row['status']),

                'created_at' => $row['created_at'] ?? null,

                'business_date' => $row['business_date']
                    ?? (!empty($row['created_at']) ? date('Y-m-d', strtotime((string) $row['created_at'])) : null),

                'remittance_ref' => !empty($row['id'])
                    ? 'RM-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT)
                    : null,

                'total_collection' =>
                    round(
                        (float) ($row['total_collection'] ?? 0),
                        2
                    ),

                'total_passengers' =>
                    (int) ($row['total_passengers'] ?? 0),

                'submitted_by' => $row['submitted_by'] ?? null,

                'verified_at' => $row['verified_at'] ?? null,

                'confirmed_by' => $row['confirmed_by'] ?? null,

                'resolution_type' => $row['resolution_type'] ?? null,

                'resolution_notes' => $row['resolution_notes'] ?? null,

                'resolved_by' => $row['resolved_by'] ?? null,

                'resolved_at' => $row['resolved_at'] ?? null,

                'supporting_document' => $row['supporting_document'] ?? null
            ];
        }

        return $records;
    }

    /**
     * =========================
     * GET TOTALS
     * =========================
     */
    public static function getTotals()
    {
        $rows =
            DiscrepancyModel
                ::getAll();

        $totalCases = 0;

        $totalAmount = 0;

        $resolved = 0;

        $pending = 0;

        foreach ($rows as $row) {

            $totalCases++;

            $amount =
                round(
                    (float) (
                        $row['discrepancy_amount']
                        ?? 0
                    ),
                    2
                );

            $totalAmount +=
                $amount;

            if (
                self::normalizeStatus($row['status'])
                === 'Resolved'
            ) {

                $resolved++;

            } else {

                $pending++;
            }
        }

        return [

            'total_cases' => $totalCases,

            'total_amount' =>
                round(
                    $totalAmount,
                    2
                ),

            'resolved' => $resolved,

            'pending' => $pending
        ];
    }

    /**
     * =========================
     * GET SINGLE RECORD
     * =========================
     */
    public static function getById(
        int $id
    ) {

        $row =
            DiscrepancyModel
                ::getById($id);

        if (!$row) {

            return null;
        }

        return [

            'id' => $row['id'] ?? null,

            'driver_id' => $row['driver_id'] ?? 'N/A',

            'full_name' => $row['full_name'] ?? 'N/A',

            'trip_no' => $row['trip_no'] ?? 'N/A',

            'bus_no' => $row['bus_no'] ?? 'N/A',

            'expected_cash' =>
                round(
                    (float) ($row['expected_cash'] ?? 0),
                    2
                ),

            'actual_cash' =>
                round(
                    (float) ($row['actual_cash'] ?? 0),
                    2
                ),

            'discrepancy_amount' =>
                round(
                    (float) ($row['discrepancy_amount'] ?? 0),
                    2
                ),

            'status' =>
                self::normalizeStatus($row['status']),

            'created_at' => $row['created_at'] ?? null,

            'total_collection' =>
                round(
                    (float) ($row['total_collection'] ?? 0),
                    2
                ),

            'total_passengers' =>
                (int) ($row['total_passengers'] ?? 0),

            'submitted_by' => $row['submitted_by'] ?? null,

            'verified_at' => $row['verified_at'] ?? null,

            'confirmed_by' => $row['confirmed_by'] ?? null,

            'finance_code' => $row['finance_code'] ?? null,

            'resolution_type' => $row['resolution_type'] ?? null,

            'resolution_notes' => $row['resolution_notes'] ?? null,

            'resolved_by' => $row['resolved_by'] ?? null,

            'resolved_at' => $row['resolved_at'] ?? null,

            'supporting_document' => $row['supporting_document'] ?? null
        ];
    }

    /**
     * =========================
     * RESOLVE CASE
     * =========================
     */
    public static function resolve(
        int $id,
        string $type,
        string $notes,
        string $resolvedBy,
        ?string $document = null
    ) {

        return
            DiscrepancyModel
                ::resolve(
                    $id,
                    $type,
                    $notes,
                    $resolvedBy,
                    $document
                );
    }
}
