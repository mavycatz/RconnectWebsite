<?php
declare(strict_types=1);

namespace Finance\Services;

require_once __DIR__ . '/FinancialReportPresentationService.php';

final class FinancialPerformancePdfService
{
    private static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function charts(array $snapshot): array
    {
        $make = static function (string $id, string $title, string $type, array $rows, array $measures, bool $fill = false, bool $stacked = false): array {
            $colors = ['revenue' => '#295738', 'expenses' => '#c4b417', 'profit' => '#295738'];
            $sets = [];
            foreach ($measures as $m) $sets[] = ['label' => ucfirst($m), 'data' => array_column($rows, $m), 'borderColor' => $colors[$m], 'backgroundColor' => $colors[$m], 'fill' => $fill, 'tension' => 0.25];
            return ['id' => $id, 'title' => $title, 'type' => $type, 'labels' => array_column($rows, 'name'), 'datasets' => $sets, 'stacked' => $stacked];
        };
        $s = $snapshot['series']; $b = $snapshot['breakdowns'];
        $result = [
            $make('revenue', 'Revenue trends over time', 'line', $s['daily'], ['revenue']),
            $make('comparison', 'Monthly revenue vs. expenses', 'bar', $s['monthly'], ['revenue', 'expenses']),
            $make('daily', 'Daily revenue area', 'line', $s['daily'], ['revenue'], true),
            $make('weekly', 'Weekly revenue area', 'line', $s['weekly'], ['revenue'], true),
            $make('performance', 'Net profit / loss trend', 'line', $s['monthly'], ['profit']),
            $make('stacked', 'Revenue and expenses per bus (top 10 by revenue)', 'bar', array_slice($b['buses'], 0, 10), ['revenue', 'expenses'], false, true),
            $make('drivers', 'Revenue and expenses per driver (top 10 by revenue)', 'bar', array_slice($b['drivers'], 0, 10), ['revenue', 'expenses'], false, true),
        ];
        $palette = ['#295738', '#c4b417', '#387e92', '#81a779', '#c26c50', '#86749c', '#a2a650', '#607b78', '#c6b79c'];
        foreach ([['expenses', 'Operational expense distribution', 'pie', $b['categories'], 'expenses'], ['buses', 'Revenue contribution per bus', 'doughnut', $b['buses'], 'revenue']] as [$id, $title, $type, $rows, $measure]) {
            $rows = array_values(array_filter($rows, static fn($r) => $r[$measure] > 0));
            usort($rows, static fn($a, $c) => $c[$measure] <=> $a[$measure]);
            $top = array_slice($rows, 0, 8);
            if (count($rows) > 8) $top[] = ['name' => 'Other', $measure => array_sum(array_column(array_slice($rows, 8), $measure))];
            $result[] = ['id' => $id, 'title' => $title, 'type' => $type, 'labels' => array_column($top, 'name'), 'datasets' => [['label' => ucfirst($measure), 'data' => array_column($top, $measure), 'backgroundColor' => $palette]], 'stacked' => false];
        }
        $allowed = $snapshot['presentation']['charts'] ?? FinancialReportPresentationService::apply($snapshot)['presentation']['charts'];
        return array_values(array_filter($result, static fn($chart) => in_array($chart['id'], $allowed, true)));
    }

    public static function text(array $s): string
    {
        $lines = ['Financial Performance Report', $s['filters']['label'], 'Generated: ' . $s['generated_at'], ''];
        foreach ($s['totals'] as $key => $value) {
            $formatted = $value === null ? 'N/A' : (in_array($key, ['passengers', 'trips'], true) ? number_format((float) $value) : ($key === 'margin' ? number_format((float) $value, 2) . '%' : '₱' . number_format((float) $value, 2)));
            $lines[] = ucwords(str_replace('_', ' ', $key)) . ': ' . $formatted;
        }
        foreach ($s['insights'] as $finding) $lines[] = $finding;
        foreach (['drivers', 'buses', 'categories', 'payments'] as $key) {
            $lines[] = ''; $lines[] = strtoupper($key);
            foreach ($s['breakdowns'][$key] as $row) $lines[] = $row['name'] . ' | Revenue ₱' . number_format($row['revenue'], 2) . ' | Expenses ₱' . number_format($row['expenses'], 2) . ' | Net ₱' . number_format($row['profit'], 2);
        }
        foreach ($s['series'] as $bucket => $rows) {
            $lines[] = strtoupper($bucket) . ' SUMMARY';
            foreach ($rows as $row) $lines[] = $row['name'] . ' | Revenue ₱' . number_format($row['revenue'], 2) . ' | Expenses ₱' . number_format($row['expenses'], 2) . ' | Net ₱' . number_format($row['profit'], 2);
        }
        return implode("\n", array_merge($lines, ['', 'DATA BASIS'], $s['notes']));
    }

    private static function chartSvg(array $chart): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="290" viewBox="0 0 720 290"><rect width="720" height="290" fill="white"/>';
        $sets = $chart['datasets']; $labels = $chart['labels']; $n = count($labels);
        if (!$n || !array_filter(array_merge(...array_column($sets, 'data')), static fn($v) => (float) $v !== 0.0)) {
            return $svg . '<text x="360" y="140" text-anchor="middle" font-size="16" fill="#888">No data for this reporting period</text></svg>';
        }
        if (in_array($chart['type'], ['pie', 'doughnut'], true)) {
            $total = array_sum($sets[0]['data']); $angle = -M_PI / 2;
            foreach ($sets[0]['data'] as $i => $value) {
                $next = $angle + $value / $total * 2 * M_PI;
                $color = $sets[0]['backgroundColor'][$i];
                if (abs($value - $total) < 0.00001) $svg .= '<circle cx="160" cy="140" r="100" fill="' . $color . '"/>';
                else $svg .= sprintf('<path d="M 160 140 L %.3f %.3f A 100 100 0 %d 1 %.3f %.3f Z" fill="%s"/>', 160 + cos($angle) * 100, 140 + sin($angle) * 100, ($next - $angle) > M_PI ? 1 : 0, 160 + cos($next) * 100, 140 + sin($next) * 100, $color);
                $y = 28 + $i * 27;
                $svg .= '<rect x="305" y="' . $y . '" width="12" height="12" fill="' . $color . '"/><text x="325" y="' . ($y + 11) . '" font-size="12" fill="#333">' . self::e(mb_strimwidth($labels[$i], 0, 26, '…')) . ' — PHP ' . number_format((float) $value, 2) . ' (' . number_format($value / $total * 100, 1) . '%)</text>';
                $angle = $next;
            }
            if ($chart['type'] === 'doughnut') $svg .= '<circle cx="160" cy="140" r="54" fill="white"/>';
        } else {
            $values = array_merge(...array_column($sets, 'data'));
            if ($chart['stacked']) for ($i = 0; $i < $n; $i++) {
                $values[] = array_sum(array_map(static fn($set) => max(0, $set['data'][$i]), $sets));
                $values[] = array_sum(array_map(static fn($set) => min(0, $set['data'][$i]), $sets));
            }
            $min = min(0, min($values)); $max = max(1, max($values)); $span = $max - $min;
            $y = static fn($v) => 225 - (($v - $min) / $span * 185);
            $zero = $y(0);
            for ($i = 0; $i <= 4; $i++) {
                $v = $min + $span * $i / 4; $yy = $y($v);
                $svg .= '<line x1="85" y1="' . $yy . '" x2="695" y2="' . $yy . '" stroke="#e3eabb"/><text x="78" y="' . ($yy + 4) . '" text-anchor="end" font-size="10" fill="#777">' . number_format($v, 0) . '</text>';
            }
            foreach ($sets as $j => $set) {
                $color = $set['borderColor']; $points = [];
                foreach ($set['data'] as $i => $value) {
                    $x = 90 + ($i + 0.5) / $n * 595;
                    if ($chart['type'] === 'bar') {
                        $width = min(30, 520 / $n / ($chart['stacked'] ? 1 : count($sets)));
                        $bottom = $chart['stacked'] ? array_sum(array_map(static fn($v) => ($value >= 0 && $v['data'][$i] >= 0) || ($value < 0 && $v['data'][$i] < 0) ? $v['data'][$i] : 0, array_slice($sets, 0, $j))) : 0;
                        $xx = $x - $width / 2 + ($chart['stacked'] ? 0 : ($j - (count($sets) - 1) / 2) * $width);
                        $svg .= '<rect x="' . $xx . '" y="' . min($y($bottom + $value), $y($bottom)) . '" width="' . $width . '" height="' . max(0.5, abs($y($bottom + $value) - $y($bottom))) . '" fill="' . $color . '"/>';
                    } else $points[] = $x . ',' . $y($value);
                }
                if ($points) {
                    if (!empty($set['fill'])) $svg .= '<polygon points="' . explode(',', $points[0])[0] . ',' . $zero . ' ' . implode(' ', $points) . ' ' . explode(',', $points[count($points) - 1])[0] . ',' . $zero . '" fill="' . $color . '" fill-opacity="0.16"/>';
                    $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
                    if ($n === 1) $svg .= '<circle cx="' . explode(',', $points[0])[0] . '" cy="' . $y($set['data'][0]) . '" r="4" fill="' . $color . '"/>';
                }
                $svg .= '<rect x="' . (90 + $j * 140) . '" y="12" width="12" height="12" fill="' . $color . '"/><text x="' . (107 + $j * 140) . '" y="23" font-size="12">' . self::e($set['label']) . '</text>';
            }
            $stride = max(1, (int) ceil($n / 6));
            foreach ($labels as $i => $label) if ($i % $stride === 0) $svg .= '<text x="' . (90 + ($i + 0.5) / $n * 595) . '" y="250" text-anchor="middle" font-size="10" fill="#777">' . self::e(mb_strimwidth($label, 0, 16, '…')) . '</text>';
            $svg .= '<text x="90" y="275" font-size="10" fill="#777">Philippine Peso (PHP)</text>';
        }
        return $svg . '</svg>';
    }

    private static function document(array $s, string $actor, string $body, bool $includeHeader = true): string
    {
        // Match printAiReport() in Admin Driver Performance Analytics. Table
        // layout replaces its flex/grid constructs for Dompdf compatibility.
        $styles = <<<'CSS'
@page{size:A4 portrait;margin:16mm 14mm}
body{font-family:DejaVu Sans,sans-serif;color:#2e4836;padding:0;margin:0;line-height:1.55;font-size:12px}
.report-head{width:100%;table-layout:fixed;border-collapse:collapse;border-bottom:2px solid #295738;margin:0 0 20px}
.report-head td{border:0;background:transparent;padding:0 0 14px;vertical-align:top}
.rh-left{width:66%;padding-right:24px!important}.rh-right{width:34%;text-align:right}
.rh-system{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8a9a8a;margin-bottom:4px}
.rh-title{font-size:22px;font-weight:bold;color:#295738;margin:0 0 4px;line-height:1.2}
.rh-category{font-size:13px;font-weight:bold;color:#3a4a3c}.rh-snapshot{font-size:10px;color:#8a9a8a;margin-top:4px}
.rh-row{margin-bottom:6px}.rh-label{display:block;font-size:9px;letter-spacing:.06em;text-transform:uppercase;color:#8a9a8a}.rh-value{font-size:12px;font-weight:bold;color:#3a4a3c}
.section{margin-bottom:24px}.section-title{font-size:12px;font-weight:bold;text-transform:uppercase;color:#295738;border-bottom:1px solid #ece6cf;margin:0 0 12px;padding-bottom:6px;page-break-after:avoid}
.overview-grid{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:8px;margin:0 -8px 0}
.overview-grid td{border:1px solid #e5e0a4;border-radius:10px;padding:12px 14px;vertical-align:top;page-break-inside:avoid}
.overview-grid td.overview-empty{border:0;padding:0}.ov-label{font-size:10px;color:#6f7d6f;text-transform:uppercase;margin-bottom:3px}.ov-value{font-weight:bold;font-size:16px;color:#295738;line-height:1.25;word-wrap:break-word}
table{width:100%;border-collapse:collapse;font-size:12px}thead{display:table-header-group}
th,td{border-bottom:1px solid #f0ede0;padding:8px 10px;text-align:left;vertical-align:top;word-wrap:break-word}
th{background:#f6f8ec;text-transform:uppercase;font-size:10px;letter-spacing:.03em;color:#2e4836}
.num{text-align:right;white-space:nowrap}tr{page-break-inside:avoid}
.comparison-note{font-size:11px;color:#8a9a8a;margin-bottom:8px}
.fp-chart{page-break-inside:avoid;margin:0 0 24px}.fp-chart img{display:block;width:100%;height:auto;max-height:320px;margin:0 auto}
.fp-analysis{white-space:normal;text-align:left;line-height:1.65}.fp-analysis p{font-size:13px;color:#3a4a3c;margin:0 0 14px;text-align:left}
.fp-analysis h4{font-size:11px;font-weight:bold;text-transform:uppercase;color:#7a6f4f;margin:14px 0 4px;page-break-after:avoid}
CSS;
        $header = '';
        if ($includeHeader) {
            $labels = ['full' => 'Comprehensive', 'revenue' => 'Revenue Analysis', 'expenses' => 'Operating Expenses', 'fleet' => 'Fleet Performance', 'drivers' => 'Driver Performance', 'routes' => 'Route Performance', 'summary' => 'Financial Summary', 'risk' => 'Risk Assessment'];
            $category = $labels[$s['report_type']] ?? 'Financial Report';
            $title = $s['title'] ?? $category . ' Report';
            $scope = [];
            foreach (['driver' => 'Driver', 'bus' => 'Bus'] as $key => $label) if ($s['filters'][$key] !== '') $scope[] = $label . ': ' . $s['filters'][$key];
            $header = '<table class="report-head"><tr><td class="rh-left"><div class="rh-system">R-Connect Transport Management System</div><h1 class="rh-title">' . self::e($title) . '</h1><div class="rh-category">Finance Department · ' . self::e($category) . '</div>';
            if ($scope) $header .= '<div class="rh-snapshot">' . self::e(implode(' · ', $scope)) . '</div>';
            $header .= '<div class="rh-snapshot">Data snapshot: ' . self::e($s['generated_at']) . ' (Philippine time)</div></td><td class="rh-right"><div class="rh-row"><span class="rh-label">Period</span><span class="rh-value">' . self::e($s['filters']['label']) . '</span></div><div class="rh-row"><span class="rh-label">Generated</span><span class="rh-value">' . self::e($actor . ' · ' . date('F j, Y g:i A')) . '</span></div></td></tr></table>';
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . self::e($s['title'] ?? 'Financial Report') . '</title><style>' . $styles . '</style></head><body>' . $header . $body . '</body></html>';
    }

    private static function analysisHtml(array $s): string
    {
        if (empty($s['narrative'])) return '';
        $body = '<div class="section"><div class="section-title">AI Financial Analysis</div><div class="fp-analysis">';
        foreach (preg_split('/\n\s*\n/', trim($s['narrative'])) as $paragraph) {
            $text = trim($paragraph);
            $heading = strlen($text) < 90 && !str_contains($text, "\n") && preg_match('/^(?:executive|revenue|expense|operating|profit|fleet|driver|route|risk|recommendation|conclusion|financial|cost|key|summary|cash)/i', $text) && !preg_match('/[.!?₱]/u', $text);
            $body .= $heading ? '<h4>' . self::e($text) . '</h4>' : '<p>' . nl2br(self::e($text)) . '</p>';
        }
        return $body . '</div></div>';
    }

    private static function notesHtml(array $s): string
    {
        $body = '<div class="section"><div class="section-title">Data Basis</div><ul>';
        foreach ($s['notes'] as $note) $body .= '<li>' . self::e($note) . '</li>';
        return $body . '</ul></div>';
    }

    public static function html(array $s, string $actor, bool $includeTables = true, bool $includeAnalysis = true): string
    {
        $s = FinancialReportPresentationService::apply($s);
        $presentation = $s['presentation'];
        $money = static fn($v) => '₱' . number_format((float) $v, 2);
        $t = $s['totals'];
        $body = '';
        $cards = [['Total Revenue', $money($t['revenue'])], ['Total Expenses', $money($t['expenses'])], ['Net Profit / Loss', $money($t['profit'])], ['Fuel Expenses', $money($t['fuel'])], ['Operational Costs', $money($t['operational_costs'])], ['Passenger Revenue', $money($t['passenger_revenue'])], ['Trips with Collections', number_format($t['trips'])], ['Revenue Growth', $s['growth']['revenue'] === null ? 'No comparison base' : $s['growth']['revenue'] . '%']];
        if ($presentation['cards']) {
            $columns = min(4, count($presentation['cards']));
            $body .= '<div class="section"><div class="section-title">Financial Overview</div><table class="overview-grid"><tbody>';
            foreach ($presentation['cards'] as $position => $index) {
                if ($position % $columns === 0) $body .= '<tr>';
                $body .= '<td><div class="ov-label">' . self::e($cards[$index][0]) . '</div><div class="ov-value">' . self::e($cards[$index][1]) . '</div></td>';
                if (($position + 1) % $columns === 0) $body .= '</tr>';
            }
            $remaining = count($presentation['cards']) % $columns;
            if ($remaining) {
                for ($i = $remaining; $i < $columns; $i++) $body .= '<td class="overview-empty"></td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        } else {
            $body .= '<div class="section"><div class="section-title">Financial Overview</div><p>' . self::e(implode(' ', $s['insights'])) . '</p></div>';
        }
        if ($s['report_type'] === 'full') $body .= '<div class="section fp-short-section"><div class="section-title">Fare Collection Summary</div><table><thead><tr><th>Passenger Revenue</th><th>Passenger Fare Records</th><th>Trips with Collections</th><th>Remitted Cash (Settlement)</th></tr></thead><tbody><tr><td>' . $money($t['passenger_revenue']) . '</td><td>' . number_format($t['passengers']) . '</td><td>' . number_format($t['trips']) . '</td><td>' . $money($t['collections']) . '</td></tr></tbody></table><p>Remittances are settlement records, not additional revenue.</p></div>';
        if ($presentation['profit']) {
        $body .= '<div class="section fp-short-section"><div class="section-title">Profit and Loss / Previous Period</div><table><thead><tr><th>Measure</th><th class="num">Current</th><th class="num">Previous</th><th class="num">Growth</th></tr></thead><tbody>';
        foreach (['revenue', 'expenses', 'profit'] as $key) $body .= '<tr><td>' . ucfirst($key) . '</td><td class="num">' . $money($t[$key]) . '</td><td class="num">' . $money($s['previous']['totals'][$key]) . '</td><td class="num">' . ($s['growth'][$key] === null ? 'N/A — no comparison base' : number_format($s['growth'][$key], 1) . '%') . '</td></tr>';
        $body .= '</tbody></table><p>Comparison: ' . self::e($s['previous']['from'] . ' – ' . $s['previous']['to']) . '</p></div>';
        }
        foreach (self::charts($s) as $chart) $body .= '<div class="fp-chart"><div class="section-title">' . self::e($chart['title']) . '</div><img alt="' . self::e($chart['title']) . '" src="data:image/svg+xml;base64,' . base64_encode(self::chartSvg($chart)) . '"></div>';
        if ($includeTables) {
        foreach (array_merge($s['series'], $s['breakdowns']) as $label => $rows) {
            if (!in_array($label, $presentation['tables'], true)) continue;
            $body .= '<div class="section' . (count($rows) <= 8 ? ' fp-short-section' : '') . '"><div class="section-title">' . self::e(ucfirst($label)) . ' Financial Summary</div><table><thead><tr><th>Period / Name</th>';
            foreach ($presentation['metrics'] as $metric) $body .= '<th class="num">' . self::e(ucfirst($metric)) . '</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $body .= '<tr><td>' . self::e($row['name']) . '</td>';
                foreach ($presentation['metrics'] as $metric) $body .= '<td class="num">' . $money($row[$metric]) . '</td>';
                $body .= '</tr>';
            }
            if (!$rows) $body .= '<tr><td colspan="' . (count($presentation['metrics']) + 1) . '">No records for the selected filters.</td></tr>';
            $body .= '</tbody></table></div>';
        }
        if ($presentation['records']) {
        $body .= '<div class="section"><div class="section-title">Detailed Financial Records</div><table style="table-layout:fixed;"><thead><tr><th>Date / Reference</th><th>Driver / Bus</th><th>Category</th><th>Type</th><th class="num">Amount</th><th>Description</th></tr></thead><tbody>';
        foreach ($s['records'] as $row) $body .= '<tr><td>' . self::e($row['date'] . ' / ' . $row['reference']) . '</td><td>' . self::e(($row['driver'] ?: 'Unassigned') . ' / ' . ($row['bus'] ?: 'Unassigned')) . '</td><td>' . self::e($row['category']) . '</td><td>' . self::e($row['kind']) . '</td><td class="num">' . $money($row['amount']) . '</td><td>' . self::e($row['description'] ?? '') . '</td></tr>';
        if (!$s['records']) $body .= '<tr><td colspan="6">No financial records.</td></tr>';
        $body .= '</tbody></table></div>';
        }
        }
        if ($includeAnalysis) $body .= self::analysisHtml($s) . self::notesHtml($s);
        return self::document($s, $actor, $body);
    }

    private static function tableDocuments(array $s, string $actor, int $batchSize): \Generator
    {
        $money = static fn($value) => '₱' . number_format((float) $value, 2);
        $presentation = $s['presentation'];
        foreach (array_merge($s['series'], $s['breakdowns'], ['records' => $s['records']]) as $label => $rows) {
            $records = $label === 'records';
            if ($records ? !$presentation['records'] : !in_array($label, $presentation['tables'], true)) continue;
            $title = $records ? 'Detailed Financial Records' : ucfirst($label) . ' Financial Summary';
            $headers = $records
                ? '<th>Date / Reference</th><th>Driver / Bus</th><th>Category</th><th>Type</th><th class="num">Amount</th><th>Description</th>'
                : '<th>Period / Name</th>' . implode('', array_map(static fn($metric) => '<th class="num">' . ucfirst($metric) . '</th>', $presentation['metrics']));
            $total = count($rows);
            $offset = 0;
            do {
                $start = $offset;
                $body = '<style>thead{display:table-header-group}tr{page-break-inside:avoid}td{word-wrap:break-word}.section-title{page-break-after:avoid}</style>';
                $body .= '<div class="section-title">' . self::e($title) . '</div><table style="table-layout:fixed"><thead><tr>' . $headers . '</tr></thead><tbody>';
                while ($offset < $total && $offset - $start < $batchSize && strlen($body) < 131072) {
                    $row = $rows[$offset++];
                    if ($records) {
                        $body .= '<tr><td>' . self::e($row['date'] . ' / ' . $row['reference']) . '</td><td>' . self::e(($row['driver'] ?: 'Unassigned') . ' / ' . ($row['bus'] ?: 'Unassigned')) . '</td><td>' . self::e($row['category']) . '</td><td>' . self::e($row['kind']) . '</td><td class="num">' . $money($row['amount']) . '</td><td>' . self::e($row['description'] ?? '') . '</td></tr>';
                    } else {
                        $body .= '<tr><td>' . self::e($row['name']) . '</td>';
                        foreach ($presentation['metrics'] as $metric) $body .= '<td class="num">' . $money($row[$metric]) . '</td>';
                        $body .= '</tr>';
                    }
                }
                if (!$total) $body .= '<tr><td colspan="' . ($records ? 6 : count($presentation['metrics']) + 1) . '">No records for the selected filters.</td></tr>';
                $body .= '</tbody></table>';
                // Reuse the Admin print typography without repeating the
                // title and metadata header on every table batch.
                yield self::document($s, $actor, $body, false);
            } while ($offset < $total);
        }
    }

    public static function pdf(array $s, string $actor, int $batchSize = 100): string
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $s = FinancialReportPresentationService::apply($s);
        if ($batchSize < 1 || $batchSize > 100) throw new \InvalidArgumentException('PDF batches must contain between 1 and 100 rows.');
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('pdfBackend', 'CPDF');
        $hasTables = (bool) $s['presentation']['tables'] || $s['presentation']['records'];
        $dompdf = new \Dompdf\Dompdf($options);
        // Render overview/charts first, then bounded tables and finally the
        // AI analysis, matching the Admin printed report's section order.
        $dompdf->loadHtml(self::html($s, $actor, false, !$hasTables), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $documents = (static function () use ($s, $actor, $batchSize, $hasTables): \Generator {
            if (!$hasTables) return;
            yield from self::tableDocuments($s, $actor, $batchSize);
            yield self::document($s, $actor, self::analysisHtml($s) . self::notesHtml($s), false);
        })();
        foreach ($documents as $html) {
            $canvas->new_page();
            $batch = new \Dompdf\Dompdf($options);
            $batch->setCanvas($canvas);
            $batch->getFontMetrics()->setCanvas($canvas);
            $batch->loadHtml($html, 'UTF-8');
            $batch->render();
            unset($batch, $html);
            gc_collect_cycles();
        }
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.54, 0.60, 0.54]);
        return $canvas->output();
    }
}
