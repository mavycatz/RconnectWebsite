<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/DiscrepancyLedgerModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/DiscrepancyCaseService.php';
require_once __DIR__ . '/DiscrepancyAuthorizationService.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Models\DiscrepancyLedgerModel;
use Finance\Models\AuditTrailModel;

/**
 * Records and governs the financial instruments that settle a case.
 *
 * The original discrepancy snapshot is never modified here: every settlement
 * is a ledger entry, and the outstanding balance is derived from posted
 * entries only. Proposal and approval are separate permissions enforced
 * through DiscrepancyAuthorizationService; posting requires recorded findings
 * and a resolution reason.
 */
class DiscrepancyLedgerService
{
    /**
     * entry_type => recovery_class plus extra requirements.
     */
    public const ENTRY_TYPES = [
        'additional_payment' => [
            'class' => 'cash',
            'requires_reference' => true,
            'requires_authorization' => false,
        ],
        'source_correction' => [
            'class' => 'correction',
            'requires_reference' => true,
            'requires_authorization' => false,
        ],
        'accounting_adjustment' => [
            'class' => 'adjustment',
            'requires_reference' => true,
            'requires_authorization' => false,
        ],
        'payroll_recovery' => [
            'class' => 'payroll',
            'requires_reference' => false,
            'requires_authorization' => true,
        ],
        'waiver' => [
            'class' => 'adjustment',
            'requires_reference' => false,
            'requires_authorization' => false,
        ],
    ];

    /** Case statuses from which a resolution may be proposed. */
    private const PROPOSABLE_STATUSES = [
        DiscrepancyCaseModel::STATUS_INVESTIGATING,
        DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE,
        DiscrepancyCaseModel::STATUS_APPROVAL,
        DiscrepancyCaseModel::STATUS_RECOVERY,
    ];

    private \PDO $db;
    private DiscrepancyLedgerModel $ledger;
    private DiscrepancyCaseService $cases;
    private DiscrepancyAuthorizationService $auth;

    public function __construct(
        ?\PDO $db = null,
        ?DiscrepancyLedgerModel $ledger = null,
        ?DiscrepancyCaseService $cases = null,
        ?DiscrepancyAuthorizationService $auth = null
    ) {
        if ($db === null) {
            $db = \App\Core\Database::get();
        }
        $this->db = $db;
        $this->ledger = $ledger ?? new DiscrepancyLedgerModel($this->db);
        $this->cases = $cases ?? new DiscrepancyCaseService($this->db);
        $this->auth = $auth ?? new DiscrepancyAuthorizationService($this->db);
    }

    public function authorization(): DiscrepancyAuthorizationService
    {
        return $this->auth;
    }

    public static function entryTypeMeta(string $entryType): array
    {
        if (!isset(self::ENTRY_TYPES[$entryType])) {
            throw new \InvalidArgumentException('Unsupported resolution instrument: ' . $entryType);
        }
        return self::ENTRY_TYPES[$entryType];
    }

    public static function entryTypeLabel(string $entryType): string
    {
        return DiscrepancyAuthorizationService::ACTION_LABELS[$entryType] ?? ucwords(str_replace('_', ' ', $entryType));
    }

    private function transactional(callable $fn)
    {
        $started = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $started = true;
        }

        try {
            $result = $fn();
            if ($started) {
                $this->db->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($started && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function configReference(): string
    {
        $rules = $this->auth->rules();
        $stored = false;
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
            $stmt->execute([DiscrepancyAuthorizationService::SETTING_KEY]);
            $stored = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $stored = false;
        }
        return ($stored ? 'discrepancy_auth_rules:v' : 'discrepancy_auth_rules:default-v') . $rules['version'];
    }

    /* =========================================================
     * PROPOSE
     * ========================================================= */

    /**
     * Propose a resolution instrument. Creates a proposed ledger entry and a
     * pending approval request, then moves the case to Awaiting Approval.
     */
    public function propose(array $data, ?array $actor = null): array
    {
        $actor = DiscrepancyCaseService::currentActor($actor);

        $caseId = (int) ($data['case_id'] ?? 0);
        $entryType = trim((string) ($data['entry_type'] ?? ''));
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $direction = ($data['direction'] ?? 'reduce') === 'increase' ? 'increase' : 'reduce';
        $reference = trim((string) ($data['reference'] ?? ''));
        $authorizationReference = trim((string) ($data['authorization_reference'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($caseId <= 0) {
            throw new \InvalidArgumentException('A valid case is required.');
        }
        $meta = self::entryTypeMeta($entryType);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('A resolution amount greater than zero is required.');
        }

        $case = $this->cases->model()->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }

        if ($case['case_type'] === DiscrepancyCaseModel::TYPE_OVERDUE && $case['actual_amount'] === null) {
            throw new \InvalidArgumentException('Process the late remittance before proposing a resolution for an overdue case.');
        }

        if (!in_array($case['status'], self::PROPOSABLE_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'A resolution cannot be proposed while the case is ' . $case['status'] . '. Investigate the case first.'
            );
        }

        if ($this->hasPendingApproval($caseId)) {
            throw new \InvalidArgumentException(
                'An approval is already pending for this case. Decide it before proposing another resolution.'
            );
        }

        if ($meta['requires_reference'] && $reference === '') {
            throw new \InvalidArgumentException('A reference is required for ' . self::entryTypeLabel($entryType) . '.');
        }

        if ($meta['requires_authorization'] && $authorizationReference === '') {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Resolution Proposed',
                'Refused: ' . self::entryTypeLabel($entryType) . ' requires an authorization reference.',
                $this->cases->refFor($caseId),
                null,
                null,
                'Failed',
                $this->db
            );
            throw new \InvalidArgumentException(
                'The applicable legal and cooperative-policy authorization reference is required for a payroll recovery.'
            );
        }

        if (!$this->auth->canPropose($entryType, $amount, $actor['role'])) {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Resolution Proposed',
                'Refused: role ' . ($actor['role'] ?? 'unknown') . ' may not propose ' . self::entryTypeLabel($entryType) . '.',
                $this->cases->refFor($caseId),
                null,
                ['amount' => $amount, 'entry_type' => $entryType],
                'Failed',
                $this->db
            );
            throw new \UnexpectedValueException('Your role is not authorized to propose ' . self::entryTypeLabel($entryType) . '.');
        }

        return $this->transactional(function () use (
            $caseId,
            $case,
            $entryType,
            $meta,
            $amount,
            $direction,
            $reference,
            $authorizationReference,
            $notes,
            $reason,
            $actor
        ) {
            $entryId = $this->ledger->insertEntry([
                'case_id' => $caseId,
                'entry_type' => $entryType,
                'recovery_class' => $meta['class'],
                'amount' => $amount,
                'direction' => $direction,
                'state' => DiscrepancyLedgerModel::STATE_PROPOSED,
                'reference' => $reference !== '' ? $reference : null,
                'authorization_reference' => $authorizationReference !== '' ? $authorizationReference : null,
                'notes' => $notes !== '' ? $notes : null,
                'proposed_by' => $actor['username'],
                'proposed_at' => DiscrepancyCaseService::manilaNow()->format('Y-m-d H:i:s'),
            ]);

            $approvalId = $this->ledger->insertApproval([
                'case_id' => $caseId,
                'ledger_entry_id' => $entryId,
                'action' => $entryType,
                'amount' => $amount,
                'reason' => $reason !== '' ? $reason : ($notes !== '' ? $notes : 'Resolution proposed.'),
                'status' => 'pending',
                'requested_by' => $actor['username'],
                'requested_at' => DiscrepancyCaseService::manilaNow()->format('Y-m-d H:i:s'),
                'config_reference' => $this->configReference(),
            ]);

            if ($case['status'] !== DiscrepancyCaseModel::STATUS_APPROVAL) {
                $this->cases->transition(
                    $caseId,
                    DiscrepancyCaseModel::STATUS_APPROVAL,
                    'Resolution proposed for approval.',
                    $actor
                );
            }

            AuditTrailModel::record(
                'Finance',
                'Discrepancy Resolution Proposed',
                self::entryTypeLabel($entryType) . ' of ' . number_format($amount, 2) . ' proposed; awaiting approval.',
                $this->cases->refFor($caseId),
                null,
                [
                    'entry_id' => $entryId,
                    'entry_type' => $entryType,
                    'amount' => $amount,
                    'direction' => $direction,
                    'reference' => $reference,
                    'authorization_reference' => $authorizationReference,
                    'status' => DiscrepancyCaseModel::STATUS_APPROVAL,
                ],
                'Success',
                $this->db
            );

            return [
                'entry_id' => $entryId,
                'approval_id' => $approvalId,
                'state' => DiscrepancyLedgerModel::STATE_PROPOSED,
                'config_reference' => $this->configReference(),
            ];
        });
    }

    /* =========================================================
     * DECIDE
     * ========================================================= */

    /**
     * Approve or reject a pending proposal. Rejection never posts.
     */
    public function decide(int $approvalId, string $decision, string $reason, ?array $actor = null): array
    {
        $actor = DiscrepancyCaseService::currentActor($actor);
        $decision = strtolower(trim($decision));
        $reason = trim($reason);

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('The decision must be approved or rejected.');
        }
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for every approval decision.');
        }

        return $this->decideTransactional($approvalId, $decision, $reason, $actor);
    }

    /**
     * Decision work runs in a transaction; authorization refusals are recorded
     * after the rollback so the Failed audit entry is never lost.
     */
    private function decideTransactional(int $approvalId, string $decision, string $reason, array $actor): array
    {
        $auditAction = 'Discrepancy Resolution ' . ucfirst($decision);
        $refusal = null;

        try {
            return $this->transactional(function () use ($approvalId, $decision, $reason, $actor, &$refusal) {
                $approval = $this->ledger->lockApproval($approvalId);
                if (!$approval) {
                    throw new \InvalidArgumentException('Approval request not found.');
                }
                if ($approval['status'] !== 'pending') {
                    throw new \InvalidArgumentException('This approval request has already been decided.');
                }

                $entry = $this->ledger->lockEntry((int) $approval['ledger_entry_id']);
                if (!$entry) {
                    throw new \InvalidArgumentException('Ledger entry not found.');
                }
                if ($entry['state'] !== DiscrepancyLedgerModel::STATE_PROPOSED) {
                    throw new \InvalidArgumentException('Only a proposed entry can be decided.');
                }

                $action = (string) $entry['entry_type'];
                $amount = (float) $entry['amount'];
                $caseId = (int) $entry['case_id'];

                if (!$this->auth->canApprove($action, $amount, $actor['role'])) {
                    $refusal = [
                        'case_id' => $caseId,
                        'entry_id' => (int) $entry['id'],
                        'description' => 'Refused: role ' . ($actor['role'] ?? 'unknown') . ' may not decide this action.',
                    ];
                    throw new \UnexpectedValueException('Your role is not authorized to approve or reject this action.');
                }

                if ($entry['proposed_by'] === $actor['username'] && !$this->auth->allowSelfApproval($action, $amount)) {
                    $refusal = [
                        'case_id' => $caseId,
                        'entry_id' => (int) $entry['id'],
                        'description' => 'Refused: self-approval is not allowed for ' . $action . '.',
                    ];
                    throw new \UnexpectedValueException('You cannot approve your own proposal.');
                }

                $now = DiscrepancyCaseService::manilaNow()->format('Y-m-d H:i:s');

                $this->ledger->updateApproval((int) $approval['id'], [
                    'status' => $decision,
                    'decided_by' => $actor['username'],
                    'decided_at' => $now,
                    'decision_reason' => $reason,
                ]);

                $this->ledger->updateEntry((int) $entry['id'], [
                    'state' => $decision === 'approved'
                        ? DiscrepancyLedgerModel::STATE_APPROVED
                        : DiscrepancyLedgerModel::STATE_REJECTED,
                    'decided_by' => $actor['username'],
                    'decided_at' => $now,
                    'decision_reason' => $reason,
                ]);

                $case = $this->cases->model()->getById($caseId);
                $otherPending = $this->hasOtherPendingApprovals($caseId, (int) $approval['id']);

                if (!$otherPending && $case && $case['status'] === DiscrepancyCaseModel::STATUS_APPROVAL) {
                    $target = $decision === 'approved'
                        ? DiscrepancyCaseModel::STATUS_RECOVERY
                        : DiscrepancyCaseModel::STATUS_INVESTIGATING;
                    $this->cases->transition(
                        $caseId,
                        $target,
                        $decision === 'approved'
                            ? 'Resolution approved; awaiting posting.'
                            : 'Resolution rejected: ' . $reason,
                        $actor
                    );
                }

                AuditTrailModel::record(
                    'Finance',
                    'Discrepancy Resolution ' . ucfirst($decision),
                    self::entryTypeLabel($action) . ' of ' . number_format($amount, 2) . ' ' . $decision . '.',
                    $this->cases->refFor($caseId),
                    ['state' => DiscrepancyLedgerModel::STATE_PROPOSED],
                    [
                        'entry_id' => (int) $entry['id'],
                        'state' => $decision === 'approved'
                            ? DiscrepancyLedgerModel::STATE_APPROVED
                            : DiscrepancyLedgerModel::STATE_REJECTED,
                        'reason' => $reason,
                        'approver' => $actor['username'],
                        'config_reference' => $approval['config_reference'],
                    ],
                    'Success',
                    $this->db
                );

                return [
                    'entry_id' => (int) $entry['id'],
                    'approval_id' => (int) $approval['id'],
                    'decision' => $decision,
                    'state' => $this->ledger->getEntryById((int) $entry['id'])['state'],
                ];
            });
        } catch (\UnexpectedValueException $e) {
            if ($refusal !== null) {
                AuditTrailModel::record(
                    'Finance',
                    $auditAction,
                    $refusal['description'],
                    $this->cases->refFor((int) $refusal['case_id']),
                    null,
                    ['entry_id' => $refusal['entry_id'], 'decision' => $decision],
                    'Failed',
                    $this->db
                );
            }
            throw $e;
        }
    }

    private function hasOtherPendingApprovals(int $caseId, int $excludeApprovalId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM discrepancy_approvals
             WHERE case_id = ? AND status = 'pending' AND id <> ?"
        );
        $stmt->execute([$caseId, $excludeApprovalId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasPendingApproval(int $caseId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM discrepancy_approvals WHERE case_id = ? AND status = 'pending'"
        );
        $stmt->execute([$caseId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /* =========================================================
     * POST
     * ========================================================= */

    /**
     * Post an approved entry so it affects the outstanding balance. Requires
     * recorded findings and a resolution reason on the case.
     */
    public function post(int $entryId, ?array $actor = null): array
    {
        $actor = DiscrepancyCaseService::currentActor($actor);

        return $this->transactional(function () use ($entryId, $actor) {
            $entry = $this->ledger->lockEntry($entryId);
            if (!$entry) {
                throw new \InvalidArgumentException('Ledger entry not found.');
            }
            if ($entry['state'] !== DiscrepancyLedgerModel::STATE_APPROVED) {
                throw new \InvalidArgumentException('Only an approved entry can be posted.');
            }

            $caseId = (int) $entry['case_id'];
            $case = $this->cases->model()->getById($caseId);
            if (!$case) {
                throw new \InvalidArgumentException('Discrepancy case not found.');
            }

            if (trim((string) $case['findings']) === '') {
                throw new \InvalidArgumentException('Record the investigation findings before posting a resolution.');
            }

            $reason = trim((string) ($case['resolution_reason'] ?: ($entry['decision_reason'] ?: $entry['notes'])));
            if ($reason === '') {
                throw new \InvalidArgumentException('Record the resolution reason before posting a resolution.');
            }

            $outstandingBefore = $this->outstandingBalance($caseId);

            $this->ledger->updateEntry($entryId, [
                'state' => DiscrepancyLedgerModel::STATE_POSTED,
                'posted_by' => $actor['username'],
                'posted_at' => DiscrepancyCaseService::manilaNow()->format('Y-m-d H:i:s'),
            ]);

            $outstandingAfter = $this->outstandingBalance($caseId);

            AuditTrailModel::record(
                'Finance',
                'Discrepancy Resolution Posted',
                self::entryTypeLabel((string) $entry['entry_type']) . ' of ' . number_format((float) $entry['amount'], 2) . ' posted.',
                $this->cases->refFor($caseId),
                ['outstanding_balance' => $outstandingBefore],
                [
                    'entry_id' => $entryId,
                    'state' => DiscrepancyLedgerModel::STATE_POSTED,
                    'amount' => (float) $entry['amount'],
                    'recovery_class' => $entry['recovery_class'],
                    'reference' => $entry['reference'],
                    'outstanding_balance' => $outstandingAfter,
                ],
                'Success',
                $this->db
            );

            return [
                'entry_id' => $entryId,
                'state' => DiscrepancyLedgerModel::STATE_POSTED,
                'outstanding_before' => $outstandingBefore,
                'outstanding_after' => $outstandingAfter,
            ];
        });
    }

    /* =========================================================
     * VOID
     * ========================================================= */

    public function void(int $entryId, string $reason, ?array $actor = null): array
    {
        $actor = DiscrepancyCaseService::currentActor($actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required to void a ledger entry.');
        }

        return $this->transactional(function () use ($entryId, $reason, $actor) {
            $entry = $this->ledger->lockEntry($entryId);
            if (!$entry) {
                throw new \InvalidArgumentException('Ledger entry not found.');
            }
            if (!in_array($entry['state'], [
                DiscrepancyLedgerModel::STATE_APPROVED,
                DiscrepancyLedgerModel::STATE_POSTED,
            ], true)) {
                throw new \InvalidArgumentException('Only an approved or posted entry can be voided.');
            }

            $caseId = (int) $entry['case_id'];
            $outstandingBefore = $this->outstandingBalance($caseId);

            $this->ledger->updateEntry($entryId, [
                'state' => DiscrepancyLedgerModel::STATE_VOIDED,
                'void_reason' => $reason,
            ]);

            $outstandingAfter = $this->outstandingBalance($caseId);

            AuditTrailModel::record(
                'Finance',
                'Discrepancy Resolution Voided',
                self::entryTypeLabel((string) $entry['entry_type']) . ' of ' . number_format((float) $entry['amount'], 2) . ' voided: ' . $reason,
                $this->cases->refFor($caseId),
                ['state' => $entry['state'], 'outstanding_balance' => $outstandingBefore],
                [
                    'entry_id' => $entryId,
                    'state' => DiscrepancyLedgerModel::STATE_VOIDED,
                    'reason' => $reason,
                    'outstanding_balance' => $outstandingAfter,
                ],
                'Success',
                $this->db
            );

            return [
                'entry_id' => $entryId,
                'state' => DiscrepancyLedgerModel::STATE_VOIDED,
                'outstanding_before' => $outstandingBefore,
                'outstanding_after' => $outstandingAfter,
            ];
        });
    }

    /* =========================================================
     * BALANCE
     * ========================================================= */

    public function outstandingBalance(int $caseId): float
    {
        $case = $this->cases->model()->getById($caseId);
        if (!$case) {
            throw new \InvalidArgumentException('Discrepancy case not found.');
        }
        $sums = $this->cases->model()->getPostedSums([$caseId]);
        return $this->cases->outstandingFromSums($case, $sums[$caseId] ?? ['reduce' => 0.0, 'increase' => 0.0]);
    }

    /**
     * Per-class totals for a case, for the details header.
     */
    public function totalsForCase(int $caseId): array
    {
        $entries = $this->cases->model()->getLedgerEntries($caseId);

        $totals = [
            'cash_received' => 0.0,
            'payroll_recovery' => 0.0,
            'corrections' => 0.0,
            'adjustments' => 0.0,
            'pending_amount' => 0.0,
            'posted_count' => 0,
        ];

        foreach ($entries as $entry) {
            $amount = (float) $entry['amount'];
            $signed = ($entry['direction'] ?? 'reduce') === 'increase' ? -$amount : $amount;

            if ($entry['state'] === DiscrepancyLedgerModel::STATE_PROPOSED) {
                $totals['pending_amount'] += $amount;
            }

            if ($entry['state'] !== DiscrepancyLedgerModel::STATE_POSTED) {
                continue;
            }

            $totals['posted_count']++;

            if ($entry['recovery_class'] === 'cash') {
                $totals['cash_received'] += $signed;
            } elseif ($entry['recovery_class'] === 'payroll') {
                $totals['payroll_recovery'] += $signed;
            } elseif ($entry['recovery_class'] === 'correction') {
                $totals['corrections'] += $signed;
            } else {
                $totals['adjustments'] += $signed;
            }
        }

        foreach (['cash_received', 'payroll_recovery', 'corrections', 'adjustments', 'pending_amount'] as $key) {
            $totals[$key] = round($totals[$key], 2);
        }

        return $totals;
    }
}
