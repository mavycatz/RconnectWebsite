<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailerService
{
    private const LOGO_PATH = __DIR__ . '/../../assets/images/logo/logoR.png';

    private function configureMailer(): PHPMailer
    {
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/PHPMailer/src/Exception.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rconnect@rconnecttms.online';
        $mail->Password   = 'MavyAbrenica#15';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->Timeout    = 15;

        $mail->setFrom('rconnect@rconnecttms.online', 'R-Connect Security');
        $mail->isHTML(true);

        return $mail;
    }

    /**
     * Login URL of the Dispatcher portal.
     *
     * The dispatcher front controller only serves the rotated hash routes
     * from dispatcher/utils/urls.php; direct .php paths are blocked by the
     * dispatcher .htaccess. Reading the current hash keeps the emailed link
     * valid across rotations. If the mapping cannot be read, the dispatcher
     * root is used, which redirects to the current login route.
     */
    private function dispatcherPortalUrl(): string
    {
        $url = 'https://rconnecttms.online/dispatcher/';

        $urlsFile = __DIR__ . '/../../dispatcher/utils/urls.php';

        if (is_file($urlsFile)) {

            $urls = require $urlsFile;

            if (is_array($urls) && !empty($urls['login'])) {

                $url = 'https://rconnecttms.online/dispatcher/' . $urls['login'];

            }

        }

        return $url;
    }

    private function renderEmailShell(PHPMailer $mail, string $headerSubtitle, string $bodyContent): string
    {
        $year = date('Y');
        $logoCid = '';
        if (file_exists(self::LOGO_PATH)) {
            $mail->addEmbeddedImage(self::LOGO_PATH, 'logo', 'logoR.png');
            $logoCid = 'cid:logo';
        }
        $logoHtml = $logoCid
            ? "<img src='{$logoCid}' alt='R-Connect' width='48' height='48' style='display:block;margin:0 auto;border:0;outline:none;text-decoration:none;width:48px;height:48px;'>"
            : "<span style='display:block;font-size:24px;font-weight:700;color:#ffffff;line-height:48px;'>R-Connect</span>";

        return "
<style>
@media only screen and (max-width:600px){
    .email-shell{padding:20px 10px !important;}
    .email-card{border-radius:12px !important;}
    .email-header{padding:24px 16px !important;}
    .email-body{padding:20px 16px !important;}
    .email-footer{padding:16px !important;}
    .email-table td{display:block !important;width:100% !important;padding:6px 0 !important;text-align:left !important;border-bottom:1px solid #e5e7eb !important;}
    .email-table td:last-child{border-bottom:none !important;}
    .email-table td:first-child{font-size:12px !important;}
    .email-table tr:last-child td{border-bottom:none !important;}
    .email-btn{padding:12px 24px !important;font-size:13px !important;display:block !important;text-align:center !important;}
    .email-security{padding:12px !important;}
    .email-security h4{font-size:11px !important;}
    .email-security p{font-size:12px !important;}
    .email-section{padding:14px !important;margin:14px 0 !important;}
    .email-section h3{font-size:13px !important;}
    .email-body p{font-size:13px !important;}
    .otp-code{font-size:26px !important;letter-spacing:6px !important;padding:14px 12px 14px 18px !important;}
}
</style>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' class='email-shell' style='background:#f0f2f0;'>
    <tr>
        <td align='center' style='padding:40px 20px;font-family:Poppins,Arial,sans-serif;'>
            <table role='presentation' width='560' cellpadding='0' cellspacing='0' border='0' class='email-card' style='width:100%;max-width:560px;background:#ffffff;border-radius:16px;'>
                <tr>
                    <td class='email-header' style='background:#1b5e20;padding:32px 24px;text-align:center;border-bottom:4px solid #2e7d32;border-radius:16px 16px 0 0;'>
                        {$logoHtml}
                        <p style='margin:12px 0 0;color:#a5d6a7;font-size:12px;font-weight:500;letter-spacing:2px;text-transform:uppercase;font-family:Poppins,Arial,sans-serif;'>{$headerSubtitle}</p>
                    </td>
                </tr>
                <tr>
                    <td class='email-body' style='padding:32px 28px;font-family:Poppins,Arial,sans-serif;'>
                        {$bodyContent}
                    </td>
                </tr>
                <tr>
                    <td class='email-footer' style='background:#f9fafb;padding:20px 28px;text-align:center;color:#999;font-size:11px;border-top:1px solid #e5e7eb;border-radius:0 0 16px 16px;font-family:Poppins,Arial,sans-serif;'>
                        &copy; {$year} R-Connect Transport Management System
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>";
    }

    private function credentialsTable(array $rows): string
    {
        $html = '';
        $count = count($rows);
        foreach ($rows as $i => $row) {
            $bottomBorder = $i < $count - 1 ? 'border-bottom:1px solid #e5e7eb;' : '';
            $html .= "<tr>
                <td style='padding:10px 0;color:#666;font-size:13px;{$bottomBorder}'>{$row['label']}</td>
                <td style='padding:10px 0;text-align:right;color:#1b5e20;font-weight:700;font-size:14px;{$bottomBorder}'>{$row['value']}</td>
            </tr>";
        }
        return $html;
    }

    private function sectionBox(string $content, string $bg = '#f9fafb', string $border = '#e5e7eb'): string
    {
        return "<div style='background:{$bg};border:1px solid {$border};border-radius:12px;padding:20px;margin:20px 0;'>{$content}</div>";
    }

    private function credentialsBox(string $tableHtml): string
    {
        return "
        <div class='email-section' style='background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin:20px 0;'>
            <table class='email-table' style='width:100%;border-collapse:collapse;'>
                {$tableHtml}
            </table>
        </div>";
    }

    private function securityBox(string $text): string
    {
        return "
        <div style='background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:10px;padding:16px;margin-top:24px;'>
            <h4 style='margin:0 0 8px;color:#92400e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;'>Security Reminder</h4>
            <p style='margin:0;color:#666;line-height:1.8;font-size:13px;'>{$text}</p>
        </div>";
    }

    private function actionButton(string $url, string $label): string
    {
        return "
        <div style='text-align:center;margin:24px 0;'>
            <a href='{$url}'
               style='display:inline-block;background:#1b5e20;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:600;font-size:14px;'>
                {$label}
            </a>
        </div>";
    }

    private function sectionTitle(string $title): string
    {
        return "<h3 style='margin:0 0 16px;color:#1b5e20;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;'>{$title}</h3>";
    }

    private function bodyText(string $text): string
    {
        return "<p style='color:#555;line-height:1.9;font-size:14px;margin:0 0 6px;'>{$text}</p>";
    }

    public function sendOtp(string $toEmail, string $otp): bool
    {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Your OTP Code - R-Connect';

            $body = "
            <h2 style='margin-top:0;color:#1b5e20;font-size:20px;font-weight:700;'>One-Time Password</h2>
            " . $this->bodyText('We received a request to verify your identity. Use the OTP code below to continue.') . "
            <div style='text-align:center;margin:28px 0;'>
                <div class='otp-code' style='display:inline-block;background:#f9fafb;border:2px dashed #a5d6a7;border-radius:12px;padding:18px 26px 18px 36px;font-size:34px;font-weight:700;color:#1b5e20;letter-spacing:10px;white-space:nowrap;'>{$otp}</div>
            </div>
            <div style='background:#f9fafb;border-left:4px solid #2e7d32;padding:14px 16px;border-radius:8px;'>
                <strong style='color:#1b5e20;font-size:13px;'>Security Notice</strong>
                <p style='margin:8px 0 0;color:#666;line-height:1.6;font-size:13px;'>This OTP will expire in <strong>5 minutes</strong>. Do not share this code with anyone.</p>
            </div>
            ";

            $mail->AltBody = 'Your R-Connect verification code is ' . $otp . '. It expires in 5 minutes. Do not share this code with anyone.';

            $mail->Body = $this->renderEmailShell($mail, 'Security Verification', $body);

            if (!$mail->send()) {
                $_SESSION['error'] = "Mail Error: " . $mail->ErrorInfo;
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $_SESSION['error'] = "Mail Exception: " . $e->getMessage();
            return false;
        }
    }

    public function sendFinanceCodeExpiry(
        string $toEmail,
        int $daysLeft,
        string $expiresAtLabel
    ): bool {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Finance Authorization Code Expiring - R-Connect';

            $urgency = $daysLeft <= 1
                ? 'Your Finance Authorization Code expires <strong>tomorrow</strong>.'
                : 'Your Finance Authorization Code expires in <strong>' . (int) $daysLeft . ' days</strong>.';

            $body = "
            <h2 style='margin-top:0;color:#1b5e20;font-size:20px;font-weight:700;'>Finance Authorization Code Expiring</h2>
            " . $this->bodyText($urgency) . "
            " . $this->bodyText('Expiration date: <strong>' . htmlspecialchars($expiresAtLabel, ENT_QUOTES, 'UTF-8') . '</strong>') . "
            " . $this->bodyText('Renew it from Finance &rarr; Remittance &rarr; Finance Code so you can keep authorizing driver remittance submissions.') . "
            " . $this->securityBox('Your Finance Authorization Code verifies and authorizes finance transactions, including driver remittance submissions. For security, it expires every 30 days and must be renewed. Never share your code with anyone.') . "
            ";

            $mail->AltBody = 'Your R-Connect Finance Authorization Code expires on ' . $expiresAtLabel
                . '. Renew it in Finance > Remittance to keep authorizing transactions. Never share your code with anyone.';
            $mail->Body = $this->renderEmailShell($mail, 'Finance Authorization Code', $body);

            if (!$mail->send()) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sendPasswordReset(string $toEmail, string $resetLink): bool
    {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Reset Your Password - R-Connect';

            $body = "
            <h2 style='margin-top:0;color:#1b5e20;font-size:20px;font-weight:700;'>Reset Your Password</h2>
            " . $this->bodyText('We received a request to reset the password for your R-Connect account.') . "
            " . $this->bodyText('Click the button below to create a new password.') . "
            " . $this->actionButton($resetLink, 'Reset Password') . "
            <div style='background:#f9fafb;border-left:4px solid #2e7d32;padding:14px 16px;border-radius:8px;'>
                <strong style='color:#1b5e20;font-size:13px;'>Security Notice</strong>
                <p style='margin:8px 0 0;color:#666;line-height:1.6;font-size:13px;'>This password reset link will expire in <strong>1 hour</strong>.</p>
            </div>
            <p style='color:#666;line-height:1.8;font-size:13px;margin-top:20px;'>If you did not request a password reset, you can safely ignore this email.</p>
            ";

            $mail->Body = $this->renderEmailShell($mail, 'Password Recovery', $body);
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function sendDriverCredentials(
        string $toEmail,
        string $fullName,
        string $driverId,
        string $username,
        string $password
    ): bool
    {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Welcome to R-Connect Driver Portal';

            $body = "
            <p style='color:#555;line-height:1.9;font-size:14px;margin-top:0;'>
                Hello <strong style='color:#1b5e20;'>{$fullName}</strong>,
                your driver account is ready. To begin receiving assignments, viewing schedules,
                managing trips, and accessing driver services, install the official R-Connect Driver App.
            </p>
            " . $this->actionButton(
                'https://expo.dev/accounts/mavycatz/projects/Rconnect-Inspect/builds/10d20da0-3bb7-4162-9cfd-90d72f1092ea',
                'Download Driver App'
            ) . "
            " . $this->sectionTitle('Account Credentials') . "
            " . $this->credentialsBox(
                $this->credentialsTable([
                    ['label' => 'Driver ID', 'value' => $driverId],
                    ['label' => 'Username', 'value' => $username],
                    ['label' => 'Password', 'value' => $password],
                ])
            ) . "
            " . $this->securityBox('After installing the app, sign in using the credentials provided above. Once logged in, change your password immediately and complete your profile setup before accepting trips.') . "
            ";

            $mail->Body = $this->renderEmailShell($mail, 'Driver Account Created', $body);
            $mail->AltBody = "Driver ID: {$driverId}\nUsername: {$username}\nPassword: {$password}";

            if (!$mail->send()) {
                throw new Exception($mail->ErrorInfo);
            }
            return true;
        } catch (Throwable $e) {
            throw new Exception('MAIL ERROR: ' . $e->getMessage());
        }
    }

    public function sendEmployeeCredentials(
        string $toEmail,
        string $fullName,
        string $employeeId,
        string $username,
        string $password,
        string $role
    ): bool
    {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Welcome to R-Connect Finance Department';

            $roleName = ucwords(str_replace('_', ' ', $role));

            $body = "
            <p style='color:#555;line-height:1.9;font-size:14px;margin-top:0;'>
                Hello <strong style='color:#1b5e20;'>{$fullName}</strong>,
                welcome to the R-Connect Finance Department. You can now access financial reports,
                revenue summaries, expense tracking, analytics dashboards, and other finance-related tools.
            </p>
            " . $this->sectionTitle('Account Credentials') . "
            " . $this->credentialsBox(
                $this->credentialsTable([
                    ['label' => 'Employee ID', 'value' => $employeeId],
                    ['label' => 'Username', 'value' => $username],
                    ['label' => 'Password', 'value' => $password],
                    ['label' => 'Role', 'value' => $roleName],
                ])
            ) . "
            " . $this->sectionTitle('Finance Department Access') . "
            <p style='color:#555;line-height:1.9;font-size:14px;'>
                Use your credentials to access the R-Connect Finance Management System,
                monitor revenues, review reports, generate analytics, and manage finance operations.
            </p>
            " . $this->actionButton('https://rconnecttms.online/public/login', 'Open Finance Portal') . "
            " . $this->securityBox('Change your password immediately after your first login and never share your credentials with anyone.') . "
            ";

            $mail->Body = $this->renderEmailShell($mail, 'Finance Account Created', $body);
            $mail->AltBody = "Finance Department Account Created\n\nEmployee ID: {$employeeId}\nUsername: {$username}\nPassword: {$password}\nRole: {$roleName}\n\nPortal:\nhttps://rconnecttms.online";

            return $mail->send();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function sendAdminCredentials(
        string $toEmail,
        string $fullName,
        string $adminId,
        string $username,
        string $password,
        string $role
    ): bool
    {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);
            $mail->Subject = 'Welcome to R-Connect Administration Department';

            $roleName = ucwords(str_replace('_', ' ', $role));

            $body = "
            <p style='color:#555;line-height:1.9;font-size:14px;margin-top:0;'>
                Hello <strong style='color:#1b5e20;'>{$fullName}</strong>,
                welcome to the R-Connect Administration Department. You can now access administrative tools,
                user management, employee records, operational dashboards, system settings, and other management features.
            </p>
            " . $this->sectionTitle('Account Credentials') . "
            " . $this->credentialsBox(
                $this->credentialsTable([
                    ['label' => 'Admin ID', 'value' => $adminId],
                    ['label' => 'Username', 'value' => $username],
                    ['label' => 'Password', 'value' => $password],
                    ['label' => 'Role', 'value' => $roleName],
                ])
            ) . "
            " . $this->sectionTitle('Administration Portal Access') . "
            <p style='color:#555;line-height:1.9;font-size:14px;'>
                Use your credentials to access the R-Connect Administration Portal,
                manage users, oversee system operations, monitor activities,
                review reports, and configure platform settings.
            </p>
            " . $this->actionButton('https://rconnecttms.online/public/login', 'Open Admin Portal') . "
            " . $this->securityBox('Change your password immediately after your first login and never share your administrator credentials with anyone. Administrator accounts have elevated access privileges.') . "
            ";

            $mail->Body = $this->renderEmailShell($mail, 'Administration Account Created', $body);
            $mail->AltBody = "Administration Department Account Created\n\nAdmin ID: {$adminId}\nUsername: {$username}\nPassword: {$password}\nRole: {$roleName}\n\nPortal:\nhttps://rconnecttms.online/admin/login";

            return $mail->send();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function sendInspectorCredentials(
        string $toEmail,
        string $fullName,
        string $inspectorId,
        string $password,
        string $role = 'inspector'
    ): bool {
        try {
            $mail = $this->configureMailer();
            $mail->addAddress($toEmail);

            $roleLabel = ucwords(str_replace('_', ' ', $role));

            if ($role === 'dispatcher') {
                $mail->Subject = 'Welcome to R-Connect — Dispatcher Portal';
                $headerSubtitle = 'Dispatcher Account Created';
                $description = 'Your Dispatcher account has been successfully created. You can now manage trip dispatching, coordinate driver assignments, monitor bus schedules, oversee terminal operations, and facilitate passenger transport coordination.';
                $portalUrl = $this->dispatcherPortalUrl();
                $portalBtnLabel = 'Open Dispatcher Portal';
                $portalTitle = 'Dispatcher Portal Access';
                $portalDesc = 'Use your credentials to access the R-Connect Dispatcher Portal and manage dispatching, trips, and transport operations from your device.';
                $securityText = 'Change your password immediately after your first login and never share your credentials with anyone. Dispatcher accounts have elevated access privileges.';
            } else {
                $mail->Subject = 'Welcome to R-Connect — Inspector Portal';
                $headerSubtitle = 'Inspector Account Created';
                $description = 'Your Inspector Portal account has been successfully created. You can now access the Inspector Portal to manage inspections, verify passenger trips, monitor boarding activities, and oversee daily transport operations.';
                $portalUrl = 'https://expo.dev/accounts/mavycatz/projects/Rconnect-Inspect/builds/10d20da0-3bb7-4162-9cfd-90d72f1092ea';
                $portalBtnLabel = 'Open Inspector Portal';
                $portalTitle = 'Inspector Portal Access';
                $portalDesc = 'Use your credentials to access the R-Connect Inspector Portal and manage your daily transport operations.';
                $securityText = 'Change your password immediately after your first login and never share your credentials with anyone. Inspector accounts have elevated access privileges.';
            }

            $rows = [['label' => 'User ID', 'value' => $inspectorId]];
            if ($role !== 'inspector') {
                $rows[] = ['label' => 'Role', 'value' => $roleLabel];
            }
            $rows[] = ['label' => 'Password', 'value' => $password];
            $rows[] = ['label' => 'Portal', 'value' => 'rconnecttms.online'];

            $body = "
            <p style='color:#555;line-height:1.9;font-size:14px;margin-top:0;'>
                Hello <strong style='color:#1b5e20;'>{$fullName}</strong>, {$description}
            </p>
            " . $this->sectionTitle('Account Credentials') . "
            " . $this->credentialsBox(
                $this->credentialsTable($rows)
            ) . "
            " . $this->sectionTitle($portalTitle) . "
            <p style='color:#555;line-height:1.9;font-size:14px;'>{$portalDesc}</p>
            " . $this->actionButton($portalUrl, $portalBtnLabel) . "
            " . $this->securityBox($securityText) . "
            ";

            $mail->Body = $this->renderEmailShell($mail, $headerSubtitle, $body);
            return $mail->send();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
