<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Models\AuditTrailModel;

/**
 * Case lifecycle for discrepancy investigations.
 *
 * Owns creation from remittance records, the status transition map, reviewer
 * assignment, findings, evidence, case-details assembly, filtering, and the
 * legacy backfill. Amount snapshots are immutable once asserted: resolution
 * activity belongs to the ledger service and never rewrites them.
 */
class DiscrepancyCaseService
{
    public const DEFAULT_INVESTIGATION_DAYS = 3;

    public const TRANSITIONS = [
        DiscrepancyCaseModel::STATUS_OPEN => [
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ],
        DiscrepancyCaseModel::STATUS_INVESTIGATING => [
            DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE,
            DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
            DiscrepancyCaseModel::STATUS_APPROVAL,
            DiscrepancyCaseModel::STATUS_RECOVERY,
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ],
        DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE => [
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
            DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
            DiscrepancyCaseModel::STATUS_APPROVAL,
            DiscrepancyCaseModel::STATUS_RECOVERY,
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ],
        DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED => [
            DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL,
            DiscrepancyCaseModel::STATUS_RETURNED,
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
        ],
        DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL => [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
            DiscrepancyCaseModel::STATUS_RETURNED,
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
        ],
        DiscrepancyCaseModel::STATUS_RETURNED => [
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
            DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
        ],
        DiscrepancyCaseModel::STATUS_APPROVAL => [
            DiscrepancyCaseModel::STATUS_RECOVERY,
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ],
        DiscrepancyCaseModel::STATUS_RECOVERY => [
            DiscrepancyCaseModel::STATUS_APPROVAL,
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ],
        DiscrepancyCaseModel::STATUS_RESOLVED => [
            DiscrepancyCaseModel::STATUS_CLOSED,
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
        ],
        DiscrepancyCaseModel::STATUS_CLOSED => [
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
        ],
    ];

    public static function isManagerRole(?string $role): bool
    {
        return $role !== null && in_array($role, DiscrepancyCaseModel::MANAGER_ROLES, true);
    }

    public static function isInvestigatorRole(?string $role): bool
    {
        return $role !== null && in_array($role, DiscrepancyCaseModel::INVESTIGATOR_ROLES, true);
    }

    /**
     * Role separation for the investigation approval workflow:
     *  - only managers resolve, close, decide submitted findings, or reopen;
     *  - only investigators submit findings;
     *  - officers can never approve their own or anyone else's resolution.
     */
    public function assertRoleCanTransition(string $from, string $to, ?string $role): void
    {
        $managerTargets = [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ];
        $managerOnlySources = [
            DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
            DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL,
        ];
        $finalized = [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ];

        if (in_array($to, $managerTargets, true) && !self::isManagerRole($role)) {
            throw new \UnexpectedValueException('Only a Finance Manager can resolve or close a case.');
        }

        if (in_array($from, $managerOnlySources, true) && !self::isManagerRole($role)) {
            throw new \UnexpectedValueException('Only a Finance Manager can review submitted findings or record the approval decision.');
        }

        if (in_array($from, $finalized, true) && !self::isManagerRole($role)) {
            throw new \UnexpectedValueException('Only a Finance Manager can reopen or amend a finalized case.');
        }

        if ($to === DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED && $from !== DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED) {
            if (!self::isInvestigatorRole($role)) {
                throw new \UnexpectedValueException('Only a Finance Officer or Manager can submit investigation findings.');
            }
        }
    }

    private \PDO $db;
    private DiscrepancyCaseModel $cases;
    private static bool $schemaEnsured = false;

    public function __construct(?\PDO $db = null, ?DiscrepancyCaseModel $cases = null)
    {
        if ($db === null) {
            $db = \App\Core\Database::get();
        }
        $this->db = $db;
        $this->cases = $cases ?? new DiscrepancyCaseModel($this->db);
        $this->ensureSchema();
    }

    public function model(): DiscrepancyCaseModel
    {
        return $this->cases;
    }

    public function db(): \PDO
    {
        return $this->db;
    }

    public static function manilaNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
    }

    public static function manilaToday(): string
    {
        return self::manilaNow()->format('Y-m-d');
    }

    /**
     * Resolve the acting user from the session unless one was supplied.
     * Backend-trusted: request input never reaches this value directly.
     */
    public static function currentActor(?array $actor = null): array
    {
        if ($actor !== null) {
            return [
                'id' => isset($actor['id']) ? (int) $actor['id'] : null,
                'username' => (string) ($actor['username'] ?? 'System'),
                'role' => $actor['role'] ?? null,
            ];
        }

        $user = $_SESSION['user'] ?? [];
        return [
            'id' => isset($user['id']) ? (int) $user['id'] : null,
            'username' => (string) ($user['username'] ?? 'System'),
            'role' => $user['role'] ?? null,
        ];
    }

    /**
     * Create the additive workflow schema when missing. Runs from the service
     * constructor so no entry point (metrics, list, details, ledger) can query
     * the tables before they exist. Public for explicit calls and tests.
     */
    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $this->cases->ensureResolutionColumns($this->db);
        $this->cases->initTables($this->db);

        // Only cache success once every workflow table is actually visible, so
        // a failed or partial migration is retried instead of being masked by
        // the static flag.
        $required = [
            'discrepancy_cases',
            'discrepancy_status_history',
            'discrepancy_evidence',
            'discrepancy_ledger_entries',
            'discrepancy_approvals',
        ];
        foreach ($required as $table) {
            if (!$this->tableExists($table)) {
                if ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    error_log(
                        'Discrepancy workflow table ' . $table . ' is missing. Apply '
                        . 'finance/database/migrations/004_discrepancy_resolution_columns.sql and '
                        . 'finance/database/migrations/005_discrepancy_workflow_tables.sql, or run '
                        . 'php finance/tools/backfill_discrepancy_cases.php with a user that can create tables.'
                    );
                }
                return;
            }
        }

        self::$schemaEnsured = true;
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                return (int) $stmt->fetchColumn() > 0;
            }

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* =========================================================
     * STATUS MODEL
     * ========================================================= */

    public function assertTransition(string $from, string $to, ?string $reason = null): void
    {
        if (!in_array($to, DiscrepancyCaseModel::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown case status: ' . $to);
        }

        if ($from === $to) {
            throw new \InvalidArgumentException('The case is already in status ' . $to . '.');
        }

        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \InvalidArgumentException(
                'Invalid status transition from ' . $from . ' to ' . $to . '.'
            );
        }

        if ($this->reasonRequired($from, $to) && trim((string) $reason) === '') {
            throw new \InvalidArgumentException(
                'A reason is required to move a case to ' . $to . ' or out of Resolved or Closed.'
            );
        }
    }

    public function reasonRequired(string $from, string $to): bool
    {
        $significantTargets = [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ];
        $significantSources = [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ];

        return in_array($to, $significantTargets, true) || in_array($from, $significantSources, true);
    }

    public static function nextAction(string $status): string
    {
        switch ($status) {
            case DiscrepancyCaseModel::STATUS_OPEN:
                return 'Assign a reviewer and investigate';
            case DiscrepancyCaseModel::STATUS_INVESTIGATING:
                return 'Record findings';
            case DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE:
                return 'Follow up on the driver response';
            case DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED:
                return 'Manager review of submitted findings';
            case DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL:
                return 'Manager approval decision';
            case DiscrepancyCaseModel::STATUS_RETURNED:
                return 'Continue the investigation';
            case DiscrepancyCaseModel::STATUS_APPROVAL:
                return 'Await approval decision';
            case DiscrepancyCaseModel::STATUS_RECOVERY:
                return 'Post recovery or settlement';
            case DiscrepancyCaseModel::STATUS_RESOLVED:
                return 'Close the case';
            case DiscrepancyCaseModel::STATUS_CLOSED:
            default:
                return 'No action required';
        }
    }

    /* =========================================================
     * CREATION FROM REMITTANCE
     * ========================================================= */

    /**
     * Create or update the case for one remittance log.
     *
     * Shortage/excess rows create a case; overdue rows create an Overdue
     * Remittance case with no asserted amount; a later late remittance
     * establishes the mismatch on the same case (original business date
     * preserved) or resolves it when the late remittance is balanced.
     *
     * @return array{created:bool,case_id:?int,case:?array,reason?:string}
     */
    public function syncFromRemittance(int $remittanceLogId, array $snapshot): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($snapshot['actor'] ?? null);
        $existing = $this->cases->findByRemittanceLogId($remittanceLogId);

        $expected = round((float) ($snapshot['expected_amount'] ?? 0), 2);
        $actual = array_key_exists('actual_amount', $snapshot) && $snapshot['actual_amount'] !== null
            ? round((float) $snapshot['actual_amount'], 2)
            : null;

        if ($existing === null) {
            $snapshot['remittance_log_id'] = $remittanceLogId;

            if ($actual === null) {
                return $this->createCase(array_merge($snapshot, [
                    'actual_amount' => null,
                    'expected_amount' => $expected,
                ]), $actor);
            }

            if ($this->isBalanced($expected, $actual)) {
                return ['created' => false, 'case_id' => null, 'case' => null, 'reason' => 'balanced'];
            }

            return $this->createCase(array_merge($snapshot, [
                'actual_amount' => $actual,
                'expected_amount' => $expected,
            ]), $actor);
        }

        if ($existing['actual_amount'] === null) {
            if ($actual === null) {
                return ['created' => false, 'case_id' => (int) $existing['id'], 'case' => $existing, 'reason' => 'unchanged'];
            }

            if ($this->isBalanced($expected, $actual)) {
                $reason = 'Late remittance reconciled balanced against the original business date '
                    . (string) ($snapshot['business_date'] ?? $existing['business_date']) . '.';
                $this->cases->updateCase((int) $existing['id'], [
                    'resolution_reason' => $reason,
                ]);
                // Automated reconciliation, not a manager decision: the system
                // closes out an overdue case when the late remittance balances.
                $case = $this->transition(
                    (int) $existing['id'],
                    DiscrepancyCaseModel::STATUS_RESOLVED,
                    $reason,
                    ['id' => null, 'username' => 'System', 'role' => 'finance_manager']
                );
                return ['created' => false, 'case_id' => (int) $existing['id'], 'case' => $case, 'reason' => 'late_balanced'];
            }

            [$type, $discrepancy, $excess] = $this->classifyAmounts($expected, $actual);
            $this->cases->applyMismatch((int) $existing['id'], [
                'expected_amount' => $expected,
                'actual_amount' => $actual,
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => $excess,
            ]);
            $this->cases->updateCase((int) $existing['id'], ['case_type' => $type]);

            $reason = 'Late remittance processed against the original business date '
                . (string) ($snapshot['business_date'] ?? $existing['business_date']) . '.';
            $this->cases->insertHistory([
                'case_id' => (int) $existing['id'],
                'from_status' => $existing['status'],
                'to_status' => $existing['status'],
                'event_type' => 'mismatch_established',
                'description' => 'Mismatch established as ' . $type . '.',
                'reason' => $reason,
                'actor_id' => $actor['id'],
                'actor_username' => $actor['username'],
                'actor_role' => $actor['role'],
            ]);
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Mismatch Established',
                $reason,
                $this->refFor((int) $existing['id']),
                [
                    'case_type' => $existing['case_type'],
                    'expected_amount' => $existing['expected_amount'],
                    'actual_amount' => $existing['actual_amount'],
                ],
                [
                    'case_type' => $type,
                    'expected_amount' => $expected,
                    'actual_amount' => $actual,
                    'discrepancy_amount' => $discrepancy,
                    'excess_amount' => $excess,
                ],
                'Success',
                $this->db
            );

            return [
                'created' => false,
                'case_id' => (int) $existing['id'],
                'case' => $this->cases->getById((int) $existing['id']),
                'reason' => 'mismatch_established',
            ];
        }

        return [
            'created' => false,
            'case_id' => (int) $existing['id'],
            'case' => $existing,
            'reason' => 'exists',
        ];
    }

    /**
     * Force-create a case (used by sync and the backfill tool). A balanced
     * remittance with no asserted amount is rejected: it is not a case.
     */
    public function createCase(array $snapshot, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = is_array($actor) ? $actor : self::currentActor($actor);

        $expected = round((float) ($snapshot['expected_amount'] ?? 0), 2);
        $actual = array_key_exists('actual_amount', $snapshot) && $snapshot['actual_amount'] !== null
            ? round((float) $snapshot['actual_amount'], 2)
            : null;

        if ($actual === null) {
            $type = DiscrepancyCaseModel::TYPE_OVERDUE;
            $discrepancy = 0.0;
            $excess = 0.0;
        } else {
            if ($this->isBalanced($expected, $actual)) {
                return ['created' => false, 'case_id' => null, 'case' => null, 'reason' => 'balanced'];
            }
            [$type, $discrepancy, $excess] = $this->classifyAmounts($expected, $actual);
        }

        $remittanceLogId = (int) ($snapshot['remittance_log_id'] ?? 0);
        if ($remittanceLogId <= 0) {
            throw new \InvalidArgumentException('A case requires a remittance log id.');
        }

        $now = self::manilaNow();
        $openedBy = (string) ($snapshot['opened_by'] ?? $actor['username']);
        $status = DiscrepancyCaseModel::STATUS_OPEN;

        try {
            $id = $this->cases->insertCase([
                'remittance_log_id' => $remittanceLogId,
                'driver_id' => (string) ($snapshot['driver_id'] ?? ''),
                'business_date' => $snapshot['business_date'] ?? null,
                'case_type' => $type,
                'status' => $status,
                'expected_amount' => $expected,
                'actual_amount' => $actual,
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => $excess,
                'due_date' => $now->modify('+' . self::DEFAULT_INVESTIGATION_DAYS . ' days')->format('Y-m-d'),
                'opened_by' => $openedBy,
                'opened_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $existing = $this->cases->findByRemittanceLogId($remittanceLogId);
            if ($existing) {
                return ['created' => false, 'case_id' => (int) $existing['id'], 'case' => $existing, 'reason' => 'exists'];
            }
            throw $e;
        }

        $ref = $this->refFor($id);
        $this->cases->updateCase($id, ['case_ref' => $ref]);

        $this->cases->insertHistory([
            'case_id' => $id,
            'from_status' => null,
            'to_status' => $status,
            'event_type' => 'case_opened',
            'description' => 'Case opened as ' . $type . '.',
            'reason' => $snapshot['reason'] ?? null,
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Case Opened',
            'Opened ' . $type . ' case for driver ' . ($snapshot['driver_id'] ?? '') . ' on ' . ($snapshot['business_date'] ?? ''),
            $ref,
            null,
            [
                'case_type' => $type,
                'expected_amount' => $expected,
                'actual_amount' => $actual,
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => $excess,
                'status' => $status,
            ],
            'Success',
            $this->db
        );

        return [
            'created' => true,
            'case_id' => $id,
            'case' => $this->cases->getById($id),
            'reason' => 'created',
        ];
    }

    private function classifyAmounts(float $expected, float $actual): array
    {
        if ($actual > $expected) {
            return [DiscrepancyCaseModel::TYPE_EXCESS, 0.0, round($actual - $expected, 2)];
        }
        return [DiscrepancyCaseModel::TYPE_SHORTAGE, round($expected - $actual, 2), 0.0];
    }

    private function isBalanced(float $expected, float $actual): bool
    {
        return number_format($expected, 2, '.', '') === number_format($actual, 2, '.', '');
    }

    public function refFor(int $caseId): string
    {
        return 'DIS-' . str_pad((string) $caseId, 5, '0', STR_PAD_LEFT);
    }

    /* =========================================================
     * LIFECYCLE ACTIONS
     * ========================================================= */

    public function transition(int $caseId, string $toStatus, ?string $reason, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);
        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        $from = (string) $case['status'];
        $this->assertTransition($from, $toStatus, $reason);
        $this->assertRoleCanTransition($from, $toStatus, $actor['role']);

        $fields = ['status' => $toStatus];
        if ($toStatus === DiscrepancyCaseModel::STATUS_RESOLVED) {
            $fields['resolved_at'] = self::manilaNow()->format('Y-m-d H:i:s');
            if (trim((string) $reason) !== '') {
                $fields['resolution_reason'] = (string) $reason;
            }
        }
        if ($toStatus === DiscrepancyCaseModel::STATUS_CLOSED) {
            $fields['closed_at'] = self::manilaNow()->format('Y-m-d H:i:s');
        }
        $this->cases->updateCase($caseId, $fields);

        $reopening = in_array($from, [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ], true) && !in_array($toStatus, [
            DiscrepancyCaseModel::STATUS_RESOLVED,
            DiscrepancyCaseModel::STATUS_CLOSED,
        ], true);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $from,
            'to_status' => $toStatus,
            'event_type' => $reopening ? 'reopened' : 'status_change',
            'description' => 'Status changed from ' . $from . ' to ' . $toStatus . '.',
            'reason' => $reason,
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            $reopening ? 'Discrepancy Case Reopened' : 'Discrepancy Status Changed',
            'Case status changed from ' . $from . ' to ' . $toStatus . '.',
            $this->refFor($caseId),
            ['status' => $from],
            ['status' => $toStatus, 'reason' => $reason],
            'Success',
            $this->db
        );

        $this->mirrorStatusToRemittance($case, $toStatus, $reason, $actor, $reopening);

        return $this->cases->getById($caseId);
    }

    /**
     * Keep the legacy remittance_logs status and resolution columns in sync so
     * existing dashboards, analytics, and history views keep working while
     * discrepancy_cases stays the workflow source of truth.
     *
     * Resolved/Closed mirrors to the log's historical 'Resolved' state and
     * fills the legacy resolution columns; reopening restores the log's
     * pre-resolution state (Overdue for an unprocessed overdue case, otherwise
     * Discrepancy). A balanced late remittance keeps its final 'Completed'
     * status untouched.
     */
    private function mirrorStatusToRemittance(
        array $case,
        string $toStatus,
        ?string $reason,
        array $actor,
        bool $reopening
    ): void {
        $logId = (int) ($case['remittance_log_id'] ?? 0);
        if ($logId <= 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT status FROM remittance_logs WHERE id = ? LIMIT 1");
            $stmt->execute([$logId]);
            $current = strtolower(trim((string) $stmt->fetchColumn()));

            if ($reopening) {
                if ($current !== 'resolved') {
                    return;
                }
                $restored = ($case['case_type'] === DiscrepancyCaseModel::TYPE_OVERDUE && $case['actual_amount'] === null)
                    ? 'Overdue'
                    : 'Discrepancy';
                $update = $this->db->prepare("
                    UPDATE remittance_logs
                    SET status = ?,
                        resolution_type = NULL,
                        resolution_notes = NULL,
                        resolved_by = NULL,
                        resolved_at = NULL
                    WHERE id = ?
                ");
                $update->execute([$restored, $logId]);
                return;
            }

            if (!in_array($toStatus, [
                DiscrepancyCaseModel::STATUS_RESOLVED,
                DiscrepancyCaseModel::STATUS_CLOSED,
            ], true)) {
                return;
            }

            if (!in_array($current, ['discrepancy', 'overdue', 'resolved'], true)) {
                return;
            }

            if ($current === 'resolved') {
                return;
            }

            $update = $this->db->prepare("
                UPDATE remittance_logs
                SET status = 'Resolved',
                    resolution_type = ?,
                    resolution_notes = ?,
                    resolved_by = ?,
                    resolved_at = ?
                WHERE id = ?
            ");
            $update->execute([
                $case['case_type'] ?? null,
                trim((string) ($reason ?? '')) !== '' ? (string) $reason : ($case['resolution_reason'] ?? null),
                $actor['username'] ?? 'System',
                self::manilaNow()->format('Y-m-d H:i:s'),
                $logId,
            ]);
        } catch (\Throwable $e) {
            // The legacy columns may be unavailable; the case remains the
            // source of truth and the workflow is unaffected.
            error_log('Discrepancy legacy mirror failed for case ' . ($case['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }

    public function reclassify(int $caseId, string $caseType, string $reason, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);

        if (!in_array($caseType, DiscrepancyCaseModel::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown discrepancy type: ' . $caseType);
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to reclassify a case.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }
        if ($case['case_type'] === $caseType) {
            throw new \InvalidArgumentException('The case is already classified as ' . $caseType . '.');
        }

        $this->cases->updateCase($caseId, ['case_type' => $caseType]);
        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $case['status'],
            'to_status' => $case['status'],
            'event_type' => 'reclassified',
            'description' => 'Type changed from ' . $case['case_type'] . ' to ' . $caseType . '.',
            'reason' => $reason,
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Case Reclassified',
            'Case reclassified from ' . $case['case_type'] . ' to ' . $caseType . '.',
            $this->refFor($caseId),
            ['case_type' => $case['case_type']],
            ['case_type' => $caseType, 'reason' => $reason],
            'Success',
            $this->db
        );

        return $this->cases->getById($caseId);
    }

    public function assign(int $caseId, string $reviewer, ?string $dueDate = null, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);
        $reviewer = trim($reviewer);
        if ($reviewer === '') {
            throw new \InvalidArgumentException('A reviewer is required.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        $dueDate = trim((string) $dueDate);
        if ($dueDate !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', $dueDate);
            if (!$parsed || $parsed->format('Y-m-d') !== $dueDate) {
                throw new \InvalidArgumentException('Invalid due date.');
            }
        } else {
            $dueDate = $case['due_date'] ?: self::manilaNow()
                ->modify('+' . self::DEFAULT_INVESTIGATION_DAYS . ' days')->format('Y-m-d');
        }

        $this->cases->updateCase($caseId, [
            'assigned_reviewer' => $reviewer,
            'assigned_at' => self::manilaNow()->format('Y-m-d H:i:s'),
            'due_date' => $dueDate,
        ]);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $case['status'],
            'to_status' => $case['status'],
            'event_type' => 'assigned',
            'description' => 'Assigned to ' . $reviewer . ' with due date ' . $dueDate . '.',
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Case Assigned',
            'Case assigned to ' . $reviewer . ' (due ' . $dueDate . ').',
            $this->refFor($caseId),
            ['assigned_reviewer' => $case['assigned_reviewer'], 'due_date' => $case['due_date']],
            ['assigned_reviewer' => $reviewer, 'due_date' => $dueDate],
            'Success',
            $this->db
        );

        if ($case['status'] === DiscrepancyCaseModel::STATUS_OPEN) {
            return $this->transition(
                $caseId,
                DiscrepancyCaseModel::STATUS_INVESTIGATING,
                'Reviewer assigned.',
                $actor
            );
        }

        return $this->cases->getById($caseId);
    }

    public function saveFindings(int $caseId, string $findings, ?string $reason = null, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);
        $findings = trim($findings);
        if ($findings === '') {
            throw new \InvalidArgumentException('Investigation findings are required.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        $this->cases->updateCase($caseId, [
            'findings' => $findings,
            'resolution_reason' => trim((string) $reason) !== '' ? trim((string) $reason) : $case['resolution_reason'],
        ]);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $case['status'],
            'to_status' => $case['status'],
            'event_type' => 'findings',
            'description' => $findings,
            'reason' => $reason,
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Findings Recorded',
            'Findings recorded for case ' . $this->refFor($caseId) . '.',
            $this->refFor($caseId),
            ['findings' => $case['findings']],
            ['findings' => $findings, 'resolution_reason' => $reason],
            'Success',
            $this->db
        );

        if ($case['status'] === DiscrepancyCaseModel::STATUS_OPEN) {
            return $this->transition(
                $caseId,
                DiscrepancyCaseModel::STATUS_INVESTIGATING,
                'Investigation started.',
                $actor
            );
        }

        return $this->cases->getById($caseId);
    }

    /**
     * Finance Officer submits completed findings and a recommended resolution
     * for manager review. The officer cannot approve or finalize anything.
     */
    public function submitFindingsForApproval(
        int $caseId,
        string $findings,
        string $recommendation,
        string $remarks = '',
        ?array $actor = null
    ): array {
        $this->ensureSchema();
        $actor = self::currentActor($actor);

        if (!self::isInvestigatorRole($actor['role'])) {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Findings Submitted',
                'Refused: role ' . ($actor['role'] ?? 'unknown') . ' may not submit findings.',
                $this->refFor($caseId),
                null,
                null,
                'Failed',
                $this->db
            );
            throw new \UnexpectedValueException('Your role is not authorized to submit investigation findings.');
        }

        $findings = trim($findings);
        $recommendation = trim($recommendation);
        $remarks = trim($remarks);

        if ($findings === '') {
            throw new \InvalidArgumentException('Investigation findings are required before submission.');
        }
        if ($recommendation === '') {
            throw new \InvalidArgumentException('A recommended resolution is required before submission.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        $submittable = [
            DiscrepancyCaseModel::STATUS_INVESTIGATING,
            DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE,
            DiscrepancyCaseModel::STATUS_RETURNED,
        ];
        if (!in_array($case['status'], $submittable, true)) {
            throw new \InvalidArgumentException(
                'Findings can only be submitted from Under Investigation, Awaiting Driver Response, or Returned for Further Investigation.'
            );
        }

        $now = self::manilaNow()->format('Y-m-d H:i:s');

        $this->cases->updateCase($caseId, [
            'status' => DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
            'findings' => $findings,
            'resolution_reason' => $recommendation,
            'findings_submitted_by' => $actor['username'],
            'findings_submitted_at' => $now,
            'manager_decision' => null,
            'manager_decision_by' => null,
            'manager_decision_at' => null,
            'manager_decision_reason' => null,
        ]);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $case['status'],
            'to_status' => DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
            'event_type' => 'findings_submitted',
            'description' => 'Findings submitted for manager review.',
            'reason' => $recommendation . ($remarks !== '' ? ' — ' . $remarks : ''),
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Findings Submitted',
            'Findings and recommended resolution submitted for manager review.',
            $this->refFor($caseId),
            ['status' => $case['status']],
            [
                'status' => DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
                'recommendation' => $recommendation,
                'remarks' => $remarks,
                'submitted_by' => $actor['username'],
                'submitted_at' => $now,
            ],
            'Success',
            $this->db
        );

        return $this->cases->getById($caseId);
    }

    /**
     * Finance Manager records the final decision on submitted findings:
     * approve (finalize as Resolved), return, or reject the recommendation
     * (both return the case for further investigation). Managers may not
     * decide findings they submitted themselves.
     */
    public function managerDecision(int $caseId, string $decision, string $reason, ?array $actor = null): array
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);
        $decision = strtolower(trim($decision));
        $reason = trim($reason);

        if (!in_array($decision, ['approved', 'returned', 'rejected'], true)) {
            throw new \InvalidArgumentException('The decision must be approved, returned, or rejected.');
        }
        if ($reason === '') {
            throw new \InvalidArgumentException('A documented reason is required for every approval decision.');
        }

        if (!self::isManagerRole($actor['role'])) {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Manager ' . ucfirst($decision),
                'Refused: role ' . ($actor['role'] ?? 'unknown') . ' may not decide submitted findings.',
                $this->refFor($caseId),
                null,
                null,
                'Failed',
                $this->db
            );
            throw new \UnexpectedValueException('Only a Finance Manager can decide on submitted findings.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }
        if ($case['status'] !== DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL) {
            throw new \InvalidArgumentException('Approval decisions can only be recorded while the case is Pending Manager Approval.');
        }
        if ((string) ($case['findings_submitted_by'] ?? '') === $actor['username']) {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Manager ' . ucfirst($decision),
                'Refused: self-approval is not allowed for ' . $this->refFor($caseId) . '.',
                $this->refFor($caseId),
                null,
                ['decision' => $decision],
                'Failed',
                $this->db
            );
            throw new \UnexpectedValueException('You cannot decide on findings you submitted yourself.');
        }

        $now = self::manilaNow()->format('Y-m-d H:i:s');
        $target = $decision === 'approved'
            ? DiscrepancyCaseModel::STATUS_RESOLVED
            : DiscrepancyCaseModel::STATUS_RETURNED;

        $fields = [
            'status' => $target,
            'manager_decision' => $decision,
            'manager_decision_by' => $actor['username'],
            'manager_decision_at' => $now,
            'manager_decision_reason' => $reason,
        ];
        if ($target === DiscrepancyCaseModel::STATUS_RESOLVED) {
            $fields['resolved_at'] = $now;
            $fields['resolution_reason'] = $reason;
        }
        $this->cases->updateCase($caseId, $fields);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL,
            'to_status' => $target,
            'event_type' => 'manager_decision',
            'description' => 'Manager decision: ' . $decision . '.',
            'reason' => $reason,
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Manager ' . ucfirst($decision),
            'Manager ' . $decision . ' the submitted findings: ' . $reason,
            $this->refFor($caseId),
            ['status' => $case['status'], 'decision' => $case['manager_decision']],
            [
                'status' => $target,
                'decision' => $decision,
                'reason' => $reason,
                'approver' => $actor['username'],
                'decided_at' => $now,
                'findings_submitted_by' => $case['findings_submitted_by'],
            ],
            'Success',
            $this->db
        );

        $this->mirrorStatusToRemittance($case, $target, $reason, $actor, false);

        return $this->cases->getById($caseId);
    }

    /**
     * Append evidence to a case with uploader metadata.
     */
    public function recordEvidence(int $caseId, string $filePath, array $meta, ?array $actor = null): int
    {
        $this->ensureSchema();
        $actor = self::currentActor($actor);
        $filePath = trim($filePath);

        if ($filePath === '') {
            throw new \InvalidArgumentException('Evidence file path is required.');
        }

        $case = $this->cases->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        $id = $this->cases->insertEvidence([
            'case_id' => $caseId,
            'file_path' => $filePath,
            'original_name' => $meta['original_name'] ?? null,
            'mime' => $meta['mime'] ?? null,
            'size' => $meta['size'] ?? null,
            'uploaded_by' => $actor['username'],
        ]);

        $this->cases->insertHistory([
            'case_id' => $caseId,
            'from_status' => $case['status'],
            'to_status' => $case['status'],
            'event_type' => 'evidence',
            'description' => 'Evidence uploaded: ' . ($meta['original_name'] ?? $filePath),
            'actor_id' => $actor['id'],
            'actor_username' => $actor['username'],
            'actor_role' => $actor['role'],
        ]);

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Evidence Uploaded',
            'Evidence uploaded for case ' . $this->refFor($caseId) . ': ' . ($meta['original_name'] ?? $filePath),
            $this->refFor($caseId),
            null,
            ['file' => $filePath, 'mime' => $meta['mime'] ?? null, 'size' => $meta['size'] ?? null],
            'Success',
            $this->db
        );

        return $id;
    }

    /**
     * Validate an uploaded evidence file. PDF/PNG/JPG, max 5 MB, matching the
     * existing discrepancy document rules.
     */
    public function validateEvidenceFile(array $file): string
    {
        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
        ];

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = $tmp !== '' && is_file($tmp) ? (mime_content_type($tmp) ?: '') : (string) ($file['type'] ?? '');

        if (!isset($allowedMimes[$mime])) {
            throw new \InvalidArgumentException('Only PDF, PNG, or JPG files are allowed.');
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Supporting document must be 5MB or smaller.');
        }

        return $allowedMimes[$mime];
    }

    /**
     * Move a validated upload into the discrepancy evidence directory.
     */
    public function storeEvidenceFile(array $file): string
    {
        $extension = $this->validateEvidenceFile($file);

        $dir = __DIR__ . '/../uploads/discrepancy-documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = 'disc-' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Unable to save the supporting document.');
        }

        return 'uploads/discrepancy-documents/' . $name;
    }

    /* =========================================================
     * DETAILS, LISTING, TIMELINE
     * ========================================================= */

    public function getDetails(int $caseId, ?string $role = null): ?array
    {
        $case = $this->cases->getById($caseId);
        if (!$case) {
            return null;
        }

        $remittance = $this->cases->getRemittanceLog((int) $case['remittance_log_id']);
        $ledger = $this->cases->getLedgerEntries($caseId);
        $approvals = $this->cases->getApprovals($caseId);

        $case['driver_name'] = null;
        try {
            $stmt = $this->db->prepare("SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1");
            $stmt->execute([$case['driver_id']]);
            $case['driver_name'] = $stmt->fetchColumn() ?: null;
        } catch (\Throwable $e) {
            // drivers table may be unavailable in restricted environments
        }

        $expected = (float) $case['expected_amount'];
        $actual = $case['actual_amount'] !== null ? (float) $case['actual_amount'] : null;

        $outstanding = $this->outstandingFromEntries($case, $ledger);

        $isOverdueInvestigation = $case['due_date'] !== null
            && $case['due_date'] < self::manilaToday()
            && !in_array($case['status'], [
                DiscrepancyCaseModel::STATUS_RESOLVED,
                DiscrepancyCaseModel::STATUS_CLOSED,
            ], true);

        $hasPendingApproval = false;
        foreach ($approvals as $approval) {
            if (($approval['status'] ?? '') === 'pending') {
                $hasPendingApproval = true;
                break;
            }
        }

        return [
            'case' => $case,
            'remittance' => $remittance ? [
                'id' => (int) $remittance['id'],
                'ref' => 'RM-' . str_pad((string) $remittance['id'], 5, '0', STR_PAD_LEFT),
                'business_date' => $remittance['business_date'] ?? null,
                'status' => $remittance['status'] ?? null,
                'expected_cash' => round((float) ($remittance['expected_cash'] ?? 0), 2),
                'actual_cash' => $remittance['actual_cash'] !== null ? round((float) $remittance['actual_cash'], 2) : null,
                'discrepancy_amount' => round((float) ($remittance['discrepancy_amount'] ?? 0), 2),
                'total_collection' => round((float) ($remittance['total_collection'] ?? 0), 2),
                'total_passengers' => (int) ($remittance['total_passengers'] ?? 0),
                'trip_no' => $remittance['trip_no'] ?? null,
                'submitted_by' => $remittance['submitted_by'] ?? null,
                'confirmed_by' => $remittance['confirmed_by'] ?? null,
                'created_at' => $remittance['created_at'] ?? null,
                'resolution_type' => $remittance['resolution_type'] ?? null,
                'resolution_notes' => $remittance['resolution_notes'] ?? null,
                'resolved_by' => $remittance['resolved_by'] ?? null,
                'resolved_at' => $remittance['resolved_at'] ?? null,
                'supporting_document' => $remittance['supporting_document'] ?? null,
            ] : null,
            'calculation' => [
                'expected_amount' => round($expected, 2),
                'actual_amount' => $actual !== null ? round($actual, 2) : null,
                'shortage' => round((float) $case['discrepancy_amount'], 2),
                'excess' => round((float) $case['excess_amount'], 2),
                'outstanding_balance' => $outstanding,
            ],
            'trips' => $this->cases->getTripBreakdown((string) $case['driver_id'], (string) $case['business_date']),
            'payment_references' => $this->cases->getPaymentReferences((string) $case['driver_id'], (string) $case['business_date']),
            'evidence' => $this->cases->getEvidence($caseId),
            'ledger' => $ledger,
            'approvals' => $approvals,
            'history' => $this->cases->getHistory($caseId),
            'timeline' => $this->buildTimeline($caseId),
            'age_days' => $this->ageDays((string) $case['opened_at']),
            'next_action' => self::nextAction((string) $case['status']),
            'is_overdue_investigation' => $isOverdueInvestigation,
            'has_pending_approval' => $hasPendingApproval,
            'allowed_transitions' => $this->allowedTransitionsFor($case, $role),
            'workflow_permissions' => [
                'role' => $role,
                'is_manager' => self::isManagerRole($role),
                'is_investigator' => self::isInvestigatorRole($role),
                'can_submit_findings' => self::isInvestigatorRole($role) && in_array($case['status'], [
                    DiscrepancyCaseModel::STATUS_INVESTIGATING,
                    DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE,
                    DiscrepancyCaseModel::STATUS_RETURNED,
                ], true),
                'can_start_manager_review' => self::isManagerRole($role)
                    && $case['status'] === DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED,
                'can_decide' => self::isManagerRole($role)
                    && $case['status'] === DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL
                    && (string) ($case['findings_submitted_by'] ?? '') !== (self::currentActor()['username'] ?? ''),
                'is_submitter' => (string) ($case['findings_submitted_by'] ?? '') !== ''
                    && (string) ($case['findings_submitted_by'] ?? '') === (self::currentActor()['username'] ?? ''),
                'findings_submitted_by' => $case['findings_submitted_by'] ?? null,
                'manager_decision' => $case['manager_decision'] ?? null,
                'manager_decision_by' => $case['manager_decision_by'] ?? null,
                'manager_decision_at' => $case['manager_decision_at'] ?? null,
                'manager_decision_reason' => $case['manager_decision_reason'] ?? null,
            ],
        ];
    }

    /**
     * Transitions valid for the acting role. The full map is returned when no
     * role context is supplied (internal/tests).
     */
    public function allowedTransitionsFor(array $case, ?string $role): array
    {
        $allowed = self::TRANSITIONS[(string) $case['status']] ?? [];
        if ($role === null) {
            return $allowed;
        }

        return array_values(array_filter($allowed, function (string $to) use ($case, $role): bool {
            try {
                $this->assertRoleCanTransition((string) $case['status'], $to, $role);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }));
    }

    /**
     * Combined chronological activity for a case: status changes, findings,
     * evidence, ledger entries, and approvals, oldest first.
     */
    public function buildTimeline(int $caseId): array
    {
        $events = [];

        foreach ($this->cases->getHistory($caseId) as $row) {
            $events[] = [
                'at' => $row['created_at'],
                'kind' => 'history',
                'event' => $row['event_type'],
                'title' => $this->historyTitle((string) $row['event_type'], $row),
                'detail' => $row['description'] ?? null,
                'reason' => $row['reason'] ?? null,
                'actor' => $row['actor_username'] ?? null,
            ];
        }

        foreach ($this->cases->getLedgerEntries($caseId) as $row) {
            $label = $this->ledgerLabel((string) $row['entry_type']);
            $amount = number_format((float) $row['amount'], 2);
            $events[] = [
                'at' => $row['created_at'],
                'kind' => 'ledger',
                'event' => $row['state'],
                'title' => $label . ' ' . $amount . ' (' . $row['state'] . ')',
                'detail' => $row['reference'] ?? null,
                'reason' => $row['decision_reason'] ?? $row['notes'] ?? null,
                'actor' => $row['decided_by'] ?: ($row['posted_by'] ?: $row['proposed_by']),
            ];
        }

        foreach ($this->cases->getApprovals($caseId) as $row) {
            $events[] = [
                'at' => $row['decided_at'] ?: $row['requested_at'],
                'kind' => 'approval',
                'event' => $row['status'],
                'title' => 'Approval ' . $row['status'] . ' for ' . $this->ledgerLabel((string) $row['action']),
                'detail' => $row['amount'] !== null ? number_format((float) $row['amount'], 2) : null,
                'reason' => $row['decision_reason'] ?: $row['reason'],
                'actor' => $row['decided_by'] ?: $row['requested_by'],
            ];
        }

        foreach ($this->cases->getEvidence($caseId) as $row) {
            $events[] = [
                'at' => $row['created_at'],
                'kind' => 'evidence',
                'event' => 'uploaded',
                'title' => 'Evidence uploaded',
                'detail' => $row['original_name'] ?? $row['file_path'],
                'reason' => null,
                'actor' => $row['uploaded_by'] ?? null,
            ];
        }

        usort($events, static function (array $a, array $b): int {
            $cmp = strcmp((string) $a['at'], (string) $b['at']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['kind'] . $a['title'], $b['kind'] . $b['title']);
        });

        return $events;
    }

    private function historyTitle(string $eventType, array $row): string
    {
        switch ($eventType) {
            case 'case_opened':
                return 'Case opened';
            case 'status_change':
                return 'Status changed';
            case 'reopened':
                return 'Case reopened';
            case 'reclassified':
                return 'Case reclassified';
            case 'assigned':
                return 'Reviewer assigned';
            case 'findings':
                return 'Findings recorded';
            case 'findings_submitted':
                return 'Findings submitted for approval';
            case 'manager_review_started':
                return 'Manager review started';
            case 'manager_decision':
                return 'Manager decision recorded';
            case 'evidence':
                return 'Evidence uploaded';
            case 'mismatch_established':
                return 'Mismatch established';
            default:
                return ucwords(str_replace('_', ' ', $eventType));
        }
    }

    private function ledgerLabel(string $entryType): string
    {
        switch ($entryType) {
            case 'additional_payment':
                return 'Additional payment';
            case 'source_correction':
                return 'Source-record correction';
            case 'accounting_adjustment':
                return 'Accounting adjustment';
            case 'payroll_recovery':
                return 'Payroll recovery';
            case 'waiver':
                return 'Waiver';
            default:
                return ucwords(str_replace('_', ' ', $entryType));
        }
    }

    /**
     * @return array{data:array,total:int,page:int,perPage:int,pages:int}
     */
    public function listCases(array $filters, int $page = 1, int $perPage = 25): array
    {
        $this->ensureSchema();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $filters = $this->normalizeFilters($filters);
        $total = $this->cases->countCases($filters);
        $rows = $this->cases->listCases($filters, $perPage, ($page - 1) * $perPage);

        $ids = array_map(static fn($row) => (int) $row['id'], $rows);
        $postedSums = $this->cases->getPostedSums($ids);
        $pendingApprovals = $this->cases->getPendingApprovalCaseIds($ids);

        $data = [];
        foreach ($rows as $row) {
            $outstanding = $this->outstandingFromSums($row, $postedSums[(int) $row['id']] ?? ['reduce' => 0.0, 'increase' => 0.0]);
            $overdue = $row['due_date'] !== null
                && $row['due_date'] < self::manilaToday()
                && !in_array($row['status'], [
                    DiscrepancyCaseModel::STATUS_RESOLVED,
                    DiscrepancyCaseModel::STATUS_CLOSED,
                ], true);

            $row['driver_name'] = $row['driver_name'] ?: $row['driver_id'];
            $row['case_ref'] = $row['case_ref'] ?: $this->refFor((int) $row['id']);
            $row['age_days'] = $this->ageDays((string) $row['opened_at']);
            $row['next_action'] = self::nextAction((string) $row['status']);
            $row['outstanding_balance'] = $outstanding;
            $row['is_overdue_investigation'] = $overdue;
            $row['has_pending_approval'] = isset($pendingApprovals[(int) $row['id']]);
            $data[] = $row;
        }

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Translate UI filter input into SQL filter bounds. Age buckets are
     * converted to opened-date bounds so MySQL and SQLite behave the same.
     */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'status' => $filters['status'] ?? null,
            'case_type' => $filters['case_type'] ?? $filters['type'] ?? null,
            'driver' => trim((string) ($filters['driver'] ?? '')),
            'business_from' => self::normalizeDate($filters['business_from'] ?? null),
            'business_to' => self::normalizeDate($filters['business_to'] ?? null),
            'reviewer' => trim((string) ($filters['reviewer'] ?? '')),
            'today' => self::manilaToday(),
        ];

        $bucket = trim((string) ($filters['age'] ?? ''));
        $today = self::manilaNow();

        switch ($bucket) {
            case '0-3':
                $normalized['opened_from'] = $today->modify('-3 days')->format('Y-m-d');
                break;
            case '4-7':
                $normalized['opened_from'] = $today->modify('-7 days')->format('Y-m-d');
                $normalized['opened_to'] = $today->modify('-4 days')->format('Y-m-d');
                break;
            case '8-14':
                $normalized['opened_from'] = $today->modify('-14 days')->format('Y-m-d');
                $normalized['opened_to'] = $today->modify('-8 days')->format('Y-m-d');
                break;
            case '15+':
                $normalized['opened_to'] = $today->modify('-15 days')->format('Y-m-d');
                break;
        }

        if (!empty($filters['overdue_investigation'])) {
            $normalized['overdue_investigation'] = true;
        }

        return $normalized;
    }

    private static function normalizeDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : null;
    }

    public function ageDays(?string $openedAt): int
    {
        if (!$openedAt) {
            return 0;
        }
        try {
            $opened = new \DateTimeImmutable(substr($openedAt, 0, 10), new \DateTimeZone('Asia/Manila'));
            $today = self::manilaNow()->setTime(0, 0, 0);
            $diff = $opened->diff($today);
            return max(0, (int) $diff->days);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Outstanding balance for a case row given posted ledger sums.
     * Shortage cases owe the shortage; excess cases carry a reconciliation
     * exposure. Increase-direction entries add back; reduce-direct entries
     * settle. The original snapshot is never part of this calculation's
     * stored state.
     */
    public function outstandingFromSums(array $case, array $sums): float
    {
        $exposure = ($case['case_type'] ?? '') === DiscrepancyCaseModel::TYPE_EXCESS
            ? (float) $case['excess_amount']
            : (float) $case['discrepancy_amount'];

        $balance = $exposure - (float) ($sums['reduce'] ?? 0) + (float) ($sums['increase'] ?? 0);
        return round(max(0, $balance), 2);
    }

    private function outstandingFromEntries(array $case, array $entries): float
    {
        $sums = ['reduce' => 0.0, 'increase' => 0.0];
        foreach ($entries as $entry) {
            if (($entry['state'] ?? '') !== 'posted') {
                continue;
            }
            if (($entry['direction'] ?? 'reduce') === 'increase') {
                $sums['increase'] += (float) $entry['amount'];
            } else {
                $sums['reduce'] += (float) $entry['amount'];
            }
        }
        return $this->outstandingFromSums($case, $sums);
    }

    /* =========================================================
     * LEGACY BACKFILL
     * ========================================================= */

    /**
     * Create cases for legacy remittance rows (shortages, excesses, and
     * overdue flags). Idempotent: rows that already have a case are skipped.
     *
     * @return array{scanned:int,created:int,existing:int,by_status:array<string,int>,applied:bool}
     */
    public function backfillLegacyCases(bool $apply = false): array
    {
        $this->ensureSchema();
        $rows = $this->fetchLegacyRows();

        $byStatus = [];
        $created = 0;
        $existing = 0;

        foreach ($rows as $row) {
            $legacyStatus = strtolower(trim((string) ($row['status'] ?? '')));
            $byStatus[$legacyStatus] = ($byStatus[$legacyStatus] ?? 0) + 1;

            $id = (int) $row['id'];
            if ($this->cases->findByRemittanceLogId($id)) {
                $existing++;
                continue;
            }
            if (!$apply) {
                continue;
            }

            $expected = round((float) ($row['expected_cash'] ?? 0), 2);
            $actual = $row['actual_cash'] !== null ? round((float) $row['actual_cash'], 2) : null;

            if ($actual === null) {
                $type = DiscrepancyCaseModel::TYPE_OVERDUE;
                $discrepancy = 0.0;
                $excess = 0.0;
            } elseif ($actual > $expected) {
                $type = DiscrepancyCaseModel::TYPE_EXCESS;
                $discrepancy = 0.0;
                $excess = round($actual - $expected, 2);
            } else {
                $type = DiscrepancyCaseModel::TYPE_SHORTAGE;
                $discrepancy = round(max(0, $expected - $actual), 2);
                $excess = 0.0;
            }

            $resolved = in_array($legacyStatus, ['resolved', 'completed'], true) && $actual !== null;
            $status = $resolved ? DiscrepancyCaseModel::STATUS_RESOLVED : DiscrepancyCaseModel::STATUS_OPEN;

            $openedAt = $row['business_date'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
            $openedAt = strlen((string) $openedAt) === 10 ? $openedAt . ' 00:00:00' : (string) $openedAt;

            $caseId = $this->cases->insertCase([
                'remittance_log_id' => $id,
                'driver_id' => (string) ($row['driver_id'] ?? ''),
                'business_date' => $row['business_date'] ?? null,
                'case_type' => $type,
                'status' => $status,
                'expected_amount' => $expected,
                'actual_amount' => $actual,
                'discrepancy_amount' => $discrepancy,
                'excess_amount' => $excess,
                'findings' => $row['resolution_notes'] ?? null,
                'resolution_reason' => $row['resolution_type'] ?? null,
                'opened_by' => 'Legacy import',
                'opened_at' => $openedAt,
                'due_date' => date('Y-m-d', strtotime(substr($openedAt, 0, 10) . ' +' . self::DEFAULT_INVESTIGATION_DAYS . ' days')),
            ]);
            $this->cases->updateCase($caseId, ['case_ref' => $this->refFor($caseId)]);

            if ($resolved) {
                $this->cases->updateCase($caseId, [
                    'resolved_at' => $row['resolved_at'] ?? $openedAt,
                ]);
            }

            $this->cases->insertHistory([
                'case_id' => $caseId,
                'from_status' => null,
                'to_status' => $status,
                'event_type' => 'legacy_import',
                'description' => 'Imported from legacy remittance record #' . $id . ' (' . ($row['status'] ?? '') . ').',
                'reason' => $row['resolution_type'] ?? null,
                'actor_username' => 'Legacy import',
            ]);

            $created++;
        }

        return [
            'scanned' => count($rows),
            'created' => $created,
            'existing' => $existing,
            'by_status' => $byStatus,
            'applied' => $apply,
        ];
    }

    private function fetchLegacyRows(): array
    {
        $withResolution = "
            SELECT id, driver_id, business_date, expected_cash, actual_cash,
                   discrepancy_amount, status, created_at,
                   resolution_type, resolution_notes, resolved_at
            FROM remittance_logs
            WHERE COALESCE(discrepancy_amount, 0) > 0
               OR LOWER(TRIM(COALESCE(status, ''))) = 'overdue'
               OR (actual_cash IS NOT NULL AND expected_cash IS NOT NULL AND actual_cash > expected_cash)
            ORDER BY id ASC
        ";

        try {
            $stmt = $this->db->query($withResolution);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Resolution columns may be missing when the migration could not
            // run; fall back without them.
            $stmt = $this->db->query("
                SELECT id, driver_id, business_date, expected_cash, actual_cash,
                       discrepancy_amount, status, created_at,
                       NULL AS resolution_type, NULL AS resolution_notes, NULL AS resolved_at
                FROM remittance_logs
                WHERE COALESCE(discrepancy_amount, 0) > 0
                   OR LOWER(TRIM(COALESCE(status, ''))) = 'overdue'
                   OR (actual_cash IS NOT NULL AND expected_cash IS NOT NULL AND actual_cash > expected_cash)
                ORDER BY id ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
}
