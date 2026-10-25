<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../services/FuelCostAnalyticsService.php';
require_once __DIR__ . '/../services/DateFilterService.php';

$__fuelAuditModelPath = __DIR__ . '/../models/AuditTrailModel.php';
if (is_file($__fuelAuditModelPath)) {
    require_once $__fuelAuditModelPath;
}

use App\Auth\Auth;
use App\Core\Database;
use App\Core\MailerService;
use Finance\Models\AuditTrailModel;
use Finance\Services\DateFilterService;
use Finance\Services\FuelCostAnalyticsService;

class FuelCostAnalyticsController
{
    private FuelCostAnalyticsService $service;

    public function __construct()
    {
        $this->service = new FuelCostAnalyticsService();
    }

    private function guard()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }
    }

    private function readFilters(): array
    {
        $resolved = DateFilterService::normalizeRequest($_GET, null, DateFilterService::TODAY);

        return [
            'bus' => trim((string) ($_GET['bus'] ?? '')),
            'driver' => trim((string) ($_GET['driver'] ?? '')),
            'from' => $resolved['start'],
            'to' => $resolved['end'],
            'search' => trim((string) ($_GET['search'] ?? '')),
            'period' => $resolved['period'],
        ];
    }

    private function signerName(): string
    {
        $user = Auth::user() ?? [];
        $name = trim((string) ($user['name'] ?? ''));
        if ($name === '') {
            try {
                $db = Database::tryGet();
                if ($db !== null) {
                    $stmt = $db->prepare("SELECT name FROM users WHERE id = :id");
                    $stmt->execute([':id' => Auth::userId()]);
                    $name = trim((string) $stmt->fetchColumn());
                }
            } catch (\Throwable $e) {}
        }
        return $name !== '' ? $name : (string) ($user['username'] ?? 'Finance Staff');
    }

    public function index()
    {
        $this->guard();

        $filters = $this->readFilters();
        $period = $filters['period'];

        $summary = $this->service->getSummary($filters);
        $perBus = $this->service->getFuelCostPerBus($filters);
        $dailyTrend = $this->service->getDailyFuelTrend($filters);
        $costComparison = $this->service->getCostComparison($filters);
        /* Receipts / transactions come from the actual refuel rows (fuel_refuels
           plus legacy fuel_logs refuel entries), not the per-trip ODO/KPL rows. */
        $recent = $this->service->getRefuelTransactions($filters, 5);
        $initialRows = $this->service->getRefuelTransactions($filters);
        $transactions = $this->service->getRefuelTransactions([]);
        $allRows = $this->service->getReceipts([]);
        $buses = $this->service->getBuses();
        $drivers = $this->service->getDrivers();

        $periodLabel = DateFilterService::label($period);
        $financePeriodConfig = DateFilterService::config($period, $filters['from'], $filters['to']);
        $signerName = $this->signerName();

        require __DIR__ . '/../views/fuel_cost_analytics/index.php';
    }

    public function exportPdf()
    {
        $this->guard();

        $filters = $this->readFilters();

        try {
            require_once __DIR__ . '/../../vendor/autoload.php';

            if (Database::tryGet() === null) {
                $this->logFuelDownload($filters, [], 'PDF', 'Failed');
                throw new \RuntimeException('Database connection unavailable.');
            }

            $rows = $this->service->getRefuelTransactions($filters);
            $rangeLabel = $this->rangeLabel($filters);
            $signerName = $this->signerName();

            ob_start();
            require __DIR__ . '/../views/fuel_cost_analytics/export_pdf.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $this->logFuelDownload($filters, $rows, 'PDF', 'Success');

            $dompdf->stream('fuel-cost-analytics.pdf', ['Attachment' => true]);

        } catch (\Exception $e) {
            $this->logFuelDownload($filters, [], 'PDF', 'Failed');
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    public function exportExcel()
    {
        $this->guard();

        $filters = $this->readFilters();

        try {
            if (Database::tryGet() === null) {
                $this->logFuelDownload($filters, [], 'Excel', 'Failed');
                throw new \RuntimeException('Database connection unavailable.');
            }

            $rows = $this->service->getRefuelTransactions($filters);
            $rangeLabel = $this->rangeLabel($filters);

            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="fuel-cost-analytics.xls"');
            header('Cache-Control: max-age=0');

            require __DIR__ . '/../views/fuel_cost_analytics/export_excel.php';

            $this->logFuelDownload($filters, $rows, 'Excel', 'Success');

        } catch (\Exception $e) {
            $this->logFuelDownload($filters, [], 'Excel', 'Failed');
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    private function logFuelDownload(array $filters, array $rows, string $format, string $status): void
    {
        $hasFilters = $filters['bus'] !== ''
            || $filters['driver'] !== ''
            || $filters['from'] !== ''
            || $filters['to'] !== ''
            || $filters['search'] !== '';

        $scope = $hasFilters ? 'filtered' : 'all';

        $newValues = [
            'scope'       => $scope,
            'date_from'   => $filters['from'] !== '' ? $filters['from'] : null,
            'date_to'     => $filters['to'] !== '' ? $filters['to'] : null,
            'bus'         => $filters['bus'] !== '' ? $filters['bus'] : null,
            'driver'      => $filters['driver'] !== '' ? $filters['driver'] : null,
            'search'      => $filters['search'] !== '' ? $filters['search'] : null,
            'period'      => $filters['period'] ?? null,
            'format'      => $format,
            'record_count'=> count($rows),
        ];

        $rangeLabel = $this->rangeLabel($filters);
        $descScope = $scope === 'all'
            ? 'Downloaded all available fuel logs'
            : 'Downloaded fuel logs for ' . $rangeLabel;

        $description = $descScope . ' (' . $format . ').';

        if ($status === 'Failed') {
            $description = 'Failed to generate fuel log export (' . $format . ').';
        }

        try {
            if (!class_exists('Finance\Models\AuditTrailModel')) {
                error_log('Fuel download audit skipped: AuditTrailModel not loaded.');
                return;
            }
            AuditTrailModel::record(
                'Finance',
                $status === 'Failed' ? 'Fuel Logs Download' : 'Fuel Logs Downloaded',
                $description,
                null,
                null,
                $newValues,
                $status
            );
        } catch (\Throwable $e) {
            error_log('Fuel download audit failed: ' . $e->getMessage());
        }
    }

    public function requestOtp()
    {
        $this->guard();
        header('Content-Type: application/json');
        echo json_encode($this->buildOtpResponse());
        exit;
    }

    public function verifyOtp()
    {
        $this->guard();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $otp = trim((string) ($input['otp'] ?? ''));
        $result = $this->verifyOtpResponse($otp);

        if (!empty($result['success'])) {
            $fuelLogId = isset($input['fuel_log_id'])
                ? (int) $input['fuel_log_id']
                : null;

            try {
                if (class_exists('Finance\Models\AuditTrailModel')) {
                    AuditTrailModel::record(
                        'Finance',
                        'Fuel Receipt Printed',
                        $fuelLogId
                            ? 'Printed fuel receipt #' . $fuelLogId . '.'
                            : 'Printed fuel receipt(s).',
                        $fuelLogId ? (string) $fuelLogId : null,
                        null,
                        ['format' => 'print'],
                        'Success'
                    );
                } else {
                    error_log('Fuel receipt print audit skipped: AuditTrailModel not loaded.');
                }
            } catch (\Throwable $e) {
                error_log('Fuel receipt print audit failed: ' . $e->getMessage());
            }
        }

        echo json_encode($result);
        exit;
    }

    private function buildOtpResponse(): array
    {
        try {
            $uid = Auth::userId();
            $db = Database::tryGet();
            if ($db === null) {
                return ['success' => false, 'message' => 'Could not send OTP. Please try again.'];
            }
            $stmt = $db->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->execute([':id' => $uid]);
            $email = (string) $stmt->fetchColumn();
            if ($email === '') {
                return ['success' => false, 'message' => 'No email address on file for this account.'];
            }

            $otp = random_int(100000, 999999);
            $_SESSION['fuel_print_otp'] = [
                'hash' => password_hash((string) $otp, PASSWORD_DEFAULT),
                'expires' => time() + 300,
                'attempts' => 0,
            ];

            $mailer = new MailerService();
            $sent = $mailer->sendOtp($email, (string) $otp);
            if (!$sent) {
                return ['success' => false, 'message' => 'Failed to send OTP email. Please try again.'];
            }

            return ['success' => true, 'message' => 'OTP sent to ' . $email . '. It expires in 5 minutes. Check spam/junk if not received.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not send OTP. Please try again.'];
        }
    }

    private function verifyOtpResponse(string $otp): array
    {
        $s = $_SESSION['fuel_print_otp'] ?? null;

        if (!$s) {
            return ['success' => false, 'message' => 'No OTP requested. Click Send Code first.'];
        }
        if (time() > (int) ($s['expires'] ?? 0)) {
            unset($_SESSION['fuel_print_otp']);
            return ['success' => false, 'message' => 'OTP expired. Request a new one.'];
        }
        if (($s['attempts'] ?? 0) >= 5) {
            unset($_SESSION['fuel_print_otp']);
            return ['success' => false, 'message' => 'Too many failed attempts. Request a new OTP.'];
        }
        if ($otp === '' || !preg_match('/^\d{6}$/', $otp) || !password_verify($otp, $s['hash'])) {
            $_SESSION['fuel_print_otp']['attempts'] = (int) ($s['attempts'] ?? 0) + 1;
            return ['success' => false, 'message' => 'Invalid OTP.'];
        }

        unset($_SESSION['fuel_print_otp']);
        $_SESSION['fuel_print_authorized'] = time();
        return ['success' => true, 'message' => 'Authorized.'];
    }

    private function rangeLabel(array $filters): string
    {
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        if ($from && $to) {
            return date('M d, Y', strtotime($from)) . ' â€“ ' . date('M d, Y', strtotime($to));
        }
        if ($from) {
            return 'From ' . date('M d, Y', strtotime($from));
        }
        if ($to) {
            return 'Until ' . date('M d, Y', strtotime($to));
        }
        return 'All dates';
    }
}
