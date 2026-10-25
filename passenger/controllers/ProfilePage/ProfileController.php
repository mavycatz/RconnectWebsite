<?php

use App\Core\Database;

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/models/ProfilePage/Passenger.php';

class ProfileController {

    private $db;

    public function __construct(){
        $this->db = Database::get();
    }

    public function getProfile($passenger_id){
        $model = new Profile($this->db);
        return $model->getById($passenger_id);
    }

    public function getOutstandingBalance($passenger_id){
        try {
            $helper = $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/TripSchemaHelper.php';

            if (is_file($helper)) {
                require_once $helper;

                if (function_exists('ensureTripFlowSchema')) {
                    ensureTripFlowSchema($this->db);
                }
            }
        } catch (\Throwable $e) {
            error_log('Profile schema check failed: ' . $e->getMessage());
        }

        $model = new Profile($this->db);
        $trips = $model->getOutstandingBalance($passenger_id);

        return [
            'trips' => is_array($trips) ? $trips : [],
            'count' => is_array($trips) ? count($trips) : 0,
            'total' => $model->getTotalOutstanding($passenger_id),
        ];
    }

    public function getTrustedDevice($passenger_id){
        try {
            return \App\Passenger\PassengerSessionService::trustedDevice($this->db, (int) $passenger_id);
        } catch (\Throwable $e) {
            error_log('Profile trusted device read failed: ' . $e->getMessage());
            return null;
        }
    }

    public function revokeTrustedDevice($passenger_id){
        try {
            \App\Passenger\PassengerSessionService::revokeTrustedDevice($this->db, (int) $passenger_id);
            return true;
        } catch (\Throwable $e) {
            error_log('Profile trusted device revoke failed: ' . $e->getMessage());
            return false;
        }
    }

    public function update($passenger_id){

        try {

            $name  = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));

            if (class_exists('App\Passenger\PassengerValidator')) {
                $nameCheck = \App\Passenger\PassengerValidator::validateFullName($name);
                if (!$nameCheck['valid']) {
                    $_SESSION['profile_update_status'] = 'error';
                    $_SESSION['profile_update_error'] = $nameCheck['error'];
                    header('Location: ' . passenger_url('profile'));
                    exit;
                }
                $name = $nameCheck['value'];

                $emailCheck = \App\Passenger\PassengerValidator::validateEmail($email);
                if (!$emailCheck['valid']) {
                    $_SESSION['profile_update_status'] = 'error';
                    $_SESSION['profile_update_error'] = $emailCheck['error'];
                    header('Location: ' . passenger_url('profile'));
                    exit;
                }
                $email = $emailCheck['value'];

                $phoneCheck = \App\Passenger\PassengerValidator::validatePhone($phone);
                if (!$phoneCheck['valid']) {
                    $_SESSION['profile_update_status'] = 'error';
                    $_SESSION['profile_update_error'] = $phoneCheck['error'];
                    header('Location: ' . passenger_url('profile'));
                    exit;
                }
                $phone = $phoneCheck['normalized'];
            } else {
                $nameOk = $name !== ''
                    && mb_strlen($name) >= 2
                    && mb_strlen($name) <= 100
                    && (bool) preg_match("/^[\p{L}\p{M} .'\\-]+$/u", $name);

                $emailOk = (bool) filter_var($email, FILTER_VALIDATE_EMAIL)
                    && strlen($email) <= 100;

                $phoneDigits = (string) preg_replace('/[\s\-\(\)]/', '', $phone);
                $phoneOk = (bool) preg_match('/^(?:\+?63|0)?9\d{9}$/', $phoneDigits);

                if(!$nameOk || !$emailOk || !$phoneOk){
                    $_SESSION['profile_update_status'] = 'error';
                    $_SESSION['profile_update_error'] = 'Please enter a valid name, email address, and mobile number (e.g. 09171234567).';
                    header('Location: ' . passenger_url('profile'));
                    exit;
                }
            }

            // Check duplicate account
            if (class_exists('App\Passenger\PassengerValidator')) {
                $emailNorm = \App\Passenger\PassengerValidator::normalizeEmailString((string)$email);
                $phoneNorm = \App\Passenger\PassengerValidator::normalizePhoneString((string)$phone);
            } else {
                $emailNorm = strtolower(trim((string)$email));
                $phoneNorm = (string)$phone;
            }
            /* Positional placeholders: native (non-emulated) prepared
               statements reject a named parameter reused within one query. */
            $dupStmt = $this->db->prepare(
                "SELECT id FROM passengers
                 WHERE id != ?
                   AND LOWER(COALESCE(status, '')) <> 'deleted'
                   AND LOWER(COALESCE(account_status, '')) <> 'deleted'
                   AND ((? <> '' AND LOWER(TRIM(email)) = ?)
                        OR (? <> '' AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('phone') . " = ?))
                 LIMIT 1"
            );
            $dupStmt->execute([$passenger_id, $emailNorm, $emailNorm, $phoneNorm, $phoneNorm]);
            if ($dupStmt->fetch()) {
                $_SESSION['profile_update_status'] = 'error';
                $_SESSION['profile_update_error'] = 'An account with this email or mobile number already exists.';
                header('Location: ' . passenger_url('profile'));
                exit;
            }

            $profileImage = null;
            $discountID = null;
            $newDiscountDocument = null;

            $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/passenger/';

            if(!is_dir($uploadPath)){
                mkdir($uploadPath, 0755, true);
            }

            if(isset($_FILES['profile_image']) && (int)$_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE){

                [$ok, $detail] = passenger_validate_image_upload($_FILES['profile_image']);

                if(!$ok){
                    throw new RuntimeException($detail);
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $detail;

                if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath . $fileName)){
                    $profileImage = 'passenger/' . $fileName;
                }
            }

            if(isset($_FILES['discount_id']) && (int)$_FILES['discount_id']['error'] !== UPLOAD_ERR_NO_FILE){

                [$ok, $detail] = passenger_validate_image_upload($_FILES['discount_id']);

                if(!$ok){
                    throw new RuntimeException($detail);
                }

                $discountDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/discount_ids/';

                if(!is_dir($discountDir)){
                    mkdir($discountDir, 0755, true);
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $detail;

                if(move_uploaded_file($_FILES['discount_id']['tmp_name'], $discountDir . $fileName)){
                    $discountID = 'discount_ids/' . $fileName;
                }
            }

            $oldContactStmt = $this->db->prepare("SELECT email, phone FROM passengers WHERE id = ? LIMIT 1");
            $oldContactStmt->execute([$passenger_id]);
            $oldContact = $oldContactStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $query = "UPDATE passengers SET full_name=?, email=?, phone=?";
            $params = [$name, $email, $phone];

            if($profileImage){
                $query .= ", id_image=?";
                $params[] = $profileImage;
            }

            if($discountID){
                // Store the new document first; its verification state is
                // decided below by re-reading it, so a clean upload takes effect
                // immediately instead of always restarting administrator review.
                $query .= ", discount_id=?";
                $params[] = $discountID;
                $newDiscountDocument = $discountID;
            }

            $query .= " WHERE id=?";
            $params[] = $passenger_id;

            $stmt = $this->db->prepare($query);

            if($stmt->execute($params)){
                $_SESSION['passenger_name'] = $name;
                $_SESSION['profile_update_status'] = 'success';
                unset($_SESSION['profile_update_error']);

                /* Identity claims follow the new email/phone: the old values
                   are released so they can be registered again (a stale claim
                   would otherwise block a new registration while no passenger
                   row carries the value), and the new values are claimed.
                   Claims are secondary to the profile save: a sync failure is
                   logged and never turns a saved profile into an error. */
                try {
                    require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerAccountService.php';
                    \PassengerAccountService::syncIdentityClaims(
                        $this->db,
                        (int) $passenger_id,
                        (string) ($oldContact['email'] ?? ''),
                        (string) ($oldContact['phone'] ?? ''),
                        (string) $email,
                        (string) $phone
                    );
                } catch (\Throwable $e) {
                    error_log('Profile identity claim sync failed: ' . $e->getMessage());
                }

                if(isset($newDiscountDocument) && $newDiscountDocument !== null){
                    $this->autoVerifyDocument((int)$passenger_id, (string)$newDiscountDocument);
                }
            } else {
                $_SESSION['profile_update_status'] = 'error';
                $_SESSION['profile_update_error'] = 'Unable to update your profile right now.';
            }

        } catch (RuntimeException $e) {

            $_SESSION['profile_update_status'] = 'error';
            $_SESSION['profile_update_error'] = $e->getMessage();

        } catch (Throwable $e) {

            error_log('Passenger profile update failed: ' . $e->getMessage());
            $_SESSION['profile_update_status'] = 'error';
            $_SESSION['profile_update_error'] = 'Unable to update your profile right now.';
        }

        header('Location: ' . passenger_url('profile'));
        exit;
    }

    /**
     * Re-read a freshly submitted discount document and apply the automatic
     * verification verdict. A document that matches the declared details is
     * verified immediately - existing uploads do not wait for administrator
     * approval - while an unreadable, mismatched, expired or underage document
     * is left in the review queue. Failures here never break the profile save.
     */
    private function autoVerifyDocument(int $passenger_id, string $discountID): void
    {
        try {
            if (!defined('SCAN_ID_LIB_ONLY')) {
                define('SCAN_ID_LIB_ONLY', true);
            }
            require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/Create_Account/scanID.php';
            require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerAccountService.php';

            if (!function_exists('resolve_auto_verification') || !class_exists('PassengerAccountService')) {
                return;
            }

            $stmt = $this->db->prepare('SELECT full_name, date_of_birth, student_id, discount_type FROM passengers WHERE id = ?');
            $stmt->execute([$passenger_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $idTypeMap = [
                'student' => 'student-id',
                'senior' => 'senior-citizen-id',
                'seniorcitizen' => 'senior-citizen-id',
                'pwd' => 'pwd-id',
                'personwithdisability' => 'pwd-id',
                'teacher' => 'teacher-id',
                'nurse' => 'prc-id',
                'cooperative' => 'coop-beneficiary-id',
            ];
            $typeKey = strtolower((string) preg_replace('/[^a-z]/i', '', (string)($row['discount_type'] ?? '')));

            $declared = [
                'full_name' => (string)($row['full_name'] ?? ''),
                'date_of_birth' => (string)($row['date_of_birth'] ?? ''),
                'student_id' => (string)($row['student_id'] ?? ''),
                'id_type' => $idTypeMap[$typeKey] ?? '',
                'discount' => (string)($row['discount_type'] ?? ''),
            ];

            $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . ltrim($discountID, '/');
            $assessment = resolve_auto_verification($imagePath, $declared);

            \PassengerAccountService::applyDocumentVerification($this->db, $passenger_id, $assessment);
        } catch (\Throwable $e) {
            error_log('Profile document auto-verification failed: ' . $e->getMessage());
        }
    }
}
