<?php

if (!defined('SCAN_ID_LIB_ONLY')) {
    header('Content-Type: application/json');
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ob_start();
    // A fatal (timeout, memory, engine error) must still answer with JSON so
    // the app shows a readable message instead of "scanner unavailable".
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        if (!empty($GLOBALS['scan_id_responded'])) {
            return;
        }
        error_log('[scanID] fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'quality' => quality_payload('none', 0, 0),
            'message' => 'The ID scanner could not finish reading this photo. Please retake it or enter the details manually.',
            'manual_fallback' => true,
        ]);
    });
}
date_default_timezone_set('Asia/Manila');

/* =========================================================
   OCR provider is unchanged (OCR.Space, same key). What
   changed is the pipeline around it: multiple preprocessing
   variants / engine configurations, result scoring, field
   extraction with fuzzy label matching, and a non-blocking
   quality result instead of a bare "No text detected".
   ========================================================= */

const OCR_SPACE_KEY = 'K88769509588957';
const OCR_GOOD_SCORE = 68;
const OCR_MAX_ATTEMPTS_PER_SIDE = 4;
const OCR_MAX_UPLOAD_BYTES = 900000;

/**
 * Resolve the OCR.Space API key.
 *
 * The shared default key is a free demo key that OCR.Space throttles under
 * load ("Free OCR API overloaded currently..."), which looks like "clear ID
 * not recognized". A deployment can supply its own key through:
 *   1. environment variable OCR_SPACE_KEY (recommended), or
 *   2. $_SERVER['OCR_SPACE_KEY'], or
 *   3. passenger/config/ocr.php returning ['api_key' => '...'].
 */
function ocr_space_key(): string
{
    $key = (string) getenv('OCR_SPACE_KEY');
    if (trim($key) === '') {
        $key = (string) ($_SERVER['OCR_SPACE_KEY'] ?? '');
    }
    if (trim($key) === '') {
        $config = dirname(__DIR__, 2) . '/config/ocr.php';
        if (is_file($config)) {
            $value = require $config;
            if (is_array($value)) {
                $key = (string) ($value['api_key'] ?? '');
            } elseif (is_string($value)) {
                $key = $value;
            }
        }
    }

    return trim($key) !== '' ? trim($key) : OCR_SPACE_KEY;
}

if (!defined('SCAN_ID_LIB_ONLY')) {
try {
    // A scan must answer promptly. The time budget below stops escalation
    // long before typical PHP/web server limits, and the parallel first pass
    // means a clear ID costs one network round trip for both sides.
    set_time_limit(90);
    $scanDeadline = microtime(true) + 50;

    if (!isset($_FILES['id_front']) || !isset($_FILES['id_back'])) {
        respond([
            'success' => false,
            'quality' => quality_payload('none', 0, 0),
            'message' => 'No image uploaded',
            'manual_fallback' => true,
        ]);
    }

    $idType = trim((string) ($_POST['id_type'] ?? ''));
    $discountHint = trim((string) ($_POST['discount'] ?? ''));

    $uploadDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $frontOriginal = save_upload('id_front', $uploadDir);
    $backOriginal = save_upload('id_back', $uploadDir);

    if ($frontOriginal === null || $backOriginal === null) {
        respond([
            'success' => false,
            'quality' => quality_payload('none', 0, 0),
            'message' => 'We could not read the uploaded photo. Please retake the ID photo.',
            'manual_fallback' => true,
        ]);
    }

    // First pass: front and back are sent to OCR at the same time, so a clear
    // ID costs one round trip instead of a sequential six-request pipeline.
    $firstJobs = [];
    $firstCleanup = [];
    foreach (['front' => $frontOriginal, 'back' => $backOriginal] as $side => $original) {
        $send = ensure_under_limit($original);
        if ($send !== $original) {
            $firstCleanup[] = $send;
        }
        $firstJobs[$side] = ['path' => $send, 'engine' => '2', 'scale' => true];
    }
    $firstResults = ocr_space_multi($firstJobs);
    foreach ($firstCleanup as $tmp) {
        @unlink($tmp);
    }

    $front = run_ocr_side($frontOriginal, 'front', $scanDeadline, $firstResults['front'] ?? null);
    $back = run_ocr_side($backOriginal, 'back', $scanDeadline, $firstResults['back'] ?? null);

    $frontText = (string) $front['text'];
    $backText = (string) $back['text'];
    $text = trim($frontText . "\n" . $backText);
    $attempts = array_merge($front['attempts'], $back['attempts']);

    if (trim($text) === '') {
        $ocrError = '';
        $limitHit = false;
        foreach ($attempts as $a) {
            if (!empty($a['error'])) {
                $ocrError = (string) $a['error'];
                if (ocr_error_is_limit($ocrError)) {
                    $limitHit = true;
                }
                break;
            }
        }

        ocr_debug_log([
            'stage' => 'scan',
            'result' => 'no_text',
            'type' => $idType,
            'provider_error' => preg_replace('/\d{3,}/', '###', $ocrError),
            'attempts' => array_map(static fn($a) => ['side' => $a['side'] ?? '', 'variant' => $a['variant'] ?? '', 'error' => !empty($a['error'])], $attempts),
        ]);

        respond([
            'success' => false,
            'scanner_version' => '3.0',
            'quality' => quality_payload('none', 0, 0),
            'message' => $limitHit
                ? 'The ID scanning service is busy right now. Please wait a minute and try again, or enter your details manually.'
                : ($ocrError !== ''
                    ? 'Could not read the ID photo. Retake it with good lighting and no glare.'
                    : 'Could not read the ID clearly. Keep the whole ID inside the frame and retake the photo.'),
            'manual_fallback' => true,
            'attempts' => $attempts,
        ]);
    }

    $usableChars = strlen((string) preg_replace('/\s+/', '', $text));
    $score = max((int) $front['score'], (int) $back['score'], ocr_score($text));

    $frontChars = strlen((string) preg_replace('/\s+/', '', $frontText));
    $backChars = strlen((string) preg_replace('/\s+/', '', $backText));

    $parsed = parse_document($idType, $front['texts'] ?? [$frontText], $back['texts'] ?? [$backText], $text, $discountHint, $score);
    // Keep the server's best OCR passes, bound to the exact uploaded images.
    // OTP must not downgrade a successful scan because a second OCR request
    // is throttled or reads fewer fields than the enhanced scan pipeline.
    ocr_cache_document_side($frontOriginal, $front['texts'] ?? [$frontText], (int) $front['score']);
    ocr_cache_document_side($backOriginal, $back['texts'] ?? [$backText], (int) $back['score']);
    if (empty($parsed['mismatch'])) {
        $autoAssessment = resolve_auto_verification($frontOriginal, [
            'id_type' => $idType, 'discount' => $discountHint,
            'full_name' => (string) ($parsed['full_name'] ?? ''),
            'date_of_birth' => (string) ($parsed['date_of_birth'] ?? ''),
            'student_id' => (string) (($parsed['student_number'] ?? '') ?: ($parsed['id_number'] ?? '')),
        ], null, $backOriginal);
        if ($autoAssessment['status'] === 'verified') {
            $parsed['verification'] = 'verified';
            $parsed['review_flags'] = [];
            ocr_remember_verified_document($frontOriginal, $backOriginal, [
                'id_type' => $idType, 'discount' => $discountHint,
                'full_name' => (string) ($parsed['full_name'] ?? ''),
                'date_of_birth' => (string) ($parsed['date_of_birth'] ?? ''),
                'student_id' => (string) (($parsed['student_number'] ?? '') ?: ($parsed['id_number'] ?? '')),
            ], $autoAssessment);
        }
    }

    ocr_debug_log([
        'stage' => 'scan',
        'result' => !empty($parsed['mismatch']) ? 'mismatch' : 'parsed',
        'type' => $idType,
        'score' => $score,
        'chars' => $usableChars,
        'detected_type' => $parsed['detected_type'] ?? null,
        'verification' => $parsed['verification'] ?? null,
        'name_found' => !empty($parsed['full_name']),
        'id_found' => !empty($parsed['id_number']),
        'dob_found' => !empty($parsed['date_of_birth']),
        'school_found' => !empty($parsed['school']),
        'school_year_found' => !empty($parsed['school_year']),
        'validity_found' => !empty($parsed['validity_until']),
        'review_flags' => count($parsed['review_flags'] ?? []),
    ]);

    if (!empty($parsed['mismatch'])) {
        respond([
            'success' => false,
            'mismatch' => true,
            'quality' => quality_payload('none', $score, $usableChars),
            'message' => $parsed['message'],
            'manual_fallback' => true,
            'detected_type' => $parsed['detected_type'] ?? null,
            'expected_type' => $idType,
            'attempts' => $attempts,
            'sides' => ['front' => $frontChars, 'back' => $backChars],
        ]);
    }

    $hasName = !empty($parsed['full_name']);
    $hasIdNumber = !empty($parsed['id_number']);
    $hasDob = !empty($parsed['date_of_birth']);

    if ($score >= OCR_GOOD_SCORE && $hasName && ($hasIdNumber || $hasDob)) {
        $level = 'good';
    } elseif ($hasName || $hasIdNumber || $hasDob || $usableChars >= 20) {
        $level = 'partial';
    } else {
        $level = 'none';
    }

    $missing = [];
    if (!$hasName) $missing[] = 'full_name';
    if (!$hasIdNumber) $missing[] = 'id_number';
    if (!$hasDob) $missing[] = 'date_of_birth';
    if (!empty($parsed['requires_address']) && empty($parsed['address'])) $missing[] = 'address';
    if ($idType === 'student-id' && empty($parsed['student_number'])) $missing[] = 'student_number';
    if ($idType === 'student-id' && empty($parsed['school_year'])) $missing[] = 'school_year';

    $age = null;
    if ($hasDob) {
        try {
            $birth = new DateTime($parsed['date_of_birth']);
            $age = (new DateTime())->diff($birth)->y;
        } catch (Exception $e) {
            $age = null;
        }
    }

    $warning = $parsed['warning'] ?? null;
    $ageWarning = null;
    if ($idType === 'senior-citizen-id' && $age !== null && $age < 60) {
        $ageWarning = 'This birth date gives an age of ' . $age . '. Senior discount usually needs 60 or older — please double-check.';
    }

    // Actionable message for the "text detected but fields missing" case, so
    // the passenger is told exactly what to fix instead of a generic failure.
    $fieldMessage = null;
    if ($level === 'none') {
        $fieldMessage = 'Some text was detected but the ID details could not be identified. Please correct the fields below, retake the photo, or enter them manually.';
    } elseif ($level === 'partial') {
        $fieldMessage = 'Some ID details could not be read. Please check the fields below and correct anything that is missing.';
    }

    respond(array_merge($parsed, [
        'success' => $level !== 'none',
        'scanner_version' => '3.0',
        'message' => $fieldMessage,
        'quality' => quality_payload($level, $score, $usableChars),
        'age' => $age,
        'missing' => $missing,
        'manual_fallback' => $level !== 'good',
        'warning' => $warning,
        'age_warning' => $ageWarning,
        'attempts' => $attempts,
        'sides' => ['front' => $frontChars, 'back' => $backChars],
        'text_preview' => safe_substr($text, 0, 300),
    ]));
} catch (Throwable $e) {
    respond([
        'success' => false,
        'quality' => quality_payload('none', 0, 0),
        'message' => 'Something went wrong while reading the ID. Please retake the photo or enter the details manually.',
        'manual_fallback' => true,
    ]);
}
}

/* =========================================================
   OCR pipeline
   ========================================================= */

function run_ocr_side(string $originalPath, string $side, ?float $deadline = null, ?array $precomputed = null): array
{
    // Portrait captures usually mean the card (and its text) is printed
    // sideways; rotating the image is more reliable than relying on the OCR
    // engine's orientation detection alone.
    if (image_is_portrait($originalPath)) {
        $plan = [
            ['variant' => 'original', 'engine' => '2', 'scale' => true],
            ['variant' => 'rotate90', 'engine' => '2', 'scale' => true],
            ['variant' => 'rotate270', 'engine' => '2', 'scale' => true],
            ['variant' => 'contrast', 'engine' => '2', 'scale' => true],
            ['variant' => 'binarize', 'engine' => '1', 'scale' => false],
        ];
    } else {
        $plan = [
            ['variant' => 'original', 'engine' => '2', 'scale' => true],
            ['variant' => 'upscale', 'engine' => '2', 'scale' => true],
            ['variant' => 'contrast', 'engine' => '2', 'scale' => true],
            ['variant' => 'binarize', 'engine' => '1', 'scale' => false],
        ];
    }

    $best = ['text' => '', 'score' => 0, 'combined' => 0, 'engine' => '2', 'variant' => 'original', 'attempts' => []];
    $attempts = [];
    $triedSignatures = [];
    $texts = [];
    $startIndex = 0;

    // The parallel first pass already produced this side's original-engine
    // result. Escalate to the extra variants only when it was not good enough.
    if ($precomputed !== null) {
        $startIndex = 1;
        $triedSignatures['2|1'] = true;

        $text = (string) ($precomputed['text'] ?? '');
        $error = $precomputed['error'] ?? null;
        $score = ocr_score($text);
        $combined = $score + field_signal($text);

        if (trim($text) !== '') {
            $texts[] = $text;
        }
        $attempts[] = [
            'side' => $side,
            'variant' => 'original',
            'engine' => '2',
            'scale' => true,
            'score' => $score,
            'chars' => strlen((string) preg_replace('/\s+/', '', $text)),
            'error' => $error,
        ];
        $best = ['text' => $text, 'score' => $score, 'combined' => $combined, 'engine' => '2', 'variant' => 'original', 'attempts' => []];

        // A clear read is done immediately. A provider limit cannot be fixed
        // by retrying variants, so it also ends this side at once.
        if (($score >= OCR_GOOD_SCORE && field_signal($text) >= 30) || $combined >= 100 || ocr_error_is_limit($error)) {
            $best['attempts'] = $attempts;
            $best['texts'] = array_values(array_unique($texts));
            return $best;
        }
    }

    foreach ($plan as $index => $step) {
        if ($index < $startIndex) {
            continue;
        }
        if ($index >= OCR_MAX_ATTEMPTS_PER_SIDE) {
            break;
        }
        if ($deadline !== null && microtime(true) >= $deadline) {
            $attempts[] = ['side' => $side, 'variant' => 'skipped', 'engine' => $step['engine'], 'scale' => (bool) $step['scale'], 'score' => 0, 'chars' => 0, 'error' => 'time budget reached'];
            break;
        }

        $variantPath = null;
        $variantUsed = 'original';

        if ($step['variant'] !== 'original') {
            $made = make_variant($originalPath, $step['variant']);
            if ($made !== null) {
                $variantPath = $made;
                $variantUsed = $step['variant'];
            }
        }

        // Never send the exact same OCR request twice: when a variant could not
        // be produced (no GD), skip the step if its engine/scale pair is already
        // covered by an earlier attempt.
        $signature = $step['engine'] . '|' . ($step['scale'] ? '1' : '0');
        if ($variantPath === null && $step['variant'] !== 'original' && isset($triedSignatures[$signature])) {
            continue;
        }
        $triedSignatures[$signature] = true;

        $uploadPath = $variantPath !== null ? $variantPath : $originalPath;
        $sendPath = ensure_under_limit($uploadPath);

        $result = ocr_space($sendPath, $step['engine'], (bool) $step['scale']);
        $text = (string) $result['text'];
        $score = ocr_score($text);
        $combined = $score + field_signal($text);

        if (trim($text) !== '') {
            $texts[] = $text;
        }

        $attempts[] = [
            'side' => $side,
            'variant' => $variantUsed,
            'engine' => $step['engine'],
            'scale' => (bool) $step['scale'],
            'score' => $score,
            'chars' => strlen((string) preg_replace('/\s+/', '', $text)),
            'error' => $result['error'],
        ];

        if ($combined > $best['combined']) {
            $best = [
                'text' => $text,
                'score' => $score,
                'combined' => $combined,
                'engine' => $step['engine'],
                'variant' => $variantUsed,
                'attempts' => [],
            ];
        }

        if ($sendPath !== $uploadPath) {
            @unlink($sendPath);
        }
        if ($variantPath !== null) {
            @unlink($variantPath);
        }

        // Stop early on a clear result, on a provider-side limit (retrying
        // cannot help), or on a hard error with no text.
        if ($combined >= 100 || ($score >= OCR_GOOD_SCORE && field_signal($text) >= 30)) {
            break;
        }
        if ($result['error'] !== null && trim($text) === '') {
            break;
        }
    }

    $best['attempts'] = $attempts;
    $best['texts'] = array_values(array_unique($texts));
    return $best;
}

function ocr_space_fields(string $filePath, string $engine = '2', bool $scale = true): array
{
    return [
        'apikey' => ocr_space_key(),
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'OCREngine' => $engine,
        'scale' => $scale ? 'true' : 'false',
        'detectOrientation' => 'true',
        'file' => new CURLFile($filePath),
    ];
}

function ocr_space_parse(string $response, string $engine = '2'): array
{
    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['text' => '', 'error' => 'Unexpected OCR response', 'engine' => $engine];
    }

    // OCR.Space reports throttling/quota problems as {error, details} without
    // any OCRExitCode/ParsedResults. Without this branch those responses look
    // like "no text detected" and blame the photo instead of the service.
    if (!empty($json['error'])) {
        $message = trim((string) $json['error']);
        if (!empty($json['details'])) {
            $message .= ' ' . trim((string) $json['details']);
        }
        return ['text' => '', 'error' => $message, 'engine' => $engine];
    }

    if (!empty($json['IsErroredOnProcessing'])) {
        $error = $json['ErrorMessage'] ?? $json['ErrorDetails'] ?? 'OCR could not process the image';
        if (is_array($error)) {
            $error = implode('; ', array_map('strval', $error));
        }
        return ['text' => '', 'error' => (string) $error, 'engine' => $engine];
    }

    $text = '';
    foreach (($json['ParsedResults'] ?? []) as $parsed) {
        $text .= (string) ($parsed['ParsedText'] ?? '') . "\n";
    }

    if (trim($text) === '') {
        $exit = isset($json['OCRExitCode']) ? (int) $json['OCRExitCode'] : 1;
        if (!in_array($exit, [1, 2], true)) {
            $detail = $json['ErrorMessage'] ?? 'OCR could not read the image';
            if (is_array($detail)) {
                $detail = implode('; ', array_map('strval', $detail));
            }
            return ['text' => '', 'error' => (string) $detail . ' (exit ' . $exit . ')', 'engine' => $engine];
        }
    }

    return ['text' => trim($text), 'error' => null, 'engine' => $engine];
}

/**
 * Resolve a CA bundle for HTTPS verification.
 *
 * Many PHP builds (and some shared hosts) ship without curl.cainfo pointed at
 * a certificate bundle. When that is the case every OCR request fails with an
 * SSL verification error even though the service is reachable. A known bundle
 * is used when present; otherwise callers may retry with relaxed verification
 * as a last resort so the scanner keeps working.
 */
function ocr_ca_bundle(): ?string
{
    static $resolved = false;
    static $bundle = null;
    if ($resolved) {
        return $bundle;
    }
    $resolved = true;

    $candidates = [
        (string) ini_get('curl.cainfo'),
        (string) ini_get('openssl.cafile'),
        (string) getenv('CURL_CA_BUNDLE'),
        (string) getenv('SSL_CERT_FILE'),
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            $bundle = $candidate;
            return $bundle;
        }
    }

    foreach ([
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/cert.pem',
        '/usr/local/share/ca-certificates',
        'C:\\php\\extras\\ssl\\cacert.pem',
        'C:\\Program Files\\PHP\\extras\\ssl\\cacert.pem',
    ] as $candidate) {
        if (is_file($candidate)) {
            $bundle = $candidate;
            return $bundle;
        }
    }

    return null;
}

function ocr_curl_options(string $filePath, string $engine, bool $scale): array
{
    $options = [
        CURLOPT_URL => 'https://api.ocr.space/parse/image',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ocr_space_fields($filePath, $engine, $scale),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 20,
    ];

    $bundle = ocr_ca_bundle();
    if ($bundle !== null) {
        $options[CURLOPT_CAINFO] = $bundle;
    }

    return $options;
}

function ocr_ssl_error(?string $error): bool
{
    return $error !== null && $error !== '' && stripos($error, 'SSL') !== false;
}

/**
 * OCR.Space throttles free keys under load and answers "please retry in a few
 * minutes". That is a temporary service condition, not a bad photo.
 */
function ocr_error_is_throttle(?string $error): bool
{
    if ($error === null || $error === '') {
        return false;
    }

    return preg_match('/throttl|overload|busy|E5\d{2}/i', $error) === 1;
}

function ocr_error_is_limit(?string $error): bool
{
    if ($error === null || $error === '') {
        return false;
    }

    return preg_match('/limit|too many|rate|exceeded|quota|throttl|overload|busy|E5\d{2}/i', $error) === 1;
}

/**
 * Perform one OCR request with a fresh handle. Using a new handle per attempt
 * avoids sticky error state (an SSL error from a first attempt otherwise
 * reappears after the option changes).
 *
 * @return array{response: string|false|null, error: string}
 */
function ocr_exec(string $filePath, string $engine, bool $scale, bool $relaxed): array
{
    $ch = curl_init();
    $options = ocr_curl_options($filePath, $engine, $scale);
    if ($relaxed) {
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $error = (string) curl_error($ch);

    return ['response' => $response, 'error' => $error];
}

function ocr_space(string $filePath, string $engine = '2', bool $scale = true): array
{
    $attempt = ocr_exec($filePath, $engine, $scale, false);

    // No CA bundle anywhere: retry once with relaxed verification so the
    // scanner still works on hosts that cannot validate certificates.
    if (($attempt['response'] === false || $attempt['response'] === null || $attempt['response'] === '') && ocr_ssl_error($attempt['error']) && ocr_ca_bundle() === null) {
        static $caWarned = false;
        if (!$caWarned) {
            $caWarned = true;
            error_log('scanID: no CA bundle available; retrying the OCR request with relaxed SSL verification.');
        }
        $attempt = ocr_exec($filePath, $engine, $scale, true);
    }

    if ($attempt['response'] === false || $attempt['response'] === null || $attempt['response'] === '') {
        return ['text' => '', 'error' => $attempt['error'] !== '' ? $attempt['error'] : 'OCR request failed', 'engine' => $engine];
    }

    $parsed = ocr_space_parse((string) $attempt['response'], $engine);

    // Free OCR.Space keys are throttled intermittently; one short retry often
    // succeeds and avoids telling the passenger their clear ID is unreadable.
    if (ocr_error_is_throttle($parsed['error'])) {
        usleep(900000);
        $retry = ocr_exec($filePath, $engine, $scale, ocr_ca_bundle() === null);
        if ($retry['response'] !== false && $retry['response'] !== null && $retry['response'] !== '') {
            $parsed = ocr_space_parse((string) $retry['response'], $engine);
        }
    }

    return $parsed;
}

/**
 * Send several OCR requests at once so the front and back of an ID are read
 * in a single round trip. $jobs maps a caller key to
 * ['path' => string, 'engine' => string, 'scale' => bool].
 *
 * @return array<string, array{text: string, error: ?string, engine: string}>
 */
function ocr_space_multi(array $jobs): array
{
    if (!function_exists('curl_multi_init')) {
        $results = [];
        foreach ($jobs as $key => $job) {
            $results[$key] = ocr_space((string) $job['path'], (string) $job['engine'], (bool) $job['scale']);
        }
        return $results;
    }

    $multi = curl_multi_init();
    $handles = [];

    foreach ($jobs as $key => $job) {
        $ch = curl_init();
        curl_setopt_array($ch, ocr_curl_options((string) $job['path'], (string) $job['engine'], (bool) $job['scale']));
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 0.5);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $error = curl_error($ch);
        curl_multi_remove_handle($multi, $ch);

        if ($body === false || $body === null || $body === '') {
            // A missing CA bundle breaks every parallel request the same way;
            // rerun this job through the single-request path which knows how
            // to retry with relaxed verification.
            if (ocr_ssl_error($error) && ocr_ca_bundle() === null) {
                $results[$key] = ocr_space((string) $jobs[$key]['path'], (string) $jobs[$key]['engine'], (bool) $jobs[$key]['scale']);
                continue;
            }
            $results[$key] = ['text' => '', 'error' => $error !== '' ? $error : 'OCR request failed', 'engine' => (string) $jobs[$key]['engine']];
            continue;
        }

        $results[$key] = ocr_space_parse((string) $body, (string) $jobs[$key]['engine']);
    }

    curl_multi_close($multi);

    // Retry throttled jobs once through the single-request path, which owns
    // the short backoff retry for the shared free key.
    foreach ($results as $key => $result) {
        if (ocr_error_is_throttle($result['error'])) {
            $results[$key] = ocr_space((string) $jobs[$key]['path'], (string) $jobs[$key]['engine'], (bool) $jobs[$key]['scale']);
        }
    }

    return $results;
}

function image_is_portrait(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $size = @getimagesize($path);
    return $size !== false && (int) ($size[1] ?? 0) > (int) ($size[0] ?? 0);
}

/**
 * Build a preprocessing variant when GD is available. Returns null when the
 * extension is missing so the caller can fall back to a different OCR config.
 */
function make_variant(string $sourcePath, string $variant): ?string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return null;
    }

    $bytes = @file_get_contents($sourcePath);
    if ($bytes === false) {
        return null;
    }

    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return null;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Rotation fixes photos where the card text runs vertically.
    if (($variant === 'rotate90' || $variant === 'rotate270') && function_exists('imagerotate')) {
        $angle = $variant === 'rotate90' ? -90 : 90;
        $rotated = @imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            @imagedestroy($image);
            $image = $rotated;
            $width = imagesx($image);
            $height = imagesy($image);
        }
    }

    if ($variant === 'upscale' && $width > 0 && $width < 1600) {
        $scale = min(2.0, 1600 / $width);
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
        $resized = @imagescale($image, $newWidth, $newHeight);
        if ($resized !== false) {
            @imagedestroy($image);
            $image = $resized;
        }
    }

    if (function_exists('imagefilter')) {
        if ($variant === 'gray' || $variant === 'contrast' || $variant === 'sharpen' || $variant === 'binarize') {
            @imagefilter($image, IMG_FILTER_GRAYSCALE);
        }
        if ($variant === 'contrast') {
            @imagefilter($image, IMG_FILTER_CONTRAST, -35);
            @imagefilter($image, IMG_FILTER_BRIGHTNESS, 14);
        }
        if ($variant === 'sharpen') {
            @imagefilter($image, IMG_FILTER_SMOOTH, -6);
            @imagefilter($image, IMG_FILTER_CONTRAST, -18);
        }
        if ($variant === 'binarize') {
            @imagefilter($image, IMG_FILTER_CONTRAST, -45);
            @imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
        }
    }

    $target = tempnam(sys_get_temp_dir(), 'ocrvar_');
    if ($target === false) {
        @imagedestroy($image);
        return null;
    }

    $jpeg = $target . '.jpg';
    @unlink($target);

    $ok = @imagejpeg($image, $jpeg, 88);
    @imagedestroy($image);

    return $ok ? $jpeg : null;
}

/**
 * OCR.Space rejects oversized uploads. Re-encode/resize when possible so a
 * large camera capture does not turn into an empty OCR response.
 */
function ensure_under_limit(string $path): string
{
    if (!is_file($path)) {
        return $path;
    }

    $size = @filesize($path);
    if ($size === false || $size <= OCR_MAX_UPLOAD_BYTES) {
        return $path;
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return $path;
    }

    $bytes = @file_get_contents($path);
    if ($bytes === false) {
        return $path;
    }

    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return $path;
    }

    $target = tempnam(sys_get_temp_dir(), 'ocrsize_');
    if ($target === false) {
        @imagedestroy($image);
        return $path;
    }
    $jpeg = $target . '.jpg';
    @unlink($target);

    for ($pass = 0; $pass < 3; $pass++) {
        @imagejpeg($image, $jpeg, 82 - ($pass * 12));
        clearstatcache(true, $jpeg);
        $newSize = @filesize($jpeg);
        if ($newSize !== false && $newSize <= OCR_MAX_UPLOAD_BYTES) {
            break;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 900) {
            break;
        }
        $resized = @imagescale($image, (int) round($width * 0.8));
        if ($resized === false) {
            break;
        }
        @imagedestroy($image);
        $image = $resized;
    }

    @imagedestroy($image);

    if (is_file($jpeg) && filesize($jpeg) > 0) {
        return $jpeg;
    }

    return $path;
}

/**
 * Rough text quality score (0-100) used to stop the attempt loop and to pick
 * the most reliable result. Rewards length, alphanumeric content, line count
 * and expected ID vocabulary.
 */
function ocr_score(string $text): int
{
    $text = trim($text);
    if ($text === '') {
        return 0;
    }

    $compact = (string) preg_replace('/\s+/', '', $text);
    $length = strlen($compact);
    $alnum = strlen((string) preg_replace('/[^A-Za-z0-9]/', '', $compact));

    $lines = preg_split('/\R+/', $text) ?: [];
    $usableLines = 0;
    foreach ($lines as $line) {
        if (strlen(trim($line)) >= 3) {
            $usableLines++;
        }
    }

    $score = min(50, $length / 5) + min(20, $alnum / 5) + min(15, $usableLines * 2);

    foreach (['NAME', 'BIRTH', 'DATE', 'ID', 'NO', 'SENIOR', 'ADDRESS', 'PWD', 'OSCA'] as $keyword) {
        if (stripos($text, $keyword) !== false) {
            $score += 3;
        }
    }

    return (int) min(100, round($score));
}

/**
 * Bonus for attempts whose text actually yields fields, so a noisy variant
 * with more characters cannot displace a cleaner, extractable result.
 */
function field_signal(string $text): int
{
    $signal = 0;

    if (extract_name($text) !== '') {
        $signal += 30;
    }
    if (extract_date_of_birth($text) !== '') {
        $signal += 25;
    }
    if (preg_match('/\b\d{4,}\b/', $text)) {
        $signal += 15;
    }
    if (preg_match('/\b(NAME|BIRTH|ADDRESS|ID\s*N0|ID\s*NO|OSCA|PWD)\b/i', $text)) {
        $signal += 15;
    }

    return $signal;
}

function quality_payload(string $level, int $score, int $usableChars): array
{
    $messages = [
        'good' => 'Looks good. Check the details and correct anything before continuing.',
        'partial' => 'Some details were unclear. Check or correct them, or retake the photo.',
        'none' => 'The scanner could not read enough text from this ID. Hold the card straight, avoid glare, fill the frame, and retake — or enter the details manually.',
    ];

    return [
        'level' => $level,
        'score' => $score,
        'usable_chars' => $usableChars,
        'message' => $messages[$level] ?? $messages['none'],
    ];
}

/* =========================================================
   Upload handling
   ========================================================= */

function save_upload(string $field, string $uploadDir): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $name = (string) ($_FILES[$field]['name'] ?? 'id.jpg');
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
    if ($safeName === '' || $safeName === null) {
        $safeName = 'id.jpg';
    }

    $target = rtrim($uploadDir, '/\\') . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $safeName;

    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return null;
    }

    return $target;
}

/* =========================================================
   Field extraction
   ========================================================= */

function parse_document(string $idType, array $frontTexts, array $backTexts, string $allText, string $discountHint = '', int $score = 100): array
{
    $profiles = type_profiles();
    $profile = $profiles[$idType] ?? null;

    $frontTexts = array_values(array_filter($frontTexts, static function ($t) {
        return trim((string) $t) !== '';
    }));
    $backTexts = array_values(array_filter($backTexts, static function ($t) {
        return trim((string) $t) !== '';
    }));

    $upper = strtoupper($allText);
    $flags = detect_type_flags($upper);

    $patterns = $profile['patterns'] ?? generic_id_patterns();
    $idExtractor = static function (string $text) use ($patterns): string {
        return extract_id_number($text, $patterns);
    };

    // Prefer the front for identity fields and the back for the address, but
    // fall back to either side so one weak photo cannot lose everything.
    $fullName = first_field($frontTexts, 'extract_name');
    if ($fullName === '') {
        $fullName = first_field($backTexts, 'extract_name');
    }

    $idNumber = first_field($frontTexts, $idExtractor);
    if ($idNumber === '') {
        $idNumber = first_field($backTexts, $idExtractor);
    }

    $dateOfBirth = first_field($frontTexts, 'extract_date_of_birth');
    if ($dateOfBirth === '') {
        $dateOfBirth = first_field($backTexts, 'extract_date_of_birth');
    }

    $address = first_field($backTexts, 'extract_address');
    if ($address === '') {
        $address = first_field($frontTexts, 'extract_address');
    }

    $detectedType = null;
    foreach ($flags as $candidate) {
        $detectedType = $candidate;
        break;
    }

    // Verify expected document type matches selected discount category (Section 7)
    $mismatchError = check_id_document_mismatch($idType, $discountHint, $flags);
    if ($mismatchError !== null) {
        return [
            'mismatch' => true,
            'message' => $mismatchError,
            'detected_type' => $detectedType,
        ];
    }

    $warning = null;
    if ($profile !== null && $detectedType !== null && $detectedType !== $profile['label']) {
        $warning = 'The photo looks more like "' . $detectedType . '". Please double-check the selected ID type.';
    }

    $course = extract_labeled_value($allText, ['COURSE', 'PROGRAM', 'DEGREE']);
    if ($course === '' && $idType === 'student-id') {
        $course = extract_course($allText);
    }
    $school = extract_school($allText);
    $studentNumber = $idType === 'student-id' ? extract_student_number($allText, $patterns) : '';
    $schoolYear = $idType === 'student-id' ? extract_school_year($allText) : '';
    $validity = extract_validity_dates($allText);
    $common = extract_common_fields($allText, $score, $idNumber, $dateOfBirth);

    $age = null;
    if ($dateOfBirth !== '') {
        try {
            $age = (new DateTime($dateOfBirth))->diff(new DateTime('today'))->y;
        } catch (Throwable $e) {
            $age = null;
        }
    }

    // OCR never grants the discount. It only reports what it read and how
    // confident it is; an administrator always approves the final status.
    $assessment = assess_document($idType, [
        'full_name' => $fullName,
        'id_number' => $idNumber,
        'date_of_birth' => $dateOfBirth,
        'student_number' => $studentNumber,
        'school' => $school,
        'school_year' => $schoolYear,
        'validity_from' => $validity['from'],
        'validity_until' => $validity['until'],
        'age' => $age,
        'low_quality' => $score < OCR_GOOD_SCORE,
    ], date('Y-m-d'));

    $badge = ['pending' => 'PENDING REVIEW', 'needs_review' => 'NEEDS REVIEW', 'expired' => 'EXPIRED — REVIEW'][$assessment['verification']] ?? 'PENDING REVIEW';

    $result = [
        'full_name' => $fullName,
        'id_number' => $idNumber,
        'date_of_birth' => $dateOfBirth,
        'address' => $address,
        'email' => $common['email'],
        // A phone number is only ever reported when one was actually detected
        // on the ID. The extractors use '' for "not found" internally, so the
        // null happens here at the response boundary.
        'phone' => $common['phone'] !== '' ? $common['phone'] : null,
        'id_type' => $profile['label'] ?? ($detectedType ?? 'Unknown'),
        'discount' => resolve_discount($flags, (string) ($profile['discount'] ?? ''), $discountHint),
        'detected_type' => $detectedType,
        'warning' => $warning,
        'requires_address' => $profile['requires_address'] ?? false,
        'course' => $course,
        'school' => $school,
        'school_year' => $schoolYear,
        'student_number' => $studentNumber,
        'validity_from' => $validity['from'],
        'validity_until' => $validity['until'],
        'validity_raw' => $validity['raw'],
        'verification' => $assessment['verification'],
        'review_flags' => $assessment['flags'],
        'status_badge' => $badge,
    ];

    if ($idType === 'student-id') {
        $result['student_id'] = $studentNumber !== '' ? $studentNumber : $idNumber;
    }

    foreach ($profile['extra_fields'] ?? [] as $field) {
        if ($field === 'profession') {
            $result['profession'] = extract_labeled_value($allText, ['PROFESSION', 'OCCUPATION']);
        }
        if ($field === 'nationality') {
            $result['nationality'] = extract_labeled_value($allText, ['NATIONALITY']);
        }
        if ($field === 'cooperative_name') {
            $result['cooperative_name'] = extract_labeled_value($allText, ['COOPERATIVE', 'COOP']);
        }
    }

    return $result;
}

/**
 * Decide the suggested verification state from what OCR actually read.
 *
 * This never returns "verified": only an administrator can grant that. The
 * result is one of pending / needs_review / expired plus the human-readable
 * reasons the admin review screen shows.
 */
function assess_document(string $idType, array $fields, string $today): array
{
    $flags = [];
    $verification = 'pending';
    $flag = static function (string $message, string $state = 'needs_review') use (&$flags, &$verification): void {
        $flags[] = $message;
        if ($state === 'expired' || ($state === 'needs_review' && $verification !== 'expired')) {
            $verification = $state;
        }
    };

    if (!empty($fields['low_quality'])) {
        $flag('The photo was difficult to read; verify each field against the document.');
    }
    if (empty($fields['full_name'])) {
        $flag('The full name was not clearly read.');
    }
    $expired = !empty($fields['validity_until']) && (string) $fields['validity_until'] < $today;

    switch ($idType) {
        case 'student-id':
            if (empty($fields['student_number'])) {
                $flag('The student number was not clearly read.');
            }
            if (empty($fields['school'])) {
                $flag('The school name was not clearly read.');
            }
            if (empty($fields['school_year'])) {
                $flags[] = 'The school year was not clearly read; confirm it with the document.';
            } else {
                $endYear = (int) substr((string) $fields['school_year'], -4);
                if ($endYear > 0 && $endYear < (int) substr($today, 0, 4)) {
                    $flag('The printed school year appears outdated.', 'expired');
                }
            }
            if ($expired) {
                $flag('The ID validity period appears to have ended.', 'expired');
            }
            // Card data alone cannot prove enrollment; the admin must confirm
            // this with the school before approving the discount.
            $flags[] = 'Confirm current enrollment before approving the student discount.';
            break;

        case 'senior-citizen-id':
        case 'philsys-id':
            if (empty($fields['date_of_birth'])) {
                $flag('The date of birth was not clearly read.');
            } elseif ($fields['age'] !== null && (int) $fields['age'] < 60) {
                $flag('The date of birth indicates an age below 60.');
            }
            if ($expired) {
                $flag('The ID expiry date has passed.', 'expired');
            }
            // OSCA IDs often have no expiry date; that is acceptable and is
            // never treated as a defect.
            break;

        case 'pwd-id':
            if (empty($fields['id_number'])) {
                $flag('The PWD ID number was not clearly read.');
            }
            if ($expired) {
                $flag('The PWD ID expiry date has passed.', 'expired');
            } elseif (empty($fields['validity_until'])) {
                $flags[] = 'No expiry date was detected; verify the PWD ID manually.';
            }
            break;

        default:
            if (empty($fields['id_number']) && empty($fields['date_of_birth'])) {
                $flag('Key ID details were not clearly read.');
            }
    }

    return ['verification' => $verification, 'flags' => array_values(array_unique($flags))];
}

/* =========================================================
   Server-side discount auto-verification (registration path)
   ========================================================= */

/**
 * Normalized tokens for a person name (case-insensitive, alphanumeric only),
 * used by ocr_fields_agree_name().
 */
function ocr_name_tokens(string $value): array
{
    $cleaned = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $value)));
    $tokens = preg_split('/\s+/', $cleaned);
    return array_values(array_filter(is_array($tokens) ? $tokens : [], static fn($t) => $t !== '' && $t !== null));
}

/**
 * Lenient-but-safe full-name agreement between the OCR text and the declared
 * name. Names with more than one part must share at least two parts, so a
 * single shared surname (or a lone OCR token) never passes. Order and
 * punctuation are ignored.
 */
function ocr_fields_agree_name(string $ocrName, string $declaredName): bool
{
    $a = ocr_name_tokens($ocrName);
    $b = ocr_name_tokens($declaredName);
    if ($a === [] || $b === []) {
        return false;
    }
    $common = 0;
    foreach ($b as $token) {
        if (in_array($token, $a, true)) {
            $common++;
        }
    }
    $required = (count($a) >= 2 || count($b) >= 2) ? 2 : 1;
    return $common >= $required;
}

/**
 * Normalize a birth date string to Y-m-d, or null when it cannot be read
 * reliably. Accepts Y-m-d, d-m-Y and DD/MM/YYYY plus the common 8-digit
 * digit-only layouts (YYYYMMDD / DDMMYYYY).
 */
function ocr_normalize_date(string $raw): ?string
{
    $digits = (string) preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) >= 8) {
        $y = (int) substr($digits, 0, 4);
        if ($y >= 1901 && $y <= 2099) {
            $m = (int) substr($digits, 4, 2);
            $d = (int) substr($digits, 6, 2);
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
        $d = (int) substr($digits, 0, 2);
        $m = (int) substr($digits, 2, 2);
        $y = (int) substr($digits, 4, 4);
        if ($y >= 1901 && $y <= 2099 && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        return null;
    }

    $clean = str_replace(['/', '.'], '-', trim($raw));
    foreach (['!Y-m-d', '!d-m-Y', '!m-d-Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $clean);
        if ($dt && $dt->format($format) === $clean && (int) $dt->format('Y') >= 1901 && (int) $dt->format('Y') <= 2099) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

/**
 * Normalize an ID number for comparison: uppercase alphanumeric only.
 */
function ocr_identity_number(string $raw): string
{
    return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', $raw));
}

/**
 * Agreement check between the declared and OCR-read ID numbers. Formatting
 * (spaces, dashes, slashes) is ignored. Near-identical values where one fully
 * contains the other are accepted, but only when both are substantial and the
 * length difference is small - so a truncated or unrelated number is rejected.
 */
function ocr_ids_agree(string $declared, string $parsed): bool
{
    $declared = ocr_identity_number($declared);
    $parsed = ocr_identity_number($parsed);
    if ($declared === '' || $parsed === '') {
        return false;
    }
    if ($declared === $parsed) {
        return true;
    }
    if (strlen($declared) < 8 || strlen($parsed) < 8 || abs(strlen($declared) - strlen($parsed)) > 2) {
        return false;
    }
    return strpos($declared, $parsed) !== false || strpos($parsed, $declared) !== false;
}

/**
 * Server-side discount auto-verification used at account creation.
 *
 * Runs clean OCR passes over the uploaded ID images - the front plus the back
 * when it is available - and combines both sides before comparing the
 * extracted details against the passenger's declared data, so a required
 * detail that only appears on one side is checked against the other before a
 * document is ever marked incomplete. Only a *server* verdict is produced - an
 * untrusted client value can never flip the result to "verified". A document
 * that cannot be read, does not match the declared details, or fails the
 * document's own validity rules stays `pending` / `needs_review` / `expired`
 * for administrator review.
 *
 * @param string   $imagePath    absolute path of the uploaded ID front image
 * @param array    $declared     declared registration data (full_name,
 *                               date_of_birth, student_id, id_type, discount)
 * @param callable|null $ocrProvider optional OCR override (tests); returns the
 *                               same array shape as ocr_space()/ocr_space_parse()
 * @param string   $backImagePath absolute path of the uploaded ID back image,
 *                               or '' when only the front was provided
 * @return array{checked: bool, status: string, score: int, flags: string[], reason: string}
 */
function resolve_auto_verification(string $imagePath, array $declared, ?callable $ocrProvider = null, string $backImagePath = ''): array
{
    $inconclusive = static fn(string $reason): array => ['checked' => false, 'status' => 'pending', 'score' => 0, 'flags' => [], 'reason' => $reason];

    if ($imagePath === '' || !is_file($imagePath)) {
        return $inconclusive('The uploaded document was not available for automatic verification.');
    }
    if ($ocrProvider === null) {
        $verified = ocr_recall_verified_document($imagePath, $backImagePath, $declared);
        if ($verified !== null) { return $verified; }
    }

    $idType = trim((string) ($declared['id_type'] ?? ''));
    $discountHint = trim((string) ($declared['discount'] ?? ''));

    $ocr = static function (string $path) use ($ocrProvider): array {
        try {
            if ($ocrProvider !== null) { return $ocrProvider($path); }
            $cached = ocr_cached_document_side($path);
            if ($cached !== null) { return $cached; }
            $side = run_ocr_side($path, 'verification', microtime(true) + 40);
            $texts = $side['texts'] ?? [(string) ($side['text'] ?? '')];
            ocr_cache_document_side($path, $texts, (int) ($side['score'] ?? 0));
            return ['text' => implode("\n", $texts), 'score' => (int) ($side['score'] ?? 0), 'error' => trim(implode("\n", $texts)) === ''];
        } catch (Throwable $e) {
            return ['text' => '', 'error' => true, 'score' => 0];
        }
    };

    $sides = ['front' => $ocr($imagePath)];
    $sides['back'] = ($backImagePath !== '' && is_file($backImagePath))
        ? $ocr($backImagePath)
        : ['text' => '', 'error' => true, 'score' => 0];

    $frontTexts = [];
    $backTexts = [];
    $allTextParts = [];
    $score = 0;
    foreach (['front', 'back'] as $sideName) {
        $sideText = trim((string) ($sides[$sideName]['text'] ?? ''));
        if ($sideText === '' || !empty($sides[$sideName]['error'])) {
            continue;
        }
        if ($sideName === 'front') {
            $frontTexts[] = $sideText;
        } else {
            $backTexts[] = $sideText;
        }
        $allTextParts[] = $sideText;
        $score = max($score, (int) ($sides[$sideName]['score'] ?? 0), ocr_score($sideText));
    }

    if (empty($allTextParts)) {
        return $inconclusive('The uploaded document could not be read automatically. An administrator will review it.');
    }

    // Parse combines both sides: identity fields prefer the front and fall
    // back to the back, address prefers the back, and the school / course /
    // student number / validity extractors search the merged text of both.
    $allText = implode("\n", $allTextParts);
    $score = max($score, ocr_score($allText));
    $parsed = parse_document($idType, $frontTexts, $backTexts, $allText, $discountHint, $score);

    if (!empty($parsed['mismatch'])) {
        $note = (string) ($parsed['message'] ?? 'The uploaded document does not match the selected discount category.');
        return ['checked' => true, 'status' => 'needs_review', 'score' => $score, 'flags' => [$note], 'reason' => $note];
    }

    // The scanner's own assessment escalates unreadable or invalid documents
    // to needs_review / expired (missing fields, under-60 senior, expired ID).
    $verification = (string) ($parsed['verification'] ?? 'needs_review');
    if ($verification !== 'pending') {
        $flags = (array) ($parsed['review_flags'] ?? []);
        return ['checked' => true, 'status' => $verification, 'score' => $score, 'flags' => $flags, 'reason' => implode(' ', $flags) ?: 'The uploaded document needs review.'];
    }

    $problems = [];

    // Name must agree on both sides.
    if (!ocr_fields_agree_name((string) ($parsed['full_name'] ?? ''), (string) ($declared['full_name'] ?? ''))) {
        $problems[] = 'The full name read from the ID does not match the name you entered.';
    }

    // A senior/philsys card must prove age 60+; ambiguous birth dates go to review.
    $dob = (string) ($parsed['date_of_birth'] ?? '');
    $age = null;
    if ($dob !== '') {
        try {
            $age = (new DateTime($dob))->diff(new DateTime('today'))->y;
        } catch (Throwable $e) {
            $age = null;
        }
    }
    if (in_array($idType, ['senior-citizen-id', 'philsys-id'], true)) {
        if ($age === null) {
            $problems[] = 'The birth date on the ID could not be confirmed; an administrator will verify it.';
        } elseif ($age < 60) {
            $problems[] = 'The date of birth indicates an age below the 60-year senior minimum.';
        }
    }

    // Declared vs OCR birth date cannot contradict each other.
    $declaredDob = (string) ($declared['date_of_birth'] ?? '');
    if ($declaredDob !== '') {
        $normParsed = ocr_normalize_date($dob);
        $normDeclared = ocr_normalize_date($declaredDob);
        if ($normParsed !== null && $normDeclared !== null && $normParsed !== $normDeclared) {
            $problems[] = 'The date of birth on the ID does not match the date you entered.';
        }
    }

    // The declared and OCR-read ID numbers must agree.
    if ($idType !== '') {
        $declaredId = ocr_identity_number((string) ($declared['student_id'] ?? ''));
        $parsedIdValue = (string) ($parsed['student_number'] ?? '');
        if ($parsedIdValue === '') {
            $parsedIdValue = (string) ($parsed['id_number'] ?? '');
        }
        if ($declaredId === '') {
            $problems[] = 'The ID number was not entered, so the document could not be auto-verified.';
        } elseif ($parsedIdValue === '' || !ocr_ids_agree($declaredId, $parsedIdValue)) {
            $problems[] = 'The ID number on the document does not match the one you entered.';
        }
    }

    if ($problems) {
        return ['checked' => true, 'status' => 'needs_review', 'score' => $score, 'flags' => $problems, 'reason' => implode(' ', $problems)];
    }

    return ['checked' => true, 'status' => 'verified', 'score' => $score, 'flags' => [], 'reason' => 'The uploaded document matched the declared details and was automatically verified.'];
}

function ocr_cache_document_side(string $path, array $texts, int $score): void
{
    if (!is_file($path)) { return; }
    $text = trim(implode("\n", array_filter($texts, 'is_string')));
    if ($text === '') { return; }
    $hash = hash_file('sha256', $path);
    if ($hash === false) { return; }
    $cache = $_SESSION['passenger_document_ocr'] ?? [];
    foreach ($cache as $key => $entry) {
        if (($entry['expires'] ?? 0) < time()) { unset($cache[$key]); }
    }
    $cache[$hash] = ['text' => $text, 'score' => $score, 'error' => false, 'expires' => time() + 3600];
    $_SESSION['passenger_document_ocr'] = array_slice($cache, -4, null, true);
}

/** A server-approved scan remains approved for these exact bytes and details. */
function ocr_remember_verified_document(string $front, string $back, array $declared, array $assessment): void
{
    if (($assessment['status'] ?? '') !== 'verified' || empty($assessment['checked']) || !is_file($front)) { return; }
    $frontHash = hash_file('sha256', $front);
    $backHash = $back !== '' && is_file($back) ? hash_file('sha256', $back) : '';
    if ($frontHash === false || $backHash === false) { return; }
    $_SESSION['passenger_verified_scan'] = ['front' => $frontHash, 'back' => $backHash,
        'declared' => $declared, 'assessment' => $assessment, 'expires' => time() + 3600];
}

function ocr_recall_verified_document(string $front, string $back, array $declared): ?array
{
    $scan = $_SESSION['passenger_verified_scan'] ?? null;
    if (!is_array($scan) || ($scan['expires'] ?? 0) < time() || !is_file($front)) { return null; }
    $backHash = $back !== '' && is_file($back) ? hash_file('sha256', $back) : '';
    if (hash_file('sha256', $front) !== $scan['front'] || $backHash !== $scan['back']) { return null; }
    $verified = $scan['declared'];
    foreach (['id_type', 'discount'] as $field) {
        if (strtolower(trim((string) ($verified[$field] ?? ''))) !== strtolower(trim((string) ($declared[$field] ?? '')))) { return null; }
    }
    if (!ocr_fields_agree_name((string) ($verified['full_name'] ?? ''), (string) ($declared['full_name'] ?? ''))
        || !ocr_ids_agree((string) ($verified['student_id'] ?? ''), (string) ($declared['student_id'] ?? ''))) { return null; }
    $dob = (string) ($verified['date_of_birth'] ?? '');
    $declaredDob = (string) ($declared['date_of_birth'] ?? '');
    if ($declaredDob !== '' && $dob !== '' && ocr_normalize_date($dob) !== ocr_normalize_date($declaredDob)) { return null; }
    return $scan['assessment'];
}

function ocr_cached_document_side(string $path): ?array
{
    if (!is_file($path)) { return null; }
    $hash = hash_file('sha256', $path);
    $entry = $hash !== false ? ($_SESSION['passenger_document_ocr'][$hash] ?? null) : null;
    return is_array($entry) && ($entry['expires'] ?? 0) >= time() ? $entry : null;
}

/**
 * Verify document matches the expected category and return specific mismatch
 * message if invalid.
 *
 * Any accepted label for the selected category satisfies the check, so a
 * correct document is never rejected just because another recognised keyword
 * was detected first. Only a document that clearly belongs to a different
 * category (or an unsupported one) is rejected.
 */
function check_id_document_mismatch(string $expectedIdType, string $discountHint, array $detectedFlags): ?string
{
    if (empty($detectedFlags)) {
        return null;
    }

    $normDiscount = strtolower(trim($discountHint));
    $normIdType = strtolower(trim($expectedIdType));

    $accepted = null;
    $rejection = '';
    if ($normIdType === 'student-id' || $normDiscount === 'student') {
        $accepted = ['Student ID'];
        $rejection = "This document does not appear to be a Student ID. Please upload your valid Student ID.";
    } elseif (in_array($normIdType, ['senior-citizen-id', 'philsys-id'], true) || in_array($normDiscount, ['senior', 'senior citizen'], true)) {
        $accepted = ['Senior Citizen ID', 'PhilSys / National ID'];
        $rejection = "This document does not appear to be an accepted Senior Citizen ID or National ID. Please upload a valid Senior Citizen ID or PhilSys / National ID.";
    } elseif ($normIdType === 'pwd-id' || $normDiscount === 'pwd') {
        $accepted = ['PWD ID'];
        $rejection = "This document does not appear to be a PWD ID. Please upload your valid PWD ID.";
    } elseif ($normIdType === 'teacher-id' || $normDiscount === 'teacher') {
        $accepted = ['Teacher / School ID'];
        $rejection = "This document does not appear to be a Teacher / School ID. Please upload your valid Teacher / School ID.";
    } elseif ($normIdType === 'prc-id' || $normDiscount === 'nurse') {
        $accepted = ['PRC ID'];
        $rejection = "This document does not appear to be a PRC ID. Please upload your valid PRC ID.";
    } elseif ($normIdType === 'coop-beneficiary-id' || in_array($normDiscount, ['cooperative', 'rpmvfmpc member', 'rpmvfmpc'], true)) {
        $accepted = ['Cooperative Beneficiary ID'];
        $rejection = "This document does not appear to be a Cooperative Beneficiary ID. Please upload your valid Cooperative Beneficiary ID.";
    }

    if ($accepted === null) {
        return null;
    }

    foreach ($detectedFlags as $flag) {
        if (in_array($flag, $accepted, true)) {
            return null;
        }
    }

    return $rejection;
}

/**
 * Try each OCR attempt's text in order and return the first value the
 * extractor can find. This keeps a field that only one attempt read.
 */
function first_field(array $texts, $extractor): string
{
    foreach ($texts as $text) {
        $value = call_user_func($extractor, (string) $text);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function type_profiles(): array
{
    return [
        'student-id' => [
            'label' => 'Student ID',
            'discount' => 'Student',
            'requires_address' => false,
            'patterns' => [
                '/\b\d{2}-\d{4}-\d{6}\b/',
                '/\b\d{4}[\s\-]?\d{2,6}\b/',
                '/\b\d{6,12}\b/',
            ],
            'extra_fields' => ['course'],
        ],
        'senior-citizen-id' => [
            'label' => 'Senior Citizen ID',
            'discount' => 'Senior',
            'requires_address' => true,
            'patterns' => [
                '/\bOSCA[\s\-]*[A-Z0-9]{2,}[\s\-]*\d*\b/',
                '/\b\d{4}[\s\-]?\d{4,8}\b/',
                '/\b\d{6,14}\b/',
            ],
            'extra_fields' => [],
        ],
        'philsys-id' => [
            'label' => 'PhilSys / National ID',
            'discount' => 'Senior',
            'requires_address' => true,
            'patterns' => [
                '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
                '/\b\d{12,16}\b/',
            ],
            'extra_fields' => [],
        ],
        'pwd-id' => [
            'label' => 'PWD ID',
            'discount' => 'PWD',
            'requires_address' => false,
            'patterns' => [
                '/\bPWD[\s\-]*[A-Z0-9]{1,}[\s\-]*\d*\b/',
                '/\b\d{4}[\s\-]?\d{4,8}\b/',
                '/\b\d{6,14}\b/',
            ],
            // No medical/disability fields are collected: the PWD ID only
            // proves discount eligibility, nothing else is needed.
            'extra_fields' => [],
        ],
        'teacher-id' => [
            'label' => 'Teacher / School ID',
            'discount' => 'Special',
            'requires_address' => false,
            'patterns' => [
                '/\b\d{4}[\s\-]?\d{2,8}\b/',
                '/\b\d{6,12}\b/',
            ],
            'extra_fields' => ['school'],
        ],
        'prc-id' => [
            'label' => 'PRC ID',
            'discount' => 'Special',
            'requires_address' => false,
            'patterns' => [
                '/\b\d{6,8}\b/',
            ],
            'extra_fields' => ['profession'],
        ],
        'coop-beneficiary-id' => [
            'label' => 'Cooperative Beneficiary ID',
            'discount' => 'Special',
            'requires_address' => true,
            'patterns' => [
                '/\b\d{4,12}\b/',
            ],
            'extra_fields' => ['cooperative_name'],
        ],
    ];
}

function generic_id_patterns(): array
{
    return [
        '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
        '/\b\d{4}[\s\-]?\d{2,8}\b/',
        '/\b\d{6,14}\b/',
    ];
}

function detect_type_flags(string $upper): array
{
    $flags = [];
    if (stripos($upper, 'SENIOR CITIZEN') !== false || stripos($upper, 'OSCA') !== false) {
        $flags[] = 'Senior Citizen ID';
    }
    if (stripos($upper, 'PWD') !== false || stripos($upper, 'PERSON WITH DISABILITY') !== false || stripos($upper, 'NATIONAL COUNCIL ON DISABILITY') !== false) {
        $flags[] = 'PWD ID';
    }
    if (stripos($upper, 'STUDENT') !== false || stripos($upper, 'COLLEGE') !== false || stripos($upper, 'UNIVERSITY') !== false || stripos($upper, 'DEPED') !== false || stripos($upper, 'CAMPUS') !== false || stripos($upper, 'MATRICULATION') !== false || stripos($upper, 'EDUCATION') !== false || stripos($upper, 'SCHOOL YEAR') !== false || preg_match('/\bS\.?\s*Y\.?\b/i', $upper)) {
        $flags[] = 'Student ID';
    }
    if (stripos($upper, 'PHILSYS') !== false || stripos($upper, 'PHILIPPINE IDENTIFICATION') !== false || stripos($upper, 'NATIONAL ID') !== false) {
        $flags[] = 'PhilSys / National ID';
    }
    if (stripos($upper, 'TEACHER') !== false || stripos($upper, 'FACULTY') !== false || stripos($upper, 'INSTRUCTOR') !== false || stripos($upper, 'PROFESSOR') !== false) {
        $flags[] = 'Teacher / School ID';
    }
    if (stripos($upper, 'PRC') !== false || stripos($upper, 'PROFESSIONAL REGULATION') !== false || stripos($upper, 'BOARD OF NURSING') !== false) {
        $flags[] = 'PRC ID';
    }
    if (stripos($upper, 'COOPERATIVE') !== false && stripos($upper, 'BENEFICIARY') !== false || stripos($upper, 'RPMVFMPC') !== false) {
        $flags[] = 'Cooperative Beneficiary ID';
    }

    // Unrelated documents (detected to reject mismatches)
    if (stripos($upper, 'DRIVER') !== false && stripos($upper, 'LICENSE') !== false) {
        $flags[] = "Driver's License";
    }
    if (stripos($upper, 'PASSPORT') !== false) {
        $flags[] = 'Passport';
    }
    if (stripos($upper, 'BARANGAY') !== false && stripos($upper, 'ID') !== false) {
        $flags[] = 'Barangay ID';
    }
    if (stripos($upper, 'POSTAL') !== false) {
        $flags[] = 'Postal ID';
    }
    if (stripos($upper, 'UMID') !== false || stripos($upper, 'UNIFIED MULTI') !== false) {
        $flags[] = 'UMID';
    }

    return $flags;
}

function discount_from_flags(array $flags, string $hint): string
{
    foreach ([['Student ID', 'Student'], ['Senior Citizen ID', 'Senior'], ['PWD ID', 'PWD'], ['Teacher / School ID', 'Special'], ['PRC ID', 'Special'], ['Cooperative Beneficiary ID', 'Special']] as [$flag, $discount]) {
        if (in_array($flag, $flags, true)) {
            return $discount;
        }
    }

    $hint = ucfirst(strtolower(trim($hint)));
    if (in_array($hint, ['Student', 'Senior', 'PWD', 'Special'], true)) {
        return $hint;
    }

    return '';
}

/**
 * Only return a discount when there is positive evidence (ID keywords or the
 * selected ID type / passenger type). Never forces "Regular" onto the user.
 */
function resolve_discount(array $flags, string $profileDiscount, string $hint): string
{
    $fromFlags = discount_from_flags($flags, '');
    if ($fromFlags !== '') {
        return $fromFlags;
    }

    if ($profileDiscount !== '' && strcasecmp($profileDiscount, 'Regular') !== 0) {
        return $profileDiscount;
    }

    return discount_from_flags([], $hint);
}

function extract_name(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $sawNameLabel = false;

    for ($i = 0; $i < count($lines); $i++) {
        $rawLine = trim($lines[$i]);
        $upperLine = strtoupper($rawLine);
        if ($upperLine === '') {
            continue;
        }

        // Other "NAME" labels that are not the person's name (school, employer,
        // guardian, emergency contact, course, etc.).
        if (preg_match('/\b(SCHOOL|COLLEGE|UNIVERSITY|INSTITUTE|COMPANY|EMPLOYER|ORGANIZATION|OFFICE|BUSINESS|COURSE|PROGRAM|DEGREE|MOTHER|FATHER|PARENT|GUARDIAN|EMERGENCY|SPOUSE|BENEFICIARY|CONTACT|COOPERATIVE|BARANGAY)\s*(?:\'S)?\s+NAME\b/i', $upperLine)
            || preg_match('/\bNAME\s+OF\s+(SCHOOL|COLLEGE|UNIVERSITY|INSTITUTE|COMPANY|EMPLOYER|ORGANIZATION|OFFICE|BUSINESS|STUDENT|GUARDIAN|MOTHER|FATHER|PARENT|EMERGENCY)\b/i', $upperLine)) {
            continue;
        }

        if (!preg_match('/\b(FULL\s*NAME|PANGALAN|PANGARAN|STUDENT\s+NAME|NAME\s+OF\s+STUDENT|NAME)\b/', $upperLine)) {
            continue;
        }

        $sawNameLabel = true;

        $value = trim((string) preg_replace('/^.*?\b(FULL\s*NAME|PANGALAN|PANGARAN|STUDENT\s+NAME|NAME\s+OF\s+STUDENT|NAME)\b[\s:.\-]*/i', '', $rawLine));
        if (name_score($value) >= 2) {
            return format_name($value);
        }

        for ($j = $i + 1; $j <= $i + 2 && $j < count($lines); $j++) {
            $next = trim($lines[$j]);
            if (preg_match('/\b(SCHOOL|COLLEGE|UNIVERSITY|INSTITUTE|COMPANY|EDUCATION|ADDRESS|BIRTH)\b/i', $next)) {
                break;
            }
            if (name_score($next) >= 2) {
                return format_name($next);
            }
        }
    }

    // A card that prints a NAME label but whose value could not be read must
    // go to manual review - never fall back to the school line.
    if ($sawNameLabel) {
        return '';
    }

    // Label-less layout: choose the single most name-like line. School lines
    // are excluded by keyword, by the extracted school value, and an ambiguous
    // tie returns '' so the admin reviews instead of receiving a school name.
    $school = extract_school($text);
    $best = '';
    $bestScore = 1;
    $ties = 0;
    foreach ($lines as $line) {
        $candidate = trim($line);
        if ($candidate === '') {
            continue;
        }
        if (has_school_keyword($candidate)) {
            continue;
        }
        if ($school !== '' && strcasecmp($candidate, trim($school)) === 0) {
            continue;
        }

        $score = name_score($candidate);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $candidate;
            $ties = 0;
        } elseif ($score === $bestScore && $best !== '' && $score > 1) {
            $ties++;
        }
    }

    if ($ties > 0) {
        return '';
    }

    return $best !== '' ? format_name($best) : '';
}

/**
 * Score how much a line looks like a person's name. Organization words,
 * digits and implausible shapes score below the acceptance threshold.
 */
function name_score(string $candidate): int
{
    $candidate = trim($candidate);
    if ($candidate === '' || strlen($candidate) < 3 || strlen($candidate) > 60) {
        return -100;
    }

    if (preg_match('/\d/', $candidate)) {
        return -100;
    }

    foreach (name_org_words() as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $candidate)) {
            return -100;
        }
    }

    $words = preg_split('/\s+/', $candidate) ?: [];
    $wordCount = count($words);
    if ($wordCount < 2 || $wordCount > 5) {
        return -100;
    }

    $score = 0;
    foreach ($words as $word) {
        $letters = (string) preg_replace('/[^A-Za-z]/', '', $word);
        $length = strlen($letters);
        if ($length === 0) {
            return -100;
        }
        if ($length === 1 && !preg_match('/^[A-Za-z]\.?$/', $word)) {
            return -100;
        }
        $score += 1;
    }

    if (strpos($candidate, ',') !== false) {
        $score += 2; // "SANTOS, MARIA V."
    }
    if (preg_match('/\b[A-Za-z]\./', $candidate)) {
        $score += 2; // middle initial "V."
    }

    return $score;
}

/* =========================================================
   Label tables shared by the address and phone extractors.
   Every pattern tolerates the OCR forms already handled
   elsewhere in this file: any letter case, an optional
   trailing "." ":" or "-", and "N0" for "No".
   ========================================================= */

/**
 * Build a regex alternation from label words, tolerating the OCR
 * renderings of each word (N0 for No, optional . : - separators).
 */
function label_pattern(array $labels): string
{
    $parts = [];
    foreach ($labels as $label) {
        $words = preg_split('/\s+/', strtoupper($label));
        $tolerant = [];
        foreach ($words as $word) {
            $tolerant[] = str_replace('NO', 'N[O0]', preg_quote($word, '/'));
        }
        $parts[] = '\b' . implode('\s*', $tolerant) . '\b';
    }

    return '/' . implode('|', $parts) . '/i';
}

/**
 * Labels that introduce an address field.
 */
function address_labels(): array
{
    return [
        'RESIDENTIAL ADDRESS',
        'HOME ADDRESS',
        'ADDRESS',
        'ADDR',
        'TIRAHAN',
    ];
}

/**
 * Labels that mark the start of a different printed field, and so
 * end an address run.
 */
function address_stop_labels(): array
{
    return [
        'FULL NAME', 'PANGALAN', 'PANGARAN', 'NAME',
        'ID NO', 'ID NUMBER', 'I.D. NO', 'NUMBER', 'NO',
        'OSCA', 'PWD', 'PRC', 'UMID', 'CRN', 'PSN',
        'STUDENT NO', 'STUDENT NUMBER', 'LICENSE NO', 'PASSPORT NO',
        'DATE OF BIRTH', 'BIRTH DATE', 'BIRTHDAY', 'DOB', 'BIRTH',
        'PHONE', 'MOBILE', 'CONTACT', 'TELEPHONE', 'TEL',
        'EMAIL', 'E-MAIL',
        'SIGNATURE', 'SIGNED', 'NATIONALITY', 'RELIGION',
        'CIVIL STATUS', 'MARITAL STATUS', 'OCCUPATION', 'PROFESSION',
        'DATE ISSUED', 'DATE EXPIRES', 'VALID UNTIL', 'EXPIRY',
        'SEX', 'GENDER', 'HEIGHT', 'WEIGHT',
    ];
}

/**
 * Words that show a line really belongs to an address.
 */
function address_component_words(): array
{
    return [
        'ADDRESS', 'TIRAHAN', 'PUROK', 'PUROKS', 'PUROK BLK',
        'BRGY', 'BARANGAY', 'POBLACION', 'SITIO',
        'STREET', 'ST.', 'ST', 'ROAD', 'RIDG',
        'CITY', 'MUNICIPALITY', 'MUNICIPAL', 'PROVINCE', 'PROV',
    ];
}

/**
 * Titles and issuing offices that appear on Philippine IDs.
 *
 * These are needed as a separate list because the component gate cannot reject
 * them on its own: a signature title such as "CITY MAYOR" or "MUNICIPAL MAYOR"
 * contains an address component word and would otherwise be accepted as an
 * address when the ID prints no address at all.
 */
function address_title_words(): array
{
    return [
        'MAYOR', 'VICE MAYOR', 'GOVERNOR', 'VICE GOVERNOR',
        'COUNCILOR', 'COUNCILLOR',
        'CONGRESSMAN', 'CONGRESSWOMAN', 'CONGRESSMAN/WOMAN', 'CONGRESSMAN/WOMAN',
        'REPRESENTATIVE', 'SENATOR',
        'CAPTAIN', 'BRGY CAPTAIN', 'BARANGAY CAPTAIN', 'PUNTOD',
        'CHAIRMAN', 'CHAIRWOMAN', 'CHAIRPERSON', 'CHAIRMAN/WOMAN',
        'SECRETARY', 'ATTORNEY', 'ATTY', 'NOTARY', 'NOTARY PUBLIC',
        'HON', 'OFFICE', 'OFFICIAL', 'DIRECTOR', 'COMMISSIONER',
    ];
}

/**
 * True when a line reads as a signature title or issuing office rather than a
 * postal address.
 */
function is_address_title_line(string $line): bool
{
    foreach (address_title_words() as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $line)) {
            return true;
        }
    }

    return false;
}

/**
 * Labels that introduce a phone / contact number.
 */
function phone_labels(): array
{
    return [
        'PHONE NUMBER', 'PHONE NO', 'PHONE',
        'MOBILE NUMBER', 'MOBILE NO', 'MOBILE',
        'CONTACT NUMBER', 'CONTACT NO', 'CONTACT',
        'TELEPHONE NUMBER', 'TELEPHONE NO', 'TELEPHONE',
        'TEL NO', 'TEL',
        'CP NO', 'CELL NO',
    ];
}

/**
 * Labels whose value must never be taken as a phone number.
 */
function competing_id_labels(): array
{
    return [
        'ID NO', 'ID NUMBER', 'I.D. NO', 'NO', 'NUMBER',
        'OSCA', 'PWD', 'PRC', 'UMID', 'CRN', 'PSN',
        'STUDENT NO', 'STUDENT NUMBER', 'SCHOOL ID',
        'LICENSE NO', 'PASSPORT NO', 'BRGY ID NO', 'COOP ID NO',
        'REGISTRATION NO', 'REG NO', 'HOUSE NO', 'BLK NO',
        'ZIP', 'ZIP CODE', 'POSTAL CODE', 'POST CODE',
        'DATE OF BIRTH', 'BIRTH DATE', 'BIRTHDAY', 'DOB', 'BIRTH',
        'DATE', 'ISSUED', 'EXPIRES',
    ];
}

/**
 * Tag one OCR line for the extractors:
 *   phone_label    - introduces a phone / contact number
 *   competing_label- introduces a value that is not a phone number
 *   date_like      - a date, so its digits are not a phone number
 *   neutral        - anything else
 */
function classify_line(string $line): string
{
    $line = trim($line);
    if ($line === '') {
        return 'neutral';
    }

    if (preg_match(label_pattern(phone_labels()), $line)) {
        return 'phone_label';
    }

    if (preg_match(label_pattern(competing_id_labels()), $line)) {
        return 'competing_label';
    }

    if (preg_match('/\b(\d{1,2}[\-\/.]\d{1,2}[\-\/.]\d{2,4}|\d{4}[\-\/]\d{1,2}[\-\/]\d{1,2})\b/', $line)) {
        return 'date_like';
    }

    return 'neutral';
}

function name_org_words(): array
{
    return [
        'UNIVERSITY', 'COLLEGE', 'SCHOOL', 'INSTITUTE', 'ACADEMY', 'EDUCATION', 'EDUCATIONAL',
        'FOUNDATION', 'POLYTECHNIC', 'NETWORK', 'CAMPUS', 'LEARNING', 'TRAINING', 'TECHNICAL',
        'TECHNOLOGY', 'LYCEUM', 'SEMINARY', 'NATIONAL', 'GOVERNMENT', 'REPUBLIC', 'PHILIPPINES', 'CITY', 'MUNICIPALITY',
        'BARANGAY', 'OFFICE', 'DEPARTMENT', 'COMPANY', 'CORPORATION', 'CORP', 'INC', 'ENTERPRISES',
        'SENIOR', 'CITIZEN', 'IDENTIFICATION', 'PWD', 'DISABILITY', 'STUDENT', 'PASSENGER', 'DRIVER',
        'LICENSE', 'PASSPORT', 'PHILSYS', 'UMID', 'POSTAL', 'COOPERATIVE', 'BENEFICIARY', 'ADDRESS',
        'BIRTH', 'SIGNATURE', 'NATIONALITY', 'GENDER', 'VALID', 'EXPIRY', 'ISSUED', 'EMERGENCY',
        'CONTACT', 'GUARDIAN', 'PARENT', 'MOTHER', 'FATHER', 'MEMBER', 'REGULAR', 'DISCOUNT',
        'REMARKS', 'NUMBER', 'NO', 'ID', 'PERSON', 'NOTIFY', 'RECEIPT', 'PAYMENT', 'AMOUNT',
    ];
}

function is_plausible_name(string $value): bool
{
    $value = trim($value);
    if (strlen($value) < 3 || strlen($value) > 60) {
        return false;
    }

    if (preg_match('/\d/', $value)) {
        return false;
    }

    $letters = strlen((string) preg_replace('/[^A-Za-z]/', '', $value));
    if ($letters < 3) {
        return false;
    }

    return true;
}

function format_name(string $value): string
{
    $value = (string) preg_replace('/[^A-Za-z ,.\-Ññ]/u', ' ', $value);
    $value = (string) preg_replace('/\s+/', ' ', trim($value));

    if ($value === strtoupper($value)) {
        $value = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    return $value;
}

function extract_id_number(string $text, array $patterns): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $labels = ['ID\s*N0', 'ID\s*NO', 'I\.?D\.?\s*NO', '1D\s*NO', 'IDNO', 'ID\s*NUMBER', 'NUMBER',
        'OSCA\s*(?:ID\s*)?(?:NO|N0|NUMBER)?', 'PWD\s*(?:ID\s*)?(?:NO|N0|NUMBER)?', 'LICENSE\s*NO',
        'PASSPORT\s*NO', 'UMID\s*NO', 'CRN', 'PSN', 'BRGY\s*(?:ID\s*)?NO', 'COOP\s*(?:ID\s*)?NO'];
    $labelPattern = '/(' . implode('|', $labels) . ')\s*[:.\-]?\s*([A-Z0-9][A-Z0-9\-\s]{4,24})/i';

    foreach ($lines as $index => $line) {
        if (preg_match($labelPattern, $line, $match)) {
            $candidate = normalize_id_candidate($match[2]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (isset($lines[$index + 1]) && preg_match('/\b(' . implode('|', $labels) . ')\b/i', $line)) {
            $nextLine = (string) $lines[$index + 1];
            // A next line that is itself a label ("PWD ID" followed by
            // "PWD NO: 1234") is a header, not the value. Skip it so the
            // label cannot swallow the following label as an ID number.
            if (preg_match('/\b(' . implode('|', $labels) . ')\b/i', $nextLine)) {
                continue;
            }
            $candidate = normalize_id_candidate($nextLine);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $candidate = normalize_id_candidate($match[0]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function normalize_id_candidate(string $value): string
{
    $value = strtoupper(trim($value));
    $value = (string) preg_replace('/\s+/', ' ', $value);
    $value = trim($value, " \t\n\r\0\x0B.,:;#");

    if ($value === '') {
        return '';
    }

    $digits = strlen((string) preg_replace('/[^0-9]/', '', $value));
    if ($digits < 4) {
        return '';
    }

    // Common OCR confusions inside mostly-numeric IDs.
    if ($digits >= 6) {
        $value = str_replace(['O', 'I', 'L', 'S', 'B', 'Z'], ['0', '1', '1', '5', '8', '2'], $value);
    }

    return $value;
}

function extract_date_of_birth(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $labelPattern = '/\b(DATE\s*OF\s*BIRTH|BIRTH\s*DATE|BIRTHDAY|DOB|PETSANG?\s*KAPANGANAKAN|PETSA)\b/i';

    for ($i = 0; $i < count($lines); $i++) {
        if (preg_match($labelPattern, $lines[$i])) {
            $same = extract_date_from_line($lines[$i]);
            if ($same !== '') {
                return $same;
            }
            for ($j = $i + 1; $j <= $i + 2 && $j < count($lines); $j++) {
                $next = extract_date_from_line($lines[$j]);
                if ($next !== '') {
                    return $next;
                }
            }
        }
    }

    // Fallback: the first date that is not part of a different printed field.
    // Validity/issued labels are skipped so an expiry date is never reported
    // as the date of birth.
    foreach ($lines as $line) {
        if (preg_match('/\b(VALID\s*(?:UNTIL|THRU|THROUGH|UP\s*TO)?|VALIDITY|VALID\s*FROM|VALID\s*DATES?|EXPIRES?|EXPIRY|DATE\s*OF\s*EXPIRY|ISSUED|DATE\s*ISSUED|SCHOOL\s*YEAR|S\.?\s*Y\.?)\b/i', $line)) {
            continue;
        }
        $value = extract_date_from_line($line);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function extract_date_from_line(string $line, bool $allowFuture = false): string
{
    $months = 'JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER|JAN|FEB|MAR|APR|JUN|JUL|AUG|SEP|OCT|NOV|DEC';

    if (preg_match('/\b(' . $months . ')\s+(\d{1,2}),?\s+(\d{4})\b/i', $line, $m)) {
        $month = date('m', strtotime($m[1] . ' 1 2000'));
        return build_date((int) $m[3], (int) $month, (int) $m[2], $allowFuture);
    }

    if (preg_match('/\b(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})\b/', $line, $m)) {
        return build_date((int) $m[1], (int) $m[2], (int) $m[3], $allowFuture);
    }

    if (preg_match('/\b(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{2,4})\b/', $line, $m)) {
        $year = (int) $m[3];
        if ($year < 100) {
            $year += $year > 30 ? 1900 : 2000;
        }
        $first = (int) $m[1];
        $second = (int) $m[2];

        $month = $first;
        $day = $second;
        if ($month > 12 && $second <= 12) {
            $month = $second;
            $day = $first;
        }

        return build_date($year, $month, $day, $allowFuture);
    }

    return '';
}

function build_date(int $year, int $month, int $day, bool $allowFuture = false): string
{
    $maxYear = (int) date('Y') + ($allowFuture ? 30 : 0);
    if ($year < 1900 || $year > $maxYear || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return '';
    }

    if (!checkdate($month, $day, $year)) {
        return '';
    }

    // Birth dates cannot be in the future, so the age gate only applies to
    // them. Validity dates legitimately sit after today.
    if (!$allowFuture) {
        $age = (int) (new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day)))->diff(new DateTime())->y;
        if ($age < 0 || $age > 120) {
            return '';
        }
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Build an address from the OCR text by following the printed address label,
 * never by a fixed line index. When a label is found the run continues over
 * the following lines until a line belonging to a different printed field
 * begins, so multi-line addresses are kept whole.
 */
function extract_address(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $labelRe = label_pattern(address_labels());
    $stopRe = label_pattern(address_stop_labels());

    // Pass 1: label-anchored. The label may sit on its own line (value below it)
    // or on the same line as the first address segment.
    for ($i = 0; $i < count($lines); $i++) {
        $line = (string) $lines[$i];
        if (!preg_match($labelRe, $line, $match)) {
            continue;
        }

        $inline = trim((string) preg_replace(
            '/^.*?' . preg_quote($match[0], '/') . '[\s:.\-]*/i',
            '',
            $line
        ));

        $segments = [];
        if (strlen($inline) >= 5 && !is_address_title_line($inline)) {
            $segments[] = $inline;
        }

        for ($j = $i + 1; $j < count($lines); $j++) {
            $next = trim((string) $lines[$j]);

            // A blank line neither ends the run nor contributes a segment.
            if ($next === '') {
                continue;
            }

            // Another printed field, or a value with no letters (a stray number
            // from the next field), ends the address run.
            if (preg_match($stopRe, $next) || !preg_match('/[A-Za-z]/', $next)) {
                break;
            }

            // A signature title ("CITY MAYOR") sits between address lines on
            // some cards. Drop it without ending the run so the real address
            // lines on either side still join up.
            if (is_address_title_line($next)) {
                continue;
            }

            $segments[] = $next;
        }

        $address = address_from_segments($segments);
        if ($address !== '') {
            return $address;
        }
    }

    // Pass 2: component-anchored fallback for cards that print the address with
    // no label at all. Only used when no labelled run was found.
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strlen($trimmed) < 8) {
            continue;
        }

        $kind = classify_line($trimmed);
        if ($kind === 'competing_label' || $kind === 'date_like') {
            continue;
        }

        if (!has_address_component($trimmed)) {
            continue;
        }

        // A signature title or issuing office can contain an address component
        // word ("CITY MAYOR"), so it must be rejected explicitly.
        if (is_address_title_line($trimmed)) {
            continue;
        }

        $address = address_from_segments([$trimmed]);
        if ($address !== '') {
            return $address;
        }
    }

    return '';
}

/**
 * Join collected address lines into one value and hand it to clean_address(),
 * which drops blank and repeated segments and enforces the length cap.
 */
function address_from_segments(array $segments): string
{
    $kept = [];
    foreach ($segments as $segment) {
        $value = (string) preg_replace('/\s+/', ' ', trim((string) $segment));
        if ($value !== '') {
            $kept[] = $value;
        }
    }

    if ($kept === []) {
        return '';
    }

    $joined = clean_address(implode(', ', $kept));

    // The component gate is what stops a label plus unrelated OCR text from
    // being reported as an address.
    return has_address_component($joined) ? $joined : '';
}

/**
 * True when the text carries a word that shows it really is an address.
 */
function has_address_component(string $value): bool
{
    foreach (address_component_words() as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $value)) {
            return true;
        }
    }

    return false;
}

function clean_address(string $value): string
{
    $value = (string) preg_replace('/\s+/', ' ', trim($value));

    // Drop blank segments and any segment the previous one already covers, so a
    // repeated OCR line is not emitted twice.
    $segments = [];
    foreach (explode(',', $value) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        $duplicate = false;
        foreach ($segments as $kept) {
            if (stripos($kept, $segment) !== false || stripos($segment, $kept) !== false) {
                $duplicate = true;
                break;
            }
        }
        if (!$duplicate) {
            $segments[] = $segment;
        }
    }

    return mb_substr(implode(', ', $segments), 0, 120);
}

function extract_labeled_value(string $text, array $labels): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $pattern = '/\b(' . implode('|', array_map(function ($l) {
        return preg_quote($l, '/');
    }, $labels)) . ')\b\s*[:.\-]?\s*(.+)$/i';

    foreach ($lines as $line) {
        if (preg_match($pattern, trim($line), $m)) {
            $value = trim($m[2]);
            if (strlen($value) >= 2) {
                return mb_substr($value, 0, 80);
            }
        }
    }

    return '';
}

/**
 * Extract the school / institution name.
 *
 * A school line is recognised by an institution keyword and by NOT looking
 * like a person's name (name_score rejects organisation words). This is what
 * keeps "UNIVERSITY OF SAINT LOUIS" from ever being read as the passenger's
 * full name, and the passenger's name from being read as the school.
 */
function extract_school(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];

    // Pass 1: an explicit "SCHOOL NAME: X" / "NAME OF SCHOOL: X" label.
    foreach ($lines as $line) {
        if (preg_match('/\b(SCHOOL\s*NAME|NAME\s*OF\s*SCHOOL)\b\s*[:.\-]?\s*(.+)$/i', trim($line), $m)) {
            $value = clean_school_value($m[2]);
            if ($value !== '' && !looks_like_person_name($value)) {
                return $value;
            }
        }
    }

    // Pass 2: a line that contains an institution keyword and reads as an
    // organisation, not a person, address or ID. When several lines qualify,
    // the most specific institution wins so an ID that prints both a parent
    // organisation / umbrella brand ("PHINMA EDUCATION") and the actual school
    // ("ARAULLO UNIVERSITY", "MALIGAYA ELEMENTARY SCHOOL") yields the school.
    // The first line at the best rank is kept, preserving card order on a tie.
    $best = '';
    $bestRank = 0;
    foreach ($lines as $line) {
        $candidate = trim($line);
        if (!looks_like_school_line($candidate)) {
            continue;
        }
        $rank = school_line_rank($candidate);
        if ($rank > $bestRank) {
            $bestRank = $rank;
            $best = $candidate;
        }
    }

    if ($best !== '') {
        return clean_school_value($best);
    }

    return '';
}

function school_keywords(): array
{
    return ['UNIVERSITY', 'COLLEGE', 'SCHOOL', 'INSTITUTE', 'INSTITUTION', 'ACADEMY', 'POLYTECHNIC', 'LYCEUM', 'SEMINARY', 'EDUCATION', 'EDUCATIONAL'];
}

/**
 * Keywords that name a specific institution rather than a parent organisation
 * or umbrella brand. A line carrying one of these is a stronger school match
 * than a generic "Education" line.
 */
function specific_institution_keywords(): array
{
    return ['UNIVERSITY', 'COLLEGE', 'SCHOOL', 'INSTITUTE', 'INSTITUTION', 'ACADEMY', 'POLYTECHNIC', 'LYCEUM', 'SEMINARY', 'CONSERVATORY', 'CAMPUS'];
}

/**
 * Rank a school-like line: 2 for a specific institution type, 1 for an
 * umbrella/parent organisation that only carries a generic education word.
 */
function school_line_rank(string $line): int
{
    $line = trim($line);
    foreach (specific_institution_keywords() as $keyword) {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $line)) {
            return 2;
        }
    }

    return has_school_keyword($line) ? 1 : 0;
}

function has_school_keyword(string $line): bool
{
    foreach (school_keywords() as $keyword) {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $line)) {
            return true;
        }
    }

    return false;
}

/**
 * A line that plausibly prints an institution name: has an institution
 * keyword, no digits (so not an address or an ID number), and a sane number
 * of words.
 */
function looks_like_school_line(string $line): bool
{
    $line = trim($line);
    if (!has_school_keyword($line)) {
        return false;
    }

    if (preg_match('/\d/', $line) || !preg_match('/[A-Za-z]{3,}/', $line)) {
        return false;
    }

    $words = preg_split('/\s+/', $line) ?: [];
    $count = count($words);
    if ($count < 2 || $count > 14) {
        return false;
    }

    return true;
}

function looks_like_person_name(string $value): bool
{
    return name_score($value) >= 2;
}

/**
 * Course/program on a label-less student ID, e.g. "BS INFORMATION
 * TECHNOLOGY". Only well-known degree shapes qualify so an address or a name
 * line is never mistaken for a course.
 */
function extract_course(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $known = '/\b(INFORMATION\s+TECHNOLOGY|COMPUTER\s+SCIENCE|ACCOUNTANCY|ACCOUNTING|EDUCATION|ENGINEERING|NURSING|CRIMINOLOGY|BUSINESS|COMMERCE|HOSPITALITY|TOURISM|AGRICULTURE|ARCHITECTURE|PSYCHOLOGY|MIDWIFERY|SOCIAL\s+WORK|MARINE|AVIATION|HUMANITIES|SCIENCE|ARTS)\b/i';
    $degree = '/\b(BACHELOR|ASSOCIATE|MASTER|MASTERS|DOCTOR|DOCTORATE|DIPLOMA|CERTIFICATE|BS|BA|AB|BSCS|BSIT|BSED|BEED|BSN|BSBA|MBA|MS|MA|PHD)\b/i';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || preg_match('/\d/', $line) || mb_strlen($line) > 80) {
            continue;
        }
        if (has_school_keyword($line)) {
            continue;
        }
        if (preg_match($known, $line) || (preg_match($degree, $line) && preg_match('/^[A-Z]{2,4}\s/i', $line))) {
            return mb_substr($line, 0, 80);
        }
    }

    return '';
}

function clean_school_value(string $value): string
{
    $value = (string) preg_replace('/^.*?\b(SCHOOL\s*NAME|NAME\s*OF\s*SCHOOL)\b[\s:.\-]*/i', '', $value);
    // Strip a leading field label such as "SCHOOL: " / "UNIVERSITY: " so only
    // the printed institution name is kept (a colon marks it as a label, so a
    // legitimate name that merely starts with a keyword is never trimmed).
    $value = (string) preg_replace('/^\s*(?:SCHOOL|UNIVERSITY|COLLEGE|INSTITUTE|ACADEMY|NAME\s*OF\s*SCHOOL|SCHOOL\s*NAME)\s*:\s*/i', '', $value);
    $value = (string) preg_replace('/[^A-Za-z0-9 ,.\-&()\'Ññ]/u', ' ', $value);
    $value = (string) preg_replace('/\s+/', ' ', trim($value));
    if ($value === strtoupper($value)) {
        $value = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    return mb_substr($value, 0, 100);
}

/**
 * Student number / LRN. Label-anchored first so an unrelated ID number is
 * not mistaken for the student number, with the profile patterns as fallback.
 */
function extract_student_number(string $text, array $patterns): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $labelRe = '/\b(STUDENT\s*(?:NO|N0|NO\.|NUMBER|ID|ID\s*NO|ID\s*NUMBER)|LRN|LEARNER\s*REFERENCE\s*NUMBER|SCHOOL\s*ID\s*(?:NO|N0|NUMBER)?|ID\s*NO|ID\s*NUMBER)\b/i';
    $looksLikeLabel = '/\b(STUDENT\s*(?:NO|N0|NO\.|NUMBER|ID|ID\s*NO|ID\s*NUMBER)|LRN|LEARNER\s*REFERENCE\s*NUMBER|SCHOOL\s*ID)\b/i';

    foreach ($lines as $index => $line) {
        if (preg_match($labelRe, $line, $match)) {
            $inline = trim((string) preg_replace('/^.*?' . preg_quote($match[0], '/') . '[\s:.\-]*/i', '', $line));
            $candidate = normalize_id_candidate($inline);
            if ($candidate !== '') {
                return $candidate;
            }
            for ($j = $index + 1; $j <= $index + 2 && $j < count($lines); $j++) {
                if (preg_match($looksLikeLabel, $lines[$j])) {
                    break;
                }
                $candidate = normalize_id_candidate($lines[$j]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $candidate = normalize_id_candidate($match[0]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

/**
 * School year such as 2026–2027 or 2026–2030, only from a school-year label
 * (S.Y. / SCHOOL YEAR / ACADEMIC YEAR / A.Y.). A bare year range on an ID is
 * treated as the validity period instead, never as the school year.
 */
function extract_school_year(string $text): string
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $labelRe = '/\b(S\.?\s*Y\.?|SCHOOL\s*YEAR|ACADEMIC\s*YEAR|A\.?\s*Y\.?)\b/i';
    $rangeRe = '/\b(20\d{2})\s*[\-–—\/]\s*(20\d{2})\b/u';

    foreach ($lines as $line) {
        if (!preg_match($labelRe, $line)) {
            continue;
        }
        if (preg_match($rangeRe, $line, $m) && (int) $m[2] > (int) $m[1]) {
            return $m[1] . '-' . $m[2];
        }
    }

    return '';
}

/**
 * Validity period of the ID itself. Returns the detected dates plus the raw
 * range when one was printed. Supports cards with a single expiry date, a
 * full date, or only a year range, and never fabricates a date.
 */
function extract_validity_dates(string $text): array
{
    $lines = preg_split('/\R+/', $text) ?: [];
    $out = ['from' => '', 'until' => '', 'raw' => ''];

    $labelRe = '/\b(VALID\s*(?:UNTIL|THRU|THROUGH|UP\s*TO)?|VALIDITY|VALID\s*DATES?|EXPIRES?|EXPIRY|DATE\s*OF\s*EXPIRY|VALID\s*FROM)\b/i';

    foreach ($lines as $index => $line) {
        if (!preg_match($labelRe, $line)) {
            continue;
        }

        $date = extract_date_from_line($line, true);
        if ($date === '') {
            for ($j = $index + 1; $j <= $index + 2 && $j < count($lines); $j++) {
                $date = extract_date_from_line($lines[$j], true);
                if ($date !== '') {
                    break;
                }
            }
        }

        if ($date === '') {
            continue;
        }

        if (preg_match('/\b(VALID\s*FROM)\b/i', $line) && $out['from'] === '') {
            $out['from'] = $date;
        } elseif ($out['until'] === '') {
            $out['until'] = $date;
        }
    }

    // Year-range validity (e.g. "2026 - 2030") on a line that is NOT a school
    // year; a school year is never used as the ID validity.
    foreach ($lines as $line) {
        if (preg_match('/(S\.?\s*Y\.?|SCHOOL\s*YEAR|ACADEMIC\s*YEAR|A\.?\s*Y\.?)/i', $line)) {
            continue;
        }
        if (preg_match('/\b(20\d{2})\s*[\-–—\/]\s*(20\d{2})\b/u', $line, $m) && (int) $m[2] > (int) $m[1]) {
            if ($out['from'] === '') {
                $out['from'] = $m[1] . '-01-01';
            }
            $until = $m[2] . '-12-31';
            if ($out['until'] === '' || $until > $out['until']) {
                $out['until'] = $until;
            }
            if ($out['raw'] === '') {
                $out['raw'] = $m[1] . '-' . $m[2];
            }
        }
    }

    return $out;
}

function extract_common_fields(string $text, int $score = 100, string $idNumber = '', string $dateOfBirth = ''): array
{
    return [
        'email' => extract_email($text),
        'phone' => extract_phone($text, $score, $idNumber, $dateOfBirth),
    ];
}

function extract_email(string $text): string
{
    // 1) Normal spacing first so neighbouring words are not merged in.
    if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z0-9]{2,6}/', $text, $m)) {
        return normalize_email($m[0]);
    }

    // 2) OCR sometimes puts spaces around the @ or inside the domain.
    if (preg_match('/([A-Za-z0-9._%+\-]+)\s*@\s*([A-Za-z0-9.\-]+)\s*\.\s*([A-Za-z0-9]{2,6})/', $text, $m)) {
        return normalize_email($m[1] . '@' . $m[2] . '.' . $m[3]);
    }

    // 3) Last resort: remove whitespace, but stop the TLD at a known value.
    $compact = (string) preg_replace('/\s+/', '', $text);
    if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.(com|net|org|edu|gov|info|ph|io|co|me|biz|online|site|tech|com\.ph|edu\.ph|gov\.ph)/i', $compact, $m)) {
        return normalize_email($m[0]);
    }

    return '';
}

function normalize_email(string $email): string
{
    $email = strtolower(trim($email, ".,;:()[]<>"));

    $email = str_replace(
        ['gmai1.', 'gma1l.', 'gmial.', 'yah00.', 'yaho0.', 'out1ook.', 'hotmai1.', 'gmaii.'],
        ['gmail.', 'gmail.', 'gmail.', 'yahoo.', 'yahoo.', 'outlook.', 'hotmail.', 'gmail.'],
        $email
    );

    // Common OCR digit swaps in the domain tail.
    $email = str_replace(
        ['.c0m', '.c0rn', '.corn', '.cpm', '.con', '.nett', '.0rg'],
        ['.com', '.com', '.com', '.com', '.com', '.net', '.org'],
        $email
    );

    return (strlen($email) >= 6 && strpos($email, '@') > 0) ? $email : '';
}

/**
 * Extract a phone number that was actually printed on the ID.
 *
 * A candidate is only ever taken from within a single line, so digits from
 * different fields can never be joined into one number. Candidates are
 * accepted label-first, then as an isolated unlabelled number, and every
 * candidate must pass Philippine mobile validation. Returns '' when nothing
 * qualifies - a number is never assembled from other digits on the ID.
 */
function extract_phone(string $text, int $score = 100, string $idNumber = '', string $dateOfBirth = ''): string
{
    // Below the project's good-quality threshold a number is treated as not
    // detected rather than guessed.
    if ($score < OCR_GOOD_SCORE) {
        return '';
    }

    $lines = preg_split('/\R+/', $text) ?: [];
    if ($lines === []) {
        return '';
    }

    $phoneRe = label_pattern(phone_labels());
    $kinds = [];
    foreach ($lines as $index => $line) {
        $kinds[$index] = classify_line((string) $line);
    }

    // Tier 1: the number sits on a phone label line, or on one of the next two
    // non-empty lines after one.
    for ($i = 0; $i < count($lines); $i++) {
        if ($kinds[$i] !== 'phone_label') {
            continue;
        }

        $found = phone_from_line((string) $lines[$i]);
        if ($found !== '') {
            return $found;
        }

        $lookahead = 0;
        for ($j = $i + 1; $j < count($lines) && $lookahead < 2; $j++) {
            if (trim((string) $lines[$j]) === '') {
                continue;
            }
            $lookahead++;
            if ($kinds[$j] === 'competing_label' || $kinds[$j] === 'date_like') {
                break;
            }
            $found = phone_from_line((string) $lines[$j]);
            if ($found !== '') {
                return $found;
            }
        }
    }

    // Tier 2: an unlabelled number, accepted only when the token stands alone
    // in the text, is not under a competing or date label, and does not reuse
    // the ID number or the birth date.
    $forbidden = [];
    foreach ([$idNumber, $dateOfBirth] as $value) {
        $digits = (string) preg_replace('/\D/', '', (string) $value);
        if (strlen($digits) < 6) {
            continue;
        }
        $normalized = normalize_phone($digits);
        $forbidden[] = $normalized !== '' ? $normalized : $digits;
        if ($normalized !== '') {
            $forbidden[] = substr($normalized, 1);
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $forbidden[] = substr($digits, 1);
        }
    }

    for ($i = 0; $i < count($lines); $i++) {
        if ($kinds[$i] === 'phone_label' || $kinds[$i] === 'competing_label' || $kinds[$i] === 'date_like') {
            continue;
        }

        $line = (string) $lines[$i];
        if (preg_match_all('/(?<![\d+])\+?\d[\d\s\-().]{8,}\d(?![\d])/', $line, $matches)) {
            foreach ($matches[0] as $token) {
                $normalized = normalize_phone($token);
                if ($normalized === '') {
                    continue;
                }
                if (in_array($normalized, $forbidden, true) || in_array(substr($normalized, 1), $forbidden, true)) {
                    continue;
                }
                return $normalized;
            }
        }
    }

    return '';
}

/**
 * Pull the phone number out of a single line and validate it.
 */
function phone_from_line(string $line): string
{
    // The token alphabet is digits, separators, and only those letters that OCR
    // substitutes for digits. Any other letter ends the run, so a label such as
    // "Mobile No." cannot be swallowed into the candidate, while a leading "0"
    // misread as O, S or B is still captured. The lookbehind stops a token from
    // starting inside a word, and a real digit is required so that words and
    // address lines never become candidates.
    $lookalikes = '0-9OQDSBZGILT';

    if (preg_match_all('/(?<![\w+])\+?[' . $lookalikes . '][' . $lookalikes . '\s\-().]{7,}[' . $lookalikes . '](?!\w)/i', $line, $matches)) {
        foreach ($matches[0] as $token) {
            if (!preg_match('/\d/', $token)) {
                continue;
            }

            $normalized = normalize_phone($token);
            if ($normalized !== '') {
                return $normalized;
            }
        }
    }

    return '';
}

/**
 * Letters that OCR routinely substitutes for digits.
 *
 * This is only safe for a field whose expected format is numeric, because the
 * target format resolves the ambiguity: "O9171234567" can only be a phone
 * number, while the same substitution applied to a name or an address would
 * corrupt real text.
 */
function ocr_lookalike_digits(string $value): string
{
    return str_replace(
        ['O', 'Q', 'D', 'S', 'B', 'Z', 'G', 'I', 'L', 'T'],
        ['0', '0', '0', '5', '8', '2', '6', '1', '1', '1'],
        strtoupper($value)
    );
}

/**
 * Normalise a detected number to the project's Philippine mobile form,
 * 0 followed by ten digits. Returns '' when the value is not a usable number.
 */
function normalize_phone(string $value): string
{
    $digits = (string) preg_replace('/\D/', '', ocr_lookalike_digits($value));
    if ($digits === '') {
        return '';
    }

    // International access prefix before the country code.
    if (strpos($digits, '00') === 0 && strlen($digits) > 10) {
        $digits = substr($digits, 2);
    }

    // +63 / 63 country code, with or without the international prefix.
    if (strpos($digits, '63') === 0 && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    } elseif (strpos($digits, '63') === 0 && strlen($digits) === 11) {
        $digits = '0' . substr($digits, 2);
    }

    if (!preg_match('/^09\d{9}$/', $digits)) {
        // A leading "0" misread as a letter ("S9171234567") cannot be settled by
        // the fixed map, which sends S to 5 and yields a number starting with 5.
        // A Philippine mobile can only begin with 0, so retry the first
        // character as a zero and keep that reading only if it validates.
        $repaired = repair_leading_zero($value);
        if ($repaired !== '' && $repaired !== $digits) {
            $digits = $repaired;
        }
    }

    if (!preg_match('/^09\d{9}$/', $digits)) {
        return '';
    }

    // A repeated-digit run is an OCR artifact, not a real number. The Create
    // Account form already rejects this shape, so the extractor agrees.
    if (preg_match('/^09(\d)\1{8}$/', $digits)) {
        return '';
    }

    return $digits;
}

/**
 * Re-run normalisation with the first character forced to read as a zero. Used
 * only when the ordinary reading cannot be a Philippine mobile number.
 */
function repair_leading_zero(string $value): string
{
    $value = trim($value);

    // Locate the first alphanumeric character so a leading label or separator is
    // not mistaken for the digit being repaired.
    if (!preg_match('/[A-Za-z0-9]/', $value, $found, PREG_OFFSET_CAPTURE)) {
        return '';
    }

    $position = $found[0][1];

    // Only a letter can be a misread zero. A leading digit is real, so a number
    // such as "59171234567" must not be turned into a mobile number.
    if (!ctype_alpha($value[$position])) {
        return '';
    }

    $rest = substr($value, $position + 1);
    $digits = (string) preg_replace('/\D/', '', ocr_lookalike_digits('0' . $rest));

    return preg_match('/^09\d{9}$/', $digits) ? $digits : '';
}

function safe_substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length);
    }

    return substr($value, $start, $length);
}

/**
 * Privacy-safe OCR diagnostics.
 *
 * Logs only structure: sizes, scores, booleans and provider error text. Raw
 * OCR output, names, ID numbers and images are never written. Disabled unless
 * PASSENGER_OCR_DEBUG=1 (env) or the existing passenger QR debug switch is on.
 */
function ocr_debug_log(array $entry): void
{
    $enabled = getenv('PASSENGER_OCR_DEBUG') === '1'
        || (defined('PASSENGER_OCR_DEBUG') && PASSENGER_OCR_DEBUG)
        || (function_exists('passenger_qr_scan_debug_enabled') && passenger_qr_scan_debug_enabled());
    if (!$enabled) {
        return;
    }

    try {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $entry['time'] = date('c');
        @file_put_contents(
            $dir . '/ocr_debug.log',
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL,
            FILE_APPEND
        );
    } catch (\Throwable $e) {
        // Diagnostics must never break a scan.
    }
}

function respond(array $payload): void
{
    $GLOBALS['scan_id_responded'] = true;
    // Drop any buffered notices before the JSON body so the response is always
    // parseable by the client.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}
