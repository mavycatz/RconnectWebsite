<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/finance/services/DateFilterService.php';

use App\Core\Database;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

$db = Database::get();

/* -----------------------------------------
   FILTER BUILDER
----------------------------------------- */

function buildFiltersFromRange($range = null){
    $resolved = \Finance\Services\DateFilterService::normalizeRequest($_GET);

    return [
        'from'   => $resolved['start'],
        'to'     => $resolved['end'],
        'period' => $resolved['period'],
    ];
}

/* -----------------------------------------
   DATE FILTER SQL
----------------------------------------- */

function buildDateSQL($alias, $filters, &$params, $dateCol = 'trip_date'){

    $conditions = [];

    if(!empty($filters['from'])){
        $conditions[] = "DATE($alias.$dateCol)>=:from_date";
        $params[':from_date']=$filters['from'];
    }

    if(!empty($filters['to'])){
        $conditions[] = "DATE($alias.$dateCol)<=:to_date";
        $params[':to_date']=$filters['to'];
    }

    return $conditions ? " AND ".implode(" AND ",$conditions) : "";
}

/* -----------------------------------------
   🔥 CORE STATS (PASSENGER-BASED INCOME)
----------------------------------------- */

function computeStats($filters){

    $db = Database::get();

    $params = [];
    $dateSQL = buildDateSQL('pt',$filters,$params,'boarding_time');

    $sqlLive = "
    SELECT COALESCE(SUM(pt.fare),0) AS total
    FROM passenger_trips pt
    WHERE LOWER(TRIM(pt.payment_status)) = 'paid'
    $dateSQL
    ";
    $stmt = $db->prepare($sqlLive);
    $stmt->execute($params);
    $liveTotal = (float)($stmt->fetchColumn() ?? 0);

    $histParams = [];
    $dateSQLHist = buildDateSQL('pth',$filters,$histParams,'trip_date');
    $sqlHist = "
    SELECT COALESCE(SUM(pth.fare),0) AS total
    FROM passenger_trip_history pth
    WHERE 1=1
    $dateSQLHist
    ";
    $stmt = $db->prepare($sqlHist);
    $stmt->execute($histParams);
    $histTotal = (float)($stmt->fetchColumn() ?? 0);

    $grossIncome = $liveTotal + $histTotal;

    // ✅ EXPENSES
    $paramsExp = [];
    $dateSQLExp = buildDateSQL('bt',$filters,$paramsExp);
    $sqlExpenses = "
    SELECT SUM(e.amount) as total
    FROM expenses e
    LEFT JOIN bus_trips bt ON bt.id = e.trip_id
    WHERE 1=1
    $dateSQLExp
    ";

    $stmt = $db->prepare($sqlExpenses);
    $stmt->execute($paramsExp);
    $expenses = (float)($stmt->fetchColumn() ?? 0);

    return [
        "gross_income" => $grossIncome,
        "expenses"     => $expenses,
        "net_profit"   => $grossIncome - $expenses
    ];
}

try{

$range = $_GET['range'] ?? 'month';
$filters = buildFiltersFromRange($range);

/* =====================================================
   COST BREAKDOWN PER BUS
===================================================== */

$params = [];
$dateSQL = buildDateSQL('bt',$filters,$params);

$sqlCost = "
SELECT 
COALESCE(b.plate_number, bt.bus_id) AS bus_label,
bt.bus_id,
SUM(CASE WHEN e.category='fuel' THEN e.amount ELSE 0 END) AS fuel,
SUM(CASE WHEN e.category='toll' THEN e.amount ELSE 0 END) AS toll,
SUM(CASE WHEN e.category='maintenance' THEN e.amount ELSE 0 END) AS maintenance,
SUM(CASE 
    WHEN e.category NOT IN ('fuel','toll','maintenance') 
    THEN e.amount ELSE 0 END
) AS other

FROM bus_trips bt

LEFT JOIN expenses e 
    ON (e.trip_id = bt.id OR e.bus_id = bt.bus_id)

LEFT JOIN buses b
    ON b.bus_id = bt.bus_id

WHERE 1=1
$dateSQL

GROUP BY bt.bus_id
ORDER BY SUM(IFNULL(e.amount,0)) DESC
LIMIT 5
";

$stmt = $db->prepare($sqlCost);
$stmt->execute($params);

$buses = [];
$busIds = [];
$driverNames = [];
$fuel = [];
$toll = [];
$maintenance = [];
$other = [];

foreach($stmt as $row){
    $buses[] = $row['bus_label'];
    $busIds[] = $row['bus_id'];

    $drvStmt = $db->prepare("
        SELECT d.full_name
        FROM driver_bus_assignment dba
        JOIN drivers d ON d.driver_id = dba.driver_id
        WHERE dba.bus_id = ?
    ");
    $drvStmt->execute([$row['bus_id']]);
    $assigned = $drvStmt->fetchAll(PDO::FETCH_COLUMN);
    $driverNames[] = $assigned ? implode(', ', $assigned) : 'Unassigned';

    $fuel[] = (float)$row['fuel'];
    $toll[] = (float)$row['toll'];
    $maintenance[] = (float)$row['maintenance'];
    $other[] = (float)$row['other'];
}

/* =====================================================
   MONTHLY INCOME + NET
===================================================== */

$params = [];
$dateSQL = buildDateSQL('bt',$filters,$params);

$sqlMonths = "
SELECT 
DATE_FORMAT(bt.trip_date,'%Y-%m') as ym,
DATE_FORMAT(bt.trip_date,'%m') as month_num,
DATE_FORMAT(bt.trip_date,'%Y') as year,
DATE_FORMAT(bt.trip_date,'%b') as month
FROM bus_trips bt
WHERE 1=1
$dateSQL
GROUP BY ym
ORDER BY ym
";

$stmt = $db->prepare($sqlMonths);
$stmt->execute($params);

$months = [];
$income = [];
$expensesMonthly = [];
$net = [];

foreach($stmt as $row){

    $monthlyFilter = [
        'month' => $row['month_num'],
        'year'  => $row['year']
    ];

    $stats = computeStats($monthlyFilter);

    $months[] = $row['month'];
    $income[] = (float)$stats['gross_income'];
    $expensesMonthly[] = (float)$stats['expenses'];
    $net[] = (float)$stats['net_profit'];
}

/* =====================================================
   OUTPUT
===================================================== */

echo json_encode([
"success"=>true,
"cost_breakdown"=>[
    "buses"=>$buses,
    "bus_ids"=>$busIds,
    "driver_names"=>$driverNames,
    "fuel"=>$fuel,
    "toll"=>$toll,
    "maintenance"=>$maintenance,
    "other"=>$other
],
"monthly"=>[
    "months"=>$months,
    "income"=>$income,
    "expenses"=>$expensesMonthly,
    "net"=>$net
]
]);

}catch(Exception $e){

echo json_encode([
"success"=>false,
"message"=>$e->getMessage()
]);

}