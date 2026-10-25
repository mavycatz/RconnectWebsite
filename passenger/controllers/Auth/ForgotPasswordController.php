<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/MailerService.php';

use App\Core\Database;
use App\Core\MailerService;

class ForgotPasswordController {

    public function sendCode(){

        ob_clean();
        header('Content-Type: application/json');

        $email = trim((string)($_POST['email'] ?? ''));

        if (class_exists('App\Passenger\PassengerValidator')) {
            $emailCheck = \App\Passenger\PassengerValidator::validateEmail($email);
            if (!$emailCheck['valid']) {
                echo json_encode([
                    "status" => "error",
                    "message" => $emailCheck['error']
                ]);
                return;
            }
            $email = $emailCheck['value'];
        } else {
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Please enter a valid email address."
                ]);
                return;
            }
        }

        if(!passenger_rate_limit('reset_otp_send', strtolower($email) . '|' . passenger_client_ip(), 5, 900, 900)){
            echo json_encode([
                "status"=>"error",
                "message"=>"Too many requests. Please try again later."
            ]);
            return;
        }

        /* Best-effort lookup: a database failure must fail the request with a
           friendly JSON response, never replace it with the service error page. */
        $exists = false;
        try {
            $conn = Database::get();

            $stmt = $conn->prepare("SELECT id FROM passengers WHERE LOWER(TRIM(email))=?");
            $stmt->execute([$email]);

            $exists = (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            error_log('Passenger profile reset lookup failed: ' . $e->getMessage());
            echo json_encode([
                "status" => "error",
                "message" => "Unable to send the code right now. Please try again."
            ]);
            return;
        }

        $_SESSION['reset_email'] = $email;
        unset($_SESSION['reset_verified'], $_SESSION['reset_code'], $_SESSION['reset_code_attempts']);

        if(!$exists){
            echo json_encode([
                "status"=>"success"
            ]);
            return;
        }

        $code = random_int(100000, 999999);

        $_SESSION['reset_code'] = $code;
        $_SESSION['reset_code_created'] = time();
        $_SESSION['reset_code_attempts'] = 0;

        $mailer = null;
        try {
            $mailer = new MailerService();
        } catch (\Throwable $e) {
            error_log('Passenger profile reset mailer unavailable: ' . $e->getMessage());
        }

        if (!$mailer || !$mailer->sendOtp($email, $code)) {
            error_log('Passenger profile reset OTP send failed for ' . $email);
            echo json_encode([
                "status"=>"error",
                "message"=>"Unable to send the code right now. Please try again."
            ]);
            return;
        }

        echo json_encode([
            "status"=>"success"
        ]);
    }

    public function verify(){

        ob_clean();
        header('Content-Type: application/json');

        $inputCode = preg_replace('/\D/', '', trim((string)($_POST['code'] ?? '')));
        $realCode  = preg_replace('/\D/', '', (string)($_SESSION['reset_code'] ?? ''));
        $created   = (int)($_SESSION['reset_code_created'] ?? 0);

        if (strlen($inputCode) !== 6) {
            echo json_encode([
                "status" => "error",
                "message" => "Please enter a valid 6-digit code."
            ]);
            return;
        }

        if($inputCode === '' || $realCode === ''){
            echo json_encode([
                "status"=>"error",
                "message"=>"Session expired"
            ]);
            return;
        }

        if($created > 0 && time() - $created > 300){
            unset($_SESSION['reset_code'], $_SESSION['reset_code_attempts'], $_SESSION['reset_code_created']);
            echo json_encode([
                "status"=>"error",
                "message"=>"Code expired. Please request a new one."
            ]);
            return;
        }

        $attempts = (int)($_SESSION['reset_code_attempts'] ?? 0) + 1;

        if($attempts > 5){
            unset($_SESSION['reset_code'], $_SESSION['reset_code_attempts']);
            echo json_encode([
                "status"=>"error",
                "message"=>"Too many incorrect attempts. Please request a new code."
            ]);
            return;
        }

        if(strlen($inputCode) === 6 && hash_equals($realCode, $inputCode)){
            unset($_SESSION['reset_code'], $_SESSION['reset_code_attempts'], $_SESSION['reset_code_created']);
            $_SESSION['reset_verified'] = true;
            echo json_encode([
                "status"=>"success"
            ]);
            return;
        }

        $_SESSION['reset_code_attempts'] = $attempts;

        echo json_encode([
            "status"=>"error",
            "message"=>"Invalid code"
        ]);
    }

    public function update(){

        ob_clean();
        header('Content-Type: application/json');

        $pass           = (string)($_POST['password'] ?? '');
        $confirm        = (string)($_POST['confirm'] ?? '');
        $currentPass    = (string)($_POST['current_password'] ?? '');
        $email          = $_SESSION['reset_email'] ?? null;

        if(empty($_SESSION['reset_verified']) || !$email){
            echo json_encode([
                "status"=>"error",
                "message"=>"Please verify the code first"
            ]);
            return;
        }

        try {
            $conn = Database::get();

            // The current password is verified server-side against the account
            // that owns the OTP-verified email before any change is accepted.
            $stmt = $conn->prepare("SELECT id, password FROM passengers WHERE LOWER(TRIM(email))=? LIMIT 1");
            $stmt->execute([$email]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$account){
                echo json_encode([
                    "status"=>"error",
                    "message"=>"Unable to verify the account. Please restart the process."
                ]);
                return;
            }

            if($currentPass === '' || !password_verify($currentPass, (string)$account['password'])){
                echo json_encode([
                    "status"=>"error",
                    "message"=>"Current password is incorrect."
                ]);
                return;
            }
        } catch (\Throwable $e) {
            error_log('Passenger Auth/ForgotPasswordController update lookup error: ' . $e->getMessage());
            echo json_encode([
                "status" => "error",
                "message" => "Unable to update password at this time. Please try again."
            ]);
            return;
        }

        if (class_exists('App\Passenger\PassengerValidator')) {
            $passCheck = \App\Passenger\PassengerValidator::validatePassword($pass, $confirm);
            if (!$passCheck['valid']) {
                echo json_encode([
                    "status" => "error",
                    "message" => $passCheck['error']
                ]);
                return;
            }
        } else {
            if (!$pass || !$confirm) {
                echo json_encode([
                    "status" => "error",
                    "message" => "All fields required"
                ]);
                return;
            }

            if ($pass !== $confirm) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Passwords do not match"
                ]);
                return;
            }

            if (strlen($pass) < 8) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Password must be at least 8 characters"
                ]);
                return;
            }

            if (strlen($pass) > 72) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Password cannot exceed 72 characters"
                ]);
                return;
            }
        }

        // Composition rule enforced here (the shared validator only covers
        // presence/length/match): at least one letter and one number.
        if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            echo json_encode([
                "status" => "error",
                "message" => "Password must include at least one letter and one number."
            ]);
            return;
        }

        try {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE passengers SET password=? WHERE LOWER(TRIM(email))=?");
            $stmt->execute([$hashed, $email]);

            $passengerId = (int)($account['id'] ?? 0);

            if ($passengerId > 0 && class_exists('App\\Passenger\\PassengerSessionService')) {
                \App\Passenger\PassengerSessionService::revokeAll($conn, $passengerId, 'password_reset');
            }
        } catch (\Throwable $e) {
            error_log('Passenger Auth/ForgotPasswordController update DB error: ' . $e->getMessage());
            echo json_encode([
                "status" => "error",
                "message" => "Unable to update password at this time. Please try again."
            ]);
            return;
        }

        unset(
            $_SESSION['reset_email'],
            $_SESSION['reset_code'],
            $_SESSION['reset_code_attempts'],
            $_SESSION['reset_code_created'],
            $_SESSION['reset_verified']
        );

        echo json_encode([
            "status"=>"success"
        ]);
    }
}
