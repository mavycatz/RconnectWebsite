<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/FinancialPerformancePdfService.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$snapshot = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$snapshot['records'] = [];
$count = 2500;
for ($i = 0; $i < $count; $i++) {
    $snapshot['records'][] = [
        'date' => '2026-10-01', 'reference' => sprintf('MEMROW-%06d', $i),
        'driver' => 'D1', 'bus' => 'B1', 'category' => 'Maintenance',
        'kind' => 'expense', 'amount' => 1.25,
        'description' => 'Memory regression fixture — recorded service cost',
    ];
}
$started = microtime(true);
$bytes = \Finance\Services\FinancialPerformancePdfService::pdf($snapshot, 'Memory fixture tester');
gc_collect_cycles();
$pdf = (new \Smalot\PdfParser\Parser())->parseContent($bytes);
$text = $pdf->getText();
preg_match_all('/MEMROW-\s*(\d{6})/', $text, $matches);
if (count($matches[1]) !== $count || count(array_unique($matches[1])) !== $count) throw new RuntimeException('PDF batch boundary lost or duplicated records: found ' . count($matches[1]) . ' markers and ' . count(array_unique($matches[1])) . ' unique records.');
if (!str_contains($text, 'Fixture financial findings') || !str_contains($text, '120.30')) throw new RuntimeException('Summary/analysis did not survive PDF batching.');
$pages = $pdf->getPages();
if (!str_contains(end($pages)->getText(), 'Page ' . count($pages) . ' of ' . count($pages))) throw new RuntimeException('Global PDF page numbering is inconsistent.');
echo sprintf("PDF memory regression passed: %d records, %d pages, %.1f MB peak, %.1f seconds.\n", $count, count($pages), memory_get_peak_usage(true) / 1048576, microtime(true) - $started);
