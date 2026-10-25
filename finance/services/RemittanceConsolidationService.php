<?php

namespace Finance\Services;

/**
 * Consolidates remittance_logs rows so a driver has exactly one remittance per
 * business date.
 *
 * Used in two places:
 *  - HistoryService, so the Remittance History table never renders duplicate
 *    rows even before existing data is repaired;
 *  - finance/tools/consolidate_remittance_duplicates.php, which safely merges
 *    legacy duplicates in the database.
 *
 * Merge rules:
 *  - Overdue placeholder rows never contribute amounts when a finalized row
 *    exists for the same day (they represent the same remittance).
 *  - If the contributing rows carry identical expected/actual amounts they are
 *    treated as true duplicates and counted once; otherwise their amounts are
 *    summed (the old flow could split a day across several records).
 *  - The kept (canonical) record prefers a resolved row, then a finalized row,
 *    then the newest row, so verification and resolution details survive.
 */
final class RemittanceConsolidationService
{
    private const FINALIZED_STATUSES = [
        'completed',
        'discrepancy',
        'resolved',
        'matched',
        'paid',
        'remitted',
    ];

    /**
     * @return array<string,array<int,array>>
     */
    public static function groupRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $driverId = trim((string) ($row['driver_id'] ?? ''));
            $businessDate = self::businessDate($row);

            if ($driverId === '' || $businessDate === '') {
                continue;
            }

            $groups[$driverId . '|' . $businessDate][] = $row;
        }

        return $groups;
    }

    public static function businessDate(array $row): string
    {
        $date = trim((string) ($row['business_date'] ?? ''));
        if ($date !== '') {
            return $date;
        }

        $created = (string) ($row['created_at'] ?? '');
        $timestamp = $created !== '' ? strtotime($created) : false;

        return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
    }

    public static function statusOf(array $row): string
    {
        return strtolower(trim((string) ($row['status'] ?? '')));
    }

    public static function isOverdue(array $row): bool
    {
        return self::statusOf($row) === 'overdue';
    }

    /**
     * Merge one driver + business date group into a single record.
     */
    public static function summarize(array $group): array
    {
        if (empty($group)) {
            return [];
        }

        $canonical = self::canonicalRow($group);

        $contributors = array_values(array_filter(
            $group,
            static fn(array $row): bool => !self::isOverdue($row)
        ));
        if (empty($contributors)) {
            $contributors = array_values($group);
        }

        $identical = self::rowsIdentical($contributors);

        $expected = 0.0;
        $actual = 0.0;
        $discrepancy = 0.0;
        $collection = 0.0;
        $passengers = 0;

        if ($identical) {
            $expected = (float) ($canonical['expected_cash'] ?? 0);
            $actual = (float) ($canonical['actual_cash'] ?? 0);
            $discrepancy = (float) ($canonical['discrepancy_amount'] ?? 0);
            $collection = (float) ($canonical['total_collection'] ?? 0);
            $passengers = (int) ($canonical['total_passengers'] ?? 0);
        } else {
            foreach ($contributors as $row) {
                $expected += (float) ($row['expected_cash'] ?? 0);
                $actual += (float) ($row['actual_cash'] ?? 0);
                $discrepancy += (float) ($row['discrepancy_amount'] ?? 0);
                $collection += (float) ($row['total_collection'] ?? 0);
                $passengers += (int) ($row['total_passengers'] ?? 0);
            }
        }

        $tripNumbers = [];
        foreach ($contributors as $row) {
            $trip = trim((string) ($row['trip_no'] ?? ''));
            if ($trip !== '' && $trip !== 'MULTI') {
                $tripNumbers[$trip] = true;
            }
        }

        $merged = $canonical;
        $merged['business_date'] = self::businessDate($canonical);
        $merged['expected_cash'] = round($expected, 2);
        $merged['actual_cash'] = round($actual, 2);
        $merged['discrepancy_amount'] = round($discrepancy, 2);
        $merged['total_collection'] = round($collection, 2);
        $merged['total_passengers'] = $passengers;
        $merged['trip_no'] = count($tripNumbers) > 1
            ? 'MULTI'
            : (count($tripNumbers) === 1 ? array_key_first($tripNumbers) : (string) ($canonical['trip_no'] ?? ''));
        $merged['consolidated_count'] = count($group);
        $merged['source_ids'] = array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $group
        );

        return $merged;
    }

    private static function canonicalRow(array $group): array
    {
        usort($group, static function (array $a, array $b): int {
            return self::canonicalScore($b) <=> self::canonicalScore($a);
        });

        return $group[0];
    }

    /**
     * @return array<int,int>
     */
    private static function canonicalScore(array $row): array
    {
        $status = self::statusOf($row);

        return [
            $status === 'resolved' ? 1 : 0,
            in_array($status, self::FINALIZED_STATUSES, true) ? 1 : 0,
            self::isOverdue($row) ? 0 : 1,
            ($row['actual_cash'] ?? null) !== null ? 1 : 0,
            (int) ($row['id'] ?? 0),
        ];
    }

    private static function rowsIdentical(array $rows): bool
    {
        if (count($rows) < 2) {
            return true;
        }

        $signatures = [];
        foreach ($rows as $row) {
            $signatures[] = number_format((float) ($row['expected_cash'] ?? 0), 2, '.', '')
                . '|' . number_format((float) ($row['actual_cash'] ?? 0), 2, '.', '');
        }

        return count(array_unique($signatures)) === 1;
    }
}
