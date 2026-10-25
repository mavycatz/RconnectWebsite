<?php
/**
 * get_fuel_monitoring_records.php
 * Fetches real fuel monitoring and fuel log records from existing `fuel_logs` table.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = null;

$config_paths = [
    __DIR__ . "/../config/database.php",
    __DIR__ . "/../../config/database.php",
    __DIR__ . "/config/database.php",
    $_SERVER['DOCUMENT_ROOT'] . "/mobile/config/database.php",
    $_SERVER['DOCUMENT_ROOT'] . "/config/database.php",
    $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php"
];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (class_exists('Database')) {
            $db = new Database();
            $conn = $db->connect();
            if ($conn) break;
        }
    }
}

if (!$conn && file_exists(__DIR__ . "/../../../scripts/db_config.php")) {
    require_once __DIR__ . "/../../../scripts/db_config.php";
}

if (!$conn) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "records" => []
    ]);
    exit;
}

$busId = trim($_GET['bus_id'] ?? ($_GET['bus_no'] ?? ''));
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;

try {
    // Check columns on fuel_logs
    $chkCols = $conn->query("SHOW COLUMNS FROM fuel_logs");
    $colMap = [];
    if ($chkCols) {
        while ($c = $chkCols->fetch_assoc()) {
            $colMap[strtolower($c['Field'])] = $c['Field'];
        }
    }

    // Auto-ensure columns exist without breaking schema
    if (!isset($colMap['starting_odometer'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN starting_odometer DECIMAL(10,2) NULL");
    }
    if (!isset($colMap['ending_odometer'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN ending_odometer DECIMAL(10,2) NULL");
    }
    if (!isset($colMap['start_km'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN start_km DECIMAL(10,2) NULL");
    }
    if (!isset($colMap['end_km'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN end_km DECIMAL(10,2) NULL");
    }
    if (!isset($colMap['distance_km'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN distance_km DECIMAL(10,2) NULL");
    }
    if (!isset($colMap['kpl'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN kpl DECIMAL(5,2) NULL DEFAULT 9.00");
    }
    if (!isset($colMap['is_full_tank'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN is_full_tank TINYINT(1) NULL DEFAULT 0");
    }
    if (!isset($colMap['notes'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN notes TEXT NULL");
    }
    if (!isset($colMap['odo_photo_url'])) {
        @$conn->query("ALTER TABLE fuel_logs ADD COLUMN odo_photo_url VARCHAR(255) NULL");
    }

    // Fetch live Diesel price from fuel_prices table
    $currentDieselPrice = 65.55;
    $priceRes = @$conn->query("SELECT price_per_liter FROM fuel_prices WHERE fuel_type LIKE '%diesel%' LIMIT 1");
    if ($priceRes && $priceRes->num_rows > 0) {
        $pRow = $priceRes->fetch_assoc();
        $currentDieselPrice = (float)$pRow['price_per_liter'];
    }

    $where = [];
    $params = [];
    $types = "";

    // Display only records that have a starting odometer
    $where[] = "(COALESCE(fl.starting_odometer, fl.start_km) IS NOT NULL AND COALESCE(fl.starting_odometer, fl.start_km) > 0)";

    if (!empty($busId)) {
        $where[] = "fl.bus_id = ?";
        $params[] = $busId;
        $types .= "s";
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $sql = "
        SELECT
            fl.fuel_log_id,
            fl.bus_id,
            COALESCE(b.plate_number, '') AS plate_number,
            fl.driver_id,
            COALESCE(d.full_name, b.driver, fl.driver_id, 'Unassigned') AS driver_name,
            fl.liters,
            fl.cost,
            fl.price_per_liter,
            fl.created_at,
            COALESCE(fl.starting_odometer, fl.start_km) AS start_km,
            COALESCE(fl.ending_odometer, fl.end_km) AS end_km,
            COALESCE(fl.kpl, 9.0) AS kpl,
            COALESCE(fl.is_full_tank, 0) AS is_full_tank,
            fl.notes,
            fl.odo_photo_url
        FROM fuel_logs fl
        LEFT JOIN buses b ON b.bus_id = fl.bus_id
        LEFT JOIN drivers d ON d.driver_id = fl.driver_id
        $whereClause
        ORDER BY fl.created_at DESC, fl.fuel_log_id DESC
        LIMIT ?
    ";

    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $records = [];

    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $startOdo = $row['start_km'] !== null ? (float)$row['start_km'] : null;
            $endOdo = $row['end_km'] !== null ? (float)$row['end_km'] : null;
            $dist = ($startOdo !== null && $endOdo !== null && $endOdo >= $startOdo) ? round($endOdo - $startOdo, 2) : null;
            $kplVal = (float)($row['kpl'] > 0 ? $row['kpl'] : 9.0);
            $expectedFuel = ($dist !== null && $dist > 0) ? round($dist / $kplVal, 2) : 0;
            $liters = (float)($row['liters'] ?? 0);
            $pricePerLiter = (float)($row['price_per_liter'] > 0 ? $row['price_per_liter'] : $currentDieselPrice);
            $cost = (float)($row['cost'] > 0 ? $row['cost'] : round($liters * $pricePerLiter, 2));
            $hasOdo = ($startOdo !== null && $endOdo !== null);

            $records[] = [
                "fuel_log_id" => (int)$row['fuel_log_id'],
                "record_id" => (int)$row['fuel_log_id'],
                "bus_id" => (string)$row['bus_id'],
                "bus_no" => (string)$row['bus_id'],
                "plate_no" => (string)$row['plate_number'],
                "plate_number" => (string)$row['plate_number'],
                "driver_id" => (string)$row['driver_id'],
                "driver_name" => (string)$row['driver_name'],
                "liters" => $liters,
                "cost" => $cost,
                "price_per_liter" => $pricePerLiter,
                "start_km" => $startOdo,
                "starting_odometer" => $startOdo,
                "end_km" => $endOdo,
                "ending_odometer" => $endOdo,
                "distance_km" => $dist,
                "distance" => $dist,
                "kpl" => $kplVal,
                "expected_liters" => $expectedFuel,
                "is_full_tank" => (bool)((int)$row['is_full_tank'] === 1),
                "has_odometer" => $hasOdo,
                "notes" => (string)($row['notes'] ?? ''),
                "odo_photo_url" => (string)($row['odo_photo_url'] ?? ''),
                "created_at" => (string)$row['created_at']
            ];
        }
        $stmt->close();
    }

    echo json_encode([
        "success" => true,
        "diesel_price" => $currentDieselPrice,
        "total" => count($records),
        "records" => $records
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error fetching fuel records: " . $e->getMessage(),
        "records" => []
    ]);
}
?>