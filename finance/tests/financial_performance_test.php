<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/FinancialPerformanceService.php';
require_once __DIR__ . '/../services/FinancialPerformancePdfService.php';
require_once __DIR__ . '/FinancialPerformanceFixturePDO.php';
require_once __DIR__ . '/../services/FinancialReportAIService.php';

use Finance\Services\FinancialPerformanceService as Service;
use Finance\Services\FinancialPerformancePdfService as Pdf;

date_default_timezone_set('Asia/Manila');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$tables = [
    'drivers' => ['rows' => [['id' => 1, 'driver_id' => 'D1', 'full_name' => 'Driver One']]],
    'buses' => ['rows' => [['id' => 9, 'bus_id' => 'B1', 'plate_number' => 'Bus One']]],
    'bus_trips' => ['rows' => [['id' => 77, 'driver_id' => 'D1', 'bus_id' => 'B1', 'trip_no' => 'T1', 'trip_date' => '2026-10-01']]],
    'passenger_trips' => ['rows' => [
        ['id' => 1, 'driver_id' => 'D1', 'trip_no' => 'T1', 'boarding_time' => '2026-10-01', 'fare' => '100.25', 'payment_status' => 'paid', 'archived_status' => 'no', 'type' => 'regular'],
        ['id' => 2, 'driver_id' => 'D1', 'trip_no' => 'T1', 'boarding_time' => '2026-10-01', 'fare' => '50.10', 'payment_status' => 'paid', 'archived_status' => 'no', 'type' => 'regular'],
        ['id' => 4, 'driver_id' => 'D1', 'trip_no' => 'T1', 'boarding_time' => '2026-10-01', 'fare' => '9999', 'payment_status' => 'pending', 'archived_status' => 'no', 'type' => 'regular'],
        ['id' => 5, 'driver_id' => 'D1', 'trip_no' => 'T1', 'boarding_time' => '2026-10-01', 'fare' => '9999', 'payment_status' => 'paid', 'archived_status' => 'yes', 'type' => 'regular'],
    ]],
    'passenger_trip_history' => ['rows' => [['history_id' => 3, 'driver_id' => 'D1', 'bus_id' => 'B1', 'trip_id' => 'T2', 'trip_date' => '2026-10-06', 'fare' => '40.00', 'type' => 'other']]],
    'expenses' => ['rows' => [['id' => 1, 'driver_id' => 'D1', 'bus_id' => 'B1', 'created_at' => '2026-10-05', 'amount' => '10.00', 'category' => 'Toll']]],
    'financial_expenses' => ['rows' => [['id' => 2, 'driver_id' => 1, 'bus_id' => 'B1', 'expense_date' => '2026-10-06', 'amount' => '20.00', 'category' => 'Fuel']]],
    'fuel_refuels' => ['rows' => [['refuel_id' => 1, 'driver_id' => 'D1', 'bus_id' => 'B1', 'created_at' => '2026-10-06', 'total_cost' => '9999']]],
    'fuel_logs' => ['rows' => []],
    'remittance_logs' => ['rows' => [['id' => 1, 'driver_id' => 'D1', 'bus_id' => 'B1', 'created_at' => '2026-10-06', 'actual_cash' => '190.35']]],
];
foreach ($tables as &$table) $table['columns'] = array_keys($table['rows'][0] ?? []);
unset($table);
$db = new FinancialPerformanceFixturePDO($tables);
$built = (new Service($db))->build(['report_period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-11', 'driver' => 'D1', 'bus' => 'B1']);
check($built['totals']['revenue'] === 190.35, 'Source pipeline excludes unpaid and archived live copies, includes archived fares');
check((float) $built['totals']['expenses'] === 30.0, 'Posted fuel ledger takes precedence over duplicate receipt sources');
check($built['totals']['passenger_revenue'] === 150.35, 'Other fares excluded from passenger revenue');
check($built['totals']['collections'] === 190.35, 'Source pipeline settlements not income');
check($built['totals']['trips'] === 2, 'Trip attribution joins unique stored trip bus');
check($built['growth']['revenue'] === null, 'Empty comparison base is unavailable');
check($built['filters']['driver'] === 'D1' && $built['filters']['bus'] === 'B1', 'Entity scope retained');
$unmatched = (new Service($db))->build(['report_period' => 'custom', 'from' => '2025-01-01', 'to' => '2025-01-01']);
check($unmatched['empty'] && (float) $unmatched['totals']['revenue'] === 0.0, 'Empty historical scope does not return current or all-time results');
$clock = new DateTimeImmutable('2026-10-11');
foreach ([
    'today' => ['2026-10-11', '2026-10-11'],
    'this_week' => ['2026-10-05', '2026-10-11'],
    'this_month' => ['2026-10-01', '2026-10-31'],
    'last6' => ['2026-05-01', '2026-10-31'],
    'this_year' => ['2026-01-01', '2026-12-31'],
] as $period => $expected) {
    $f = Service::filters(['report_period' => $period], $clock);
    check([$f['from'], $f['to']] === $expected, "$period boundaries");
}
$leap = Service::filters(['report_period' => 'this_month'], new DateTimeImmutable('2028-02-29'));
check($leap['to'] === '2028-02-29', 'Leap-year boundary');
$rollover = Service::filters(['report_period' => 'last6'], new DateTimeImmutable('2027-01-01'));
check($rollover['from'] === '2026-08-01', 'Six-month year rollover');
foreach ([['from' => '2026-02-30', 'to' => '2026-03-05'], ['from' => '2026-03-06', 'to' => '2026-03-05'], ['from' => '2026-03-06']] as $input) {
    try { Service::filters($input, $clock); check(false, 'Invalid range accepted'); }
    catch (InvalidArgumentException $e) { check(true, 'Invalid range rejected'); }
}
$f = Service::filters(['report_period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-11'], $clock);
$row = static fn($date, $kind, $cents, $driver, $bus, $category, $ref, $trip = '') => [
    'date' => $date, 'kind' => $kind, 'cents' => $cents, 'driver' => $driver, 'bus' => $bus, 'category' => $category,
    'reference' => $ref, 'source' => 'fixture', 'description' => 'Test fixture', 'payment' => 'Cash', 'trip' => $trip,
];
$rows = [
    $row('2026-10-01', 'revenue', 10025, 'D1', 'B1', 'Passenger fare', 'fare:1', 'T1'),
    $row('2026-10-01', 'revenue', 5010, 'D1', 'B1', 'Passenger fare', 'fare:2', 'T1'),
    $row('2026-10-05', 'expense', 2005, 'D1', 'B1', 'Fuel', 'expense:1'),
    $row('2026-10-05', 'expense', 1000, '', '', 'Operations', 'expense:2'),
    $row('2026-10-05', 'settlement', 15035, 'D1', 'B1', 'Remittance', 'settlement:1'),
];
$s = Service::aggregate($rows, $f, [['code' => 'D1', 'label' => 'Driver One']], [['code' => 'B1', 'label' => 'Bus One']]);
check($s['totals']['revenue'] === 150.35, 'Fare revenue cents');
check($s['totals']['expenses'] === 30.05, 'Expenses cents');
check($s['totals']['profit'] === 120.3, 'Revenue minus expenses');
check($s['totals']['collections'] === 150.35, 'Settlement shown separately, not double-counted');
check($s['totals']['trips'] === 1, 'Multiple passengers on a trip count once');
check($s['totals']['passengers'] === 2, 'Passenger fare records');
check($s['totals']['fuel'] === 20.05, 'Fuel calculation');
check($s['totals']['operational_costs'] === 10.0, 'Non-fuel operations');
foreach ($s['series'] as $key => $buckets) {
    check(abs(array_sum(array_column($buckets, 'revenue')) - $s['totals']['revenue']) < .00001, "$key revenue reconciliation");
    check(abs(array_sum(array_column($buckets, 'expenses')) - $s['totals']['expenses']) < .00001, "$key expense reconciliation");
}
foreach (['drivers', 'buses'] as $key) {
    check(abs(array_sum(array_column($s['breakdowns'][$key], 'revenue')) - $s['totals']['revenue']) < .00001, "$key revenue reconciliation");
    check(abs(array_sum(array_column($s['breakdowns'][$key], 'expenses')) - $s['totals']['expenses']) < .00001, "$key expenses, including unassigned");
}
check(count($s['series']['daily']) === 11, 'Empty days included, bounds inclusive');
$empty = Service::aggregate([], $f);
check($empty['empty'] && $empty['totals']['margin'] === null, 'Empty state and no division by zero');
$loss = Service::aggregate([$row('2026-10-01', 'expense', 2005, '', '', 'Fuel', 'loss:1')], $f);
check($loss['totals']['profit'] === -20.05, 'Loss is negative, not clamped');
$gas = Service::aggregate([$row('2026-10-01', 'expense', 100, '', '', 'Gas', 'gas:1')], $f);
check((float) $gas['totals']['fuel'] === 1.0, 'Gas ledger category is included in fuel, not duplicated as operations');
$s['previous'] = ['from' => '2026-09-20', 'to' => '2026-09-30', 'totals' => $empty['totals']];
$s['growth'] = ['revenue' => null, 'expenses' => null, 'profit' => null];
$s['notes'] = ['Fixture records for verification only.'];
$s['generated_at'] = '2026-10-11 10:00:00';
$s['insights'] = Service::insights($s);
$s['narrative'] = "Revenue Analysis\n\nFixture financial findings\n\n- Revenue and expenses use the same snapshot.\n\n<script>alert('test')</script>";
$GLOBALS['aiConfig_finReport']['api_key'] = 'fixture-key';
$ai = new class extends \Finance\Services\FinancialReportAIService {
    public array $input = [];
    public string $output = 'Fixture financial findings';
    public function generateAnalysis($financialData, $expenseBreakdown = [], $reportType = 'full', $userDescription = '', array $aggregated = [], $dateFrom = null, $dateTo = null, int $timeout = 180) {
        $this->input = compact('financialData', 'expenseBreakdown', 'reportType', 'userDescription', 'aggregated', 'dateFrom', 'dateTo', 'timeout');
        return $this->output;
    }
};
check($ai->generateSnapshotAnalysis($s, 'risk', 'Review costs') === 'Fixture financial findings', 'Snapshot AI returns successful analysis');
check($ai->input['financialData']['total_revenue'] === $s['totals']['revenue'] && $ai->input['aggregated']['verified_snapshot']['series'] === ['monthly' => $s['series']['monthly']], 'Risk AI receives authoritative totals and only its relevant trends');
check($ai->input['reportType'] === 'risk' && $ai->input['userDescription'] === 'Review costs' && $ai->input['timeout'] === 45, 'AI retains category, instructions and bounded timeout');
$longRange = $s;
$longRange['filters']['from'] = '2026-01-01'; $longRange['filters']['to'] = '2026-12-31';
$longRange['series']['daily'] = array_fill(0, 365, $s['series']['daily'][0]);
$longRange['series']['weekly'] = array_fill(0, 52, $s['series']['weekly'][0]);
$ai->generateSnapshotAnalysis($longRange, 'full', '');
check(!isset($ai->input['aggregated']['verified_snapshot']['series']['daily']) && !isset($ai->input['aggregated']['verified_snapshot']['series']['weekly']) && $ai->input['aggregated']['verified_snapshot']['series']['monthly'] === $s['series']['monthly'], 'Long-range AI input uses compact trends');
check(count($longRange['series']['daily']) === 365 && count($longRange['series']['weekly']) === 52, 'Compact AI input preserves complete saved report series');
$ai->output = 'AI Service Error: Provider timeout';
try { $ai->generateSnapshotAnalysis($s, 'full', ''); check(false, 'Provider failure accepted as narrative'); }
catch (RuntimeException $e) { check(strpos($e->getMessage(), 'retry') !== false, 'Provider failure is actionable and not saved as analysis'); }
$charts = Pdf::charts($s);
check(count($charts) === 5, 'Comprehensive report uses five distinct charts without duplicate daily/weekly revenue plots');
$revenueCharts = Pdf::charts(\Finance\Services\FinancialReportPresentationService::apply($s, 'revenue'));
$donut = array_values(array_filter($revenueCharts, static fn($c) => $c['id'] === 'buses'))[0];
check(abs(array_sum($donut['datasets'][0]['data']) - $s['totals']['revenue']) < .00001, 'Donut reconciles to total revenue');
$html = Pdf::html($s, 'Fixture tester');
check(strpos($html, 'R-Connect Transport Management System') !== false && strpos($html, 'Financial Overview') !== false && strpos($html, '@page{size:A4 portrait;margin:16mm 14mm}') !== false, 'Admin printable PDF header and portrait layout are mirrored');
check(substr_count($html, 'data:image/svg+xml;base64,') === 5, 'Only relevant comprehensive charts are embedded in PDF HTML');
check(strpos($html, 'Fare Collection Summary') !== false && strpos($html, 'Description') !== false, 'PDF includes preview fare collection and full transaction details');
check(strpos($html, '150.35') !== false && strpos($html, '120.30') !== false, 'PDF totals match UI snapshot');
check(strpos($html, 'Fixture financial findings') !== false && strpos($html, '&lt;script&gt;') !== false && strpos($html, '<script>') === false, 'PDF retains and escapes the same snapshot AI narrative');
$bytes = Pdf::pdf($s, 'Fixture tester', 2);
check(substr($bytes, 0, 5) === '%PDF-' && strlen($bytes) > 10000, 'Real Dompdf output with chart images');
$document = (new \Smalot\PdfParser\Parser())->parseContent($bytes);
$text = $document->getText();
foreach (array_column($s['records'], 'reference') as $reference) {
    check(substr_count($text, $reference) === 1, 'Batched PDF retains each record exactly once: ' . $reference);
}
check(strpos($text, 'Fixture financial findings') !== false && strpos($text, '120.30') !== false, 'Batched PDF retains the analysis and immutable totals');
$pages = $document->getPages();
check(strpos(end($pages)->getText(), 'Page ' . count($pages) . ' of ' . count($pages)) !== false, 'Page numbering spans all PDF batches');
$fullPageCount = count($pages);
unset($document, $text, $pages);
$expense = \Finance\Services\FinancialReportPresentationService::apply($s, 'expenses');
check(count($expense['records']) === 2 && count($s['records']) === 5, 'Expense scope filters displayed records without changing the stored snapshot');
check(count(Pdf::charts($expense)) === 1 && Pdf::charts($expense)[0]['id'] === 'expenses', 'Expense report renders only its category chart');
$expenseHtml = Pdf::html($expense, 'Fixture tester');
check(strpos($expenseHtml, 'Total Revenue') === false && strpos($expenseHtml, 'Profit and Loss / Previous Period') === false && strpos($expenseHtml, 'Drivers Financial Summary') === false, 'Expense PDF omits unrelated revenue, profit and driver sections');
$expensePdf = (new \Smalot\PdfParser\Parser())->parseContent(Pdf::pdf($expense, 'Fixture tester', 2));
$expenseText = $expensePdf->getText();
check(strpos($expenseText, 'expense:1') !== false && strpos($expenseText, 'expense:2') !== false && strpos($expenseText, 'fare:1') === false && strpos($expenseText, 'settlement:1') === false, 'Actual expense PDF contains expense records only');
check(count($expensePdf->getPages()) < $fullPageCount, 'Category PDF is shorter than the comprehensive report');
$ai->output = 'Fixture financial findings';
$ai->generateSnapshotAnalysis($s, 'expenses', '');
check(!isset($ai->input['aggregated']['verified_snapshot']['totals']['revenue']) && !isset($ai->input['aggregated']['verified_snapshot']['breakdowns']['drivers']), 'Expense AI omits unrelated totals and rankings');
unset($expensePdf, $expenseText);
foreach (['fleet', 'drivers', 'summary', 'risk', 'routes', 'revenue'] as $category) {
    $view = \Finance\Services\FinancialReportPresentationService::apply($s, $category);
    check(count(Pdf::charts($view)) === count($view['presentation']['charts']), "$category chart set matches its selected category");
    if (in_array($category, ['fleet', 'summary', 'risk', 'routes'], true)) check($view['records'] === [], "$category omits the full transaction appendix");
}
if (isset($argv[1])) file_put_contents($argv[1], $bytes);
if (isset($argv[2])) {
    $s['charts'] = $charts;
    $s['options'] = ['drivers' => [['code' => 'D1', 'label' => 'Driver One']], 'buses' => [['code' => 'B1', 'label' => 'Bus One']]];
    $s = \Finance\Services\FinancialReportPresentationService::apply($s);
    $base = $s;
    foreach (['expenses', 'revenue', 'fleet', 'drivers', 'summary', 'risk', 'routes'] as $category) {
        $view = \Finance\Services\FinancialReportPresentationService::apply($base, $category);
        $view['charts'] = Pdf::charts($view);
        $s['test_category_previews'][$category] = $view;
    }
    file_put_contents($argv[2], json_encode($s, JSON_THROW_ON_ERROR));
}
echo "$checks financial performance checks passed.\n";
