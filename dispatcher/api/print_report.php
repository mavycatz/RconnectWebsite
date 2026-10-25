<?php
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);

$terminalFilter = "";
$types = "s";
$params = [$date];
if ($terminal_id > 0) {
    $terminalFilter = " AND (bt.from_terminal_id = ? OR bt.to_terminal_id = ?)";
    $types .= "ii";
    $params[] = $terminal_id;
    $params[] = $terminal_id;
}

$sql = "SELECT bt.*, d.full_name AS driver_name, b.plate_number
        FROM bus_trips bt
        LEFT JOIN drivers d ON d.driver_id = bt.driver_id
        LEFT JOIN buses b ON b.bus_id = bt.bus_id
        WHERE bt.trip_date = ? $terminalFilter
        ORDER BY bt.trip_number ASC, bt.departure_time ASC";

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) dispatcher_json_error(500, "Failed to load report data");
$dispatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$name = $dispatcher_user['name'] ?? 'Dispatcher';
?>
<!DOCTYPE html>
<html>
<head><link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Daily Dispatch Report - <?php echo $date; ?></title>
<style>
body{font-family:'Courier New',monospace;margin:30px;font-size:12px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #000;padding:6px 8px;text-align:left;}
th{background:#eee;}
h2{text-align:center;margin:0;}
.header{text-align:center;margin-bottom:20px;}
.footer{margin-top:20px;text-align:center;font-size:11px;}
@media print{body{margin:15px;} .no-print{display:none;}}
</style>
</head>
<body>
<div class="header">
    <h2>R-Connect Transport Management System</h2>
    <h3>Daily Dispatch Report</h3>
    <p>Date: <?php echo $date; ?> | Dispatcher: <?php echo htmlspecialchars($name); ?></p>
</div>
<table>
<tr>
    <th>#</th><th>Trip No.</th><th>Bus</th><th>Driver</th><th>Route</th>
    <th>Dispatch Time</th><th>Arrival Time</th><th>Duration</th><th>Pax</th><th>Status</th>
</tr>
<?php foreach ($dispatches as $i => $d):
    $duration = '';
    if ($d['duration_minutes']) {
        $h = floor($d['duration_minutes'] / 60);
        $m = $d['duration_minutes'] % 60;
        $duration = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    } elseif ($d['departure_time'] && $d['arrival_time']) {
        $t1 = strtotime($d['departure_time']);
        $t2 = strtotime($d['arrival_time']);
        $diff = $t2 - $t1;
        if ($diff > 0) $duration = gmdate('H:i', $diff);
    }
    $statusLabel = match($d['status']) {
        'Scheduled' => 'Pending',
        'Departed' => 'On the Way',
        'Completed' => 'Arrived',
        'Interrupted' => 'Interrupted',
        default => $d['status']
    };
?>
<tr>
    <td><?php echo $i + 1; ?></td>
    <td><?php echo $d['trip_number'] ?? $d['id']; ?></td>
    <td><?php echo htmlspecialchars($d['bus_id']); ?></td>
    <td><?php echo htmlspecialchars($d['driver_name'] ?? $d['driver_id']); ?></td>
    <td><?php echo htmlspecialchars($d['route_name'] ?? ''); ?></td>
    <td><?php echo $d['departure_time'] ? htmlspecialchars($d['departure_time']) : '-'; ?></td>
    <td><?php echo $d['arrival_time'] ? htmlspecialchars($d['arrival_time']) : '-'; ?></td>
    <td><?php echo $duration ?: '-'; ?></td>
    <td><?php echo $d['total_passengers'] ?? '-'; ?></td>
    <td><?php echo $statusLabel; ?></td>
</tr>
<?php endforeach; ?>
</table>
<div class="footer">
    <p>Total Trips: <?php echo count($dispatches); ?></p>
    <button class="no-print" onclick="window.print()">Print</button>
    <button class="no-print" onclick="window.close()">Close</button>
</div>
</body>
</html>
