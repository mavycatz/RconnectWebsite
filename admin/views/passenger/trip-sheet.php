<?php
declare(strict_types = 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

$sheetToken = $_SESSION['admin_access_token'] ?? '';
$sheetUrl = function (string $page) use ($sheetToken): string {
    return $sheetToken ? "/admin/{$page}/{$sheetToken}" : '/admin/views/passenger/index.php';
};

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/PassengerManifestFields.php';

$h = function ($key): string {
    return htmlspecialchars((string)($_GET[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};

$tripDate = $h('trip_date') ?: date('Y-m-d');
$route = $h('trip_route') ?: $h('route');
$busNo = $h('bus_number');
$driver = $h('driver');
$tripNoRaw = (string)($_GET['trip_number'] ?? '');

if ($tripNoRaw === '') {
    $tripNo = 20000;
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        $max = \App\Core\Database::get()->query(
        "SELECT MAX(CAST(trip_id AS UNSIGNED)) FROM passenger_trip_history WHERE trip_id REGEXP '^[0-9]+$' AND CAST(trip_id AS UNSIGNED) >= 20000"
        )->fetchColumn();
        $tripNo = max(20000, ((int)$max) + 1);
    } catch (\Throwable $e) {
        $tripNo = 20000;
    }
} else {
    $tripNo = $tripNoRaw;
}

$rowsRaw = (int)($_GET['rows'] ?? 0);
$maxRows = 70;
$rowCount = $rowsRaw > 0 && $rowsRaw <= $maxRows
? $rowsRaw
: \Admin\Services\PassengerManifestFields::DEFAULT_TRIP_SHEET_ROWS;

$sheetHeaders = array_values(\Admin\Services\PassengerManifestFields::PASSENGER_HEADERS);
$sheetHeaders[] = 'Inspector Signature';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Passenger Trip Manifest — R-Connect TMS</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="/admin/assets/css/role-management.css?v=<?= time() ?>">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            html, body {
                background: #f2f2f2;
                font-family: 'Arial', 'Helvetica', sans-serif;
                color: #111;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .sheet {
                width: 297mm;
                margin: 16px auto;
                background: #fff;
                border: 1px solid #999;
                padding: 0;
                box-shadow: 0 2px 12px rgba(0, 0, 0, .15);
            }
            .sheet-inner { padding: 7mm 8mm; }

            .manifest-title {
                text-align: center;
                margin: 0 0 2mm;
                font-size: 14px;
                font-weight: 800;
                letter-spacing: 1px;
            }

            .meta-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 2mm 0 1.2mm; }
            .meta-table td { border: 1px solid #111; padding: 0.8mm 1.5mm; font-size: 10px; height: 6mm; }
            .meta-table .label { font-weight: 700; background: #eee; }

            table.main { width: 100%; border-collapse: collapse; table-layout: fixed; }
            table.main th, table.main td { border: 1px solid #111; padding: 0; }
            table.main th {
                background: #eee; font-size: 8.5px; font-weight: 700;
                text-align: center; padding: 1.2mm 0.8mm; letter-spacing: .2px;
            }
            table.main td { height: 7mm; text-align: center; font-size: 8.5px; }
            table.main td:first-child { background: #fafafa; }

            table.main th:nth-child(1), table.main td:nth-child(1) { width: 8mm; }
            table.main th:nth-child(2), table.main td:nth-child(2) { width: 44mm; }
            table.main th:nth-child(3), table.main td:nth-child(3) { width: 15mm; }
            table.main th:nth-child(4), table.main td:nth-child(4) { width: 14mm; }
            table.main th:nth-child(5), table.main td:nth-child(5) { width: 13mm; }
            table.main th:nth-child(6), table.main td:nth-child(6) { width: 30mm; }
            table.main th:nth-child(7), table.main td:nth-child(7) { width: 30mm; }
            table.main th:nth-child(8), table.main td:nth-child(8) { width: 13mm; }
            table.main th:nth-child(9), table.main td:nth-child(9) { width: 13mm; }
            table.main th:nth-child(10), table.main td:nth-child(10) { width: 35mm; }
            table.main th:nth-child(10) { font-size: 8.2px; }
            table.main td:nth-child(10) { text-align: left; padding: 0 2mm; }

            .footer-info { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
            .footer-info td { border: 1px solid #111; padding: 1mm 1.5mm; font-size: 10.5px; vertical-align: top; }
            .footer-info .label { font-weight: 700; background: #eee; }
            .tot-pax td, .tot-pax .label { height: 6mm; }
            .remarks-cell { height: 10mm; }

            .totals { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
            .totals td { border: 1px solid #111; padding: 1mm 1.5mm; font-size: 11px; height: 6mm; }
            .totals .label { font-weight: 700; width: 32mm; background: #eee; }

            .signatures { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
            .signatures td { border: 1px solid #111; padding: 1.5mm 1.5mm 2.5mm; vertical-align: bottom; text-align: center; }
            .signatures .line { border-top: 1px solid #111; padding-top: 1.2mm; font-size: 11px; font-weight: 700; }
            .signatures .cap { font-size: 10px; color: #333; margin-bottom: 1.5mm; }

            @media screen { html, body { background:linear-gradient(180deg,#e9eeda,#e1e8cd); } }
            .noprint.rmg-shell { max-width:1440px; margin:20px auto; }
            .noprint .print-hint { margin-top: 10px; font-size: 12px; color: #8a9a8a; }
            .noprint .print-hint b { color: #57694d; }
            .form-links { text-align: center; margin-bottom: 12px; }

            @page {
                size: A4 landscape;
                margin: 0;
            }

            @media print {
                html, body { background: #fff !important; }
                .sheet {
                    width: 297mm;
                    height: auto;
                    margin: 0;
                    border: none;
                    box-shadow: none;
                    overflow: visible;
                    background: #fff;
                }
                .sheet-inner { padding: 6mm 8mm 8mm; }
                .noprint, .form-links { display: none !important; }
                body { margin: 0; padding: 0; }
                thead { display: table-header-group; }
                table.main tr { break-inside: avoid; page-break-inside: avoid; }
                table.main thead tr { break-after: avoid; page-break-after: avoid; }
                .manifest-title { break-after: avoid; page-break-after: avoid; }
                .meta-table, .totals, .footer-info, .signatures { break-inside: avoid; page-break-inside: avoid; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
        </style>
    </head>
    <body>

        <div class="noprint rmg-shell"><section class="rmg">
            <header class="rmg-head"><div><h1>Passenger Trip Manifest</h1><p>Generate and print the passenger manifest for this trip.</p></div><div class="rmg-head-actions">
            <button class="rmg-btn rmg-btn-primary" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print Trip Sheet</button>
    <?php $gsQuery = http_build_query(array_filter([
'trip_date' => $tripDate,
'trip_route' => $route,
'bus_number' => $busNo,
'driver' => $driver,
'trip_number' => $tripNo,
'rows' => $_GET['rows'] ?? null,
], fn($v) => $v !== '' && $v !== null)); ?>
    <a class="rmg-btn" href="/admin/api/Passenger/trip_sheet_pdf.php?<?= $gsQuery ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</a>
<a class="rmg-btn" href="<?= $sheetUrl('passenger') ?>">&larr; Back to Passengers</a>
</div></header>
<div class="print-hint">Best print quality: use <b>Download PDF</b> — it produces a clean A4 landscape manifest with no browser address bar, page numbers, or headers/footers. For browser printing, set <b>Margins to None</b>, <b>disable Headers &amp; Footers</b>, and enable <b>Background graphics</b>.</div>
</section></div>

<div class="form-links noprint">
    <a class="rmg-btn rmg-btn-sm" href="<?= $sheetUrl('passenger-trip-sheet') . '?' . $gsQuery ?>">Blank sheet</a>
</div>

<div class="sheet">
    <div class="sheet-inner">

        <div class="manifest-title">TALAAN NG MGA PASAHERO - RTTMS</div>

        <table class="main">
            <thead>
                <tr>
                    <th>#</th>
            <?php foreach ($sheetHeaders as $label): ?>
                <th><?= $label ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php for ($n = 1; $n <= $rowCount; $n++): ?>
            <tr>
                <td><?= $n ?></td>
                <?php foreach ($sheetHeaders as $label): ?>
                    <td>&nbsp;</td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
        </table>

        <table class="meta-table">
            <tr>
                <td class="label">Trip Date:</td>
                <td><?= $tripDate ?></td>
<td class="label">Route:</td>
<td><?= $route ?></td>
<td class="label">Bus No.:</td>
<td><?= $busNo ?></td>
<td class="label">Driver:</td>
<td><?= $driver ?></td>
<td class="label">Trip No.:</td>
<td><?= htmlspecialchars((string)$tripNo, ENT_QUOTES, 'UTF-8') ?></td>
</tr>
</table>

<table class="totals">
    <tr>
        <td class="label">Total Passengers:</td>
        <td>&nbsp;</td>
        <td class="label">Total Fare:</td>
        <td>&#8369; ____________________</td>
    </tr>
</table>

<table class="footer-info">
    <tr>
        <td class="label" style="width:22mm;">Remarks:</td>
        <td class="remarks-cell">&nbsp;</td>
    </tr>
</table>

<table class="signatures">
    <tr>
        <td>
            <div class="cap">Driver Signature</div>
            <div class="line">&nbsp;</div>
        </td>
        <td>
            <div class="cap">Inspector Signature</div>
            <div class="line">&nbsp;</div>
        </td>
        <td>
            <div class="cap">Checked By</div>
            <div class="line">&nbsp;</div>
        </td>
    </tr>
</table>

</div>
</div>

</body>
</html>
