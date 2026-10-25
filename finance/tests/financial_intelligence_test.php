<?php
declare(strict_types=1);

/**
 * FinancialIntelligenceService checks against an in-memory SQLite fixture that
 * mirrors the live table shapes. Run: php finance/tests/financial_intelligence_test.php
 */
require_once __DIR__ . '/FinancialIntelligenceFixture.php';
require_once __DIR__ . '/../services/FinancialIntelligenceService.php';

use Finance\Services\FinancialIntelligenceService;

date_default_timezone_set('Asia/Manila');

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$checkSame = static function ($actual, $expected, string $message) use ($check): void {
    $check(
        $actual === $expected,
        $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'
    );
};
$close = static fn($actual, $expected, float $delta = 0.011): bool => abs((float) $actual - (float) $expected) <= $delta;

$today = new DateTimeImmutable('2026-10-13');
$resolve = static fn(array $input): array => FinancialIntelligenceService::resolveFilters($input, $today);
$october = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']);
$october['label'] = 'October 2026';

/* ---------------- Filters ---------------- */

$defaultFilters = $resolve([]);
$checkSame($defaultFilters['period'], 'today', 'empty input defaults to Today');
$checkSame($defaultFilters['from'], '2026-10-13', 'default start is current Manila day');
$checkSame($defaultFilters['to'], '2026-10-13', 'default end is current Manila day');
$checkSame($defaultFilters['previous_from'], '2026-10-12', 'default comparison starts yesterday');
$checkSame($defaultFilters['previous_to'], '2026-10-12', 'default comparison ends yesterday');

$todayFilters = $resolve(['period' => 'today']);
$checkSame($todayFilters['from'], '2026-10-13', 'today start');
$checkSame($todayFilters['to'], '2026-10-13', 'today end');
$checkSame($todayFilters['previous_from'], '2026-10-12', 'today previous day');

$yesterday = $resolve(['period' => 'yesterday']);
$checkSame($yesterday['from'], '2026-10-12', 'yesterday start');
$checkSame($yesterday['previous_from'], '2026-10-11', 'yesterday previous');

$weekly = $resolve(['period' => 'weekly']);
$checkSame($weekly['from'], '2026-10-12', 'weekly starts Monday');
$checkSame($weekly['to'], '2026-10-18', 'weekly ends Sunday');
$checkSame($weekly['previous_from'], '2026-10-05', 'weekly previous week');

$monthly = $resolve(['period' => 'monthly']);
$checkSame($monthly['from'], '2026-10-01', 'monthly start');
$checkSame($monthly['to'], '2026-10-31', 'monthly end');
$checkSame($monthly['previous_from'], '2026-09-01', 'monthly previous start');
$checkSame($monthly['previous_to'], '2026-09-30', 'monthly previous end');

$q1 = $resolve(['period' => 'q1', 'year' => '2026']);
$checkSame($q1['from'], '2026-01-01', 'Q1 start');
$checkSame($q1['to'], '2026-03-31', 'Q1 end');
$checkSame($q1['previous_from'], '2025-10-01', 'Q1 previous quarter start');
$checkSame($q1['previous_to'], '2025-12-31', 'Q1 previous quarter end');

$q4 = $resolve(['period' => 'q4', 'year' => '2026']);
$checkSame($q4['from'], '2026-10-01', 'Q4 start');
$checkSame($q4['previous_from'], '2026-07-01', 'Q4 previous quarter start');

$h1 = $resolve(['period' => 'h1', 'year' => '2026']);
$checkSame($h1['from'], '2026-01-01', 'H1 start');
$checkSame($h1['to'], '2026-06-30', 'H1 end');
$checkSame($h1['previous_from'], '2025-07-01', 'H1 previous half');

$h2 = $resolve(['period' => 'h2', 'year' => '2026']);
$checkSame($h2['from'], '2026-07-01', 'H2 start');
$checkSame($h2['previous_to'], '2026-06-30', 'H2 previous half');

$yearly = $resolve(['period' => 'yearly', 'year' => '2026']);
$checkSame($yearly['from'], '2026-01-01', 'full-year start');
$checkSame($yearly['previous_from'], '2025-01-01', 'full-year previous start');
$checkSame($yearly['label'], '2026 vs 2025', 'The yearly label states the compared year pair');

$yoy = $resolve(['period' => 'yoy', 'year' => '2026']);
$checkSame($yoy['period'], 'yearly', 'Legacy year-over-year links resolve to the yearly window');
$checkSame($yoy['from'], '2026-01-01', 'Year-over-year uses the selected year');
$checkSame($yoy['previous_from'], '2025-01-01', 'Year-over-year compares against the year immediately before, not two years back');

$custom = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-15']);
$checkSame($custom['days'], 15, 'custom day count');
$checkSame($custom['previous_from'], '2026-09-16', 'custom previous window');
$checkSame($custom['previous_to'], '2026-09-30', 'custom previous end');

/* ---------------- Overview ---------------- */

$db = FinancialIntelligenceFixture::pdo();
$service = new FinancialIntelligenceService($db);
$overview = $service->overview($october);
$actual = $overview['actual'];

$check($close($actual['collections'], 722.00), 'October collections are 722.00');
$check($close($actual['expenses'], 1450.00), 'October expenses are 1450.00 (250 other + 1200 fuel)');
$check($close($actual['fuel'], 1200.00), 'Fuel expense uses purchases when no fuel ledger exists');
$check($close($actual['other_expenses'], 250.00), 'Other expenses are 250.00');
$check($close($actual['profit'], -728.00), 'Estimated operating profit is -728.00');
$check($close($actual['margin'], -100.83), 'Operating margin is -100.83% (recorded expenses)');
$check($close($actual['configured_costs'], 2668.42), 'Configured monthly costs come from Financial Cost Settings');
$check($close($actual['total_expenses'], 4118.42), 'Total operating expenses include configured costs');
$check($close($actual['profit_after_configured'], -3396.42), 'Profit after configured costs');
$check($close($actual['margin_after_configured'], -470.42), 'Margin after configured costs');
$checkSame($overview['configured_costs']['drivers'], 2, 'Configured costs are applied per recorded driver');
$check($close($overview['configured_costs']['monthly_per_driver'], 1310.00), 'Configured monthly total per driver');
$check($close($overview['configured_costs']['months'], 1.02), 'Configured months prorated for a 31-day period');
$checkSame(count($overview['configured_costs']['items']), 4, 'Gas is excluded from configured costs to avoid double counting');
$configuredSlices = array_values(array_filter($overview['expense_breakdown'], static fn(array $row): bool => !empty($row['configured'])));
$checkSame(count($configuredSlices), 4, 'Expense breakdown includes configured items');
$amortizationSlice = null;
foreach ($configuredSlices as $slice) {
    if (str_contains((string) $slice['category'], 'Amortization')) {
        $amortizationSlice = $slice;
    }
}
$check($amortizationSlice !== null && $close($amortizationSlice['amount'], 2036.96), 'Amortization scaled per driver and period');
$checkSame($actual['passengers'], 7, 'Baggage is not counted as a passenger');
$check($close($actual['baggage_collections'], 50.00), 'Baggage collections are tracked separately');
$check($close($actual['avg_fare'], 96.00), 'Average realized fare excludes baggage');
$checkSame($actual['trips'], 4, 'Unique completed trip references in October');
$checkSame($overview['expense_completeness']['estimated'], false, 'Complete records are not flagged as estimates');

$check($close($overview['previous']['collections'], 100.00), 'Previous window collections (September)');
$check($close($overview['change']['collections_delta'], 622.00), 'Collection delta is 622.00');
$check($close($overview['change']['profit_delta'], -828.00), 'Profit delta is -828.00');
$check($overview['change']['expenses_pct'] === null, 'Zero-baseline expense growth is null, not infinite');

$waterfall = $overview['waterfall'];
$checkSame(count($waterfall), 6, 'Waterfall has six steps including configured costs');
$check($close($waterfall[0]['value'], -1234.21), 'Waterfall starts at previous profit after configured costs');
$check($close($waterfall[1]['value'], 622.00), 'Waterfall collection movement');
$check($close($waterfall[2]['value'], -1200.00), 'Waterfall fuel movement is negative when fuel rises');
$check($close($waterfall[3]['value'], -250.00), 'Waterfall other-expense movement');
$check($close($waterfall[4]['value'], -1334.21), 'Waterfall configured-cost movement');
$check($close($waterfall[5]['value'], -3396.42), 'Waterfall ends at current profit after configured costs');
$check($close($waterfall[5]['cumulative'], -3396.42), 'Waterfall cumulative reconciles to profit after configured costs');

$quarterly = $overview['quarterly'];
$byQuarter = [];
foreach ($quarterly as $quarter) {
    $byQuarter[$quarter['quarter']] = $quarter;
}
$check(isset($byQuarter['2026-Q2'], $byQuarter['2026-Q3'], $byQuarter['2026-Q4']), 'Quarterly comparison covers Q2-Q4 2026');
$check($close($byQuarter['2026-Q2']['collections'], 85.00), 'Q2 collections');
$check($close($byQuarter['2026-Q3']['collections'], 190.00), 'Q3 collections');
$check($close($byQuarter['2026-Q4']['collections'], 722.00), 'Q4 collections');
$check($close($byQuarter['2026-Q4']['profit'], -728.00), 'Q4 estimated profit');

$findings = implode(' ', $overview['findings']);
$check(str_contains($findings, 'increased by ₱622.00'), 'Findings state the collection change with an amount');
$check(str_contains($findings, 'Operating margin after configured costs is'), 'Findings report the margin after configured costs');
$check(str_contains($findings, 'Configured monthly costs add'), 'Findings disclose configured costs as estimates');
$check(str_contains($findings, 'Lowest estimated margin among routes with cost records'), 'Findings rank routes only where costs exist');

/* ---------------- Passenger types ---------------- */

$passengers = $service->passengers($october);
$types = [];
foreach ($passengers['types'] as $type) {
    $types[$type['type']] = $type;
}
$checkSame($types['Regular']['trips'], 3, 'Regular trips');
$check($close($types['Regular']['collections'], 320.00), 'Regular collections');
$check($close($types['Regular']['avg_fare'], 106.67), 'Regular average fare');
$checkSame($types['Student']['trips'], 2, 'Student trips');
$check($close($types['Student']['avg_fare'], 80.00), 'Student average realized fare');
$checkSame($types['Senior Citizen']['trips'], 1, 'Senior trips');
$checkSame($types['PWD']['trips'], 1, 'PWD trips');
$check(!isset($types['Baggage']), 'Baggage is not a passenger type');
$checkSame($passengers['baggage']['trips'], 1, 'Baggage rows are summarized separately');
$check($close($passengers['baggage']['collections'], 50.00), 'Baggage collections');
$check($close($passengers['discount_summary']['total_estimated_discount'], 74.68), 'Estimated discount impact versus Regular average');
$checkSame($passengers['monthly'][0]['month'], '2026-10', 'Monthly passenger trend bucket');
$check(count($passengers['by_route']) === 2, 'Passenger distribution covers both routes');
$routeDistribution = [];
foreach ($passengers['by_route'] as $row) {
    $routeDistribution[$row['route']] = $row;
}
$checkSame($routeDistribution['Cabanatuan - Rizal']['total_trips'], 4, 'Route A passenger trips by category');
$checkSame($routeDistribution['Cabanatuan - Kalikid']['total_trips'], 3, 'Route B passenger trips by category');

/* ---------------- Live rows without a recorded type use the account type ---------------- */

$typeColumnDb = FinancialIntelligenceFixture::pdo();
/* Real app boardings can carry the column with an empty value; the account's
   discount type must then decide the analytics category instead of Regular. */
$typeColumnDb->exec("INSERT INTO passengers (id, discount_type) VALUES (3, 'Cooperative')");
$typeColumnDb->exec("INSERT INTO passenger_trips (id, passenger_id, bus_id, driver_id, trip_no, fare, payment_status, boarding_time, payment_method, archived_status) VALUES (6, 3, 'B1', 'D1', '101', 90.00, 'paid', '2026-10-05 11:00:00', 'cash', 'no')");
$typeColumnService = new FinancialIntelligenceService($typeColumnDb);
$typeColumnPassengers = $typeColumnService->passengers($october);
$typedTypes = [];
foreach ($typeColumnPassengers['types'] as $type) {
    $typedTypes[$type['type']] = $type;
}
$check(isset($typedTypes['Student']), 'An empty trip type falls back to the account discount type');
$checkSame($typedTypes['Student']['trips'], 2, 'The account-typed live fare joins the Student category');
$check(isset($typedTypes['Cooperative']) && $typedTypes['Cooperative']['trips'] === 1, 'Cooperative accounts are reported as their own category');
$check(isset($typedTypes['Regular']) && $typedTypes['Regular']['trips'] === 3, 'Regular live fares remain Regular');
$check(!isset($typedTypes['Other']), 'Known account types are not lumped into Other');

/* ---------------- Fare matrix ---------------- */

$fare = $service->fare($october);
$checkSame(count($fare['segments']), 3, 'Fare matrix segments');
$checkSame($fare['segment_count'], 3, 'Fare matrix segment count');
$checkSame($fare['last_updated'], '2026-10-03 08:00:00', 'Fare matrix last update timestamp');
$check($close($fare['applied']['expected_collections'], 692.00), 'Expected collections at the current matrix');
$check($close($fare['applied']['actual_collections'], 672.00), 'Actual passenger collections matched to the matrix');
$check($close($fare['applied']['variance'], 20.00), 'Matrix alignment variance');
$checkSame($fare['applied']['coverage_pct'], 100.0, 'Matrix match coverage');
$fareRoutes = [];
foreach ($fare['applied']['by_route'] as $row) {
    $fareRoutes[$row['route']] = $row;
}
$check($close($fareRoutes['Cabanatuan - Rizal']['variance'], 72.00), 'Route A realized fares are below the matrix');
$check($close($fareRoutes['Cabanatuan - Kalikid']['variance'], -52.00), 'Route B realized fares are above the matrix');
$checkSame($fare['change_tracking']['observable'], true, 'Fare change tracking is observable inside October');
$check($close($fare['change_tracking']['before']['collections'], 80.00), 'Collections before the fare update');
$check($close($fare['change_tracking']['after']['collections'], 642.00), 'Collections after the fare update');
$check($close($fare['change_tracking']['avg_fare_change'], 18.67), 'Realized average fare change around the update');

/* ---------------- Fuel ---------------- */

$fuel = $service->fuel($october);
$check($close($fuel['summary']['liters'], 80.00), 'Purchased liters');
$check($close($fuel['summary']['purchase_cost'], 1200.00), 'Purchase cost');
$check($close($fuel['summary']['avg_price'], 15.00), 'Average price per liter');
$check($close($fuel['summary']['cost_per_km'], 3.43), 'Fuel cost per kilometer');
$check($close($fuel['summary']['cost_per_trip'], 400.00), 'Fuel cost per measured trip');
$check($close($fuel['summary']['avg_kpl'], 5.00), 'Average KPL from operational rows');
$checkSame($fuel['summary']['purchases'], 3, 'Refuel plus legacy purchase rows');
$fuelBuses = [];
foreach ($fuel['by_bus'] as $row) {
    $fuelBuses[$row['bus']] = $row;
}
$check($close($fuelBuses['B1']['cost'], 750.00), 'Bus B1 fuel cost includes the legacy purchase');
$check($close($fuelBuses['B2']['cost'], 450.00), 'Bus B2 fuel cost');
$check(count($fuel['by_route']['routes']) === 2, 'Fuel attributed to both routes when trip references resolve');
$check($close($fuel['by_route']['unattributed']['cost'], 0.00), 'No unattributed fuel in the fixture');
$check(count($fuel['prices']) === 2, 'Historical diesel prices are listed');
$check(str_contains($fuel['double_counting']['policy'], 'never adds them to revenue'), 'Fuel double-counting policy is documented');

$fuelPriceDb = FinancialIntelligenceFixture::pdo();
$fuelPriceDb->exec("INSERT INTO fuel_prices (id, fuel_type, price_per_liter, updated_at) VALUES (3, 'Gasoline', 70.20, '2026-10-10 00:00:00')");
$fuelPriceService = new FinancialIntelligenceService($fuelPriceDb);
$fuelPriceFuel = $fuelPriceService->fuel($october);
$checkSame($fuelPriceFuel['latest_price']['fuel_type'], 'Diesel', 'Latest fuel price stays the Diesel record when a Gasoline record has a higher id');
$check($close($fuelPriceFuel['latest_price']['price_per_liter'], 15.00), 'Latest price is the applied Diesel record');
$checkSame(count($fuelPriceFuel['prices']), 2, 'Historical diesel prices exclude other fuel types');
foreach ($fuelPriceFuel['prices'] as $priceRow) {
    $checkSame($priceRow['fuel_type'], 'Diesel', 'Only Diesel records are listed as diesel prices');
}
$noPurchaseFuel = $fuelPriceService->fuel($resolve(['period' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31']));
$check($close($noPurchaseFuel['summary']['avg_price'], 15.00), 'Average price falls back to the Diesel record, not other fuel types');
$check($close($noPurchaseFuel['summary']['purchase_cost'], 0.00), 'A purchase-free scope has no purchase cost');
$checkSame($fuel['data_window']['from'], '2026-10-05', 'Fuel data window start');
$checkSame($fuel['data_window']['to'], '2026-10-07', 'Fuel data window end');
$checkSame($fuel['data_window']['records'], 5, 'Fuel data window record count');

/* ---------------- Fleet ---------------- */

$fleet = $service->fleet($october);
$fleetRoutes = [];
foreach ($fleet['routes'] as $row) {
    $fleetRoutes[$row['route']] = $row;
}
$check($close($fleetRoutes['Cabanatuan - Rizal']['collections'], 360.00), 'Route A collections');
$check($close($fleetRoutes['Cabanatuan - Rizal']['attributable_expenses'], 950.00), 'Route A attributable costs');
$check($close($fleetRoutes['Cabanatuan - Rizal']['estimated_profit'], -590.00), 'Route A estimated profit');
$checkSame($fleetRoutes['Cabanatuan - Rizal']['cost_complete'], true, 'Route A cost records are complete');
$check($close($fleetRoutes['Cabanatuan - Rizal']['maintenance_cost'], 200.00), 'Route A maintenance cost');
$check($close($fleetRoutes['Cabanatuan - Kalikid']['collections'], 362.00), 'Route B collections');
$check($close($fleetRoutes['Cabanatuan - Kalikid']['estimated_margin'], -24.31), 'Route B estimated margin');
$fleetBuses = [];
foreach ($fleet['buses'] as $row) {
    $fleetBuses[$row['bus']] = $row;
}
$check($close($fleetBuses['B1']['attributable_expenses'], 1000.00), 'Bus B1 costs include tolls and maintenance');
$check($close($fleetBuses['B2']['collections'], 362.00), 'Bus B2 collections');
$checkSame($fleet['unattributed']['trips'], 0, 'Every trip resolved to a route in the fixture');

/* ---------------- Missing cost data is not called unprofitable ---------------- */

$partialDb = FinancialIntelligenceFixture::pdo();
$partialDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (108, 'B2', 'D2', 'Cabanatuan - Gapan', '2026-10-09', 'Completed', 0)");
$partialDb->exec("INSERT INTO passenger_trip_history (history_id, passenger_name, passenger_type, type, fare, trip_date, date_booked, bus_id, driver_id, trip_id, payment_method, boarding_point, destination) VALUES (9, 'P9', 'Regular', 'passenger', 70.00, '2026-10-09', '2026-10-09 07:00:00', 'B2', 'D2', '108', 'cash', 'Cabanatuan', 'Gapan')");
$partialService = new FinancialIntelligenceService($partialDb);
$partialOverview = $partialService->overview($october);
$partialFleet = $partialService->fleet($october);
$partialRoutes = [];
foreach ($partialFleet['routes'] as $row) {
    $partialRoutes[$row['route']] = $row;
}
$check($close($partialRoutes['Cabanatuan - Gapan']['collections'], 70.00), 'Route with revenue but no cost records is listed');
$checkSame($partialRoutes['Cabanatuan - Gapan']['cost_complete'], false, 'Route without cost records is marked incomplete');
$check($partialRoutes['Cabanatuan - Gapan']['estimated_margin'] === null, 'Route without cost records has no asserted margin');
$check($partialRoutes['Cabanatuan - Gapan']['estimated_profit'] === null, 'Route without cost records has no asserted profit');
$check(str_contains(implode(' ', $partialOverview['findings']), 'incomplete cost records'), 'Findings explicitly flag incomplete route costs');

/* ---------------- Excluded route labels never form route rows ---------------- */

$excludedDb = FinancialIntelligenceFixture::pdo();
$excludedRoute = 'Cabanatuan Central Terminal - Rizal / Poblacion Terminal';
$excludedReversed = 'Rizal / Poblacion Terminal - Cabanatuan Central Terminal';
$excludedDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (109, 'B2', 'D2', '" . $excludedRoute . "', '2026-10-08', 'Completed', 0)");
$excludedDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (111, 'B2', 'D2', '" . $excludedReversed . "', '2026-10-09', 'Completed', 0)");
$excludedDb->exec("INSERT INTO passenger_trip_history (history_id, passenger_name, passenger_type, type, fare, trip_date, date_booked, bus_id, driver_id, trip_id, payment_method, boarding_point, destination) VALUES (10, 'P10', 'Regular', 'passenger', 40.00, '2026-10-08', '2026-10-08 07:00:00', 'B2', 'D2', '109', 'cash', 'Cabanatuan', 'Poblacion')");
$excludedDb->exec("INSERT INTO passenger_trip_history (history_id, passenger_name, passenger_type, type, fare, trip_date, date_booked, bus_id, driver_id, trip_id, payment_method, boarding_point, destination) VALUES (11, 'P11', 'Regular', 'passenger', 45.00, '2026-10-09', '2026-10-09 07:00:00', 'B2', 'D2', '111', 'cash', 'Poblacion', 'Cabanatuan')");
$excludedService = new FinancialIntelligenceService($excludedDb);
$excludedOptions = $excludedService->options();
$check(!in_array($excludedRoute, $excludedOptions['routes'], true), 'Excluded route label is not offered as a filter');
$check(!in_array($excludedRoute, $excludedOptions['fare_routes'], true), 'Excluded route label is not offered in fare routes');
$check(!in_array($excludedReversed, $excludedOptions['routes'], true), 'Reversed excluded route label is not offered either');
$excludedFleet = $excludedService->fleet($october);
$excludedFleetRoutes = array_column($excludedFleet['routes'], 'route');
$check(!in_array($excludedRoute, $excludedFleetRoutes, true), 'Excluded route label never forms a route row');
$check(!in_array('Cabanatuan Central Terminal - Rizal / Poblacion Terminal', $excludedFleetRoutes, true), 'Excluded route label is not reintroduced by canonicalization');
$check($close($excludedFleet['unattributed']['collections'], 85.00), 'Excluded-route collections are reported as unattributed');
$excludedOverview = $excludedService->overview($october);
$check($close($excludedOverview['actual']['collections'], 807.00), 'Excluded-route fares remain in the period totals');
$check(!in_array($excludedRoute, array_column($excludedOverview['by_route'], 'route'), true), 'Excluded route label is absent from the overview route table');

/* ---------------- Route directions stay distinct and consistent ---------------- */

$variantDb = FinancialIntelligenceFixture::pdo();
$variantDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (110, 'B1', 'D1', 'Rizal-Cabanatuan', '2026-10-09', 'Completed', 0)");
$variantDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (112, 'B2', 'D2', 'rizal - cabanatuan', '2026-10-10', 'Completed', 0)");
$variantDb->exec("INSERT INTO passenger_trip_history (history_id, passenger_name, passenger_type, type, fare, trip_date, date_booked, bus_id, driver_id, trip_id, payment_method, boarding_point, destination) VALUES (12, 'P12', 'Regular', 'passenger', 55.00, '2026-10-09', '2026-10-09 07:00:00', 'B1', 'D1', '110', 'cash', 'Rizal', 'Cabanatuan')");
$variantDb->exec("INSERT INTO passenger_trip_history (history_id, passenger_name, passenger_type, type, fare, trip_date, date_booked, bus_id, driver_id, trip_id, payment_method, boarding_point, destination) VALUES (13, 'P13', 'Regular', 'passenger', 20.00, '2026-10-10', '2026-10-10 07:00:00', 'B2', 'D2', '112', 'cash', 'Rizal', 'Cabanatuan')");
$variantService = new FinancialIntelligenceService($variantDb);
$variantFleet = $variantService->fleet($october);
$variantRoutes = array_column($variantFleet['routes'], null, 'route');
$check(isset($variantRoutes['Cabanatuan - Rizal']), 'Recorded direction keeps its own route label');
$check(isset($variantRoutes['Rizal - Cabanatuan']), 'Opposite direction stays a separate route');
$check($close($variantRoutes['Cabanatuan - Rizal']['collections'], 360.00), 'Forward direction keeps its own collections');
$check($close($variantRoutes['Rizal - Cabanatuan']['collections'], 75.00), 'Spacing and case variants of a direction merge into it');
$check(count($variantRoutes) === 3, 'Only the recorded routes appear: two directions plus the second route');
$variantOptions = $variantService->options();
$check(in_array('Cabanatuan - Rizal', $variantOptions['routes'], true), 'Filter list offers the forward direction');
$check(in_array('Rizal - Cabanatuan', $variantOptions['routes'], true), 'Filter list offers the reverse direction');
$check(!in_array('rizal - cabanatuan', $variantOptions['routes'], true), 'Filter list normalizes spacing and case variants');
$variantFilter = $october;
$variantFilter['route'] = 'Cabanatuan-Rizal';
$variantOverview = $variantService->overview($variantFilter);
$check($close($variantOverview['actual']['collections'], 360.00), 'Filtering one direction selects only that direction');
$variantReverseFilter = $october;
$variantReverseFilter['route'] = 'Rizal - Cabanatuan';
$variantReverseOverview = $variantService->overview($variantReverseFilter);
$check($close($variantReverseOverview['actual']['collections'], 75.00), 'Filtering the reverse direction selects only its trips');
$variantFare = $variantService->fare($october);
$check(in_array('Cabanatuan - Rizal', $variantFare['routes'], true), 'Fare matrix lists the recorded direction');
$check(($variantFare['applied']['matched_rows'] ?? 0) > 0, 'Fare matrix aligns trips recorded in either direction to the route');

/* ---------------- Remittance & discrepancies ---------------- */

$remittance = $service->remittance($october);
$summary = $remittance['summary'];
$check($close($summary['expected_cash'], 330.00), 'Expected cash across remittances');
$check($close($summary['actual_cash'], 320.00), 'Actual remitted cash');
$check($close($summary['shortage'], 30.00), 'Shortages exclude overages');
$check($close($summary['overage'], 20.00), 'Overages are reported separately');
$check($close($summary['remittance_rate'], 96.97), 'Remittance rate');
$checkSame($summary['unresolved_cases'], 1, 'Only open shortage cases count as unresolved');
$check($close($summary['unresolved_amount'], 20.00), 'Unresolved amount nets recoveries, not confirmed losses');
$check($close($summary['recovered_amount'], 10.00), 'Posted cash recoveries');
$check($close($summary['outstanding_amount'], 20.00), 'Outstanding exposure after recovery');
$check(count($remittance['monthly']) === 1 && $remittance['monthly'][0]['month'] === '2026-10', 'Monthly remittance trend');
$check(str_contains(implode(' ', $remittance['notes']), 'never added to fare revenue'), 'Remittances are not counted as revenue');

/* ---------------- Forecast composition ---------------- */

$predictive = [
    'summary' => [
        'passengers' => ['forecast_total' => 70, 'range_low' => 50, 'range_high' => 90, 'period_label' => 'Next 7 days'],
    ],
    'fuel' => ['available' => true, 'estimated_liters' => 100.0, 'avg_price' => 15.0],
    'model' => ['history_from' => '2026-08-01', 'history_to' => '2026-10-12', 'reliability' => ['label' => 'Good']],
];
$forecast = $service->composeForecast($october, '7d', $predictive);
$check($close($forecast['collections']['forecast_total'], 6720.00), 'Collections forecast uses actual average fare');
$check($close($forecast['collections']['range_low'], 4800.00), 'Low collection forecast from passenger range');
$check($close($forecast['collections']['range_high'], 8640.00), 'High collection forecast from passenger range');
$check($close($forecast['fuel']['cost_estimate'], 1500.00), 'Fuel forecast uses predicted liters and price');
$check($close($forecast['other_expenses']['forecast_total'], 56.45), 'Other expenses forecast uses the daily run-rate');
$check($close($forecast['profit']['estimate'], 5163.55), 'Profit forecast subtracts fuel and other expenses');
$check(str_contains(implode(' ', $forecast['assumptions']), 'average realized fare'), 'Forecast assumptions are stated');
$check(str_contains($forecast['disclaimer'], 'not actual results'), 'Forecasts are labelled as projections');

/* ---------------- What-if ---------------- */

$whatIf = $service->whatIf($october, [
    'fare_change_pct' => 10,
    'volume_change_pct' => 5,
    'fuel_price_change_pct' => 20,
    'other_expense_change_pct' => -10,
]);
$check($close($whatIf['projected']['collections'], 833.91), 'What-if fare and volume changes scale collections');
$check($close($whatIf['projected']['fuel'], 1440.00), 'What-if fuel price change');
$check($close($whatIf['projected']['other_expenses'], 225.00), 'What-if other expense change');
$check($close($whatIf['baseline']['configured_costs'], 2668.42), 'What-if baseline includes configured costs');
$check($close($whatIf['projected']['configured_costs'], 2668.42), 'Configured costs stay fixed in the scenario');
$check($close($whatIf['projected']['profit'], -3499.51), 'What-if projected profit after configured costs');
$check($close($whatIf['difference']['profit'], -103.09), 'What-if profit difference');
$check(str_contains($whatIf['disclaimer'], 'not actual or forecast'), 'What-if is labelled a projection');

$routeScenario = $service->whatIf($october, ['route_fare_changes' => ['Cabanatuan - Rizal' => 20]]);
$byRoute = [];
foreach ($routeScenario['by_route'] as $row) {
    $byRoute[$row['route']] = $row;
}
$check($close($byRoute['Cabanatuan - Rizal']['projected_collections'], 432.00), 'Route-specific fare change is applied');
$check($close($byRoute['Cabanatuan - Rizal']['difference'], 72.00), 'Route-specific difference');

$fareCount = (int) $db->query('SELECT COUNT(*) FROM fare_pricing')->fetchColumn();
$checkSame($fareCount, 3, 'What-if does not modify the live fare matrix');

/* ---------------- Route and bus filters apply to attributable costs ---------------- */

$routeFilters = $october;
$routeFilters['route'] = 'Cabanatuan - Rizal';
$routeOverview = $service->overview($routeFilters);
$check($close($routeOverview['actual']['collections'], 360.00), 'Route filter narrows collections');
$check($close($routeOverview['actual']['fuel'], 750.00), 'Route filter narrows attributable fuel');
$check($close($routeOverview['actual']['expenses'], 950.00), 'Route filter narrows attributable costs');
$check($close($routeOverview['actual']['profit'], -590.00), 'Route-filtered profit matches the fleet route estimate');

$busFilters = $october;
$busFilters['bus'] = 'B1';
$busOverview = $service->overview($busFilters);
$check($close($busOverview['actual']['collections'], 360.00), 'Bus filter narrows collections');
$check($close($busOverview['actual']['expenses'], 1000.00), 'Bus filter narrows attributable costs including tolls');

$typeFilters = $october;
$typeFilters['passenger_type'] = 'Student';
$typeOverview = $service->overview($typeFilters);
$check($close($typeOverview['actual']['collections'], 160.00), 'Passenger type filter narrows collections');
$check($close($typeOverview['actual']['expenses'], 1450.00), 'Operating expenses stay in full under a passenger-type filter');
$check(str_contains(implode(' ', $typeOverview['notes']), 'not passenger-specific'), 'Passenger-type filter explains expense treatment');

/* ---------------- Fuel expense ledger precedence ---------------- */

$ledgerDb = FinancialIntelligenceFixture::pdo();
$ledgerDb->exec("INSERT INTO expenses (id, bus_id, trip_id, category, amount, created_at) VALUES (9, 'B1', NULL, 'fuel', 500.00, '2026-10-08 09:00:00')");
$ledgerService = new FinancialIntelligenceService($ledgerDb);
$ledgerOverview = $ledgerService->overview($october);
$check($close($ledgerOverview['actual']['fuel'], 500.00), 'Posted fuel ledger wins over receipt purchases');
$check($close($ledgerOverview['actual']['expenses'], 750.00), 'Receipts are not double-counted when a fuel ledger exists');
$check($close($ledgerOverview['actual']['profit'], -28.00), 'Profit uses the ledger-based expense total');
$check($close($ledgerOverview['actual']['other_expenses'], 250.00), 'Other expenses unchanged with fuel ledger');
$check(implode(' ', $ledgerOverview['notes']) !== '', 'Service records calculation notes');

/* ---------------- Configured costs: defaults and double-count guard ---------------- */

$defaultDb = FinancialIntelligenceFixture::pdo();
$defaultDb->exec('DROP TABLE financial_cost_settings');
$defaultService = new FinancialIntelligenceService($defaultDb);
$defaultOverview = $defaultService->overview($october);
$check($close($defaultOverview['configured_costs']['monthly_per_driver'], 1797.14), 'Shipped defaults are used when no settings rows exist');
$check($close($defaultOverview['configured_costs']['total'], 3660.70), 'Default configured cost total for October');
$check($close($defaultOverview['actual']['profit_after_configured'], -4388.70), 'Profit after default configured costs');

$postedDb = FinancialIntelligenceFixture::pdo();
$postedDb->exec("INSERT INTO expenses (id, bus_id, trip_id, category, amount, created_at) VALUES (10, NULL, NULL, 'Insurance', 50.00, '2026-10-09 09:00:00')");
$postedService = new FinancialIntelligenceService($postedDb);
$postedOverview = $postedService->overview($october);
$check($close($postedOverview['actual']['configured_costs'], 2464.72), 'Configured costs already posted in the ledger are not added again');
$check(in_array('Insurance', $postedOverview['configured_costs']['skipped'], true), 'Skipped configured item is reported');
$check(str_contains(implode(' ', $postedOverview['notes']) . $postedOverview['configured_costs']['note'], 'Insurance'), 'Skipped configured item is explained to the reader');

/* ---------------- Missing-data honesty ---------------- */

$emptyDb = new PDO('sqlite::memory:');
$emptyDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$emptyService = new FinancialIntelligenceService($emptyDb);
$emptyOverview = $emptyService->overview($october);
$check($close($emptyOverview['actual']['collections'], 0.00), 'Empty database yields zero collections, not fabricated values');
$check($emptyOverview['actual']['margin'] === null, 'No collection means no margin');
$checkSame($emptyOverview['expense_completeness']['estimated'], true, 'Missing expense records flag the result as an estimate');
$emptyFuel = $emptyService->fuel($october);
$check($emptyFuel['summary']['cost_per_km'] === null, 'Fuel cost per kilometer is null without data');
$emptyFleet = $emptyService->fleet($october);
$checkSame($emptyFleet['routes'], [], 'No routes are invented for an empty database');

/* ---------------- Filter normalization and year handling ---------------- */

$aliasSenior = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'passenger_type' => 'senior']);
$checkSame($aliasSenior['passenger_type'], 'Senior Citizen', 'Passenger-type aliases resolve to canonical stored values');
$aliasPwd = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'passenger_type' => 'pwd']);
$checkSame($aliasPwd['passenger_type'], 'PWD', 'PWD alias resolves to its canonical stored value');
$aliasBaggage = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'passenger_type' => 'baggage']);
$checkSame($aliasBaggage['passenger_type'], 'Baggage', 'Baggage remains a supported passenger filter value');
$unknownType = $resolve(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'passenger_type' => 'vip']);
$checkSame($unknownType['passenger_type'], 'Other', 'Unknown passenger values normalize instead of silently dropping every row');
$seniorOverview = $service->overview($aliasSenior);
$check($close($seniorOverview['actual']['collections'], 96.00), 'Normalized senior alias selects the senior rows');
$pwdOverview = $service->overview($aliasPwd);
$check($close($pwdOverview['actual']['collections'], 96.00), 'Normalized PWD alias selects the PWD rows');

$monthlyYearInput = $resolve(['period' => 'monthly', 'year' => '2025']);
$checkSame($monthlyYearInput['from'], '2026-10-01', 'Monthly window is date-governed and ignores a stale year input');
$checkSame($monthlyYearInput['year'], 2026, 'Date-window periods report the window year, not the ignored input');
$q1Year = $resolve(['period' => 'q1', 'year' => '2025']);
$checkSame($q1Year['from'], '2025-01-01', 'Quarter windows honor the selected year');
$q2Year = $resolve(['period' => 'q2', 'year' => '2026']);
$checkSame($q2Year['from'], '2026-04-01', 'Q2 resolves to its own window');
$q3Year = $resolve(['period' => 'q3', 'year' => '2026']);
$checkSame($q3Year['from'], '2026-07-01', 'Q3 resolves to its own window');
$invalidQuarter = $resolve(['period' => 'quarter', 'quarter' => '9', 'year' => '2026']);
$checkSame($invalidQuarter['quarter'], 4, 'Invalid quarter values are clamped to the current quarter');
$checkSame($invalidQuarter['from'], '2026-10-01', 'Clamped quarter resolves to the current quarter window');

/* ---------------- Comparison validity and equivalence ---------------- */

$checkSame($overview['previous_available'], true, 'A previous window with records is flagged available');
$checkSame($overview['previous_period']['from'], '2026-08-31', 'Previous window start is disclosed');
$checkSame($overview['previous_period']['to'], '2026-09-30', 'Previous window end is disclosed');
$check(str_contains((string) $overview['comparison_label'], '2026-08-31'), 'Comparison label discloses the previous range dates');

$noPreviousRange = $resolve(['period' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-05']);
$noPreviousOverview = $service->overview($noPreviousRange);
$checkSame($noPreviousOverview['previous_available'], false, 'A previous window without records is flagged unavailable');
$checkSame($noPreviousOverview['change'], null, 'Changes are not computed without a valid previous window');
$checkSame($noPreviousOverview['previous'], null, 'Previous totals are omitted when the window has no records');
$checkSame($noPreviousOverview['waterfall'], [], 'The profit bridge is empty without a valid previous window');

/* ---------------- Payment methods, expense summary, and scope metadata ---------------- */

$overviewPayments = $overview['payment_methods'];
$paymentSum = 0.0;
foreach ($overviewPayments as $payment) {
    $paymentSum += (float) $payment['collections'];
}
$check($close($paymentSum, 722.00), 'Payment method collections sum to fare collections');
$check(count($overviewPayments) === 2, 'Only methods with recorded collections are listed');
$check($close($overview['expense_summary']['recorded'], 1450.00), 'Expense summary labels the recorded side');
$check($close($overview['expense_summary']['configured_estimate'], 2668.42), 'Expense summary labels the configured estimate side');
$check($close($overview['expense_summary']['total'], 4118.42), 'Expense summary totals recorded plus configured');

$typeScopeMessages = array_column($typeOverview['scope']['not_applicable'], 'message');
$check($typeScopeMessages !== [], 'Scope metadata lists filters that cannot apply');
$check(str_contains(implode(' ', $typeScopeMessages), 'not passenger-specific'), 'Scope metadata explains the passenger-type limitation');
$forecastScoped = $service->forecast($aliasSenior, ['horizon' => '7d']);
$check(array_key_exists('scope', $forecastScoped), 'Forecast payload carries scope metadata');
$check(
    str_contains(implode(' ', $forecastScoped['notes']), 'passenger-type') || $forecastScoped['available'] === false,
    'Forecast explains the passenger-type scope or reports unavailability'
);

/* ---------------- Fuel route attribution and operating reconciliation ---------------- */

$fuelRouteFilters = $october;
$fuelRouteFilters['route'] = 'Cabanatuan - Rizal';
$routeFuel = $service->fuel($fuelRouteFilters);
$check($close($routeFuel['summary']['purchase_cost'], 750.00), 'Fuel section honors the route filter for attributed purchases');
$check(count($routeFuel['by_route']['routes']) === 1, 'Fuel by route lists only the selected route');
$check($close($routeFuel['operating']['fuel_cost'], 750.00), 'Operating fuel cost reconciles with the route-filtered overview');
$checkSame($routeFuel['operating']['source'], 'purchase_receipts', 'Receipt basis is identified when no fuel ledger exists');
$check(str_contains(implode(' ', $routeFuel['notes']), 'route filter'), 'Excluded fuel purchases are reported under the route filter');

$check($close($fuel['operating']['fuel_cost'], 1200.00), 'Unfiltered operating fuel equals recorded expenses when receipts are the source');

/* ---------------- Remittance filters ---------------- */

$remitBusFilters = $october;
$remitBusFilters['bus'] = 'B1';
$remitBusData = $service->remittance($remitBusFilters);
$check($close($remitBusData['summary']['expected_cash'], 230.00), 'Remittance bus filter narrows expected cash');
$check($close($remitBusData['summary']['actual_cash'], 200.00), 'Remittance bus filter narrows actual cash');
$checkSame($remitBusData['summary']['remittance_count'], 2, 'Remittance bus filter narrows the record count');
$checkSame($remitBusData['summary']['unresolved_cases'], 1, 'Discrepancy cases follow the bus filter');
$check(!in_array('DIS-00002', array_column($remitBusData['discrepancies']['cases'], 'case_ref'), true), 'Other-bus discrepancy cases are excluded under the bus filter');

$remitRouteFilters = $october;
$remitRouteFilters['route'] = 'Cabanatuan - Rizal';
$remitRouteData = $service->remittance($remitRouteFilters);
$check($close($remitRouteData['summary']['expected_cash'], 180.00), 'Remittance route filter narrows expected cash via driver/day attribution');
$check($close($remitRouteData['summary']['actual_cash'], 150.00), 'Remittance route filter narrows actual cash');
$check(str_contains(implode(' ', $remitRouteData['notes']), 'route filter'), 'Unattributable remittances are reported under the route filter');
$remitScope = $service->remittance($october)['scope'];
$check(array_key_exists('not_applicable', $remitScope), 'Remittance payload carries scope metadata');

/* ---------------- Expense de-duplication across ledger tables ---------------- */

$dupDb = FinancialIntelligenceFixture::pdo();
$dupDb->exec("INSERT INTO financial_expenses (id, category, amount, expense_date, bus_id, description) VALUES (50, 'maintenance', 200.00, '2026-10-05', 'B1', 'Brake pads')");
$dupService = new FinancialIntelligenceService($dupDb);
$dupOverview = $dupService->overview($october);
$check($close($dupOverview['actual']['expenses'], 1450.00), 'Mirrored expense rows in both tables are counted once');
$check(str_contains(implode(' ', $dupOverview['notes']), 'counted once'), 'Duplicate expense rows are disclosed');

$dupSameTableDb = FinancialIntelligenceFixture::pdo();
$dupSameTableDb->exec("INSERT INTO expenses (id, bus_id, trip_id, category, amount, description, created_at) VALUES (55, 'B1', NULL, 'maintenance', 200.00, 'Brake pads', '2026-10-05 13:00:00')");
$dupSameTableService = new FinancialIntelligenceService($dupSameTableDb);
$dupSameTableOverview = $dupSameTableService->overview($october);
$check($close($dupSameTableOverview['actual']['expenses'], 1650.00), 'Legitimate same-table duplicate entries are both counted');

/* ---------------- Configured costs: distinct trips and passenger-type basis ---------------- */

$tripCostDb = FinancialIntelligenceFixture::pdo();
$tripCostDb->exec("UPDATE financial_cost_settings SET calc_method = 'per_trip', amount = 5.00 WHERE cost_key = 'optr'");
$tripCostService = new FinancialIntelligenceService($tripCostDb);
$tripCostOverview = $tripCostService->overview($october);
$optrItem = null;
foreach ($tripCostOverview['configured_costs']['items'] as $item) {
    if ($item['key'] === 'optr') {
        $optrItem = $item;
    }
}
$check($optrItem !== null && $close($optrItem['amount'], 20.00), 'Per-trip configured costs count distinct trips, not revenue rows');
$check(
    $close($typeOverview['configured_costs']['total'], $overview['configured_costs']['total']),
    'Configured costs keep their route/bus basis under a passenger-type filter'
);

/* ---------------- Fuel ledger scope and operating reconciliation ---------------- */

$ledgerFuelSection = $ledgerService->fuel($october);
$check($close($ledgerFuelSection['operating']['fuel_cost'], 500.00), 'Fuel operating cost follows the recorded ledger');
$checkSame($ledgerFuelSection['operating']['source'], 'recorded_expenses', 'Ledger fuel is identified as the operating source');
$check($close($ledgerFuelSection['summary']['purchase_cost'], 1200.00), 'Purchase receipts stay listed for volume detail when a ledger exists');

/* ---------------- Empty-state flags ---------------- */

$checkSame($emptyOverview['payment_methods'], [], 'No payment methods are invented for an empty database');
$checkSame($emptyOverview['quarterly'], [], 'No quarterly buckets are invented for an empty database');
$checkSame($emptyOverview['waterfall'], [], 'No profit bridge is invented for an empty database');
$checkSame($emptyOverview['previous_available'], false, 'Empty previous windows are flagged unavailable');

/* ---------------- Financial insight detectors ---------------- */

$check(str_contains($findings, 'Negative estimated operating profit'), 'Negative estimated operating profit is reported with components');

$missingDb = FinancialIntelligenceFixture::pdo();
$missingDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, status, total_revenue) VALUES (120, 'B1', 'D1', 'Cabanatuan-Rizal', '2026-10-09', 'Completed', 0)");
$missingService = new FinancialIntelligenceService($missingDb);
$missingOverview = $missingService->overview($october);
$check(str_contains(implode(' ', $missingOverview['findings']), 'no matching paid collection records'), 'Completed trips without collections are reported');

$fuelInsightDb = FinancialIntelligenceFixture::pdo();
$fuelInsightDb->exec("INSERT INTO fuel_refuels (refuel_id, bus_id, driver_id, trip_id, refuel_odo, liters, price_per_liter, total_cost, is_full_tank, created_at) VALUES (20, 'B1', 'D1', '101', 9000, 20.0, 15.0, 300.00, 1, '2026-08-15 06:00:00')");
$fuelInsightDb->exec("INSERT INTO fuel_refuels (refuel_id, bus_id, driver_id, trip_id, refuel_odo, liters, price_per_liter, total_cost, is_full_tank, created_at) VALUES (21, 'B1', 'D1', '101', 11000, 20.0, 40.0, 800.00, 1, '2026-10-08 06:00:00')");
$fuelInsightService = new FinancialIntelligenceService($fuelInsightDb);
$fuelInsightOverview = $fuelInsightService->overview($october);
$check(str_contains(implode(' ', $fuelInsightOverview['findings']), 'trailing average'), 'Unusual fuel prices are reported against the trailing average');

$expenseChangeDb = FinancialIntelligenceFixture::pdo();
$expenseChangeDb->exec("INSERT INTO expenses (id, bus_id, trip_id, category, amount, description, created_at) VALUES (30, 'B1', NULL, 'maintenance', 100.00, 'September service', '2026-09-15 09:00:00')");
$expenseChangeService = new FinancialIntelligenceService($expenseChangeDb);
$expenseChangeOverview = $expenseChangeService->overview($october);
$check(str_contains(implode(' ', $expenseChangeOverview['findings']), 'Recorded expense category maintenance increased'), 'Material expense changes are reported by category');

/* ---------------- Options ---------------- */

$options = $service->options();
$check(in_array('today', array_column($options['periods'], 'value'), true), 'Options include Today');
$check(in_array('yesterday', array_column($options['periods'], 'value'), true), 'Options include Yesterday');
$check(!in_array('yoy', array_column($options['periods'], 'value'), true), 'The redundant Year over Year option is not offered');
$check(in_array('yearly', array_column($options['periods'], 'value'), true), 'Annually (Full Year) remains the year comparison option');
$check(in_array(2026, $options['years'], true), 'Options include recorded years');
$check(in_array('Cabanatuan - Rizal', $options['routes'], true), 'Options include routes from trips and fare matrix');
$check(in_array('B1', $options['buses'], true), 'Options include buses');
$check(in_array('Senior Citizen', $options['passenger_types'], true), 'Options include passenger types');
$check(in_array('Cooperative', $options['passenger_types'], true) && in_array('Beneficiary', $options['passenger_types'], true), 'Options include the cooperative and beneficiary categories');

/* ---------------- Data-integrity cases: cross-midnight, archived refs, twins, exclusion ---------------- */

$integrityDb = FinancialIntelligenceFixture::pdo();
FinancialIntelligenceFixture::seedIntegrityCases($integrityDb);
$integrityService = new FinancialIntelligenceService($integrityDb);

$nov1 = $resolve(['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-01']);
$nov2 = $resolve(['period' => 'custom', 'from' => '2026-11-02', 'to' => '2026-11-02']);
$nov3 = $resolve(['period' => 'custom', 'from' => '2026-11-03', 'to' => '2026-11-03']);
$nov4 = $resolve(['period' => 'custom', 'from' => '2026-11-04', 'to' => '2026-11-04']);
$november = $resolve(['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-30']);

$nov1Fleet = $integrityService->fleet($nov1);
$nov1ByRoute = array_column($nov1Fleet['routes'], 'collections', 'route');
$check(isset($nov1ByRoute['Cabanatuan - Rizal']) && $close($nov1ByRoute['Cabanatuan - Rizal'], 100.00), 'Cross-midnight boarding resolves through the previous day\'s recorded trip');
$check($close($nov1Fleet['unattributed']['collections'], 0.00), 'Cross-midnight window leaves nothing unattributed');
$check($nov1Fleet['reconciliation']['balanced'] === true, 'Route collections plus unattributed equal the section total');

$nov1Overview = $integrityService->overview($nov1);
$check($close($nov1Overview['actual']['collections'], 100.00), 'A live fare and its archived twin count exactly once');
$checkSame($nov1Overview['actual']['passengers'], 1, 'A live fare and its archived twin contribute one passenger');

$nov2Fleet = $integrityService->fleet($nov2);
$nov2ByRoute = array_column($nov2Fleet['routes'], 'collections', 'route');
$check(isset($nov2ByRoute['Cabanatuan - Kalikid']) && $close($nov2ByRoute['Cabanatuan - Kalikid'], 170.00), 'Archived TRIP- and ARCV- references attribute through the bus and prefix mapping');
$check($close($nov2Fleet['unattributed']['collections'], 0.00), 'Mapped archived references leave nothing unattributed');

$nov3Fleet = $integrityService->fleet($nov3);
$check($close($nov3Fleet['unattributed']['collections'], 70.00), 'Unmapped archived reference without a bus/day basis stays unattributed');
$nov3Causes = array_column($nov3Fleet['unattributed']['causes'], 'collections', 'cause');
$check(isset($nov3Causes['history_reference_unmapped']) && $close($nov3Causes['history_reference_unmapped'], 70.00), 'Unattributed amount is itemized with its cause');

$nov4Fleet = $integrityService->fleet($nov4);
$nov4Causes = array_column($nov4Fleet['unattributed']['causes'], 'collections', 'cause');
$check(isset($nov4Causes['configuration_excluded']) && $close($nov4Causes['configuration_excluded'], 50.00), 'Configuration-excluded route labels are disclosed as their own cause instead of dropped');
$check($nov4Fleet['reconciliation']['balanced'] === true, 'Excluded collections keep the section total reconciled');

$novemberFuel = $integrityService->fuel($november);
$check($close($novemberFuel['summary']['purchase_cost'], 450.00), 'A purchase recorded in both fuel sources counts once (150 + 300 + 0, not 600)');
$check($close($novemberFuel['summary']['liters'], 35.00), 'Dual-recorded fuel liters are counted once (10 + 20 + 5, not 10 + 10 + 20 + 5)');
$checkSame($novemberFuel['summary']['purchases'], 3, 'Dual-recorded fuel purchase yields one row for the duplicate pair');

/* ---------------- Eligible-based fare coverage and unmatched causes ---------------- */

$nov5 = $resolve(['period' => 'custom', 'from' => '2026-11-05', 'to' => '2026-11-05']);
$nov5Fare = $integrityService->fare($nov5);
$checkSame($nov5Fare['applied']['coverage_pct'], 0.0, 'A resolved route with no matrix row yields 0% coverage');
$checkSame($nov5Fare['applied']['eligible_rows'], 1, 'Coverage denominator counts eligible resolved routes');
$nov5Causes = array_column($nov5Fare['applied']['unmatched_causes'], 'rows', 'cause');
$check(($nov5Causes['matrix_row_missing'] ?? 0) === 1, 'Missing matrix row is reported as the unmatched cause');

$nov3Fare = $integrityService->fare($nov3);
$checkSame($nov3Fare['applied']['coverage_pct'], null, 'Coverage is unavailable when no recorded route resolves');
$nov3FareCauses = array_column($nov3Fare['applied']['unmatched_causes'], 'rows', 'cause');
$check(($nov3FareCauses['unresolved_route'] ?? 0) === 1, 'Unresolved route identity is reported as the unmatched cause');
$checkSame($nov3Fare['applied']['eligible_rows'], 0, 'No journey is eligible while its route identity is unresolved');

$octoberFareIntegrity = $integrityService->fare($october);
$checkSame($octoberFareIntegrity['applied']['coverage_pct'], 100.0, 'Fully matched October scope still reports 100% eligible coverage');
$checkSame($octoberFareIntegrity['applied']['unmatched_rows'], 0, 'Fully matched October scope has no unmatched causes');

/* ---------------- Fuel measurement states and operational months ---------------- */

$check($close($novemberFuel['summary']['avg_kpl'], 8.5), 'KPL is derived from distance and liters when not recorded');
$checkSame($novemberFuel['summary']['measurements']['derived'], 1, 'Derived measurement is counted as a measurement');
$checkSame($novemberFuel['summary']['measurements']['missing'], 2, 'Rows that cannot yield an efficiency value are counted as missing, not zero');
$checkSame($novemberFuel['summary']['measurements']['zero'], 1, 'A genuine zero record is distinguished from a missing measurement');
$checkSame($novemberFuel['summary']['avg_kpl_source'], 'recorded and derived measurements', 'Average KPL discloses that derived values participate');

$nov6 = $resolve(['period' => 'custom', 'from' => '2026-11-06', 'to' => '2026-11-06']);
$nov6Fuel = $integrityService->fuel($nov6);
$checkSame($nov6Fuel['summary']['avg_kpl'], null, 'Missing measurements yield unavailable efficiency');
$checkSame($nov6Fuel['summary']['cost_per_km'], null, 'Cost per kilometer is unavailable without purchases or valid distance');

$nov7 = $resolve(['period' => 'custom', 'from' => '2026-11-07', 'to' => '2026-11-07']);
$nov7Fuel = $integrityService->fuel($nov7);
$checkSame($nov7Fuel['summary']['measurements']['zero'], 1, 'A zero record does not become a missing-measurement warning');
$checkSame($nov7Fuel['summary']['avg_kpl'], null, 'A zero record provides no efficiency value');

$december = $resolve(['period' => 'custom', 'from' => '2026-12-01', 'to' => '2026-12-31']);
$decemberFuel = $integrityService->fuel($december);
$decemberMonths = array_column($decemberFuel['monthly'], 'month');
$check(in_array('2026-12', $decemberMonths, true), 'Months with operational records appear even without purchases');
$decemberRow = null;
foreach ($decemberFuel['monthly'] as $monthRow) {
    if ($monthRow['month'] === '2026-12') {
        $decemberRow = $monthRow;
        break;
    }
}
$check($decemberRow !== null && $close($decemberRow['avg_kpl'], 6.0), 'Operational-only month carries its measured efficiency');
$check($decemberRow !== null && $decemberRow['purchases'] === 0, 'Operational-only month reports no purchases rather than a fabricated cost');

/* ---------------- Remittance date fallback, undated disclosure, and filtered attribution ---------------- */

$novemberRemit = $integrityService->remittance($november);
$check($close($novemberRemit['summary']['expected_cash'], 80.00), 'Null business_date rows appear through the created_at fallback');
$check($close($novemberRemit['summary']['actual_cash'], 80.00), 'Fallback-dated rows contribute their settled amounts');
$checkSame($novemberRemit['summary']['undated_rows'], 1, 'Rows with no usable date are reported instead of dropped');
$check($close($novemberRemit['summary']['undated_expected_cash'], 50.00), 'Undated exposure amount is disclosed');

$nov1RouteRemit = $integrityService->remittance($resolve([
    'period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-01', 'route' => 'Cabanatuan-Rizal',
]));
$check($close($nov1RouteRemit['summary']['expected_cash'], 0.00), 'Unattributable settlements leave the route-filtered view');
$checkSame($nov1RouteRemit['summary']['excluded_by_scope']['rows'], 1, 'Filtered-out settlements are counted in the excluded bucket');
$check($close($nov1RouteRemit['summary']['excluded_by_scope']['expected_cash'], 80.00), 'Filtered-out settlement amounts are surfaced');

$nov1BusRemit = $integrityService->remittance($resolve([
    'period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-01', 'bus' => 'B1',
]));
$check($close($nov1BusRemit['summary']['expected_cash'], 80.00), 'Settlements with a matching bus reference stay in the bus-filtered view');
$checkSame($nov1BusRemit['summary']['excluded_by_scope']['rows'], 0, 'Attributable settlements leave nothing in the excluded bucket');

$remitStatusLabels = array_column($novemberRemit['statuses'], 'status');
$check(in_array('Completed', $remitStatusLabels, true), 'Settlement statuses group case-tolerantly under their recorded label');

/* ---------------- Fleet route activity and genuine zero-route state ---------------- */

$oct10 = $resolve(['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-10']);
$oct10Fleet = $integrityService->fleet($oct10);
$oct10Routes = array_column($oct10Fleet['routes'], null, 'route');
$check(isset($oct10Routes['Cabanatuan - Rizal']) && (int) $oct10Routes['Cabanatuan - Rizal']['trips'] === 1, 'A route with completed trips appears even without collections');
$check(($oct10Routes['Cabanatuan - Rizal']['estimated_profit'] ?? null) === null, 'An activity-only route withholds unqualified profitability');
$checkSame($oct10Fleet['route_activity']['completed_trips'], 1, 'Route activity counts completed trips in the scope');

$oct20 = $resolve(['period' => 'custom', 'from' => '2026-10-20', 'to' => '2026-10-20']);
$oct20Fleet = $integrityService->fleet($oct20);
$checkSame(count($oct20Fleet['routes']), 0, 'No route row is shown when no trip or collection qualifies');
$checkSame($oct20Fleet['route_activity']['completed_trips'], 0, 'The zero-route state carries zero completed trips');
$check(str_contains(implode(' ', $oct20Fleet['notes']), 'zero-route view means no qualifying trip'), 'The zero-route state explains the absence instead of implying no routes are defined');

/* ---------------- Comparison window recomputation and partial disclosure ---------------- */

$monthlyFilters = $resolve(['period' => 'monthly']);
$monthlyOverview = $service->overview($monthlyFilters);
$checkSame($monthlyOverview['period']['days'], 31, 'Current window keeps its own day count');
$checkSame($monthlyOverview['period']['previous_days'], 30, 'Previous window day count is recomputed for the previous month');
$check($close($monthlyOverview['configured_costs']['months'], 1.02), 'Current configured costs scale by the current window');
$check($close($monthlyOverview['configured_costs_previous']['months'], 0.99), 'Previous configured costs scale by the previous window, not the current one');

$todayManila = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$currentDayFilters = $resolve(['period' => 'custom', 'from' => $todayManila, 'to' => $todayManila]);
$checkSame($service->overview($currentDayFilters)['period']['partial'], true, 'A window containing the current business day is flagged partial');
$pastFilters = $resolve(['period' => 'custom', 'from' => '2020-01-01', 'to' => '2020-01-02']);
$checkSame($service->overview($pastFilters)['period']['partial'], false, 'A completed past window is not flagged partial');

$filteredMonthly = $service->overview($resolve(['period' => 'monthly', 'route' => 'Cabanatuan-Rizal']));
$checkSame($filteredMonthly['period']['route'], 'Cabanatuan-Rizal', 'Filtered comparison discloses the active route filter');
$checkSame($filteredMonthly['period']['previous_days'], 30, 'Filtered comparison still recomputes the previous window days');

/* ---------------- Journeys versus registered accounts ---------------- */

$passengerSection = $service->passengers($october);
$checkSame($passengerSection['accounts']['journeys'], 7, 'Journey metric counts recorded paid journeys, not accounts');
$checkSame($passengerSection['accounts']['registered'], 2, 'Account metric counts unique registered passenger identities');
$checkSame($passengerSection['accounts']['identified_in_scope'], 2, 'The scope discloses how many journeys name a registered identity');
$check($passengerSection['accounts']['journeys'] !== $passengerSection['accounts']['registered'], 'Journeys and accounts stay distinct measures with their own labels');
$check(str_contains($passengerSection['accounts']['note'], 'must not be substituted'), 'The journeys-versus-accounts distinction is documented in the payload');

/* ---------------- Maintained data-source map ---------------- */

$sourceMap = FinancialIntelligenceService::dataSourceMap();
$check($sourceMap !== [], 'The authoritative data-source map is maintained in the service');
$fixtureColumns = [];
foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
    $fixtureColumns[strtolower((string) $tableName)] = array_map(
        static fn(array $column): string => strtolower((string) $column['name']),
        $db->query('PRAGMA table_info(' . $tableName . ')')->fetchAll(PDO::FETCH_ASSOC)
    );
}
$missingMapEntries = [];
foreach ($sourceMap as $sectionName => $entries) {
    $check($entries !== [], 'The map covers the ' . $sectionName . ' section');
    foreach ($entries as $entry) {
        $table = strtolower((string) $entry['table']);
        if (!isset($fixtureColumns[$table])) {
            $missingMapEntries[] = $table . '.*';
            continue;
        }
        foreach ($entry['columns'] as $column) {
            if (!in_array(strtolower((string) $column), $fixtureColumns[$table], true)) {
                $missingMapEntries[] = $table . '.' . $column;
            }
        }
    }
}
$check($missingMapEntries === [], 'Every mapped table and column exists in the schema (missing: ' . implode(', ', $missingMapEntries) . ')');

$overviewWithSources = $service->section('overview', $october);
$check(in_array('passenger_trips', $overviewWithSources['data_sources'] ?? [], true), 'The overview payload cites its fare source from the map');
$check(in_array('bus_trips', $overviewWithSources['data_sources'] ?? [], true), 'The overview payload cites its attribution source from the map');
$remitWithSources = $service->section('remittance', $october);
$check(in_array('remittance_logs', $remitWithSources['data_sources'] ?? [], true), 'The remittance payload cites its settlement source from the map');
$allWithSources = $service->section('all', $october);
$check(count($allWithSources['data_sources'] ?? []) >= 8, 'The combined payload cites the union of its section sources');

echo "$checks financial intelligence checks passed.\n";
