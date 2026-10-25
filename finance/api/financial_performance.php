<?php
declare(strict_types=1);

require_once __DIR__ . '/_reporting_bootstrap.php';
try {
require_once __DIR__ . '/../middleware/validate_request.php';
require_once __DIR__ . '/../services/FinancialPerformanceService.php';
require_once __DIR__ . '/../services/FinancialPerformancePdfService.php';
require_once __DIR__ . '/../models/FinancialReportModel.php';
require_once __DIR__ . '/../models/FinancialPerformanceReportModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../services/FinancialReportAIService.php';
} catch (Throwable $e) {
    error_log('Financial performance initialization: ' . $e->getMessage());
    fpr_json(['success' => false, 'message' => 'The reporting service could not initialize. Please contact Finance support.'], 500);
}

use App\Auth\Auth;
use App\Core\Database;
use Finance\Services\FinancialPerformanceService;
use Finance\Services\FinancialPerformancePdfService;

function fpr_json(array $data, int $status = 200): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

if (!Auth::check()) fpr_json(['success' => false, 'message' => 'Please sign in to Finance.'], 401);
if (!in_array(strtolower((string) Auth::role()), ['finance_manager', 'finance_officer'], true)) fpr_json(['success' => false, 'message' => 'Finance access required.'], 403);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'analytics');
validateFinanceRequest('standard', false, $action === 'generate');
setRequestTimeout(120);
ini_set('memory_limit', '512M');

try {
    if ($action === 'options') {
        $db = Database::tryGet();
        if (!$db) throw new RuntimeException('Report filter options are unavailable.');
        fpr_json(['success' => true, 'options' => (new FinancialPerformanceService($db))->options()]);
    }
    if ($action === 'analytics' || $action === 'preview') {
        $db = Database::tryGet();
        if (!$db) throw new RuntimeException('Financial data is unavailable. Please retry.');
        $reportId = 0;
        $reportTitle = 'Generated Financial Report';
        if ($action === 'preview') {
            $reportId = (int) ($_GET['id'] ?? 0);
            $report = (new \Finance\Models\FinancialReportModel())->getReportById($reportId);
            $data = \Finance\Models\FinancialPerformanceReportModel::get($db, $reportId);
            if (!$report || !$data) fpr_json(['success' => false, 'message' => 'This report has no saved financial snapshot. Its original text and PDF remain available in the report archive.'], 404);
            $reportTitle = (string) $report['title'];
            $data['report_type'] = $data['report_type'] ?? \Finance\Services\FinancialReportPresentationService::type((string) ($report['report_category'] ?? 'full'));
        } else {
            $data = (new FinancialPerformanceService($db))->build($_GET);
            $data['report_type'] = \Finance\Services\FinancialReportPresentationService::type((string) ($_GET['report_type'] ?? 'full'));
        }
        $data['charts'] = FinancialPerformancePdfService::charts($data);
        $token = bin2hex(random_bytes(24));
        $snapshots = $_SESSION['financial_performance_snapshots'] ?? [];
        foreach ($snapshots as $key => $value) if ($value['expires'] < time()) unset($snapshots[$key]);
        while (count($snapshots) >= 3) array_shift($snapshots);
        $snapshots[$token] = ['expires' => time() + 1800, 'owner' => (string) Auth::userId(), 'data' => $data, 'report_id' => $reportId];
        $_SESSION['financial_performance_snapshots'] = $snapshots;
        fpr_json(['success' => true, 'snapshot' => $token, 'data' => \Finance\Services\FinancialReportPresentationService::apply($data), 'report_id' => $reportId, 'title' => $reportTitle]);
    }

    $token = (string) ($_POST['snapshot'] ?? $_GET['snapshot'] ?? '');
    $stored = $_SESSION['financial_performance_snapshots'][$token] ?? null;
    if (!$stored || $stored['expires'] < time() || ($stored['owner'] ?? '') !== (string) Auth::userId()) fpr_json(['success' => false, 'message' => 'This report preview expired. Reopen the saved report or retry loading it before downloading.'], 409);
    $data = $stored['data'];
    $user = Auth::user() ?: [];
    $actor = (string) ($user['username'] ?? $user['name'] ?? 'Finance');

    if ($action === 'generate') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fpr_json(['success' => false, 'message' => 'POST required.'], 405);
        if (!empty($stored['report_id'])) fpr_json(['success' => true, 'report_id' => $stored['report_id'], 'title' => $data['title'] ?? 'Financial Performance Report', 'report' => $data['narrative'] ?? '']);
        $reportType = (string) ($_POST['report_type'] ?? 'full');
        $categories = ['full' => 'comprehensive', 'revenue' => 'revenue_analysis', 'expenses' => 'operating_expenses', 'fleet' => 'fleet_performance', 'routes' => 'route_performance', 'drivers' => 'driver_performance', 'summary' => 'financial_summary', 'risk' => 'risk_assessment'];
        if (!isset($categories[$reportType])) throw new InvalidArgumentException('Unsupported report type.');
        $data['report_type'] = $reportType;
        $description = trim((string) ($_POST['description'] ?? ''));
        try {
            $data['narrative'] = (new \Finance\Services\FinancialReportAIService())->generateSnapshotAnalysis($data, $reportType, $description);
        } catch (RuntimeException $e) {
            fpr_json(['success' => false, 'message' => $e->getMessage()], 503);
        }
        $model = new \Finance\Models\FinancialReportModel();
        $model->initTables();
        $db = Database::get();
        \Finance\Models\FinancialPerformanceReportModel::initialize($db);
        $title = ucwords(str_replace('_', ' ', $categories[$reportType])) . ' Report — ' . $data['filters']['from'] . ' to ' . $data['filters']['to'];
        $data['title'] = $title;
        $db->beginTransaction();
        try {
            $id = $model->saveReport($title, $data['narrative'], $reportType, $actor, (string) ($user['role'] ?? ''));
            \Finance\Models\FinancialPerformanceReportModel::save($db, $id, $data);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $_SESSION['financial_performance_snapshots'][$token]['report_id'] = $id;
        $_SESSION['financial_performance_snapshots'][$token]['data'] = $data;
        $_SESSION['financial_performance_snapshots'][$token]['expires'] = time() + 1800;
        try {
            (new \Finance\Models\AuditTrailModel())->log('Finance', 'Generate', $title, (int) ($user['id'] ?? 0), $actor, (string) ($user['role'] ?? ''), (string) $id, null, ['filters' => $data['filters'], 'totals' => $data['totals'], 'snapshot_time' => $data['generated_at']]);
        } catch (Throwable $e) { error_log('Financial performance audit: ' . $e->getMessage()); }
        $view = \Finance\Services\FinancialReportPresentationService::apply($data);
        fpr_json(['success' => true, 'report_id' => $id, 'title' => $title, 'category' => $categories[$reportType], 'report_type' => $reportType, 'presentation' => $view['presentation'], 'charts' => FinancialPerformancePdfService::charts($view), 'insights' => $view['insights'], 'report' => $data['narrative'], 'generated_at' => date('Y-m-d H:i:s'), 'generated_by' => $actor, 'generated_by_role' => (string) ($user['role'] ?? '')]);
    }

    if ($action === 'pdf') {
        session_write_close();
        $bytes = FinancialPerformancePdfService::pdf($data, $actor);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="financial-performance-' . $data['filters']['from'] . '-' . $data['filters']['to'] . '.pdf"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    if ($action === 'csv') {
        $data = \Finance\Services\FinancialReportPresentationService::apply($data);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial-records.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Source reference', 'Type', 'Category', 'Driver', 'Bus', 'Amount PHP', 'Description'], ',', '"', '');
        foreach ($data['records'] as $r) {
            $cells = [$r['date'], $r['reference'], $r['kind'], $r['category'], $r['driver'], $r['bus'], number_format($r['amount'], 2, '.', ''), $r['description']];
            $cells = array_map(static fn($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'" . $v : $v, $cells);
            fputcsv($out, $cells, ',', '"', '');
        }
        fclose($out);
        exit;
    }
    fpr_json(['success' => false, 'message' => 'Unknown report action.'], 400);
} catch (InvalidArgumentException $e) {
    fpr_json(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Financial performance: ' . $e->getMessage());
    fpr_json(['success' => false, 'message' => 'Unable to prepare financial reporting data. Please retry or contact Finance support.'], 500);
}
