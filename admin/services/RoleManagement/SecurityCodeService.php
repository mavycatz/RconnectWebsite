<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

use PDO;

/**
 * Security Authorization Code for Role Management writes.
 *
 * A Super Admin sets one system-wide code (stored as a password hash). Any
 * protected write — account add/edit/archive/restore, or publishing the
 * official dispatch queue — must be verified with that code first.
 * Verification issues a single-use, short-lived token bound to the actor, the
 * action, the target and a hash of the exact payload, so a token cannot be
 * replayed, reused for another action, or paired with a modified request.
 *
 * Everything here is DB-portable: only generic SQL types and prepared
 * statements are used so the same schema boots on MySQL and SQLite.
 */
final class SecurityCodeService
{
    public const TOKEN_TTL = 300;        // 5 minutes
    public const FAILURE_WINDOW = 900;   // 15 minutes
    public const MAX_FAILURES = 5;       // per actor, per window
    public const LOCKOUT = 900;          // 15 minutes
    public const MIN_CODE_LENGTH = 6;
    public const RATE_WINDOW = 300;      // 5 minutes
    public const RATE_MAX = 30;          // protected actions per actor per window

    /** Keys never included in the canonical payload hash. */
    private const HASH_SKIP = [
        'security_token',
        'action',
        'verify_action',
        '_csrf',
        '_csrf_token',
        'code',
        'current_code',
        'new_code',
        'confirm_code',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Idempotent schema bootstrap.
     */
    public static function ensureSchema(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS role_security_settings ('
            . ' id INTEGER PRIMARY KEY,'
            . ' code_hash VARCHAR(255) NULL,'
            . ' updated_at VARCHAR(32) NULL,'
            . ' updated_by INTEGER NULL'
            . ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS role_security_verifications ('
            . ' token_hash VARCHAR(64) PRIMARY KEY,'
            . ' user_id INTEGER NOT NULL,'
            . ' action VARCHAR(16) NOT NULL,'
            . ' target VARCHAR(191) NOT NULL,'
            . ' payload_hash VARCHAR(64) NOT NULL,'
            . ' created_ts INTEGER NOT NULL,'
            . ' expires_ts INTEGER NOT NULL,'
            . ' used_at VARCHAR(32) NULL'
            . ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS role_security_events ('
            . ' event_id VARCHAR(64) PRIMARY KEY,'
            . ' user_id INTEGER NULL,'
            . ' username VARCHAR(120) NULL,'
            . ' event VARCHAR(32) NOT NULL,'
            . ' detail VARCHAR(400) NULL,'
            . ' created_ts INTEGER NOT NULL'
            . ')'
        );
    }

    /**
     * Map a displayed account role to the security target group the guard
     * checks against. Dispatchers are managed under "inspector" accounts and
     * Finance Officers/Managers under "finance" accounts.
     */
    public static function targetLabel(string $role): string
    {
        $role = strtolower(trim($role));

        if (in_array($role, ['inspector', 'dispatcher'], true)) {
            return 'inspector';
        }

        if (in_array($role, ['finance_officer', 'finance_manager'], true)) {
            return 'finance';
        }

        return $role;
    }

    public static function isPrivilegedTarget(string $targetLabel): bool
    {
        return in_array($targetLabel, ['admin', 'super_admin'], true);
    }

    /**
     * Security target string shared by the verifier and the write guard:
     * "label" or "label:ref". Refs that are empty (or "0") are ignored so the
     * browser and the server always produce exactly the same value.
     */
    public static function guardTarget(string $targetRole, ?string $targetRef): string
    {
        $label = self::targetLabel($targetRole);
        $ref = $targetRef === null ? '' : trim($targetRef);

        if ($ref !== '' && $ref !== '0') {
            return $label . ':' . $ref;
        }

        return $label;
    }

    /**
     * Whether the actor may perform the action on the target group.
     *
     * Admin and Super Admin accounts are Super-Admin-only. Drivers, inspectors,
     * finance accounts, and buses may be managed by any administrator — but
     * only after security verification. Publishing the official dispatch queue
     * is a fleet-wide action available to administrators after verification.
     */
    public static function actionAllowed(string $actorRole, string $action, string $targetLabel): bool
    {
        $actorRole = strtolower(trim($actorRole));
        $action = strtolower(trim($action));
        $targetLabel = strtolower(trim($targetLabel));

        if ($action === 'publish') {
            return $targetLabel === 'dispatch' && in_array($actorRole, ['admin', 'super_admin'], true);
        }

        if (!in_array($action, ['add', 'edit', 'archive', 'restore'], true)) {
            return false;
        }

        if (self::isPrivilegedTarget($targetLabel)) {
            return $actorRole === 'super_admin';
        }

        if (!in_array($targetLabel, ['driver', 'inspector', 'finance', 'bus'], true)) {
            return false;
        }

        return in_array($actorRole, ['admin', 'super_admin'], true);
    }

    public static function requiresVerification(string $action): bool
    {
        return in_array(strtolower(trim($action)), ['add', 'edit', 'archive', 'restore', 'publish'], true);
    }

    /**
     * Canonical hash of the fields a request is about to write.
     *
     * Keys are lowercased and sorted, sensitive/transport fields are skipped,
     * scalars are normalized to trimmed strings (booleans to 1/0) and joined
     * with '&' before hashing. The browser builds the identical string so a
     * tampered payload can never match the verified hash.
     *
     * @param array<string,mixed> $fields
     */
    public static function canonicalHash(array $fields): string
    {
        $clean = [];

        foreach ($fields as $key => $value) {
            $key = strtolower(trim((string)$key));

            if ($key === '' || in_array($key, self::HASH_SKIP, true)) {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $clean[$key] = trim((string)$value);
        }

        ksort($clean, SORT_STRING);

        $parts = [];
        foreach ($clean as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return hash('sha256', implode('&', $parts));
    }

    public function isConfigured(): bool
    {
        $hash = $this->codeHash();

        return $hash !== '';
    }

    public function verifyCode(string $code): bool
    {
        $hash = $this->codeHash();

        if ($hash === '') {
            return false;
        }

        return password_verify($code, $hash);
    }

    /**
     * Store a new code. Rotating the code invalidates outstanding tokens.
     */
    public function setCode(string $code, int $userId): void
    {
        $hash = password_hash($code, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'UPDATE role_security_settings SET code_hash = ?, updated_at = ?, updated_by = ? WHERE id = 1'
        );
        $stmt->execute([$hash, gmdate('Y-m-d H:i:s'), $userId]);

        if ($stmt->rowCount() === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO role_security_settings (id, code_hash, updated_at, updated_by) VALUES (1, ?, ?, ?)'
            );
            $stmt->execute([$hash, gmdate('Y-m-d H:i:s'), $userId]);
        }

        $this->invalidateOutstanding();
    }

    /**
     * @return array{at:?string,by:?int}
     */
    public function lastUpdated(): array
    {
        $row = $this->settingsRow();

        return [
            'at' => $row['updated_at'] ?? null,
            'by' => isset($row['updated_by']) ? (int)$row['updated_by'] : null,
        ];
    }

    public function invalidateOutstanding(): void
    {
        $this->db->exec('DELETE FROM role_security_verifications WHERE used_at IS NULL');
    }

    /**
     * Issue a single-use verification token.
     *
     * @return array{token:string,expires_in:int}
     */
    public function issueToken(int $userId, string $action, string $target, string $payloadHash): array
    {
        $this->cleanupExpired();

        $token = bin2hex(random_bytes(32));
        $now = time();

        $stmt = $this->db->prepare(
            'INSERT INTO role_security_verifications '
            . '(token_hash, user_id, action, target, payload_hash, created_ts, expires_ts, used_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NULL)'
        );
        $stmt->execute([
            hash('sha256', $token),
            $userId,
            $action,
            $target,
            $payloadHash,
            $now,
            $now + self::TOKEN_TTL,
        ]);

        return ['token' => $token, 'expires_in' => self::TOKEN_TTL];
    }

    /**
     * Consume a token. Succeeds only when it exists, is unused, unexpired and
     * bound to the same actor, action, target and payload.
     */
    public function consumeToken(string $rawToken, int $userId, string $action, string $target, string $payloadHash): bool
    {
        if ($rawToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        // Mark used atomically; only one caller can win this UPDATE.
        $stmt = $this->db->prepare(
            'UPDATE role_security_verifications SET used_at = ? '
            . 'WHERE token_hash = ? AND user_id = ? AND action = ? AND target = ? AND payload_hash = ? '
            . 'AND used_at IS NULL AND expires_ts >= ?'
        );
        $stmt->execute([
            gmdate('Y-m-d H:i:s'),
            $tokenHash,
            $userId,
            $action,
            $target,
            $payloadHash,
            time(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function recordEvent(?int $userId, ?string $username, string $event, string $detail = ''): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO role_security_events (event_id, user_id, username, event, detail, created_ts) '
                . 'VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                bin2hex(random_bytes(16)),
                $userId,
                $username !== null ? mb_substr($username, 0, 120) : null,
                mb_substr($event, 0, 32),
                $detail !== '' ? mb_substr($detail, 0, 400) : null,
                time(),
            ]);
        } catch (\Throwable $ignore) {
            // Security events must never break the request they describe.
        }
    }

    /**
     * Most recent verification event rows for the Security settings activity.
     *
     * @return array<int,array{event:string,detail:string,username:string,at:string}>
     */
    public function activity(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));

        $stmt = $this->db->prepare(
            'SELECT event, detail, username, created_ts FROM role_security_events ORDER BY created_ts DESC LIMIT ' . $limit
        );
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'event' => (string)($row['event'] ?? ''),
                'detail' => (string)($row['detail'] ?? ''),
                'username' => (string)($row['username'] ?? ''),
                'at' => gmdate('Y-m-d H:i:s', (int)($row['created_ts'] ?? 0)),
            ];
        }

        return $rows;
    }

    /**
     * Failed verification attempts by the actor in the current window.
     */
    public function failuresRecent(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM role_security_events '
            . 'WHERE user_id = ? AND event = ? AND created_ts >= ?'
        );
        $stmt->execute([$userId, 'failed', time() - self::FAILURE_WINDOW]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Protected actions completed by the actor recently (rate limiting).
     */
    public function usedRecent(int $userId, int $window = self::RATE_WINDOW): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM role_security_events '
            . 'WHERE user_id = ? AND event = ? AND created_ts >= ?'
        );
        $stmt->execute([$userId, 'used', time() - max(1, $window)]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * When the actor is locked out, the UTC timestamp the lockout ends at;
     * null when they are not locked out.
     */
    public function lockoutUntil(int $userId): ?string
    {
        $failedTimes = $this->failedTimestamps($userId);

        if (count($failedTimes) < self::MAX_FAILURES) {
            return null;
        }

        // The lockout starts at the MAX_FAILURES-th most recent failure.
        $start = $failedTimes[self::MAX_FAILURES - 1];
        $until = $start + self::LOCKOUT;

        return $until > time() ? gmdate('Y-m-d H:i:s', $until) : null;
    }

    /**
     * @return int[] newest first
     */
    private function failedTimestamps(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT created_ts FROM role_security_events '
            . 'WHERE user_id = ? AND event = ? AND created_ts >= ? ORDER BY created_ts DESC'
        );
        $stmt->execute([$userId, 'failed', time() - self::FAILURE_WINDOW]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function cleanupExpired(): void
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM role_security_verifications WHERE used_at IS NOT NULL OR expires_ts < ?'
            );
            $stmt->execute([time() - self::TOKEN_TTL]);
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function settingsRow(): ?array
    {
        try {
            $stmt = $this->db->query('SELECT code_hash, updated_at, updated_by FROM role_security_settings WHERE id = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function codeHash(): string
    {
        $row = $this->settingsRow();

        return (string)($row['code_hash'] ?? '');
    }
}
