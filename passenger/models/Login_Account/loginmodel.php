<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/PassengerValidator.php';

use App\Core\Database;

class LoginModel {

    private $conn;

    public function __construct(){
        $this->conn = Database::get();
    }

    public function findPassenger($login){
        $cleanLogin = trim((string)$login);
        if ($cleanLogin === '') {
            return null;
        }

        $normalizedPhone = null;
        if (class_exists('App\Passenger\PassengerValidator')) {
            $phoneCheck = \App\Passenger\PassengerValidator::validatePhone($cleanLogin, false);
            if ($phoneCheck['valid']) {
                $normalizedPhone = $phoneCheck['normalized'] ?? ($phoneCheck['value'] ?? null);
            }
        } elseif (preg_match('/^(?:09|\+639|639)(\d{9})$/', preg_replace('/[\s\-()]/', '', $cleanLogin), $m)) {
            $normalizedPhone = '09' . $m[1];
        }

        /* Canonical comparators shared with registration: the stored email is
           folded with LOWER(TRIM()), the stored phone with the validator's SQL
           expression (so 09…/+63…/63… and spaced or dashed stored formats all
           resolve), soft-deleted rows are excluded exactly like the duplicate
           checks, and an exact raw arm keeps any odd legacy stored value
           reachable. Empty canonical values never join a comparison arm: an
           email login must not match rows whose phone is empty (and vice
           versa). */
        $emailCanonical = \App\Passenger\PassengerValidator::normalizeEmailString($cleanLogin);
        $phoneCanonical = \App\Passenger\PassengerValidator::normalizePhoneString($cleanLogin);

        $conditions = array(
            "email = :raw_email",
            "phone = :raw_phone",
        );

        if ($emailCanonical !== '') {
            $conditions[] = "LOWER(TRIM(COALESCE(email, ''))) = :email";
        }

        if ($phoneCanonical !== '') {
            $conditions[] = "LOWER(TRIM(COALESCE(phone, ''))) = :phone";
        }

        if ($normalizedPhone !== null && $normalizedPhone !== '') {
            $phoneExpr = class_exists('App\Passenger\PassengerValidator')
                ? \App\Passenger\PassengerValidator::phoneColumnExpr('phone')
                : "REPLACE(REPLACE(REPLACE(REPLACE(phone,'+63','0'),' ',''),'-',''),'(','')";
            $conditions[] = $phoneExpr . " = :norm_phone";
        }

        $sql = "SELECT * FROM passengers
                WHERE LOWER(COALESCE(status, '')) <> 'deleted'
                  AND LOWER(COALESCE(account_status, '')) <> 'deleted'
                  AND (" . implode(' OR ', $conditions) . ")
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        /* Native (non-emulated) prepared statements — MySQL by default — reject
           a named placeholder that appears more than once in the query, so the
           raw arm is bound under one name per column. */
        $stmt->bindValue(':raw_email', $cleanLogin);
        $stmt->bindValue(':raw_phone', $cleanLogin);
        if ($emailCanonical !== '') {
            $stmt->bindValue(':email', $emailCanonical);
        }
        if ($phoneCanonical !== '') {
            $stmt->bindValue(':phone', $phoneCanonical);
        }
        if ($normalizedPhone !== null && $normalizedPhone !== '') {
            $stmt->bindValue(':norm_phone', $normalizedPhone);
        }

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ----------------------------------
       INCREMENT FAILED LOGIN ATTEMPTS
    -----------------------------------*/
    public function incrementAttempts(int $id): void {
        $stmt = $this->conn->prepare(
            "UPDATE passengers
             SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    /* ----------------------------------
       TEMPORARILY LOCK THE ACCOUNT
    -----------------------------------*/
    public function lockTemporarily(int $id, string $until): void {
        $stmt = $this->conn->prepare(
            "UPDATE passengers
              SET login_locked_until = :until,
                 failed_login_attempts = 3
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id, ':until' => $until]);
    }

    /* ----------------------------------
       RESET FAILED LOGIN ATTEMPTS
    -----------------------------------*/
    public function resetAttempts(int $id): void {
        $stmt = $this->conn->prepare(
            "UPDATE passengers
             SET failed_login_attempts = 0,
                  login_locked_until = NULL,
                  blocked_until = CASE WHEN status IN ('Blocked', 'Suspended') THEN blocked_until ELSE NULL END
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }
}
