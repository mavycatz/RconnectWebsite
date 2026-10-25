<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

/**
 * Database access for the discrepancy recovery/adjustment ledger and its
 * approval requests. Accepts an optional PDO for SQLite fixture tests.
 */
class DiscrepancyLedgerModel
{
    public const STATE_PROPOSED = 'proposed';
    public const STATE_APPROVED = 'approved';
    public const STATE_POSTED = 'posted';
    public const STATE_REJECTED = 'rejected';
    public const STATE_VOIDED = 'voided';

    public const STATES = [
        self::STATE_PROPOSED,
        self::STATE_APPROVED,
        self::STATE_POSTED,
        self::STATE_REJECTED,
        self::STATE_VOIDED,
    ];

    private ?\PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db;
    }

    private function db(): \PDO
    {
        if ($this->db === null) {
            $this->db = Database::get();
        }
        return $this->db;
    }

    public function insertEntry(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO discrepancy_ledger_entries (
                case_id, entry_type, recovery_class, amount, direction, state,
                reference, authorization_reference, notes,
                proposed_by, proposed_at, created_at
            ) VALUES (
                :case_id, :entry_type, :recovery_class, :amount, :direction, :state,
                :reference, :authorization_reference, :notes,
                :proposed_by, :proposed_at, :created_at
            )
        ");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            ':case_id' => (int) $data['case_id'],
            ':entry_type' => (string) $data['entry_type'],
            ':recovery_class' => (string) $data['recovery_class'],
            ':amount' => round((float) $data['amount'], 2),
            ':direction' => ($data['direction'] ?? 'reduce') === 'increase' ? 'increase' : 'reduce',
            ':state' => $data['state'] ?? self::STATE_PROPOSED,
            ':reference' => $data['reference'] ?? null,
            ':authorization_reference' => $data['authorization_reference'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':proposed_by' => $data['proposed_by'] ?? null,
            ':proposed_at' => $data['proposed_at'] ?? $now,
            ':created_at' => $data['created_at'] ?? $now,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function getEntryById(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM discrepancy_ledger_entries WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lock the entry row for the rest of the transaction on MySQL; SQLite
     * serializes writers itself and does not support FOR UPDATE.
     */
    public function lockEntry(int $id): ?array
    {
        $sql = "SELECT * FROM discrepancy_ledger_entries WHERE id = ? LIMIT 1";
        if ($this->db()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateEntry(int $id, array $fields): bool
    {
        $allowed = [
            'state', 'decided_by', 'decided_at', 'decision_reason',
            'posted_by', 'posted_at', 'void_reason',
        ];

        $sets = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $value;
        }

        if (!$sets) {
            return false;
        }

        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        $stmt = $this->db()->prepare(
            "UPDATE discrepancy_ledger_entries SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }

    public function insertApproval(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO discrepancy_approvals (
                case_id, ledger_entry_id, action, amount, reason, status,
                requested_by, requested_at, config_reference, created_at
            ) VALUES (
                :case_id, :ledger_entry_id, :action, :amount, :reason, :status,
                :requested_by, :requested_at, :config_reference, :created_at
            )
        ");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            ':case_id' => (int) $data['case_id'],
            ':ledger_entry_id' => isset($data['ledger_entry_id']) ? (int) $data['ledger_entry_id'] : null,
            ':action' => (string) $data['action'],
            ':amount' => isset($data['amount']) ? round((float) $data['amount'], 2) : null,
            ':reason' => $data['reason'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':requested_by' => $data['requested_by'] ?? null,
            ':requested_at' => $data['requested_at'] ?? $now,
            ':config_reference' => $data['config_reference'] ?? null,
            ':created_at' => $data['created_at'] ?? $now,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function getApprovalById(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM discrepancy_approvals WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function lockApproval(int $id): ?array
    {
        $sql = "SELECT * FROM discrepancy_approvals WHERE id = ? LIMIT 1";
        if ($this->db()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPendingApprovalForEntry(int $ledgerEntryId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM discrepancy_approvals
             WHERE ledger_entry_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$ledgerEntryId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateApproval(int $id, array $fields): bool
    {
        $allowed = ['status', 'decided_by', 'decided_at', 'decision_reason'];

        $sets = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $value;
        }

        if (!$sets) {
            return false;
        }

        $params[] = $id;
        $stmt = $this->db()->prepare(
            "UPDATE discrepancy_approvals SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }
}
