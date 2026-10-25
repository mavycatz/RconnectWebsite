<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

/**
 * Database access for discrepancy investigation cases and their child records.
 *
 * The models accept an optional PDO so feature tests can run against an
 * in-memory SQLite fixture; production code uses the shared connection.
 */
class DiscrepancyCaseModel
{
    public const TYPE_SHORTAGE = 'Cash Shortage';
    public const TYPE_QRPH = 'QR Ph Payment Mismatch';
    public const TYPE_SYSTEM = 'System or Fare Error';
    public const TYPE_OVERDUE = 'Overdue Remittance';
    public const TYPE_EXCESS = 'Excess Remittance';

    public const STATUS_OPEN = 'Open';
    public const STATUS_INVESTIGATING = 'Under Investigation';
    public const STATUS_DRIVER_RESPONSE = 'Awaiting Driver Response';
    public const STATUS_FINDINGS_SUBMITTED = 'Findings Submitted';
    public const STATUS_PENDING_MANAGER_APPROVAL = 'Pending Manager Approval';
    public const STATUS_RETURNED = 'Returned for Further Investigation';
    public const STATUS_APPROVAL = 'Awaiting Approval';
    public const STATUS_RECOVERY = 'Awaiting Recovery';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_CLOSED = 'Closed';

    public const TYPES = [
        self::TYPE_SHORTAGE,
        self::TYPE_QRPH,
        self::TYPE_SYSTEM,
        self::TYPE_OVERDUE,
        self::TYPE_EXCESS,
    ];

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_INVESTIGATING,
        self::STATUS_DRIVER_RESPONSE,
        self::STATUS_FINDINGS_SUBMITTED,
        self::STATUS_PENDING_MANAGER_APPROVAL,
        self::STATUS_RETURNED,
        self::STATUS_APPROVAL,
        self::STATUS_RECOVERY,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    /** Roles that may make the final approval decision or reopen a case. */
    public const MANAGER_ROLES = ['finance_manager', 'admin', 'super_admin'];

    /** Roles that may investigate and prepare findings. */
    public const INVESTIGATOR_ROLES = ['finance_officer', 'finance_manager', 'admin', 'super_admin'];

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

    /**
     * Add the legacy resolution columns to remittance_logs when missing.
     * Idempotent: information_schema-guarded, matching the existing pattern
     * used by RemittanceModel::ensureBusinessDateColumn().
     */
    public function ensureResolutionColumns(?\PDO $db = null): void
    {
        $db = $db ?? $this->db();

        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $columns = [
            'resolution_type' => 'VARCHAR(100) NULL',
            'resolution_notes' => 'TEXT NULL',
            'resolved_by' => 'VARCHAR(100) NULL',
            'resolved_at' => 'DATETIME NULL',
            'supporting_document' => 'VARCHAR(255) NULL',
        ];

        foreach ($columns as $column => $definition) {
            try {
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'remittance_logs'
                       AND COLUMN_NAME = ?"
                );
                $stmt->execute([$column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $db->exec("ALTER TABLE remittance_logs ADD COLUMN " . $column . " " . $definition);
                }
            } catch (\Throwable $e) {
                // Non-fatal: the resolution workflow degrades to case records only.
                error_log('Discrepancy resolution column ensure failed for ' . $column . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Create the additive workflow tables when they do not exist.
     * MySQL-only DDL; SQLite fixtures create their own schema.
     */
    public function initTables(?\PDO $db = null): void
    {
        $db = $db ?? $this->db();

        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        /**
         * Newer MariaDB/MySQL servers default utf8mb4 to a UCA 14.0.0
         * collation (utf8mb4_uca1400_ai_ci), while the legacy tables use
         * utf8mb4_unicode_ci. Creating these tables with the server default
         * makes driver/status joins raise error 1267 "Illegal mix of
         * collations". Align to the collation the legacy tables (and the
         * session) actually use, and convert tables created before this fix.
         */
        $collation = $this->preferredCollation($db);

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS discrepancy_cases (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    remittance_log_id INT NOT NULL,
                    case_ref VARCHAR(20) NULL,
                    driver_id VARCHAR(20) NULL,
                    business_date DATE NULL,
                    case_type VARCHAR(40) NOT NULL DEFAULT 'Cash Shortage',
                    status VARCHAR(40) NOT NULL DEFAULT 'Open',
                    expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    actual_amount DECIMAL(12,2) NULL,
                    discrepancy_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    excess_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    assigned_reviewer VARCHAR(100) NULL,
                    assigned_at DATETIME NULL,
                    due_date DATE NULL,
                    findings TEXT NULL,
                    resolution_reason TEXT NULL,
                    opened_by VARCHAR(100) NULL,
                    opened_at DATETIME NULL,
                    resolved_at DATETIME NULL,
                    closed_at DATETIME NULL,
                    findings_submitted_by VARCHAR(100) NULL,
                    findings_submitted_at DATETIME NULL,
                    manager_decision VARCHAR(20) NULL,
                    manager_decision_by VARCHAR(100) NULL,
                    manager_decision_at DATETIME NULL,
                    manager_decision_reason TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_disc_case_remittance (remittance_log_id),
                    KEY idx_disc_case_status (status),
                    KEY idx_disc_case_type (case_type),
                    KEY idx_disc_case_driver (driver_id),
                    KEY idx_disc_case_business (business_date),
                    KEY idx_disc_case_reviewer (assigned_reviewer),
                    KEY idx_disc_case_due (due_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS discrepancy_status_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    from_status VARCHAR(40) NULL,
                    to_status VARCHAR(40) NULL,
                    event_type VARCHAR(40) NOT NULL DEFAULT 'status_change',
                    description TEXT NULL,
                    reason TEXT NULL,
                    actor_id INT NULL,
                    actor_username VARCHAR(100) NULL,
                    actor_role VARCHAR(100) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_disc_hist_case (case_id),
                    KEY idx_disc_hist_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS discrepancy_evidence (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NULL,
                    mime VARCHAR(100) NULL,
                    size INT NULL,
                    uploaded_by VARCHAR(100) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_disc_ev_case (case_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS discrepancy_ledger_entries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    entry_type VARCHAR(40) NOT NULL,
                    recovery_class VARCHAR(20) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    direction VARCHAR(10) NOT NULL DEFAULT 'reduce',
                    state VARCHAR(20) NOT NULL DEFAULT 'proposed',
                    reference VARCHAR(190) NULL,
                    authorization_reference VARCHAR(190) NULL,
                    notes TEXT NULL,
                    proposed_by VARCHAR(100) NULL,
                    proposed_at DATETIME NULL,
                    decided_by VARCHAR(100) NULL,
                    decided_at DATETIME NULL,
                    decision_reason TEXT NULL,
                    posted_by VARCHAR(100) NULL,
                    posted_at DATETIME NULL,
                    void_reason VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_disc_ledger_case (case_id),
                    KEY idx_disc_ledger_state (state),
                    KEY idx_disc_ledger_class (recovery_class)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS discrepancy_approvals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    ledger_entry_id INT NULL,
                    action VARCHAR(40) NOT NULL,
                    amount DECIMAL(12,2) NULL,
                    reason TEXT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    requested_by VARCHAR(100) NULL,
                    requested_at DATETIME NULL,
                    decided_by VARCHAR(100) NULL,
                    decided_at DATETIME NULL,
                    decision_reason TEXT NULL,
                    config_reference VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_disc_appr_case (case_id, status),
                    KEY idx_disc_appr_entry (ledger_entry_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation
            ");
        } catch (\Throwable $e) {
            error_log('Discrepancy workflow table init failed: ' . $e->getMessage());
        }

        // Convert tables created before this fix (or by a server default) so
        // joins and parameter comparisons against the legacy tables do not
        // raise error 1267.
        $this->alignTableCollations($db, $collation);

        // Additive approval-workflow columns for installs created before them.
        $this->ensureCaseColumns($db);
    }

    /**
     * Add the investigation approval columns to discrepancy_cases when
     * missing. Idempotent and additive.
     */
    public function ensureCaseColumns(?\PDO $db = null): void
    {
        $db = $db ?? $this->db();

        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $columns = [
            'findings_submitted_by' => 'VARCHAR(100) NULL',
            'findings_submitted_at' => 'DATETIME NULL',
            'manager_decision' => 'VARCHAR(20) NULL',
            'manager_decision_by' => 'VARCHAR(100) NULL',
            'manager_decision_at' => 'DATETIME NULL',
            'manager_decision_reason' => 'TEXT NULL',
        ];

        foreach ($columns as $column => $definition) {
            try {
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'discrepancy_cases'
                       AND COLUMN_NAME = ?"
                );
                $stmt->execute([$column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $db->exec("ALTER TABLE discrepancy_cases ADD COLUMN " . $column . " " . $definition);
                }
            } catch (\Throwable $e) {
                error_log('Discrepancy case column ensure failed for ' . $column . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Collation the legacy tables and the connection use. Falls back to the
     * connection collation, then utf8mb4_unicode_ci.
     */
    private function preferredCollation(\PDO $db): string
    {
        $targets = [
            ['drivers', 'driver_id'],
            ['remittance_logs', 'driver_id'],
            ['users', 'role'],
        ];

        foreach ($targets as $target) {
            try {
                $stmt = $db->prepare(
                    "SELECT COLLATION_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                     LIMIT 1"
                );
                $stmt->execute([$target[0], $target[1]]);
                $found = trim((string) $stmt->fetchColumn());
                if ($found !== '' && preg_match('/^utf8mb4_[A-Za-z0-9_]+$/', $found)) {
                    return $found;
                }
            } catch (\Throwable $e) {
                // try the next target
            }
        }

        try {
            $found = trim((string) $db->query("SELECT @@collation_connection")->fetchColumn());
            if ($found !== '' && preg_match('/^utf8mb4_[A-Za-z0-9_]+$/', $found)) {
                return $found;
            }
        } catch (\Throwable $e) {
            // fall through to the hard default
        }

        return 'utf8mb4_unicode_ci';
    }

    /**
     * Convert workflow tables whose collation differs from the legacy
     * collation. Idempotent: tables already aligned are skipped.
     */
    private function alignTableCollations(\PDO $db, string $collation): void
    {
        $tables = [
            'discrepancy_cases',
            'discrepancy_status_history',
            'discrepancy_evidence',
            'discrepancy_ledger_entries',
            'discrepancy_approvals',
        ];

        foreach ($tables as $table) {
            try {
                $stmt = $db->prepare(
                    "SELECT TABLE_COLLATION FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                     LIMIT 1"
                );
                $stmt->execute([$table]);
                $current = trim((string) $stmt->fetchColumn());

                if ($current === '' || strcasecmp($current, $collation) === 0) {
                    continue;
                }

                $db->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE $collation");
            } catch (\Throwable $e) {
                error_log('Discrepancy table collation alignment failed for ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    public function findByRemittanceLogId(int $remittanceLogId): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM discrepancy_cases WHERE remittance_log_id = ? LIMIT 1");
        $stmt->execute([$remittanceLogId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM discrepancy_cases WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insert a case; the UNIQUE remittance_log_id makes duplicate creation a
     * constraint error that callers treat as "already exists".
     */
    public function insertCase(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO discrepancy_cases (
                remittance_log_id, case_ref, driver_id, business_date, case_type, status,
                expected_amount, actual_amount, discrepancy_amount, excess_amount,
                assigned_reviewer, assigned_at, due_date, findings, resolution_reason,
                opened_by, opened_at
            ) VALUES (
                :remittance_log_id, :case_ref, :driver_id, :business_date, :case_type, :status,
                :expected_amount, :actual_amount, :discrepancy_amount, :excess_amount,
                :assigned_reviewer, :assigned_at, :due_date, :findings, :resolution_reason,
                :opened_by, :opened_at
            )
        ");
        $stmt->execute([
            ':remittance_log_id' => (int) $data['remittance_log_id'],
            ':case_ref' => $data['case_ref'] ?? null,
            ':driver_id' => $data['driver_id'] ?? null,
            ':business_date' => $data['business_date'] ?? null,
            ':case_type' => $data['case_type'] ?? self::TYPE_SHORTAGE,
            ':status' => $data['status'] ?? self::STATUS_OPEN,
            ':expected_amount' => round((float) ($data['expected_amount'] ?? 0), 2),
            ':actual_amount' => isset($data['actual_amount']) && $data['actual_amount'] !== null
                ? round((float) $data['actual_amount'], 2) : null,
            ':discrepancy_amount' => round((float) ($data['discrepancy_amount'] ?? 0), 2),
            ':excess_amount' => round((float) ($data['excess_amount'] ?? 0), 2),
            ':assigned_reviewer' => $data['assigned_reviewer'] ?? null,
            ':assigned_at' => $data['assigned_at'] ?? null,
            ':due_date' => $data['due_date'] ?? null,
            ':findings' => $data['findings'] ?? null,
            ':resolution_reason' => $data['resolution_reason'] ?? null,
            ':opened_by' => $data['opened_by'] ?? null,
            ':opened_at' => $data['opened_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    /**
     * Update workflow fields. Amount snapshots are deliberately excluded so
     * resolution activity can never rewrite the original discrepancy.
     */
    public function updateCase(int $id, array $fields): bool
    {
        $allowed = [
            'case_ref', 'case_type', 'status', 'assigned_reviewer', 'assigned_at',
            'due_date', 'findings', 'resolution_reason', 'resolved_at', 'closed_at',
            'findings_submitted_by', 'findings_submitted_at',
            'manager_decision', 'manager_decision_by', 'manager_decision_at', 'manager_decision_reason',
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
            "UPDATE discrepancy_cases SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        return $stmt->execute($params);
    }

    /**
     * Establish a mismatch for a case that carried no asserted amount
     * (overdue -> late processing). Snapshots are set once and never again.
     */
    public function applyMismatch(int $id, array $amounts): bool
    {
        $stmt = $this->db()->prepare("
            UPDATE discrepancy_cases
            SET expected_amount = :expected_amount,
                actual_amount = :actual_amount,
                discrepancy_amount = :discrepancy_amount,
                excess_amount = :excess_amount,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND actual_amount IS NULL
        ");
        $stmt->execute([
            ':expected_amount' => round((float) ($amounts['expected_amount'] ?? 0), 2),
            ':actual_amount' => round((float) ($amounts['actual_amount'] ?? 0), 2),
            ':discrepancy_amount' => round((float) ($amounts['discrepancy_amount'] ?? 0), 2),
            ':excess_amount' => round((float) ($amounts['excess_amount'] ?? 0), 2),
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function insertHistory(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO discrepancy_status_history (
                case_id, from_status, to_status, event_type, description, reason,
                actor_id, actor_username, actor_role, created_at
            ) VALUES (
                :case_id, :from_status, :to_status, :event_type, :description, :reason,
                :actor_id, :actor_username, :actor_role, :created_at
            )
        ");
        $stmt->execute([
            ':case_id' => (int) $data['case_id'],
            ':from_status' => $data['from_status'] ?? null,
            ':to_status' => $data['to_status'] ?? null,
            ':event_type' => $data['event_type'] ?? 'status_change',
            ':description' => $data['description'] ?? null,
            ':reason' => $data['reason'] ?? null,
            ':actor_id' => isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            ':actor_username' => $data['actor_username'] ?? null,
            ':actor_role' => $data['actor_role'] ?? null,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function getHistory(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM discrepancy_status_history WHERE case_id = ? ORDER BY created_at ASC, id ASC"
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertEvidence(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO discrepancy_evidence (
                case_id, file_path, original_name, mime, size, uploaded_by, created_at
            ) VALUES (
                :case_id, :file_path, :original_name, :mime, :size, :uploaded_by, :created_at
            )
        ");
        $stmt->execute([
            ':case_id' => (int) $data['case_id'],
            ':file_path' => (string) $data['file_path'],
            ':original_name' => $data['original_name'] ?? null,
            ':mime' => $data['mime'] ?? null,
            ':size' => isset($data['size']) ? (int) $data['size'] : null,
            ':uploaded_by' => $data['uploaded_by'] ?? null,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function getEvidence(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM discrepancy_evidence WHERE case_id = ? ORDER BY created_at ASC, id ASC"
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Filtered, paginated case list. Filters are combined with AND.
     */
    public function listCases(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilterSql($filters);

        $sql = "
            SELECT c.*, COALESCE(d.full_name, c.driver_id) AS driver_name
            FROM discrepancy_cases c
            LEFT JOIN drivers d ON d.driver_id = c.driver_id
            $where
            ORDER BY c.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countCases(array $filters): int
    {
        [$where, $params] = $this->buildFilterSql($filters);
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM discrepancy_cases c
             LEFT JOIN drivers d ON d.driver_id = c.driver_id
             $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Build the shared filter clause used by the list and the count so the
     * two always agree.
     */
    public function buildFilterSql(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $placeholders = [];
            foreach (array_values($statuses) as $i => $status) {
                $key = ':status_' . $i;
                $placeholders[] = $key;
                $params[$key] = (string) $status;
            }
            $conditions[] = 'c.status IN (' . implode(', ', $placeholders) . ')';
        }

        if (!empty($filters['case_type'])) {
            $types = is_array($filters['case_type']) ? $filters['case_type'] : [$filters['case_type']];
            $placeholders = [];
            foreach (array_values($types) as $i => $type) {
                $key = ':type_' . $i;
                $placeholders[] = $key;
                $params[$key] = (string) $type;
            }
            $conditions[] = 'c.case_type IN (' . implode(', ', $placeholders) . ')';
        }

        if (!empty($filters['driver'])) {
            $conditions[] = "(c.driver_id LIKE :driver OR d.full_name LIKE :driver_name)";
            $like = '%' . $filters['driver'] . '%';
            $params[':driver'] = $like;
            $params[':driver_name'] = $like;
        }

        if (!empty($filters['business_from'])) {
            $conditions[] = 'c.business_date >= :business_from';
            $params[':business_from'] = $filters['business_from'];
        }

        if (!empty($filters['business_to'])) {
            $conditions[] = 'c.business_date <= :business_to';
            $params[':business_to'] = $filters['business_to'];
        }

        if (!empty($filters['reviewer'])) {
            $conditions[] = 'c.assigned_reviewer LIKE :reviewer';
            $params[':reviewer'] = '%' . $filters['reviewer'] . '%';
        }

        if (!empty($filters['opened_from'])) {
            $conditions[] = 'DATE(c.opened_at) >= :opened_from';
            $params[':opened_from'] = $filters['opened_from'];
        }

        if (!empty($filters['opened_to'])) {
            $conditions[] = 'DATE(c.opened_at) <= :opened_to';
            $params[':opened_to'] = $filters['opened_to'];
        }

        if (isset($filters['overdue_investigation']) && $filters['overdue_investigation']) {
            $conditions[] = "(c.due_date IS NOT NULL AND DATE(c.due_date) < :overdue_today AND c.status NOT IN ('Resolved', 'Closed'))";
            $params[':overdue_today'] = $filters['today'] ?? date('Y-m-d');
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params];
    }

    public function getRemittanceLog(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM remittance_logs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Posted ledger totals per case for list decoration.
     *
     * @param int[] $caseIds
     * @return array<int,array{reduce:float,increase:float}>
     */
    public function getPostedSums(array $caseIds): array
    {
        $caseIds = array_values(array_filter(array_map('intval', $caseIds)));
        if (!$caseIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($caseIds), '?'));
        $stmt = $this->db()->prepare("
            SELECT case_id,
                   COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE 0 END), 0) AS increase_total,
                   COALESCE(SUM(CASE WHEN direction <> 'increase' THEN amount ELSE 0 END), 0) AS reduce_total
            FROM discrepancy_ledger_entries
            WHERE state = 'posted'
              AND case_id IN ($placeholders)
            GROUP BY case_id
        ");
        $stmt->execute($caseIds);

        $sums = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $sums[(int) $row['case_id']] = [
                'reduce' => (float) $row['reduce_total'],
                'increase' => (float) $row['increase_total'],
            ];
        }
        return $sums;
    }

    /**
     * Case ids among the given list that have an undecided approval.
     *
     * @param int[] $caseIds
     * @return array<int,bool>
     */
    public function getPendingApprovalCaseIds(array $caseIds): array
    {
        $caseIds = array_values(array_filter(array_map('intval', $caseIds)));
        if (!$caseIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($caseIds), '?'));
        $stmt = $this->db()->prepare("
            SELECT DISTINCT case_id
            FROM discrepancy_approvals
            WHERE status = 'pending'
              AND case_id IN ($placeholders)
        ");
        $stmt->execute($caseIds);

        $ids = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $ids[(int) $row['case_id']] = true;
        }
        return $ids;
    }

    /**
     * Ledger rows for a case (all states, newest first).
     */
    public function getLedgerEntries(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM discrepancy_ledger_entries WHERE case_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Approval rows for a case (all statuses, newest first).
     */
    public function getApprovals(int $caseId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT * FROM discrepancy_approvals WHERE case_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Driver-day trips with cash and QR Ph breakdown, plus per-trip bus.
     * Live trips come from bus_trips; archived/generated trips from
     * passenger_trip_history. Payment method mapping: cash and the cash
     * portion of hybrid count as cash; gcash, QRPh, online, and non-cash
     * methods count as QR Ph / e-payment.
     */
    public function getTripBreakdown(string $driverId, string $businessDate): array
    {
        $trips = [];

        try {
            $stmt = $this->db()->prepare("
                SELECT bt.id AS trip_no, bt.bus_id, COALESCE(bt.total_passengers, 0) AS total_passengers,
                       COALESCE(SUM(pt.fare), 0) AS total_collection,
                       COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(pt.payment_method, ''))) IN ('cash', 'hybrid')
                                   THEN COALESCE(pt.cash_amount, pt.fare, 0) ELSE 0 END), 0) AS cash_total,
                       COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(pt.payment_method, ''))) IN ('gcash', 'qrph', 'online', 'non-cash', 'other')
                                   THEN COALESCE(pt.gcash_amount, pt.fare, 0) ELSE 0 END), 0) AS qrph_total
                FROM bus_trips bt
                LEFT JOIN passenger_trips pt
                    ON pt.trip_no = bt.id
                   AND LOWER(TRIM(COALESCE(pt.payment_status, ''))) = 'paid'
                WHERE bt.driver_id = ?
                  AND bt.trip_date = ?
                GROUP BY bt.id, bt.bus_id, bt.total_passengers
                ORDER BY bt.id ASC
            ");
            $stmt->execute([$driverId, $businessDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['source'] = 'live';
                $trips[] = $row;
            }
        } catch (\Throwable $e) {
            error_log('Discrepancy trip breakdown (live) failed: ' . $e->getMessage());
        }

        try {
            $stmt = $this->db()->prepare("
                SELECT COALESCE(NULLIF(TRIM(pth.trip_id), ''), CAST(pth.history_id AS CHAR)) AS trip_no,
                       pth.bus_number AS bus_id,
                       COUNT(*) AS total_passengers,
                       COALESCE(SUM(pth.fare), 0) AS total_collection,
                       COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(pth.payment_method, ''))) IN ('cash', 'hybrid')
                                   THEN COALESCE(pth.fare, 0) ELSE 0 END), 0) AS cash_total,
                       COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(pth.payment_method, ''))) IN ('gcash', 'qrph', 'online', 'non-cash', 'other')
                                   THEN COALESCE(pth.fare, 0) ELSE 0 END), 0) AS qrph_total
                FROM passenger_trip_history pth
                WHERE pth.driver_id = ?
                  AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = ?
                GROUP BY COALESCE(NULLIF(TRIM(pth.trip_id), ''), CAST(pth.history_id AS CHAR)), pth.bus_number
                ORDER BY MIN(pth.history_id) ASC
            ");
            $stmt->execute([$driverId, $businessDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['source'] = 'history';
                $trips[] = $row;
            }
        } catch (\Throwable $e) {
            error_log('Discrepancy trip breakdown (history) failed: ' . $e->getMessage());
        }

        return $trips;
    }

    /**
     * Payment references for the case: paid non-cash payments on the driver's
     * business day with their gateway/reference identifiers. Live rows come
     * from passenger_trips (PayMongo/GCash references); archived rows come
     * from passenger_trip_history, which does not carry gateway identifiers,
     * so their reference columns are reported as null.
     */
    public function getPaymentReferences(string $driverId, string $businessDate): array
    {
        $references = [];

        try {
            $stmt = $this->db()->prepare("
                SELECT pt.id AS payment_id,
                       pt.trip_no,
                       pt.fare,
                       pt.payment_method,
                       pt.payment_status,
                       pt.paymongo_payment_id,
                       pt.gcash_reference_no,
                       pt.boarding_time AS occurred_at,
                       'live' AS source
                FROM passenger_trips pt
                INNER JOIN bus_trips bt ON pt.trip_no = bt.id
                WHERE bt.driver_id = ?
                  AND bt.trip_date = ?
                  AND LOWER(TRIM(COALESCE(pt.payment_method, ''))) <> 'cash'
                  AND LOWER(TRIM(COALESCE(pt.payment_status, ''))) = 'paid'
                ORDER BY pt.boarding_time ASC, pt.id ASC
            ");
            $stmt->execute([$driverId, $businessDate]);
            $references = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Discrepancy payment references (live) failed: ' . $e->getMessage());
        }

        try {
            $stmt = $this->db()->prepare("
                SELECT NULL AS payment_id,
                       COALESCE(NULLIF(TRIM(pth.trip_id), ''), CAST(pth.history_id AS CHAR)) AS trip_no,
                       pth.fare,
                       pth.payment_method,
                       pth.payment_status,
                       NULL AS paymongo_payment_id,
                       NULL AS gcash_reference_no,
                       pth.trip_date AS occurred_at,
                       'history' AS source
                FROM passenger_trip_history pth
                WHERE pth.driver_id = ?
                  AND DATE(COALESCE(pth.trip_date, pth.date_booked)) = ?
                  AND LOWER(TRIM(COALESCE(pth.payment_method, ''))) <> 'cash'
                ORDER BY pth.history_id ASC
            ");
            $stmt->execute([$driverId, $businessDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $references[] = $row;
            }
        } catch (\Throwable $e) {
            error_log('Discrepancy payment references (history) failed: ' . $e->getMessage());
        }

        return $references;
    }
}
