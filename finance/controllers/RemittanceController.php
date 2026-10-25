<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../services/RemittanceService.php';
require_once __DIR__ . '/../validators/RemittanceValidator.php';
require_once __DIR__ . '/../models/RemittanceModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';

use Finance\Services\RemittanceService;
use Finance\Validators\RemittanceValidator;
use Finance\Models\RemittanceModel;
use Finance\Models\AuditTrailModel;
use App\Auth\Auth;
use App\Core\MailerService;
use App\Core\Database;

class RemittanceController
{
    /**
     * =========================
     * LOAD VIEW
     * =========================
     */
    public function index()
    {
        $phDate = RemittanceService::manilaDate();
        $businessDate = $phDate;
        $isOpen = RemittanceService::isBusinessDateOpen();

        $data = RemittanceService::getTrips($phDate);

        foreach ($data as &$row) {
            $row['panel_key'] = (string) ($row['driver_id'] ?? '');
        }
        unset($row);

        $drivers = RemittanceService::getDrivers($phDate);

        foreach ($drivers as &$d) {
            $log = RemittanceService::getRemittanceLog($d['driver_id'], $phDate);

            $d['remittance_ref'] = $log
                ? 'RM-' . str_pad((string) $log['id'], 5, '0', STR_PAD_LEFT)
                : null;
            $d['actual_cash'] = $log['actual_cash'] ?? null;
            $d['panel_key'] = (string) $d['driver_id'];
            $d['business_date'] = $phDate;
            $d['overdue'] = false;
        }
        unset($d);

        $overdueDrivers = RemittanceService::getOverdueDrivers();

        $this->dispatchFinanceCodeReminders();
        $financeCodeMeta = RemittanceModel::getFinanceCodeMeta();

        foreach ($overdueDrivers as $overdue) {
            $panelKey = $overdue['driver_id'] . '|' . $overdue['business_date'];

            $day = RemittanceService::computeDriverDay($overdue['driver_id'], $overdue['business_date']);

            foreach ($day['trips'] as $trip) {
                $trip['panel_key'] = $panelKey;
                $trip['submitted'] = 0;
                $data[] = $trip;
            }

            $drivers[] = [
                'driver_id' => $overdue['driver_id'],
                'driver_name' => $overdue['full_name'],
                'total_collection' => (float) $overdue['total_collection'],
                'expected_cash' => (float) $overdue['expected_cash'],
                'submitted' => false,
                'submitted_by' => null,
                'remittance_ref' => 'RM-' . str_pad((string) $overdue['id'], 5, '0', STR_PAD_LEFT),
                'panel_key' => $panelKey,
                'business_date' => $overdue['business_date'],
                'overdue' => true,
            ];
        }

        require __DIR__
            . '/../views/remittance/index.php';
    }

    /**
     * =========================
     * FINAL REMITTANCE
     * =========================
     */
    public function confirm()
    {
        header('Content-Type: application/json');

        $driver = '';
        $businessDate = null;

        try {

            $user = $this->requireFinanceActor();
            $reviewer = trim(
                (string) (
                    $user['username']
                    ?? $user['name']
                    ?? 'Finance Manager'
                )
            );

            $driver = trim($_POST['driver'] ?? '');
            $code = trim($_POST['code'] ?? '');

            $actualCash = round(
                (float) ($_POST['actual_cash'] ?? 0),
                2
            );

            $businessDate =
                RemittanceService::normalizeBusinessDate($_POST['business_date'] ?? null)
                ?? RemittanceService::manilaDate();

            RemittanceValidator::validateDriver($driver);
            RemittanceValidator::validateCode($code);

            /*
            |---------------------------------------------------------
            | FINANCE CODE (server-side, hashed, rate-limited)
            |---------------------------------------------------------
            */

            $actorId = (int) ($user['id'] ?? 0);
            $rate = RemittanceModel::financeCodeAttemptStatus('verify', $actorId);

            if ($rate['blocked']) {
                $minutes = max(1, (int) ceil($rate['retry_after'] / 60));

                AuditTrailModel::record(
                    'Finance', 'Remittance Submission',
                    "Blocked remittance submission for driver $driver on $businessDate: finance code verification temporarily locked.",
                    'RM-' . $driver,
                    null,
                    ['business_date' => $businessDate],
                    'Failed'
                );

                echo json_encode([
                    'success' => false,
                    'code' => 'finance_code_locked',
                    'message' => "Too many incorrect finance code attempts. Please try again in $minutes minute(s)."
                ]);

                exit;
            }

            $verification = RemittanceModel::verifyFinanceCode($code);

            if ($verification['status'] !== 'ok') {

                if ($verification['status'] === 'invalid') {
                    RemittanceModel::recordFinanceCodeFailure('verify', $actorId);
                }

                $message = match ($verification['status']) {
                    'expired' => 'Your Finance Authorization Code expired'
                        . (!empty($verification['meta']['expires_at_label']) ? ' on ' . $verification['meta']['expires_at_label'] : '')
                        . '. Please renew it before submitting remittances.',
                    'missing' => 'No Finance Authorization Code is set. Please set one before submitting remittances.',
                    default => 'Incorrect finance authorization code.',
                };

                AuditTrailModel::record(
                    'Finance', 'Remittance Submission',
                    "Failed to submit remittance for driver $driver on $businessDate: finance code {$verification['status']}.",
                    'RM-' . $driver,
                    null,
                    ['business_date' => $businessDate],
                    'Failed'
                );

                echo json_encode([
                    'success' => false,
                    'code' => 'finance_code_' . $verification['status'],
                    'message' => $message
                ]);

                exit;
            }

            RemittanceModel::clearFinanceCodeFailures('verify', $actorId);

            AuditTrailModel::record(
                'Finance', 'Finance Code Verified',
                "Finance authorization code verified for remittance of driver $driver on $businessDate.",
                'RM-' . $driver,
                null,
                ['business_date' => $businessDate],
                'Success'
            );

            /*
            |---------------------------------------------------------
            | SERVER-AUTHORITATIVE SUBMISSION (NORMAL OR LATE)
            |---------------------------------------------------------
            */

            $result = RemittanceService::submitRemittance([
                'driver_id' => $driver,
                'business_date' => $businessDate,
                'actual_cash' => $actualCash,
                'code' => $code,
                'reviewer' => $reviewer,
                'actor_user_id' => (int) ($user['id'] ?? 0),
            ]);

            $ref = 'RM-' . str_pad((string) $result['remittance_id'], 5, '0', STR_PAD_LEFT);

            AuditTrailModel::record(
                'Finance', 'Remittance Created',
                ($result['is_late'] ? 'Late remittance' : 'Remittance')
                    . " $ref for driver $driver for business date {$result['business_date']} with an amount of \u{20B1}"
                    . number_format($result['actual_cash'], 2) . ".",
                $ref,
                $result['previous_status'] !== null
                    ? ['status' => $result['previous_status'], 'business_date' => $result['business_date']]
                    : null,
                [
                    'driver' => $driver,
                    'business_date' => $result['business_date'],
                    'expected_cash' => $result['expected_cash'],
                    'actual_cash' => $result['actual_cash'],
                    'discrepancy_amount' => $result['discrepancy_amount'],
                    'status' => $result['status'],
                    'late' => $result['is_late'],
                ],
                'Success'
            );

            echo json_encode([
                'success' => true,
                'message' => 'Remittance successfully submitted.',
                'late' => $result['is_late'],
                'summary' => [
                    'remittance_ref' => $ref,
                    'business_date' => $result['business_date'],
                    'total_passengers' => $result['total_passengers'],
                    'trip_count' => $result['trip_count'],
                    'expected_cash' => $result['expected_cash'],
                    'actual_cash' => $result['actual_cash'],
                    'discrepancy_amount' => $result['discrepancy_amount'],
                    'status' => $result['status'],
                ]
            ]);

        } catch (\Throwable $e) {

            try {
                AuditTrailModel::record(
                    'Finance', 'Remittance Submission',
                    "Failed to submit remittance for driver $driver"
                        . ($businessDate ? " on $businessDate" : '')
                        . ': ' . $e->getMessage(),
                    'RM-' . $driver,
                    null,
                    ['business_date' => $businessDate],
                    'Failed'
                );
            } catch (\Throwable $ignore) {
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * =========================
     * DOWNLOAD REPORT (PDF)
     * =========================
     */
    public function downloadReport()
    {
        $this->requireFinanceActor();

        $driverId = trim($_GET['driver'] ?? '');

        if (!$driverId) {
            echo json_encode(['success' => false, 'message' => 'No driver specified.']);
            exit;
        }

        $businessDate =
            RemittanceService::normalizeBusinessDate($_GET['business_date'] ?? null)
            ?? RemittanceService::manilaDate();

        $result = RemittanceService::downloadReport($driverId, $businessDate);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'No trips to export for this driver.']);
            exit;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('pdfBackend', 'CPDF');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($result['html'], 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $dompdf->output();
        exit;
    }

    /**
     * =========================
     * DRIVER TRIPS (READ-ONLY JSON)
     * =========================
     * Used by the Remittance page (including Overdue / late processing) to
     * render a driver's trip details for one business date.
     */
    public function driverTrips()
    {
        header('Content-Type: application/json');

        try {
            $this->requireFinanceActor();

            $driver = trim($_GET['driver'] ?? '');
            RemittanceValidator::validateDriver($driver);

            $businessDate =
                RemittanceService::normalizeBusinessDate($_GET['business_date'] ?? null)
                ?? RemittanceService::manilaDate();

            $day = RemittanceService::computeDriverDay($driver, $businessDate);

            echo json_encode([
                'success' => true,
                'data' => [
                    'driver_id' => $driver,
                    'business_date' => $businessDate,
                    'trip_count' => $day['trip_count'],
                    'total_passengers' => $day['total_passengers'],
                    'total_collection' => $day['total_collection'],
                    'cash_total' => $day['cash_total'],
                    'gcash_total' => $day['gcash_total'],
                    'expected_cash' => $day['expected_cash'],
                    'trips' => $day['trips'],
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * =========================
     * FINANCE ACTOR GUARD
     * =========================
     */
    private function requireFinanceActor(): array
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
            exit;
        }

        $user = Auth::user();
        $role = strtolower(trim((string) ($user['role'] ?? '')));

        if (!in_array($role, ['finance_manager', 'finance_officer', 'admin', 'super_admin'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: finance access required.']);
            exit;
        }

        return $user;
    }

    /**
     * =========================
     * FINANCE CODE STATUS (read-only JSON)
     * =========================
     */
    public function financeCodeStatus()
    {
        header('Content-Type: application/json');

        try {
            $this->requireFinanceActor();
            $meta = RemittanceModel::getFinanceCodeMeta();
            $this->dispatchFinanceCodeReminders($meta);

            $status = 'active';
            if ($meta['legacy_pending'] && !$meta['has_code']) {
                $status = 'legacy';
            } elseif (!$meta['has_code']) {
                $status = 'missing';
            } elseif ($meta['expired']) {
                $status = 'expired';
            } elseif ($meta['days_left'] !== null && $meta['days_left'] <= 7) {
                $status = 'expiring';
            }

            echo json_encode([
                'success' => true,
                'status' => $status,
                'has_code' => (bool) $meta['has_code'],
                'expires_at_label' => $meta['expires_at_label'],
                'days_left' => $meta['days_left'],
                'expired' => (bool) $meta['expired'],
                'legacy_pending' => (bool) $meta['legacy_pending'],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * =========================
     * SEND IDENTITY OTP (alternative to account password)
     * =========================
     */
    public function sendCodeOtp()
    {
        header('Content-Type: application/json');

        try {
            $user = $this->requireFinanceActor();
            $actorId = (int) ($user['id'] ?? 0);

            $rate = RemittanceModel::financeCodeAttemptStatus('auth', $actorId);
            if ($rate['blocked']) {
                $minutes = max(1, (int) ceil($rate['retry_after'] / 60));
                echo json_encode(['success' => false, 'message' => "Too many attempts. Please try again in $minutes minute(s)."]);
                exit;
            }

            $sentAt = (int) ($_SESSION['finance_code_otp_sent_at'] ?? 0);
            if ($sentAt > 0 && (time() - $sentAt) < 60) {
                $wait = 60 - (time() - $sentAt);
                echo json_encode(['success' => false, 'message' => "Please wait $wait second(s) before requesting another code."]);
                exit;
            }

            $email = $this->financeUserEmail($actorId);
            if ($email === '') {
                echo json_encode(['success' => false, 'message' => 'No email address is registered for your account.']);
                exit;
            }

            $otp = (string) random_int(100000, 999999);
            $_SESSION['finance_code_otp'] = [
                'hash' => password_hash($otp, PASSWORD_DEFAULT),
                'expires' => time() + 300,
                'attempts' => 0,
            ];
            $_SESSION['finance_code_otp_sent_at'] = time();

            $mailer = new MailerService();
            if (!$mailer->sendOtp($email, $otp)) {
                unset($_SESSION['finance_code_otp'], $_SESSION['finance_code_otp_sent_at']);
                echo json_encode(['success' => false, 'message' => 'We could not send the verification code. Please try again.']);
                exit;
            }

            AuditTrailModel::record(
                'Finance', 'Finance Code OTP Sent',
                'A verification code was sent to the registered email for a finance authorization code change.',
                null,
                null,
                null,
                'Success'
            );

            echo json_encode([
                'success' => true,
                'message' => 'A verification code was sent to ' . $this->maskEmail($email) . '.',
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * =========================
     * VERIFY IDENTITY BEFORE CODE CHANGE
     * =========================
     */
    public function verifyCodeIdentity()
    {
        header('Content-Type: application/json');

        try {
            $user = $this->requireFinanceActor();
            $actorId = (int) ($user['id'] ?? 0);

            $rate = RemittanceModel::financeCodeAttemptStatus('auth', $actorId);
            if ($rate['blocked']) {
                $minutes = max(1, (int) ceil($rate['retry_after'] / 60));
                echo json_encode(['success' => false, 'message' => "Too many attempts. Please try again in $minutes minute(s)."]);
                exit;
            }

            $method = strtolower(trim((string) ($_POST['method'] ?? 'password')));
            $ok = false;
            $failureReason = 'invalid credentials';

            if ($method === 'otp') {
                $input = trim((string) ($_POST['otp'] ?? ''));
                $otp = $_SESSION['finance_code_otp'] ?? null;

                if (!is_array($otp)) {
                    echo json_encode(['success' => false, 'message' => 'No verification code was requested. Please send one to your email first.']);
                    exit;
                }
                if (time() > (int) ($otp['expires'] ?? 0)) {
                    unset($_SESSION['finance_code_otp']);
                    echo json_encode(['success' => false, 'message' => 'The verification code expired. Please request a new one.']);
                    exit;
                }
                if ((int) ($otp['attempts'] ?? 0) >= 3) {
                    unset($_SESSION['finance_code_otp']);
                    echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.']);
                    exit;
                }

                $ok = $input !== '' && password_verify($input, (string) $otp['hash']);
                $_SESSION['finance_code_otp']['attempts'] = (int) ($otp['attempts'] ?? 0) + 1;
                $failureReason = 'incorrect verification code';
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $db = Database::get();
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$actorId]);
                $hash = (string) $stmt->fetchColumn();
                $ok = $hash !== '' && $password !== '' && password_verify($password, $hash);
                $failureReason = 'incorrect account password';
            }

            if (!$ok) {
                RemittanceModel::recordFinanceCodeFailure('auth', $actorId);

                AuditTrailModel::record(
                    'Finance', 'Finance Code Identity Check',
                    'Failed identity verification for a finance authorization code change (' . $failureReason . ').',
                    null,
                    null,
                    null,
                    'Failed'
                );

                echo json_encode([
                    'success' => false,
                    'message' => $method === 'otp' ? 'Incorrect verification code.' : 'Incorrect account password.',
                ]);
                exit;
            }

            RemittanceModel::clearFinanceCodeFailures('auth', $actorId);
            unset($_SESSION['finance_code_otp'], $_SESSION['finance_code_otp_sent_at']);
            $_SESSION['finance_code_identity'] = [
                'user_id' => $actorId,
                'at' => time(),
            ];

            AuditTrailModel::record(
                'Finance', 'Finance Code Identity Check',
                'Identity verified for a finance authorization code change.',
                null,
                null,
                null,
                'Success'
            );

            echo json_encode(['success' => true, 'message' => 'Identity verified. You can now set your new code.']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * =========================
     * SAVE FINANCE CODE (requires verified identity, exactly 6 digits)
     * =========================
     */
    public function saveCode()
    {
        header('Content-Type: application/json');

        try {
            $user = $this->requireFinanceActor();
            $actorId = (int) ($user['id'] ?? 0);

            $identity = $_SESSION['finance_code_identity'] ?? null;
            $verified = is_array($identity)
                && (int) ($identity['user_id'] ?? 0) === $actorId
                && (time() - (int) ($identity['at'] ?? 0)) <= 600;

            if (!$verified) {
                echo json_encode([
                    'success' => false,
                    'code' => 'identity_required',
                    'message' => 'Please verify your identity (account password or email code) before setting a new finance code.',
                ]);
                exit;
            }

            $code = trim((string) ($_POST['code'] ?? ''));
            $confirmCode = trim((string) ($_POST['confirm_code'] ?? ''));

            if (!preg_match('/^\d{6}$/', $code)) {
                throw new \Exception('The Finance Authorization Code must be exactly 6 numeric digits.');
            }

            if ($confirmCode !== '' && !hash_equals($code, $confirmCode)) {
                throw new \Exception('The confirmation code does not match.');
            }

            $email = $this->financeUserEmail($actorId);
            $meta = RemittanceModel::setFinanceCodeHash($code, $actorId, $email !== '' ? $email : null);

            unset($_SESSION['finance_code_identity'], $_SESSION['finance_code_otp'], $_SESSION['finance_code_otp_sent_at']);

            AuditTrailModel::record(
                'Finance', 'Finance Code Updated',
                'Finance authorization code was set and expires on ' . ($meta['expires_at_label'] ?? '') . '.',
                null,
                null,
                null,
                'Success'
            );

            echo json_encode([
                'success' => true,
                'message' => 'Finance authorization code saved.',
                'expires_at_label' => $meta['expires_at_label'],
                'days_left' => $meta['days_left'],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * =========================
     * FINANCE CODE HELPERS
     * =========================
     */
    private function financeUserEmail(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        try {
            $db = Database::get();
            $stmt = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return trim((string) $stmt->fetchColumn());
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return $email;
        }

        $name = $parts[0];
        $masked = substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1));

        return $masked . '@' . $parts[1];
    }

    private function dispatchFinanceCodeReminders(?array $meta = null): void
    {
        $meta = $meta ?? RemittanceModel::getFinanceCodeMeta();

        if (!$meta['has_code'] || $meta['expired'] || $meta['days_left'] === null) {
            return;
        }

        foreach (RemittanceModel::FINANCE_CODE_NOTIFY_DAYS as $days) {
            if ($meta['days_left'] > $days || !empty($meta['notified'][$days])) {
                continue;
            }

            RemittanceModel::markFinanceCodeNotified($days);

            $title = $days <= 1
                ? 'Finance Authorization Code expires tomorrow'
                : 'Finance Authorization Code expires in ' . $days . ' days';
            $description = 'Your Finance Authorization Code expires on '
                . ($meta['expires_at_label'] ?? 'soon')
                . '. Renew it in Finance > Remittance > Finance Code.';

            try {
                $notification = new \App\Models\Notification();
                $notification->createForRole('finance_manager', $title, $description, 'warning', 'finance_code', $days);
                $notification->createForRole('finance_officer', $title, $description, 'warning', 'finance_code', $days);
            } catch (\Throwable $e) {
                error_log('Finance code reminder notification failed: ' . $e->getMessage());
            }

            $email = (string) ($meta['owner_email'] ?? '');
            if ($email === '') {
                $actor = Auth::user();
                $email = $this->financeUserEmail((int) ($actor['id'] ?? 0));
            }

            if ($email !== '') {
                try {
                    (new MailerService())->sendFinanceCodeExpiry($email, $days, (string) ($meta['expires_at_label'] ?? ''));
                } catch (\Throwable $e) {
                    error_log('Finance code reminder email failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * =========================
     * AUDIT TRAIL (read-only)
     * =========================
     */
    public function auditTrail()
    {
        header('Content-Type: application/json');

        $recordId = trim(
            $_GET['record_id']
            ?? $_POST['record_id']
            ?? ''
        );

        if (!$recordId) {
            echo json_encode(['success' => false, 'message' => 'No record specified.']);
            exit;
        }

        $model = new AuditTrailModel();
        $rows = $model->getByRecordId($recordId, 'Finance');

        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
}
?>