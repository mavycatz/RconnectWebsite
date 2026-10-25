<?php declare(strict_types=1);

/**
 * Bus Dispatch Queue page markup checks: the section reuses the shared rmg
 * design system (shell header, summary, filters, list, form modals) and
 * exposes every control the page script wires. No database is required.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

ob_start();
require __DIR__ . '/../views/dispatch-queue/sections/directory.php';
$html = (string)ob_get_clean();

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$byId = static function (string $id) use ($xpath): ?DOMElement {
    $nodes = $xpath->query('//*[@id="' . $id . '"]');

    return $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;
};

check($xpath->query('//section[@id="dispatchQueueSection"]')->length === 1, 'The page renders one dispatch queue section');
check($xpath->query('//section[@id="dispatchQueueSection"]//h1')->length === 1, 'The section has a single heading');
check(trim($xpath->query('//section[@id="dispatchQueueSection"]//h1')->item(0)->textContent) === 'Bus Dispatch Queue', 'The heading names the dispatch queue');
check($xpath->query('//section[@id="dispatchQueueSection"]//header[contains(@class,"rmg-head")]')->length === 1, 'The shared rmg header is reused');
check($xpath->query('//div[contains(@class,"rmg-summary")][@id="dqSummary"]')->length === 1, 'Summary cards container is present');
check($xpath->query('//div[contains(@class,"rmg-filters")]')->length === 1, 'Filters row is present');
check($xpath->query('//div[contains(@class,"rmg-list")][@id="dqList"]')->length === 1, 'The list container is present');
check($xpath->query('//*[contains(@class,"rmg-btn-primary")][@id="dqPublishBtn"]')->length === 1, 'Publish button uses the primary rmg button style');

foreach ([
    'dqDate' => 'input',
    'dqSearch' => 'input',
    'dqTerminal' => 'select',
    'dqSummary' => 'div',
    'dqList' => 'div',
    'dqPublishBtn' => 'button',
    'dqSaveBtn' => 'button',
    'dqHistoryBtn' => 'button',
    'dqPrintBtn' => 'button',
    'dqRefresh' => 'button',
    'dqClearFilters' => 'button',
    'dqStateChip' => 'span',
    'dqDateStatus' => 'span',
    'dqHistoryNotice' => 'div',
    'dqHistoryDate' => 'div',
    'dqHistoryBody' => 'div',
    'dqAvailabilityBus' => 'div',
    'dqAvailabilityStatus' => 'select',
    'dqAvailabilityHint' => 'div',
    'dqAvailabilityReason' => 'textarea',
    'dqAvailabilityError' => 'div',
    'dqAvailabilitySave' => 'button',
    'dqTerminalBus' => 'div',
    'dqTerminalSelect' => 'select',
    'dqTerminalBusHint' => 'div',
    'dqTerminalError' => 'div',
    'dqTerminalSave' => 'button',
] as $id => $tag) {
    $node = $byId($id);
    check($node !== null && strtolower($node->tagName) === $tag, 'Control present: ' . $id);
}

check($xpath->query('//div[@id="dqTerminalModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'Terminal modal reuses the shared form modal style');

check($xpath->query('//div[@id="dqAvailabilityModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'Availability modal reuses the shared form modal style');

check($xpath->query('//div[@id="dqHistoryModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'History modal reuses the shared form modal style');

check($xpath->query('//div[@id="dqAssignModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'Assign-driver modal reuses the shared form modal style');
check($xpath->query('//div[@id="dqOverrideModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'Override modal reuses the shared form modal style');
check($xpath->query('//div[@id="dqTerminalModal"]//div[contains(@class,"rmg-form-modal")]')->length === 1, 'Terminal modal reuses the shared form modal style');
check($xpath->query('//div[@id="dqTerminalModal"]//select[@id="dqTerminalSelect"]')->length === 1, 'Terminal modal exposes the bus terminal select');
check($xpath->query('//*[@id="dqTerminalDriverSelect"]')->length === 0, 'The modal no longer offers a separate driver terminal (the driver follows the bus)');
check($xpath->query('//*[@id="dqTerminalDriverHint"]')->length === 0, 'The removed driver-terminal field left no orphan hint');
check($byId('dqTerminalSave') !== null, 'Terminal modal exposes the save action');
check($byId('dqAssignDriver') !== null, 'Assign modal exposes the driver select');
check($byId('dqAssignSave') !== null, 'Assign modal exposes the save action');
check($byId('dqOverridePosition') !== null && $byId('dqOverrideReason') !== null, 'Override modal exposes position and reason inputs');
check($byId('dqOverrideSave') !== null, 'Override modal exposes the apply action');

check($xpath->query('//*[contains(@class,"dq-hint")]')->length === 1, 'The trip-history safety hint is shown');

/* The Actions column and the table wrap must keep the four row controls
   reachable: the buttons need ~130px, and the wrap must scroll rather than
   clip the right-most column. */
$queueCss = (string) file_get_contents(__DIR__ . '/../assets/css/dispatch-queue.css');
check(
    preg_match('/dq-col-actions \{\s*width:\s*(\d+)px/', $queueCss, $match) === 1 && (int)$match[1] >= 140,
    'The actions column budget fits the four row buttons'
);
check(
    str_contains($queueCss, 'overflow-x: auto')
    && !str_contains($queueCss, '#dqList > .rmg-table-wrap { overflow: hidden'),
    'The queue table wrap scrolls instead of clipping the actions column'
);

echo "$checks dispatch queue layout checks passed.\n";
