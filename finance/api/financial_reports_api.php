<?php

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ . '/_reporting_bootstrap.php';
require_once __DIR__ . '/../middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../models/FinancialReportModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../models/ExportAuditModel.php';
require_once __DIR__ . '/../services/FinancialReportAIService.php';
require_once __DIR__ . '/../services/DateFilterService.php';
require_once __DIR__ . '/../models/FinancialPerformanceReportModel.php';
require_once __DIR__ . '/../services/FinancialPerformancePdfService.php';
require_once __DIR__ . '/../services/FinancialPerformanceService.php';
require_once __DIR__ . '/../services/TripHistoryCsvImportService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

ini_set('display_errors', '0');
error_reporting(0);

// Discard any PHP error output from the require chain
ob_clean();

use App\Core\Database;
use App\Core\FinancialCosts;
use Finance\Models\FinancialReportModel;
use Finance\Models\AuditTrailModel;
use Finance\Models\ExportAuditModel;
use Finance\Services\FinancialReportAIService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');

function sendJson(array $data): void
{
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

$model = new FinancialReportModel();
$model->initTables();

$auditModel = new AuditTrailModel();
$auditModel->initTables();
$exportLogModel = new ExportAuditModel();
$exportLogModel->initTables();

$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
$currentUsername = $_SESSION['user']['username'] ?? 'System';
$currentUserRole = $_SESSION['user']['role'] ?? 'Unknown';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* Shared Finance period model: canonical period/from/to with legacy mapping.
   Actions that accept a reporting window use these resolved bounds. */
$financePeriod = \Finance\Services\DateFilterService::normalizeRequest($_GET);
$periodFrom = $financePeriod['start'];
$periodTo = $financePeriod['end'];
$periodSummary = \Finance\Services\DateFilterService::summary(
    $financePeriod['period'],
    $periodFrom,
    $periodTo
);
/* Financial Reports exposes a custom date range only: when the request
   carries no date filter, actions keep their all-data behavior. */
$hasDateFilter = !empty($_GET['period']) || !empty($_GET['from']) || !empty($_GET['to'])
    || !empty($_GET['fromDate']) || !empty($_GET['toDate'])
    || !empty($_GET['start']) || !empty($_GET['end']) || !empty($_GET['date']);

try {

    /*
    |--------------------------------------------------------------------------
    | HELPER: Parse rows from CSV or Excel
    |--------------------------------------------------------------------------
    */
    function parseImportFile(string $tmpPath, string $origName): array
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (in_array($ext, ['doc', 'docx'])) {
            return []; // handled as reference in confirm
        }

        if ($ext === 'csv') {
            $handle = fopen($tmpPath, 'r');
            if (!$handle) throw new Exception('Cannot read CSV file.');
            $rawHeaders = fgetcsv($handle);
            if (!$rawHeaders) { fclose($handle); throw new Exception('Empty CSV.'); }
            // Strip BOM and trim headers
            $headers = [];
            foreach ($rawHeaders as $h) {
                $headers[] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            }
            if (empty($headers)) {
                fclose($handle);
                throw new Exception('CSV has no valid headers.');
            }
            $rows = [];
            while (($line = fgetcsv($handle)) !== false) {
                // Skip completely empty rows (null or all-empty single element)
                if ($line === null || (count($line) === 1 && ($line[0] === null || trim($line[0]) === ''))) {
                    continue;
                }
                $row = [];
                $hasValue = false;
                foreach ($headers as $i => $h) {
                    $val = isset($line[$i]) ? trim($line[$i]) : '';
                    $row[$h] = $val;
                    if ($val !== '') $hasValue = true;
                }
                if ($hasValue) {
                    $rows[] = $row;
                }
            }
            fclose($handle);
            return $rows;
        }

        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($tmpPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            if (empty($data) || count($data) < 1) throw new Exception('Empty Excel file.');
            $headers = $data[0];
            $rows = [];
            for ($i = 1; $i < count($data); $i++) {
                $row = [];
                $hasValue = false;
                foreach ($headers as $col => $h) {
                    $h = trim((string)$h);
                    $val = isset($data[$i][$col]) ? trim((string)$data[$i][$col]) : '';
                    $row[$h] = $val;
                    if ($val !== '') $hasValue = true;
                }
                if ($hasValue) {
                    $rows[] = $row;
                }
            }
            return $rows;
        }

        throw new Exception('Unsupported file format. Use CSV, XLSX, or XLS.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: Normalise row to expense columns
    |--------------------------------------------------------------------------
    */
    function normaliseExpenseRow(array $raw): array
    {
        $row = [];
        $lc = [];
        foreach ($raw as $k => $v) { $lc[trim(strtolower($k))] = $v; }

        $row['category'] = $lc['category'] ?? $lc['type'] ?? 'Other';
        $row['amount'] = (float)($lc['amount'] ?? $lc['fare'] ?? $lc['cost'] ?? 0);

        $dateRaw = $lc['date'] ?? $lc['expense_date'] ?? $lc['trip_date'] ?? '';
        if ($dateRaw && is_numeric($dateRaw)) {
            $row['expense_date'] = ExcelDate::excelToDateTimeObject((float)$dateRaw)->format('Y-m-d');
        } elseif ($dateRaw) {
            $row['expense_date'] = date('Y-m-d', strtotime((string)$dateRaw));
        } else {
            $row['expense_date'] = date('Y-m-d');
        }

        $row['bus_id'] = !empty($lc['bus_id'] ?? $lc['bus'] ?? $lc['bus_no'] ?? '') ? (int)($lc['bus_id'] ?? $lc['bus'] ?? $lc['bus_no']) : null;
        $row['driver_id'] = !empty($lc['driver_id'] ?? $lc['driver'] ?? '') ? (int)($lc['driver_id'] ?? $lc['driver']) : null;
        $row['description'] = $lc['description'] ?? $lc['notes'] ?? $lc['remarks'] ?? null;
        return $row;
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT — PREVIEW
    |--------------------------------------------------------------------------
    */
    if ($action === 'import_preview') {
        if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            sendJson(['success' => false, 'message' => 'No file uploaded.']);
        }
        $uploadError = $_FILES['file']['error'];
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE directive.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
            ];
            $msg = $errorMessages[$uploadError] ?? 'Unknown upload error (code ' . $uploadError . ').';
            sendJson(['success' => false, 'message' => $msg]);
        }
        $tmp = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'];
        if (!is_file($tmp)) {
            sendJson(['success' => false, 'message' => 'Invalid file.']);
        }

        $maxSize = 50 * 1024 * 1024;
        if (filesize($tmp) > $maxSize) {
            sendJson(['success' => false, 'message' => 'File exceeds the 50 MB size limit.']);
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'doc', 'docx'])) {
            sendJson(['success' => false, 'message' => 'Unsupported file format. Use CSV, XLSX, XLS, DOC, or DOCX.']);
        }

        // Word docs — preview as reference file only
        if (in_array($ext, ['doc', 'docx'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/financial_references';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $dest = $uploadDir . '/' . time() . '_' . basename($name);
            move_uploaded_file($tmp, $dest);
            sendJson(['success' => true, 'type' => 'reference', 'file' => $dest, 'message' => 'Document stored as reference.']);
        }

        $allRows = parseImportFile($tmp, $name);
        $totalRows = count($allRows);
        $preview = array_slice($allRows, 0, 10);
        $headers = $totalRows > 0 ? array_keys($allRows[0]) : [];

        sendJson(['success' => true, 'preview' => $preview, 'total_rows' => $totalRows, 'headers' => $headers]);
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT — CONFIRM
    |--------------------------------------------------------------------------
    */
    if ($action === 'import_confirm') {
        try {
            if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('No file uploaded.');
            }
            $uploadError = $_FILES['file']['error'];
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize limit.',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE directive.',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
                ];
                throw new Exception($errorMessages[$uploadError] ?? 'Unknown upload error (code ' . $uploadError . ').');
            }
            $tmp = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            if (!is_file($tmp)) {
                throw new Exception('Invalid file.');
            }

            $maxSize = 50 * 1024 * 1024;
            if (filesize($tmp) > $maxSize) {
                throw new Exception('File exceeds the 50 MB size limit.');
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['csv', 'xlsx', 'xls', 'doc', 'docx'])) {
                throw new Exception('Unsupported file format. Use CSV, XLSX, XLS, DOC, or DOCX.');
            }

            // Save copy to generated_reports/imports/
            $importDir = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/imports';
            if (!is_dir($importDir)) mkdir($importDir, 0777, true);
            $storedFile = $importDir . '/' . date('Y_m_d_His') . '_' . basename($name);
            copy($tmp, $storedFile);

            // Word docs — reference only
            if (in_array($ext, ['doc', 'docx'])) {
                $auditModel->log('Finance', 'Import', "Saved reference document {$name}", $currentUserId, $currentUsername, $currentUserRole, null, null, ['file' => $name]);
                sendJson(['success' => true, 'imported' => 0, 'file' => '/generated_reports/imports/' . basename($storedFile), 'message' => 'Document saved as reference.']);
            }

            // CSV / Excel — parse and insert
            $allRows = parseImportFile($tmp, $name);
            if (empty($allRows)) {
                throw new Exception('No data rows found in file. Check that the file has headers and at least one data row.');
            }
            $normalised = array_map('normaliseExpenseRow', $allRows);

            // AI auto-classify rows with missing/ambiguous category
            $aiService = new FinancialReportAIService();
            foreach ($normalised as &$r) {
                $needsAi = empty($r['category']) || $r['category'] === 'Other' || trim($r['category']) === '';
                if ($needsAi && !empty($r['description'])) {
                    try {
                        $suggested = $aiService->suggestCategory($r['description'], $r['amount']);
                        if (!empty($suggested) && $suggested !== 'Other') {
                            $r['category'] = $suggested;
                        }
                    } catch (\Throwable $e) {
                        // AI failure — keep default category
                    }
                }
            }
            unset($r);

            Database::reconnect();
            $inserted = $model->bulkInsertExpenses($normalised);

            // Generate summary of imported data
            $totalAmount = 0;
            $catCounts = [];
            foreach ($normalised as $r) {
                $totalAmount += $r['amount'];
                $cat = $r['category'] ?? 'Other';
                $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
            }
            $catSummary = '';
            arsort($catCounts);
            foreach (array_slice($catCounts, 0, 5) as $cat => $cnt) {
                $catSummary .= "- {$cat}: {$cnt} entries\n";
            }

            $summary = "Import Summary\n";
            $summary .= "==============\n";
            $summary .= "File: {$name}\n";
            $summary .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $summary .= "Records Imported: {$inserted}\n";
            $summary .= "Total Amount: ₱" . number_format($totalAmount, 2) . "\n";
            $summary .= "\nTop Categories:\n{$catSummary}\n";

            $summaryFile = $importDir . '/' . date('Y_m_d_His') . '_summary.txt';
            file_put_contents($summaryFile, $summary);

            $auditModel->log('Finance', 'Import', "Imported {$inserted} records from {$name}", $currentUserId, $currentUsername, $currentUserRole, null, null, ['imported' => $inserted, 'file' => $name]);

            sendJson([
                'success' => true,
                'imported' => $inserted,
                'rows_parsed' => count($allRows),
                'rows_normalised' => count($normalised),
                'file' => '/generated_reports/imports/' . basename($storedFile),
                'summary' => $summary,
                'summary_file' => '/generated_reports/imports/' . basename($summaryFile),
            ]);
        } catch (\Throwable $e) {
            $auditModel->log('Finance', 'Import', "Import failed: {$e->getMessage()}", $currentUserId, $currentUsername, $currentUserRole, null, null, ['file' => $_FILES['file']['name'] ?? 'unknown', 'error' => $e->getMessage()], 'Failed');
            sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: Build and save the report generated by a trip-history import
    |--------------------------------------------------------------------------
    */
    function buildTripHistoryImportReport(\PDO $db, array $result, string $username, string $role): array
    {
        $file = (string) ($result['file'] ?? 'CSV');
        $from = $result['date_from'] ?? null;
        $to = $result['date_to'] ?? null;

        $where = '1=1';
        $params = [];
        if (is_string($from) && $from !== '') {
            $where .= ' AND COALESCE(trip_date, DATE(date_booked)) >= ?';
            $params[] = $from;
        }
        if (is_string($to) && $to !== '') {
            $where .= ' AND COALESCE(trip_date, DATE(date_booked)) <= ?';
            $params[] = $to;
        }

        $summary = ['passengers' => 0, 'trips' => 0, 'collections' => 0.0, 'avg_fare' => 0.0];
        $paymentRows = [];
        $destinationRows = [];

        try {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) AS passengers,
                    COUNT(DISTINCT NULLIF(TRIM(trip_id), '')) AS trips,
                    COALESCE(SUM(fare), 0) AS collections,
                    COALESCE(AVG(fare), 0) AS avg_fare
                FROM passenger_trip_history
                WHERE $where
            ");
            $stmt->execute($params);
            $summary = array_merge($summary, $stmt->fetch(\PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable $e) {
            // Degrade to the import counts when the history table cannot be read.
        }

        try {
            $stmt = $db->prepare("
                SELECT payment_method, COUNT(*) AS entries, COALESCE(SUM(fare), 0) AS amount
                FROM passenger_trip_history
                WHERE $where
                GROUP BY payment_method
                ORDER BY amount DESC
            ");
            $stmt->execute($params);
            $paymentRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $paymentRows = [];
        }

        try {
            $stmt = $db->prepare("
                SELECT destination, COUNT(*) AS entries, COALESCE(SUM(fare), 0) AS amount
                FROM passenger_trip_history
                WHERE $where AND destination IS NOT NULL AND TRIM(destination) <> ''
                GROUP BY destination
                ORDER BY entries DESC
                LIMIT 5
            ");
            $stmt->execute($params);
            $destinationRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $destinationRows = [];
        }

        $periodLabel = ($from && $to)
            ? ($from === $to ? $from : $from . ' to ' . $to)
            : 'All recorded dates';

        $title = 'Trip History Import - ' . $file . ' - ' . date('M j, Y H:i');

        $lines = [];
        $lines[] = 'TRIP HISTORY IMPORT REPORT';
        $lines[] = '=========================';
        $lines[] = 'Generated: ' . date('Y-m-d H:i:s') . ' (Asia/Manila)';
        $lines[] = 'Imported file: ' . $file;
        $lines[] = 'Imported by: ' . $username . ' (' . $role . ')';
        $lines[] = 'Period covered by imported records: ' . $periodLabel;
        $lines[] = '';
        $lines[] = 'Import summary';
        $lines[] = '--------------';
        $lines[] = 'Rows imported: ' . (int) ($result['imported'] ?? 0);
        $lines[] = 'Duplicate rows skipped: ' . (int) ($result['skipped'] ?? 0);
        $lines[] = 'Invalid rows: ' . (int) ($result['failed'] ?? 0);
        $lines[] = '';
        $lines[] = 'Period financials (trip history records)';
        $lines[] = '--------------------------------------';
        $lines[] = 'Fare collections: PHP ' . number_format((float) $summary['collections'], 2);
        $lines[] = 'Passenger entries: ' . (int) $summary['passengers'];
        $lines[] = 'Distinct trips: ' . (int) $summary['trips'];
        $lines[] = 'Average fare: PHP ' . number_format((float) $summary['avg_fare'], 2);
        $lines[] = '';
        $lines[] = 'Collections by payment method';
        $lines[] = '----------------------------';
        if ($paymentRows !== []) {
            foreach ($paymentRows as $row) {
                $lines[] = '- ' . ($row['payment_method'] !== null && $row['payment_method'] !== '' ? $row['payment_method'] : 'Unspecified')
                    . ': PHP ' . number_format((float) $row['amount'], 2) . ' across ' . (int) $row['entries'] . ' entries';
            }
        } else {
            $lines[] = 'No payment-method records in the imported period.';
        }
        $lines[] = '';
        $lines[] = 'Top destinations';
        $lines[] = '----------------';
        if ($destinationRows !== []) {
            foreach ($destinationRows as $row) {
                $lines[] = '- ' . $row['destination'] . ': ' . (int) $row['entries'] . ' entries, PHP ' . number_format((float) $row['amount'], 2);
            }
        } else {
            $lines[] = 'No destinations recorded in the imported period.';
        }
        $lines[] = '';
        $lines[] = 'Note: this report is generated automatically after the CSV import and reflects';
        $lines[] = 'the trip-history records stored for the period above.';

        $content = implode("\n", $lines);
        $model = new FinancialReportModel();
        $id = $model->saveReport($title, $content, 'import', $username, $role);

        // Keep the same footprint as AI-generated reports: a filesystem copy
        // under generated_reports/imports in addition to the library row.
        $reportFile = null;
        $reportDir = ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2)) . '/generated_reports/imports';
        if (!is_dir($reportDir)) {
            @mkdir($reportDir, 0777, true);
        }
        if (is_dir($reportDir)) {
            $filename = 'import_' . date('Y_m_d_His') . '.md';
            if (@file_put_contents($reportDir . '/' . $filename, $content) !== false) {
                $reportFile = '/generated_reports/imports/' . $filename;
            }
        }

        return ['id' => $id, 'title' => $title, 'content' => $content, 'file' => $reportFile];
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP HISTORY IMPORT — PREVIEW
    |--------------------------------------------------------------------------
    | Uses the same CSV structure, column mapping, date/time handling and
    | validation rules as the admin Trip History CSV Import through the
    | shared TripHistoryCsvImportService.
    */
    if ($action === 'trip_history_preview') {
        try {
            [$tmp, $name] = \Finance\Services\TripHistoryCsvImportService::validateUpload($_FILES['file'] ?? []);
            $preview = \Finance\Services\TripHistoryCsvImportService::preview(Database::get(), $tmp, $name);

            // The preview table shows the first rows; the counts stay complete.
            $preview['rows'] = array_slice($preview['rows'], 0, 200);
            foreach ($preview['rows'] as &$row) {
                unset($row['data']);
            }
            unset($row);
            $preview['truncated'] = $preview['counts']['total'] > count($preview['rows']);

            sendJson($preview);
        } catch (\Throwable $e) {
            sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP HISTORY IMPORT — CONFIRM
    |--------------------------------------------------------------------------
    */
    if ($action === 'trip_history_import') {
        try {
            [$tmp, $name] = \Finance\Services\TripHistoryCsvImportService::validateUpload($_FILES['file'] ?? []);
            $result = \Finance\Services\TripHistoryCsvImportService::import(Database::get(), $tmp, $name);

            // A successful import produces a report so the batch shows up in
            // the saved Reports library straight away.
            if (!empty($result['imported'])) {
                try {
                    $report = buildTripHistoryImportReport(Database::get(), $result, $currentUsername, $currentUserRole);
                    $result['report_id'] = (int) $report['id'];
                    $result['report_title'] = (string) $report['title'];
                    $result['report_file'] = $report['file'] ?? null;
                } catch (\Throwable $reportError) {
                    $result['report_error'] = $reportError->getMessage();
                    error_log('Trip history import report generation failed: ' . $reportError->getMessage());
                }
            }

            $auditModel->log(
                'Finance',
                'Import',
                "Imported {$result['imported']} trip-history record(s) from {$name}"
                    . ($result['skipped'] > 0 ? " ({$result['skipped']} duplicate row(s) skipped)" : '')
                    . ($result['failed'] > 0 ? " ({$result['failed']} invalid row(s))" : '')
                    . (!empty($result['report_id']) ? " — report #{$result['report_id']}" : ''),
                $currentUserId,
                $currentUsername,
                $currentUserRole,
                null,
                null,
                ['imported' => $result['imported'], 'skipped' => $result['skipped'], 'failed' => $result['failed'], 'file' => $name, 'report_id' => $result['report_id'] ?? null]
            );

            sendJson($result);
        } catch (\Throwable $e) {
            $auditModel->log('Finance', 'Import', "Import failed: {$e->getMessage()}", $currentUserId, $currentUsername, $currentUserRole, null, null, ['file' => $_FILES['file']['name'] ?? 'unknown', 'error' => $e->getMessage()], 'Failed');
            sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADD EXPENSE (MANUAL ENTRY)
    |--------------------------------------------------------------------------
    */
    if ($action === 'add_expense') {
        $category = $_POST['category'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
        $busId = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
        $driverId = !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
        $description = $_POST['description'] ?? '';

        if (empty($category) || $amount <= 0) {
            throw new Exception('Category and amount are required.');
        }

        $id = $model->insertExpense($category, $amount, $expenseDate, $busId, $driverId, $description);
        $auditModel->log('Finance', 'Create', "Added expense: {$category} ₱{$amount}", $currentUserId, $currentUsername, $currentUserRole, (string)$id);
        sendJson(['success' => true, 'id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET FILTERED EXPENSES
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_filtered_expenses') {
        $from = $hasDateFilter ? $periodFrom : null;
        $to = $hasDateFilter ? $periodTo : null;
        $category = $_GET['category'] ?? null;

        $conditions = [];
        $params = [];

        if ($from) { $conditions[] = "fe.expense_date >= :from"; $params[':from'] = $from; }
        if ($to) { $conditions[] = "fe.expense_date <= :to"; $params[':to'] = $to; }
        if ($category) { $conditions[] = "fe.category = :cat"; $params[':cat'] = $category; }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $db = Database::get();

        // Main query
        $sql = "SELECT fe.* FROM financial_expenses fe $where ORDER BY fe.expense_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Total
        $totalSql = "SELECT COALESCE(SUM(amount),0) as total FROM financial_expenses fe $where";
        $tStmt = $db->prepare($totalSql);
        $tStmt->execute($params);
        $total = (float)$tStmt->fetchColumn();

        sendJson([
            'success' => true,
            'expenses' => $expenses,
            'total' => $total,
            'count' => count($expenses),
            'filter_category' => $category,
            'filter_from' => $from,
            'filter_to' => $to,
            'period' => $financePeriod['period'],
            'summary' => $periodSummary,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE AI REPORT
    |--------------------------------------------------------------------------
    */
    if ($action === 'generate_report') {
        $reportType = $_GET['report_type'] ?? 'full';
        $userDescription = $_GET['description'] ?? '';

        $validReportTypes = ['full', 'revenue', 'expenses', 'fleet', 'routes', 'drivers', 'summary', 'risk'];
        if (!in_array($reportType, $validReportTypes, true)) {
            throw new Exception('Unsupported report type.');
        }

        $from = $hasDateFilter ? $periodFrom : null;
        $to = $hasDateFilter ? $periodTo : null;

        $db = Database::get();

        // Build date clauses
        $tripDateWhere = "1=1";
        $expenseDateWhere = "1=1";
        $remitDateWhere = "1=1";
        $busTripDateWhere = "1=1";
        $finExpenseDateWhere = "1=1";
        $dispatchDateWhere = "1=1";
        if ($from) {
            $fromEsc = $db->quote($from);
            $tripDateWhere .= " AND DATE(boarding_time) >= $fromEsc";
            $expenseDateWhere .= " AND DATE(created_at) >= $fromEsc";
            $remitDateWhere .= " AND DATE(created_at) >= $fromEsc";
            $busTripDateWhere .= " AND trip_date >= $fromEsc";
            $finExpenseDateWhere .= " AND expense_date >= $fromEsc";
            $dispatchDateWhere .= " AND trip_date >= $fromEsc";
        }
        if ($to) {
            $toEsc = $db->quote($to);
            $tripDateWhere .= " AND DATE(boarding_time) <= $toEsc";
            $expenseDateWhere .= " AND DATE(created_at) <= $toEsc";
            $remitDateWhere .= " AND DATE(created_at) <= $toEsc";
            $busTripDateWhere .= " AND trip_date <= $toEsc";
            $finExpenseDateWhere .= " AND expense_date <= $toEsc";
            $dispatchDateWhere .= " AND trip_date <= $toEsc";
        }

        // ── Trip history date clause ──
        $tripHistoryDateWhere = "1=1";
        if ($from) { $fromEsc = $db->quote($from); $tripHistoryDateWhere .= " AND COALESCE(trip_date, DATE(date_booked)) >= $fromEsc"; }
        if ($to) { $toEsc = $db->quote($to); $tripHistoryDateWhere .= " AND COALESCE(trip_date, DATE(date_booked)) <= $toEsc"; }

        // ── Revenue from all sources (including archived history) ──
        $revenueStmt = $db->query("
            SELECT
                COALESCE((SELECT SUM(actual_cash) FROM remittances WHERE $remitDateWhere), 0) AS remittance_revenue,
                COALESCE((SELECT SUM(fare) FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid' AND $tripDateWhere), 0) AS trip_fare_revenue,
                COALESCE((SELECT SUM(fare) FROM passenger_trip_history WHERE $tripHistoryDateWhere), 0) AS history_fare_revenue,
                COALESCE((SELECT SUM(total_revenue) FROM bus_trips WHERE $busTripDateWhere), 0) AS bus_trip_revenue,
                COALESCE((SELECT SUM(amount) FROM expenses WHERE $expenseDateWhere), 0) AS expense_total,
                COALESCE((SELECT SUM(amount) FROM financial_expenses WHERE $finExpenseDateWhere), 0) AS financial_expense_total
        ");
        $finRow = $revenueStmt->fetch(\PDO::FETCH_ASSOC);
        $totalRevenue = (float)($finRow['remittance_revenue'] ?? 0) + (float)($finRow['trip_fare_revenue'] ?? 0) + (float)($finRow['history_fare_revenue'] ?? 0) + (float)($finRow['bus_trip_revenue'] ?? 0);
        $totalExpenses = (float)($finRow['expense_total'] ?? 0) + (float)($finRow['financial_expense_total'] ?? 0);
        $netProfit = $totalRevenue - $totalExpenses;

        // ── Expense breakdown (combined from both tables) ──
        $expBreakStmt = $db->query("
            SELECT category, SUM(amount) AS total, COUNT(*) AS count
            FROM (
                SELECT CONVERT(category USING utf8mb4) AS category, amount FROM expenses WHERE $expenseDateWhere
                UNION ALL
                SELECT CONVERT(category USING utf8mb4) AS category, amount FROM financial_expenses WHERE $finExpenseDateWhere
            ) combined
            GROUP BY category ORDER BY total DESC
        ");
        $expenseBreakdown = $expBreakStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Fuel cost ──
        $fuelStmt = $db->query("
            SELECT COALESCE(SUM(amount),0) FROM expenses WHERE LOWER(category) = 'fuel' AND $expenseDateWhere
        ");
        $fuelCost = (float)$fuelStmt->fetchColumn();

        // ── Passenger & trip data (combined live + history) ──
        $passStmt = $db->query("
            SELECT
                COUNT(*) AS total_passengers,
                COUNT(DISTINCT trip_no) AS total_trips,
                COALESCE(SUM(fare),0) AS total_fare,
                COALESCE(AVG(fare),0) AS avg_fare,
                COALESCE(SUM(cash_amount),0) AS total_cash,
                COALESCE(SUM(gcash_amount),0) AS total_gcash,
                COALESCE(SUM(discrepancy_amount),0) AS total_discrepancy
            FROM (
                SELECT trip_no AS trip_no, fare, cash_amount, gcash_amount, discrepancy_amount
                FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid' AND $tripDateWhere
                UNION ALL
                SELECT trip_id AS trip_no, fare, NULL AS cash_amount, NULL AS gcash_amount, NULL AS discrepancy_amount
                FROM passenger_trip_history WHERE $tripHistoryDateWhere
            ) combined
        ");
        $tripData = $passStmt->fetch(\PDO::FETCH_ASSOC);

        // ── Payment method breakdown (combined) ──
        $payStmt = $db->query("
            SELECT payment_method, COUNT(*) AS total, COALESCE(SUM(fare),0) AS revenue
            FROM (
                SELECT payment_method, fare FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid' AND $tripDateWhere
                UNION ALL
                SELECT payment_method, fare FROM passenger_trip_history WHERE $tripHistoryDateWhere
            ) combined
            GROUP BY payment_method
        ");
        $paymentMethods = $payStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Route performance (combined) ──
        $routeStmt = $db->query("
            SELECT
                route_name, COUNT(*) AS trips, COALESCE(SUM(fare),0) AS revenue, COUNT(*) AS passengers
            FROM (
                SELECT CONCAT(boarding_point, ' -> ', destination) AS route_name, fare, passenger_id
                FROM passenger_trips WHERE LOWER(TRIM(payment_status)) = 'paid' AND $tripDateWhere
                UNION ALL
                SELECT CONCAT(boarding_point, ' -> ', destination) AS route_name, fare, NULL AS passenger_id
                FROM passenger_trip_history WHERE $tripHistoryDateWhere
            ) combined
            GROUP BY route_name ORDER BY revenue DESC LIMIT 5
        ");
        $routes = $routeStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Bus performance ──
        $busStmt = $db->query("
            SELECT
                b.bus_id, b.plate_number,
                COALESCE(bt.trip_count,0) AS trips,
                COALESCE(bt.total_rev,0) AS revenue,
                COALESCE(e.total_cost,0) AS total_cost,
                COALESCE(fe.fin_cost,0) AS fin_cost
            FROM buses b
            LEFT JOIN (SELECT bus_id, COUNT(*) AS trip_count, COALESCE(SUM(total_revenue),0) AS total_rev FROM bus_trips WHERE $busTripDateWhere GROUP BY bus_id) bt ON bt.bus_id = b.bus_id
            LEFT JOIN (SELECT bus_id, COALESCE(SUM(amount),0) AS total_cost FROM expenses WHERE bus_id IS NOT NULL AND $expenseDateWhere GROUP BY bus_id) e ON e.bus_id = b.bus_id
            LEFT JOIN (SELECT bus_id, COALESCE(SUM(amount),0) AS fin_cost FROM financial_expenses WHERE bus_id IS NOT NULL AND $finExpenseDateWhere GROUP BY bus_id) fe ON fe.bus_id = b.bus_id
            ORDER BY b.bus_id LIMIT 10
        ");
        $buses = $busStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Driver performance (combined live + history) ──
        $driverStmt = $db->query("
            SELECT driver, SUM(trips) AS trips, SUM(revenue) AS revenue, SUM(discrepancy) AS discrepancy FROM (
                SELECT COALESCE(d.full_name, 'Unknown') AS driver, COUNT(pt.id) AS trips, COALESCE(SUM(pt.fare),0) AS revenue, COALESCE(SUM(pt.discrepancy_amount),0) AS discrepancy
                FROM drivers d
                LEFT JOIN passenger_trips pt ON pt.driver_id = d.driver_id AND LOWER(TRIM(pt.payment_status)) = 'paid' AND $tripDateWhere
                GROUP BY d.driver_id
                UNION ALL
                SELECT drivers_full_name AS driver, COUNT(*) AS trips, COALESCE(SUM(fare),0) AS revenue, 0 AS discrepancy
                FROM passenger_trip_history WHERE $tripHistoryDateWhere AND drivers_full_name IS NOT NULL
                GROUP BY drivers_full_name
            ) combined GROUP BY driver ORDER BY revenue DESC LIMIT 10
        ");
        $drivers = $driverStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Summary stats ──
        $driverCountStmt = $db->query("SELECT COUNT(*) AS total, SUM(IF(status='Active',1,0)) AS active FROM drivers");
        $driverStats = $driverCountStmt->fetch(\PDO::FETCH_ASSOC);
        $busCountStmt = $db->query("SELECT COUNT(*) AS total FROM buses");
        $busCount = (int)$busCountStmt->fetchColumn();
        $dispatchStmt = $db->query("SELECT COUNT(*) AS total FROM dispatches WHERE status = 'completed' AND $dispatchDateWhere");
        $dispatchCount = (int)$dispatchStmt->fetchColumn();

        // ── Remittance summary ──
        $remitStmt = $db->query("
            SELECT status, COUNT(*) AS total, COALESCE(SUM(expected_cash),0) AS expected, COALESCE(SUM(actual_cash),0) AS actual, COALESCE(SUM(discrepancy_amount),0) AS discrepancy
            FROM remittances WHERE $remitDateWhere GROUP BY status
        ");
        $remittanceSummary = $remitStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Bus trip summary ──
        $btStmt = $db->query("
            SELECT COUNT(*) AS total_trips, COALESCE(SUM(total_passengers),0) AS total_passengers, COALESCE(SUM(total_revenue),0) AS total_revenue, COALESCE(AVG(fuel_cost),0) AS avg_fuel_cost
            FROM bus_trips WHERE $busTripDateWhere
        ");
        $busTripSummary = $btStmt->fetch(\PDO::FETCH_ASSOC);

        // ── Passenger Trip History (for trend analysis) ──
        $histSummaryStmt = $db->query("SELECT COUNT(DISTINCT trip_id) AS total_trips, COUNT(*) AS total_passengers, SUM(fare) AS total_revenue, AVG(fare) AS average_fare, MIN(fare) AS lowest_fare, MAX(fare) AS highest_fare FROM passenger_trip_history WHERE $tripHistoryDateWhere");
        $historicalSummary = $histSummaryStmt->fetch(\PDO::FETCH_ASSOC);

        $histMonthlyStmt = $db->query("SELECT DATE_FORMAT(COALESCE(trip_date, DATE(date_booked)),'%Y-%m') AS month, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue, AVG(fare) AS average_fare FROM passenger_trip_history WHERE $tripHistoryDateWhere GROUP BY month ORDER BY month DESC LIMIT 12");
        $historicalMonthly = $histMonthlyStmt->fetchAll(\PDO::FETCH_ASSOC);

        $histPassTypesStmt = $db->query("SELECT passenger_type, COUNT(*) AS total, SUM(fare) AS revenue, AVG(fare) AS average_fare FROM passenger_trip_history WHERE $tripHistoryDateWhere GROUP BY passenger_type");
        $historicalPassTypes = $histPassTypesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $histDriversStmt = $db->query("SELECT drivers_full_name AS driver, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue, AVG(fare) AS average_fare FROM passenger_trip_history WHERE $tripHistoryDateWhere GROUP BY drivers_full_name ORDER BY revenue DESC LIMIT 10");
        $historicalDrivers = $histDriversStmt->fetchAll(\PDO::FETCH_ASSOC);

        $histPayMethodsStmt = $db->query("SELECT payment_method, COUNT(*) AS total, SUM(fare) AS revenue FROM passenger_trip_history WHERE $tripHistoryDateWhere GROUP BY payment_method");
        $historicalPayMethods = $histPayMethodsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Trip expenses (toll, other costs per trip) ──
        $tripExpStmt = $db->query("SELECT COALESCE(SUM(toll_fees),0) AS toll_fees, COALESCE(SUM(other_cost),0) AS other_cost FROM trip_expenses");
        $tripExpenses = $tripExpStmt->fetch(\PDO::FETCH_ASSOC);

        // ── Fuel prices ──
        $fuelPriceStmt = $db->query("SELECT fuel_type, price_per_liter FROM fuel_prices ORDER BY updated_at DESC LIMIT 5");
        $fuelPrices = $fuelPriceStmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Fuel logs (real refuel records, same source as Net Income) ──
        $fuelLogDateWhere = "1=1";
        if ($from) { $fromEsc = $db->quote($from); $fuelLogDateWhere .= " AND DATE(created_at) >= $fromEsc"; }
        if ($to) { $toEsc = $db->quote($to); $fuelLogDateWhere .= " AND DATE(created_at) <= $toEsc"; }
        $fuelLogStmt = $db->query("SELECT COALESCE(SUM(liters),0) AS liters, COALESCE(SUM(cost),0) AS cost, COUNT(*) AS logs FROM fuel_logs WHERE $fuelLogDateWhere");
        $fuelLogsData = $fuelLogStmt->fetch(\PDO::FETCH_ASSOC);

        // ── Active drivers in period (drivers with recorded trips) ──
        $activeDriversStmt = $db->query("
            SELECT COUNT(DISTINCT d.id)
            FROM drivers d
            WHERE (
                EXISTS (SELECT 1 FROM passenger_trips pt WHERE pt.driver_id = d.driver_id AND $tripDateWhere)
                OR EXISTS (SELECT 1 FROM passenger_trip_history pth WHERE REPLACE(LOWER(TRIM(pth.drivers_full_name)), ' ', '') = REPLACE(LOWER(TRIM(d.full_name)), ' ', '') AND $tripHistoryDateWhere)
            )
        ");
        $activeDrivers = (int)$activeDriversStmt->fetchColumn();

        // ── Per-driver fixed costs (Finance → Financial Cost Settings, scaled to period) ──
        $monthFactor = 1.0;
        if ($from && $to) {
            $days = (strtotime($to) - strtotime($from)) / 86400 + 1;
            $monthFactor = max(0.0, (float)$days / 30.4375);
        }
        $fixedTripCount = (int)($tripData['total_trips'] ?? 0);
        $avgGross = $activeDrivers > 0 ? $totalRevenue / $activeDrivers : 0.0;
        $avgTrips = $activeDrivers > 0 ? $fixedTripCount / $activeDrivers : 0.0;
        $fixedPerDriver = FinancialCosts::fixedCosts($monthFactor, [
            'gross' => $avgGross,
            'trips' => $avgTrips,
        ]);
        $monthlyFixedCostPerDriver = (float)$fixedPerDriver['monthly_total'];
        $fixedAmortization = round($fixedPerDriver['amortization'] * $activeDrivers, 2);
        $fixedInsurance    = round($fixedPerDriver['insurance'] * $activeDrivers, 2);
        $fixedGps          = round($fixedPerDriver['gps'] * $activeDrivers, 2);
        $fixedOptr         = round($fixedPerDriver['optr'] * $activeDrivers, 2);
        $driverFixedCosts  = round($fixedPerDriver['total'] * $activeDrivers, 2);
        $fixedCosts = [
            'amortization' => $fixedAmortization,
            'insurance'    => $fixedInsurance,
            'gps'          => $fixedGps,
            'optr'         => $fixedOptr,
            'total'        => $driverFixedCosts,
            'per_driver'   => $monthlyFixedCostPerDriver,
            'month_factor' => $monthFactor,
        ];

        $aggregated = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'fuel_cost' => $fuelCost,
            'passenger_count' => (int)($tripData['total_passengers'] ?? 0),
            'trip_count' => (int)($tripData['total_trips'] ?? 0),
            'avg_fare' => (float)($tripData['avg_fare'] ?? 0),
            'total_cash' => (float)($tripData['total_cash'] ?? 0),
            'total_gcash' => (float)($tripData['total_gcash'] ?? 0),
            'total_discrepancy' => (float)($tripData['total_discrepancy'] ?? 0),
            'payment_methods' => $paymentMethods,
            'routes' => $routes,
            'buses' => $buses,
            'drivers' => $drivers,
            'expense_breakdown' => $expenseBreakdown,
            'driver_stats' => $driverStats,
            'total_buses' => $busCount,
            'completed_dispatches' => $dispatchCount,
            'remittance_summary' => $remittanceSummary,
            'bus_trip_summary' => $busTripSummary,
            'historical_summary' => $historicalSummary,
            'historical_monthly' => $historicalMonthly,
            'historical_passenger_types' => $historicalPassTypes,
            'historical_drivers' => $historicalDrivers,
            'historical_payment_methods' => $historicalPayMethods,
            'trip_expenses' => $tripExpenses,
            'fuel_prices' => $fuelPrices,
            'fuel_logs' => $fuelLogsData,
            'active_drivers' => $activeDrivers,
            'fixed_costs' => $fixedCosts,
        ];

        $financialSnapshotToken = (string) ($_GET['financial_snapshot'] ?? '');
        $savedSnapshot = $_SESSION['financial_performance_snapshots'][$financialSnapshotToken] ?? null;
        if ($financialSnapshotToken !== '' && (!$savedSnapshot || $savedSnapshot['expires'] < time() || ($savedSnapshot['owner'] ?? '') !== (string) \App\Auth\Auth::userId())) {
            throw new Exception('Refresh the financial analytics before generating this report; the snapshot expired.');
        }
        $financialSnapshot = $savedSnapshot ? $savedSnapshot['data'] : (new \Finance\Services\FinancialPerformanceService(Database::get()))->build($_GET);
        $totalRevenue = $financialSnapshot['totals']['revenue'];
        $totalExpenses = $financialSnapshot['totals']['expenses'];
        $netProfit = $financialSnapshot['totals']['profit'];
        $from = $financialSnapshot['filters']['from'];
        $to = $financialSnapshot['filters']['to'];
        $overview = ['total_revenue' => $totalRevenue, 'total_expenses' => $totalExpenses, 'net_profit' => $netProfit];
        $breakdown = array_map(static fn($r) => ['category' => $r['name'], 'total' => $r['expenses'], 'count' => count(array_filter($financialSnapshot['records'], static fn($v) => $v['kind'] === 'expense' && $v['category'] === $r['name']))], $financialSnapshot['breakdowns']['categories']);
        $aggregated = [
            'total_revenue' => $totalRevenue, 'total_expenses' => $totalExpenses, 'net_profit' => $netProfit,
            'fuel_cost' => $financialSnapshot['totals']['fuel'], 'passenger_count' => $financialSnapshot['totals']['passengers'],
            'trip_count' => $financialSnapshot['totals']['trips'], 'expense_breakdown' => $breakdown,
            'drivers' => $financialSnapshot['breakdowns']['drivers'], 'buses' => $financialSnapshot['breakdowns']['buses'],
            'payment_methods' => array_map(static fn($r) => ['payment_method' => $r['name'], 'total' => $r['count'], 'revenue' => $r['revenue']], $financialSnapshot['breakdowns']['payments']),
            'financial_summary_buckets' => $financialSnapshot['series'],
            'total_buses' => count(array_filter($financialSnapshot['breakdowns']['buses'], static fn($r) => $r['key'] !== 'unassigned')),
            'driver_stats' => ['total' => count($financialSnapshot['breakdowns']['drivers']), 'active' => count($financialSnapshot['breakdowns']['drivers'])],
            'avg_fare' => $financialSnapshot['totals']['passengers'] > 0 ? $financialSnapshot['totals']['passenger_revenue'] / $financialSnapshot['totals']['passengers'] : 0,
            'verified_findings' => $financialSnapshot['insights'], 'data_basis' => $financialSnapshot['notes'],
        ];

        $aiService = new FinancialReportAIService();
        Database::reconnect();
        $reportContent = $aiService->generateAnalysis($overview, $breakdown, $reportType, $userDescription, $aggregated, $from, $to);

        Database::reconnect();

        // Save to filesystem
        $categoryMap = [
            'full' => 'comprehensive',
            'revenue' => 'revenue_analysis',
            'expenses' => 'operating_expenses',
            'fleet' => 'fleet_performance',
            'routes' => 'route_performance',
            'drivers' => 'driver_performance',
            'summary' => 'financial_summary',
            'risk' => 'risk_assessment',
        ];
        $category = $categoryMap[$reportType] ?? 'general';
        $reportDir = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/' . $category;
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0777, true);
        }

        $filename = $category . '_' . date('Y_m_d_His') . '.md';
        $filepath = $reportDir . '/' . $filename;
        file_put_contents($filepath, $reportContent);

        // Also save to DB
        $title = ucfirst(str_replace('_', ' ', $category)) . ' Report - ' . date('F j, Y');
        $reportId = $model->saveReport($title, $reportContent, $reportType, $currentUsername, $currentUserRole);
        $financialSnapshot['narrative'] = $reportContent;
        \Finance\Models\FinancialPerformanceReportModel::initialize(Database::get());
        \Finance\Models\FinancialPerformanceReportModel::save(Database::get(), $reportId, $financialSnapshot);
        if ($financialSnapshotToken !== '' && isset($_SESSION['financial_performance_snapshots'][$financialSnapshotToken])) {
            $_SESSION['financial_performance_snapshots'][$financialSnapshotToken]['data'] = $financialSnapshot;
            $_SESSION['financial_performance_snapshots'][$financialSnapshotToken]['report_id'] = $reportId;
            $_SESSION['financial_performance_snapshots'][$financialSnapshotToken]['expires'] = time() + 1800;
        }
        $auditModel->log('Finance', 'Generate', "Generated {$category} report", $currentUserId, $currentUsername, $currentUserRole, (string)$reportId);

        sendJson([
            'success' => true,
            'report_id' => $reportId,
            'title' => $title,
            'category' => $category,
            'file' => '/generated_reports/' . $category . '/' . $filename,
            'report' => $reportContent,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS LIST (with optional filter, search, archived)
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_reports') {
        $from = $hasDateFilter ? $periodFrom : null;
        $to = $hasDateFilter ? $periodTo : null;
        $category = $_GET['category'] ?? null;
        $archived = ($_GET['archived'] ?? '') === '1';
        $search = $_GET['search'] ?? '';

        $allReports = $model->getReports($archived, $search);
        $filtered = [];

        foreach ($allReports as $r) {
            $match = true;
            $savedScope = \Finance\Models\FinancialPerformanceReportModel::get(Database::get(), (int) $r['id']);
            $recordFrom = $savedScope['filters']['from'] ?? substr((string) $r['generated_at'], 0, 10);
            $recordTo = $savedScope['filters']['to'] ?? substr((string) $r['generated_at'], 0, 10);

            if ($from && $recordTo < $from) {
                $match = false;
            }
            if ($to && $recordFrom > $to) {
                $match = false;
            }

            if ($category && $match) {
                $cat = strtolower(trim($category));
                $title = strtolower(trim($r['title'] ?? ''));
                $match = strtolower((string) ($r['report_category'] ?? '')) === $cat || strpos($title, $cat) !== false;
                if (!$match && strlen($cat) >= 4) {
                    $prefix = substr($cat, 0, strlen($cat) - 1);
                    $match = strpos($title, $prefix) !== false;
                }
                // Also try matching the report_type keyword from the title
                $keywords = ['revenue','expense','fleet','route','driver','summary','risk','comprehensive','full'];
                if (!$match) {
                    foreach ($keywords as $kw) {
                        if (strpos($title, $kw) !== false) {
                            $match = (strpos($kw, $cat) !== false) || (strpos($cat, $kw) !== false);
                            if ($match) break;
                        }
                    }
                }
            }

            if ($match) {
                if (!empty($_GET['driver']) || !empty($_GET['bus'])) {
                    if (!$savedScope) continue;
                    if (!empty($_GET['driver']) && ($savedScope['filters']['driver'] ?? '') !== $_GET['driver']) continue;
                    if (!empty($_GET['bus']) && ($savedScope['filters']['bus'] ?? '') !== $_GET['bus']) continue;
                }
                $r['period_from'] = $recordFrom;
                $r['period_to'] = $recordTo;
                $filtered[] = $r;
            }
        }

        sendJson(['success' => true, 'reports' => $filtered]);
    }

    /*
    |--------------------------------------------------------------------------
    | ARCHIVE REPORT
    |--------------------------------------------------------------------------
    */
    if ($action === 'archive_report') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid report ID.');
        $model->setArchiveStatus($id, true);
        $auditModel->log('Finance', 'Archive', "Archived report #{$id}", $currentUserId, $currentUsername, $currentUserRole, (string)$id);
        sendJson(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | UNARCHIVE REPORT
    |--------------------------------------------------------------------------
    */
    if ($action === 'unarchive_report') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid report ID.');
        $model->setArchiveStatus($id, false);
        $auditModel->log('Finance', 'Restore', "Unarchived report #{$id}", $currentUserId, $currentUsername, $currentUserRole, (string)$id);
        sendJson(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT REPORT AS EXCEL
    |--------------------------------------------------------------------------
    */
    if ($action === 'export_report_excel') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid report ID.');
        $report = $model->getReportById($id);
        if (!$report) throw new Exception('Report not found.');

        require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $sheet->setCellValue('A1', $report['title'] ?? 'Financial Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Parse report data into rows
        $content = $report['report_data'] ?? '';
        $lines = explode("\n", $content);
        $row = 3;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $sheet->setCellValue('A' . $row, $line);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(120);

        $auditModel->log('Finance', 'Export', "Exported report #{$id} to Excel", $currentUserId, $currentUsername, $currentUserRole, (string)$id);
        $exportLogModel->logExport($report['title'] ?? null, 'Financial Report', null, 'Excel', $currentUsername, $currentUserRole, $_SERVER['HTTP_USER_AGENT'] ?? null, "report_{$id}.xlsx");

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report_' . $id . '.xlsx"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT REPORT AS PDF
    |--------------------------------------------------------------------------
    */
    if ($action === 'export_report_pdf') {
        $savedFinancialSnapshot = \Finance\Models\FinancialPerformanceReportModel::get(Database::get(), (int) ($_GET['id'] ?? 0));
        if ($savedFinancialSnapshot !== null) {
            $savedReport = $model->getReportById((int) ($_GET['id'] ?? 0));
            $savedFinancialSnapshot['report_type'] = $savedFinancialSnapshot['report_type'] ?? \Finance\Services\FinancialReportPresentationService::type((string) ($savedReport['report_category'] ?? 'full'));
            session_write_close();
            $pdfBytes = \Finance\Services\FinancialPerformancePdfService::pdf($savedFinancialSnapshot, $currentUsername);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="financial-performance-report.pdf"');
            echo $pdfBytes;
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid report ID.');
        $report = $model->getReportById($id);
        if (!$report) throw new Exception('Report not found.');

        require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
        require_once __DIR__ . '/../../app/Views/pdf/template.php';

        function formatPdfContent(string $text, string $todayDate): string {
            $text = preg_replace('/[*#_~`>]+/', '', $text);
            $text = preg_replace('/^\s*[-=]{3,}\s*$/m', '', $text);
            $text = preg_replace('/\|/', '', $text);
            $text = preg_replace('/\[.*?\]\(.*?\)/', '', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            $text = trim($text);

            $lines = explode("\n", $text);
            $html = '';
            $i = 0;

            while ($i < count($lines)) {
                $line = $lines[$i];
                $trimmed = trim($line);

                if ($trimmed === '') { $i++; continue; }

                // Section header: short capitalized line
                if (preg_match('/^[A-Z][A-Za-z\s]{2,60}$/', $trimmed) && strlen($trimmed) < 60 && !str_contains($trimmed, '  ')) {
                    $html .= '<h2 style="color:#05590c;font-size:13px;font-weight:800;margin:18px 0 6px 0;padding-bottom:4px;border-bottom:2px solid #e7e3af;">' . htmlspecialchars($trimmed) . '</h2>';
                    $i++;
                    continue;
                }

                // Table row: detect 2+ columns separated by 3+ spaces
                $parts = preg_split('/\s{3,}/', $trimmed);
                if (count($parts) >= 2) {
                    $html .= '<table style="width:100%;border-collapse:collapse;margin:6px 0;font-size:9px;">';
                    $firstRow = true;
                    while ($i < count($lines)) {
                        $cur = trim($lines[$i]);
                        if ($cur === '') { $i++; continue; }
                        $cells = preg_split('/\s{3,}/', $cur);
                        if (count($cells) < 2) break;
                        if ($firstRow || preg_match('/^(Revenue|Expense|Category|Amount|Bus|Driver|Route|Passenger|Trip|Financial|KPI|Metric|Total|Payment|Fuel)/i', $cells[0])) {
                            $html .= '<tr style="background:#f5f9f5;">';
                            foreach ($cells as $c) {
                                $html .= '<td style="padding:4px 8px;border:1px solid #d0d0d0;font-weight:700;color:#05590c;">' . htmlspecialchars(trim($c)) . '</td>';
                            }
                            $firstRow = false;
                        } else {
                            $html .= '<tr>';
                            foreach ($cells as $c) {
                                $html .= '<td style="padding:4px 8px;border:1px solid #d0d0d0;color:#444;">' . htmlspecialchars(trim($c)) . '</td>';
                            }
                        }
                        $html .= '</tr>';
                        $i++;
                    }
                    $html .= '</table>';
                    continue;
                }

                // Bullet point
                if (preg_match('/^[-•]\s/', $trimmed)) {
                    $html .= '<li style="margin:3px 0 3px 16px;color:#444;font-size:9.5px;">' . htmlspecialchars(preg_replace('/^[-•]\s+/', '', $trimmed)) . '</li>';
                    $i++;
                    continue;
                }

                // Numbered item
                if (preg_match('/^\d+[.)]\s/', $trimmed)) {
                    $html .= '<p style="margin:4px 0;color:#444;font-size:9.5px;padding-left:14px;"><strong>' . htmlspecialchars($trimmed) . '</strong></p>';
                    $i++;
                    continue;
                }

                // Regular paragraph
                $html .= '<p style="margin:4px 0;color:#444;font-size:9.5px;line-height:1.6;">' . htmlspecialchars($trimmed) . '</p>';
                $i++;
            }

            return $html;
        }

        $content = $report['report_data'] ?? '';
        $title = $report['title'] ?? 'Financial Report';
        $category = $report['report_category'] ?? 'Financial';

        $todayDate = date('F j, Y');
        $formattedBody = formatPdfContent($content, $todayDate);

        $bodyContent = '
            <div class="section">
                <div class="section-title">Report Content</div>
                <div class="summary-box">' . $formattedBody . '</div>
            </div>';

        $html = renderPdfHtml(
            title: $title,
            bodyContent: $bodyContent,
            orgSub: 'Transportation Management System &mdash; Official Financial Report',
            meta: [
                'Report #' . $id,
                $category,
                $todayDate,
                'Generated by' => ($report['generated_by'] ?? 'System') . ' (' . ($report['generated_by_role'] ?? 'Unknown') . ')',
                'Downloaded by' => $currentUsername . ' (' . $currentUserRole . ')'
            ]
        );

        $auditModel->log('Finance', 'Export', "Exported report #{$id} to PDF", $currentUserId, $currentUsername, $currentUserRole, (string)$id);
        $exportLogModel->logExport($title, 'Financial Report', null, 'PDF', $currentUsername, $currentUserRole, $_SERVER['HTTP_USER_AGENT'] ?? null, "report_{$id}.pdf");

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (ob_get_length()) ob_end_clean();
        $dompdf->stream("report_{$id}.pdf", ['Attachment' => true]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORT BY ID
    |--------------------------------------------------------------------------
    */
    if ($action === 'get_report') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('Invalid report ID.');
        }
        $report = $model->getReportById($id);
        if (!$report) {
            throw new Exception('Report not found.');
        }
        // Strip markdown formatting from stored report content for existing reports
        if (!empty($report['report_data'])) {
            $content = $report['report_data'];
            $content = preg_replace('/[*#_~`>]+/', '', $content);
            $content = preg_replace('/^\s*[-=]{3,}\s*$/m', '', $content);
            $content = preg_replace('/[|]/', '', $content);
            $content = preg_replace('/\[.*?\]\(.*?\)/', '', $content);
            // Strip lines containing only N/A
            $lines = explode("\n", $content);
            $filtered = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^\s*(?:N\/A|\-)\s*$/', $trimmed)) continue;
                if (preg_match('/\bN\/A\b/', $trimmed) && !preg_match('/[A-Za-z]{3,}/', $trimmed)) continue;
                $filtered[] = $line;
            }
            $content = implode("\n", $filtered);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            $report['report_data'] = trim($content);
        }
        $savedSnapshot = \Finance\Models\FinancialPerformanceReportModel::get(Database::get(), $id);
        sendJson([
            'success' => true, 'report' => $report,
            'has_financial_snapshot' => $savedSnapshot !== null,
            'period_from' => $savedSnapshot['filters']['from'] ?? null,
            'period_to' => $savedSnapshot['filters']['to'] ?? null,
        ]);
    }

    throw new Exception('Unknown action: ' . $action);

} catch (Throwable $e) {
    sendJson([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
