<?php declare(strict_types=1);

namespace Admin\Services;

/**
 * Optional, strictly grounded AI narrative for generated reports.
 *
 * The prompt receives only computed figures and a small sample of the filtered
 * records; the strict rules forbid inventing numbers, causes, or targets.
 * Any failure returns null so report generation continues with the computed
 * KPIs, tables, totals, and findings only.
 */
class AIReportService
{
    private string $apiKey = '';
    private string $model = '';
    private string $apiUrl = '';

    public function __construct()
    {
        $aiConfig = require $_SERVER['DOCUMENT_ROOT'] . '/finance/config/ai.php';
        if (!empty($aiConfig['api_key'])) {
            $this->apiKey = (string)$aiConfig['api_key'];
            $this->model = (string)$aiConfig['model'];
            $this->apiUrl = (string)$aiConfig['api_url'];
        } else {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/config/Env.php';
            $this->apiKey = (string)\Finance\Config\Env::get('FINANCE_GROQ_KEY', '');
            $this->model = 'llama-3.3-70b-versatile';
            $this->apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
            error_log('AIReportService: FINANCE_OPENAI_KEY not set, falling back to FINANCE_GROQ_KEY');
        }
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * Returns ['summary'=>string,'insights'=>[],'recommendations'=>[]] or null.
     */
    public function generateNarrative(array $report): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $sampleRows = array_slice($report['records'] ?? [], 0, 20);

        $payload = [
            'report_type' => (string)($report['type_label'] ?? ''),
            'period' => (string)($report['period']['label'] ?? 'All records'),
            'applied_filters' => array_map(
                static fn ($f) => (string)($f['label'] ?? '') . ': ' . (string)($f['value'] ?? ''),
                $report['applied_filters'] ?? []
            ),
            'record_count' => (int)($report['record_count'] ?? 0),
            'kpis' => array_map(
                static fn ($k) => (string)($k['label'] ?? '') . ': ' . (string)($k['value'] ?? '') . (trim((string)($k['hint'] ?? '')) !== '' ? ' (' . $k['hint'] . ')' : ''),
                $report['kpis'] ?? []
            ),
            'totals' => array_map(
                static fn ($t) => (string)($t['label'] ?? '') . ': ' . (string)($t['value'] ?? ''),
                $report['totals'] ?? []
            ),
            'comparison' => $report['comparison'] ?? null,
            'distributions' => $report['distributions'] ?? [],
            'findings' => $report['findings'] ?? [],
            'sample_records' => $sampleRows,
        ];

        $prompt = "You are a transport operations analyst for a Philippine public transport management system, writing for an ADMIN.

CRITICAL RULES:
- Base every statement ONLY on the verified figures, findings, and sample records below. Do NOT invent numbers.
- If a measure is missing or undetermined in the data, say it is not recorded instead of guessing.
- Do NOT invent causes, reasons, or operational explanations that are not explicitly present in the data.
- Do NOT set arbitrary numeric targets unless a target is supplied in the data.
- Do not mention database terms, raw column names, or IDs.
- Return ONLY a valid JSON object with exactly these keys:
{\"summary\":\"\",\"insights\":[],\"recommendations\":[]}
- summary: 2-3 sentences describing what the selected report, period, and filters show.
- insights: up to 5 factual observations grounded in the figures above; each names the figures or records behind it (max 2 sentences each).
- recommendations: up to 3 practical, data-supported suggestions.
- No markdown, no code fences, no extra keys.

Verified report data:

" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response = $this->sendRequest($prompt);
        if ($response === null) {
            return null;
        }

        return $this->processResponse($response);
    }

    private function sendRequest(string $prompt): ?string
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a transportation operations analyst. Return ONLY valid JSON. Never invent figures.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            error_log('AIReportService: request failed: ' . $error);
            return null;
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            error_log('AIReportService: empty response');
            return null;
        }

        return $content;
    }

    private function processResponse(string $content): ?array
    {
        $content = preg_replace('/```json|```/', '', trim($content)) ?? trim($content);

        $decoded = json_decode($content, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
        }
        if (!is_array($decoded)) {
            error_log('AIReportService: invalid JSON response');
            return null;
        }

        $clean = static function ($text): string {
            return trim(preg_replace('/[*_`]/', '', (string)$text) ?? (string)$text);
        };

        $summary = $clean($decoded['summary'] ?? '');
        $insights = array_values(array_filter(array_map($clean, is_array($decoded['insights'] ?? null) ? $decoded['insights'] : []), static fn ($x) => $x !== ''));
        $recommendations = array_values(array_filter(array_map($clean, is_array($decoded['recommendations'] ?? null) ? $decoded['recommendations'] : []), static fn ($x) => $x !== ''));

        if ($summary === '' && !$insights && !$recommendations) {
            return null;
        }

        return [
            'summary' => $summary,
            'insights' => $insights,
            'recommendations' => $recommendations,
        ];
    }
}
