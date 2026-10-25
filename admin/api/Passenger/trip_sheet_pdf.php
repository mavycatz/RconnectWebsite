<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/PassengerManifestFields.php';

use App\Auth\Session;
use App\Auth\Auth;
use Dompdf\Dompdf;
use Dompdf\Options;

Session::start();

if (!Auth::check() || !in_array(Auth::user()['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$h = static fn ($key): string => htmlspecialchars((string)($_GET[$key] ?? ''), ENT_QUOTES, 'UTF-8');

$tripDate = $h('trip_date') ?: date('Y-m-d');
$route = $h('trip_route') ?: $h('route');
$busNo    = $h('bus_number');
$driver   = $h('driver');
$tripNoRaw = (string)($_GET['trip_number'] ?? '');

if ($tripNoRaw === '') {
    $tripNo = 20000;
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        $rawTripNo = \App\Core\Database::get()->query(
            "SELECT MAX(CAST(trip_id AS UNSIGNED)) FROM passenger_trip_history WHERE trip_id REGEXP '^[0-9]+$' AND CAST(trip_id AS UNSIGNED) >= 20000"
        )->fetchColumn();
        $tripNo = max(20000, ((int)$rawTripNo) + 1);
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

$rowsHtml = '';
for ($n = 1; $n <= $rowCount; $n++) {
    $rowsHtml .= '<tr>
        <td>' . $n . '</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td class="sig">&nbsp;</td>
    </tr>';
}

$headersHtml = '<th>#</th>';
foreach ($sheetHeaders as $label) {
    $headersHtml .= '<th>' . $label . '</th>';
}

$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Arial", "Helvetica", sans-serif; color: #111; }

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
    table.main th { background: #eee; font-size: 8.5px; font-weight: 700; text-align: center; padding: 1.2mm 0.8mm; }
    table.main td { height: 7mm; text-align: center; font-size: 8.5px; }

    table.main th:nth-child(1),  table.main td:nth-child(1)  { width: 8mm; }
    table.main th:nth-child(2),  table.main td:nth-child(2)  { width: 44mm; }
    table.main th:nth-child(3),  table.main td:nth-child(3)  { width: 15mm; }
    table.main th:nth-child(4),  table.main td:nth-child(4)  { width: 14mm; }
    table.main th:nth-child(5),  table.main td:nth-child(5)  { width: 13mm; }
    table.main th:nth-child(6),  table.main td:nth-child(6)  { width: 30mm; }
    table.main th:nth-child(7),  table.main td:nth-child(7)  { width: 30mm; }
    table.main th:nth-child(8),  table.main td:nth-child(8)  { width: 13mm; }
    table.main th:nth-child(9),  table.main td:nth-child(9)  { width: 13mm; }
    table.main th:nth-child(10), table.main td:nth-child(10) { width: 35mm; }
    table.main th:nth-child(10) { font-size: 8.2px; }
    table.main td:nth-child(10) { text-align: left; }

    .totals { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
    .totals td { border: 1px solid #111; padding: 1mm 1.5mm; font-size: 10px; height: 6mm; }
    .totals .label { font-weight: 700; width: 32mm; background: #eee; }

    .footer-info { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
    .footer-info td { border: 1px solid #111; padding: 1mm 1.5mm; font-size: 10px; vertical-align: top; }
    .footer-info .label { font-weight: 700; background: #eee; width: 22mm; }
    .remarks-box { font-size: 10px; min-height: 10mm; }

    table.signatures { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 2mm; }
    table.signatures td { border: 1px solid #111; padding: 1.5mm 1.5mm 2.5mm; vertical-align: bottom; text-align: center; }
    .signatures .line { border-top: 1px solid #111; padding-top: 1.2mm; font-size: 10px; font-weight: 700; }
    .signatures .cap { font-size: 9px; color: #333; margin-bottom: 1.5mm; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    td.sig { line-height: 6mm; }
    .no-break { page-break-inside: avoid; }
</style>
</head>
<body>
    <div class="manifest-title">TALAAN NG MGA PASAHERO - RTTMS</div>
    <table class="main">
        <thead>
            <tr>' . $headersHtml . '</tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
    </table>

    <div class="no-break">
    <table class="meta-table">
        <tr>
            <td class="label">Trip Date:</td><td>' . $tripDate . '</td>
            <td class="label">Route:</td><td>' . $route . '</td>
            <td class="label">Bus No.:</td><td>' . $busNo . '</td>
            <td class="label">Driver:</td><td>' . $driver . '</td>
            <td class="label">Trip No.:</td><td>' . htmlspecialchars((string)$tripNo, ENT_QUOTES, 'UTF-8') . '</td>
        </tr>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total Passengers:</td><td>&nbsp;</td>
            <td class="label">Total Fare:</td><td>&#8369; ____________________</td>
        </tr>
    </table>

    <table class="footer-info">
        <tr>
            <td class="label">Remarks:</td>
            <td class="remarks-box">&nbsp;</td>
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
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Passenger_Trip_Manifest_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
