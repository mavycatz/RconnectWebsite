<?php
declare(strict_types=1);

/**
 * Live-database reconciliation harness for FinancialIntelligenceService.
 *
 * Runs the service against the real R-Connect database and compares each
 * section's aggregates with independently executed read-only SQL over the
 * same authoritative sources, for several filter scenarios. The script exits
 * 0 when the database is unavailable so it can run in environments without
 * MySQL; it prints SKIPPED in that case.
 *
 * Usage: php finance/tests/financial_intelligence_live_test.php [period] [from] [to]
 * Defaults to the monthly period; custom ranges take from and to.
 */
require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../services/FinancialIntelligenceService.php';
require_once __DIR__ . '/../services/FinancialPerformanceService.php';

use App\Core\Database;
use Finance\Services\FinancialIntelligenceService;
use Finance\Services\FinancialPerformanceService;

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$close = static fn($a, $b, float $delta = 0.02): bool => abs((float) $a - (float) $b) <= $delta;

$db = Database::tryGet();
if ($db === null) {
    echo "SKIPPED: live database unavailable; run this script where MySQL is reachable.\n";
    exit(0);
}

$input = ['period' => $argv[1] ?? 'monthly'];
if (($argv[1] ?? '') === 'custom' && isset($argv[2], $argv[3])) {
    $input['from'] = $argv[2];
    $input['to'] = $argv[3];
}
$filters = FinancialIntelligenceService::resolveFilters($input);
echo 'Live reconciliation for ' . $filters['label'] . ' (' . $filters['from'] . ' to ' . $filters['to'] . ")\n";

$service = new FinancialIntelligenceService($db);

/* ---- Baseline internal consistency and cross-service reconciliation ---- */
$baseline = $service->overview($filters);
$baselineActual = $baseline['actual'];
$check($close($baselineActual['profit'], $baselineActual['collections'] - $baselineActual['expenses']), 'Profit equals collections minus recorded expenses');
if ($baseline['waterfall'] !== []) {
    $last = $baseline['waterfall'][count($baseline['waterfall']) - 1];
    $check($close($last['value'], $baselineActual['profit_after_configured']), 'Waterfall ends at the estimated operating profit');
    $check($close($last['cumulative'], $baselineActual['profit_after_configured']), 'Waterfall cumulative reconciles to the estimated operating profit');
}
echo '  baseline collections=' . $baselineActual['collections'] . ' expenses=' . $baselineActual['expenses'] . ' profit=' . $baselineActual['profit']
    . ' configured=' . $baselineActual['configured_costs'] . ' estimated_profit=' . $baselineActual['profit_after_configured']
    . ' passengers=' . $baselineActual['passengers'] . ' trips=' . $baselineActual['trips'] . "\n";

$performance = new FinancialPerformanceService($db);
$snapshot = $performance->build(['report_period' => 'custom', 'from' => $filters['from'], 'to' => $filters['to']]);
$check($close($snapshot['totals']['revenue'], $baselineActual['collections'], 0.05), 'Collections reconcile with Financial Performance revenue');
$check($close($snapshot['totals']['expenses'], $baselineActual['expenses'], 0.05), 'Expenses reconcile with Financial Performance expenses');
$check($close($snapshot['totals']['profit'], $baselineActual['profit'], 0.05), 'Profit reconciles with Financial Performance profit');

$passengersSection = $service->passengers($filters);
$check(is_array($passengersSection['types']), 'Passenger types section returns categories');
$check(is_array($passengersSection['baggage']), 'Baggage fare category is separated from passenger types');
$check(($passengersSection['accounts']['journeys'] ?? null) !== ($passengersSection['accounts']['registered'] ?? null) || $passengersSection['accounts']['journeys'] === 0, 'Journeys and registered accounts are distinct measures');

$forecast = $service->forecast($filters, ['horizon' => '30d']);
if (($forecast['available'] ?? false) === true) {
    $check($close($forecast['profit']['estimate'], $forecast['collections']['forecast_total'] - $forecast['expenses']['forecast_total'], 0.05), 'Forecast profit subtracts forecast expenses');
} else {
    echo '  forecast: unavailable (' . ($forecast['message'] ?? 'insufficient history') . ")\n";
}

$whatIf = $service->whatIf($filters, ['fare_change_pct' => 5, 'volume_change_pct' => 2, 'fuel_price_change_pct' => 3, 'other_expense_change_pct' => -2]);
$check($close($whatIf['projected']['profit'], $whatIf['projected']['collections'] - $whatIf['projected']['expenses']), 'What-if profit equals projected collections minus projected expenses');
$optionsBaseline = $service->options();
$check(count($optionsBaseline['periods']) === 9, 'Options expose every supported reporting period (Year over Year removed)');
$check(!in_array('yoy', array_column($optionsBaseline['periods'], 'value'), true), 'The Year over Year option is not offered live');

/* ---- Authoritative data-source map vs the live schema (read-only) ---- */
$liveColumns = [];
$stmt = $db->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $liveColumns[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])] = true;
}
$optionalAbsent = ['financial_cost_settings'];
$mapFailures = [];
foreach (FinancialIntelligenceService::dataSourceMap() as $sectionName => $entries) {
    foreach ($entries as $entry) {
        $table = strtolower((string) $entry['table']);
        if (!isset($liveColumns[$table])) {
            if (!in_array($table, $optionalAbsent, true)) {
                $mapFailures[] = $table . '.*';
            }
            continue;
        }
        foreach ($entry['columns'] as $column) {
            if (!isset($liveColumns[$table][strtolower((string) $column)])) {
                $mapFailures[] = $table . '.' . $column;
            }
        }
    }
}
$check($mapFailures === [], 'The data-source map matches the live schema (missing: ' . implode(', ', $mapFailures) . ')');
echo "  data-source map: verified against the live schema\n";

/* ---- SQL helpers ---- */
$sqlValue = static function (string $sql, array $params = []) use ($db) {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
};

$historyExists = isset($liveColumns['passenger_trip_history']);
$historyDate = "COALESCE(NULLIF(h.trip_date, '0000-00-00'), DATE(h.date_booked))";
$untilExclusive = static fn(string $to): string => (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');

$paidLive = "LOWER(TRIM(p.payment_status)) = 'paid'";
/* The live fare source has no `type` column, so no live row is baggage; the
   archived source marks extras with type <> 'passenger'. */
$nonBaggageLive = '1 = 1';
$paidHistory = "LOWER(TRIM(h.payment_status)) = 'paid'";
$nonBaggageHistory = "(h.type IS NULL OR TRIM(h.type) = '' OR LOWER(TRIM(h.type)) = 'passenger')";

/* ---- Section reconciliation scenarios ---- */
$scenarios = [
    'today' => FinancialIntelligenceService::resolveFilters(['period' => 'today']),
    'historical month' => FinancialIntelligenceService::resolveFilters(['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']),
    'year vs previous year' => FinancialIntelligenceService::resolveFilters(['period' => 'yearly', 'year' => '2026']),
];
$options = $service->options();
if (!empty($options['routes'])) {
    $scenarios['one route'] = FinancialIntelligenceService::resolveFilters(['period' => 'monthly', 'route' => (string) $options['routes'][0]]);
}
if (!empty($options['buses'])) {
    $scenarios['one bus'] = FinancialIntelligenceService::resolveFilters(['period' => 'monthly', 'bus' => (string) $options['buses'][0]]);
}
$scenarios['one passenger type'] = FinancialIntelligenceService::resolveFilters(['period' => 'monthly', 'passenger_type' => 'Regular']);
$scenarios['custom range'] = FinancialIntelligenceService::resolveFilters(['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-10-15']);

$reconciled = 0;
foreach ($scenarios as $label => $scenarioFilters) {
    /* A fresh service per scenario mirrors production (one request = one
       instance) and keeps memo caches from accumulating across windows. */
    $service = new FinancialIntelligenceService($db);
    $from = $scenarioFilters['from'];
    $to = $scenarioFilters['to'];
    $until = $untilExclusive($to);
    $params = [':from' => $from, ':until' => $until];

    /* Scenario-specific SQL predicates mirroring the applied filters, so the
       reconciliation queries run on exactly the same scope as the service. */
    $liveExtra = '';
    $liveJoin = '';
    $historyExtra = '';
    $fuelExtra = '';
    $scenarioParams = $params;
    $extFrom = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
    $extTo = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $routeKeySql = "REPLACE(REPLACE(LOWER(bt.route_name), ' ', ''), '-', '')";
    if ($scenarioFilters['route'] !== '') {
        $routeKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $scenarioFilters['route']));
        $liveExtra .= " AND EXISTS (SELECT 1 FROM bus_trips bt WHERE bt.id = CAST(p.trip_no AS UNSIGNED) AND bt.trip_date >= :extfrom AND bt.trip_date <= :extto AND $routeKeySql = :routekey)";
        $historyExtra .= " AND h.trip_id LIKE 'ARCV-%' AND EXISTS (SELECT 1 FROM bus_trips bt WHERE bt.id = CAST(SUBSTRING(TRIM(h.trip_id), 6) AS UNSIGNED) AND bt.trip_date >= :extfrom AND bt.trip_date <= :extto AND $routeKeySql = :routekey)";
        $fuelExtra .= " AND EXISTS (SELECT 1 FROM bus_trips bt WHERE bt.id = CAST(fr.trip_id AS UNSIGNED) AND bt.trip_date >= :extfrom AND bt.trip_date <= :extto AND $routeKeySql = :routekey)";
        $scenarioParams[':routekey'] = $routeKey;
        $scenarioParams[':extfrom'] = $extFrom;
        $scenarioParams[':extto'] = $extTo;
    }
    if ($scenarioFilters['bus'] !== '') {
        $liveExtra .= ' AND LOWER(TRIM(p.bus_id)) = LOWER(:busc)';
        $historyExtra .= ' AND LOWER(TRIM(COALESCE(h.bus_number, h.bus_id))) = LOWER(:busc)';
        $fuelExtra .= ' AND LOWER(TRIM(fr.bus_id)) = LOWER(:busc)';
        $scenarioParams[':busc'] = (string) $scenarioFilters['bus'];
    }
    if ($scenarioFilters['passenger_type'] !== '') {
        $liveJoin = ' LEFT JOIN passengers pp ON pp.id = p.passenger_id';
        $ptypeExpr = "(CASE WHEN p.passenger_type IS NOT NULL AND TRIM(p.passenger_type) <> '' THEN LOWER(TRIM(p.passenger_type)) WHEN pp.type IS NOT NULL AND TRIM(pp.type) <> '' THEN LOWER(TRIM(pp.type)) ELSE '' END)";
        $ptype = strtolower((string) $scenarioFilters['passenger_type']);
        if ($ptype === 'regular') {
            /* The service maps empty and generic values to Regular. */
            $liveExtra .= " AND $ptypeExpr IN ('', 'regular', 'normal', 'adult', 'passenger', 'none')";
            $historyExtra .= " AND COALESCE(LOWER(TRIM(h.passenger_type)), '') IN ('', 'regular', 'normal', 'adult', 'passenger', 'none')";
        } else {
            $liveExtra .= " AND $ptypeExpr = :ptype";
            $historyExtra .= ' AND LOWER(TRIM(h.passenger_type)) = :ptype';
            $scenarioParams[':ptype'] = $ptype;
        }
    }

    $overview = $service->overview($scenarioFilters);
    $actual = $overview['actual'];

    /* Collections: live paid + archived paid, minus the archived twin of a
       live fare (numeric history trip_id matching the live id/date/driver/fare). */
    $liveCollections = (float) $sqlValue(
        "SELECT COALESCE(SUM(p.fare), 0) FROM passenger_trips p $liveJoin WHERE p.boarding_time >= :from AND p.boarding_time < :until AND $paidLive $liveExtra",
        $scenarioParams
    );
    $historyCollections = 0.0;
    $historyPassengers = 0;
    $historyTwins = 0;
    if ($historyExists) {
        $historyCollections = (float) $sqlValue(
            "SELECT COALESCE(SUM(h.fare), 0) FROM passenger_trip_history h WHERE $historyDate >= :from AND $historyDate < :until AND $paidHistory $historyExtra",
            $scenarioParams
        );
        $historyTwins = (float) $sqlValue(
            "SELECT COALESCE(SUM(h.fare), 0) FROM passenger_trip_history h
             WHERE $historyDate >= :from AND $historyDate < :until AND $paidHistory $historyExtra
               AND h.trip_id REGEXP '^[0-9]+$'
               AND EXISTS (
                   SELECT 1 FROM passenger_trips p
                   WHERE p.id = CAST(h.trip_id AS UNSIGNED)
                     AND DATE(p.boarding_time) = $historyDate
                     AND p.driver_id = h.driver_id
                     AND p.fare = h.fare
                     AND $paidLive
               )",
            $scenarioParams
        );
    }
    $sqlCollections = $liveCollections + $historyCollections - $historyTwins;
    $check(
        $close($actual['collections'], $sqlCollections, 0.05),
        "Collections reconcile with SQL for '" . $label . "' (service " . $actual['collections'] . ' vs SQL ' . $sqlCollections . ')'
    );

    /* Passenger journeys: paid non-baggage records, single-counted. */
    $livePassengers = (int) $sqlValue(
        "SELECT COUNT(*) FROM passenger_trips p $liveJoin WHERE p.boarding_time >= :from AND p.boarding_time < :until AND $paidLive AND $nonBaggageLive $liveExtra",
        $scenarioParams
    );
    if ($historyExists) {
        $historyPassengers = (int) $sqlValue(
            "SELECT COUNT(*) FROM passenger_trip_history h
             WHERE $historyDate >= :from AND $historyDate < :until AND $paidHistory AND $nonBaggageHistory $historyExtra
               AND NOT (h.trip_id REGEXP '^[0-9]+$' AND EXISTS (
                   SELECT 1 FROM passenger_trips p
                   WHERE p.id = CAST(h.trip_id AS UNSIGNED)
                     AND DATE(p.boarding_time) = $historyDate
                     AND p.driver_id = h.driver_id
                     AND p.fare = h.fare
                     AND $paidLive
               ))",
            $scenarioParams
        );
    }
    $check(
        (int) $actual['passengers'] === $livePassengers + $historyPassengers,
        "Passenger journeys reconcile with SQL for '" . $label . "' (service " . $actual['passengers'] . ' vs SQL ' . ($livePassengers + $historyPassengers) . ')'
    );

    /* Completed recorded trips and operating routes. */
    $sqlCompletedTrips = (int) $sqlValue(
        "SELECT COUNT(*) FROM bus_trips bt WHERE bt.trip_date >= :from AND bt.trip_date < :until AND LOWER(TRIM(bt.status)) = 'completed'",
        $params
    );
    $sqlCompletedRoutes = (int) $sqlValue(
        "SELECT COUNT(DISTINCT bt.route_name) FROM bus_trips bt WHERE bt.trip_date >= :from AND bt.trip_date < :until AND LOWER(TRIM(bt.status)) = 'completed'",
        $params
    );
    $fleet = $service->fleet($scenarioFilters);
    if ($scenarioFilters['route'] === '' && $scenarioFilters['bus'] === '') {
        $check(
            (int) ($fleet['route_activity']['completed_trips'] ?? -1) === $sqlCompletedTrips,
            "Completed trips reconcile with SQL for '" . $label . "' (service " . ($fleet['route_activity']['completed_trips'] ?? 'n/a') . ' vs SQL ' . $sqlCompletedTrips . ')'
        );
        $check(
            (int) ($fleet['route_activity']['routes_with_trips'] ?? -1) === $sqlCompletedRoutes,
            "Operating routes reconcile with SQL for '" . $label . "' (service " . ($fleet['route_activity']['routes_with_trips'] ?? 'n/a') . ' vs SQL ' . $sqlCompletedRoutes . ')'
        );
    }
    $check($fleet['reconciliation']['balanced'] === true, "Route collections plus unattributed reconcile to the total for '" . $label . "'");

    /* Fuel purchases: recorded refuel receipts plus legacy logs with liters,
       cross-source de-duplicated by bus/date/liters/cost. */
    $refuelCost = (float) $sqlValue(
        'SELECT COALESCE(SUM(total_cost), 0) FROM fuel_refuels fr WHERE fr.created_at >= :from AND fr.created_at < :until' . $fuelExtra,
        $scenarioParams
    );
    $legacyCost = (float) $sqlValue(
        "SELECT COALESCE(SUM(fl.cost), 0) FROM fuel_logs fl
         WHERE fl.created_at >= :from AND fl.created_at < :until AND COALESCE(fl.liters, 0) > 0
           AND NOT EXISTS (
               SELECT 1 FROM fuel_refuels fr
               WHERE LOWER(TRIM(fr.bus_id)) = LOWER(TRIM(fl.bus_id))
                 AND DATE(fr.created_at) = DATE(fl.created_at)
                 AND ROUND(fr.liters, 2) = ROUND(fl.liters, 2)
                 AND ROUND(fr.total_cost, 2) = ROUND(fl.cost, 2)
           )",
        $params
    );
    $fuel = $service->fuel($scenarioFilters);
    $check(
        $close($fuel['summary']['purchase_cost'], $refuelCost + $legacyCost, 0.05),
        "Fuel purchases reconcile with SQL for '" . $label . "' (service " . $fuel['summary']['purchase_cost'] . ' vs SQL ' . ($refuelCost + $legacyCost) . ')'
    );
    if ($scenarioFilters['route'] === '' && $scenarioFilters['bus'] === '') {
        $fuelCoverage = $fuel['summary']['measurements'];
        $check(
            array_sum($fuelCoverage) === (int) $sqlValue(
                'SELECT COUNT(*) FROM fuel_logs fl WHERE fl.created_at >= :from AND fl.created_at < :until',
                $params
            ),
            "Fuel measurement states cover every recorded log for '" . $label . "'"
        );
    }

    /* Remittances: created_at fallback (live table has no business_date). */
    $remitCount = (int) $sqlValue(
        'SELECT COUNT(*) FROM remittance_logs rl WHERE COALESCE(DATE(rl.created_at), DATE(rl.verified_at)) >= :from AND COALESCE(DATE(rl.created_at), DATE(rl.verified_at)) < :until',
        $params
    );
    $remitExpected = (float) $sqlValue(
        'SELECT COALESCE(SUM(rl.expected_cash), 0) FROM remittance_logs rl WHERE COALESCE(DATE(rl.created_at), DATE(rl.verified_at)) >= :from AND COALESCE(DATE(rl.created_at), DATE(rl.verified_at)) < :until',
        $params
    );
    $remittance = $service->remittance($scenarioFilters);
    if ($scenarioFilters['route'] === '' && $scenarioFilters['bus'] === '') {
        $check(
            (int) $remittance['summary']['remittance_count'] === $remitCount,
            "Remittance count reconciles with SQL for '" . $label . "' (service " . $remittance['summary']['remittance_count'] . ' vs SQL ' . $remitCount . ')'
        );
        $check(
            $close($remittance['summary']['expected_cash'], $remitExpected, 0.05),
            "Remittance expected cash reconciles with SQL for '" . $label . "' (service " . $remittance['summary']['expected_cash'] . ' vs SQL ' . $remitExpected . ')'
        );
    }

    /* Fare coverage internal identities and independent non-baggage count. */
    $fare = $service->fare($scenarioFilters);
    $applied = $fare['applied'];
    $matched = (int) $applied['matched_rows'];
    $unmatched = (int) $applied['unmatched_rows'];
    $eligible = (int) $applied['eligible_rows'];
    $unresolved = 0;
    foreach ($applied['unmatched_causes'] as $cause) {
        if (($cause['cause'] ?? '') === 'unresolved_route') {
            $unresolved = (int) $cause['rows'];
        }
    }
    $check($matched + $unmatched === (int) $applied['total_rows'], "Fare matched plus unmatched equals all non-baggage rows for '" . $label . "'");
    $check($eligible === $matched + $unmatched - $unresolved, "Fare eligible equals matched plus route-resolved unmatched rows for '" . $label . "'");
    if ($eligible > 0) {
        $check(
            $close((float) $applied['coverage_pct'], round($matched / $eligible * 100, 1), 0.05),
            "Fare coverage equals matched over eligible for '" . $label . "'"
        );
    }

    /* Previous-window availability must match whether the window has records
       (checked on unfiltered scenarios, where the SQL scope is exact). */
    if ($scenarioFilters['previous_from'] !== '' && $scenarioFilters['previous_to'] !== ''
        && $scenarioFilters['route'] === '' && $scenarioFilters['bus'] === '' && $scenarioFilters['passenger_type'] === '') {
        $previousUntil = $untilExclusive((string) $scenarioFilters['previous_to']);
        $previousParams = [':from' => $scenarioFilters['previous_from'], ':until' => $previousUntil];
        $previousRevenue = (float) $sqlValue(
            "SELECT COALESCE(SUM(p.fare), 0) FROM passenger_trips p WHERE p.boarding_time >= :from AND p.boarding_time < :until AND $paidLive",
            $previousParams
        );
        if ($historyExists) {
            $previousRevenue += (float) $sqlValue(
                "SELECT COALESCE(SUM(h.fare), 0) FROM passenger_trip_history h WHERE $historyDate >= :from AND $historyDate < :until AND $paidHistory",
                $previousParams
            );
        }
        $previousExpenses = (float) $sqlValue(
            'SELECT COALESCE(SUM(amount), 0) FROM financial_expenses WHERE expense_date >= :from AND expense_date < :until',
            $previousParams
        );
        $hasPreviousData = $previousRevenue > 0 || $previousExpenses > 0;
        $check(
            $overview['previous_available'] === $hasPreviousData,
            "Previous-window availability matches SQL for '" . $label . "' (service " . var_export($overview['previous_available'], true) . ' vs data ' . var_export($hasPreviousData, true) . ')'
        );
    }

    $reconciled++;
    echo "  reconciled: $label (" . $from . ' to ' . $to . ") collections=" . $actual['collections']
        . ' passengers=' . $actual['passengers'] . ' routes=' . count($fleet['routes'])
        . ' purchase_cost=' . $fuel['summary']['purchase_cost'] . ' coverage=' . ($applied['coverage_pct'] ?? 'n/a') . "%\n";
}

$check($reconciled >= 6, 'At least six filter scenarios were reconciled against SQL');
echo "$checks live reconciliation checks passed across $reconciled scenarios.\n";
