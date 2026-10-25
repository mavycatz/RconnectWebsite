<?php declare(strict_types=1);

namespace Admin\Controllers;

/*
|--------------------------------------------------------------------------
| REQUIRE SERVICES AND REPORT BUILDER
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/AIReportService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/PDFService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/reports/report_builder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/ReportCenterPresentationService.php';

use Admin\Services\AIReportService;
use Admin\Services\PDFService;
use Admin\Services\ReportCenterPresentationService;

class GenerateReportController
{
    private AIReportService $ai;
    private PDFService $pdf;

    public function __construct()
    {
        $this->ai = new AIReportService();
        $this->pdf = new PDFService();
    }

    /**
     * Build the filtered report, attach the optional grounded AI narrative,
     * render the PDF, and return the result payload.
     */
    public function generate(
        string $reportType,
        array $input = [],
        ?array $actor = null
    ): array {
        $report = rgen_build_report($reportType, $input, $actor, false);

        $narrative = null;
        try {
            $narrative = $this->ai->generateNarrative($report);
        } catch (\Throwable $e) {
            $narrative = null;
        }
        if (is_array($narrative)) {
            $report['narrative'] = $narrative;
        }

        $report = ReportCenterPresentationService::prepare($report);

        $tables = [[
            'title' => 'Detailed Records',
            'columns' => $report['columns'],
            'rows' => $report['records'],
        ]];
        foreach ($report['extra_tables'] ?? [] as $table) {
            $tables[] = $table;
        }

        $period = $report['period'] ?? null;
        $pdfPayload = [
            'report_center' => true,
            'charts' => $report['charts'],
            'data_basis' => $report['data_basis'],
            'category' => $report['category'],
            'description' => $report['description'],
            'source_of_truth' => 'Computed from the filtered database records',
            'meta' => [
                'type_label' => $report['type_label'],
                'period_label' => $period ? (string)$period['label'] : 'All records',
                'range_label' => $period ? ($period['start_date'] . ' to ' . $period['end_date']) : '',
                'generated_at' => rgen_datetime($report['generated_at']) . ' (PHT)',
                'generated_by' => $report['generated_by'],
                'record_count' => $report['record_count'],
                'filters' => $report['applied_filters'],
            ],
            'kpis' => $report['kpis'],
            'totals' => $report['totals'],
            'comparison' => $report['comparison'],
            'tables' => $tables,
            'distributions' => $report['distributions'],
            'trends' => $report['trends'],
            'findings' => $report['findings'],
            'narrative' => $report['narrative'],
        ];

        $pdfUrl = $this->pdf->generate($report['title'], $pdfPayload);

        return [
            'report_type' => $reportType,
            'title' => $report['title'],
            'category' => $report['category'],
            'generated_at' => $report['generated_at'],
            'record_count' => $report['record_count'],
            'applied_filters' => $report['applied_filters'],
            'kpis' => $report['kpis'],
            'findings' => $report['findings'],
            'has_ai_narrative' => is_array($report['narrative']),
            'raw_report' => [
                'report_type' => $reportType,
                'total_records' => $report['record_count'],
                'generated_at' => $report['generated_at'],
                'records' => $report['records'],
            ],
            'ai_report' => is_array($report['narrative']) ? $report['narrative'] : [],
            'pdf_url' => $pdfUrl,
            'preview_report' => ReportCenterPresentationService::preview($report),
        ];
    }
}
