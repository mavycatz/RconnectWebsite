<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/MailerService.php';

use App\Core\MailerService;
use App\Core\Database;

class ForgotPasswordController
{
    private function startSession()
    {
        if (function_exists('passenger_session_start')) {
            passenger_session_start();
        }
    }

    public function index()
    {
        $this->startSession();

        require $_SERVER['DOCUMENT_ROOT']
        . '/passenger/views/ForgotPassword/index.php';
    }

    public function sendOTP()
    {
        $this->startSession();

        $email = trim((string)($_POST['email'] ?? ''));

        if (class_exists('App\Passenger\PassengerValidator')) {
            $emailCheck = \App\Passenger\PassengerValidator::validateEmail($email);
            if (!$emailCheck['valid']) {
                $_SESSION['error'] = $emailCheck['error'];
                header("Location: " . passenger_url('forgot-password'));
                exit;
            }
            $email = $emailCheck['value'];
        } else {
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Please enter a valid email address.";
                header("Location: " . passenger_url('forgot-password'));
                exit;
            }
        }

        if(!passenger_rate_limit('reset_otp_send', strtolower($email) . '|' . passenger_client_ip(), 5, 900, 900)){

            $_SESSION['error'] =
                "Too many requests. Please try again later.";

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        $ack = "If this email is registered, a reset code has been sent.";

        /* The lookup is best-effort for non-disclosure and availability: a
           database failure behaves like an unknown email and never replaces
           the redirect with the generic service error page. */
        $user = false;
        try {
            $db =
                Database::get();

            /* Canonical email matching, exactly like the login lookup. */
            $stmt =
                $db->prepare("
                    SELECT *
                    FROM passengers
                    WHERE LOWER(TRIM(email)) = ?
                    LIMIT 1
                ");

            $stmt->execute([strtolower(trim($email))]);

            $user =
                $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Passenger reset lookup failed: ' . $e->getMessage());
            $user = false;
        }

        $_SESSION['reset_email'] = $email;

        /* Non-disclosing: known, unknown, and email-less accounts all see the
           same acknowledgment, and a send failure is masked and logged for
           operators instead of revealing that the address exists. */
        if(!$user || trim((string)($user['email'] ?? '')) === ''){

            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts'], $_SESSION['otp_created']);

            if ($user) {
                error_log('Passenger reset requested for an account with no email on file (id ' . (int)($user['id'] ?? 0) . ')');
            }

            $_SESSION['success'] = $ack;

            header(
                "Location: " . passenger_url('verify-reset-otp')
            );

            exit;
        }

        $otp = random_int(100000, 999999);

        $_SESSION['reset_otp'] =
            $otp;

        $_SESSION['otp_created'] =
            time();

        $_SESSION['reset_otp_attempts'] = 0;

        $_SESSION['reset_otp_resend_at'] = time();

        try {

            $mailer =
                new MailerService();

            $sent =
                $mailer->sendOtp($email, $otp);

            if(!$sent){

                unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts'], $_SESSION['otp_created']);

                error_log('Passenger reset OTP send failed for account ' . (int)($user['id'] ?? 0));
            }

        } catch (\Throwable $e) {

            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts'], $_SESSION['otp_created']);

            error_log('Passenger reset OTP send failed: ' . $e->getMessage());
        }

        $_SESSION['success'] = $ack;

        header(
            "Location: " . passenger_url('verify-reset-otp')
        );

        exit;
    }

    public function verifyOTPPage()
    {
        $this->startSession();

        if(!isset($_SESSION['reset_email'])){

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        require $_SERVER['DOCUMENT_ROOT']
        . '/passenger/views/ForgotPassword/verify_otp.php';
    }

    public function verifyOTP()
    {
        $this->startSession();

        $enteredOtp =
            preg_replace('/\D/', '', trim((string)($_POST['otp'] ?? '')));

        if (strlen($enteredOtp) !== 6) {
            $_SESSION['error'] = "Please enter a valid 6-digit verification code.";
            header("Location: " . passenger_url('verify-reset-otp'));
            exit;
        }

        $sessionOtp =
            preg_replace('/\D/', '', (string)($_SESSION['reset_otp'] ?? ''));

        if($sessionOtp === ''){

            /* Unknown or email-less accounts never receive a code; behave
               exactly like a wrong code and stay on the page so the flow
               cannot be used to probe which emails exist. */
            if (isset($_SESSION['reset_email'])) {

                $_SESSION['error'] =
                    "Invalid OTP.";

                header(
                    "Location: " . passenger_url('verify-reset-otp')
                );

                exit;
            }

            $_SESSION['error'] =
                "Session expired.";

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        if(isset($_SESSION['otp_created'])){

            if(time() - (int)$_SESSION['otp_created'] > 300){

                unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts']);

                $_SESSION['error'] =
                    "OTP expired.";

                header(
                    "Location: " . passenger_url('forgot-password')
                );

                exit;
            }
        }

        $attempts = (int)($_SESSION['reset_otp_attempts'] ?? 0) + 1;

        if($attempts > 5){

            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts']);

            $_SESSION['error'] =
                "Too many incorrect attempts. Please request a new code.";

            header(
                "Location: " . passenger_url('verify-reset-otp')
            );

            exit;
        }

        if(strlen($enteredOtp) === 6 && hash_equals($sessionOtp, $enteredOtp)){

            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts']);

            $_SESSION['reset_verified'] =
                true;

            header(
                "Location: " . passenger_url('new-password')
            );

            exit;
        }

        $_SESSION['reset_otp_attempts'] = $attempts;

        $_SESSION['error'] =
            "Invalid OTP.";

        header(
            "Location: " . passenger_url('verify-reset-otp')
        );

        exit;
    }

    public function resendOTP()
    {
        $this->startSession();

        $email =
            $_SESSION['reset_email'] ?? '';

        if(empty($email)){

            $_SESSION['error'] =
                "Session expired. Please start again.";

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        $lastResend = (int)($_SESSION['reset_otp_resend_at'] ?? 0);

        if(time() - $lastResend < 60){

            $_SESSION['error'] =
                "Please wait before requesting another code.";

            header(
                "Location: " . passenger_url('verify-reset-otp')
            );

            exit;
        }

        if(!passenger_rate_limit('reset_otp_send', strtolower($email) . '|' . passenger_client_ip(), 5, 900, 900)){

            $_SESSION['error'] =
                "Too many requests. Please try again later.";

            header(
                "Location: " . passenger_url('verify-reset-otp')
            );

            exit;
        }

        $ack = "If this email is registered, a new reset code has been sent.";

        /* Best-effort lookup, same non-disclosing behavior as sendOTP. */
        $user = false;
        try {
            $db = Database::get();

            /* Canonical email matching; unknown or email-less accounts get no code
               but the same acknowledgment, and send failures are masked+logged. */
            $stmt = $db->prepare("SELECT * FROM passengers WHERE LOWER(TRIM(email)) = ? LIMIT 1");
            $stmt->execute([strtolower(trim($email))]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Passenger reset resend lookup failed: ' . $e->getMessage());
            $user = false;
        }

        if ($user && trim((string)($user['email'] ?? '')) !== '') {

            $otp = random_int(100000, 999999);

            try {

                $mailer =
                    new MailerService();

                $sent =
                    $mailer->sendOtp($email, $otp);

                if($sent){

                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['otp_created'] = time();
                    $_SESSION['reset_otp_attempts'] = 0;
                    $_SESSION['reset_otp_resend_at'] = time();

                } else {

                    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts'], $_SESSION['otp_created']);

                    error_log('Passenger reset OTP resend failed for account ' . (int)$user['id']);
                }

            } catch (\Throwable $e) {

                unset($_SESSION['reset_otp'], $_SESSION['reset_otp_attempts'], $_SESSION['otp_created']);

                error_log('Passenger reset OTP resend failed: ' . $e->getMessage());
            }
        }

        $_SESSION['success'] = $ack;

        header(
            "Location: " . passenger_url('verify-reset-otp')
        );

        exit;
    }

    public function newPasswordPage()
    {
        $this->startSession();

        if(!isset($_SESSION['reset_verified'])){

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        require $_SERVER['DOCUMENT_ROOT']
        . '/passenger/views/ForgotPassword/new_password.php';
    }

    public function updatePassword()
    {
        $this->startSession();

        if(!isset($_SESSION['reset_verified'])){

            header(
                "Location: " . passenger_url('forgot-password')
            );

            exit;
        }

        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        if (class_exists('App\Passenger\PassengerValidator')) {
            $passCheck = \App\Passenger\PassengerValidator::validatePassword($password, $confirmPassword);
            if (!$passCheck['valid']) {
                $_SESSION['error'] = $passCheck['error'];
                header("Location: " . passenger_url('new-password'));
                exit;
            }
        } else {
            if (strlen($password) < 8) {
                $_SESSION['error'] = "Password must be at least 8 characters.";
                header("Location: " . passenger_url('new-password'));
                exit;
            }
            if ($password !== $confirmPassword) {
                $_SESSION['error'] = "Passwords do not match.";
                header("Location: " . passenger_url('new-password'));
                exit;
            }
        }

        $hashedPassword =
            password_hash($password, PASSWORD_DEFAULT);

        try {
            $db = Database::get();

            $stmt =
                $db->prepare("
                    UPDATE passengers
                    SET password = ?
                    WHERE email = ?
                ");

            $updated =
                $stmt->execute([
                    $hashedPassword,
                    $email
                ]);
        } catch (\Throwable $e) {
            error_log('Passenger updatePassword DB error: ' . $e->getMessage());
            $_SESSION['error'] = "Unable to update password at this time. Please try again.";
            header("Location: " . passenger_url('new-password'));
            exit;
        }

        if($updated){

            try {
                $stmt = $db->prepare("SELECT id FROM passengers WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $passengerId = (int)$stmt->fetchColumn();

                if($passengerId > 0 && class_exists('App\\Passenger\\PassengerSessionService')){
                    \App\Passenger\PassengerSessionService::revokeAll($db, $passengerId, 'password_reset');
                }
            } catch (\Throwable $e) {
                error_log('Passenger updatePassword post-update lookup failed: ' . $e->getMessage());
            }

            unset(
                $_SESSION['reset_email'],
                $_SESSION['reset_otp'],
                $_SESSION['reset_otp_attempts'],
                $_SESSION['reset_otp_resend_at'],
                $_SESSION['otp_created'],
                $_SESSION['reset_verified']
            );

            $_SESSION['success'] =
                "Password updated successfully.";

            header(
                "Location: " . passenger_url('login')
            );

            exit;
        }

        $_SESSION['error'] =
            "Failed to update password.";

        header(
            "Location: " . passenger_url('new-password')
        );

        exit;
    }
}
