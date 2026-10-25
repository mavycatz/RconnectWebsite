<?php
/**
 * PaymentScreenshotValidator
 *
 * OCR-based validation of passenger payment screenshots using Google Cloud
 * Vision TEXT_DETECTION. Fail-closed: an upload is only ever ACCEPTED when
 * OCR text was obtained and the multi-field rule engine passes. It never
 * verifies the payment itself — it only certifies that the screenshot looks
 * like a genuine, complete, successful payment confirmation.
 *
 * Credentials: app/config/vision-key.json (service account JSON), or the
 * GOOGLE_APPLICATION_CREDENTIALS env var.
 *
 * Usage:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymentScreenshotValidator.php';
 *   $v = new PaymentScreenshotValidator();
 *   $result = $v->validate($tmpFilePath, $expectedAmount);
 *   // $result = ['ok'=>bool, 'reason'=>?string, 'reason_code'=>?string,
 *   //           'extracted'=>[...], 'error'=>?string]
 */
class PaymentScreenshotValidator
{
    /** Path to the Google Cloud Vision service-account JSON key. */
    private const VISION_CREDENTIALS = __DIR__ . '/../../app/config/vision-key.json';

    /** Amount tolerance when comparing the extracted amount to the expected one. */
    private const AMOUNT_TOLERANCE = 0.05;

    /** Minimum OCR word count below which we call the screenshot "unclear". */
    private const MIN_WORDS = 6;

    /** Minimum normalized text length below which we call it "unclear". */
    private const MIN_CHARS = 40;

    /** Max share of junk characters before an image is flagged suspicious. */
    private const MAX_JUNK_RATIO = 0.25;

    /** Success status markers (lowercase, extracted from the receipt). */
    private const SUCCESS_MARKERS = [
        'successful', 'successfully', 'success', 'completed', 'complete',
        'paid', 'credited', 'sent', 'received', 'done', 'thank you',
        'transaction complete', 'payment confirmed', 'confirmed',
    ];

    /** Failure markers that contradict a success confirmation. */
    private const FAILURE_MARKERS = [
        'failed', 'failure', 'declined', 'cancelled', 'canceled',
        'insufficient', 'pending', 'processing', 'on hold', 'error',
        'rejected', 'refunded', 'expired',
    ];

    /** Supported payment platform indicators. */
    private const PLATFORM_MARKERS = [
        'gcash', 'g-cash', 'maya', 'paymaya', 'e-wallet', 'ewallet',
        'instapay', 'pesonet', 'bdo', 'bpi', 'ubp', 'paypal',
        'coins.ph', 'grabpay', 'bank transfer', 'bank',
    ];

    private const AMOUNT_SYMBOL = '₱';

    /** Optional injected OCR callable for tests: fn(string $path): ?string */
    private $ocrOverride = null;

    public function __construct(?callable $ocrOverride = null)
    {
        $this->ocrOverride = $ocrOverride;
    }

    /**
     * Validate a screenshot file.
     *
     * @param string      $imagePath      Path to the uploaded image (tmp or stored).
     * @param float|null  $expectedAmount Expected GCash amount for the trip, or null.
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   reason_code?: string,
     *   extracted: array,
     *   text?: string,
     *   error?: string
     * }
     */
    public function validate(string $imagePath, ?float $expectedAmount = null): array
    {
        $extracted = [
            'reference_no' => null,
            'amount' => null,
            'date' => null,
            'time' => null,
            'success' => false,
            'provider' => null,
            'sender' => null,
            'recipient' => null,
        ];

        $text = $this->ocrText($imagePath);

        if ($text === null || $text === '') {
            return $this->fail('ocr_unavailable', 'Unable to verify screenshot. Please upload a clearer screenshot.', $extracted, $text);
        }

        $normalized = $this->normalize($text);

        // Too little text → unreadable / blurry / not a screenshot.
        $words = preg_split('/\s+/', trim($normalized)) ?: [];
        if (count($words) < self::MIN_WORDS || mb_strlen($normalized) < self::MIN_CHARS) {
            return $this->fail('unclear', 'Screenshot is too blurry or incomplete to verify the payment. Please upload a clearer screenshot.', $extracted, $text);
        }

        // Junk-character density → suspicious/manipulated or wrong content.
        if ($this->junkRatio($normalized) > self::MAX_JUNK_RATIO) {
            return $this->fail('suspicious', 'Image does not contain readable payment information. Please upload a clear payment screenshot.', $extracted, $text);
        }

        $extracted['reference_no'] = $this->extractReference($normalized);
        $extracted['amount'] = $this->extractAmount($normalized);
        $extracted['date'] = $this->extractDate($normalized);
        $extracted['time'] = $this->extractTime($normalized);
        $extracted['success'] = $this->detectSuccess($normalized);
        $extracted['provider'] = $this->detectProvider($normalized);

        $parties = $this->extractParties($normalized);
        $extracted['sender'] = $parties['sender'];
        $extracted['recipient'] = $parties['recipient'];

        // Is this even a payment-related image at all? Brand keywords alone
        // (or nothing but prose) never pass — real payment data (a reference
        // and/or amount, or a success marker) must be present.
        $hasMoneyLike = $extracted['amount'] !== null || $extracted['reference_no'] !== null;
        if (!$hasMoneyLike && !$extracted['success']) {
            return $this->fail('not_payment', 'The uploaded image does not appear to be a payment confirmation. Please upload your payment screenshot.', $extracted, $text);
        }

        // Required fields must be present together.
        if ($extracted['reference_no'] === null) {
            return $this->fail('missing_reference', 'No reference/transaction number was found in the screenshot. Please upload a complete payment confirmation.', $extracted, $text);
        }
        if ($extracted['amount'] === null) {
            return $this->fail('missing_amount', 'No payment amount was found in the screenshot. Please upload a complete payment confirmation.', $extracted, $text);
        }
        if ($extracted['date'] === null) {
            return $this->fail('missing_date', 'No payment date/time was found in the screenshot. Please upload a complete payment confirmation.', $extracted, $text);
        }
        if (!$extracted['success']) {
            return $this->fail('not_successful', 'The screenshot does not show a successful/completed payment. Only successful payment confirmations are accepted.', $extracted, $text);
        }

        // Expected-amount comparison (skipped only when no expected amount is recorded).
        if ($expectedAmount !== null && $expectedAmount > 0) {
            $delta = abs($extracted['amount'] - $expectedAmount);
            if ($delta > self::AMOUNT_TOLERANCE) {
                return $this->fail('amount_mismatch', 'The amount in the screenshot does not match the expected payment amount.', $extracted, $text);
            }
        }

        return [
            'ok' => true,
            'extracted' => $extracted,
            'text' => $text,
        ];
    }

    // ---------------------------------------------------------------
    // OCR
    // ---------------------------------------------------------------

    /**
     * Run Google Cloud Vision TEXT_DETECTION on the image and return the
     * full OCR text, or null when OCR could not be performed (missing
     * credentials, API error, no text detected).
     */
    private function ocrText(string $imagePath): ?string
    {
        if ($this->ocrOverride !== null) {
            $cb = $this->ocrOverride;
            return $cb($imagePath);
        }

        if (!is_file($imagePath)) {
            return null;
        }

        try {
            $autoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }

            $credentials = $this->resolveCredentials();
            if ($credentials === null) {
                return null;
            }

            $client = new \Google\Cloud\Vision\V1\Client\ImageAnnotatorClient([
                'credentials' => $credentials,
                'transport' => 'rest',
            ]);

            $image = (new \Google\Cloud\Vision\V1\Image())
                ->setContent(file_get_contents($imagePath));

            $feature = (new \Google\Cloud\Vision\V1\Feature())
                ->setType(\Google\Cloud\Vision\V1\Feature::TEXT_DETECTION);

            $request = (new \Google\Cloud\Vision\V1\AnnotateImageRequest())
                ->setImage($image)
                ->setFeatures([$feature]);

            $batch = (new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest())
                ->setRequests([$request]);

            $response = $client->batchAnnotateImages($batch);

            $annotations = $response->getResponses();
            if (empty($annotations)) {
                return null;
            }

            $error = $annotations[0]->getError();
            if ($error !== null && $error->getCode() !== 0) {
                error_log('Vision OCR error: ' . $error->getMessage());
                return null;
            }

            $textAnnos = $annotations[0]->getTextAnnotations();
            if (empty($textAnnos)) {
                return null;
            }

            $text = trim($textAnnos[0]->getDescription() ?? '');
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            error_log('Vision OCR exception: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveCredentials(): ?string
    {
        $env = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }
        if (is_file(self::VISION_CREDENTIALS)) {
            return self::VISION_CREDENTIALS;
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Normalization
    // ---------------------------------------------------------------

    /**
     * Case-fold, convert full-width digits/letters to ASCII, unify the peso
     * sign, and collapse whitespace so regexes match reliably.
     */
    private function normalize(string $text): string
    {
        $text = mb_convert_kana($text, 'as');
        $text = mb_strtolower($text);
        $text = str_replace([self::AMOUNT_SYMBOL, '₱', 'pesos'], ' php ', $text);
        $text = str_replace(["\r", "\t"], ' ', $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = trim($text);
        return $text;
    }

    // ---------------------------------------------------------------
    // Field extraction
    // ---------------------------------------------------------------

    /**
     * Reference/transaction number: prefer labeled forms
     * ("reference no.: ABC1234567", "transaction id: ...", "trx ..."),
     * then fall back to a bare 12+ digit run (typical wallet/instapay ref).
     * Requires 6–40 alphanumeric chars.
     */
    private function extractReference(string $normalized): ?string
    {
        // Prefer labeled references: "reference no.: ABC1234567" etc.
        if (preg_match('/(?:ref(?:erence)?\.?\s*(?:no\.?|id|number)?|transaction\s*(?:id|no\.?|number)?|trx)[\s:.#-]*([a-z0-9]{6,40})\b/i', $normalized, $m) === 1) {
            return $m[1];
        }
        // Fall back to a bare 12+ digit run (typical wallet/instapay ref).
        if (preg_match('/\b\d{12,}\b/', $normalized, $m) === 1) {
            return $m[0];
        }
        return null;
    }

    /**
     * Amount: "php 25.00", "php 1,250.50", or a labeled amount
     * ("amount: 25.00", "total: 25"). Returns a float.
     */
    private function extractAmount(string $normalized): ?float
    {
        if (preg_match('/php\s*(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?/', $normalized, $m) === 1) {
            return $this->buildAmount($m);
        }
        if (preg_match('/(?:amount|total|fare|paid|balance)\s*[:]?\s*(?:php)?\s*(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?/', $normalized, $m) === 1) {
            return $this->buildAmount($m);
        }
        return null;
    }

    private function buildAmount(array $m): ?float
    {
        $int = str_replace(',', '', $m[1]);
        $dec = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
        if (!is_numeric($int)) {
            return null;
        }
        return (float) sprintf('%s.%s', $int, substr($dec . '00', 0, 2));
    }

    /**
     * Date: YYYY-MM-DD, MM/DD/YYYY, MM-DD-YYYY, or "sep 5 2025".
     * Returns the raw matched date string.
     */
    private function extractDate(string $normalized): ?string
    {
        if (preg_match('#\b\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\b#', $normalized, $m) === 1) {
            return $m[0];
        }
        if (preg_match('#\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b#', $normalized, $m) === 1) {
            return $m[0];
        }
        if (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:,?\s+\d{4})?\b/i', $normalized, $m) === 1) {
            return $m[0];
        }
        return null;
    }

    /**
     * Time: "1:45 pm", "13:45", "13:45:02". Returns the raw match.
     */
    private function extractTime(string $normalized): ?string
    {
        if (preg_match('/\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:am|pm|hrs?)?\b/', $normalized, $m) === 1) {
            return $m[0];
        }
        return null;
    }

    /**
     * Success only when a positive marker is present and no failure marker
     * strongly contradicts it (failure markers win).
     */
    private function detectSuccess(string $normalized): bool
    {
        $lower = $normalized;
        foreach (self::FAILURE_MARKERS as $f) {
            if (strpos($lower, $f) !== false) {
                return false;
            }
        }
        foreach (self::SUCCESS_MARKERS as $s) {
            if (strpos($lower, $s) !== false) {
                return true;
            }
        }
        return false;
    }

    private function detectProvider(string $normalized): ?string
    {
        foreach (self::PLATFORM_MARKERS as $p) {
            if (strpos($normalized, $p) !== false) {
                if ($p === 'bank') {
                    // "bank" alone is weak; only accept when part of
                    // "bank transfer" or a named bank already matched.
                    continue;
                }
                return strtoupper($p);
            }
        }
        return null;
    }

    /**
     * Sender/recipient: "to <name>", "from <name>", "paid to", "sent to",
     * "received from". Names must be 2-40 word chars; kept when detected.
     */
    private function extractParties(string $normalized): array
    {
        $sender = null;
        $recipient = null;

        if (preg_match('/\b(?:from|received from|sender)\b\s*:?\s*([a-z][a-z .\'-]{2,40})/', $normalized, $m) === 1) {
            $candidate = trim($m[1]);
            if (strlen($candidate) >= 2) {
                $sender = $candidate;
            }
        }
        if (preg_match('/\b(?:to|paid to|sent to|recipient|paid)\b\s*:?\s*([a-z][a-z .\'-]{2,40})/', $normalized, $m) === 1) {
            $candidate = trim($m[1]);
            if (strlen($candidate) >= 2) {
                $recipient = $candidate;
            }
        }

        return [
            'sender' => $sender,
            'recipient' => $recipient,
        ];
    }

    // ---------------------------------------------------------------
    // Quality / suspicion heuristics
    // ---------------------------------------------------------------

    private function junkRatio(string $normalized): float
    {
        $total = mb_strlen($normalized);
        if ($total === 0) {
            return 1.0;
        }
        $clean = preg_replace("/[a-z0-9\s.,:;()\-+'\"]/", '', $normalized) ?? '';
        return mb_strlen($clean) / $total;
    }

    private function fail(string $code, string $reason, array $extracted, ?string $text): array
    {
        return [
            'ok' => false,
            'reason_code' => $code,
            'reason' => $reason,
            'extracted' => $extracted,
            'text' => $text,
        ];
    }
}