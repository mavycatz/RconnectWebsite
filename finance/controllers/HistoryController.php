<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../services/HistoryService.php';
require_once __DIR__ . '/../services/DateFilterService.php';
require_once __DIR__ . '/../models/HistoryModel.php';
require_once __DIR__ . '/../models/ReceiptOtpModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../../app/Core/MailerService.php';

use Finance\Services\HistoryService;
use Finance\Services\DateFilterService;
use Finance\Models\HistoryModel;
use Finance\Models\ReceiptOtpModel;
use Finance\Models\AuditTrailModel;
use App\Auth\Auth;
use App\Core\MailerService;

class HistoryController
{
    /**
     * =========================
     * LOAD HISTORY PAGE
     * =========================
     */
    public function index()
    {
        try {

            // =========================
            // GET HISTORY
            // =========================
            $history =
                HistoryService::getHistory();

            // =========================
            // GET TOTALS
            // =========================
            $totals =
                HistoryService::getTotals();

            // =========================
            // LOAD VIEW
            // =========================
            require __DIR__
                . '/../views/history/index.php';

        } catch (\Exception $e) {

            die(
                'Unable to load remittance history: '
                . $e->getMessage()
            );
        }
    }

    /**
     * =========================
     * VIEW SINGLE REMITTANCE
     * =========================
     */
    public function view()
    {
        try {

            $id =
                (int)(
                    $_GET['id']
                    ?? 0
                );

            if ($id <= 0) {

                throw new \Exception(
                    'Invalid remittance ID.'
                );
            }

            // =========================
            // GET RECORD
            // =========================
            $remittance =
                HistoryService
                    ::getById($id);

            if (!$remittance) {

                throw new \Exception(
                    'Remittance record not found.'
                );
            }

            // =========================
            // LOAD DETAILS VIEW
            // =========================
            require __DIR__
                . '/../views/history/details.php';

        } catch (\Exception $e) {

            die(
                $e->getMessage()
            );
        }
    }

    /**
     * =========================
     * REQUEST PRINT OTP
     * =========================
     */
    public function requestOtp()
    {
        header('Content-Type: application/json');

        try {

            $id =
                (int)(
                    $_POST['id']
                    ?? 0
                );

            if ($id <= 0) {

                throw new \Exception(
                    'Invalid remittance ID.'
                );
            }

            $receipt =
                HistoryService
                    ::getById($id);

            if (!$receipt) {

                throw new \Exception(
                    'Receipt not found.'
                );
            }

            $user =
                Auth::user();

            if (!$user || empty($user['id'])) {

                throw new \Exception(
                    'You must be logged in to print a receipt.'
                );
            }

            // =========================
            // FINANCE USER EMAIL
            // =========================
            $db =
                \App\Core\Database::get();

            $stmt =
                $db->prepare("

                    SELECT email

                    FROM users

                    WHERE id = ?

                    LIMIT 1

                ");

            $stmt->execute([
                (int) $user['id']
            ]);

            $emailRow =
                $stmt->fetch(
                    \PDO::FETCH_ASSOC
                );

            $email =
                $emailRow['email']
                ?? null;

            if (!$email) {

                throw new \Exception(
                    'No email is linked to your account. Please contact an administrator.'
                );
            }

            // =========================
            // CREATE OTP
            // =========================
            $otpModel =
                new ReceiptOtpModel();

            $otpResult =
                $otpModel->create(
                    (int) $user['id'],
                    $id
                );

            // =========================
            // SEND OTP
            // =========================
            $mailer =
                new MailerService();

            $sent =
                $mailer->sendOtp(
                    $email,
                    $otpResult['otp']
                );

            if (!$sent) {

                $detail =
                    $_SESSION['error']
                    ?? 'Unknown mailer error.';

                unset(
                    $_SESSION['error']
                );

                throw new \Exception(
                    'Unable to send the OTP. '
                    . $detail
                );
            }

            echo json_encode([

                'success' => true,

                'message' => 'An OTP has been sent to your email. It expires in 5 minutes.'
            ]);

        } catch (\Exception $e) {

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * =========================
     * PRINT RECEIPT (OTP GATED)
     * POST + otp -> JSON {success, html} for the in-page modal
     * GET (direct link) -> HTML receipt or OTP fallback page
     * =========================
     */
    public function print()
    {
        try {

            $isJson =
                ($_SERVER['REQUEST_METHOD'] === 'POST');

            $id =
                (int)(
                    $_GET['id']
                    ?? $_POST['id']
                    ?? 0
                );

            if ($id <= 0) {

                throw new \Exception(
                    'Invalid remittance ID.'
                );
            }

            // =========================
            // GET RECEIPT
            // =========================
            $receipt =
                HistoryService
                    ::getById($id);

            if (!$receipt) {

                throw new \Exception(
                    'Receipt not found.'
                );
            }

            $user =
                Auth::user();

            $otp =
                trim(
                    (string)(
                        $_GET['otp']
                        ?? $_POST['otp']
                        ?? ''
                    )
                );

            $otpValid =
                false;

            // =========================
            // VALIDATE OTP BEFORE PRINT
            // =========================
            if (
                $user
                && !empty($user['id'])
                && $otp !== ''
            ) {

                $otpModel =
                    new ReceiptOtpModel();

                $otpValid =
                    $otpModel->verify(
                        (int) $user['id'],
                        $id,
                        $otp
                    );
            }

            if ($otpValid) {

                // =========================
                // AUDIT PRINT
                // =========================
                $audit =
                    new AuditTrailModel();

                $audit->log(
                    'Finance',
                    'Print Receipt',
                    'Receipt #' . $id . ' printed for driver '
                        . ($receipt['full_name'] ?? 'N/A')
                        . ' (Bus ' . ($receipt['bus_no'] ?? 'N/A') . ')',
                    $user['id'] ?? null,
                    $user['username'] ?? 'System',
                    $user['role'] ?? null,
                    (string) $id
                );

                if ($isJson) {

                    // =========================
                    // RETURN RECEIPT HTML FOR THE MODAL
                    // =========================
                    ob_start();

                    require __DIR__
                        . '/../views/history/receipt.php';

                    $html =
                        ob_get_clean();

                    header(
                        'Content-Type: application/json'
                    );

                    echo json_encode([

                        'success' => true,
                        'html' => $html
                    ]);

                    exit;
                }

                // =========================
                // LOAD RECEIPT VIEW (direct link)
                // =========================
                require __DIR__
                    . '/../views/history/receipt.php';

                return;
            }

            if ($isJson) {

                header(
                    'Content-Type: application/json'
                );

                echo json_encode([

                    'success' => false,
                    'message' =>
                        'Invalid or expired OTP. Please request a new one.'
                ]);

                exit;
            }

            // =========================
            // OTP ENTRY FALLBACK PAGE (direct link)
            // =========================
            $error =
                'Invalid or expired OTP. Please request a new one.';

            require __DIR__
                . '/../views/history/otp.php';

        } catch (\Exception $e) {

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                === 'POST'
            ) {

                header(
                    'Content-Type: application/json'
                );

                echo json_encode([

                    'success' => false,
                    'message' =>
                        $e->getMessage()
                ]);

                exit;
            }

            die(
                $e->getMessage()
            );
        }
    }

    /**
     * =========================
     * EXPORT PDF
     * =========================
     */
    public function exportPdf()
    {
        try {

            [$data, $rangeLabel] = $this->exportData();

            require_once __DIR__ . '/../../vendor/autoload.php';

            ob_start();
            require __DIR__ . '/../views/history/export_pdf.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $this->logHistoryExport('PDF', count($data), $rangeLabel);

            $dompdf->stream('remittance-history.pdf', ['Attachment' => true]);

        } catch (\Exception $e) {

            die(
                $e->getMessage()
            );
        }
        exit;
    }

    /**
     * =========================
     * EXPORT EXCEL
     * =========================
     */
    public function exportExcel()
    {
        try {

            [$data, $rangeLabel] = $this->exportData();

            // =========================
            // EXCEL HEADERS
            // =========================
            header(
                'Content-Type: application/vnd.ms-excel'
            );

            header(
                'Content-Disposition: attachment; filename="remittance-history.xls"'
            );

            require __DIR__
                . '/../views/history/export_excel.php';

            $this->logHistoryExport('Excel', count($data), $rangeLabel);

        } catch (\Exception $e) {

            die(
                $e->getMessage()
            );
        }
        exit;
    }

    /**
     * =========================
     * PERIOD-SCOPED EXPORT DATA
     * =========================
     *
     * @return array{0: array, 1: string} [rows inside the active period, period label]
     */
    private function exportData(): array
    {
        $resolved = DateFilterService::normalizeRequest($_GET);
        $rangeLabel = DateFilterService::summary(
            $resolved['period'],
            $resolved['start'],
            $resolved['end']
        );

        $data = array_values(array_filter(
            HistoryService::getHistory(),
            static function (array $row) use ($resolved): bool {
                $date = substr((string) ($row['created_at'] ?? ''), 0, 10);

                return $date !== ''
                    && $date >= $resolved['start']
                    && $date <= $resolved['end'];
            }
        ));

        return [$data, $rangeLabel];
    }

    /**
     * =========================
     * AUDIT HELPER — HISTORY EXPORTS
     * =========================
     */
    private function logHistoryExport(string $format, int $count, string $rangeLabel = ''): void
    {
        try {

            $user =
                $_SESSION['user']
                ?? Auth::user()
                ?? [];

            $audit = new AuditTrailModel();
            $audit->log(
                'Finance',
                'History Exported',
                'Exported remittance history (' . $format . ').',
                null,
                $user['username'] ?? 'System',
                $user['role'] ?? null,
                null,
                null,
                [
                    'scope'        => $rangeLabel !== '' ? 'filtered' : 'all',
                    'range'        => $rangeLabel,
                    'format'       => $format,
                    'record_count' => $count
                ]
            );

        } catch (\Throwable $e) {

            error_log(
                'History export audit failed: ' . $e->getMessage()
            );
        }
    }
}
?>