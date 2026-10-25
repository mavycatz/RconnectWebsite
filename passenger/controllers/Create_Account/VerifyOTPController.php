<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT']
.'/app/Core/Database.php';

require_once $_SERVER['DOCUMENT_ROOT']
.'/passenger/services/PassengerQRSchema.php';

require_once $_SERVER['DOCUMENT_ROOT']
.'/passenger/services/PassengerCodeService.php';
require_once dirname(__DIR__, 2) . '/services/PassengerAccountService.php';
if (!defined('SCAN_ID_LIB_ONLY')) { define('SCAN_ID_LIB_ONLY', true); }
require_once dirname(__DIR__, 2) . '/controllers/Create_Account/scanID.php';

use App\Core\Database;

class VerifyOTPController
{

public function verify()
{

    try{

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){

            throw new Exception(
                'Invalid request method.'
            );
        }

        if(!passenger_rate_limit('register_otp_verify', passenger_client_ip(), 20, 900, 900)){

            throw new Exception(
                'Too many verification attempts. Please try again later.'
            );
        }

        // Build the entered code from a single canonical source: the six
        // digit boxes when present, otherwise the combined `otp` field (for
        // clients that only send that one value).
        $enteredOtp = $this->extractEnteredOtp();

        // Normalize both sides to digits-only strings before comparing so
        // whitespace or formatting never causes a valid code to be rejected.
        $enteredOtp = preg_replace('/\D/', '', (string)$enteredOtp);
        $sessionOtp = preg_replace('/\D/', '', (string)($_SESSION['otp'] ?? ''));

        if(empty($enteredOtp)){
            throw new Exception(
                'Please enter the 6-digit OTP code.'
            );
        }

        if(strlen($enteredOtp) !== 6){
            throw new Exception(
                'Please enter the complete 6-digit OTP code.'
            );
        }

        if(empty($sessionOtp)){
            throw new Exception(
                'OTP session expired. Please click "Resend OTP" to receive a new code.'
            );
        }

        // Single expiry check: `otp_expiry` is the absolute timestamp set by
        // both store() and resendOtp() at generation time. A missing or
        // elapsed expiry means "please resend".
        $now = time();
        $expiry = isset($_SESSION['otp_expiry']) ? (int)$_SESSION['otp_expiry'] : 0;
        if($expiry <= 0 || $now > $expiry){
            throw new Exception(
                'OTP has expired. Please click "Resend OTP" to receive a new code.'
            );
        }

        $attempts = (int)($_SESSION['otp_attempts'] ?? 0) + 1;

        if($attempts > 5){

            unset(
                $_SESSION['otp'],
                $_SESSION['otp_expiry'],
                $_SESSION['otp_attempts']
            );

            throw new Exception(
                'Too many incorrect attempts. Please click "Resend OTP" to receive a new code.'
            );
        }

        if(!hash_equals($sessionOtp, $enteredOtp)){

            $_SESSION['otp_attempts'] = $attempts;

            throw new Exception(
                'Invalid OTP code. Please check your email and enter the latest 6-digit code.'
            );
        }

        $data =
            $_SESSION['passenger_data']
            ?? [];

        if(empty($data)){

            throw new Exception(
                'Registration data missing.'
            );
        }

/*
|--------------------------------------------------------------------------
| SAVE ADDRESS TO SESSION
|--------------------------------------------------------------------------
*/

if(!empty($data['address'])){
    $_SESSION['passenger_address'] = $data['address'];
}

/*
|--------------------------------------------------------------------------
| GET SAVED DISCOUNT ID PATH
|--------------------------------------------------------------------------
*/

$discountId =
    $_SESSION['discount_id_path']
    ?? null;

$discountIdBack =
    $_SESSION['discount_id_back_path']
    ?? null;

        /*
        |--------------------------------------------------------------------------
        | ID DOCUMENT AUTO-VERIFICATION
        |--------------------------------------------------------------------------
        | OTP success alone never grants a discount. For discounted accounts the
        | server re-reads the uploaded document and compares it with the declared
        | details, setting "verified" only when the document cleanly validates.
        | When the document or OCR cannot be validated the account stays pending
        | (or needs_review / expired) for an administrator - never verified.
        */

        $discountType = (string)($data['discount'] ?? 'Regular');

        $discountStatus = 'verified';

        $idVerification = null;

        if ($discountType !== 'Regular') {

            // The client/OCR suggested status only acts as a fallback when the
            // server cannot re-scan the document, and it can never be verified.
            $discountStatus = in_array(($data['verification_hint'] ?? 'pending'), ['pending', 'needs_review', 'expired'], true)
                ? (string)$data['verification_hint']
                : 'pending';

            $imagePath = ($discountId !== null && $discountId !== '')
                ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim((string)$discountId, '/')
                : '';

            $backPath = ($discountIdBack !== null && $discountIdBack !== '')
                ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim((string)$discountIdBack, '/')
                : '';

            if (function_exists('resolve_auto_verification')) {
                $idVerification = resolve_auto_verification($imagePath, $data, null, $backPath);
                if (!empty($idVerification['checked'])) {
                    $discountStatus = (string)$idVerification['status'];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT PASSENGER
        |--------------------------------------------------------------------------
        */

        $db =
            Database::get();

        ensurePassengerQrSchema($db);
        PassengerAccountSchema::ensure($db);

        $dateOfBirthValue = null;
        if(!empty($data['date_of_birth'])){

            $dobRaw =
                str_replace('/', '-', (string)$data['date_of_birth']);

            $dob =
                DateTime::createFromFormat('!Y-m-d', $dobRaw);

            if(
                $dob
                && $dob->format('Y-m-d') === $dobRaw
                && $dob <= new DateTime('today')
                && $dob >= new DateTime('1900-01-01')
            ){

                $dateOfBirthValue =
                    $dob->format('Y-m-d');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FRIENDLY DUPLICATE PRE-CHECK
        |--------------------------------------------------------------------------
        | Detect an existing account before insert so the user gets a clear
        | message instead of a raw database error. The DB UNIQUE constraint is
        | still the definitive guard for concurrent requests.
        */
        require_once dirname(__DIR__, 2) . '/services/PassengerValidator.php';

        $phoneNorm = !empty($data['phone'])
            ? \App\Passenger\PassengerValidator::normalizePhoneString((string)$data['phone'])
            : '';

        $emailNorm = \App\Passenger\PassengerValidator::normalizeEmailString((string)($data['email'] ?? ''));

        /* A number stored only on a one-time registration (contact_number)
           does not block registration: the live one-time record is adopted
           as the account below, so no second passenger is created for the
           same person. Only rows that are already real accounts (an email,
           phone or password exists) block on contact_number — legacy
           one-time rows can carry the default registration_type. */
        $contactArm = '';
        $dupParams = [$emailNorm, $emailNorm, $phoneNorm, $phoneNorm];

        if (\App\Passenger\PassengerValidator::tableHasColumn($db, 'passengers', 'contact_number')) {
            $contactArm = " OR (? <> '' AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('contact_number') . " = ?"
                . " AND (COALESCE(email, '') <> '' OR COALESCE(phone, '') <> '' OR COALESCE(password, '') <> ''))";
            $dupParams[] = $phoneNorm;
            $dupParams[] = $phoneNorm;
        }

        $dup = $db->prepare("
            SELECT COUNT(*) AS c
            FROM passengers
            WHERE LOWER(COALESCE(status, '')) <> 'deleted'
              AND LOWER(COALESCE(account_status, '')) <> 'deleted'
              AND ((? <> '' AND LOWER(TRIM(email)) = ?)
                   OR (? <> '' AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('phone') . " = ?)"
                   . $contactArm . ")
        ");

        $dup->execute($dupParams);

        if((int)$dup->fetchColumn() > 0){

            echo json_encode([

                'success' => false,

                'message' =>
                    'An account with this email or mobile number already exists.'

            ]);

            exit;
        }

        $stmt =
            $db->prepare("

                INSERT INTO passengers (

                    full_name,
                    email,
                    phone,
                    password,
                    discount_type,
                    student_id,
                    discount_id,
                    discount_status,
                    latitude,
                    longitude,
                    date_of_birth,
                    privacy_accepted_at

                )

                VALUES (

                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?

                )

            ");

        $db->beginTransaction();

        $accountValues = [

            $data['full_name'] ?? '',

            $data['email'] ?? '',

            $data['phone'] ?? '',

            $_SESSION['passenger_password_hash']
                ?? password_hash(
                    (string)($data['password'] ?? ''),
                    PASSWORD_DEFAULT
                ),

            $data['discount'] ?? 'Regular',

            $data['student_id'] ?? '',

            $discountId,

            // Regular accounts need no document. Discount accounts are
            // auto-verified only when the server re-read the uploaded ID and
            // it matched the declared details; otherwise they stay pending /
            // needs_review / expired for administrator review.
            $discountStatus,
            !empty($data['latitude'])
                ? $data['latitude']
                : null,

            !empty($data['longitude'])
                ? $data['longitude']
                : null,

            $dateOfBirthValue,

            $_SESSION['privacy_accepted_at']
                ?? null

        ];

        /* A live one-time record that carries this number only in
           contact_number becomes the account instead of blocking the
           registration: one passenger row per person, and the one-time
           trips stay linked to the new account. No email, phone or
           password means the row is not an account yet — older one-time
           rows can carry the default registration_type. */
        $adoptId = 0;

        if ($phoneNorm !== '') {
            try {
                $adopt = $db->prepare("
                    SELECT id FROM passengers
                    WHERE LOWER(COALESCE(status, '')) <> 'deleted'
                      AND LOWER(COALESCE(account_status, '')) <> 'deleted'
                      AND COALESCE(email, '') = ''
                      AND COALESCE(phone, '') = ''
                      AND COALESCE(password, '') = ''
                      AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('contact_number') . " = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $adopt->execute([$phoneNorm]);
                $adoptId = (int) $adopt->fetchColumn();
            } catch (\Throwable $e) {
                $adoptId = 0;
            }
        }

        if ($adoptId > 0) {
            $db->prepare("
                UPDATE passengers
                SET full_name = ?,
                    email = ?,
                    phone = ?,
                    password = ?,
                    discount_type = ?,
                    student_id = ?,
                    discount_id = ?,
                    discount_status = ?,
                    latitude = ?,
                    longitude = ?,
                    date_of_birth = ?,
                    privacy_accepted_at = ?,
                    registration_type = 'regular'
                WHERE id = ?
            ")->execute(array_merge($accountValues, [$adoptId]));

            $passengerId = $adoptId;
        } else {
            $stmt->execute($accountValues);

$passengerId = (int)$db->lastInsertId();
        }
        PassengerAccountService::claimIdentity($db, $passengerId, (string)($data['email'] ?? ''), (string)($data['phone'] ?? ''));
        $db->prepare("UPDATE passengers SET account_status = 'ACTIVE', status = 'Active' WHERE id = ?")->execute([$passengerId]);

        if ($discountType !== 'Regular' && $discountStatus === 'verified') {
            $db->prepare("UPDATE passengers SET verified_at = ? WHERE id = ?")
                ->execute([date('Y-m-d H:i:s'), $passengerId]);
            PassengerAccountService::recordAutoVerification($db, $passengerId, is_array($idVerification) ? $idVerification : []);
        }

        // Auto-verified discounts receive their classification prefix (STU- /
        // SEN- / PWD-) immediately; pending documents stay on the Standard
        // prefix until an administrator verifies them.
        $passengerCode = PassengerCodeService::generate(
            $db,
            $passengerId,
            (string)($data['discount'] ?? 'Regular')
        );

        $db->commit();

        unset(

            $_SESSION['otp'],
            $_SESSION['otp_expiry'],
            $_SESSION['otp_attempts'],
            $_SESSION['passenger_data'],
            $_SESSION['passenger_password_hash'],
            $_SESSION['passenger_files'],
            $_SESSION['discount_id_path'],
            $_SESSION['discount_id_back_path'],
            $_SESSION['privacy_accepted_at'],
            $_SESSION['passenger_address']

        );

$_SESSION['registered_passenger_code'] = $passengerCode;
$_SESSION['registered_passenger_id'] = $passengerId;

        // The registration confirmation is separate from the persisted,
        // server-assessed discount eligibility. Wording never changes status.
        $verificationMessage = 'Your account was created. Please use your credentials to log in.';

        echo json_encode([

            'success' => true,

            'message' =>
                $verificationMessage,

            'verification_status' =>
                $discountStatus,

            'passenger_id' =>
                $passengerId,

            'passenger_code' =>
                $passengerCode,

            'redirect' =>
                passenger_url('login')

        ]);

        exit;

    }catch(Throwable $e){

        if(
            isset($db)
            && $db->inTransaction()
        ){
            $db->rollBack();
        }

        $message =
            $this->translateError($e);

        echo json_encode([

            'success' => false,

            'message' =>
                $message

        ]);

        exit;
    }
}

private function extractEnteredOtp(): string
{
    $hasDigitFields = false;
    $assembled = '';

    for($i = 1; $i <= 6; $i++){
        if(isset($_POST["otp_digit_{$i}"])){
            $hasDigitFields = true;
            $assembled .= trim((string)$_POST["otp_digit_{$i}"]);
        }
    }

    if($hasDigitFields){
        return $assembled;
    }

    return trim((string)($_POST['otp'] ?? ''));
}

private function translateError(Throwable $e){

    $code =
        $e->getCode();

    $msg =
        $e->getMessage();

    $isDuplicate =
        strpos((string)$code, '1062') !== false
        || strpos((string)$code, '23000') !== false
        || strpos((string)$code, '19') !== false
        || stripos($msg, 'duplicate') !== false
        || stripos($msg, 'unique constraint') !== false
        || stripos($msg, 'unique constraint failed') !== false;

    if($isDuplicate){

        return
            'An account with this email or mobile number already exists.';
    }

    error_log(
        'Passenger verify-otp failed: '
        . get_class($e) . ': ' . $msg
        . ' @ ' . $e->getFile() . ':' . $e->getLine()
    );

    $isInfrastructure =
        $e instanceof \PDOException
        || $e instanceof \Error
        || stripos($msg, 'sqlstate') !== false
        || stripos($msg, 'database connection') !== false;

    if($isInfrastructure || trim($msg) === ''){

        return
            'We could not complete your registration right now. Please try again.';
    }

    return $msg;
}
}
