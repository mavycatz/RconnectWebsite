<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

/**
 * CV/resume text extraction and AI-assisted structuring.
 *
 * Text extraction is local (PDF via the installed PDF parser, DOCX via the
 * document XML). Structuring into education/experience/skills uses the
 * existing OpenAI-compatible configuration and is optional: any failure
 * returns the extracted text with a warning so the user can fill fields
 * manually, never fabricated values.
 */
class CvExtractionService
{
    private const MAX_AI_CHARS = 15000;
    private const MAX_TEXT_CHARS = 200000;

    /**
     * @return array{text:string,education:array,experience:array,skills:array,warning:?string}
     */
    public function run(string $absolutePath, string $extension): array
    {
        $extension = strtolower(trim($extension));
        $text = '';
        $warning = null;

        if ($extension === 'pdf') {
            $text = $this->pdfText($absolutePath);
        } elseif ($extension === 'docx') {
            $text = $this->docxText($absolutePath);
        } elseif ($extension === 'doc') {
            $warning = 'Legacy .doc files cannot be read automatically. Upload a PDF or DOCX, or enter the details manually.';
        }

        $text = $this->cleanText($text);

        if ($warning === null && $text === '') {
            $warning = 'No readable text was found in the document. You can still enter the details manually.';
        }

        $structured = ['education' => [], 'experience' => [], 'skills' => []];
        if ($text !== '') {
            $ai = $this->structure($text);
            if ($ai === null) {
                $warning = $warning ?: 'Automatic structuring is unavailable right now. The document text was extracted; fill the fields manually or try again.';
            } else {
                $structured = $ai;
            }
        }

        return [
            'text' => $text,
            'education' => $structured['education'],
            'experience' => $structured['experience'],
            'skills' => $structured['skills'],
            'warning' => $warning,
        ];
    }

    protected function pdfText(string $path): string
    {
        try {
            $autoload = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
            if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                return '';
            }
            $parser = new \Smalot\PdfParser\Parser();
            $document = $parser->parseFile($path);
            return (string)$document->getText();
        } catch (\Throwable $e) {
            error_log('CvExtractionService pdf: ' . $e->getMessage());
            return '';
        }
    }

    protected function docxText(string $path): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return '';
            }
            $xml = (string)$zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml === '') {
                return '';
            }
            $xml = preg_replace('/<\/w:p>/i', "\n", $xml) ?? $xml;
            $xml = preg_replace('/<w:tab[^>]*\/>/i', "\t", $xml) ?? $xml;
            $text = strip_tags($xml);
            return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        } catch (\Throwable $e) {
            error_log('CvExtractionService docx: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Structure the extracted text into education/experience/skills, or null
     * when the AI is unavailable or its response cannot be parsed.
     */
    protected function structure(string $text): ?array
    {
        $prompt = "You are extracting structured data from a resume/CV for a Philippine transport management system.

CRITICAL RULES:
- Use ONLY the document text below. Do NOT invent, infer, or embellish anything.
- If a field or section is not present, leave it out; do not guess.
- Keep each value short and factual (as written in the document).
- Return ONLY a valid JSON object with exactly these keys:
{\"education\":[{\"degree\":\"\",\"institution\":\"\",\"year\":\"\"}],\"experience\":[{\"position\":\"\",\"organization\":\"\",\"period\":\"\",\"description\":\"\"}],\"skills\":[\"\"]}
- education: degrees/courses with institution and year when stated.
- experience: work history entries with position, organization, period, and a short description when stated.
- skills: concrete skills mentioned in the document.
- No markdown, no code fences, no extra keys.

Document text:

" . mb_substr($text, 0, self::MAX_AI_CHARS);

        $content = $this->requestCompletion($prompt);
        if ($content === null) {
            return null;
        }

        $content = preg_replace('/```json|```/', '', trim($content)) ?? trim($content);
        $decoded = json_decode($content, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
        }
        if (!is_array($decoded)) {
            return null;
        }

        return [
            'education' => $this->normalizeEducation($decoded['education'] ?? []),
            'experience' => $this->normalizeExperience($decoded['experience'] ?? []),
            'skills' => $this->normalizeSkills($decoded['skills'] ?? []),
        ];
    }

    private function normalizeEducation($items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $entry = [
                'degree' => $this->clip((string)($item['degree'] ?? ''), 200),
                'institution' => $this->clip((string)($item['institution'] ?? ''), 200),
                'year' => $this->clip((string)($item['year'] ?? ''), 50),
            ];
            if (implode('', $entry) !== '') {
                $out[] = $entry;
            }
            if (count($out) >= 10) {
                break;
            }
        }
        return $out;
    }

    private function normalizeExperience($items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $entry = [
                'position' => $this->clip((string)($item['position'] ?? ''), 200),
                'organization' => $this->clip((string)($item['organization'] ?? ''), 200),
                'period' => $this->clip((string)($item['period'] ?? ''), 100),
                'description' => $this->clip((string)($item['description'] ?? ''), 400),
            ];
            if (implode('', $entry) !== '') {
                $out[] = $entry;
            }
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    private function normalizeSkills($items): array
    {
        if (is_string($items)) {
            $items = explode(',', $items);
        }
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $skill) {
            $skill = $this->clip((string)$skill, 100);
            if ($skill !== '' && !in_array($skill, $out, true)) {
                $out[] = $skill;
            }
            if (count($out) >= 30) {
                break;
            }
        }
        return $out;
    }

    /**
     * Send the prompt to the configured AI provider; null on any failure.
     */
    protected function requestCompletion(string $prompt): ?string
    {
        $apiKey = '';
        $model = 'gpt-4o-mini';
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $configFile = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/finance/config/ai.php';
        if (is_file($configFile)) {
            $aiConfig = require $configFile;
            if (is_array($aiConfig) && !empty($aiConfig['api_key'])) {
                $apiKey = (string)$aiConfig['api_key'];
                $model = (string)($aiConfig['model'] ?? $model);
                $apiUrl = (string)($aiConfig['api_url'] ?? $apiUrl);
            }
        }
        if ($apiKey === '') {
            $envFile = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/finance/config/Env.php';
            if (is_file($envFile)) {
                require_once $envFile;
                $apiKey = (string)\Finance\Config\Env::get('FINANCE_GROQ_KEY', '');
                if ($apiKey !== '') {
                    $model = 'llama-3.3-70b-versatile';
                    $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
                }
            }
        }
        if ($apiKey === '' || !function_exists('curl_init')) {
            return null;
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You extract structured resume data. Return ONLY valid JSON. Never invent facts.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1600,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            error_log('CvExtractionService ai: ' . $error);
            return null;
        }

        $result = json_decode((string)$response, true);
        $content = $result['choices'][0]['message']['content'] ?? null;
        return is_string($content) && trim($content) !== '' ? $content : null;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);
        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }

    private function clip(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }
}
