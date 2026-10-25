<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Notification
{
    public const ADMIN_ROLES = ['admin', 'super_admin'];

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $instance = $pdo ?? Database::tryGet();
        if ($instance === null) {
            throw new \RuntimeException("Database unavailable for Notification");
        }
        $this->db = $instance;
    }

    public function getUnread(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, description, type, reference_type, reference_id, created_at, is_read
            FROM notifications
            WHERE user_id = :user_id
              AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * All notifications created today (Manila time), read or unread,
     * plus the unread count for the badge.
     *
     * created_at is written with MySQL NOW() (server time, UTC), so
     * "today" is computed as Manila midnight converted to UTC.
     */
    public function getToday(int $userId): array
    {
        date_default_timezone_set('Asia/Manila');

        $startOfTodayUtc =
            (new \DateTime(
                'midnight',
                new \DateTimeZone('Asia/Manila')
            ))
            ->setTimezone(
                new \DateTimeZone('UTC')
            )
            ->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            SELECT id, title, description, type, reference_type, reference_id, created_at, is_read
            FROM notifications
            WHERE user_id = :user_id
              AND created_at >= :start
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([
            'user_id' => $userId,
            'start' => $startOfTodayUtc,
        ]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND is_read = 0
              AND created_at >= :start
        ");
        $countStmt->execute([
            'user_id' => $userId,
            'start' => $startOfTodayUtc,
        ]);

        return [
            'items' => $items,
            'unread' => (int) $countStmt->fetchColumn(),
        ];
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = :user_id
              AND is_read = 0
        ");
        $stmt->execute(['user_id' => $userId]);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id
              AND user_id = :user_id
              AND is_read = 0
        ");
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    public function create(
        int $userId,
        string $title,
        string $description,
        string $type = 'info',
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, description, type, reference_type, reference_id, created_at, is_read)
            VALUES (:user_id, :title, :description, :type, :reference_type, :reference_id, NOW(), 0)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createForRole(
        string $role,
        string $title,
        string $description,
        string $type,
        string $referenceType,
        int $referenceId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, description, type, reference_type, reference_id, created_at, is_read)
            SELECT id, :title, :description, :type, :ref_type, :ref_id, NOW(), 0
            FROM users
            WHERE role = :role
              AND NOT EXISTS (
                  SELECT 1 FROM notifications n
                  WHERE n.user_id = users.id
                    AND n.reference_type = :ref_type_check
                    AND n.reference_id = :ref_id_check
                    AND n.title = :title_check
              )
        ");
        $stmt->execute([
            'role' => $role,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'ref_type' => $referenceType,
            'ref_id' => $referenceId,
            'ref_type_check' => $referenceType,
            'ref_id_check' => $referenceId,
            'title_check' => $title,
        ]);
        return $stmt->rowCount();
    }

    public function createForAdminRoles(
        string $title,
        string $description,
        string $type,
        string $referenceType,
        int $referenceId,
        ?int $actorUserId = null,
        ?string $actorTitle = null,
        ?string $actorDescription = null
    ): int {
        $created = 0;

        if ($actorUserId !== null && $actorTitle !== null) {
            $created += $this->createForActor(
                $actorUserId,
                $actorTitle,
                $actorDescription ?? $description,
                $type,
                $referenceType,
                $referenceId
            );
        }

        foreach (self::ADMIN_ROLES as $role) {
            $created += $this->createForRole($role, $title, $description, $type, $referenceType, $referenceId);
        }

        return $created;
    }

    public function createForActor(
        int $userId,
        string $title,
        string $description,
        string $type,
        string $referenceType,
        int $referenceId
    ): int {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $role = $stmt->fetchColumn();

        if (!in_array($role, self::ADMIN_ROLES, true)) {
            return 0;
        }

        $check = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND reference_type = :reference_type
              AND reference_id = :reference_id
              AND title = :title
        ");
        $check->execute([
            'user_id' => $userId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'title' => $title,
        ]);

        if ((int) $check->fetchColumn() > 0) {
            return 0;
        }

        return $this->create($userId, $title, $description, $type, $referenceType, $referenceId);
    }
}
