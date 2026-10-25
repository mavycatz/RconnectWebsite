<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;

header('Content-Type: application/json');

try{

$db = Database::get();

$driver_id = trim($_GET['driver_id'] ?? '');

if(!$driver_id){
    echo json_encode(["success"=>false,"message"=>"Driver ID missing"]);
    exit;
}

/* =====================================================
   DATE FILTERS (same selected period as the dashboard)
   Legacy fromDate/toDate, month/year, or the canonical
   period/from/to selector all resolve to one window.
===================================================== */

$month    = trim((string)($_GET['month'] ?? ''));
$year     = trim((string)($_GET['year'] ?? ''));
$fromDate = trim((string)($_GET['fromDate'] ?? ''));
$toDate   = trim((string)($_GET['toDate'] ?? ''));

if ($fromDate === '' && $toDate === '' && $month === '' && $year === '') {
    $period = trim((string)($_GET['period'] ?? ''));
    if ($period !== '') {
        $servicePath = $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DateFilterService.php';
        if (is_file($servicePath)) {
            require_once $servicePath;
            $resolved = \Finance\Services\DateFilterService::normalizeRequest($_GET);
            $fromDate = $resolved['start'];
            $toDate   = $resolved['end'];
        }
    }
}

/* Date conditions are built per query branch: native prepares reject the
   same named placeholder appearing twice in one statement. */
$buildDateConds = static function (string $prefix) use ($month, $year, $fromDate, $toDate): array {
    $conds = [];
    $params = [];
    if ($fromDate !== '') {
        $conds[] = 'DATE(%DATE%) >= :' . $prefix . '_from';
        $params[':' . $prefix . '_from'] = $fromDate;
    }
    if ($toDate !== '') {
        $conds[] = 'DATE(%DATE%) <= :' . $prefix . '_to';
        $params[':' . $prefix . '_to'] = $toDate;
    }
    if ($month !== '' && $fromDate === '') {
        $conds[] = 'MONTH(%DATE%) = :' . $prefix . '_month';
        $params[':' . $prefix . '_month'] = $month;
    }
    if ($year !== '' && $fromDate === '') {
        $conds[] = 'YEAR(%DATE%) = :' . $prefix . '_year';
        $params[':' . $prefix . '_year'] = $year;
    }
    return [$conds, $params];
};

$renderDateSQL = static function (string $column, array $conds): string {
    if (!$conds) {
        return '';
    }
    return ' AND ' . str_replace('%DATE%', $column, implode(' AND ', $conds));
};

[$dailyLiveConds, $dailyLiveParams] = $buildDateConds('dlive');
[$dailyHistConds, $dailyHistParams] = $buildDateConds('dhist');
[$passLiveConds, $passLiveParams]   = $buildDateConds('plive');
[$passHistConds, $passHistParams]   = $buildDateConds('phist');

/* =====================================================
   DRIVER INFO (column names vary by deployment)
===================================================== */

$driverColumns = [];
try {
    foreach ($db->query('SHOW COLUMNS FROM drivers') as $columnRow) {
        $driverColumns[strtolower((string)$columnRow['Field'])] = (string)$columnRow['Field'];
    }
} catch (\Throwable $e) {
    $driverColumns = [];
}

$driverNameColumn = null;
foreach (['full_name', 'name', 'driver_name'] as $candidate) {
    if (isset($driverColumns[$candidate])) { $driverNameColumn = $driverColumns[$candidate]; break; }
}
$driverPhotoColumn = null;
foreach (['photo', 'photo_url', 'profile_photo', 'image'] as $candidate) {
    if (isset($driverColumns[$candidate])) { $driverPhotoColumn = $driverColumns[$candidate]; break; }
}
$driverNameExpr = $driverNameColumn !== null ? '`' . $driverNameColumn . '`' : "''";
$driverPhotoExpr = $driverPhotoColumn !== null ? '`' . $driverPhotoColumn . '`' : "''";

$stmtDriver = $db->prepare("SELECT driver_id, $driverNameExpr AS full_name, $driverPhotoExpr AS photo FROM drivers WHERE driver_id = ? LIMIT 1");
$stmtDriver->execute([$driver_id]);
$driver = $stmtDriver->fetch(PDO::FETCH_ASSOC);

if(!$driver){
    echo json_encode(["success"=>false,"message"=>"Driver not found"]);
    exit;
}

$driverHistoryNameExpr = $driverNameColumn !== null ? 'd.`' . $driverNameColumn . '`' : "''";

/* =====================================================
   DRIVER BUSES (for live trip matching)
===================================================== */

$stmtBus = $db->prepare("SELECT bus_id FROM driver_bus_assignment WHERE driver_id = ?");
$stmtBus->execute([$driver_id]);
$buses = $stmtBus->fetchAll(PDO::FETCH_COLUMN);

/* The live table's identifier columns vary by deployment (bus_no vs bus_id;
   driver_id may be absent on older schemas). Resolve them once so the live
   branch always matches the driver's own rows and their assigned buses. */
$ptColumns = [];
foreach ($db->query('SHOW COLUMNS FROM passenger_trips') as $columnRow) {
    $ptColumns[strtolower((string)$columnRow['Field'])] = (string)$columnRow['Field'];
}
$ptDriverColumn = $ptColumns['driver_id'] ?? null;
$ptBusColumn = $ptColumns['bus_no'] ?? $ptColumns['bus_id'] ?? null;

$buildLiveIdentity = static function (string $prefix, array $buses, ?string $driverColumn, ?string $busColumn) use ($driver_id): array {
    $parts = [];
    $params = [];
    if ($driverColumn !== null) {
        $parts[] = 'pt.`' . $driverColumn . '` = :' . $prefix . '_driver';
        $params[':' . $prefix . '_driver'] = $driver_id;
    }
    if ($busColumn !== null && !empty($buses)) {
        $keys = [];
        foreach (array_values($buses) as $index => $bus) {
            $key = ':' . $prefix . '_bus_' . $index;
            $keys[] = $key;
            $params[$key] = $bus;
        }
        $parts[] = 'pt.`' . $busColumn . '` IN (' . implode(',', $keys) . ')';
    }
    return [$parts, $params];
};

[$dailyIdentityParts, $dailyIdentityParams] = $buildLiveIdentity('dlive', $buses, $ptDriverColumn, $ptBusColumn);
[$passIdentityParts, $passIdentityParams] = $buildLiveIdentity('plive', $buses, $ptDriverColumn, $ptBusColumn);

/* =====================================================
   DAILY ANALYTICS (live + archived combined, period-scoped)
===================================================== */

$dailyParams = array_merge($dailyLiveParams, $dailyIdentityParams);

if (!$dailyIdentityParts) {
    $liveDailySQL = "
    SELECT
        DATE(pt.boarding_time) AS day,
        COUNT(DISTINCT pt.trip_no) AS trips,
        COALESCE(SUM(pt.fare),0) AS earnings
    FROM passenger_trips pt
    WHERE 1=0
    GROUP BY DATE(pt.boarding_time)
";
    $dailyParams = [];
} else {
    $liveDailySQL = "
    SELECT
        DATE(pt.boarding_time) AS day,
        COUNT(DISTINCT pt.trip_no) AS trips,
        COALESCE(SUM(pt.fare),0) AS earnings
    FROM passenger_trips pt
    WHERE 1=1" . $renderDateSQL('pt.boarding_time', $dailyLiveConds) . "
      AND (" . implode(' OR ', $dailyIdentityParts) . ")
    GROUP BY DATE(pt.boarding_time)
";
}

$dailyParams[':daily_driver'] = $driver_id;
$dailyParams = array_merge($dailyParams, $dailyHistParams);

$dailySQL = "
SELECT day, SUM(trips) AS trips, SUM(earnings) AS earnings
FROM (
" . $liveDailySQL . "

    UNION ALL

    SELECT
        DATE(pth.trip_date) AS day,
        COUNT(DISTINCT pth.trip_id) AS trips,
        COALESCE(SUM(pth.fare),0) AS earnings
    FROM passenger_trip_history pth
        INNER JOIN drivers d
        ON REPLACE(LOWER(TRIM(pth.drivers_full_name)), ' ', '')
        = REPLACE(LOWER(TRIM($driverHistoryNameExpr)), ' ', '')
    WHERE d.driver_id = :daily_driver" . $renderDateSQL('pth.trip_date', $dailyHistConds) . "
    GROUP BY DATE(pth.trip_date)

) combined
GROUP BY day
ORDER BY day ASC
";

$stmtDaily = $db->prepare($dailySQL);
$stmtDaily->execute($dailyParams);
$daily = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$tripCounts = [];
$earnings = [];

foreach($daily as $row){
    $labels[] = $row['day'];
    $tripCounts[] = (int)$row['trips'];
    $earnings[] = (float)$row['earnings'];
}

/* =====================================================
   PASSENGER ANALYTICS (live + archived combined, period-scoped)
===================================================== */

$passParams = array_merge($passLiveParams, $passIdentityParams);

/* Passenger classification columns also vary by deployment: production has
   discount_type, the live schema classifies through type (and cargo through
   type='other'). Fall back to the trip row's own passenger_type. */
$passengerColumns = [];
foreach ($db->query('SHOW COLUMNS FROM passengers') as $columnRow) {
    $passengerColumns[strtolower((string)$columnRow['Field'])] = (string)$columnRow['Field'];
}
$discountColumn = $passengerColumns['discount_type'] ?? $passengerColumns['type'] ?? null;
$typeColumn = $passengerColumns['type'] ?? $passengerColumns['discount_type'] ?? null;
$discountExpr = $discountColumn !== null ? 'p.`' . $discountColumn . '`' : 'pt.passenger_type';
$typeExpr = $typeColumn !== null ? 'p.`' . $typeColumn . '`' : 'pt.passenger_type';

$livePassSelect = "
    SELECT
        SUM(CASE WHEN $discountExpr IS NULL OR $discountExpr='' OR LOWER($discountExpr) IN ('regular','local') THEN 1 ELSE 0 END) AS regular_passengers,
        SUM(CASE WHEN $discountExpr IS NOT NULL AND $discountExpr!='' AND LOWER($discountExpr) NOT IN ('regular','local') THEN 1 ELSE 0 END) AS special_passengers,
        SUM(CASE WHEN LOWER($discountExpr)='student' THEN 1 ELSE 0 END) AS students,
        SUM(CASE WHEN LOWER($discountExpr) LIKE '%senior%' THEN 1 ELSE 0 END) AS seniors,
        SUM(CASE WHEN LOWER($discountExpr)='pwd' THEN 1 ELSE 0 END) AS pwd,
        SUM(CASE WHEN LOWER($discountExpr)='nurse' THEN 1 ELSE 0 END) AS nurses,
        SUM(CASE WHEN LOWER($discountExpr)='teacher' THEN 1 ELSE 0 END) AS teachers,
        SUM(CASE WHEN LOWER(COALESCE(pt.payment_method,''))='cash' THEN 1 ELSE 0 END) AS cash,
        SUM(CASE WHEN LOWER(COALESCE(pt.payment_method,''))='gcash' THEN 1 ELSE 0 END) AS gcash,
        SUM(CASE WHEN LOWER(COALESCE(pt.payment_method,''))='hybrid' THEN 1 ELSE 0 END) AS hybrid,
        SUM(CASE WHEN COALESCE($typeExpr,'') = 'other' THEN 1 ELSE 0 END) AS other_count,
        SUM(CASE WHEN COALESCE($typeExpr,'') != 'other' THEN 1 ELSE 0 END) AS passenger_count
    FROM passenger_trips pt
    LEFT JOIN passengers p ON p.id = pt.passenger_id
";

if (!$passIdentityParts) {
    $livePassSQL = "
    SELECT
        0 AS regular_passengers, 0 AS special_passengers, 0 AS students,
        0 AS seniors, 0 AS pwd, 0 AS nurses, 0 AS teachers,
        0 AS cash, 0 AS gcash, 0 AS hybrid, 0 AS other_count, 0 AS passenger_count
    FROM passenger_trips pt
    WHERE 1=0
";
    $passParams = [];
} else {
    $livePassSQL = $livePassSelect
        . "    WHERE 1=1" . $renderDateSQL('pt.boarding_time', $passLiveConds) . "
      AND (" . implode(' OR ', $passIdentityParts) . ")
";
}

$passParams[':pass_driver'] = $driver_id;
$passParams = array_merge($passParams, $passHistParams);

$passSQL = "
SELECT
    COALESCE(SUM(regular_passengers), 0) AS regular_passengers,
    COALESCE(SUM(special_passengers), 0) AS special_passengers,
    COALESCE(SUM(students), 0) AS students,
    COALESCE(SUM(seniors), 0) AS seniors,
    COALESCE(SUM(pwd), 0) AS pwd,
    COALESCE(SUM(nurses), 0) AS nurses,
    COALESCE(SUM(teachers), 0) AS teachers,
    COALESCE(SUM(cash), 0) AS cash,
    COALESCE(SUM(gcash), 0) AS gcash,
    COALESCE(SUM(hybrid), 0) AS hybrid,
    COALESCE(SUM(passenger_count), 0) AS passenger_count,
    COALESCE(SUM(other_count), 0) AS other_count
FROM (
" . $livePassSQL . "

    UNION ALL

    SELECT
        SUM(CASE WHEN COALESCE(pth.passenger_type,'') = '' OR LOWER(pth.passenger_type) IN ('regular','local') THEN 1 ELSE 0 END) AS regular_passengers,
        SUM(CASE WHEN COALESCE(pth.passenger_type,'') != '' AND LOWER(pth.passenger_type) NOT IN ('regular','local') THEN 1 ELSE 0 END) AS special_passengers,
        SUM(CASE WHEN LOWER(pth.passenger_type) = 'student' THEN 1 ELSE 0 END) AS students,
        SUM(CASE WHEN LOWER(pth.passenger_type) LIKE '%senior%' THEN 1 ELSE 0 END) AS seniors,
        SUM(CASE WHEN LOWER(pth.passenger_type) = 'pwd' THEN 1 ELSE 0 END) AS pwd,
        SUM(CASE WHEN LOWER(pth.passenger_type) = 'nurse' THEN 1 ELSE 0 END) AS nurses,
        SUM(CASE WHEN LOWER(pth.passenger_type) = 'teacher' THEN 1 ELSE 0 END) AS teachers,
        SUM(CASE WHEN LOWER(COALESCE(pth.payment_method,'')) = 'cash' THEN 1 ELSE 0 END) AS cash,
        SUM(CASE WHEN LOWER(COALESCE(pth.payment_method,'')) = 'gcash' THEN 1 ELSE 0 END) AS gcash,
        SUM(CASE WHEN LOWER(COALESCE(pth.payment_method,'')) = 'hybrid' THEN 1 ELSE 0 END) AS hybrid,
        SUM(CASE WHEN COALESCE(pth.type,'') = 'other' THEN 1 ELSE 0 END) AS other_count,
        SUM(CASE WHEN COALESCE(pth.type,'') != 'other' THEN 1 ELSE 0 END) AS passenger_count
    FROM passenger_trip_history pth
        INNER JOIN drivers d
        ON REPLACE(LOWER(TRIM(pth.drivers_full_name)), ' ', '')
        = REPLACE(LOWER(TRIM($driverHistoryNameExpr)), ' ', '')
    WHERE d.driver_id = :pass_driver" . $renderDateSQL('pth.trip_date', $passHistConds) . "

) combined
";

$stmtPass = $db->prepare($passSQL);
$stmtPass->execute($passParams);
$passengers = $stmtPass->fetch(PDO::FETCH_ASSOC);

/* =====================================================
   RESPONSE
===================================================== */

$photoUrl = $driver['photo'] ?? '';
if (!empty($photoUrl) && $photoUrl[0] !== '/') {
    $photoUrl = '/' . $photoUrl;
}

echo json_encode([
    "success"=>true,
    "driver_id"=>$driver_id,
    "name"=>$driver['full_name'] ?? 'Unknown Driver',
    "photo"=>$photoUrl,
    "daily_labels"=>$labels,
    "daily_trips"=>$tripCounts,
    "daily_earnings"=>$earnings,
    "regular_passengers"=>(int)($passengers['regular_passengers'] ?? 0),
    "special_passengers"=>(int)($passengers['special_passengers'] ?? 0),
    "passenger_count"=>(int)($passengers['passenger_count'] ?? 0),
    "other_count"=>(int)($passengers['other_count'] ?? 0),
    "students"=>(int)($passengers['students'] ?? 0),
    "seniors"=>(int)($passengers['seniors'] ?? 0),
    "pwd"=>(int)($passengers['pwd'] ?? 0),
    "nurses"=>(int)($passengers['nurses'] ?? 0),
    "teachers"=>(int)($passengers['teachers'] ?? 0),
    "cash"=>(int)($passengers['cash'] ?? 0),
    "gcash"=>(int)($passengers['gcash'] ?? 0),
    "hybrid"=>(int)($passengers['hybrid'] ?? 0)
]);

}catch(Throwable $e){
    echo json_encode([
        "success"=>false,
        "error"=>$e->getMessage(),
        "line"=>$e->getLine(),
        "file"=>$e->getFile()
    ]);
}
