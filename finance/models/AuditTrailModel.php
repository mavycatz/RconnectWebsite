<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;
use App\Auth\Auth;

class AuditTrailModel
{
    public function initTables(?\PDO $db = null): void
    {
        try {
            $db = $db ?? Database::get();
            $db->exec("
                CREATE TABLE IF NOT EXISTS audit_trail (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    username VARCHAR(100) NULL,
                    user_role VARCHAR(100) NULL,
                    module VARCHAR(100) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    record_id VARCHAR(100) NULL,
                    description TEXT NULL,
                    previous_values LONGTEXT NULL,
                    new_values LONGTEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(500) NULL,
                    status ENUM('Success','Failed') NOT NULL DEFAULT 'Success',
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_module (module),
                    INDEX idx_action (action),
                    INDEX idx_username (username),
                    INDEX idx_user_role (user_role),
                    INDEX idx_status (status),
                    INDEX idx_is_read (is_read),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            try {
                $db->exec("ALTER TABLE audit_trail ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
            } catch(\Exception $e) {
                // column already exists
            }
        } catch(\Exception $e) {
            // silently pass
        }
    }

    public function log(
        string $module,
        string $action,
        ?string $description = null,
        ?int $userId = null,
        ?string $username = null,
        ?string $userRole = null,
        ?string $recordId = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        string $status = 'Success',
        ?\PDO $db = null
    ): int {
        $db = $db ?? Database::get();
        $sql = "INSERT INTO audit_trail
                (user_id, username, user_role, module, action, record_id, description, previous_values, new_values, ip_address, user_agent, status)
                VALUES
                (:user_id, :username, :user_role, :module, :action, :record_id, :description, :previous_values, :new_values, :ip_address, :user_agent, :status)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':user_role' => $userRole,
            ':module' => $module,
            ':action' => $action,
            ':record_id' => $recordId,
            ':description' => $description,
            ':previous_values' => $previousValues ? json_encode($previousValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':status' => $status,
        ]);
        return (int)$db->lastInsertId();
    }

    public function getTrails(
        ?string $search = null,
        ?string $module = null,
        ?string $action = null,
        ?string $username = null,
        ?string $userRole = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sort = 'DESC',
        int $page = 1,
        int $perPage = 25
    ): array {
        $db = Database::get();
        $conditions = [];
        $params = [];

        $conditions[] = "user_role IN ('finance_manager', 'finance_officer')";
        $conditions[] = "module != 'Login'";

        if ($search) {
            $conditions[] = "(username LIKE :search OR record_id LIKE :search2 OR description LIKE :search3)";
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
            $params[':search3'] = '%' . $search . '%';
        }
        if ($module) { $conditions[] = "module = :module"; $params[':module'] = $module; }
        if ($action) { $conditions[] = "action = :action"; $params[':action'] = $action; }
        if ($username) { $conditions[] = "username LIKE :username"; $params[':username'] = '%' . $username . '%'; }
        if ($userRole) { $conditions[] = "user_role = :user_role"; $params[':user_role'] = $userRole; }
        if ($status) { $conditions[] = "status = :status"; $params[':status'] = $status; }
        if ($dateFrom) { $conditions[] = "DATE(created_at) >= :date_from"; $params[':date_from'] = $dateFrom; }
        if ($dateTo) { $conditions[] = "DATE(created_at) <= :date_to"; $params[':date_to'] = $dateTo; }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sortDir = strtoupper($sort) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) FROM audit_trail $where";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT * FROM audit_trail $where ORDER BY created_at $sortDir, id $sortDir LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getById(int $id): ?array
    {
        $db = Database::get();
        $stmt = $db->prepare("SELECT * FROM audit_trail WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['previous_values']) $row['previous_values'] = json_decode($row['previous_values'], true);
            if ($row['new_values']) $row['new_values'] = json_decode($row['new_values'], true);
        }
        return $row ?: null;
    }

    public function getModules(): array
    {
        $db = Database::get();
        $stmt = $db->query("SELECT DISTINCT module FROM audit_trail ORDER BY module");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getActions(): array
    {
        $db = Database::get();
        $stmt = $db->query("SELECT DISTINCT action FROM audit_trail ORDER BY action");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getRecentUnread(string $userRole, int $limit = 20): array
    {
        $db = Database::get();
        $stmt = $db->prepare("
            SELECT id, user_id, username, user_role, module, action,
                   record_id, description, status, is_read, created_at
            FROM audit_trail
            WHERE module = 'Finance'
              AND (user_role = :role OR user_role IS NULL)
              AND is_read = 0
            ORDER BY created_at DESC, id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':role', $userRole, \PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markAllRead(string $userRole): void
    {
        $db = Database::get();
        $stmt = $db->prepare("
            UPDATE audit_trail
            SET is_read = 1
            WHERE module = 'Finance'
              AND (user_role = :role OR user_role IS NULL)
              AND is_read = 0
        ");
        $stmt->execute([':role' => $userRole]);
    }

    /**
     * Reusable, backend-trusted audit logger.
     *
     * The authenticated user is ALWAYS resolved from the session
     * (App\Auth\Auth), never from request input, so the audit trail
     * cannot be falsified by a client.
     */
    public static function record(
        string $module,
        string $action,
        ?string $description = null,
        ?string $recordId = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        string $status = 'Success',
        ?\PDO $db = null
    ): int {
        $user = $_SESSION['user'] ?? Auth::user() ?? [];
        $model = new self();
        $model->initTables($db);
        return $model->log(
            $module,
            $action,
            $description,
            isset($user['id']) ? (int) $user['id'] : null,
            $user['username'] ?? 'System',
            $user['role'] ?? null,
            $recordId,
            $previousValues,
            $newValues,
            $status,
            $db
        );
    }

    /**
     * Fetch audit entries for a specific record (used by detail/history views).
     */
    public function getByRecordId(string $recordId, ?string $module = null, ?\PDO $db = null): array
    {
        $db = $db ?? Database::get();
        $sql = "SELECT * FROM audit_trail WHERE record_id = :rid";
        $params = [':rid' => $recordId];
        if ($module) {
            $sql .= " AND module = :mod";
            $params[':mod'] = $module;
        }
        $sql .= " ORDER BY created_at DESC, id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['previous_values']) {
                $row['previous_values'] = json_decode($row['previous_values'], true);
            }
            if ($row['new_values']) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
        }
        return $rows;
    }
}
