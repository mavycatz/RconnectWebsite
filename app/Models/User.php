<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class User {

    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    /* ----------------------------------
       FIND BY USERNAME (FULL RECORD)
    -----------------------------------*/
    public function findByUsername(string $username): ?array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /* ----------------------------------
       FIND BY USERNAME OR EMAIL
    -----------------------------------*/
    public function findByUsernameOrEmail(string $input): ?array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE username = :username
               OR email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $input,
            'email'    => $input
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /* ----------------------------------
       FIND BY ID (SAFE RETURN)
    -----------------------------------*/
    public function findById(int $id): ?array {

        $stmt = $this->db->prepare("
            SELECT id, username, email, role,
                   failed_attempts, lock_until,
                   lock_count, permanently_locked
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /* ----------------------------------
       RESET FAILED ATTEMPTS
    -----------------------------------*/
    public function resetAttempts(int $id): void {

        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_attempts = 0,
                lock_until = NULL
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    /* ----------------------------------
       INCREMENT FAILED ATTEMPTS
    -----------------------------------*/
    public function incrementAttempts(int $id): void {

        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_attempts = failed_attempts + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    /* ----------------------------------
       LOCK ACCOUNT TEMPORARILY
    -----------------------------------*/
    public function lockTemporarily(int $id, string $until): void {

        $stmt = $this->db->prepare("
            UPDATE users
            SET lock_until = :until,
                lock_count = lock_count + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'until' => $until,
            'id'    => $id
        ]);
    }

    /* ----------------------------------
       LOCK ACCOUNT PERMANENTLY
    -----------------------------------*/
    public function lockPermanently(int $id): void {

        $stmt = $this->db->prepare("
            UPDATE users
            SET permanently_locked = 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    /* ----------------------------------
       UPDATE PASSWORD
    -----------------------------------*/
    public function updatePassword(int $id, string $hashedPassword): void {

        $stmt = $this->db->prepare("
            UPDATE users
            SET password = :password,
                failed_attempts = 0,
                lock_until = NULL
            WHERE id = :id
        ");

        $stmt->execute([
            'password' => $hashedPassword,
            'id'       => $id
        ]);
    }

    /* ----------------------------------
       STORE PASSWORD RESET OTP
    -----------------------------------*/
    public function storePasswordReset(int $userId, string $otpHash, string $expires): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (user_id, otp_hash, expires_at)
                VALUES (:user_id, :otp_hash, :expires_at)
            ");
            $stmt->execute([
                'user_id'   => $userId,
                'otp_hash'  => $otpHash,
                'expires_at'=> $expires
            ]);
        } catch (\Throwable $e) {}
    }

    /* ----------------------------------
       GET ACTIVE PASSWORD RESET
    -----------------------------------*/
    public function getActivePasswordReset(int $userId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM password_resets
                WHERE user_id = :user_id
                AND expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            return $reset ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ----------------------------------
       INCREMENT RESET ATTEMPTS
    -----------------------------------*/
    public function incrementResetAttempts(int $resetId): void {

        $stmt = $this->db->prepare("
            UPDATE password_resets
            SET attempts = attempts + 1
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $resetId
        ]);
    }

    /* ----------------------------------
       DELETE PASSWORD RESET
    -----------------------------------*/
    public function deletePasswordReset(int $userId): void {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM password_resets
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
        } catch (\Throwable $e) {}
    }
    /* ----------------------------------
   FIND BY REMEMBER TOKEN
-----------------------------------*/
public function findByRememberToken(string $token): ?array {

    $stmt = $this->db->prepare("
        SELECT *
        FROM users
        WHERE remember_token = :token
        LIMIT 1
    ");

    $stmt->execute([
        'token' => $token
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}
}