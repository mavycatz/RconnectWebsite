<?php
declare(strict_types=1);

namespace Admin\Services;

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExcelService
{
    private Spreadsheet $book;

    public function __construct()
    {
        $this->book = new Spreadsheet();
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $cols): void
    {
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $range = "A1:{$last}1";
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '295738']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
        ]);
        $sheet->freezePane('A2');
    }

    private function writeSheet(string $title, array $headers, array $rows): void
    {
        $sheet = $this->book->getActiveSheet();
        $this->book->getActiveSheet()->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_values($row), null, "A{$r}");
            $r++;
        }
        $this->styleHeader($sheet, count($headers));
        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        if ($title !== 'Summary') {
            $this->book->createSheet();
            $this->book->setActiveSheetIndex($this->book->getActiveSheetIndex() + 1);
        }
    }

    public function generate(string $title, array $m): string
    {
        // ===== Summary sheet =====
        $att = $m['attendance'];
        $tr = $m['trips'];
        $pa = $m['passengers'];
        $ot = $m['other'];
        $fa = $m['fare'];
        $ca = $m['capacity'];

        $summaryRows = [
            ['Field', 'Value'],
            ['Driver', $m['name'] . ' (' . $m['driver_id'] . ')'],
            ['Period', ucfirst($m['period'] ?? 'monthly')],
            ['Attendance Present (days)', $att['present']],
            ['Attendance Absent (days)', $att['absent']],
            ['Attendance Scheduled (days)', $att['scheduled']],
            ['Attendance Rate (%)', $att['rate']],
            ['Trips Total', $tr['total']],
            ['Trips Completed', $tr['completed']],
            ['Trips Cancelled', $tr['cancelled']],
            ['Trips Pending', $tr['pending']],
            ['Passengers Total', $pa['total']],
            ['Passengers Regular', $pa['regular']],
            ['Passengers Special', $pa['special']],
            ['Other Items (records)', $ot['records']],
            ['Other Occupied Spaces', $ot['occupied_spaces']],
            ['Fare Total', $fa['total']],
            ['Fare Passenger', $fa['passenger']],
            ['Fare Other', $fa['other']],
            ['Fare Paid', $fa['paid']],
            ['Fare Pending', $fa['pending']],
            ['Gross Income', $m['gross_income'] ?? 0],
            ['Fixed Cost - Amortization', $m['fixed_costs']['amortization'] ?? 0],
            ['Fixed Cost - Insurance', $m['fixed_costs']['insurance'] ?? 0],
            ['Fixed Cost - GPS', $m['fixed_costs']['gps'] ?? 0],
            ['Fixed Cost - OPTR', $m['fixed_costs']['optr'] ?? 0],
            ['Total Fixed Costs', $m['fixed_costs']['total'] ?? 0],
            ['Fixed Cost Months (period)', $m['fixed_costs']['months'] ?? 1],
            ['Monthly Fixed Cost Total', $m['fixed_costs']['monthly_total'] ?? ($m['fixed_costs']['total'] ?? 0)],
            ['Net Income', $m['net_income'] ?? 0],
            ['Vehicle Capacity', $ca['vehicle_capacity']],
            ['Occupied Passenger', $ca['occupied_passenger']],
            ['Occupied Other', $ca['occupied_other']],
            ['Occupancy Rate (%)', $ca['occupancy']],
        ];
        $this->writeSheet('Summary', ['Field', 'Value'], $summaryRows);

        // daily performance
        $daily = array_map(function ($d) {
            return [
                'Day' => $d['day'],
                'Trips' => $d['trips'],
                'Passengers' => $d['passengers'],
                'Fare' => round((float)($d['fare'] ?? 0), 2),
            ];
        }, $m['daily_performance'] ?? []);
        if ($daily) {
            $this->writeSheet('Daily', ['Day', 'Trips', 'Passengers', 'Fare'], $daily);
        }

        // trip records
        $trips = array_map(function ($t) {
            return [
                'Trip' => $t['trip'],
                'Day' => $t['day'],
                'Records' => $t['records'],
                'Fare' => round((float)($t['fare'] ?? 0), 2),
                'Payment Status' => $t['payment_status'] ?? '',
            ];
        }, $m['trip_records'] ?? []);
        if ($trips) {
            $this->writeSheet('Trips', ['Trip', 'Day', 'Records', 'Fare', 'Payment Status'], $trips);
        }

        // passengers
        $pass = array_map(function ($p) {
            return [
                'Name' => $p['full_name'] ?? '',
                'Type' => $p['type'] ?? '',
                'Spaces' => $p['space_occupied'] ?? '',
                'Fare' => round((float)($p['fare'] ?? 0), 2),
                'Payment' => $p['payment_method'] ?? '',
                'Day' => $p['day'] ?? '',
            ];
        }, $m['passengers_records'] ?? []);
        if ($pass) {
            $this->writeSheet('Passengers', ['Name', 'Type', 'Spaces', 'Fare', 'Payment', 'Day'], $pass);
        }

        // other items
        $other = array_map(function ($o) {
            return [
                'Name' => $o['full_name'] ?? '',
                'Type' => $o['type'] ?? '',
                'Spaces' => $o['space_occupied'] ?? '',
                'Fare' => round((float)($o['fare'] ?? 0), 2),
                'Boarding' => $o['boarding_point'] ?? '',
                'Destination' => $o['destination'] ?? '',
                'Day' => $o['day'] ?? '',
            ];
        }, $m['other_items'] ?? []);
        if ($other) {
            $this->writeSheet('Other', ['Name', 'Type', 'Spaces', 'Fare', 'Boarding', 'Destination', 'Day'], $other);
        }

        // payments
        $pay = array_map(function ($p) {
            return [
                'Method' => $p['payment_method'] ?? '',
                'Status' => $p['status'] ?? '',
                'Count' => $p['count'] ?? 0,
                'Amount' => round((float)($p['amount'] ?? 0), 2),
            ];
        }, $m['payments'] ?? []);
        if ($pay) {
            $this->writeSheet('Payments', ['Method', 'Status', 'Count', 'Amount'], $pay);
        }

        // reset active to first
        $this->book->setActiveSheetIndex(0);

        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
        $directory = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/driver_excel';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $filename = $safeTitle . '_' . date('Ymd_His') . '.xlsx';
        $path = $directory . '/' . $filename;

        $writer = new Xlsx($this->book);
        $writer->save($path);

        return '/generated_reports/driver_excel/' . $filename;
    }
}
