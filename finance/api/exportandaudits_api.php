<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once __DIR__ . '/../middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../models/ExportAuditModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../models/FinancialReportModel.php';
require_once __DIR__ . '/../services/DateFilterService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use App\Core\Database;
use Finance\Models\ExportAuditModel;
use Finance\Models\AuditTrailModel;
use Finance\Models\FinancialReportModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

ini_set('memory_limit', '256M');
set_time_limit(120);

header('Content-Type: application/json');

$model = new ExportAuditModel();
$model->initTables();

$auditModel = new AuditTrailModel();
$auditModel->initTables();

$currentUserId = null; $currentUsername = 'System'; $currentUserRole = 'Unknown';
try {
    $sid = session_id();
    if ($sid) {
        $st = Database::get()->prepare("SELECT user_id FROM sessions WHERE id = ?");
        $st->execute([$sid]);
        $row = $st->fetch();
        if ($row && $row['user_id']) {
            $us = Database::get()->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $us->execute([$row['user_id']]);
            $u = $us->fetch();
            if ($u) {
                $currentUserId = (int)$u['id'];
                $currentUsername = $u['username'] ?? 'User';
                $currentUserRole = $u['role'] ?? 'Staff';
            }
        }
    }
} catch (\Throwable $e) {}

$action = $_GET['api'] ?? $_POST['api'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

/* Shared Finance period model: canonical period/from/to with legacy mapping. */
$financePeriod = \Finance\Services\DateFilterService::normalizeRequest($_GET);
$periodFrom = $financePeriod['start'];
$periodTo = $financePeriod['end'];
$periodSummary = \Finance\Services\DateFilterService::summary(
    $financePeriod['period'],
    $periodFrom,
    $periodTo
);

try {

    /*
    |--------------------------------------------------------------------------
    | LIST AUDITS
    |--------------------------------------------------------------------------
    */
    if ($action === 'list_audits') {
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);

        $audits = $model->listAudits($status, null, $limit, $periodFrom, $periodTo);
        echo json_encode([
            'success' => true,
            'audits' => $audits,
            'period' => $financePeriod['period'],
            'summary' => $periodSummary,
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET AUDIT DETAILS
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_audit') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid audit ID.');

        $audit = $model->getAuditById($id);
        if (!$audit) throw new Exception('Audit not found.');

        if ($audit['financial_snapshot']) {
            $parsed = json_decode($audit['financial_snapshot'], true);
            $audit['financial_data'] = $parsed ?: $audit['financial_snapshot'];
        }

        echo json_encode(['success' => true, 'audit' => $audit]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE NEW AUDIT
    |--------------------------------------------------------------------------
    */
    if ($action === 'generate_audit') {
        $auditType = $_POST['audit_type'] ?? $_GET['audit_type'] ?? 'Full Financial Audit';
        $performedBy = $_POST['performed_by'] ?? $_GET['performed_by'] ?? 'System';

        $snapshot = $model->getFinancialSnapshot();
        $snapshotJson = json_encode($snapshot);

        $auditId = $model->insertAudit($auditType, $performedBy, $snapshotJson);

        $auditModel->log('Finance', 'Generate', "Generated financial audit #AUD-" . str_pad((string)$auditId, 3, '0', STR_PAD_LEFT), $currentUserId, $currentUsername, $currentUserRole, 'AUD-' . str_pad((string)$auditId, 3, '0', STR_PAD_LEFT), null, $snapshot);

        echo json_encode([
            'success' => true,
            'audit_id' => $auditId,
            'message' => 'Audit generated successfully.',
            'snapshot' => $snapshot,
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT TRAIL — LIST
    |--------------------------------------------------------------------------
    */
    if ($action === 'audit_trail_list') {
        $req = $_POST ?: $_GET;
        $search = $req['search'] ?? null;
        $module = $req['module'] ?? null;
        $actionFilter = $req['act'] ?? null;
        $username = $req['username'] ?? null;
        $userRole = $req['user_role'] ?? null;
        $status = $req['status'] ?? null;
        $dateFrom = $req['date_from'] ?? null;
        $dateTo = $req['date_to'] ?? null;
        $sort = $req['sort'] ?? 'DESC';
        $page = max(1, (int)($req['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req['per_page'] ?? 25)));

        $hasRangeInput = !empty($req['period']) || !empty($req['from']) || !empty($req['to'])
            || !empty($req['date_from']) || !empty($req['date_to']);
        if ($hasRangeInput) {
            $trailRequest = $req;
            if (empty($trailRequest['from']) && !empty($dateFrom)) {
                $trailRequest['from'] = $dateFrom;
            }
            if (empty($trailRequest['to']) && !empty($dateTo)) {
                $trailRequest['to'] = $dateTo;
            }
            $trailResolved = \Finance\Services\DateFilterService::normalizeRequest($trailRequest);
            $dateFrom = $trailResolved['start'] !== '' ? $trailResolved['start'] : null;
            $dateTo = $trailResolved['end'] !== '' ? $trailResolved['end'] : null;
        }

        $result = $auditModel->getTrails($search, $module, $actionFilter, $username, $userRole, $status, $dateFrom, $dateTo, $sort, $page, $perPage);
        echo json_encode(['success' => true] + $result);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT TRAIL — DETAIL
    |--------------------------------------------------------------------------
    */
    if ($action === 'audit_trail_detail') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid audit trail ID.');
        $entry = $auditModel->getById($id);
        if (!$entry) throw new Exception('Audit trail entry not found.');
        echo json_encode(['success' => true, 'entry' => $entry]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT TRAIL — FILTER OPTIONS
    |--------------------------------------------------------------------------
    */
    if ($action === 'audit_trail_filters') {
        $db = Database::get();
        $actions = $db->query("SELECT DISTINCT action FROM audit_trail WHERE action IS NOT NULL AND action != '' ORDER BY action")->fetchAll(\PDO::FETCH_COLUMN);
        $roles = ['finance_manager', 'finance_officer'];
        echo json_encode(['success' => true, 'actions' => $actions, 'roles' => $roles]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT TRAIL — LOG (internal helper, also callable from other modules)
    |--------------------------------------------------------------------------
    */
    if ($action === 'audit_trail_log') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required.');

        $module = $_POST['module'] ?? '';
        $act = $_POST['log_action'] ?? '';
        $desc = $_POST['description'] ?? null;
        $recordId = $_POST['record_id'] ?? null;
        $prev = isset($_POST['previous_values']) ? json_decode($_POST['previous_values'], true) : null;
        $new = isset($_POST['new_values']) ? json_decode($_POST['new_values'], true) : null;
        $status = $_POST['status'] ?? 'Success';

        if (empty($module) || empty($act)) throw new Exception('Module and action are required.');

        $id = $auditModel->log($module, $act, $desc, $currentUserId, $currentUsername, $currentUserRole, $recordId, $prev, $new, $status);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET CURRENT USER INFO
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_current_user') {
        $su = \App\Auth\Auth::user();
        echo json_encode([
            'success' => true,
            'user_id' => $su['id'] ?? null,
            'username' => $su['username'] ?? $su['full_name'] ?? 'System',
            'role' => $su['role'] ?? 'Unknown'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT LOG — LIST
    |--------------------------------------------------------------------------
    */
    if ($action === 'export_log_list') {
        $logFrom = $_GET['date_from'] ?? null;
        $logTo = $_GET['date_to'] ?? null;
        $logHasRange = !empty($_GET['period']) || !empty($_GET['from']) || !empty($_GET['to'])
            || !empty($_GET['date_from']) || !empty($_GET['date_to']);
        if ($logHasRange) {
            $logRequest = $_GET;
            if (empty($logRequest['from']) && !empty($logFrom)) {
                $logRequest['from'] = $logFrom;
            }
            if (empty($logRequest['to']) && !empty($logTo)) {
                $logRequest['to'] = $logTo;
            }
            $logResolved = \Finance\Services\DateFilterService::normalizeRequest($logRequest);
            $logFrom = $logResolved['start'] !== '' ? $logResolved['start'] : null;
            $logTo = $logResolved['end'] !== '' ? $logResolved['end'] : null;
        }

        $result = $model->getExportLogs(
            $_GET['search'] ?? null,
            $_GET['format'] ?? null,
            $_GET['report_type'] ?? null,
            $logFrom,
            $logTo,
            $_GET['sort'] ?? 'DESC',
            max(1, (int)($_GET['page'] ?? 1)),
            min(100, max(10, (int)($_GET['per_page'] ?? 25)))
        );
        echo json_encode(['success' => true] + $result);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE ACTIVITY FEED (Audits section)
    |--------------------------------------------------------------------------
    | Verifiable financial/administrative actions from saved records:
    | remittance submissions and verifications, discrepancy workflow events,
    | report exports, financial audit snapshots, and recorded audit-trail
    | actions. Actors and timestamps come from the records; demo/simulated
    | rows are flagged rather than presented as genuine users' work.
    */
    if ($action === 'finance_audit_feed') {
        $feed = $model->getFinanceAuditFeed(
            $periodFrom,
            $periodTo,
            (string)($_GET['type'] ?? ''),
            (string)($_GET['search'] ?? ''),
            max(1, (int)($_GET['page'] ?? 1)),
            min(100, max(10, (int)($_GET['per_page'] ?? 25)))
        );
        echo json_encode(['success' => true] + $feed + [
            'period' => $financePeriod['period'],
            'summary' => $periodSummary,
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET EXPORT SUMMARY (live data for cards)
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_export_summary') {
        echo json_encode([
            'success' => true,
            'period' => $financePeriod['period'],
            'summary' => $periodSummary,
            'data' => $model->getExportSummary($periodFrom, $periodTo),
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET GENERATED REPORTS
    |--------------------------------------------------------------------------
    | Returns every report in the Financial Reports library (financial_reports
    | table) merged with legacy report files under generated_reports/. Files
    | that are the on-disk copy of a library report are folded into that
    | report's card so nothing is listed twice.
    */
    if ($action === 'get_generated_reports') {
        $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports';

        // Library category => folder used by generated_reports/<folder>, plus
        // display labels shared by both sources.
        $categoryFolders = [
            'full' => 'comprehensive',
            'revenue' => 'revenue_analysis',
            'expenses' => 'operating_expenses',
            'fleet' => 'fleet_performance',
            'routes' => 'route_performance',
            'drivers' => 'driver_performance',
            'summary' => 'financial_summary',
            'risk' => 'risk_assessment',
            'import' => 'imports',
            'imports' => 'imports',
            'comprehensive' => 'comprehensive',
            'revenue_analysis' => 'revenue_analysis',
            'operating_expenses' => 'operating_expenses',
            'bus_maintenance' => 'bus_maintenance',
            'fleet_performance' => 'fleet_performance',
            'route_performance' => 'route_performance',
            'driver_performance' => 'driver_performance',
            'financial_summary' => 'financial_summary',
            'risk_assessment' => 'risk_assessment',
            'general' => 'general',
        ];
        $categoryLabels = [
            'comprehensive' => 'Comprehensive',
            'revenue_analysis' => 'Revenue Analysis',
            'operating_expenses' => 'Operating Expenses',
            'bus_maintenance' => 'Bus Maintenance',
            'fleet_performance' => 'Fleet Performance',
            'route_performance' => 'Route Performance',
            'driver_performance' => 'Driver Performance',
            'financial_summary' => 'Financial Summary',
            'risk_assessment' => 'Risk Assessment',
            'imports' => 'Import',
            'general' => 'General',
        ];

        $reports = [];

        // 1) Library reports (all of them — archived ones are flagged).
        $libraryFolderStamps = [];
        try {
            $dbRows = Database::get()->query(
                "SELECT id, title, report_category, generated_by, generated_by_role, generated_at, archived, LENGTH(report_data) AS size
                 FROM financial_reports
                 ORDER BY generated_at DESC"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $dbRows = [];
        }

        foreach ($dbRows as $row) {
            $dbCategory = strtolower(trim((string) ($row['report_category'] ?? '')));
            $folder = $categoryFolders[$dbCategory] ?? ($dbCategory !== '' ? $dbCategory : 'general');
            $generatedAt = (string) ($row['generated_at'] ?? '');
            $stamp = $generatedAt !== '' ? (int) strtotime($generatedAt) : 0;
            if ($stamp > 0) {
                $libraryFolderStamps[$folder][] = $stamp;
            }

            $reports[] = [
                'id' => (int) $row['id'],
                'source' => 'db',
                'title' => (string) ($row['title'] ?? 'Report'),
                'category' => $categoryLabels[$folder] ?? ucfirst($folder),
                'folder' => '',
                'file' => '',
                'generated_at' => $generatedAt,
                'generated_by' => $row['generated_by'] ?? null,
                'generated_by_role' => $row['generated_by_role'] ?? null,
                'archived' => (int) ($row['archived'] ?? 0) === 1,
                'size' => (int) ($row['size'] ?? 0),
            ];
        }

        // 2) Legacy files without a matching library row.
        $isCopyOfLibraryReport = static function (string $folder, int $mtime) use ($libraryFolderStamps): bool {
            foreach ($libraryFolderStamps[$folder] ?? [] as $stamp) {
                if (abs($stamp - $mtime) <= 15) {
                    return true;
                }
            }
            return false;
        };

        $collectFile = static function (string $folder, string $file, string $filePath) use (&$reports, $categoryLabels, $isCopyOfLibraryReport): void {
            $mtime = (int) filemtime($filePath);
            if ($isCopyOfLibraryReport($folder, $mtime)) {
                return;
            }
            $title = str_replace('_', ' ', pathinfo($file, PATHINFO_FILENAME));
            $reports[] = [
                'id' => null,
                'source' => 'file',
                'title' => $title,
                'category' => $categoryLabels[$folder] ?? ($folder !== '' ? ucfirst($folder) : 'General'),
                'folder' => $folder,
                'file' => $file,
                'generated_at' => date('Y-m-d H:i:s', $mtime),
                'generated_by' => null,
                'generated_by_role' => null,
                'archived' => false,
                'size' => (int) filesize($filePath),
            ];
        };

        if (is_dir($baseDir)) {
            foreach (scandir($baseDir) as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $baseDir . '/' . $item;
                if (is_dir($itemPath)) {
                    foreach (scandir($itemPath) as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $filePath = $itemPath . '/' . $file;
                        if (!is_file($filePath)) continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['md', 'txt', 'html'], true)) {
                            $collectFile($item, $file, $filePath);
                        }
                    }
                } elseif (is_file($itemPath)) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($ext, ['md', 'txt', 'html'], true)) {
                        $collectFile('', $item, $itemPath);
                    }
                }
            }
        }

        usort($reports, static function (array $a, array $b): int {
            return strcmp((string) $b['generated_at'], (string) $a['generated_at']);
        });

        echo json_encode([
            'success' => true,
            'period' => $financePeriod['period'],
            'summary' => $periodSummary,
            'reports' => array_slice(array_values($reports), 0, 500),
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET GENERATED REPORT CONTENT
    |--------------------------------------------------------------------------
    | Supports library reports by id (financial_reports) and legacy files by
    | folder/file.
    */
    if ($action === 'get_generated_report') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $report = (new FinancialReportModel())->getReportById($id);
            if (!$report) throw new Exception('Report not found.');

            echo json_encode([
                'success' => true,
                'report' => [
                    'id' => (int) $report['id'],
                    'source' => 'db',
                    'title' => (string) ($report['title'] ?? 'Report'),
                    'content' => (string) ($report['report_data'] ?? ''),
                    'category' => (string) ($report['report_category'] ?? ''),
                    'generated_at' => (string) ($report['generated_at'] ?? ''),
                    'generated_by' => $report['generated_by'] ?? null,
                    'archived' => (int) ($report['archived'] ?? 0) === 1,
                ],
            ]);
            exit;
        }

        $folder = trim((string) ($_GET['folder'] ?? ''), "/\\ \t\n\r\0\x0B");
        $file = basename(trim((string) ($_GET['file'] ?? '')));
        if ($file === '') throw new Exception('Invalid file.');
        if ($folder !== '' && !preg_match('/^[A-Za-z0-9 _\-]+$/', $folder)) {
            throw new Exception('Invalid folder.');
        }
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['md', 'txt', 'html'], true)) {
            throw new Exception('Unsupported report file.');
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/';
        if ($folder !== '') $filePath .= $folder . '/';
        $filePath .= $file;

        if (!is_file($filePath)) throw new Exception('Report file not found.');
        $content = file_get_contents($filePath);
        $title = pathinfo($file, PATHINFO_FILENAME);

        echo json_encode([
            'success' => true,
            'report' => [
                'id' => null,
                'source' => 'file',
                'title' => str_replace('_', ' ', $title),
                'content' => $content,
                'category' => '',
                'generated_at' => date('Y-m-d H:i:s', (int) filemtime($filePath)),
                'generated_by' => null,
                'archived' => false,
                'folder' => $folder,
                'file' => $file,
            ]
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL
    |--------------------------------------------------------------------------
    */
    if ($action === 'export_excel') {
        $type = $_GET['type'] ?? 'revenue';
        $from = $periodFrom;
        $to = $periodTo;
        $filename = 'financial_' . $type . '_' . date('Y_m_d_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($type));

        switch ($type) {
            case 'revenue':
                $data = $model->getRevenueData($from, $to);

                $sheet->setCellValue('A1', 'Revenue Data Export');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValue('A2', 'Generated: ' . date('Y-m-d H:i:s'));

                $headers = ['ID', 'Bus No', 'Driver', 'Passengers', 'Expected', 'Collected', 'Discrepancy', 'Status', 'Date'];
                $col = 'A';
                foreach ($headers as $h) {
                    $sheet->setCellValue($col . '4', $h);
                    $sheet->getStyle($col . '4')->getFont()->setBold(true);
                    $sheet->getColumnDimension($col)->setWidth(18);
                    $col++;
                }

                $row = 5;
                foreach ($data as $d) {
                    $sheet->setCellValue('A' . $row, $d['id'] ?? '');
                    $sheet->setCellValue('B' . $row, $d['bus_no'] ?? '');
                    $sheet->setCellValue('C' . $row, $d['driver_name'] ?? $d['driver_id'] ?? '');
                    $sheet->setCellValue('D' . $row, $d['total_passengers'] ?? 0);
                    $sheet->setCellValue('E' . $row, $d['expected_cash'] ?? 0);
                    $sheet->setCellValue('F' . $row, $d['actual_cash'] ?? 0);
                    $sheet->setCellValue('G' . $row, $d['discrepancy_amount'] ?? 0);
                    $sheet->setCellValue('H' . $row, $d['status'] ?? '');
                    $sheet->setCellValue('I' . $row, $d['created_at'] ?? '');
                    $row++;
                }
                break;

            case 'expenses':
                $data = $model->getExpenseData($from, $to);

                $sheet->setCellValue('A1', 'Expenses Data Export');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValue('A2', 'Generated: ' . date('Y-m-d H:i:s'));

                $headers = ['ID', 'Category', 'Amount', 'Bus ID', 'Trip ID', 'Description', 'Date'];
                $col = 'A';
                foreach ($headers as $h) {
                    $sheet->setCellValue($col . '4', $h);
                    $sheet->getStyle($col . '4')->getFont()->setBold(true);
                    $sheet->getColumnDimension($col)->setWidth(18);
                    $col++;
                }

                $row = 5;
                foreach ($data as $d) {
                    $sheet->setCellValue('A' . $row, $d['id'] ?? '');
                    $sheet->setCellValue('B' . $row, $d['category'] ?? '');
                    $sheet->setCellValue('C' . $row, $d['amount'] ?? 0);
                    $sheet->setCellValue('D' . $row, $d['bus_id'] ?? '');
                    $sheet->setCellValue('E' . $row, $d['trip_id'] ?? '');
                    $sheet->setCellValue('F' . $row, $d['description'] ?? '');
                    $sheet->setCellValue('G' . $row, $d['created_at'] ?? '');
                    $row++;
                }
                break;

            case 'remittances':
                $data = $model->getRemittanceData($from, $to);

                $sheet->setCellValue('A1', 'Remittance Data Export');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValue('A2', 'Generated: ' . date('Y-m-d H:i:s'));

                $headers = ['ID', 'Bus No', 'Driver ID', 'Passengers', 'Expected', 'Actual', 'Discrepancy', 'Status', 'Confirmed By', 'Date'];
                $col = 'A';
                foreach ($headers as $h) {
                    $sheet->setCellValue($col . '4', $h);
                    $sheet->getStyle($col . '4')->getFont()->setBold(true);
                    $sheet->getColumnDimension($col)->setWidth(20);
                    $col++;
                }

                $row = 5;
                foreach ($data as $d) {
                    $sheet->setCellValue('A' . $row, $d['id'] ?? '');
                    $sheet->setCellValue('B' . $row, $d['bus_no'] ?? '');
                    $sheet->setCellValue('C' . $row, $d['driver_id'] ?? '');
                    $sheet->setCellValue('D' . $row, $d['total_passengers'] ?? 0);
                    $sheet->setCellValue('E' . $row, $d['expected_cash'] ?? 0);
                    $sheet->setCellValue('F' . $row, $d['actual_cash'] ?? 0);
                    $sheet->setCellValue('G' . $row, $d['discrepancy_amount'] ?? 0);
                    $sheet->setCellValue('H' . $row, $d['status'] ?? '');
                    $sheet->setCellValue('I' . $row, $d['confirmed_by'] ?? '');
                    $sheet->setCellValue('J' . $row, $d['created_at'] ?? '');
                    $row++;
                }
                break;

            default:
                throw new Exception('Unknown export type: ' . $type);
        }

        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        /* Recorded only after the workbook streamed successfully, so the
           Report Export Log shows genuine export operations. */
        try {
            $model->logExport(
                ucfirst($type) . ' Data Export',
                ucfirst($type) . ' Export',
                $from . ' to ' . $to,
                'Excel',
                $currentUsername,
                $currentUserRole,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $filename
            );
            $auditModel->log('Finance', 'Export', 'Exported ' . $type . ' data to Excel', $currentUserId, $currentUsername, $currentUserRole, null, null, ['type' => $type, 'from' => $from, 'to' => $to]);
        } catch (\Throwable $e) {
            // Export history must never break a successful download.
        }

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT PDF
    |--------------------------------------------------------------------------
    */
    if ($action === 'export_pdf') {
        $from = $periodFrom;
        $to = $periodTo;

        $snapshot = $model->getFinancialSnapshot();

        require_once __DIR__ . '/../../app/Views/pdf/template.php';

        $bodyContent = '
            <div class="section">
                <div class="section-title">Financial Overview</div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-title">Total Revenue</div>
                        <div class="stats-value">₱' . number_format($snapshot['total_revenue'] ?? 0, 2) . '</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-title">Total Expenses</div>
                        <div class="stats-value">₱' . number_format($snapshot['total_expenses'] ?? 0, 2) . '</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-title">Net Profit</div>
                        <div class="stats-value ' . (($snapshot['net_profit'] ?? 0) >= 0 ? 'green' : 'red') . '">₱' . number_format($snapshot['net_profit'] ?? 0, 2) . '</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-title">Total Collection</div>
                        <div class="stats-value">₱' . number_format($snapshot['total_collection'] ?? 0, 2) . '</div>
                    </div>
                </div>
            </div>
            <div class="section">
                <div class="section-title">Operational Summary</div>
                <table>
                    <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Trips</td><td>' . number_format($snapshot['trip_count'] ?? 0) . '</td></tr>
                    <tr><td>Total Passengers</td><td>' . number_format($snapshot['passenger_count'] ?? 0) . '</td></tr>
                    <tr><td>Snapshot Date</td><td>' . ($snapshot['snapshot_date'] ?? '') . '</td></tr>
                </table>
            </div>';

        if (!empty($snapshot['expense_breakdown'])) {
            $bodyContent .= '<div class="section">
                <div class="section-title">Expense Breakdown</div>
                <table>
                    <tr><th>Category</th><th>Total</th><th>Percentage</th></tr>';
            foreach ($snapshot['expense_breakdown'] as $cat) {
                $bodyContent .= '<tr><td>' . htmlspecialchars($cat['category'] ?? '') . '</td>
                          <td>₱' . number_format($cat['total'] ?? 0, 2) . '</td>
                          <td>' . ($cat['percentage'] ?? 0) . '%</td></tr>';
            }
            $bodyContent .= '</table></div>';
        }

        $html = renderPdfHtml(
            title: 'Financial Summary Report',
            bodyContent: $bodyContent,
            orgSub: 'Financial Summary',
            meta: ['Generated: ' . date('F j, Y H:i:s')]
        );

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'financial_summary_' . date('Y_m_d_His') . '.pdf';

        if (ob_get_length()) ob_end_clean();

        $dompdf->stream($filename, ['Attachment' => true]);
        /* Recorded only after the PDF streamed successfully. */
        try {
            $model->logExport(
                'Financial Summary Report',
                'Financial Report',
                ($from ?? '') . ' to ' . ($to ?? ''),
                'PDF',
                $currentUsername,
                $currentUserRole,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $filename
            );
            $auditModel->log('Finance', 'Export', 'Exported financial summary to PDF', $currentUserId, $currentUsername, $currentUserRole, null, null, ['from' => $from ?? null, 'to' => $to ?? null]);
        } catch (\Throwable $e) {
            // Export history must never break a successful download.
        }

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE AUDIT STATUS
    |--------------------------------------------------------------------------
    */
    if ($action === 'update_audit_status') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $status = $_POST['status'] ?? $_GET['status'] ?? '';
        if ($id <= 0 || !in_array($status, ['Pending', 'Ongoing', 'Completed'])) {
            throw new Exception('Invalid parameters.');
        }
        $model->updateAuditStatus($id, $status);
        $auditModel->log('Finance', 'Update', "Updated audit #AUD-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " status to $status", $currentUserId, $currentUsername, $currentUserRole, 'AUD-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT), null, ['status' => $status]);
        echo json_encode(['success' => true, 'message' => 'Status updated.']);
        exit;
    }

    throw new Exception('Unknown action: ' . $action);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
