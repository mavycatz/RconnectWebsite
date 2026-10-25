<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PassengerService {

    private $model;

    public function __construct($model){
        $this->model = $model;
    }

    private function configureMailer(): PHPMailer
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/Create_Account/PHPMailer/src/PHPMailer.php';
        require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/Create_Account/PHPMailer/src/SMTP.php';
        require_once $_SERVER['DOCUMENT_ROOT'].'/passenger/services/Create_Account/PHPMailer/src/Exception.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rconnect@rconnecttms.online';
        $mail->Password   = 'MavyAbrenica#15'; // 🔥 fix this
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('rconnect@rconnecttms.online', 'R-Connect Security');
        $mail->isHTML(true);

        return $mail;
    }

    public function sendOtp(string $toEmail, string $otp): bool
    {
        try {

            $mail = $this->configureMailer();

            $mail->addAddress($toEmail);
            $mail->Subject = 'Your OTP Code - R-Connect';

            $mail->Body = "
                <div style='font-family:Poppins, sans-serif;'>
                    <h2 style='color:#03580a;'>R-Connect Security Verification</h2>
                    <p>Your One-Time Password (OTP) is:</p>
                    <div style='font-size:28px; font-weight:bold; color:#0e7f00; margin:15px 0;'>
                        {$otp}
                    </div>
                    <p>This code expires in <strong>5 minutes</strong>.</p>
                </div>
            ";

            $mail->send();
            return true;

        } catch (Exception $e) {
            return false;
        }
    }
}