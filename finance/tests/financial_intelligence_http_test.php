<?php
declare(strict_types=1);

/**
 * HTTP acceptance tests for the Financial Intelligence API. Runs the
 * production endpoint against a file-backed SQLite fixture through PHP's
 * built-in server, covering the role gate, JSON envelope, filters, and every
 * section's key arithmetic.
 *
 * Usage: php finance/tests/financial_intelligence_http_test.php
 */

$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_financial_intelligence_http_fixture.sqlite';
@unlink($fixture);

foreach (glob($root . '/finance/storage/ratelimit/*') ?: [] as $rateFile) {
    @unlink($rateFile);
}

$port = 8138;
$base = 'http://127.0.0.1:' . $port;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function httpRequest(string $query, array $headers = []): array
{
    global $base;
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 60,
        ],
    ];
    $response = @file_get_contents($base . $query, false, stream_context_create($options));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match)) {
            $status = (int) $match[1];
        }
    }
    return [$status, (string) $response];
}

function jsonRequest(string $query, string $role = 'finance_manager', array $extraHeaders = []): array
{
    [$status, $response] = httpRequest($query, array_merge(['X-Test-Role: ' . $role], $extraHeaders));
    $decoded = json_decode($response, true);
    return [$status, is_array($decoded) ? $decoded : null, $response];
}

$command = [
    PHP_BINARY,
    '-d', 'display_errors=0',
    '-d', 'memory_limit=512M',
    '-S', '127.0.0.1:' . $port,
    'finance/tests/financial_intelligence_http_router.php',
];

$serverLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_financial_intelligence_http_server.log';
@unlink($serverLogFile);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $serverLogFile, 'a'],
    2 => ['file', $serverLogFile, 'a'],
];
$process = proc_open($command, $descriptors, $pipes, $root, null, ['bypass_shell' => true]);

if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the test HTTP server.\n");
    exit(1);
}

$ready = false;
for ($i = 0; $i < 60; $i++) {
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
    if ($socket) {
        fclose($socket);
        $ready = true;
        break;
    }
    usleep(150000);
}

if (!$ready) {
    proc_terminate($process);
    fwrite(STDERR, "The test HTTP server did not start.\n");
    exit(1);
}

$october = '&period=custom&from=2026-10-01&to=2026-10-31';

try {
    /* ---------- role gate ---------- */

    [$status, $data] = jsonRequest('?section=overview' . $october, 'none');
    check($status === 401 && $data && $data['success'] === false, 'Unauthenticated callers receive 401 JSON');

    [$status, $data] = jsonRequest('?section=overview' . $october, 'driver');
    check($status === 403 && $data && $data['success'] === false, 'Non-finance roles receive 403 JSON');

    [$status, $data] = jsonRequest('?section=overview' . $october, 'finance_officer');
    check($status === 200 && $data['success'] === true, 'Finance officers can read the overview');

    /* ---------- overview ---------- */

    [$status, $data, $raw] = jsonRequest('?section=overview' . $october);
    check($status === 200 && $data && $data['success'] === true, 'Overview responds with JSON success');
    check(abs((float) $data['data']['actual']['collections'] - 722.00) < 0.011, 'Overview collections match the fixture');
    check(abs((float) $data['data']['actual']['profit'] - (-728.00)) < 0.011, 'Overview profit matches the fixture');
    check(abs((float) $data['data']['actual']['configured_costs'] - 2668.42) < 0.011, 'Overview includes configured monthly costs');
    check(abs((float) $data['data']['actual']['profit_after_configured'] - (-3396.42)) < 0.011, 'Overview includes profit after configured costs');
    check(count($data['data']['waterfall']) === 6, 'Overview includes the six-step profit bridge');
    check(count($data['data']['findings']) >= 4, 'Overview returns findings');
    check(count($data['data']['quarterly']) === 3, 'Quarterly comparison covers the fixture quarters');
    check(count($data['data']['by_route']) === 2, 'Overview lists both routes');

    /* ---------- passenger types ---------- */

    [$status, $data] = jsonRequest('?section=passengers' . $october);
    $types = array_column($data['data']['types'], 'trips', 'type');
    check($data['success'] && ($types['Regular'] ?? 0) === 3, 'Passenger types respond with Regular trips');
    check((float) $data['data']['baggage']['collections'] === 50.0, 'Baggage collections reported separately');
    check(!isset($types['Baggage']), 'Baggage is not listed as a passenger type');

    /* ---------- fare matrix ---------- */

    [$status, $data] = jsonRequest('?section=fare' . $october);
    check($data['success'] && (int) $data['data']['segment_count'] === 3, 'Fare matrix segments are returned');
    check(abs((float) $data['data']['applied']['variance'] - 20.00) < 0.011, 'Fare matrix alignment variance');
    check($data['data']['change_tracking']['observable'] === true, 'Fare change tracking is observable in October');

    /* ---------- fuel ---------- */

    [$status, $data] = jsonRequest('?section=fuel' . $october);
    check(abs((float) $data['data']['summary']['purchase_cost'] - 1200.00) < 0.011, 'Fuel purchase cost');
    check(count($data['data']['by_route']['routes']) === 2, 'Fuel attributed to both routes');
    check(count($data['data']['prices']) === 2, 'Historical diesel prices returned');

    /* ---------- fleet ---------- */

    [$status, $data] = jsonRequest('?section=fleet' . $october);
    $routes = array_column($data['data']['routes'], null, 'route');
    check(abs((float) $routes['Cabanatuan - Rizal']['estimated_profit'] - (-590.00)) < 0.011, 'Fleet route profit');
    check($routes['Cabanatuan - Rizal']['cost_complete'] === true, 'Fleet route cost completeness flag');
    check(count($data['data']['buses']) === 2, 'Fleet buses returned');

    /* ---------- remittance ---------- */

    [$status, $data] = jsonRequest('?section=remittance' . $october);
    check(abs((float) $data['data']['summary']['shortage'] - 30.00) < 0.011, 'Remittance shortage');
    check(abs((float) $data['data']['summary']['overage'] - 20.00) < 0.011, 'Remittance overage');
    check(abs((float) $data['data']['summary']['outstanding_amount'] - 20.00) < 0.011, 'Outstanding discrepancy exposure');

    /* ---------- filters apply to costs ---------- */

    [$status, $data] = jsonRequest('?section=overview' . $october . '&route=Cabanatuan-Rizal');
    check(abs((float) $data['data']['actual']['collections'] - 360.00) < 0.011, 'Route filter matches the recorded direction and narrows collections');
    check(abs((float) $data['data']['actual']['expenses'] - 950.00) < 0.011, 'Route filter narrows attributable costs');

    [$status, $data] = jsonRequest('?section=overview' . $october . '&bus=B1');
    check(abs((float) $data['data']['actual']['collections'] - 360.00) < 0.011, 'Bus filter narrows collections');
    check(abs((float) $data['data']['actual']['expenses'] - 1000.00) < 0.011, 'Bus filter narrows attributable costs');

    /* ---------- fuel route filter and operating reconciliation ---------- */

    [$status, $data] = jsonRequest('?section=fuel' . $october . '&route=Cabanatuan-Rizal');
    check(abs((float) $data['data']['summary']['purchase_cost'] - 750.00) < 0.011, 'Fuel route filter narrows attributed purchases');
    check(count($data['data']['by_route']['routes']) === 1, 'Fuel route filter lists only the selected route');
    check(abs((float) $data['data']['operating']['fuel_cost'] - 750.00) < 0.011, 'Fuel operating cost reconciles under the route filter');

    /* ---------- remittance filters ---------- */

    [$status, $data] = jsonRequest('?section=remittance' . $october . '&bus=B1');
    check(abs((float) $data['data']['summary']['expected_cash'] - 230.00) < 0.011, 'Remittance bus filter narrows expected cash over HTTP');

    [$status, $data] = jsonRequest('?section=remittance' . $october . '&route=Cabanatuan-Rizal');
    check(abs((float) $data['data']['summary']['expected_cash'] - 180.00) < 0.011, 'Remittance route filter narrows expected cash over HTTP');

    /* ---------- forecast and scope metadata ---------- */

    [$status, $data] = jsonRequest('?section=forecast' . $october . '&horizon=7d&passenger_type=Student');
    check($data['success'] === true && array_key_exists('scope', $data['data']), 'Forecast carries scope metadata for passenger-type requests');

    [$status, $data] = jsonRequest('?section=overview' . $october . '&passenger_type=Student');
    $scopeNotes = array_column($data['data']['scope']['not_applicable'], 'message');
    check($scopeNotes !== [] && str_contains(implode(' ', $scopeNotes), 'not passenger-specific'), 'Overview scope metadata explains the passenger-type limitation');

    /* ---------- empty-state payloads ---------- */

    [$status, $data] = jsonRequest('?section=overview&period=custom&from=2020-01-01&to=2020-01-02');
    check($data['data']['payment_methods'] === [], 'Empty selections return no payment methods');
    check($data['data']['quarterly'] === [], 'Empty selections return no quarterly buckets');
    check($data['data']['previous_available'] === false, 'Empty selections flag the comparison as unavailable');
    check($data['data']['waterfall'] === [], 'Empty selections return no profit bridge');

    /* ---------- partial data and primary-source failure ---------- */

    [$status, $data] = jsonRequest('?section=overview' . $october, 'finance_manager', ['X-Test-Fail: from passenger_trip_history']);
    check($status === 200 && $data && $data['success'] === true, 'A failed secondary source still returns data');
    check(($data['data']['partial'] ?? null) === true, 'A failed secondary source flags the payload as partial');
    check(($data['data']['source_errors'][0]['source'] ?? '') === 'passenger_trip_history', 'The partial payload names the failed source');
    check(abs((float) $data['data']['actual']['collections'] - 280.00) < 0.011, 'Partial data still reports the readable live source');

    [$status, $data] = jsonRequest('?section=overview' . $october, 'finance_manager', ['X-Test-Fail: from passenger_trips']);
    check($status === 500 && $data && $data['success'] === false, 'A failed primary source returns the error envelope, not empty data');

    [$status, $data] = jsonRequest('?section=overview' . $october);
    check(($data['data']['partial'] ?? null) === false, 'A complete payload is not flagged as partial');
    check(($data['data']['source_errors'] ?? null) === [], 'A complete payload reports no source errors');
    check(in_array('passenger_trips', $data['data']['data_sources'] ?? [], true), 'Payloads cite their authoritative sources from the maintained map');

    /* ---------- what-if ---------- */

    [$status, $data] = jsonRequest('?section=whatif' . $october . '&fare_change_pct=10&volume_change_pct=5&fuel_price_change_pct=20&other_expense_change_pct=-10');
    check(abs((float) $data['data']['projected']['profit'] - (-3499.51)) < 0.011, 'What-if projected profit after configured costs');
    check(abs((float) $data['data']['difference']['collections'] - 111.91) < 0.011, 'What-if collection difference');

    /* ---------- forecast endpoint responds ---------- */

    [$status, $data] = jsonRequest('?section=forecast' . $october . '&horizon=7d');
    check($status === 200 && $data['success'] === true && array_key_exists('available', $data['data']), 'Forecast section responds with an availability flag');

    /* ---------- options and periods ---------- */

    [$status, $data] = jsonRequest('?section=options');
    check(count($data['data']['periods']) === 9, 'Options list every supported period (year-over-year removed)');
    $periodValues = array_column($data['data']['periods'], 'value');
    check(in_array('quarter', $periodValues, true), 'Options include a single functional Quarterly period');
    check(in_array('h1', $periodValues, true) && in_array('h2', $periodValues, true), 'Options include both semi-annual halves');
    check(in_array('yearly', $periodValues, true), 'Options include the annual period');
    check(!in_array('yoy', $periodValues, true), 'Options no longer expose the redundant year-over-year period');
    [$status, $data] = jsonRequest('?section=overview&period=yoy&year=2026');
    check(
        $status === 200 && $data['success'] === true
        && ($data['period']['period'] ?? '') === 'yearly'
        && ($data['period']['label'] ?? '') === '2026 vs 2025',
        'Legacy year-over-year requests resolve to the selected year against the year before'
    );

    [$status, $data] = jsonRequest('?section=overview&period=q4&year=2026');
    check($data['success'] && abs((float) $data['data']['actual']['collections'] - 722.00) < 0.011, 'Q4 period resolves and returns the fixture total');

    /* ---------- validation ---------- */

    [$status, $data] = jsonRequest('?section=not-a-section' . $october);
    check($status === 400 && $data['success'] === false, 'Unknown sections are rejected with 400');

    [$status, $data] = jsonRequest('?section=overview&period=custom&from=2026-10-01');
    check($status === 422 && $data['success'] === false, 'Incomplete custom ranges are rejected with 422');

    echo "$checks financial intelligence HTTP checks passed.\n";
} catch (Throwable $error) {
    $log = is_file($serverLogFile) ? (string) file_get_contents($serverLogFile) : '';
    fwrite(STDERR, "FAILED: " . $error->getMessage() . "\n");
    if ($log !== '') {
        fwrite(STDERR, "--- server log (tail) ---\n" . substr($log, -2000) . "\n");
    }
    proc_terminate($process);
    exit(1);
}

proc_terminate($process);
