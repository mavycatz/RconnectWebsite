<?php declare(strict_types=1);

namespace Admin\Services;

/** Category-scoped presentation of the existing admin report, never new queries. */
final class ReportCenterPresentationService
{
    private const COLORS = ['#295738', '#c4b417', '#387e92', '#81a779', '#c26c50', '#86749c'];

    private static function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function prepare(array $report): array
    {
        $charts = [];
        foreach ($report['distributions'] ?? [] as $group) {
            $counts = $group['counts'] ?? [];
            arsort($counts);
            $top = array_slice($counts, 0, 8, true);
            $labels = array_map('strval', array_keys($top));
            $values = array_values($top);
            if (count($counts) > 8) {
                $labels[] = 'Other categories';
                $values[] = array_sum(array_slice($counts, 8, null, true));
            }
            if ($values) $charts[] = self::chart((string)$group['label'], 'bar', $labels, $values, 'Records');
        }
        foreach ($report['trends'] ?? [] as $trend) {
            $points = $trend['points'] ?? [];
            ksort($points);
            if (!$points) continue;
            $charts[] = self::chart('Record activity over time', 'line', array_keys($points), array_column($points, 'count'), 'Records');
            if (!empty($trend['measure_label'])) {
                $charts[] = self::chart((string)$trend['measure_label'] . ' over time', 'line', array_keys($points), array_column($points, 'sum'), 'PHP');
            }
        }
        if (($report['type'] ?? '') === 'driver_performance' && !empty($report['records'])) {
            $rows = $report['records'];
            $charts[] = self::chart('Daily fare performance', 'line', array_column($rows, 'day'), array_column($rows, 'fare'), 'PHP');
            $charts[] = self::chart('Daily passenger activity', 'bar', array_column($rows, 'day'), array_column($rows, 'passengers'), 'Passengers');
        }
        // Registry types without a date or status breakdown still have real
        // numeric evidence (for example route fares or assignment counts).
        if (!$charts && !empty($report['records'])) {
            $columns = $report['columns'] ?? [];
            $numeric = array_values(array_filter($columns, static fn($c) => in_array($c['type'] ?? '', ['money', 'int'], true)));
            $names = array_values(array_filter($columns, static fn($c) => ($c['type'] ?? '') === 'text'));
            if ($numeric) {
                $measure = $numeric[0];
                $rows = $report['records'];
                usort($rows, static fn($a, $b) => (float)($b[$measure['key']] ?? 0) <=> (float)($a[$measure['key']] ?? 0));
                $rows = array_slice($rows, 0, 8);
                $labels = [];
                foreach ($rows as $i => $row) $labels[] = (string)($row[$names[0]['key'] ?? ''] ?? ('Record ' . ($i + 1)));
                $charts[] = self::chart('Top records by ' . $measure['label'], 'bar', $labels, array_map(static fn($r) => (float)($r[$measure['key']] ?? 0), $rows), ($measure['type'] ?? '') === 'money' ? 'PHP' : (string)$measure['label']);
            }
        }
        $report['charts'] = $charts;
        $report['data_basis'] = [
            'Metrics, breakdowns, and charts are computed from all records matching this admin report type and its applied filters, before preview limits.',
            'Dates use Asia/Manila (Philippine time). Daily charts show dates with recorded activity; missing dates are not invented.',
            'Detailed records and supplementary tables are the supporting evidence. File references are displayed as recorded; attachment contents are not independently verified.',
            'Prior-period comparisons retain the same non-date filters. A zero prior-period base has no percentage comparison.',
        ];
        if (!$charts) $report['data_basis'][] = 'This report has no applicable numeric or categorical series; its source records are presented instead of a fabricated chart.';
        return $report;
    }

    public static function preview(array $report, int $limit = 100): array
    {
        $report['preview'] = ['limit' => $limit, 'shown' => min(count($report['records']), $limit), 'total' => $report['record_count'], 'truncated' => count($report['records']) > $limit];
        $report['records'] = array_slice($report['records'], 0, $limit);
        foreach ($report['extra_tables'] ?? [] as $i => $table) {
            $report['extra_tables'][$i]['total'] = count($table['rows'] ?? []);
            $report['extra_tables'][$i]['rows'] = array_slice($table['rows'] ?? [], 0, $limit);
        }
        unset($report['metrics']);
        return $report;
    }

    private static function chart(string $title, string $type, array $labels, array $values, string $unit): array
    {
        $values = array_map(static fn($v) => is_numeric($v) && is_finite((float)$v) ? (float)$v : 0.0, $values);
        $labels = array_values($labels);
        foreach ($values as $i => $value) $labels[$i] = (string)($labels[$i] ?? ('Record ' . ($i + 1)));
        $labels = array_slice($labels, 0, count($values));
        $chart = compact('title', 'type', 'labels', 'values', 'unit');
        $chart['svg'] = self::svg($chart);
        return $chart;
    }

    /** Like the finance PDF diagrams: self-contained SVG, no external renderer. */
    private static function svg(array $chart): string
    {
        $labels = $chart['labels']; $values = $chart['values']; $n = count($values);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 290" width="720" height="290" role="img"><title>' . self::e($chart['title']) . '</title><rect width="720" height="290" fill="#fff"/>';
        if (!$n) return $svg . '</svg>';
        $min = min(0, min($values)); $max = max(1, max($values)); $span = $max - $min;
        $y = static fn($v) => 225 - (($v - $min) / $span * 185);
        for ($i = 0; $i <= 4; $i++) {
            $value = $min + $span * $i / 4; $yy = $y($value);
            $svg .= '<line x1="85" y1="' . $yy . '" x2="700" y2="' . $yy . '" stroke="#e3eabb"/><text x="78" y="' . ($yy + 4) . '" text-anchor="end" font-size="11" fill="#6b786d">' . self::e(number_format($value, $chart['unit'] === 'PHP' ? 2 : 0)) . '</text>';
        }
        $points = [];
        foreach ($values as $i => $value) {
            $x = 90 + ($i + .5) / $n * 600;
            if ($chart['type'] === 'bar') {
                $w = min(42, 500 / $n);
                $svg .= '<rect x="' . ($x - $w / 2) . '" y="' . min($y($value), $y(0)) . '" width="' . $w . '" height="' . max(.5, abs($y($value) - $y(0))) . '" fill="' . self::COLORS[$i % count(self::COLORS)] . '"><title>' . self::e($labels[$i] . ': ' . $value . ' ' . $chart['unit']) . '</title></rect>';
            } else {
                $points[] = $x . ',' . $y($value);
                $svg .= '<circle cx="' . $x . '" cy="' . $y($value) . '" r="3" fill="#295738"><title>' . self::e($labels[$i] . ': ' . $value . ' ' . $chart['unit']) . '</title></circle>';
            }
            if ($i % max(1, (int)ceil($n / 6)) === 0) {
                $label = function_exists('mb_strimwidth') ? mb_strimwidth((string)$labels[$i], 0, 18, '…', 'UTF-8') : substr((string)$labels[$i], 0, 18);
                $svg .= '<text x="' . $x . '" y="250" text-anchor="middle" font-size="10" fill="#6b786d">' . self::e($label) . '</text>';
            }
        }
        if ($points) $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="#295738" stroke-width="2"/>';
        return $svg . '<text x="85" y="278" font-size="11" fill="#6b786d">' . self::e($chart['unit']) . '</text></svg>';
    }

    public static function pdfCharts(array $charts): string
    {
        $html = '';
        foreach ($charts as $chart) {
            $html .= '<div class="section report-chart"><div class="section-title">' . self::e($chart['title']) . '</div><img style="width:100%;height:auto;" src="data:image/svg+xml;base64,' . base64_encode($chart['svg']) . '" /></div>';
        }
        return $html;
    }

    public static function document(string $title, array $report, string $body): string
    {
        $meta = $report['meta'] ?? [];
        $style = '@page{size:A4 portrait;margin:16mm 14mm}body{font-family:DejaVu Sans,sans-serif;color:#2e4836;font-size:11px;line-height:1.55}.report-head{border-bottom:2px solid #295738;padding-bottom:14px;margin-bottom:20px}.report-system{font-size:9px;text-transform:uppercase;color:#8a9a8a}h1{font-size:22px;color:#295738;margin:4px 0}.section{margin-bottom:20px}.section-title{font-size:11px;font-weight:bold;text-transform:uppercase;color:#295738;border-bottom:1px solid #ece6cf;padding-bottom:6px;margin-bottom:10px;page-break-after:avoid}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9px}thead{display:table-header-group}th,td{padding:6px;border-bottom:1px solid #f0ede0;text-align:left;vertical-align:top;word-wrap:break-word}th{background:#f6f8ec;text-transform:uppercase;color:#295738}tr{page-break-inside:avoid}.stats-card{display:inline-block;vertical-align:top;box-sizing:border-box;border:1px solid #e5e0a4;border-radius:10px;padding:10px;background:#eef5e7}.stats-title{font-size:9px;text-transform:uppercase;color:#6b786d}.stats-value{font-size:16px;font-weight:bold;color:#295738}.report-chart{page-break-inside:avoid}.green{color:#295738}.red{color:#b94a48}';
        return '<!doctype html><html><head><meta charset="UTF-8"><title>' . self::e($title) . '</title><style>' . $style . '</style></head><body><div class="report-head"><div class="report-system">R-Connect Transport Management System · Admin Reports</div><h1>' . self::e($title) . '</h1><div>' . self::e($meta['period_label'] ?? 'All records') . ' · ' . self::e($meta['range_label'] ?? '') . '</div><div>Generated: ' . self::e($meta['generated_at'] ?? '') . ' · ' . self::e($meta['generated_by'] ?? '') . '</div></div>' . $body . '</body></html>';
    }
}
