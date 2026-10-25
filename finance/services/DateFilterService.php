<?php
declare(strict_types=1);

namespace Finance\Services;

use DateTimeImmutable;

/**
 * Single source of truth for Finance date-period filtering.
 *
 * Canonical periods: today, monthly, quarterly, semi_annually, annually, custom.
 * Presets resolve to the calendar period containing the reference date, using
 * the application clock (Asia/Manila, pinned in app/Core/Bootstrap.php).
 */
final class DateFilterService
{
    public const TODAY = 'today';
    public const MONTHLY = 'monthly';
    public const QUARTERLY = 'quarterly';
    public const SEMI_ANNUALLY = 'semi_annually';
    public const ANNUALLY = 'annually';
    public const CUSTOM = 'custom';

    public const DEFAULT_PERIOD = self::MONTHLY;

    private const LEGACY_MAP = [
        'today' => self::TODAY,
        'daily' => self::TODAY,
        'weekly' => self::MONTHLY,
        'this_week' => self::MONTHLY,
        'last_30_days' => self::MONTHLY,
        'all' => self::MONTHLY,
        'all_time' => self::MONTHLY,
        'monthly' => self::MONTHLY,
        'month' => self::MONTHLY,
        'this_month' => self::MONTHLY,
        'quarterly' => self::QUARTERLY,
        'quarter' => self::QUARTERLY,
        'semi_annually' => self::SEMI_ANNUALLY,
        'semi-annually' => self::SEMI_ANNUALLY,
        'semiannually' => self::SEMI_ANNUALLY,
        'half_yearly' => self::SEMI_ANNUALLY,
        'half-yearly' => self::SEMI_ANNUALLY,
        'annually' => self::ANNUALLY,
        'annual' => self::ANNUALLY,
        'yearly' => self::ANNUALLY,
        'year' => self::ANNUALLY,
        'this_year' => self::ANNUALLY,
        'custom' => self::CUSTOM,
        'custom_range' => self::CUSTOM,
    ];

    /**
     * Standard option set, in display order.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::TODAY, 'label' => 'Today'],
            ['value' => self::MONTHLY, 'label' => 'Monthly'],
            ['value' => self::QUARTERLY, 'label' => 'Quarterly'],
            ['value' => self::SEMI_ANNUALLY, 'label' => 'Semi-Annually'],
            ['value' => self::ANNUALLY, 'label' => 'Annually'],
            ['value' => self::CUSTOM, 'label' => 'Custom Range'],
        ];
    }

    public static function label(?string $period): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $period) {
                return $option['label'];
            }
        }

        return self::label(self::DEFAULT_PERIOD);
    }

    /**
     * Map a legacy or canonical period value to a canonical value.
     * Returns null when the value is not recognized.
     */
    public static function mapLegacyPeriod(?string $value): ?string
    {
        $key = strtolower(trim((string) $value));
        if ($key === '') {
            return null;
        }

        return self::LEGACY_MAP[$key] ?? null;
    }

    /**
     * Resolve a period (canonical or legacy value) to an inclusive date range.
     *
     * @return array{
     *     period: string,
     *     start: string,
     *     end: string,
     *     from: ?string,
     *     to: ?string,
     *     fallback: bool,
     *     reference: string
     * }
     */
    public static function resolve(
        ?string $period,
        ?string $from = null,
        ?string $to = null,
        ?string $referenceDate = null,
        ?string $defaultPeriod = null
    ): array {
        $reference = self::referenceDate($referenceDate);
        $today = new DateTimeImmutable($reference);
        $fallback = false;

        $raw = strtolower(trim((string) $period));
        if ($raw === '') {
            $canonical = self::normalizeDefaultPeriod($defaultPeriod);
        } else {
            $mapped = self::mapLegacyPeriod($raw);
            if ($mapped === null) {
                $canonical = self::normalizeDefaultPeriod($defaultPeriod);
                $fallback = true;
            } else {
                $canonical = $mapped;
            }
        }

        $start = $reference;
        $end = $reference;

        switch ($canonical) {
            case self::TODAY:
                break;

            case self::MONTHLY:
                $start = $today->modify('first day of this month')->format('Y-m-d');
                $end = $today->modify('last day of this month')->format('Y-m-d');
                break;

            case self::QUARTERLY:
                $firstMonth = ((int) floor(((int) $today->format('n') - 1) / 3) * 3) + 1;
                $quarterStart = $today->setDate(
                    (int) $today->format('Y'),
                    $firstMonth,
                    1
                );
                $start = $quarterStart->format('Y-m-d');
                $end = $quarterStart->modify('+2 months')->modify('last day of this month')->format('Y-m-d');
                break;

            case self::SEMI_ANNUALLY:
                $firstHalf = (int) $today->format('n') <= 6;
                $semesterStart = $today->setDate((int) $today->format('Y'), $firstHalf ? 1 : 7, 1);
                $start = $semesterStart->format('Y-m-d');
                $end = $semesterStart->modify('+5 months')->modify('last day of this month')->format('Y-m-d');
                break;

            case self::ANNUALLY:
                $start = $today->setDate((int) $today->format('Y'), 1, 1)->format('Y-m-d');
                $end = $today->setDate((int) $today->format('Y'), 12, 31)->format('Y-m-d');
                break;

            case self::CUSTOM:
                $custom = self::normalizeCustomRange($from, $to);
                if ($custom === null) {
                    $canonical = self::DEFAULT_PERIOD;
                    $fallback = true;
                    $start = $today->modify('first day of this month')->format('Y-m-d');
                    $end = $today->modify('last day of this month')->format('Y-m-d');
                } else {
                    $start = $custom['start'];
                    $end = $custom['end'];
                }
                break;
        }

        return [
            'period' => $canonical,
            'start' => $start,
            'end' => $end,
            'from' => $canonical === self::CUSTOM ? $start : null,
            'to' => $canonical === self::CUSTOM ? $end : null,
            'fallback' => $fallback,
            'reference' => $reference,
        ];
    }

    /**
     * Previous equivalent range for period-over-period comparisons.
     *
     * @return array{0: string, 1: string} [previous start, previous end]
     */
    public static function previousRange(string $period, string $start, string $end): array
    {
        if ($start === '' || $end === '') {
            return ['', ''];
        }

        $startDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);

        switch ($period) {
            case self::TODAY:
                $previous = $startDate->modify('-1 day');
                return [$previous->format('Y-m-d'), $previous->format('Y-m-d')];

            case self::MONTHLY:
                $monthStart = $startDate->setDate(
                    (int) $startDate->format('Y'),
                    (int) $startDate->format('n'),
                    1
                );
                $previous = $monthStart->modify('-1 month');
                return [$previous->format('Y-m-01'), $previous->format('Y-m-t')];

            case self::QUARTERLY:
                $firstMonth = ((int) floor(((int) $startDate->format('n') - 1) / 3) * 3) + 1;
                $quarterStart = $startDate
                    ->setDate((int) $startDate->format('Y'), $firstMonth, 1)
                    ->modify('-3 months');
                return [
                    $quarterStart->format('Y-m-d'),
                    $quarterStart->modify('+2 months')->modify('last day of this month')->format('Y-m-d'),
                ];

            case self::SEMI_ANNUALLY:
                $firstHalf = (int) $startDate->format('n') <= 6;
                $semesterStart = $startDate
                    ->setDate((int) $startDate->format('Y'), $firstHalf ? 1 : 7, 1)
                    ->modify('-6 months');
                return [
                    $semesterStart->format('Y-m-d'),
                    $semesterStart->modify('+5 months')->modify('last day of this month')->format('Y-m-d'),
                ];

            case self::ANNUALLY:
                $year = (int) $startDate->format('Y') - 1;
                return [$year . '-01-01', $year . '-12-31'];

            case self::CUSTOM:
            default:
                // Full calendar periods keep calendar-aligned previous windows.
                if (
                    $startDate->format('Y-m-d') === $startDate->format('Y-m-01')
                    && $endDate->format('Y-m-d') === $endDate->format('Y-m-t')
                    && $startDate->format('Y-m') === $endDate->format('Y-m')
                ) {
                    $previous = $startDate->modify('-1 month');
                    return [$previous->format('Y-m-01'), $previous->format('Y-m-t')];
                }
                if ($startDate->format('m-d') === '01-01' && $endDate->format('m-d') === '12-31') {
                    $year = (int) $startDate->format('Y') - 1;
                    return [$year . '-01-01', $year . '-12-31'];
                }

                $length = (int) floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / 86400) + 1;
                $previousTo = $startDate->modify('-1 day');
                $previousFrom = $previousTo->modify('-' . max(0, $length - 1) . ' days');
                return [$previousFrom->format('Y-m-d'), $previousTo->format('Y-m-d')];
        }
    }

    /**
     * Resolve a page request (canonical or legacy parameters) to a range.
     *
     * Accepted period keys: period, type (Profit Overview), range (Dashboard).
     * Accepted date keys: from/start/fromDate, to/end/toDate, date (daily).
     *
     * $defaultPeriod is used only when the request carries no usable period
     * selector; callers that omit it keep resolving to DEFAULT_PERIOD.
     */
    public static function normalizeRequest(
        array $request,
        ?string $referenceDate = null,
        ?string $defaultPeriod = null
    ): array {
        $periodParam = self::scalar($request['period'] ?? null);
        $typeParam = self::scalar($request['type'] ?? null);
        $rangeParam = self::scalar($request['range'] ?? null);

        $from = self::firstDate($request, ['from', 'start', 'fromDate']);
        $to = self::firstDate($request, ['to', 'end', 'toDate']);

        $hasPeriod = $periodParam !== null && trim($periodParam) !== '';

        if ($hasPeriod) {
            $periodKey = strtolower(trim($periodParam));

            // Legacy daily picker: a concrete day becomes a single-day custom range.
            if ($periodKey === 'daily') {
                $day = self::firstDate($request, ['date']);
                if ($day !== null) {
                    return self::resolve(self::CUSTOM, $day, $day, $referenceDate)
                        + ['source' => 'daily'];
                }
            }

            return self::resolve($periodParam, $from, $to, $referenceDate, $defaultPeriod);
        }

        // Legacy selector keys: range (Dashboard) or type (Profit Overview).
        $selector = ($rangeParam !== null && trim($rangeParam) !== '') ? $rangeParam : $typeParam;
        $selectorKey = strtolower(trim((string) $selector));

        // Legacy month/year selectors (Dashboard): honor an explicit month or
        // year with a custom range; otherwise fall back to the closest preset.
        // Explicit from/to dates take precedence over the selector.
        if ($from === null && $to === null) {
            if ($selectorKey === 'month') {
                $month = self::scalar($request['month'] ?? null);
                $year = self::scalar($request['year'] ?? null);
                $custom = self::legacyMonthRange($month, $year, $referenceDate);
                if ($custom !== null) {
                    return self::resolve(self::CUSTOM, $custom['start'], $custom['end'], $referenceDate)
                        + ['source' => 'month'];
                }
            }

            if ($selectorKey === 'year') {
                $year = self::scalar($request['year'] ?? null);
                $custom = self::legacyYearRange($year, $referenceDate);
                if ($custom !== null) {
                    return self::resolve(self::CUSTOM, $custom['start'], $custom['end'], $referenceDate)
                        + ['source' => 'year'];
                }
            }
        }

        // Explicit dates without a usable period are a custom range (legacy
        // callers that only passed dates); invalid or missing bounds fall back
        // to the default inside resolve().
        if ($from !== null || $to !== null) {
            return self::resolve(self::CUSTOM, $from, $to, $referenceDate);
        }

        return self::resolve($selector, $from, $to, $referenceDate, $defaultPeriod);
    }

    /**
     * Config payload for embedding into a page (resolved presets + current range).
     */
    public static function config(
        ?string $selected = null,
        ?string $from = null,
        ?string $to = null,
        ?string $referenceDate = null
    ): array {
        $presets = [];
        foreach (self::options() as $option) {
            $value = $option['value'];
            if ($value === self::CUSTOM) {
                $presets[$value] = [
                    'value' => $value,
                    'label' => $option['label'],
                    'start' => null,
                    'end' => null,
                    'summary' => null,
                ];
                continue;
            }

            $range = self::resolve($value, null, null, $referenceDate);
            $presets[$value] = [
                'value' => $value,
                'label' => $option['label'],
                'start' => $range['start'],
                'end' => $range['end'],
                'summary' => self::summary($value, $range['start'], $range['end']),
            ];
        }

        $current = self::resolve($selected, $from, $to, $referenceDate);

        return [
            'options' => self::options(),
            'presets' => $presets,
            'selected' => $current['period'],
            'start' => $current['start'],
            'end' => $current['end'],
            'from' => $current['from'],
            'to' => $current['to'],
            'summary' => self::summary($current['period'], $current['start'], $current['end']),
            'fallback' => $current['fallback'],
            'reference' => $current['reference'],
        ];
    }

    /**
     * Header summary, e.g. "Monthly · October 1–31, 2026" or
     * "Today · October 11, 2026 (Sunday)".
     */
    public static function summary(string $period, string $start, string $end): string
    {
        $label = self::label($period);
        $startDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);

        if ($period === self::TODAY) {
            return $label . ' · ' . $startDate->format('F j, Y (l)');
        }

        if ($start === $end) {
            return $label . ' · ' . $startDate->format('F j, Y');
        }

        if ($startDate->format('Y') !== $endDate->format('Y')) {
            return $label . ' · '
                . $startDate->format('F j, Y') . '–' . $endDate->format('F j, Y');
        }

        if ($startDate->format('n') === $endDate->format('n')) {
            return $label . ' · '
                . $startDate->format('F j') . '–' . $endDate->format('j, Y');
        }

        return $label . ' · '
            . $startDate->format('F j') . '–' . $endDate->format('F j, Y');
    }

    /**
     * Today summary with weekday, e.g. "Today · October 11, 2026 (Sunday)".
     */
    public static function todaySummary(?string $referenceDate = null): string
    {
        $reference = self::referenceDate($referenceDate);
        $date = new DateTimeImmutable($reference);

        return 'Today · ' . $date->format('F j, Y (l)');
    }

    public static function today(?string $referenceDate = null): string
    {
        return self::referenceDate($referenceDate);
    }

    private static function referenceDate(?string $referenceDate): string
    {
        if ($referenceDate !== null && self::validDate($referenceDate)) {
            return $referenceDate;
        }

        return date('Y-m-d');
    }

    /**
     * Canonical page default period; unknown or open-ended values keep the
     * service-wide DEFAULT_PERIOD.
     */
    private static function normalizeDefaultPeriod(?string $defaultPeriod): string
    {
        $mapped = self::mapLegacyPeriod($defaultPeriod);
        if ($mapped === null || $mapped === self::CUSTOM) {
            return self::DEFAULT_PERIOD;
        }

        return $mapped;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private static function normalizeCustomRange(?string $from, ?string $to): ?array
    {
        if (!self::validDate($from) || !self::validDate($to)) {
            return null;
        }

        $start = (string) $from;
        $end = (string) $to;
        if ($start > $end) {
            $swap = $start;
            $start = $end;
            $end = $swap;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Legacy Dashboard month selector: explicit month/year become a custom range
     * unless they point at the reference month, which maps to Monthly.
     *
     * @return array{start: string, end: string}|null
     */
    private static function legacyMonthRange(?string $month, ?string $year, ?string $referenceDate): ?array
    {
        if ($month === null || !ctype_digit($month) || (int) $month < 1 || (int) $month > 12) {
            return null;
        }

        $reference = new DateTimeImmutable(self::referenceDate($referenceDate));
        $yearValue = ($year !== null && ctype_digit($year)) ? (int) $year : (int) $reference->format('Y');

        if ($yearValue === (int) $reference->format('Y') && (int) $month === (int) $reference->format('n')) {
            return null;
        }

        $first = (new DateTimeImmutable(self::referenceDate($referenceDate)))->setDate($yearValue, (int) $month, 1);

        return [
            'start' => $first->format('Y-m-d'),
            'end' => $first->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    /**
     * Legacy Dashboard year selector: explicit year becomes a custom range unless
     * it is the reference year, which maps to Annually.
     *
     * @return array{start: string, end: string}|null
     */
    private static function legacyYearRange(?string $year, ?string $referenceDate): ?array
    {
        if ($year === null || !ctype_digit($year)) {
            return null;
        }

        $reference = new DateTimeImmutable(self::referenceDate($referenceDate));
        if ((int) $year === (int) $reference->format('Y')) {
            return null;
        }

        return [
            'start' => $year . '-01-01',
            'end' => $year . '-12-31',
        ];
    }

    private static function firstDate(array $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = self::scalar($request[$key] ?? null);
            if ($value !== null && self::validDate($value)) {
                return $value;
            }
        }

        return null;
    }

    private static function scalar($value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function validDate($value): bool
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
