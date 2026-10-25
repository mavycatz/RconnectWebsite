<?php declare(strict_types=1);

/**
 * Translation files must stay valid UTF-8 JSON with intact symbols.
 *
 * A mojibake arrow ("→" saved through the wrong encoding) rendered as random
 * characters on the passenger trips page ("Tap for details â†’"), so the
 * arrows and the files' encoding are pinned here.
 */

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    $checks++;
}

$dir = dirname(__DIR__) . '/translations';
$arrow = "\xE2\x86\x92";               // →
$replacement = "\xEF\xBF\xBD";         // U+FFFD
$mojibakeArrow = "\xC3\xA2\xE2\x80\xA0"; // "â†" (corrupted →)

$requiredKeys = ['trips.tap_details', 'home.payment_method_locked_settings'];

foreach (['en', 'tl', 'ilo'] as $lang) {
    $file = $dir . '/' . $lang . '.json';
    check(is_file($file), "$lang.json exists");

    $raw = (string) file_get_contents($file);
    check($raw !== '', "$lang.json is not empty");
    check(mb_check_encoding($raw, 'UTF-8'), "$lang.json is valid UTF-8");
    check(strpos($raw, $replacement) === false, "$lang.json has no replacement characters");
    check(strpos($raw, $mojibakeArrow) === false, "$lang.json has no corrupted arrow");

    $data = json_decode($raw, true);
    check(is_array($data), "$lang.json decodes to an object");

    foreach ($requiredKeys as $key) {
        check(isset($data[$key]) && is_string($data[$key]), "$lang.json defines $key");
        check(strpos((string) $data[$key], $arrow) !== false, "$lang.json $key keeps its arrow");
    }
}

echo "$checks translation checks passed.\n";
