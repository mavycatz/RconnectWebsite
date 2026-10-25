<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/cache.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/Database.php';

use App\Core\Database;

header('Content-Type: application/json');

try {

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

    $cacheKey = dashboard_cache_key('trip_analytics', [
        'bus'      => $bus,
        'day'      => $day,
        'month'    => $month,
        'year'     => $year,
        'fromDate' => $fromDate,
        'toDate'   => $toDate,
        'fromTime' => $fromTime,
        'toTime'   => $toTime,
    ]);

    $cached = dashboard_cache_get($cacheKey, 45);
    if ($cached !== null) {
        echo json_encode($cached);
        exit;
    }

    $db = Database::get();

    $conditionsPT  = [];
    $conditionsPTH = [];

    $params = [];

    /*
    |--------------------------------------------------------------------------
    | LIVE PASSENGER TRIPS FILTERS
    |--------------------------------------------------------------------------
    */

    if ($bus !== '') {
        $conditionsPT[] = "LOWER(TRIM(pt.bus_no)) = LOWER(TRIM(:busPT))";
        $params[':busPT'] = $bus;
    }

    if ($day !== '') {
        $conditionsPT[] = "DAY(pt.boarding_time) = :dayPT";
        $params[':dayPT'] = (int)$day;
    }

    if ($month !== '') {
        $conditionsPT[] = "MONTH(pt.boarding_time) = :monthPT";
        $params[':monthPT'] = (int)$month;
    }

    if ($year !== '') {
        $conditionsPT[] = "YEAR(pt.boarding_time) = :yearPT";
        $params[':yearPT'] = (int)$year;
    }

    if ($fromDate !== '') {
        $conditionsPT[] = "DATE(pt.boarding_time) >= :fromDatePT";
        $params[':fromDatePT'] = $fromDate;
    }

    if ($toDate !== '') {
        $conditionsPT[] = "DATE(pt.boarding_time) <= :toDatePT";
        $params[':toDatePT'] = $toDate;
    }

    if ($fromTime !== '') {
        $conditionsPT[] = "TIME(pt.boarding_time) >= :fromTimePT";
        $params[':fromTimePT'] = $fromTime;
    }

    if ($toTime !== '') {
        $conditionsPT[] = "TIME(pt.boarding_time) <= :toTimePT";
        $params[':toTimePT'] = $toTime;
    }

    /*
    |--------------------------------------------------------------------------
    | ARCHIVED PASSENGER HISTORY FILTERS
    |--------------------------------------------------------------------------
    */

    if ($bus !== '') {
        $conditionsPTH[] = "LOWER(TRIM(pth.bus_number)) = LOWER(TRIM(:busPTH))";
        $params[':busPTH'] = $bus;
    }

    if ($day !== '') {
        $conditionsPTH[] = "DAY(pth.trip_date) = :dayPTH";
        $params[':dayPTH'] = (int)$day;
    }

    if ($month !== '') {
        $conditionsPTH[] = "MONTH(pth.trip_date) = :monthPTH";
        $params[':monthPTH'] = (int)$month;
    }

    if ($year !== '') {
        $conditionsPTH[] = "YEAR(pth.trip_date) = :yearPTH";
        $params[':yearPTH'] = (int)$year;
    }

    if ($fromDate !== '') {
        $conditionsPTH[] = "DATE(pth.trip_date) >= :fromDatePTH";
        $params[':fromDatePTH'] = $fromDate;
    }

    if ($toDate !== '') {
        $conditionsPTH[] = "DATE(pth.trip_date) <= :toDatePTH";
        $params[':toDatePTH'] = $toDate;
    }

    $wherePT = '';
    $wherePTH = '';

    if (!empty($conditionsPT)) {

        $wherePT =

            'WHERE '

            . implode(
                ' AND ',
                $conditionsPT
            );
    }
    if (!empty($conditionsPTH)) {

        $wherePTH =

            'WHERE '

            . implode(

                ' AND ',
                $conditionsPTH
            );
    }

    /* =====================================================
       COMBINED TRIPS
    ===================================================== */

    $sql = "

    SELECT

        combined.trip_date,

        COUNT(DISTINCT combined.trip_key) AS trips

    FROM (

        SELECT

            DATE(pt.boarding_time) AS trip_date,

            CONCAT('l-', COALESCE(pt.trip_no, CONCAT(COALESCE(pt.bus_no,'X'), DATE(pt.boarding_time)), pt.id)) AS trip_key

        FROM passenger_trips pt

        $wherePT

        UNION ALL

        SELECT

            DATE(pth.trip_date) AS trip_date,

            CONCAT('h-', COALESCE(pth.trip_id, pth.history_id)) AS trip_key

        FROM passenger_trip_history pth

        $wherePTH

    ) combined

    GROUP BY combined.trip_date

    ORDER BY combined.trip_date ASC

    ";

    $stmt =
        $db->prepare($sql);

    $stmt->execute($params);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $final = [];

    foreach ($rows as $r) {
        $final[] = [
            "trip_date" => $r['trip_date'],
            "trips" => (int)$r['trips'],
            "is_predicted" => false
        ];
    }

    /* =====================================================
       PASSENGER SUMMARY
    ===================================================== */

    $sqlSummary = "

    SELECT

        /* =========================================
           PAYMENT METHODS
        ========================================= */

        SUM(

            CASE

                WHEN combined.payment_method
                    = 'Cash'

                THEN 1

                ELSE 0

            END

        ) AS cash,

        SUM(

            CASE

                WHEN combined.payment_method
                    = 'GCash'

                THEN 1

                ELSE 0

            END

        ) AS gcash,

        SUM(

            CASE

                WHEN combined.payment_method
                    = 'Hybrid'

                THEN 1

                ELSE 0

            END

        ) AS hybrid,

        /* =========================================
           PASSENGER TYPES
        ========================================= */

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'Regular'

                OR combined.passenger_type
                    IS NULL

                OR combined.passenger_type
                    = ''

                THEN 1

                ELSE 0

            END

        ) AS regular,

        SUM(

            CASE

                WHEN combined.passenger_type
                    != 'Regular'

                AND combined.passenger_type
                    IS NOT NULL

                AND combined.passenger_type
                    != ''

                THEN 1

                ELSE 0

            END

        ) AS special,

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'Student'

                THEN 1

                ELSE 0

            END

        ) AS students,

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'Senior'

                THEN 1

                ELSE 0

            END

        ) AS seniors,

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'PWD'

                THEN 1

                ELSE 0

            END

        ) AS pwd,

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'Nurse'

                THEN 1

                ELSE 0

            END

        ) AS nurses,

        SUM(

            CASE

                WHEN combined.passenger_type
                    = 'Teacher'

                THEN 1

                ELSE 0

            END

        ) AS teachers,

        /* =========================================
           TRIP TYPES
        ========================================= */

        COUNT(

            DISTINCT CASE

                WHEN combined.trip_source
                    = 'Regular'

                THEN combined.trip_record

            END

        ) AS regular_trips,

        COUNT(

            DISTINCT CASE

                WHEN combined.trip_source
                    = 'Special'

                THEN combined.trip_record

            END

        ) AS special_trips,

        COUNT(

            DISTINCT CASE

                WHEN combined.trip_source
                    = 'Archived'

                THEN combined.trip_record

            END

        ) AS archived_trips

    FROM (

        /* =========================================
           LIVE PASSENGERS
        ========================================= */

        SELECT

            pt.id AS trip_record,

            pt.payment_method,

            COALESCE(
                p.discount_type,
                'Regular'
            ) AS passenger_type,

            CASE

                WHEN tor.trip_group_code
                    IS NOT NULL

                THEN 'Special'

                ELSE 'Regular'

            END AS trip_source

        FROM passenger_trips pt

        LEFT JOIN passengers p

            ON p.id = pt.passenger_id

        LEFT JOIN trip_operations_reports tor

            ON LOWER(TRIM(tor.bus_id))

            = LOWER(TRIM(pt.bus_no))

            AND DATE(tor.trip_date)

            = DATE(pt.boarding_time)

        $wherePT

        UNION ALL

        /* =========================================
           ARCHIVED PASSENGERS
        ========================================= */

        SELECT

            pth.history_id AS trip_record,

            COALESCE(NULLIF(TRIM(pth.payment_method), ''), 'Cash') AS payment_method,

            COALESCE(
                NULLIF(TRIM(pth.passenger_type), ''),
                'Regular'
            ) AS passenger_type,

            'Archived' AS trip_source

        FROM passenger_trip_history pth

        $wherePTH

    ) combined

    ";

    $stmt =
        $db->prepare($sqlSummary);

    $stmt->execute($params);

    $summary =
        $stmt->fetch(PDO::FETCH_ASSOC);

    /* =====================================================
       RESPONSE
    ===================================================== */

    $response = [

        "success" => true,

        "trips" => $final,

        "passenger_summary" => [

            "cash" =>

                (int)($summary['cash'] ?? 0),

            "gcash" =>

                (int)($summary['gcash'] ?? 0),

            "hybrid" =>

                (int)($summary['hybrid'] ?? 0),

            "regular" =>

                (int)($summary['regular'] ?? 0),

            "special" =>

                (int)($summary['special'] ?? 0),

            "students" =>

                (int)($summary['students'] ?? 0),

            "seniors" =>

                (int)($summary['seniors'] ?? 0),

            "pwd" =>

                (int)($summary['pwd'] ?? 0),

            "nurses" =>

                (int)($summary['nurses'] ?? 0),

            "teachers" =>

                (int)($summary['teachers'] ?? 0),

            "regular_trips" =>

                (int)($summary['regular_trips'] ?? 0),

            "special_trips" =>

                (int)($summary['special_trips'] ?? 0),

            "archived_trips" =>

                (int)($summary['archived_trips'] ?? 0)

        ]

    ];

    if ($cacheKey !== null && function_exists('dashboard_cache_save')) {
        dashboard_cache_save($cacheKey, $response, 45);
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