<?php

namespace App\Audit;

require_once __DIR__ . '/../Core/Database.php';

use App\Core\Database;

class Audit
{
    public static function log(
        string $module,
        string $action,
        ?string $description = null,
        ?int $userId = null,
        ?string $username = null,
        ?string $userRole = null,
        ?string $recordId = null,
        string $status = 'Success'
    ): int {
        $db = Database::get();
        $sql = "INSERT INTO audit_trail
                (user_id, username, user_role, module, action, record_id, description, ip_address, user_agent, status)
                VALUES
                (:user_id, :username, :user_role, :module, :action, :record_id, :description, :ip_address, :user_agent, :status)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':user_role' => $userRole,
            ':module' => $module,
            ':action' => $action,
            ':record_id' => $recordId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':status' => $status,
        ]);
        return (int)$db->lastInsertId();
    }
}
