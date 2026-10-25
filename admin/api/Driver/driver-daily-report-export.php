<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once __DIR__ . '/driver-daily-report.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    send_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user = Auth::user();
if (!isset($user['role']) || !in_array(strtolower($user['role']), ['admin', 'super_admin'])) {
    send_json(['success' => false, 'message' => 'Forbidden'], 403);
}

use App\Core\Database;

$reportDate = trim((string)($_GET['report_date'] ?? ''));
if (empty($reportDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
    date_default_timezone_set('Asia/Manila');
    $reportDate = date('Y-m-d', strtotime('yesterday'));
}

try {
    $db = Database::get();
    ddr_ensure_table($db);

    $stored = ddr_load_stored($db, $reportDate);
    $actor = $user ?? [];
    $exportedBy = (string)($actor['username'] ?? $actor['name'] ?? 'admin');
    $exportedByRole = (string)($actor['role'] ?? '');
    $generatedBy = $stored['generated_by'] ?? null;
    $generatedAt = $stored['created_at'] ?? null;

    if ($stored && $stored['status'] === 'Finalized') {
        $snapshot = $stored['report_data'];
    } else {
        $check = ddr_complete_check($db, $reportDate);
        if (!$check['complete']) {
            send_json(['success' => false, 'message' => 'Cannot export: the daily closing for ' . $reportDate . ' is still pending. Complete all remittances first.'], 400);
        }
        $snapshot = ddr_build_report($db, $reportDate);
        $snapshot['stored_id'] = ddr_save($db, $reportDate, 'Finalized', $snapshot, $exportedBy);
        $generatedBy = $exportedBy;
        $generatedAt = date('Y-m-d H:i:s');
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Views/pdf/template.php';

    $totals = $snapshot['totals'] ?? [];
    $drivers = $snapshot['drivers'] ?? [];
    $insights = $snapshot['insights'] ?? [];

    $money = function ($n) {
        return '&#8369;' . number_format((float)$n, 2);
    };
    $num = function ($n) {
        return number_format((float)$n);
    };

    $body = '';

    // Summary KPI cards
    $cards = [
        ['Total Trips', $num($totals['trips'] ?? 0)],
        ['Passengers', $num($totals['passengers'] ?? 0)],
        ['Other Items', $num($totals['other'] ?? 0)],
        ['Gross Income', $money($totals['gross_income'] ?? 0)],
        ['Total Remittance', $money($totals['remittance'] ?? 0)],
        ['Fuel Expenses', $money($totals['fuel'] ?? 0)],
        ['Net Income', $money($totals['net_income'] ?? 0)],
        ['Attendance', (int)($totals['attendance_present'] ?? 0) . ' / ' . (int)($totals['attendance_scheduled'] ?? 0)],
    ];
    $body .= '<div style="margin-bottom:20px;">';
    foreach ($cards as $c) {
        $body .= '<div class="stats-card"><div class="stats-title">' . $c[0] . '</div><div class="stats-value">' . $c[1] . '</div></div>';
    }
    $body .= '<div style="clear:both;"></div></div>';

    // Per-driver breakdown
    $body .= '<div class="section"><div class="section-title">Per-Driver Daily Breakdown</div>';
    if ($drivers) {
        $body .= '<table><thead><tr>'
            . '<th>Driver</th><th>Att.</th><th>Trips</th><th>Pax</th><th>Other</th>'
            . '<th>Gross Income</th><th>Remittance</th><th>Fuel</th><th>Expenses</th><th>Net Income</th><th>Remit Status</th>'
            . '</tr></thead><tbody>';
        foreach ($drivers as $d) {
            $att = $d['attendance'] ?? [];
            $body .= '<tr><td>' . htmlspecialchars($d['name'] ?? 'Unknown') . '<br><span style="color:#666;">' . htmlspecialchars($d['driver_id'] ?? '') . '</span></td>'
                . '<td>' . ($att['present'] ?? 0) . '/' . ($att['scheduled'] ?? 0) . '</td>'
                . '<td>' . $num($d['trips'] ?? 0) . '</td>'
                . '<td>' . $num($d['passengers'] ?? 0) . '</td>'
                . '<td>' . $num($d['other'] ?? 0) . '</td>'
                . '<td>' . $money($d['gross_income'] ?? 0) . '</td>'
                . '<td>' . $money($d['remittance'] ?? 0) . '</td>'
                . '<td>' . $money($d['fuel_cost'] ?? 0) . '</td>'
                . '<td>' . $money($d['expenses'] ?? 0) . '</td>'
                . '<td>' . $money($d['net_income'] ?? 0) . '</td>'
                . '<td>' . htmlspecialchars($d['remittance_status'] ?? 'No Remit') . '</td></tr>';
        }
        $body .= '</tbody></table>';
    } else {
        $body .= '<p>No driver data for this date.</p>';
    }
    $body .= '</div>';

    // Insights
    $body .= '<div class="section"><div class="section-title">Daily Insights</div><ul>';
    $added = false;
    foreach ($insights as $label => $val) {
        if (!$val || (is_array($val) && !count($val))) continue;
        $nice = ucwords(str_replace('_', ' ', (string)$label));
        $text = is_array($val) ? implode('; ', array_map('htmlspecialchars', $val)) : htmlspecialchars((string)$val);
        $body .= '<li><span class="green">' . $nice . ':</span> ' . $text . '</li>';
        $added = true;
    }
    if (!$added) {
        $body .= '<li>No notable observations for this day.</li>';
    }
    $body .= '</ul></div>';

    $safeDate = preg_replace('/[^0-9]/', '', $reportDate);
    $title = 'Daily_Closing_Report_' . $safeDate;

    $html = renderPdfHtml(
        title: 'Daily Closing Report &mdash; ' . date('F d, Y', strtotime($reportDate)),
        bodyContent: $body,
        orgSub: 'Daily Finalized Closing Report',
        meta: [
            'Report Date: ' . date('F d, Y', strtotime($reportDate)),
            'Status: Finalized',
            'Generated by: ' . ($generatedBy ?: $exportedBy) . ($generatedAt ? ' on ' . date('F d, Y h:i A', strtotime($generatedAt)) : ''),
            'Exported by: ' . $exportedBy . ' on ' . date('F d, Y h:i A'),
        ]
    );

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $directory = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/daily_report';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $filename = $title . '_' . date('Ymd_His') . '.pdf';
    file_put_contents($directory . '/' . $filename, $dompdf->output());

    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';
        \AuditService::log(
            'Driver Performance',
            'Report Exported',
            "Exported PDF daily closing report for {$reportDate}",
            $reportDate,
            null,
            ['format' => 'PDF', 'generated_by' => $generatedBy ?: $exportedBy, 'exported_by' => $exportedBy],
            'Success'
        );
    } catch (\Throwable $e) {
        error_log('Audit log error (daily export): ' . $e->getMessage());
    }

    send_json([
        'success' => true,
        'url' => '/generated_reports/daily_report/' . $filename,
        'generated_by' => $generatedBy ?: $exportedBy,
        'generated_at' => $generatedAt,
        'exported_by' => $exportedBy,
    ]);
} catch (Throwable $e) {
    send_json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}
