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

        $cacheKey = dashboard_cache_key('pickup', [
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
       PASSENGER TRIPS FILTERS
    ===================================================== */

    $conditionsPT = [];
    $paramsPT = [];

    /* BUS */

    if ($bus !== '') {

        $conditionsPT[] =

            "LOWER(TRIM(pt.bus_no))
            = LOWER(TRIM(:busPT))";

        $paramsPT[':busPT'] =
            $bus;
    }

    /* DAY */

    if ($day !== '') {

        $conditionsPT[] =

            "DAY(pt.boarding_time)
            = :dayPT";

        $paramsPT[':dayPT'] =
            (int)$day;
    }

    /* MONTH */

    if ($month !== '') {

        $conditionsPT[] =

            "MONTH(pt.boarding_time)
            = :monthPT";

        $paramsPT[':monthPT'] =
            (int)$month;
    }

    /* YEAR */

    if ($year !== '') {

        $conditionsPT[] =

            "YEAR(pt.boarding_time)
            = :yearPT";

        $paramsPT[':yearPT'] =
            (int)$year;
    }

    /* DATE RANGE */

    if ($fromDate !== '') {

        $conditionsPT[] =

            "DATE(pt.boarding_time)
            >= :fromDatePT";

        $paramsPT[':fromDatePT'] =
            $fromDate;
    }

    if ($toDate !== '') {

        $conditionsPT[] =

            "DATE(pt.boarding_time)
            <= :toDatePT";

        $paramsPT[':toDatePT'] =
            $toDate;
    }

    /* TIME RANGE */

    if ($fromTime !== '') {

        $conditionsPT[] =

            "TIME(pt.time_of_departure)
            >= :fromTimePT";

        $paramsPT[':fromTimePT'] =
            $fromTime;
    }

    if ($toTime !== '') {

        $conditionsPT[] =

            "TIME(pt.time_of_departure)
            <= :toTimePT";

        $paramsPT[':toTimePT'] =
            $toTime;
    }

    /* VALID PICKUPS */

    $conditionsPT[] =

        "pt.boarding_point
        IS NOT NULL";

    $conditionsPT[] =

        "TRIM(pt.boarding_point)
        != ''";

    $wherePT =

        count($conditionsPT)

        ? 'WHERE '
            . implode(
                ' AND ',
                $conditionsPT
            )

        : '';

    /* =====================================================
       ARCHIVED HISTORY FILTERS
    ===================================================== */

    $conditionsPTH = [];
    $paramsPTH = [];

    /* DAY */

    if ($day !== '') {

        $conditionsPTH[] =

            "DAY(pth.trip_date)
            = :dayPTH";

        $paramsPTH[':dayPTH'] =
            (int)$day;
    }

    /* MONTH */

    if ($month !== '') {

        $conditionsPTH[] =

            "MONTH(pth.trip_date)
            = :monthPTH";

        $paramsPTH[':monthPTH'] =
            (int)$month;
    }

    /* YEAR */

    if ($year !== '') {

        $conditionsPTH[] =

            "YEAR(pth.trip_date)
            = :yearPTH";

        $paramsPTH[':yearPTH'] =
            (int)$year;
    }

    /* DATE RANGE */

    if ($fromDate !== '') {

        $conditionsPTH[] =

            "DATE(pth.trip_date)
            >= :fromDatePTH";

        $paramsPTH[':fromDatePTH'] =
            $fromDate;
    }

    if ($toDate !== '') {

        $conditionsPTH[] =

            "DATE(pth.trip_date)
            <= :toDatePTH";

        $paramsPTH[':toDatePTH'] =
            $toDate;
    }

    /* TIME RANGE */

    if ($fromTime !== '') {
        $conditionsPTH[] = "TIME(pth.time_of_departure) >= :fromTimePTH";
        $paramsPTH[':fromTimePTH'] = $fromTime;
    }

    if ($toTime !== '') {
        $conditionsPTH[] = "TIME(pth.time_of_departure) <= :toTimePTH";
        $paramsPTH[':toTimePTH'] = $toTime;
    }

    /* VALID PICKUPS */

    $conditionsPTH[] =

        "pth.boarding_point
        IS NOT NULL";

    $conditionsPTH[] =

        "TRIM(pth.boarding_point)
        != ''";

        $wherePTH =

            count($conditionsPTH)

            ? 'WHERE '
                . implode(
                    ' AND ',
                    $conditionsPTH
                )

            : '';

    /* =====================================================
       MERGED QUERY
    ===================================================== */

    $sql = "

    SELECT

        combined.location,

        COUNT(
            combined.trip_record
        ) AS passengers,

        COUNT(
            DISTINCT combined.passenger_identifier
        ) AS unique_passengers,

        ROUND(

            COALESCE(
                SUM(combined.fare),
                0
            ),

            2

        ) AS revenue

    FROM (

        /* =========================================
           LIVE PASSENGERS
        ========================================= */

        SELECT

            pt.boarding_point
                AS location,

            pt.id
                AS trip_record,

            CONCAT(
                'LIVE-',
                pt.passenger_id
            ) AS passenger_identifier,

            pt.fare

        FROM passenger_trips pt

        $wherePT

        UNION ALL

        /* =========================================
           ARCHIVED PASSENGERS
        ========================================= */

        SELECT

            pth.boarding_point
                AS location,

            pth.history_id
                AS trip_record,

            CONCAT(

                'ARCHIVE-',

                pth.passenger_name,

                '-',

                pth.history_id

            ) AS passenger_identifier,

            pth.fare

        FROM passenger_trip_history pth

        INNER JOIN trip_operation_evidences toe

            ON toe.trip_id = pth.trip_id

        INNER JOIN drivers drv

            ON REPLACE(

                LOWER(TRIM(drv.full_name)),

                ' ',

                ''

            )

            =

            REPLACE(

                LOWER(TRIM(toe.driver_name)),

                ' ',

                ''

            )

        $wherePTH

    ) combined

    GROUP BY combined.location

    ORDER BY

        passengers DESC,

        revenue DESC

    LIMIT 5

    ";

    /* =====================================================
       MERGE PARAMS
    ===================================================== */

    $params = array_merge(
        $paramsPT,
        $paramsPTH
    );

    /* =====================================================
       EXECUTE MAIN
    ===================================================== */

    $stmt =
        $db->prepare($sql);

    $stmt->execute($params);

    $data =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =====================================================
       IMPORTED RECORDS
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

    $impWhere = 'WHERE ' . implode(' AND ', $impConditions);

    $importedSQL = "
        SELECT
            pth.boarding_point AS location,
            COUNT(*) AS passengers,
            COALESCE(SUM(pth.fare), 0) AS revenue
        FROM passenger_trip_history pth
        LEFT JOIN trip_operation_evidences toe ON toe.trip_id = pth.trip_id
        $impWhere
        GROUP BY pth.boarding_point
        ORDER BY passengers DESC
        LIMIT 5
    ";
    $importedStmt = $db->prepare($importedSQL);
    $importedStmt->execute($impParams);
    $importedRows = $importedStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($importedRows as $ir) {
        $found = false;
        foreach ($data as &$row) {
            if ($row['location'] === $ir['location']) {
                $row['passengers'] += (int)$ir['passengers'];
                $row['revenue'] += (float)$ir['revenue'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $data[] = $ir;
        }
    }

    /* =====================================================
       CLEAN TYPES
    ===================================================== */

    foreach ($data as &$row) {

        $row['passengers'] =

            (int)$row['passengers'];

        $row['unique_passengers'] =

            (int)$row['unique_passengers'];

        $row['revenue'] =

            (float)$row['revenue'];
    }

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
            "toTime"    => $toTime

        ],

        "count" =>
            count($data),

        "data" =>
            $data

    ];

    if ($cacheKey !== null && function_exists('dashboard_cache_save')) {
        dashboard_cache_save($cacheKey, $response);
    }

    echo json_encode($response);

} catch (Throwable $e) {

    echo json_encode([

        "success" => false,

        "error" => $e->getMessage(),

        "line" => $e->getLine(),

        "file" => $e->getFile()

    ]);
}