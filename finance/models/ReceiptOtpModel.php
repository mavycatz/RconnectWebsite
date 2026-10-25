<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;

class ReceiptOtpModel
{
    private const LIFETIME_SECONDS = 300;

    /**
     * =========================
     * ENSURE TABLE EXISTS
     * =========================
     */
    public function initTables(): void
    {
        try {
            $db = Database::get();
            $db->exec("
                CREATE TABLE IF NOT EXISTS receipt_otps (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    remittance_id INT NOT NULL,
                    otp_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_receipt_otp_user (user_id),
                    INDEX idx_receipt_otp_remittance (remittance_id),
                    INDEX idx_receipt_otp_expiry (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Exception $e) {
            // silently pass
        }
    }

    /**
     * =========================
     * GENERATE + STORE OTP
     * Returns ['otp' => string, 'expires_at' => string]
     * =========================
     */
    public function create(
        int $userId,
        int $remittanceId
    ): array {
        $this->initTables();

        $this->invalidate($userId, $remittanceId);

        $otp = (string) random_int(100000, 999999);

        $expiresAt = gmdate(
            'Y-m-d H:i:s',
            time() + self::LIFETIME_SECONDS
        );

        $db = Database::get();

        $stmt = $db->prepare("

            INSERT INTO receipt_otps (
                user_id,
                remittance_id,
                otp_hash,
                expires_at
            )
            VALUES (?, ?, ?, ?)

        ");

        $stmt->execute([
            $userId,
            $remittanceId,
            hash('sha256', $otp),
            $expiresAt
        ]);

        return [
            'otp' => $otp,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * =========================
     * INVALIDATE PRIOR UNUSED OTPS
     * =========================
     */
    public function invalidate(
        int $userId,
        int $remittanceId
    ): void {
        $this->initTables();

        $db = Database::get();

        $stmt = $db->prepare("

            UPDATE receipt_otps

            SET used_at = NOW()

            WHERE user_id = ?

            AND remittance_id = ?

            AND used_at IS NULL

        ");

        $stmt->execute([
            $userId,
            $remittanceId
        ]);
    }

    /**
     * =========================
     * VERIFY OTP (single-use, expired-safe)
     * =========================
     */
    public function verify(
        int $userId,
        int $remittanceId,
        string $otp
    ): bool {
        $this->initTables();

        if ($otp === '' || !preg_match('/^\d{6}$/', $otp)) {
            return false;
        }

        $db = Database::get();

        $stmt = $db->prepare("

            SELECT id, otp_hash, expires_at

            FROM receipt_otps

            WHERE user_id = ?

            AND remittance_id = ?

            AND used_at IS NULL

            ORDER BY id DESC

            LIMIT 1

        ");

        $stmt->execute([
            $userId,
            $remittanceId
        ]);

        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return false;
        }

        $hashMatches = hash_equals(
            (string) $record['otp_hash'],
            hash('sha256', $otp)
        );

        $notExpired =
            strtotime((string) $record['expires_at'] . ' UTC') >= time();

        if (!$hashMatches || !$notExpired) {
            return false;
        }

        $consume = $db->prepare("

            UPDATE receipt_otps

            SET used_at = NOW()

            WHERE id = ?

            AND used_at IS NULL

        ");

        $consume->execute([
            (int) $record['id']
        ]);

        return $consume->rowCount() > 0;
    }
}
