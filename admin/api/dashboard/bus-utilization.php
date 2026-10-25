<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

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
$driver   = trim($_GET['driver'] ?? '');
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

    $cacheKey = dashboard_cache_key('busUtil', [
        'bus'      => $bus,
        'driver'   => $driver,
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
   WHERE — passenger_trips
===================================================== */

$ptWhere = [];
$ptParams = [];

$ptWhere[] = "pt.bus_no IS NOT NULL";
$ptWhere[] = "TRIM(pt.bus_no) != ''";

if($bus !== ''){
    $ptWhere[] = "pt.bus_no = :pt_bus";
    $ptParams[':pt_bus'] = $bus;
}

if($driver !== ''){
    $ptWhere[] = "d.full_name LIKE :pt_driver";
    $ptParams[':pt_driver'] = "%$driver%";
}

if($day !== ''){
    $ptWhere[] = "DAY(pt.boarding_time) = :pt_day";
    $ptParams[':pt_day'] = (int)$day;
}

if($month !== ''){
    $ptWhere[] = "MONTH(pt.boarding_time) = :pt_month";
    $ptParams[':pt_month'] = (int)$month;
}

if($year !== ''){
    $ptWhere[] = "YEAR(pt.boarding_time) = :pt_year";
    $ptParams[':pt_year'] = (int)$year;
}

if($fromDate !== ''){
    $ptWhere[] = "DATE(pt.boarding_time) >= :pt_fromDate";
    $ptParams[':pt_fromDate'] = $fromDate;
}

if($toDate !== ''){
    $ptWhere[] = "DATE(pt.boarding_time) <= :pt_toDate";
    $ptParams[':pt_toDate'] = $toDate;
}

if($fromTime !== ''){
    $ptWhere[] = "TIME(pt.boarding_time) >= :pt_fromTime";
    $ptParams[':pt_fromTime'] = $fromTime;
}

if($toTime !== ''){
    $ptWhere[] = "TIME(pt.boarding_time) <= :pt_toTime";
    $ptParams[':pt_toTime'] = $toTime;
}

$ptWhereSQL =
    count($ptWhere)
    ? "WHERE ".implode(" AND ", $ptWhere)
    : "";

/* =====================================================
   WHERE — passenger_trip_history
===================================================== */

$pthWhere = [];
$pthParams = [];

$pthWhere[] = "pth.bus_number IS NOT NULL";
$pthWhere[] = "TRIM(pth.bus_number) != ''";

if($bus !== ''){
    $pthWhere[] = "pth.bus_number = :pth_bus";
    $pthParams[':pth_bus'] = $bus;
}

if($driver !== ''){
    $pthWhere[] = "pth.drivers_full_name LIKE :pth_driver";
    $pthParams[':pth_driver'] = "%$driver%";
}

if($day !== ''){
    $pthWhere[] = "DAY(pth.trip_date) = :pth_day";
    $pthParams[':pth_day'] = (int)$day;
}

if($month !== ''){
    $pthWhere[] = "MONTH(pth.trip_date) = :pth_month";
    $pthParams[':pth_month'] = (int)$month;
}

if($year !== ''){
    $pthWhere[] = "YEAR(pth.trip_date) = :pth_year";
    $pthParams[':pth_year'] = (int)$year;
}

if($fromDate !== ''){
    $pthWhere[] = "DATE(pth.trip_date) >= :pth_fromDate";
    $pthParams[':pth_fromDate'] = $fromDate;
}

if($toDate !== ''){
    $pthWhere[] = "DATE(pth.trip_date) <= :pth_toDate";
    $pthParams[':pth_toDate'] = $toDate;
}

/* No time filter on pth (no time column) */

$pthWhereSQL =
    count($pthWhere)
    ? "WHERE ".implode(" AND ", $pthWhere)
    : "";

/* =====================================================
    QUERY — combine both sources via UNION ALL
===================================================== */

$sql = "

SELECT
    bus_id,
    SUM(passengers) AS passengers,
    SUM(unique_passengers) AS unique_passengers,
    SUM(trips) AS trips,
    ROUND(SUM(revenue), 2) AS revenue,
    ROUND(
        CASE
            WHEN SUM(trips) = 0 THEN 0
            ELSE SUM(revenue) / SUM(trips)
        END,
        2
    ) AS revenue_per_trip
FROM (

    /* ---- passenger_trips ---- */

    SELECT
        pt.bus_no AS bus_id,
        COUNT(pt.id) AS passengers,
        COUNT(DISTINCT pt.passenger_id) AS unique_passengers,
        COUNT(DISTINCT pt.trip_no) AS trips,
        COALESCE(SUM(pt.fare), 0) AS revenue
    FROM passenger_trips pt
    LEFT JOIN buses b ON b.bus_id = pt.bus_no
    LEFT JOIN driver_bus_assignment dba ON dba.bus_id = b.bus_id
    LEFT JOIN drivers d ON d.driver_id = dba.driver_id
    $ptWhereSQL
    GROUP BY pt.bus_no

    UNION ALL

    /* ---- passenger_trip_history ---- */

    SELECT
        pth.bus_number AS bus_id,
        COUNT(pth.history_id) AS passengers,
        COUNT(DISTINCT pth.passenger_name) AS unique_passengers,
        COUNT(DISTINCT pth.trip_id) AS trips,
        COALESCE(SUM(pth.fare), 0) AS revenue
    FROM passenger_trip_history pth
    $pthWhereSQL
    GROUP BY pth.bus_number

) combined

WHERE bus_id IS NOT NULL AND bus_id != ''

GROUP BY bus_id

ORDER BY
    passengers DESC,
    revenue DESC

LIMIT 5

";

/* =====================================================
   EXECUTE
===================================================== */

$allParams = array_merge($ptParams, $pthParams);
$stmt = $db->prepare($sql);
$stmt->execute($allParams);

$data =
$stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   CLEAN TYPES
===================================================== */

foreach($data as &$row){

    $row['passengers'] =
        (int)$row['passengers'];

    $row['unique_passengers'] =
        (int)$row['unique_passengers'];

    $row['trips'] =
        (int)$row['trips'];

    $row['revenue'] =
        (float)$row['revenue'];

    $row['revenue_per_trip'] =
        (float)$row['revenue_per_trip'];
}

/* =====================================================
   RESPONSE
===================================================== */

$response = [

    "success" => true,

    "filters" => [

        "bus" => $bus,
        "driver" => $driver,
        "day" => $day,
        "month" => $month,
        "year" => $year,
        "fromDate" => $fromDate,
        "toDate" => $toDate,
        "fromTime" => $fromTime,
        "toTime" => $toTime

    ],

    "count" => count($data),

    "data" => $data

];

if ($cacheKey !== null && function_exists('dashboard_cache_save')) {
    dashboard_cache_save($cacheKey, $response);
}

echo json_encode($response);

} catch(Throwable $e){

echo json_encode([

    "success" => false,

    "error" => $e->getMessage(),

    "line" => $e->getLine(),

    "file" => $e->getFile()
]);

}
