<?php

declare(strict_types=1);

namespace Finance\Services;

require_once __DIR__ . '/../models/PredictiveAnalysisModel.php';
require_once __DIR__ . '/FuelCostAnalyticsService.php';
require_once __DIR__ . '/DateFilterService.php';

use DateTimeImmutable;
use Finance\Models\PredictiveAnalysisModel;

/**
 * Baseline forecasting for the Finance Predictive Analysis page.
 *
 * Everything returned by build()/buildFromData() is either aggregated from
 * stored R-Connect data or derived from those aggregates (moving average,
 * linear trend, residual spread, demand-level thresholds). Nothing is
 * hardcoded: every prediction passes validation before it is returned, and
 * thin or inconsistent history yields data_quality.unavailable = true with
 * the "Prediction unavailable" message instead of numbers.
 */
class PredictiveAnalysisService
{
    public const MIN_DAYS_WITH_DATA = 14;
    public const MIN_PASSENGER_RECORDS = 30;
    public const UNAVAILABLE_MESSAGE = 'Prediction unavailable due to insufficient or inconsistent historical data.';

    private const HIGH_FACTOR = 1.15;
    private const MEDIUM_FACTOR = 0.85;
    private const RANGE_Z = 1.28;
    private const MIN_TRAINING_DAYS = 30;
    private const MAX_TRAINING_DAYS = 90;
    private const BACKTEST_DAYS = 7;

    /** Total-volume error (WAPE) above this is not meaningful and is withheld. */
    private const MAX_MEANINGFUL_ERROR = 100.0;

    /** Daily demand above this coefficient of variation is too erratic to forecast. */
    private const MAX_CONSISTENCY_CV = 1.5;

    /** A forecast point above this multiple of the historical daily peak is treated as runaway. */
    private const MAX_FORECAST_GROWTH_FACTOR = 4.0;

    private PredictiveAnalysisModel $model;

    public function __construct(?PredictiveAnalysisModel $model = null)
    {
        $this->model = $model ?? new PredictiveAnalysisModel();
    }

    /**
     * Fetch everything from the database and build the page payload.
     */
    public function build(array $input = []): array
    {
        $filters = $this->normalizeFilters($input);

        if (!$this->model->isAvailable()) {
            return ['success' => false, 'message' => 'Database connection unavailable.'];
        }

        $options = $this->model->getFilterOptions();
        $today = new DateTimeImmutable('today');
        $dates = $this->resolveDates($filters, $today);

        $scope = [
            'bus' => $filters['bus'],
            'route' => $filters['route'],
            'time_start' => $filters['time_start'],
            'time_end' => $filters['time_end'],
        ];

        $daily = $this->model->getDailyPassengers($dates['history_start'], $dates['history_end_exclusive'], $scope);
        $hourly = $this->model->getHourlyPassengers($dates['history_start'], $dates['history_end_exclusive'], $scope);
        $completed = $this->model->getDailyCompletedTrips($dates['history_start'], $dates['history_end_exclusive'], $scope);
        $fuel = $this->collectFuel($dates, $filters);

        return $this->buildFromData([
            'daily' => $daily,
            'hourly' => $hourly,
            'completed' => $completed,
            'fuel' => $fuel,
        ], $filters, $options, $today);
    }

    /**
     * Pure computation over already-fetched data (also the fixture-test entry).
     *
     * @param array{
     *   daily?: array<string, array{passengers: int, trips: int}>,
     *   hourly?: array<string, array<int, array{passengers: int, trips: int}>>,
     *   completed?: array<string, array{trips: int, passengers: int}>,
     *   fuel?: array<string, mixed>
     * } $data
     */
    public function buildFromData(array $data, array $filters = [], array $options = [], ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? new DateTimeImmutable('today');
        $filters = $this->normalizeFilters($filters, $today);
        $dates = $this->resolveDates($filters, $today);
        $periodDays = $filters['period_days'];

        $daily = is_array($data['daily'] ?? null) ? $data['daily'] : [];
        $series = $this->continuousSeries($daily, $dates['history_start'], $dates['history_end']);

        $passengerTotal = 0;
        $tripTotal = 0;
        $daysWithData = 0;
        $dailyValues = [];
        foreach ($series as $day => $row) {
            $dailyValues[$day] = (int) $row['passengers'];
            $passengerTotal += (int) $row['passengers'];
            $tripTotal += (int) $row['trips'];
            if ((int) $row['passengers'] > 0) {
                $daysWithData++;
            }
        }

        $fuelInput = is_array($data['fuel'] ?? null) ? $data['fuel'] : [];
        $completedTrips = $this->sumCompletedTrips($data['completed'] ?? []);
        $recordsUsed = $passengerTotal + $completedTrips;
        $values = array_values($dailyValues);
        $cv = $this->coefficientOfVariation($values);

        /* A trailing day that is still being recorded (far below the recent
           level) must not drag the weighted average down. */
        $tail = $this->trimIncompleteTail($dailyValues);
        $fitValues = $tail['values'];
        if (count($fitValues) > self::MAX_TRAINING_DAYS) {
            $fitValues = array_slice($fitValues, -self::MAX_TRAINING_DAYS);
        }

        $insufficient = $daysWithData < self::MIN_DAYS_WITH_DATA || $passengerTotal < self::MIN_PASSENGER_RECORDS;
        if ($insufficient) {
            return $this->unavailablePayload($filters, $options, $dates, $daysWithData, $passengerTotal, $recordsUsed, $completedTrips, 'insufficient', $cv);
        }

        if ($cv > self::MAX_CONSISTENCY_CV) {
            return $this->unavailablePayload($filters, $options, $dates, $daysWithData, $passengerTotal, $recordsUsed, $completedTrips, 'inconsistent', $cv);
        }

        $forecastPoints = $this->forecastSeries($fitValues, $periodDays, new DateTimeImmutable($dates['forecast_start']));
        $forecastValidation = $this->validateForecast($forecastPoints, $fitValues);
        if ($forecastValidation !== null) {
            return $this->unavailablePayload($filters, $options, $dates, $daysWithData, $passengerTotal, $recordsUsed, $completedTrips, $forecastValidation, $cv);
        }

        $forecastTotal = 0;
        $forecastLowerTotal = 0;
        $forecastUpperTotal = 0;
        foreach ($forecastPoints as $point) {
            $forecastTotal += $point['value'];
            $forecastLowerTotal += $point['lower'];
            $forecastUpperTotal += $point['upper'];
        }

        /* ---- previous equivalent period (up to the last N days of the history window) ---- */
        $previousTotal = 0;
        $previousDays = min($periodDays, (int) ($dates['range_days'] ?? $periodDays));
        for ($i = 0; $i < $previousDays; $i++) {
            $day = (new DateTimeImmutable($dates['history_end']))->modify('-' . $i . ' days')->format('Y-m-d');
            $previousTotal += $dailyValues[$day] ?? 0;
        }
        $passengerChangePct = $previousTotal > 0
            ? round((($forecastTotal - $previousTotal) / $previousTotal) * 100, 1)
            : null;

        $history = [];
        foreach ($series as $day => $row) {
            $history[] = [
                'date' => $day,
                'passengers' => (int) $row['passengers'],
                'trips' => (int) $row['trips'],
            ];
        }

        $byTime = $this->buildDemandByTime($data['hourly'] ?? [], $dates['history_start'], $dates['history_end']);
        $fuel = $this->buildFuelForecast($fuelInput, $data['completed'] ?? [], $tripTotal, $periodDays, $dates, $filters);
        $backtest = $this->backtestEvaluation($fitValues);
        $evaluation = $backtest['evaluation'];
        $reliability = $this->assessReliability($daysWithData, $dates['training_days'], $completedTrips, $evaluation, $cv, $backtest['reason']);

        $peak = $this->pickPeak($byTime);
        $summary = [
            'passengers' => [
                'range_low' => $forecastLowerTotal,
                'range_high' => $forecastUpperTotal,
                'forecast_total' => $forecastTotal,
                'change_pct' => $passengerChangePct,
                'change_direction' => $passengerChangePct === null ? null : $this->direction($passengerChangePct),
                'has_change' => $passengerChangePct !== null,
                'period_label' => 'Next ' . $periodDays . ' days',
                'previous_total' => $previousTotal,
            ],
            'peak' => $peak,
            'fuel' => [
                'liters' => $fuel['estimated_liters'] ?? null,
                'period_label' => 'Next ' . $periodDays . ' days',
                'change_pct' => $fuel['change_pct'],
                'change_direction' => $fuel['change_pct'] === null ? null : $this->direction((float) $fuel['change_pct']),
                'has_change' => $fuel['change_pct'] !== null,
                'available' => $fuel['available'],
            ],
        ];

        $model = [
            'name' => 'Weighted moving average + linear trend (baseline)',
            'records_used' => $recordsUsed,
            'passenger_records' => $passengerTotal,
            'completed_trips' => $this->sumCompletedTrips($data['completed'] ?? []),
            'history_from' => $dates['history_start'],
            'history_to' => $dates['history_end'],
            'forecast_period' => [
                'start' => $dates['forecast_start'],
                'end' => $dates['forecast_end'],
                'days' => $periodDays,
            ],
            'computed_at' => date('Y-m-d H:i:s'),
            'evaluation' => $evaluation,
            'reliability' => $reliability,
            'sufficiency' => [
                'days_with_data' => $daysWithData,
                'training_days' => $dates['training_days'],
                'passenger_records' => $passengerTotal,
                'completed_trips' => $completedTrips,
                'min_days_required' => self::MIN_DAYS_WITH_DATA,
                'min_records_required' => self::MIN_PASSENGER_RECORDS,
            ],
        ];

        return [
            'success' => true,
            'data_quality' => [
                'unavailable' => false,
                'inconsistent' => false,
                'reason' => null,
                'detail' => null,
                'insufficient' => false,
                'message' => null,
                'days_with_data' => $daysWithData,
                'min_days_required' => self::MIN_DAYS_WITH_DATA,
                'passenger_records' => $passengerTotal,
                'min_records_required' => self::MIN_PASSENGER_RECORDS,
                'training_days' => $dates['training_days'],
                'history_from' => $dates['history_start'],
                'history_to' => $dates['history_end'],
            ],
            'summary' => $summary,
            'forecast' => [
                'history' => $history,
                'points' => $forecastPoints,
                'forecast_start' => $dates['forecast_start'],
                'forecast_end' => $dates['forecast_end'],
                'range_band_label' => 'Predicted range',
                'incomplete_dates' => $tail['incomplete_dates'],
            ],
            'by_time' => $byTime,
            'fuel' => $fuel,
            'model' => $model,
            'insights' => $this->buildInsights($summary, $fuel, $forecastPoints, $periodDays),
            'filters' => $this->filtersPayload($filters, $options),
        ];
    }

    /* =========================================================
       FETCH HELPERS
    ========================================================= */

    private function collectFuel(array $dates, array $filters): array
    {
        try {
            $service = new FuelCostAnalyticsService();

            $base = ['from' => $dates['history_start'], 'to' => $dates['history_end']];
            if ($filters['bus'] !== '') {
                $base['bus'] = $filters['bus'];
            }

            $summary = $service->getSummary($base);
            $perBus = $service->getFuelCostPerBus($base);

            $previous = $service->getSummary([
                'from' => $dates['previous_start'],
                'to' => $dates['history_end'],
            ] + ($filters['bus'] !== '' ? ['bus' => $filters['bus']] : []));

            $distance = 0.0;
            $topBus = null;
            $topCost = -1.0;
            foreach ($perBus as $row) {
                $distance += (float) ($row['distance'] ?? 0);
                $cost = (float) ($row['total'] ?? 0);
                if ($cost > $topCost) {
                    $topCost = $cost;
                    $topBus = (string) ($row['bus'] ?? $row['bus_id'] ?? '');
                }
            }

            /* Recorded fuel cost history: daily totals for the training window
               plus the previous equal window's recorded cost for comparison. */
            $dailyCost = [];
            foreach ($service->getDailyFuelTrend($base) as $row) {
                $day = (string) ($row['day'] ?? '');
                if ($day === '') {
                    continue;
                }
                $dailyCost[] = [
                    'date' => $day,
                    'cost' => round((float) ($row['total'] ?? 0), 2),
                    'liters' => round((float) ($row['consumed_liters'] ?? 0), 2),
                    'trips' => (int) ($row['trips_count'] ?? 0),
                ];
            }

            return [
                'available' => true,
                'distance' => round($distance, 2),
                'consumed_liters' => (float) ($summary['total_fuel_consumed'] ?? 0),
                'valid_trips' => (int) ($summary['valid_trips_count'] ?? 0),
                'avg_price' => (float) ($summary['avg_price'] ?? 0),
                'previous_liters' => (float) ($previous['total_fuel_consumed'] ?? 0),
                'previous_avg_price' => (float) ($previous['avg_price'] ?? 0),
                'history_cost' => (float) ($summary['total_cost'] ?? 0),
                'previous_cost' => (float) ($previous['total_cost'] ?? 0),
                'daily_cost' => $dailyCost,
                'top_bus' => $topBus,
            ];
        } catch (\Throwable $e) {
            error_log('PredictiveAnalysisService::collectFuel failed: ' . $e->getMessage());
            return ['available' => false];
        }
    }

    /* =========================================================
       DATE / FILTER RESOLUTION
    ========================================================= */

    private function normalizeFilters(array $input, ?DateTimeImmutable $today = null): array
    {
        $horizon = (string) ($input['horizon'] ?? '');
        if (!in_array($horizon, ['7d', '30d', '90d'], true)) {
            $legacy = (string) ($input['period'] ?? '');
            $horizon = in_array($legacy, ['7d', '30d', '90d'], true) ? $legacy : '30d';
        }
        $periodDays = ['7d' => 7, '30d' => 30, '90d' => 90][$horizon];

        /* Standard historical window, resolved independently from the horizon.
           Legacy callers that passed the horizon as `period` keep forecasting. */
        $today = $today ?? new DateTimeImmutable('today');
        $rawPeriod = (string) ($input['date_period'] ?? $input['period'] ?? '');
        if (in_array($rawPeriod, ['7d', '30d', '90d'], true)) {
            $rawPeriod = '';
        }
        $from = $input['from'] ?? $input['history_start'] ?? null;
        $to = $input['to'] ?? $input['history_end'] ?? null;
        if ($rawPeriod === '' && !$from && !$to) {
            // The forecast horizon is not the training window. Use sufficient
            // completed history even when the current month has just started.
            $rawPeriod = 'custom';
            $to = $today->modify('-1 day')->format('Y-m-d');
            $from = $today->modify('-' . self::MAX_TRAINING_DAYS . ' days')->format('Y-m-d');
        }
        $range = DateFilterService::normalizeRequest([
            'period' => $rawPeriod,
            'from' => $from,
            'to' => $to,
        ], $today->format('Y-m-d'));

        $timeMap = [
            'all' => [null, null],
            'morning' => [6, 10],
            'midday' => [10, 14],
            'afternoon' => [14, 18],
            'evening' => [18, 22],
        ];
        $timePeriod = (string) ($input['time_period'] ?? 'all');
        if (!isset($timeMap[$timePeriod])) {
            $timePeriod = 'all';
        }

        return [
            'period' => $horizon,
            'horizon' => $horizon,
            'period_days' => $periodDays,
            'date_period' => $range['period'],
            'history_start' => $range['start'],
            'history_end' => $range['end'],
            'route' => trim((string) ($input['route'] ?? '')),
            'bus' => trim((string) ($input['bus'] ?? '')),
            'time_period' => $timePeriod,
            'time_start' => $timeMap[$timePeriod][0],
            'time_end' => $timeMap[$timePeriod][1],
        ];
    }

    private function resolveDates(array $filters, DateTimeImmutable $today): array
    {
        $periodDays = (int) ($filters['period_days'] ?? 30);
        $yesterday = $today->modify('-1 day');

        $requestedEnd = (string) ($filters['history_end'] ?? '');
        $end = new DateTimeImmutable($requestedEnd !== '' ? $requestedEnd : $yesterday->format('Y-m-d'));
        if ($end > $yesterday) {
            $end = $yesterday;
        }

        $requestedStart = (string) ($filters['history_start'] ?? '');
        $start = new DateTimeImmutable($requestedStart !== '' ? $requestedStart : $end->format('Y-m-d'));
        if ($start > $end) {
            $start = $end;
        }

        $rangeDays = (int) $start->diff($end)->days + 1;
        $trainingDays = min(self::MAX_TRAINING_DAYS, max(1, $rangeDays));
        $previousStart = $end->modify('-' . ($periodDays - 1) . ' days');

        return [
            'training_days' => $trainingDays,
            'range_days' => $rangeDays,
            'history_start' => $start->format('Y-m-d'),
            'history_end' => $end->format('Y-m-d'),
            'history_end_exclusive' => $end->modify('+1 day')->format('Y-m-d'),
            'forecast_start' => $today->format('Y-m-d'),
            'forecast_end' => $today->modify('+' . ($periodDays - 1) . ' days')->format('Y-m-d'),
            'previous_start' => $previousStart->format('Y-m-d'),
        ];
    }

    private function filtersPayload(array $filters, array $options): array
    {
        $timePeriods = [
            ['value' => 'all', 'label' => 'All Day'],
            ['value' => 'morning', 'label' => '6:00 AM - 10:00 AM'],
            ['value' => 'midday', 'label' => '10:00 AM - 2:00 PM'],
            ['value' => 'afternoon', 'label' => '2:00 PM - 6:00 PM'],
            ['value' => 'evening', 'label' => '6:00 PM - 10:00 PM'],
        ];

        return [
            'period' => $filters['period'],
            'date_period' => $filters['date_period'] ?? null,
            'date_periods' => DateFilterService::options(),
            'date_range' => [
                'start' => $filters['history_start'] ?? null,
                'end' => $filters['history_end'] ?? null,
                'summary' => DateFilterService::summary(
                    (string) ($filters['date_period'] ?? DateFilterService::DEFAULT_PERIOD),
                    (string) ($filters['history_start'] ?? date('Y-m-d')),
                    (string) ($filters['history_end'] ?? date('Y-m-d'))
                ),
            ],
            'route' => $filters['route'],
            'bus' => $filters['bus'],
            'time_period' => $filters['time_period'],
            'periods' => [
                ['value' => '7d', 'label' => '7 Days'],
                ['value' => '30d', 'label' => '30 Days'],
                ['value' => '90d', 'label' => '90 Days'],
            ],
            'routes' => array_values($options['routes'] ?? []),
            'buses' => array_values($options['buses'] ?? []),
            'time_periods' => $timePeriods,
        ];
    }

    private function unavailablePayload(
        array $filters,
        array $options,
        array $dates,
        int $daysWithData,
        int $passengerTotal,
        int $recordsUsed,
        int $completedTrips,
        string $reason,
        float $cv
    ): array {
        $periodDays = $filters['period_days'];
        $trainingDays = $dates['training_days'];

        if ($reason === 'inconsistent') {
            $detail = 'Daily demand varies too widely (about ' . round($cv, 1) . 'x the average) across the '
                . $trainingDays . '-day history to produce a reliable forecast.';
        } elseif ($reason === 'unreliable') {
            $detail = 'The computed forecast did not pass validation checks and was withheld.';
        } else {
            $reason = 'insufficient';
            $detail = 'Only ' . $daysWithData . ' day(s) with recorded demand (minimum ' . self::MIN_DAYS_WITH_DATA
                . ') and ' . $passengerTotal . ' passenger record(s) (minimum ' . self::MIN_PASSENGER_RECORDS
                . ') were found in the ' . $trainingDays . '-day history.';
        }

        return [
            'success' => true,
            'data_quality' => [
                'unavailable' => true,
                'insufficient' => $reason === 'insufficient',
                'inconsistent' => $reason === 'inconsistent',
                'reason' => $reason,
                'message' => self::UNAVAILABLE_MESSAGE,
                'detail' => $detail,
                'days_with_data' => $daysWithData,
                'min_days_required' => self::MIN_DAYS_WITH_DATA,
                'passenger_records' => $passengerTotal,
                'min_records_required' => self::MIN_PASSENGER_RECORDS,
                'training_days' => $trainingDays,
                'history_from' => $dates['history_start'],
                'history_to' => $dates['history_end'],
            ],
            'summary' => null,
            'forecast' => null,
            'by_time' => [],
            'fuel' => ['available' => false],
            'model' => [
                'name' => 'Weighted moving average + linear trend (baseline)',
                'records_used' => $recordsUsed,
                'passenger_records' => $passengerTotal,
                'completed_trips' => $completedTrips,
                'history_from' => $dates['history_start'],
                'history_to' => $dates['history_end'],
                'forecast_period' => [
                    'start' => $dates['forecast_start'],
                    'end' => $dates['forecast_end'],
                    'days' => $periodDays,
                ],
                'computed_at' => date('Y-m-d H:i:s'),
                'evaluation' => null,
                'reliability' => [
                    'level' => 'Unavailable',
                    'label' => 'Unavailable',
                    'score' => 0,
                    'coverage' => $trainingDays > 0 ? round($daysWithData / $trainingDays, 2) : 0.0,
                    'cv' => round($cv, 2),
                    'reason' => $detail,
                ],
                'sufficiency' => [
                    'days_with_data' => $daysWithData,
                    'training_days' => $trainingDays,
                    'passenger_records' => $passengerTotal,
                    'completed_trips' => $completedTrips,
                    'min_days_required' => self::MIN_DAYS_WITH_DATA,
                    'min_records_required' => self::MIN_PASSENGER_RECORDS,
                ],
            ],
            'insights' => [
                'Predictive insights are unavailable because the historical data is insufficient or inconsistent.',
            ],
            'filters' => $this->filtersPayload($filters, $options),
        ];
    }

    /* =========================================================
       SERIES / FORECAST MATH
    ========================================================= */

    /**
     * @param array<string, array{passengers: int, trips: int}> $daily
     * @return array<string, array{passengers: int, trips: int}>
     */
    private function continuousSeries(array $daily, string $start, string $endInclusive): array
    {
        $out = [];
        $cursor = new DateTimeImmutable($start);
        $end = new DateTimeImmutable($endInclusive);
        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $out[$day] = [
                'passengers' => (int) ($daily[$day]['passengers'] ?? 0),
                'trips' => (int) ($daily[$day]['trips'] ?? 0),
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $out;
    }

    private function sumCompletedTrips(array $completed): int
    {
        $total = 0;
        foreach ($completed as $row) {
            $total += (int) ($row['trips'] ?? 0);
        }
        return $total;
    }

    /**
     * Drop up to three trailing days whose recorded volume is far below the
     * recent level — typically the current day still being recorded. Returns
     * the values used for fitting plus the excluded dates for the chart.
     *
     * @param array<string, int> $dailyValues date => passengers
     * @return array{values: list<int>, incomplete_dates: list<string>}
     */
    private function trimIncompleteTail(array $dailyValues): array
    {
        $values = array_values($dailyValues);
        $dates = array_keys($dailyValues);
        $removed = [];

        while (count($values) >= 8 && count($removed) < 3) {
            $count = count($values);
            $last = (float) $values[$count - 1];

            $prior = array_slice($values, $count - 8, 7);
            $priorActive = [];
            foreach ($prior as $value) {
                if ((float) $value >= 1) {
                    $priorActive[] = (float) $value;
                }
            }
            if (!$priorActive) {
                break;
            }

            sort($priorActive);
            $median = $this->medianValue($priorActive);
            if ($last >= $median * 0.25) {
                break;
            }

            array_pop($values);
            $removed[] = (string) array_pop($dates);
        }

        return [
            'values' => array_map('intval', $values),
            'incomplete_dates' => array_reverse($removed),
        ];
    }

    /**
     * Weighted moving average blended with a linear trend over daily totals.
     * Returns points plus the residual spread that forms the range band.
     *
     * @param array<string, int> $dailyValues date => passengers
     * @return list<array{date: string, value: int, lower: int, upper: int}>
     */
    public function forecastSeries(array $dailyValues, int $horizon, DateTimeImmutable $forecastStart): array
    {
        $values = array_values($dailyValues);
        if (!$values || $horizon <= 0) {
            return [];
        }

        /* Spike-resistant fit: cap extreme days at median + 3x MAD, damp the
           trend, and never extrapolate above twice the typical high day. */
        $cap = $this->robustCap($values);
        $fit = $this->winsorized($values, $cap);
        $wma = $this->weightedMovingAverage($fit);
        $slope = $this->dampedSlope($wma, $this->linearSlope($fit));
        $spread = $this->residualStd($fit);
        $ceiling = $cap > 0 ? $cap * 2 : 0.0;

        $points = [];
        $cursor = $forecastStart;
        for ($step = 1; $step <= $horizon; $step++) {
            $centered = $wma + $slope * $step;
            if ($ceiling > 0) {
                $centered = min($centered, $ceiling);
            }
            $centered = max(0.0, $centered);
            $band = $cap > 0 ? min(self::RANGE_Z * $spread, $cap) : self::RANGE_Z * $spread;
            $points[] = [
                'date' => $cursor->format('Y-m-d'),
                'value' => (int) round($centered),
                'lower' => max(0, (int) floor($centered - $band)),
                'upper' => (int) ceil($centered + $band),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $points;
    }

    private function weightedMovingAverage(array $values, int $window = 7): float
    {
        $slice = array_slice($values, -$window);
        $count = count($slice);
        if ($count === 0) {
            return 0.0;
        }

        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($slice as $index => $value) {
            $weight = $index + 1;
            $weighted += (float) $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $weighted / $weightSum : 0.0;
    }

    private function linearSlope(array $values): float
    {
        $slice = array_slice($values, -30);
        $n = count($slice);
        if ($n < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;
        foreach ($slice as $x => $y) {
            $xf = (float) $x;
            $yf = (float) $y;
            $sumX += $xf;
            $sumY += $yf;
            $sumXY += $xf * $yf;
            $sumXX += $xf * $xf;
        }

        $denominator = $n * $sumXX - $sumX * $sumX;
        if (abs($denominator) < 1e-9) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }

    private function residualStd(array $values, int $window = 7): float
    {
        $n = count($values);
        if ($n <= $window) {
            return 0.0;
        }

        $residuals = [];
        $startIndex = max($window, $n - 14);
        for ($i = $startIndex; $i < $n; $i++) {
            $history = array_slice($values, 0, $i);
            $fitted = $this->weightedMovingAverage($history, $window);
            $residuals[] = (float) $values[$i] - $fitted;
        }

        if (count($residuals) < 3) {
            return 0.0;
        }

        $mean = array_sum($residuals) / count($residuals);
        $variance = 0.0;
        foreach ($residuals as $residual) {
            $variance += ($residual - $mean) ** 2;
        }

        return sqrt($variance / count($residuals));
    }

    /**
     * Rolling one-day-ahead backtest: for each of the most recent days that
     * have recorded demand, fit on everything before that day and predict it.
     * Because every prediction uses the latest history, abrupt level changes
     * do not inflate the error the way a single fixed train/test split did,
     * and the check stays meaningful when operations are not recorded daily.
     *
     * Always returns the reason so the UI can explain why no metric exists;
     * a meaningless error is described qualitatively instead of printing an
     * absurd percentage.
     *
     * @param list<int> $values daily passenger counts
     * @return array{evaluation: ?array, reason: ?string}
     */
    private function backtestEvaluation(array $values): array
    {
        $activeIndices = [];
        foreach ($values as $index => $value) {
            if ((int) $value >= 1) {
                $activeIndices[] = $index;
            }
        }

        if (count($activeIndices) < 3) {
            return [
                'evaluation' => null,
                'reason' => 'Fewer than 3 days in the history have recorded demand.',
            ];
        }

        $holdout = array_slice($activeIndices, -self::BACKTEST_DAYS);
        $absoluteError = 0.0;
        $actualTotal = 0.0;
        $predictedTotal = 0.0;
        $samples = 0;

        foreach ($holdout as $index) {
            $actual = (float) $values[$index];
            if ($actual < 1) {
                continue;
            }
            $predicted = $this->oneStepPrediction(array_slice($values, 0, (int) $index));
            if ($predicted === null) {
                continue;
            }
            $absoluteError += abs($actual - $predicted);
            $actualTotal += $actual;
            $predictedTotal += $predicted;
            $samples++;
        }

        if ($samples < 3 || $actualTotal <= 0) {
            return [
                'evaluation' => null,
                'reason' => 'Not enough history before the most recent days with demand to run a backtest.',
            ];
        }

        /* WAPE: total absolute error over total demand. Unlike MAPE it does not
           explode when a test day is small, so it measures what the forecast is
           actually used for here — total passenger volume for the period. */
        $wape = ($absoluteError / $actualTotal) * 100;

        /* A total-volume miss larger than the demand itself is not meaningful for
           a baseline model. It is withheld and described qualitatively instead of
           being shown as a misleading percentage. */
        if (!is_finite($wape) || $wape > self::MAX_MEANINGFUL_ERROR) {
            $actualAverage = $actualTotal / $samples;
            $predictedAverage = $predictedTotal / $samples;

            if ($predictedAverage >= $actualAverage * 2) {
                $reason = 'Recorded demand dropped sharply in the most recent days, so the forecast cannot be validated.';
            } elseif ($actualAverage >= $predictedAverage * 2) {
                $reason = 'Recorded demand rose sharply in the most recent days, so the forecast cannot be validated.';
            } else {
                $reason = 'Historical demand changes too abruptly to measure the forecast error reliably.';
            }

            return [
                'evaluation' => null,
                'reason' => $reason,
            ];
        }

        return [
            'evaluation' => [
                'metric' => 'WAPE',
                'value' => round($wape, 1),
                'sample_days' => $samples,
                'method' => 'Rolling one-day-ahead volume error on the most recent ' . count($holdout) . ' days with demand',
            ],
            'reason' => null,
        ];
    }

    /**
     * Prediction the robust model would have made for the next day, using the
     * same fits as forecastSeries().
     */
    private function oneStepPrediction(array $history): ?float
    {
        if (count($history) < 7) {
            return null;
        }

        $cap = $this->robustCap($history);
        $fit = $this->winsorized($history, $cap);
        $wma = $this->weightedMovingAverage($fit);
        $slope = $this->dampedSlope($wma, $this->linearSlope($fit));

        $predicted = $wma + $slope;
        if ($cap > 0) {
            $predicted = min($predicted, $cap * 2);
        }

        return max(0.0, $predicted);
    }

    /**
     * Robust upper bound for fitting: median + 3x median absolute deviation of
     * the days that actually have demand, so a spike cannot dominate the model.
     *
     * @param list<int|float> $values
     */
    private function robustCap(array $values): float
    {
        $active = [];
        foreach ($values as $value) {
            if ((float) $value >= 1) {
                $active[] = (float) $value;
            }
        }
        if (!$active) {
            return 0.0;
        }

        sort($active);
        $median = $this->medianValue($active);
        $deviations = [];
        foreach ($active as $value) {
            $deviations[] = abs($value - $median);
        }
        sort($deviations);
        $mad = $this->medianValue($deviations);

        return max(1.0, $median + 3 * $mad);
    }

    /** @param list<float> $sortedValues */
    private function medianValue(array $sortedValues): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $sortedValues[$middle];
        }

        return ((float) $sortedValues[$middle - 1] + (float) $sortedValues[$middle]) / 2;
    }

    /**
     * @param list<int|float> $values
     * @return list<float>
     */
    private function winsorized(array $values, float $cap): array
    {
        $out = [];
        foreach ($values as $value) {
            $float = (float) $value;
            $out[] = ($cap > 0 && $float > $cap) ? $cap : $float;
        }

        return $out;
    }

    /** Limit the trend to 5% of the recent level per day so it cannot explode. */
    private function dampedSlope(float $base, float $slope): float
    {
        $maxStep = max(1.0, $base * 0.05);

        return min(max($slope, -$maxStep), $maxStep);
    }

    /* =========================================================
       DEMAND SECTIONS
    ========================================================= */

    private function demandLevel(float $value, float $average): string
    {
        if ($average <= 0) {
            return $value > 0 ? 'High' : 'Low';
        }
        if ($value >= $average * self::HIGH_FACTOR) {
            return 'High';
        }
        if ($value >= $average * self::MEDIUM_FACTOR) {
            return 'Medium';
        }
        return 'Low';
    }

    private function direction(float $changePct): string
    {
        if ($changePct > 0.5) {
            return 'up';
        }
        if ($changePct < -0.5) {
            return 'down';
        }
        return 'stable';
    }

    /**
     * Fixed 2-hour windows requested by the page: 6 AM through 8 PM.
     *
     * @param array<string, array<int, array{passengers: int, trips: int}>> $hourly
     * @return list<array<string, mixed>>
     */
    private function buildDemandByTime(array $hourly, string $start, string $endInclusive): array
    {
        $windows = [[6, 8], [8, 10], [10, 12], [12, 14], [14, 16], [16, 18], [18, 20]];

        $days = [];
        $cursor = new DateTimeImmutable($start);
        $end = new DateTimeImmutable($endInclusive);
        while ($cursor <= $end) {
            $days[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $buckets = [];
        foreach ($windows as [$from, $to]) {
            $dailyTotals = [];
            $passengers = 0;
            $trips = 0;

            foreach ($days as $day) {
                $dayTotal = 0;
                for ($hour = $from; $hour < $to; $hour++) {
                    $cell = $hourly[$day][$hour] ?? null;
                    if ($cell === null) {
                        continue;
                    }
                    $dayTotal += (int) $cell['passengers'];
                    $trips += (int) $cell['trips'];
                }
                $dailyTotals[] = $dayTotal;
                $passengers += $dayTotal;
            }

            $daysWithService = count(array_filter($dailyTotals, static fn (int $v): bool => $v > 0));
            $activeTotals = array_values(array_filter($dailyTotals, static fn (int $v): bool => $v > 0));
            $averageDaily = $activeTotals ? array_sum($activeTotals) / count($activeTotals) : 0.0;
            $std = $this->stdDev($activeTotals);

            $buckets[] = [
                'label' => $this->formatHourLabel($from) . "\u{2013}" . $this->formatHourLabel($to),
                'hour_start' => $from,
                'hour_end' => $to,
                'passengers' => $passengers,
                'trips' => $trips,
                'days_with_service' => $daysWithService,
                'avg_daily' => (int) round($averageDaily),
                'range_low' => max(0, (int) round($averageDaily - $std)),
                'range_high' => (int) round($averageDaily + $std),
                'avg_per_trip' => $trips > 0 ? round($passengers / $trips, 1) : null,
                'level' => 'Low',
                'is_peak' => false,
            ];
        }

        $averageBucket = 0.0;
        $withData = array_values(array_filter($buckets, static fn (array $b): bool => $b['passengers'] > 0));
        if ($withData) {
            $averageBucket = array_sum(array_column($withData, 'passengers')) / count($withData);
        }

        $peakIndex = 0;
        $peakValue = -1;
        foreach ($buckets as $index => $bucket) {
            $buckets[$index]['level'] = $bucket['passengers'] > 0
                ? $this->demandLevel((float) $bucket['passengers'], $averageBucket)
                : 'Low';
            if ($bucket['passengers'] > $peakValue) {
                $peakValue = $bucket['passengers'];
                $peakIndex = $index;
            }
        }

        if ($peakValue > 0) {
            $buckets[$peakIndex]['is_peak'] = true;
        }

        return $buckets;
    }

    private function pickPeak(array $byTime): ?array
    {
        $peak = null;
        foreach ($byTime as $bucket) {
            if (!empty($bucket['is_peak'])) {
                $peak = $bucket;
                break;
            }
        }

        if ($peak === null) {
            return null;
        }

        return [
            'label' => $peak['label'],
            'hour_start' => $peak['hour_start'],
            'hour_end' => $peak['hour_end'],
            'passengers' => $peak['passengers'],
            'avg_daily' => $peak['avg_daily'],
            'range_low' => $peak['range_low'],
            'range_high' => $peak['range_high'],
            'avg_per_trip' => $peak['avg_per_trip'],
            'level' => $peak['level'],
        ];
    }

    /* =========================================================
       VALIDATION / RELIABILITY
    ========================================================= */

    private function coefficientOfVariation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        if ($mean <= 0) {
            return 0.0;
        }

        return $this->stdDev($values) / $mean;
    }

    /**
     * Guards the computed forecast before anything is shown:
     * finite, non-negative, ordered range, positive total when demand exists,
     * and no runaway trend above the historical daily peak.
     *
     * @param list<array{date: string, value: int, lower: int, upper: int}> $points
     * @param list<int> $historyValues
     * @return string|null 'unreliable' when invalid, null when valid
     */
    private function validateForecast(array $points, array $historyValues): ?string
    {
        if (!$points) {
            return 'unreliable';
        }

        $historyTotal = array_sum($historyValues);
        $historyMax = $historyValues ? (int) max($historyValues) : 0;
        $forecastTotal = 0;
        $forecastMax = 0;

        foreach ($points as $point) {
            $value = (float) ($point['value'] ?? 0);
            $lower = (float) ($point['lower'] ?? 0);
            $upper = (float) ($point['upper'] ?? 0);

            if (!is_finite($value) || !is_finite($lower) || !is_finite($upper)) {
                return 'unreliable';
            }
            if ($value < 0 || $lower < 0 || $upper < 0) {
                return 'unreliable';
            }
            if ($lower > $value || $upper < $value) {
                return 'unreliable';
            }

            $forecastTotal += $value;
            $forecastMax = max($forecastMax, (int) $value);
        }

        if ($historyTotal > 0 && $forecastTotal <= 0) {
            return 'unreliable';
        }

        if ($historyMax > 0 && $forecastMax > $historyMax * self::MAX_FORECAST_GROWTH_FACTOR) {
            return 'unreliable';
        }

        return null;
    }

    /**
     * Limited / Moderate / Good derived from actual coverage, completed-trip
     * volume, computed backtest error, and daily consistency.
     */
    private function assessReliability(
        int $daysWithData,
        int $trainingDays,
        int $completedTrips,
        ?array $evaluation,
        float $cv,
        ?string $evaluationReason = null
    ): array {
        $coverage = $trainingDays > 0 ? $daysWithData / $trainingDays : 0.0;

        /* Without a meaningful backtest the quality cannot be quantified. */
        if ($evaluation === null || !isset($evaluation['value']) || !is_finite((float) $evaluation['value'])) {
            return [
                'level' => 'Unknown',
                'label' => 'Forecast reliability cannot be determined yet.',
                'score' => null,
                'coverage' => round($coverage, 2),
                'cv' => round($cv, 2),
                'reason' => $evaluationReason ?: 'A meaningful backtest error could not be computed from the available history.',
            ];
        }

        $score = 0;
        $limits = [];

        if ($coverage >= 0.8) {
            $score += 2;
        } elseif ($coverage >= 0.6) {
            $score += 1;
            $limits[] = 'partial daily coverage';
        } else {
            $limits[] = 'sparse daily coverage';
        }

        if ($completedTrips >= 60) {
            $score += 1;
        } elseif ($completedTrips < self::MIN_PASSENGER_RECORDS) {
            $limits[] = 'few completed trips';
        }

        $mape = (float) $evaluation['value'];
        if ($mape <= 20) {
            $score += 2;
        } elseif ($mape <= 40) {
            $score += 1;
            $limits[] = 'moderate backtest error';
        } else {
            $limits[] = 'high backtest error';
        }

        if ($cv <= 0.8) {
            $score += 1;
        } elseif ($cv > 1.2) {
            $limits[] = 'inconsistent daily demand';
        }

        if ($score >= 5) {
            $level = 'Good';
        } elseif ($score >= 3) {
            $level = 'Moderate';
        } else {
            $level = 'Limited';
        }

        $reason = $limits
            ? ucfirst(implode('; ', array_unique($limits))) . '.'
            : 'Coverage, completed trips, and backtest checks all passed.';

        return [
            'level' => $level,
            'label' => $level,
            'score' => $score,
            'coverage' => round($coverage, 2),
            'cv' => round($cv, 2),
            'reason' => $reason,
        ];
    }

    /* =========================================================
       FUEL SECTION
    ========================================================= */

    private function buildFuelForecast(
        array $fuel,
        array $completed,
        int $passengerSourceTrips,
        int $periodDays,
        array $dates,
        array $filters
    ): array {
        if (empty($fuel['available'])) {
            return [
                'available' => false,
                'message' => 'Fuel requirements cannot be estimated until fuel and distance history is recorded for this scope.',
                'bus_context' => $filters['bus'] !== '' ? $filters['bus'] : null,
                'route_context' => $filters['route'] !== '' ? $filters['route'] : null,
                'period_label' => 'Next ' . $periodDays . ' days',
                'period_days' => $periodDays,
                'change_pct' => null,
                'estimated_cost' => null,
                'history_cost' => null,
                'previous_cost' => null,
                'cost_change_pct' => null,
                'cost_available' => false,
                'daily_cost' => [],
            ];
        }

        $distance = (float) ($fuel['distance'] ?? 0);
        $liters = (float) ($fuel['consumed_liters'] ?? 0);
        $validTrips = (int) ($fuel['valid_trips'] ?? 0);
        $previousLiters = (float) ($fuel['previous_liters'] ?? 0);

        $avgDistancePerTrip = $validTrips > 0 ? $distance / $validTrips : 0.0;
        $kpl = ($distance > 0 && $liters > 0) ? $distance / $liters : 0.0;
        $avgLitersPerTrip = $validTrips > 0 ? $liters / $validTrips : 0.0;

        $historyDays = max(1, $dates['training_days']);
        $completedTrips = $this->sumCompletedTrips($completed);
        $fuelTrips = (int) ($fuel['valid_trips'] ?? 0);

        /* Use the largest actual trip count: bus_trips can be nearly empty on
           imported history while passenger and fuel records prove the real
           volume, and an under-counted trip base skews fuel way down. */
        $tripBase = max($completedTrips, $passengerSourceTrips, $fuelTrips);
        $tripsPerDay = $tripBase / $historyDays;
        $projectedTrips = $periodDays > 0 ? (int) round($tripsPerDay * $periodDays) : 0;

        $expectedDistance = $avgDistancePerTrip * $projectedTrips;
        if ($kpl > 0 && $expectedDistance > 0) {
            $estimatedLiters = $expectedDistance / $kpl;
        } else {
            $expectedDistance = 0.0;
            $estimatedLiters = $avgLitersPerTrip * $projectedTrips;
        }

        $changePct = $previousLiters > 0
            ? round((($estimatedLiters - $previousLiters) / $previousLiters) * 100, 1)
            : null;

        /* Fuel cost projection: estimated liters valued at the period's
           recorded diesel price, compared with the previous window's recorded
           fuel cost. Never a fabricated price or cost. */
        $avgPrice = (float) ($fuel['avg_price'] ?? 0);
        $historyCost = (float) ($fuel['history_cost'] ?? 0);
        $previousCost = (float) ($fuel['previous_cost'] ?? 0);
        $estimatedCost = ($estimatedLiters > 0 && $avgPrice > 0)
            ? round($estimatedLiters * $avgPrice, 2)
            : null;
        $costChangePct = ($estimatedCost !== null && $previousCost > 0)
            ? round((($estimatedCost - $previousCost) / $previousCost) * 100, 1)
            : null;

        return [
            'available' => $estimatedLiters > 0 || $expectedDistance > 0,
            'expected_distance' => round($expectedDistance, 1),
            'avg_distance_per_trip' => round($avgDistancePerTrip, 1),
            'estimated_kpl' => round($kpl, 2),
            'estimated_liters' => round($estimatedLiters, 1),
            'avg_liters_per_trip' => round($avgLitersPerTrip, 2),
            'history_liters' => round($liters, 1),
            'projected_trips' => $projectedTrips,
            'previous_liters' => round($previousLiters, 1),
            'change_pct' => $changePct,
            'rate_scope' => $filters['bus'] !== '' ? 'bus' : 'fleet',
            'bus_context' => $filters['bus'] !== '' ? $filters['bus'] : (string) ($fuel['top_bus'] ?? ''),
            'route_context' => $filters['route'] !== '' ? $filters['route'] : null,
            'avg_price' => $avgPrice,
            'history_cost' => round($historyCost, 2),
            'previous_cost' => round($previousCost, 2),
            'estimated_cost' => $estimatedCost,
            'cost_change_pct' => $costChangePct,
            'cost_available' => $estimatedCost !== null,
            'daily_cost' => is_array($fuel['daily_cost'] ?? null) ? $fuel['daily_cost'] : [],
            'period_days' => $periodDays,
            'period_label' => 'Next ' . $periodDays . ' days',
        ];
    }

    /* =========================================================
       INSIGHTS
    ========================================================= */

    private function buildInsights(
        array $summary,
        array $fuel,
        array $forecastPoints,
        int $periodDays
    ): array {
        $insights = [];

        $peak = $summary['peak'] ?? null;
        if (is_array($peak) && !empty($peak['label'])) {
            $insights[] = 'Demand is expected to peak between ' . $peak['label'] . '.';
        }

        $passengerChange = $summary['passengers']['change_pct'] ?? null;
        if (count($insights) < 3 && $passengerChange !== null && $summary['passengers']['change_direction'] !== 'stable') {
            $insights[] = 'Passenger demand is expected to ' . ($passengerChange >= 0 ? 'increase' : 'decrease')
                . ' about ' . abs($passengerChange) . '% versus the previous ' . $periodDays . ' days.';
        }

        if (count($insights) < 3 && $forecastPoints) {
            $average = array_sum(array_column($forecastPoints, 'value')) / count($forecastPoints);
            $peakPoint = $forecastPoints[0];
            foreach ($forecastPoints as $point) {
                if ($point['value'] > $peakPoint['value']) {
                    $peakPoint = $point;
                }
            }
            if ($average > 0 && $peakPoint['value'] >= $average * 1.15) {
                $insights[] = 'Highest demand is expected on ' . date('M j', strtotime((string) $peakPoint['date']))
                    . ' (~' . $peakPoint['value'] . ' passengers).';
            }
        }

        if (count($insights) < 3 && !empty($fuel['available'])) {
            $fuelChange = $fuel['change_pct'] ?? null;
            if ($fuelChange !== null) {
                $insights[] = 'Fuel need is about ' . $fuel['estimated_liters'] . ' L, '
                    . ($fuelChange >= 0 ? 'up' : 'down') . ' ' . abs((float) $fuelChange) . '% versus the previous period.';
            } else {
                $insights[] = 'Fuel need is about ' . $fuel['estimated_liters'] . ' L for the next ' . $periodDays . ' days.';
            }
        }

        return array_slice($insights, 0, 3);
    }

    /* =========================================================
       SMALL UTILITIES
    ========================================================= */

    private function stdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ((float) $value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }

    private function formatHourLabel(int $hour): string
    {
        $normalized = $hour % 24;
        $suffix = $normalized < 12 ? 'AM' : 'PM';
        $display = $normalized % 12;
        if ($display === 0) {
            $display = 12;
        }
        return $display . ':00 ' . $suffix;
    }
}
