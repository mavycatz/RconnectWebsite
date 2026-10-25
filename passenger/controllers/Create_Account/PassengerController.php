<?php

// =====================================
// REQUIRED FILES
// =====================================

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/MailerService.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/models/Passenger/PassengerModel.php';


// =====================================
// PASSENGER SERVICE FIX
// =====================================

$servicePath1 =
$_SERVER['DOCUMENT_ROOT'].'/passenger/services/passengerService.php';

$servicePath2 =
$_SERVER['DOCUMENT_ROOT'].'/passenger/services/Create_Account/passengerService.php';


if(file_exists($servicePath1)){

require_once $servicePath1;

}elseif(file_exists($servicePath2)){

require_once $servicePath2;

}else{

die(
'PassengerService.php not found.<br><br>' .

'Checked:<br>' .

$servicePath1 . '<br><br>' .

$servicePath2
);

}


// =====================================
// USE
// =====================================

use App\Core\Database;
use App\Core\MailerService;


// =====================================
// CONTROLLER
// =====================================

class PassengerController {

public function store()
{
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request.'
        ]);

        exit;
    }

    $privacyAccepted = $_POST['privacy_accepted'] ?? '0';

    if($privacyAccepted !== '1'){

        header(
            'Content-Type: application/json'
        );

        echo json_encode([

            'success' => false,

            'message' =>
                'You must accept the Privacy Policy before registering.'

        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVER-SIDE VALIDATION
    |--------------------------------------------------------------------------
    */
    require_once dirname(__DIR__, 2) . '/services/PassengerValidator.php';

    $nameResult = \App\Passenger\PassengerValidator::validateFullName((string)($_POST['full_name'] ?? ''));
    $phoneResult = \App\Passenger\PassengerValidator::validatePhone((string)($_POST['phone'] ?? ''), true);
    $emailResult = \App\Passenger\PassengerValidator::validateEmail((string)($_POST['email'] ?? ''), true);
    $typeResult = \App\Passenger\PassengerValidator::validatePassengerType((string)($_POST['discount'] ?? 'Regular'));
    $dobResult = \App\Passenger\PassengerValidator::validateDateOfBirth((string)($_POST['date_of_birth'] ?? ''), true);

    $discount = $typeResult['value'];
    $idTypeResult = \App\Passenger\PassengerValidator::validatePassengerIdType($discount, (string)($_POST['id_type'] ?? ''));

    if ($discount === 'Regular') {
        $idResult = ['valid' => true, 'value' => '', 'error' => null];
    } else {
        $idResult = \App\Passenger\PassengerValidator::validateIdNumber((string)($_POST['student_id'] ?? ''), $discount, true);
    }

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $passResult = \App\Passenger\PassengerValidator::validatePassword($password, $confirmPassword);

    $address = \App\Passenger\PassengerValidator::sanitizeString((string)($_POST['address'] ?? ''), 255);

    $validateErrors = [];

    if (!$nameResult['valid']) {
        $validateErrors[] = $nameResult['error'];
    }
    if (!$emailResult['valid']) {
        $validateErrors[] = $emailResult['error'];
    }
    if (!$phoneResult['valid']) {
        $validateErrors[] = $phoneResult['error'];
    }
    if (!$dobResult['valid']) {
        $validateErrors[] = $dobResult['error'];
    }
    if (!$typeResult['valid']) {
        $validateErrors[] = $typeResult['error'];
    }
    if (!$idTypeResult['valid']) {
        $validateErrors[] = $idTypeResult['error'];
    }
    if (!$idResult['valid']) {
        $validateErrors[] = $idResult['error'];
    }
    if (!$passResult['valid']) {
        $validateErrors[] = $passResult['error'];
    }

    $fullName = $nameResult['value'];
    $email = $emailResult['value'];
    $phone = $phoneResult['value'];
    $dateOfBirth = $dobResult['value'] ?? '';
    $studentId = ($discount === 'Regular') ? '' : $idResult['value'];
    $idType = $idTypeResult['value'];

    if(!empty($validateErrors)){

        header(
            'Content-Type: application/json'
        );

        echo json_encode([

            'success' => false,

            'message' =>
                implode(
                    ' ',
                    $validateErrors
                ),

            'errors' =>
                $validateErrors

        ]);

        exit;
    }

    try {
        $db = Database::tryGet();
        if ($db) {
            $emailNorm = \App\Passenger\PassengerValidator::normalizeEmailString($email);
            $phoneNorm = \App\Passenger\PassengerValidator::normalizePhoneString($phone);

            /* A number stored only on a one-time registration (contact_number)
               does not block registration: the OTP verification adopts the
               live one-time record as the account, so no second passenger is
               created for the same person. Only rows that are already real
               accounts (an email, phone or password exists) block here —
               legacy one-time rows can carry the default registration_type. */
            $contactArm = '';
            $params = [$emailNorm, $emailNorm, $phoneNorm, $phoneNorm];

            if (\App\Passenger\PassengerValidator::tableHasColumn($db, 'passengers', 'contact_number')) {
                $contactArm = " OR (? <> '' AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('contact_number') . " = ?"
                    . " AND (COALESCE(email, '') <> '' OR COALESCE(phone, '') <> '' OR COALESCE(password, '') <> ''))";
                $params[] = $phoneNorm;
                $params[] = $phoneNorm;
            }

            $checkStmt = $db->prepare("
                SELECT COUNT(*) FROM passengers
                WHERE LOWER(COALESCE(status, '')) <> 'deleted'
                  AND LOWER(COALESCE(account_status, '')) <> 'deleted'
                  AND ((? <> '' AND LOWER(TRIM(email)) = ?)
                       OR (? <> '' AND " . \App\Passenger\PassengerValidator::phoneColumnExpr('phone') . " = ?)"
                       . $contactArm . ")
            ");
            $checkStmt->execute($params);
            if ((int)$checkStmt->fetchColumn() > 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'An account with this email or mobile number already exists.'
                ]);
                exit;
            }
        }
    } catch (\Throwable $e) {
        // Pre-check failed non-fatally; VerifyOTPController will perform definitive check
    }

    if (!passenger_rate_limit('register_otp_send', strtolower($email) . '|' . passenger_client_ip(), 5, 900, 900)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please try again later.']);
        exit;
    }

    $otp = (string) random_int(100000, 999999);

    $sessionData = $_POST;
    unset($sessionData['password'], $sessionData['confirm_password']);

    $sessionData['full_name'] = $fullName;
    $sessionData['email'] = $email;
    $sessionData['phone'] = $phone;
    $sessionData['date_of_birth'] = $dateOfBirth;
    $sessionData['address'] = $address;
    $sessionData['student_id'] = $studentId;
    $sessionData['discount'] = $discount;
    $sessionData['id_type'] = $idType;

    // Client-declared OCR assessment. Only the pre-approval states are
    // accepted; "verified" can never come from the client. The server
    // independently re-reads the uploaded document at OTP verification and may
    // grant "verified" automatically when it matches the declared details.
    $verificationHint = strtolower(trim((string)($_POST['ocr_verification'] ?? '')));
    $sessionData['verification_hint'] = in_array($verificationHint, ['pending', 'needs_review', 'expired'], true)
        ? $verificationHint
        : 'pending';

    $_SESSION['otp'] = $otp;

    $_SESSION['otp_expiry'] =
        time() + 300;

    $_SESSION['otp_attempts'] = 0;

    $_SESSION['otp_resend_at'] = time();

    $_SESSION['passenger_data'] =
        $sessionData;

    $_SESSION['passenger_password_hash'] =
        password_hash($password, PASSWORD_DEFAULT);

    $_SESSION['privacy_accepted_at'] =
        date('Y-m-d H:i:s');

    /*
    |--------------------------------------------------------------------------
    | SAVE DISCOUNT ID IMAGE(S)
    |--------------------------------------------------------------------------
    | Both captured sides travel to OTP verification in the session: the front
    | is the stored discount document, the back is an optional second OCR
    | source so a detail that only appears on one side is never missed when the
    | server re-reads the ID and auto-verifies it.
    */

    $discountIdPath = null;

    $discountIdBackPath = null;

    if($discount !== 'Regular'){

        $discountIdPath = $this->saveIdImage('id_front', true);

        $discountIdBackPath = $this->saveIdImage('id_back', false);
    }

    $_SESSION['discount_id_path'] =
        $discountIdPath ?: '';

    $_SESSION['discount_id_back_path'] =
        $discountIdBackPath ?: '';

    session_write_close();

    /*
    |--------------------------------------------------------------------------
    | SEND OTP
    |--------------------------------------------------------------------------
    */

    try {

        $mailer =
            new MailerService();

        $sent =
            $mailer->sendOtp(
                $_POST['email'],
                $otp
            );

    } catch (\Throwable $e) {

        error_log('Passenger register OTP send failed: ' . $e->getMessage());

        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'message' => 'Unable to send the OTP right now. Please try again.'
        ]);

        exit;
    }

    if(!$sent){

        header(
            'Content-Type: application/json'
        );

        echo json_encode([

            'success' => false,

            'message' =>
                'Failed to send OTP.'

        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    header(
        'Content-Type: application/json'
    );

    echo json_encode([

        'success' => true,

        'message' =>
            'OTP sent successfully.'

    ]);

    exit;
}

    /**
     * Save one discount ID image upload and return its public URL path, or
     * null when the upload was skipped or could not be saved. The front image
     * is the required discount evidence so a rejected front aborts the request
     * with the validation message; a bad back capture is simply skipped because
     * the back is an optional second OCR side and never the primary document.
     */
    private function saveIdImage(string $field, bool $required = true): ?string
    {
        if(
            !isset($_FILES[$field])
            ||
            (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)
                === UPLOAD_ERR_NO_FILE
        ){
            return null;
        }

        [$uploadOk, $uploadDetail] =
            passenger_validate_image_upload(
                $_FILES[$field]
            );

        if(!$uploadOk){

            if(!$required){
                return null;
            }

            header('Content-Type: application/json');

            echo json_encode([
                'success' => false,
                'message' => $uploadDetail
            ]);

            exit;
        }

        $uploadDir =
            $_SERVER['DOCUMENT_ROOT']
            . '/uploads/discount_ids/';

        if(!is_dir($uploadDir)){

            mkdir(
                $uploadDir,
                0755,
                true
            );
        }

        $filename =
            bin2hex(random_bytes(16))
            . '.'
            . $uploadDetail;

        if(
            !move_uploaded_file(
                $_FILES[$field]['tmp_name'],
                $uploadDir . $filename
            )
        ){
            return null;
        }

        return '/uploads/discount_ids/' . $filename;
    }

public function resendOtp()
{
    header('Content-Type: application/json');

    if($_SERVER['REQUEST_METHOD'] !== 'POST'){

        echo json_encode([
            'success' => false,
            'message' => 'Invalid request.'
        ]);

        exit;
    }

    if(!isset($_SESSION['passenger_data']['email'])){

        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please register again.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | RATE LIMIT
    |--------------------------------------------------------------------------
    */

    $lastResend =
        $_SESSION['otp_resend_at'] ?? 0;

    if(time() - $lastResend < 60){

        echo json_encode([
            'success' => false,
            'message' => 'Please wait before requesting another OTP.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | REGENERATE OTP
    |--------------------------------------------------------------------------
    */

    if (!passenger_rate_limit('register_otp_send', strtolower((string) $_SESSION['passenger_data']['email']) . '|' . passenger_client_ip(), 5, 900, 900)) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many OTP requests. Please try again later.'
        ]);
        exit;
    }

    $otp = (string) random_int(100000, 999999);

    /*
    |--------------------------------------------------------------------------
    | SEND OTP
    |--------------------------------------------------------------------------
    | Send the new code first. The session is only updated after the email
    | arrives safely, so a failed resend leaves the previously issued code
    | valid instead of overwriting it with a code the user never received.
    */

    try {

        $mailer =
            new MailerService();

        $sent =
            $mailer->sendOtp(
                $_SESSION['passenger_data']['email'],
                $otp
            );

    } catch (\Throwable $e) {

        error_log('Passenger register OTP resend failed: ' . $e->getMessage());

        echo json_encode([
            'success' => false,
            'message' => 'Unable to resend the OTP right now. Please try again.'
        ]);

        exit;
    }

    if(!$sent){

        echo json_encode([
            'success' => false,
            'message' => 'Failed to resend OTP. Please try again.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | STORE NEW OTP (only after a successful send)
    |--------------------------------------------------------------------------
    */

    $_SESSION['otp'] = $otp;

    $_SESSION['otp_expiry'] =
        time() + 300;

    $_SESSION['otp_attempts'] = 0;

    $_SESSION['otp_resend_at'] =
        time();

    session_write_close();

    echo json_encode([
        'success' => true,
        'message' => 'OTP resent to your email.'
    ]);

    exit;
}
}

?>