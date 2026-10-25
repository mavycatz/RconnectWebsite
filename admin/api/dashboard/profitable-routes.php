<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/Database.php';

if (file_exists(__DIR__ . '/cache.php')) {
    require_once __DIR__ . '/cache.php';
}

use App\Core\Database;

header('Content-Type: application/json');

try {

    $db = Database::get();

    /* =====================================================
       FILTERS
    ===================================================== */

    $bus       = trim($_GET['bus'] ?? '');
    $day       = trim($_GET['day'] ?? '');
    $month     = trim($_GET['month'] ?? '');
    $year      = trim($_GET['year'] ?? '');
    $fromDate  = trim($_GET['fromDate'] ?? '');
    $toDate    = trim($_GET['toDate'] ?? '');
    $fromTime  = trim($_GET['fromTime'] ?? '');
    $toTime    = trim($_GET['toTime'] ?? '');

    /* =====================================================
       CACHE: serve fresh entries to skip heavy aggregation
    ===================================================== */

    $cacheKey = null;

    if (function_exists('dashboard_cache_key')
        && function_exists('dashboard_cache_get')) {

        $cacheKey = dashboard_cache_key('routes', [
            'bus'      => $bus,
            'day'      => $day,
            'month'    => $month,
            'year'     => $year,
            'fromDate' => $fromDate,
            'toDate'   => $toDate,
            'fromTime' => $fromTime,
            'toTime'   => $toTime,
        ]);

        $cached = in_array(strtolower((string)($_GET['_nocache'] ?? '')), ['1', 'true', 'yes'], true)
            ? null
            : dashboard_cache_get($cacheKey);

        if ($cached !== null) {
            echo json_encode($cached);
            exit;
        }
    }

    /* =====================================================
       LOAD FARE PRICING ROUTE MAP
    ===================================================== */

    $fpRows = $db->query(
        "SELECT LOWER(TRIM(destination)) AS stop, route_name, display_order
         FROM fare_pricing
         ORDER BY route_name, display_order"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* route_name => [ stop => display_order ] */
    $routeMap = [];
    foreach ($fpRows as $r) {
        $routeMap[$r['route_name']][$r['stop']] = (int)$r['display_order'];
    }

    /* build reverse lookup: normalized stop => route_name => display_order */
    $stopIndex = []; /* normalized_stop => [ route_name => display_order ] */
    foreach ($fpRows as $r) {
        $stopIndex[$r['stop']][$r['route_name']] = (int)$r['display_order'];
    }

    /* find best matching route_name for a (from, to) pair */
    function resolveRoute(string $from, string $to, array $stopIndex): ?string
    {
        $from = strtolower(trim($from));
        $to   = strtolower(trim($to));
        if ($from === '' || $to === '' || $from === $to) return null;

        /* 1. try exact match first */
        if (isset($stopIndex[$from]) && isset($stopIndex[$to])) {
            foreach ($stopIndex[$from] as $rn => $fOrd) {
                $tOrd = $stopIndex[$to][$rn] ?? null;
                if ($tOrd !== null && $fOrd < $tOrd) {
                    return $rn;
                }
            }
        }

        /* 2. fuzzy: stop name is contained in a fare_pricing destination */
        $candidates = []; /* route_name => [ from_order, to_order ] */
        foreach ($stopIndex as $fpStop => $routes) {
            if (str_contains($fpStop, $from) || str_contains($from, $fpStop)) {
                foreach ($routes as $rn => $ord) {
                    $candidates[$rn]['from'] = $ord;
                }
            }
            if (str_contains($fpStop, $to) || str_contains($to, $fpStop)) {
                foreach ($routes as $rn => $ord) {
                    $candidates[$rn]['to'] = $ord;
                }
            }
        }

        foreach ($candidates as $rn => $pair) {
            if (isset($pair['from'], $pair['to']) && $pair['from'] < $pair['to']) {
                return $rn;
            }
        }

        return null;
    }

    /* =====================================================
       BUILD FILTERS
    ===================================================== */

    $conditionsPT = [];
    $paramsPT = [];

    if ($bus !== '') {
        $conditionsPT[] = "LOWER(TRIM(pt.bus_no)) = LOWER(TRIM(:busPT))";
        $paramsPT[':busPT'] = $bus;
    }
    if ($day !== '') {
        $conditionsPT[] = "DAY(pt.boarding_time) = :dayPT";
        $paramsPT[':dayPT'] = (int)$day;
    }
    if ($month !== '') {
        $conditionsPT[] = "MONTH(pt.boarding_time) = :monthPT";
        $paramsPT[':monthPT'] = (int)$month;
    }
    if ($year !== '') {
        $conditionsPT[] = "YEAR(pt.boarding_time) = :yearPT";
        $paramsPT[':yearPT'] = (int)$year;
    }
    if ($fromDate !== '') {
        $conditionsPT[] = "DATE(pt.boarding_time) >= :fromDatePT";
        $paramsPT[':fromDatePT'] = $fromDate;
    }
    if ($toDate !== '') {
        $conditionsPT[] = "DATE(pt.boarding_time) <= :toDatePT";
        $paramsPT[':toDatePT'] = $toDate;
    }
    if ($fromTime !== '') {
        $conditionsPT[] = "TIME(pt.boarding_time) >= :fromTimePT";
        $paramsPT[':fromTimePT'] = $fromTime;
    }
    if ($toTime !== '') {
        $conditionsPT[] = "TIME(pt.boarding_time) <= :toTimePT";
        $paramsPT[':toTimePT'] = $toTime;
    }
    $conditionsPT[] = "pt.boarding_point IS NOT NULL AND TRIM(pt.boarding_point) != ''";
    $conditionsPT[] = "pt.destination IS NOT NULL AND TRIM(pt.destination) != ''";

    $wherePT = count($conditionsPT) ? 'WHERE ' . implode(' AND ', $conditionsPT) : '';

    $conditionsPTH = [];
    $paramsPTH = [];

    if ($bus !== '') {
        $conditionsPTH[] = "LOWER(TRIM(pth.bus_number)) = LOWER(TRIM(:busPTH))";
        $paramsPTH[':busPTH'] = $bus;
    }
    if ($day !== '') {
        $conditionsPTH[] = "DAY(pth.trip_date) = :dayPTH";
        $paramsPTH[':dayPTH'] = (int)$day;
    }
    if ($month !== '') {
        $conditionsPTH[] = "MONTH(pth.trip_date) = :monthPTH";
        $paramsPTH[':monthPTH'] = (int)$month;
    }
    if ($year !== '') {
        $conditionsPTH[] = "YEAR(pth.trip_date) = :yearPTH";
        $paramsPTH[':yearPTH'] = (int)$year;
    }
    if ($fromDate !== '') {
        $conditionsPTH[] = "DATE(pth.trip_date) >= :fromDatePTH";
        $paramsPTH[':fromDatePTH'] = $fromDate;
    }
    if ($toDate !== '') {
        $conditionsPTH[] = "DATE(pth.trip_date) <= :toDatePTH";
        $paramsPTH[':toDatePTH'] = $toDate;
    }
    if ($fromTime !== '') {
        $conditionsPTH[] = "TIME(pth.time_of_departure) >= :fromTimePTH";
        $paramsPTH[':fromTimePTH'] = $fromTime;
    }
    if ($toTime !== '') {
        $conditionsPTH[] = "TIME(pth.time_of_departure) <= :toTimePTH";
        $paramsPTH[':toTimePTH'] = $toTime;
    }
    $conditionsPTH[] = "pth.boarding_point IS NOT NULL AND TRIM(pth.boarding_point) != ''";
    $conditionsPTH[] = "pth.destination IS NOT NULL AND TRIM(pth.destination) != ''";

    $wherePTH = count($conditionsPTH) ? 'WHERE ' . implode(' AND ', $conditionsPTH) : '';

    /* =====================================================
       MAIN QUERY (PT + PTH with toe)
    ===================================================== */

    $sql = "
    SELECT
        combined.boarding_point,
        combined.destination,
        combined.fare
    FROM (
        SELECT pt.boarding_point, pt.destination, pt.fare
        FROM passenger_trips pt
        $wherePT
        UNION ALL
        SELECT pth.boarding_point, pth.destination, pth.fare
        FROM passenger_trip_history pth
        INNER JOIN trip_operation_evidences toe ON toe.trip_id = pth.trip_id
        INNER JOIN drivers drv ON REPLACE(LOWER(TRIM(drv.full_name)), ' ', '') = REPLACE(LOWER(TRIM(toe.driver_name)), ' ', '')
        $wherePTH
    ) combined
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($paramsPT, $paramsPTH));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =====================================================
       IMPORTED RECORDS (PTH without toe)
    ===================================================== */

    $impConditions = ["toe.trip_id IS NULL"];
    $impParams = [];

    if ($bus !== '') {
        $impConditions[] = "LOWER(TRIM(pth.bus_number)) = LOWER(TRIM(:impBus))";
        $impParams[':impBus'] = $bus;
    }
    if ($day !== '') {
        $impConditions[] = "DAY(pth.trip_date) = :impDay";
        $impParams[':impDay'] = (int)$day;
    }
    if ($month !== '') {
        $impConditions[] = "MONTH(pth.trip_date) = :impMonth";
        $impParams[':impMonth'] = (int)$month;
    }
    if ($year !== '') {
        $impConditions[] = "YEAR(pth.trip_date) = :impYear";
        $impParams[':impYear'] = (int)$year;
    }
    if ($fromDate !== '') {
        $impConditions[] = "DATE(pth.trip_date) >= :impFrom";
        $impParams[':impFrom'] = $fromDate;
    }
    if ($toDate !== '') {
        $impConditions[] = "DATE(pth.trip_date) <= :impTo";
        $impParams[':impTo'] = $toDate;
    }
    if ($fromTime !== '') {
        $impConditions[] = "TIME(pth.time_of_departure) >= :impFromTime";
        $impParams[':impFromTime'] = $fromTime;
    }
    if ($toTime !== '') {
        $impConditions[] = "TIME(pth.time_of_departure) <= :impToTime";
        $impParams[':impToTime'] = $toTime;
    }

    $impWhere = 'WHERE ' . implode(' AND ', $impConditions);

    $importedSQL = "
        SELECT pth.boarding_point, pth.destination, pth.fare
        FROM passenger_trip_history pth
        LEFT JOIN trip_operation_evidences toe ON toe.trip_id = pth.trip_id
        $impWhere
    ";
    $importedStmt = $db->prepare($importedSQL);
    $importedStmt->execute($impParams);
    $importedRows = $importedStmt->fetchAll(PDO::FETCH_ASSOC);

    /* merge all trips */
    $allTrips = array_merge($data, $importedRows);

    /* =====================================================
       AGGREGATE BY ROUTE NAME
    ===================================================== */

    $routes = []; /* route_name => [ passengers, revenue, trips ] */
    $other  = []; /* for unmatched routes */
    $unknownLabel = 'Other Routes';

    foreach ($allTrips as $t) {
        $rn = resolveRoute($t['boarding_point'], $t['destination'], $stopIndex);
        $fare = (float)($t['fare'] ?? 0);
        $bucket = $rn ?: $unknownLabel;

        $routes[$bucket]['passengers'] = ($routes[$bucket]['passengers'] ?? 0) + 1;
        $routes[$bucket]['revenue']    = ($routes[$bucket]['revenue']    ?? 0) + $fare;
        $routes[$bucket]['trips']      = ($routes[$bucket]['trips']      ?? 0) + 1;
    }

    /* format as array and compute derived fields */
    $result = [];
    foreach ($routes as $name => $agg) {
        $result[] = [
            'route'            => $name,
            'passengers'       => $agg['passengers'],
            'unique_passengers'=> $agg['passengers'],
            'trips'            => $agg['trips'],
            'revenue'          => round($agg['revenue'], 2),
            'revenue_per_trip' => round($agg['revenue'] / max($agg['trips'], 1), 2),
        ];
    }

    /* sort by revenue descending */
    usort($result, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

    /* keep top 5 */
    $result = array_slice($result, 0, 5);

    /* =====================================================
       RESPONSE
    ===================================================== */

    $response = [
        "success" => true,
        "filters" => [
            "bus"       => $bus,
            "day"       => $day,
            "month"     => $month,
            "year"      => $year,
            "fromDate"  => $fromDate,
            "toDate"    => $toDate,
            "fromTime"  => $fromTime,
            "toTime"    => $toTime,
        ],
        "count" => count($result),
        "data"  => $result,
    ];

    if (isset($_GET['debug'])) {
        /* count source distribution */
        $srcCount = ['from_main_query' => count($data), 'from_imported' => count($importedRows)];
        $matched = 0; $unmatched = 0;
        $matchedSamples = [];
        foreach ($allTrips as $t) {
            $rn = resolveRoute($t['boarding_point'], $t['destination'], $stopIndex);
            if ($rn !== null) {
                $matched++;
                if (count($matchedSamples) < 10) {
                    $matchedSamples[] = [
                        'boarding_point' => $t['boarding_point'],
                        'destination'    => $t['destination'],
                        'resolved_to'    => $rn,
                    ];
                }
            } else {
                $unmatched++;
            }
        }
        $response['_debug'] = [
            'fare_pricing_stops' => $routeMap,
            'source_counts' => $srcCount,
            'matched_count' => $matched,
            'unmatched_count' => $unmatched,
            'matched_samples' => $matchedSamples,
        ];
    }

    if ($cacheKey !== null && function_exists('dashboard_cache_save')) {
        dashboard_cache_save($cacheKey, $response);
    }

    echo json_encode($response);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage(),
        "line"    => $e->getLine(),
        "file"    => $e->getFile(),
    ]);
}
