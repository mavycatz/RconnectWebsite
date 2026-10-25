<?php

namespace Admin\Controllers\Announcement;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/FacebookService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Models/Notification.php';
require_once __DIR__ . '/../../migrations/add_announcement_author_role.php';
require_once __DIR__ . '/../../migrations/add_announcement_visibility.php';

use App\Core\Database;
use Admin\Services\FacebookService;

/**
 * =========================
 * ANNOUNCEMENT CONTROLLER
 * =========================
 *
 * Handles all announcement business logic:
 *   - Create / List / Delete / Repost to Facebook
 */
class AnnouncementController
{
    /**
     * Supported internal roles for Private announcement audiences.
     * Single source of truth for server-side validation and the
     * create-modal role picker in admin/views/announcement/index.php.
     */
    public const AUDIENCE_ROLES = [
        'super_admin'     => 'Super Admin',
        'admin'           => 'Admin',
        'finance_manager' => 'Finance Manager',
        'finance_officer' => 'Finance Officer',
        'inspector'       => 'Inspector',
        'driver'          => 'Driver',
        'dispatcher'      => 'Dispatcher',
    ];

    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    /**
     * Validate and normalize an announcement audience.
     *
     * @param string       $visibility  'public' or 'private'
     * @param array|string $targetRoles Role keys (array or comma-separated string)
     * @return array{0:string,1:string} [visibility, comma-separated target roles]
     * @throws \InvalidArgumentException on unsupported visibility or role values
     */
    public static function normalizeAudience(string $visibility, $targetRoles): array
    {
        $visibility = strtolower(trim($visibility));
        if (!in_array($visibility, ['public', 'private'], true)) {
            throw new \InvalidArgumentException('Invalid announcement audience type.');
        }

        if (is_string($targetRoles)) {
            $targetRoles = explode(',', $targetRoles);
        }
        if (!is_array($targetRoles)) {
            throw new \InvalidArgumentException('Invalid audience role selection.');
        }

        $roles = [];
        foreach ($targetRoles as $role) {
            $role = strtolower(trim((string)$role));
            if ($role === '') {
                continue;
            }
            if (!isset(self::AUDIENCE_ROLES[$role])) {
                throw new \InvalidArgumentException('Unknown audience role: ' . $role . '.');
            }
            $roles[$role] = true;
        }

        if ($visibility === 'public') {
            return ['public', ''];
        }

        if (empty($roles)) {
            throw new \InvalidArgumentException('Select at least one role for a private announcement.');
        }

        return ['private', implode(',', array_keys($roles))];
    }

    /**
     * A row written by an operational source (driver alert, bus problem,
     * inspector report, scheduled trip work) rather than the admin composer.
     * Operational rows always belong to the internal Team Feed, whatever the
     * default visibility column currently says.
     */
    public static function isOperationalRow(array $row): bool
    {
        $type = trim((string)($row['type'] ?? ''));
        if ($type !== '' && $type !== 'System Announcement') {
            return true;
        }
        foreach (['bus_id', 'driver_name', 'recording_url'] as $field) {
            if (trim((string)($row[$field] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Effective category/audience of a row, correcting legacy rows that are
     * still on the default public visibility. Returns [visibility, roles].
     * Operational rows default to the team-wide role set when no targets
     * were stored, so they remain reachable by the internal roles.
     *
     * @return array{0:string,1:array<int,string>}
     */
    public static function effectiveAudience(array $row): array
    {
        $stored = strtolower(trim((string)($row['visibility'] ?? '')));
        $isTeam = ($stored === 'private') || self::isOperationalRow($row);

        if (!$isTeam) {
            return ['public', []];
        }

        $targets = array_values(array_filter(array_map('trim', explode(',', (string)($row['target_roles'] ?? '')))));
        if (empty($targets)) {
            $targets = array_keys(self::AUDIENCE_ROLES);
        }

        return ['private', $targets];
    }

    /**
     * Audience predicate: keep only the announcements visible to a user.
     * Public Updates always pass; Team posts only for super admins, the
     * author, or users whose role is in the effective audience.
     *
     * @param bool $managementView  When true (the admin announcements page),
     *                              any `admin` also sees every Team post, since
     *                              that page is the administration surface.
     */
    public static function filterVisibleForUser(array $rows, string $role, string $username, bool $managementView = false): array
    {
        $role     = strtolower(trim($role));
        $username = trim($username);

        return array_values(array_filter($rows, static function (array $row) use ($role, $username, $managementView): bool {
            [$visibility, $targets] = self::effectiveAudience($row);
            if ($visibility !== 'private') {
                return true;
            }
            if ($role === 'super_admin') {
                return true;
            }
            if ($managementView && $role === 'admin') {
                return true;
            }
            if ($username !== '' && (string)($row['reported_by'] ?? '') === $username) {
                return true;
            }
            return in_array($role, $targets, true);
        }));
    }

    /**
     * -----------------------------------------
     * SAVE ANNOUNCEMENT
     * -----------------------------------------
     *
     * @param array $data  ['title', 'message', 'publish_date', 'post_to_fb', 'image_path']
     * @param int   $userId
     * @return array       ['success' => bool, 'announcement_id' => int|null,
     *                       'facebook_post_id' => string|null, 'facebook_error' => string|null]
     */
    public function save(array $data): array
    {
        $title       = trim($data['title'] ?? '');
        $message     = trim($data['message'] ?? '');
        $imagePath   = $data['image_path'] ?? null;
        $postToFb    = !empty($data['post_to_fb']);
        $publishDate = $data['publish_date'] ?? date('Y-m-d H:i:s');

        /* ---- Validate audience before any side effect ---- */
        [$visibility, $targetRoles] = self::normalizeAudience(
            (string)($data['visibility'] ?? 'public'),
            $data['target_roles'] ?? []
        );

        /* ---- Get current user name and role ---- */
        $userData = \App\Auth\Auth::user();
        $reportedBy = $userData['username'] ?? 'Admin';
        $authorRole = strtolower(trim((string)($userData['role'] ?? '')));
        $authorRole = $authorRole !== '' ? $authorRole : null;

        /* ---- Step 1: Insert into database ---- */
        \admin_ensure_announcement_author_role($this->db);
        \admin_ensure_announcement_visibility($this->db);

        $stmt = $this->db->prepare("
            INSERT INTO announcements
                (type, title, message, image_path, priority, status, reported_by, author_role, created_at, post_to_facebook, visibility, target_roles)
            VALUES
                ('System Announcement', :title, :message, :image_path, 'Normal', 'Active', :reported_by, :author_role, :created_at, :post_to_fb, :visibility, :target_roles)
        ");
        $stmt->execute([
            ':title'       => $title,
            ':message'     => $message,
            ':image_path'  => $imagePath,
            ':reported_by' => $reportedBy,
            ':author_role' => $authorRole,
            ':created_at'  => $publishDate,
            ':post_to_fb'  => $postToFb ? 1 : 0,
            ':visibility'  => $visibility,
            ':target_roles' => $visibility === 'private' ? $targetRoles : null,
        ]);

        $announcementId = (int)$this->db->lastInsertId();

        /* ---- Notify all admins about the new announcement ---- */
        try {
            $notificationModel = new \App\Models\Notification();
            $actorUserId = (int) ($userData['id'] ?? 0);
            $notificationModel->createForAdminRoles(
                'New Announcement',
                'New announcement posted: ' . $title,
                'info',
                'announcement',
                $announcementId,
                $actorUserId ?: null,
                'You posted an announcement',
                'You posted: ' . $title
            );
        } catch (\Throwable $e) {
            error_log("Announcement notification error: " . $e->getMessage());
        }

        $result = [
            'success'            => true,
            'announcement_id'    => $announcementId,
            'facebook_post_id'   => null,
            'facebook_error'     => null,
            'facebook_error_type' => null,
            'facebook_error_code'    => null,
            'facebook_error_subcode' => null,
        ];

        /* ---- Audit: Announcement Created ---- */
        $this->audit('Announcements', 'Create', "Created announcement: {$title}", [
            'title'        => $title,
            'image_path'   => $imagePath,
            'post_to_fb'   => $postToFb ? 'Yes' : 'No',
            'visibility'   => $visibility,
            'target_roles' => $visibility === 'private' ? $targetRoles : 'All internal roles',
        ]);

        /* ---- Step 2: Post to Facebook if checked ---- */

        if ($postToFb) {
            $fbResult = $this->postToFacebook($title, $message, $imagePath, $announcementId);

            $result['facebook_post_id']   = $fbResult['facebook_post_id'];
            $result['facebook_error']     = $fbResult['error'];
            $result['facebook_error_type'] = $fbResult['error_type'] ?? null;
            $result['facebook_error_code']    = $fbResult['error_code'] ?? null;
            $result['facebook_error_subcode'] = $fbResult['error_subcode'] ?? null;
        }

        return $result;
    }

    /**
     * -----------------------------------------
     * REPOST FAILED FACEBOOK POST
     * -----------------------------------------
     */
    public function repostToFacebook(int $announcementId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM announcements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $announcementId]);
        $announcement = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$announcement) {
            return ['success' => false, 'error' => 'Announcement not found.', 'error_type' => null];
        }

        return $this->postToFacebook(
            $announcement['title'],
            $announcement['message'],
            $announcement['image_path'],
            $announcementId
        );
    }

    /**
     * -----------------------------------------
     * DELETE ANNOUNCEMENT
     * -----------------------------------------
     */
    public function delete(int $announcementId): bool
    {
        /* Get title for audit before deleting */
        $info = $this->db->prepare("SELECT title, image_path FROM announcements WHERE id = :id");
        $info->execute([':id' => $announcementId]);
        $row = $info->fetch(\PDO::FETCH_ASSOC);
        $title = $row['title'] ?? 'Unknown';
        $image = $row['image_path'] ?? null;

        /* Delete uploaded image file if exists */
        if ($image && file_exists($_SERVER['DOCUMENT_ROOT'] . $image)) {
            @unlink($_SERVER['DOCUMENT_ROOT'] . $image);
        }

        $stmt = $this->db->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute([':id' => $announcementId]);
        $deleted = $stmt->rowCount() > 0;

        if ($deleted) {
            $this->audit('Announcements', 'Delete', "Deleted announcement ID {$announcementId}: {$title}");
        }

        return $deleted;
    }

    /**
     * -----------------------------------------
     * UPDATE ANNOUNCEMENT
     * -----------------------------------------
     *
     * Updates content, category/audience, and image only. Type, status,
     * created_at, reported_by, author_role, and Facebook fields are preserved.
     *
     * @param array $data ['title', 'message', 'visibility', 'target_roles',
     *                     'image_path' (new uploaded path|null), 'remove_image']
     */
    public function update(int $announcementId, array $data): array
    {
        $stmt = $this->db->prepare("SELECT * FROM announcements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $announcementId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$existing) {
            return ['success' => false, 'message' => 'Announcement not found.'];
        }

        $title   = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        [$visibility, $targetRoles] = self::normalizeAudience(
            (string)($data['visibility'] ?? 'public'),
            $data['target_roles'] ?? []
        );

        $imagePath   = $existing['image_path'] ?? null;
        $newImage    = $data['image_path'] ?? null;
        $removeImage = !empty($data['remove_image']);

        if ($newImage) {
            if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $imagePath);
            }
            $imagePath = $newImage;
        } elseif ($removeImage && $imagePath) {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $imagePath);
            }
            $imagePath = null;
        }

        $update = $this->db->prepare("
            UPDATE announcements
            SET title        = :title,
                message      = :message,
                image_path   = :image_path,
                visibility   = :visibility,
                target_roles = :target_roles
            WHERE id = :id
        ");
        $update->execute([
            ':title'        => $title,
            ':message'      => $message,
            ':image_path'   => $imagePath,
            ':visibility'   => $visibility,
            ':target_roles' => $visibility === 'private' ? $targetRoles : null,
            ':id'           => $announcementId,
        ]);

        $this->audit('Announcements', 'Update', "Updated announcement ID {$announcementId}: {$title}", [
            'title'        => $title,
            'visibility'   => $visibility,
            'target_roles' => $visibility === 'private' ? $targetRoles : 'All internal roles',
            'image_path'   => $imagePath,
        ]);

        return [
            'success'         => true,
            'announcement_id' => $announcementId,
            'image_path'      => $imagePath,
        ];
    }

    /**
     * -----------------------------------------
     * LIST ANNOUNCEMENTS
     * -----------------------------------------
     */
    public function list(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM announcements
            ORDER BY created_at DESC
        ");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $user     = \App\Auth\Auth::user() ?? [];
        $role     = (string)($user['role'] ?? '');
        $username = (string)($user['username'] ?? '');

        $visible = self::filterVisibleForUser($rows, $role, $username, true);

        /* Normalize the effective category so the feed tabs and cards are
           correct even while legacy rows still carry the public default. */
        return array_map(static function (array $row): array {
            [$visibility, $targets] = self::effectiveAudience($row);
            $row['visibility']   = $visibility;
            $row['target_roles'] = $visibility === 'private' ? implode(',', $targets) : null;
            return $row;
        }, $visible);
    }

    /**
     * -----------------------------------------
     * GET SINGLE ANNOUNCEMENT
     * -----------------------------------------
     */
    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM announcements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * -----------------------------------------
     * POST TO FACEBOOK (PRIVATE HELPER)
     * -----------------------------------------
     */
    protected function postToFacebook(string $title, string $message, ?string $imagePath, int $announcementId): array
    {
        /* ---- Load Facebook settings from database ---- */

        $getSetting = function (string $key, string $default = '') {
            try {
                $s = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                $s->execute([$key]);
                $r = $s->fetch(\PDO::FETCH_ASSOC);
                return $r ? $r['setting_value'] : $default;
            } catch (\Throwable $e) {
                return $default;
            }
        };

        $pageId  = $getSetting('facebook_page_id', '');
        $fbToken = $getSetting('facebook_page_access_token', '');

        /* Fallback to config file if DB values are empty */
        if (empty($pageId) || empty($fbToken)) {
            $fbConfig = require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/facebook.php';
            if (empty($pageId))  $pageId  = $fbConfig['page_id'] ?? '';
            if (empty($fbToken)) $fbToken = $fbConfig['access_token'] ?? '';
        }

        if (empty($pageId) || empty($fbToken)) {
            return [
                'success'          => false,
                'facebook_post_id' => null,
                'error'            => 'Facebook Page ID or Access Token not configured.',
                'error_type'       => null,
                'error_code'       => null,
                'error_subcode'    => null,
            ];
        }

        $apiVersion = ($fbConfig ?? [])['api_version'] ?? 'v23.0';

        error_log("Facebook post request for announcement #{$announcementId} (page_id={$pageId}, has_image=" . ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath) ? '1' : '0') . ")");

        $fbService  = new FacebookService($pageId, $fbToken, $apiVersion);

        /* ---- Build the message ---- */
        $fbMessage = "📢 {$title}\n\n{$message}";

        /* ---- Post with or without image ---- */
        if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
            $imageUrl = 'https://' . $_SERVER['HTTP_HOST'] . $imagePath;
            $fbResult = $fbService->postImage($fbMessage, $imageUrl);
        } else {
            $fbResult = $fbService->postText($fbMessage);
        }

        /* ---- Audit: Facebook Post ---- */
        $errorType = $fbResult['error_type'] ?? null;
        if ($fbResult['success']) {
            $this->audit('Announcements', 'Facebook Post', "Facebook post created for announcement ID {$announcementId}: {$title}", [
                'facebook_post_id' => $fbResult['facebook_post_id'],
            ]);
        } elseif ($errorType === 'token_expired') {
            $this->audit('Announcements', 'Facebook Token Expired', "Facebook token expired for announcement ID {$announcementId}: {$title} — {$fbResult['error']}", null, 'Failed');
        } else {
            error_log("Facebook post FAILED for announcement #{$announcementId}: type=" . ($errorType ?: 'other') . " code=" . ($fbResult['error_code'] ?? '') . " subcode=" . ($fbResult['error_subcode'] ?? '') . " error=" . ($fbResult['error'] ?? 'unknown'));
            $this->audit('Announcements', 'Facebook Post Failed', "Facebook post failed for announcement ID {$announcementId}: {$title} — {$fbResult['error']}", null, 'Failed');
        }

        /* ---- Update announcement record ---- */
        if ($fbResult['success']) {
            $update = $this->db->prepare("
                UPDATE announcements
                SET facebook_posted     = 1,
                    facebook_post_id    = :fb_id,
                    facebook_posted_at  = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':fb_id' => $fbResult['facebook_post_id'],
                ':id'    => $announcementId,
            ]);
        }

        return [
            'success'          => $fbResult['success'],
            'facebook_post_id' => $fbResult['facebook_post_id'],
            'error'            => $fbResult['error'],
            'error_type'       => $fbResult['error_type'] ?? null,
            'error_code'       => $fbResult['error_code'] ?? null,
            'error_subcode'    => $fbResult['error_subcode'] ?? null,
        ];
    }

    /**
     * -----------------------------------------
     * AUDIT LOG HELPER
     * -----------------------------------------
     */
    private function audit(string $module, string $action, string $description, ?array $newValues = null, string $status = 'Success'): void
    {
        try {
            \AuditService::log($module, $action, $description, null, null, $newValues, $status);
        } catch (\Throwable $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}
