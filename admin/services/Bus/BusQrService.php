<?php declare(strict_types=1);

namespace Admin\Services\Bus;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Bus QR credentials.
 *
 * Every bus owns one active QR credential bound to the bus's permanent
 * internal identifier (`buses.id`). Credentials are created automatically at
 * registration, persist across assignment changes, and are only retired by an
 * explicit, authorized replacement or revocation. A retired token is never
 * reused, and only the active credential resolves at scan time.
 */
final class BusQrService
{
    public const TABLE = 'bus_qr_credentials';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Idempotent schema bootstrap. Callers that open a transaction must call
     * this before `beginTransaction()` (MySQL DDL implicitly commits).
     */
    public static function ensureSchema(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' ('
            . ' credential_id VARCHAR(64) PRIMARY KEY,'
            . ' bus_ref INTEGER NOT NULL,'
            . ' token VARCHAR(64) NOT NULL,'
            . ' status VARCHAR(16) NOT NULL DEFAULT \'active\','
            . ' payload_url VARCHAR(255) NULL,'
            . ' image_path VARCHAR(255) NULL,'
            . ' created_at VARCHAR(32) NULL,'
            . ' created_by VARCHAR(120) NULL,'
            . ' replaced_at VARCHAR(32) NULL,'
            . ' reason VARCHAR(255) NULL'
            . ')'
        );

        try {
            if (self::driverName($db) === 'sqlite') {
                $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_bus_qr_token ON ' . self::TABLE . ' (token)');
            } else {
                $db->exec('ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX uq_bus_qr_token (token)');
            }
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            // 1061 = index already exists; 1062 = legacy duplicates exist,
            // which the code-level checks still keep from growing.
            if ($code !== 1061 && $code !== 1062) {
                error_log('BusQrService: unique index not applied: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            // Non-fatal: uniqueness is also enforced by tokenExists().
        }
    }

    public static function driverName(PDO $db): string
    {
        try {
            return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * The scan payload: keeps the compatible `bus` deep link and adds the
     * opaque credential token. Carries no plate, driver, or employee data.
     */
    public static function buildPayloadUrl(string $origin, string $busId, string $token): string
    {
        return rtrim($origin, '/')
            . '/passenger/scan.php?bus=' . rawurlencode($busId)
            . '&t=' . rawurlencode($token);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function activeFor(int $busRef): ?array
    {
        $this->ensureWhenSafe();

        $stmt = $this->db->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE bus_ref = ? AND status = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$busRef, self::STATUS_ACTIVE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>> newest first
     */
    public function historyFor(int $busRef, int $limit = 20): array
    {
        $this->ensureWhenSafe();
        $limit = max(1, min(100, $limit));

        $stmt = $this->db->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE bus_ref = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$busRef]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function resolveByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $this->ensureWhenSafe();

        $stmt = $this->db->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE token = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$token, self::STATUS_ACTIVE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Resolve an active credential that belongs to the given bus.
     *
     * @return array<string,mixed>|null
     */
    public function resolveForBus(string $token, int $busRef): ?array
    {
        $row = $this->resolveByToken($token);
        if ($row === null || (int)($row['bus_ref'] ?? 0) !== $busRef) {
            return null;
        }

        return $row;
    }

    /**
     * True when the token appears anywhere in the credential history.
     */
    public function tokenExists(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return true;
        }
        $this->ensureWhenSafe();

        $stmt = $this->db->prepare('SELECT 1 FROM ' . self::TABLE . ' WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Return the bus's active credential, creating it once when absent.
     * Safe to call repeatedly; never creates a second credential.
     *
     * @return array<string,mixed>
     */
    public function ensureForBus(int $busRef, string $busId, string $origin, ?string $actor): array
    {
        $existing = $this->activeFor($busRef);
        if ($existing !== null) {
            return $existing;
        }

        $this->retireActive($busRef, null, 'superseded during credential creation');

        return $this->insertCredential($busRef, $busId, $origin, $actor, null);
    }

    /**
     * Replace the bus's active credential with a fresh unique one.
     *
     * @return array<string,mixed>
     */
    public function replace(int $busRef, string $busId, string $origin, ?string $actor, string $reason): array
    {
        $this->retireActive($busRef, $actor, $reason);

        return $this->insertCredential($busRef, $busId, $origin, $actor, $reason);
    }

    /**
     * Revoke the bus's active credential. Returns false when none was active.
     */
    public function revoke(int $busRef, ?string $actor, string $reason): bool
    {
        return $this->retireActive($busRef, $actor, $reason);
    }

    /**
     * @return array<string,mixed>
     */
    public function markImage(string $credentialId, string $imagePath): void
    {
        $this->ensureWhenSafe();

        $stmt = $this->db->prepare('UPDATE ' . self::TABLE . ' SET image_path = ? WHERE credential_id = ?');
        $stmt->execute([$imagePath, $credentialId]);
    }

    /**
     * @return array<string,mixed> the newly stored credential row
     */
    private function insertCredential(int $busRef, string $busId, string $origin, ?string $actor, ?string $reason): array
    {
        $this->ensureWhenSafe();

        $token = $this->generateToken();
        $attempts = 0;
        while ($this->tokenExists($token)) {
            $token = $this->generateToken();
            if (++$attempts > 10) {
                throw new RuntimeException('Could not generate a unique bus QR token.');
            }
        }

        $credentialId = bin2hex(random_bytes(16));
        $now = gmdate('Y-m-d H:i:s');
        $payload = self::buildPayloadUrl($origin, $busId, $token);

        $stmt = $this->db->prepare(
            'INSERT INTO ' . self::TABLE
            . ' (credential_id, bus_ref, token, status, payload_url, image_path, created_at, created_by, replaced_at, reason)'
            . ' VALUES (?, ?, ?, ?, ?, NULL, ?, ?, NULL, ?)'
        );
        $stmt->execute([
            $credentialId,
            $busRef,
            $token,
            self::STATUS_ACTIVE,
            $payload,
            $now,
            $actor !== null && trim($actor) !== '' ? mb_substr(trim($actor), 0, 120) : null,
            $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
        ]);

        $created = $this->activeFor($busRef);
        if ($created === null) {
            throw new RuntimeException('The bus QR credential could not be read back after creation.');
        }

        return $created;
    }

    private function retireActive(int $busRef, ?string $actor, string $reason): bool
    {
        $this->ensureWhenSafe();

        $stmt = $this->db->prepare(
            'UPDATE ' . self::TABLE
            . ' SET status = ?, replaced_at = ?, reason = ?'
            . ' WHERE bus_ref = ? AND status = ?'
        );
        $stmt->execute([
            self::STATUS_REVOKED,
            gmdate('Y-m-d H:i:s'),
            $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
            $busRef,
            self::STATUS_ACTIVE,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function ensureWhenSafe(): void
    {
        if (!$this->db->inTransaction()) {
            self::ensureSchema($this->db);
        }
    }
}
