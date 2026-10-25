<?php

namespace Finance\Services;

require_once __DIR__ . '/DiscrepancyCaseService.php';

/**
 * Shared reporting service for the five distinct discrepancy metrics.
 *
 * Page, PDF, and Excel exports all read from here so the numbers always
 * agree. Metrics are computed in SQL from stored records; client-supplied
 * amounts are never trusted. Effects:
 *  - original discrepancy: sum of shortage snapshots (excess cases excluded)
 *  - recovered:           posted cash + payroll ledger amounts (net of increases)
 *  - approved adjustments: posted adjustments and waivers (net of increases)
 *  - outstanding balance: shortage exposure less posted settlements, never below zero
 *  - case count:          every filtered case, open or closed
 */
class DiscrepancyMetricsService
{
    private \PDO $db;
    private DiscrepancyCaseService $cases;

    public function __construct(?\PDO $db = null, ?DiscrepancyCaseService $cases = null)
    {
        if ($db === null) {
            $db = \App\Core\Database::get();
        }
        $this->db = $db;
        $this->cases = $cases ?? new DiscrepancyCaseService($this->db);
    }

    public function cases(): DiscrepancyCaseService
    {
        return $this->cases;
    }

    /**
     * @return array{original_discrepancy:float,recovered:float,approved_adjustments:float,outstanding_balance:float,case_count:int,open_cases:int,overdue_investigations:int,pending_approvals:int}
     */
    public function summary(array $filters = []): array
    {
        $normalized = $this->cases->normalizeFilters($filters);
        [$where, $params] = $this->cases->model()->buildFilterSql($normalized);

        $today = $normalized['today'] ?? DiscrepancyCaseService::manilaToday();

        $sql = "
            SELECT
                COUNT(*) AS case_count,
                COALESCE(SUM(CASE WHEN c.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END), 0) AS open_cases,
                COALESCE(SUM(CASE
                    WHEN c.due_date IS NOT NULL
                     AND DATE(c.due_date) < :metric_today
                     AND c.status NOT IN ('Resolved', 'Closed')
                    THEN 1 ELSE 0 END), 0) AS overdue_investigations,
                COALESCE(SUM(CASE WHEN c.case_type <> 'Excess Remittance' THEN c.discrepancy_amount ELSE 0 END), 0) AS original_discrepancy,
                COALESCE(SUM(CASE WHEN c.case_type <> 'Excess Remittance' THEN COALESCE(l.recovered, 0) ELSE 0 END), 0) AS recovered,
                COALESCE(SUM(CASE WHEN c.case_type <> 'Excess Remittance' THEN COALESCE(l.adjustments, 0) ELSE 0 END), 0) AS approved_adjustments,
                COALESCE(SUM(CASE
                    WHEN c.case_type = 'Excess Remittance' THEN 0
                    WHEN (c.discrepancy_amount - COALESCE(l.reduce_total, 0) + COALESCE(l.increase_total, 0)) < 0 THEN 0
                    ELSE (c.discrepancy_amount - COALESCE(l.reduce_total, 0) + COALESCE(l.increase_total, 0))
                END), 0) AS outstanding_balance
            FROM discrepancy_cases c
            LEFT JOIN drivers d ON d.driver_id = c.driver_id
            LEFT JOIN (
                SELECT case_id,
                       SUM(CASE WHEN direction = 'increase' THEN -amount ELSE amount END) AS net_total,
                       SUM(CASE WHEN recovery_class IN ('cash', 'payroll')
                                THEN CASE WHEN direction = 'increase' THEN -amount ELSE amount END
                                ELSE 0 END) AS recovered,
                       SUM(CASE WHEN recovery_class = 'adjustment'
                                THEN CASE WHEN direction = 'increase' THEN -amount ELSE amount END
                                ELSE 0 END) AS adjustments,
                       SUM(CASE WHEN direction = 'increase' THEN amount ELSE 0 END) AS increase_total,
                       SUM(CASE WHEN direction <> 'increase' THEN amount ELSE 0 END) AS reduce_total
                FROM discrepancy_ledger_entries
                WHERE state = 'posted'
                GROUP BY case_id
            ) l ON l.case_id = c.id
            $where
        ";

        $stmt = $this->db->prepare($sql);
        $params[':metric_today'] = $today;
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $summary = [
            'original_discrepancy' => round((float) ($row['original_discrepancy'] ?? 0), 2),
            'recovered' => round((float) ($row['recovered'] ?? 0), 2),
            'approved_adjustments' => round((float) ($row['approved_adjustments'] ?? 0), 2),
            'outstanding_balance' => round((float) ($row['outstanding_balance'] ?? 0), 2),
            'case_count' => (int) ($row['case_count'] ?? 0),
            'open_cases' => (int) ($row['open_cases'] ?? 0),
            'overdue_investigations' => (int) ($row['overdue_investigations'] ?? 0),
            'pending_approvals' => $this->pendingApprovalCount($where, $params),
        ];

        return $summary;
    }

    /**
     * Distinct cases among the filtered set with an undecided approval.
     */
    private function pendingApprovalCount(string $where, array $params): int
    {
        unset($params[':metric_today']);

        $sql = "
            SELECT COUNT(DISTINCT c.id)
            FROM discrepancy_cases c
            LEFT JOIN drivers d ON d.driver_id = c.driver_id
            INNER JOIN discrepancy_approvals a
                ON a.case_id = c.id AND a.status = 'pending'
            $where
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('Discrepancy pending approval metric failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Every filtered case (no pagination) for exports.
     */
    public function exportRows(array $filters = [], int $limit = 5000): array
    {
        $normalized = $this->cases->normalizeFilters($filters);
        $rows = $this->cases->model()->listCases($normalized, $limit, 0);

        $ids = array_map(static fn($row) => (int) $row['id'], $rows);
        $postedSums = $this->cases->model()->getPostedSums($ids);
        $pending = $this->cases->model()->getPendingApprovalCaseIds($ids);

        $data = [];
        foreach ($rows as $row) {
            $row['outstanding_balance'] = $this->cases->outstandingFromSums(
                $row,
                $postedSums[(int) $row['id']] ?? ['reduce' => 0.0, 'increase' => 0.0]
            );
            $row['age_days'] = $this->cases->ageDays((string) $row['opened_at']);
            $row['next_action'] = DiscrepancyCaseService::nextAction((string) $row['status']);
            $row['has_pending_approval'] = isset($pending[(int) $row['id']]);
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Human-readable filter description for export headers and audit records.
     */
    public function describeFilters(array $filters): string
    {
        $normalized = $this->cases->normalizeFilters($filters);
        $parts = [];

        if (!empty($normalized['status'])) {
            $statuses = is_array($normalized['status']) ? $normalized['status'] : [$normalized['status']];
            $parts[] = 'Status: ' . implode(', ', $statuses);
        }
        if (!empty($normalized['case_type'])) {
            $types = is_array($normalized['case_type']) ? $normalized['case_type'] : [$normalized['case_type']];
            $parts[] = 'Type: ' . implode(', ', $types);
        }
        if (!empty($normalized['driver'])) {
            $parts[] = 'Driver: ' . $normalized['driver'];
        }
        if (!empty($normalized['business_from']) || !empty($normalized['business_to'])) {
            $parts[] = 'Business date: ' . ($normalized['business_from'] ?: 'any')
                . ' to ' . ($normalized['business_to'] ?: 'any');
        }
        if (!empty($normalized['reviewer'])) {
            $parts[] = 'Reviewer: ' . $normalized['reviewer'];
        }
        if (!empty($filters['age'])) {
            $parts[] = 'Age: ' . $filters['age'] . ' days';
        }
        if (!empty($normalized['overdue_investigation'])) {
            $parts[] = 'Overdue investigations only';
        }

        return $parts ? implode('; ', $parts) : 'All cases';
    }
}
