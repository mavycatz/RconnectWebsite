<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/DateFilterService.php';

use Finance\Services\DateFilterService;

date_default_timezone_set('Asia/Manila');

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$reference = '2026-10-13';

// Empty request resolves to the supplied page default on the reference day.
$range = DateFilterService::normalizeRequest([], $reference, DateFilterService::TODAY);
$check($range['period'] === DateFilterService::TODAY, 'Empty request uses the supplied default period');
$check($range['start'] === $reference && $range['end'] === $reference, 'Today default resolves to one reference day');
$check($range['fallback'] === false, 'Default resolution is not a fallback');

// The page config marks the default active and formats its summary.
$config = DateFilterService::config($range['period'], $range['start'], $range['end'], $reference);
$check($config['selected'] === DateFilterService::TODAY, 'Page config selects the default period');
$check(
    str_starts_with((string) $config['summary'], 'Today · October 13, 2026'),
    'Page config summary names the default day'
);

// An explicit preset period takes precedence over the default.
$monthly = DateFilterService::normalizeRequest(['period' => 'monthly'], $reference, DateFilterService::TODAY);
$check($monthly['period'] === DateFilterService::MONTHLY, 'Explicit preset period overrides the default');
$check($monthly['start'] === '2026-10-01' && $monthly['end'] === '2026-10-31', 'Monthly range resolves normally');

// An explicit custom range takes precedence over the default.
$custom = DateFilterService::normalizeRequest(
    ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-15'],
    $reference,
    DateFilterService::TODAY
);
$check(
    $custom['period'] === DateFilterService::CUSTOM
        && $custom['start'] === '2026-09-01'
        && $custom['end'] === '2026-09-15',
    'Explicit custom range overrides the default'
);

// An unrecognized period falls back to the supplied default.
$bogus = DateFilterService::normalizeRequest(['period' => 'not-a-period'], $reference, DateFilterService::TODAY);
$check($bogus['period'] === DateFilterService::TODAY && $bogus['fallback'] === true, 'Unknown period falls back to the supplied default');
$check($bogus['start'] === $reference && $bogus['end'] === $reference, 'Fallback default resolves to the reference day');

// Callers that pass no default keep the service-wide Monthly default.
$legacy = DateFilterService::normalizeRequest([], $reference);
$check($legacy['period'] === DateFilterService::MONTHLY, 'Caller without a default keeps Monthly');
$check($legacy['start'] === '2026-10-01' && $legacy['end'] === '2026-10-31', 'Legacy default still resolves the calendar month');

// Without a reference date the default follows the pinned Manila clock.
$live = DateFilterService::normalizeRequest([], null, DateFilterService::TODAY);
$check(
    $live['period'] === DateFilterService::TODAY
        && $live['start'] === date('Y-m-d')
        && $live['end'] === date('Y-m-d'),
    'Default Today follows the Asia/Manila application clock'
);

echo "$checks date filter default checks passed.\n";
