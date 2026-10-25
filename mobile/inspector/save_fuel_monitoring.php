<?php
/**
 * save_fuel_monitoring.php
 * Persists fuel records and odometer readings directly into existing `fuel_logs` table.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON payload"]);
    exit;
}

$fuelLogId = isset($data['fuel_log_id']) && !empty($data['fuel_log_id']) ? (int)$data['fuel_log_id'] : (isset($data['record_id']) && !empty($data['record_id']) ? (int)$data['record_id'] : null);
$busId = trim($data['bus_id'] ?? ($data['bus_no'] ?? ''));
$driverId = trim($data['driver_id'] ?? '');

$startKm = isset($data['starting_odometer']) && $data['starting_odometer'] !== '' ? (float)$data['starting_odometer'] : (isset($data['start_km']) && $data['start_km'] !== '' ? (float)$data['start_km'] : null);

// Check if ending ODO was provided (optional during new record creation)
$endKm = null;
if (isset($data['ending_odometer']) && $data['ending_odometer'] !== null && $data['ending_odometer'] !== '') {
    $val = (float)$data['ending_odometer'];
    if ($val > 0) $endKm = $val;
} else if (isset($data['end_km']) && $data['end_km'] !== null && $data['end_km'] !== '') {
    $val = (float)$data['end_km'];
    if ($val > 0) $endKm = $val;
}

$distKm = ($startKm !== null && $endKm !== null && $endKm >= $startKm) ? round($endKm - $startKm, 2) : null;
$kpl = isset($data['kpl']) && (float)$data['kpl'] > 0 ? (float)$data['kpl'] : 9.0;
$isFullTank = !empty($data['is_full_tank']) ? 1 : 0;

// Fuel price from fuel_prices table if not provided
$pricePerLiter = isset($data['price_per_liter']) && (float)$data['price_per_liter'] > 0 ? (float)$data['price_per_liter'] : 0;
if ($pricePerLiter <= 0) {
    $priceRes = @$conn->query("SELECT price_per_liter FROM fuel_prices WHERE fuel_type LIKE '%diesel%' LIMIT 1");
    if ($priceRes && $priceRes->num_rows > 0) {
        $pRow = $priceRes->fetch_assoc();
        $pricePerLiter = (float)$pRow['price_per_liter'];
    } else {
        $pricePerLiter = 65.55;
    }
}

$liters = isset($data['liters']) ? (float)$data['liters'] : (isset($data['actual_liters']) ? (float)$data['actual_liters'] : 0);
$cost = isset($data['cost']) && (float)$data['cost'] > 0 ? (float)$data['cost'] : round($liters * $pricePerLiter, 2);

$notes = trim($data['notes'] ?? '');
$odoPhotoUrl = trim($data['odo_photo_url'] ?? ($data['start_odo_photo'] ?? ''));

try {
    // Check columns on fuel_logs
    $chkCols = $conn->query("SHOW COLUMNS FROM fuel_logs");
    $colMap = [];
    if ($chkCols) {
        while ($c = $chkCols->fetch_assoc()) {
            $colMap[strtolower($c['Field'])] = $c['Field'];
        }
    }

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

    if ($fuelLogId) {
        // UPDATE existing record in fuel_logs (e.g. entering Ending ODO after trip)
        $escapedBus = $conn->real_escape_string($busId);
        $escapedDriver = $conn->real_escape_string($driverId);
        $escapedNotes = $conn->real_escape_string($notes);
        $escapedPhoto = $conn->real_escape_string($odoPhotoUrl);

        $startKmSql = $startKm !== null ? "$startKm" : "NULL";
        $endKmSql = $endKm !== null ? "$endKm" : "NULL";
        $distKmSql = $distKm !== null ? "$distKm" : "NULL";

        $updateSql = "
            UPDATE fuel_logs
            SET starting_odometer = $startKmSql,
                ending_odometer = $endKmSql,
                start_km = $startKmSql,
                end_km = $endKmSql,
                distance_km = $distKmSql,
                liters = $liters,
                cost = $cost,
                price_per_liter = $pricePerLiter,
                kpl = $kpl,
                is_full_tank = $isFullTank,
                notes = '$escapedNotes',
                odo_photo_url = '$escapedPhoto'
        ";
        if (!empty($busId)) {
            $updateSql .= ", bus_id = '$escapedBus'";
        }
        if (!empty($driverId)) {
            $updateSql .= ", driver_id = '$escapedDriver'";
        }
        $updateSql .= " WHERE fuel_log_id = $fuelLogId";

        $conn->query($updateSql);

        echo json_encode([
            "success" => true,
            "message" => "Fuel log and odometer updated successfully",
            "fuel_log_id" => $fuelLogId
        ]);
    } else {
        // INSERT new record into fuel_logs at the start of the trip
        if (empty($busId)) {
            echo json_encode(["success" => false, "message" => "Bus selection is required"]);
            exit;
        }

        $escapedBus = $conn->real_escape_string($busId);
        $escapedDriver = $conn->real_escape_string($driverId);
        $escapedNotes = $conn->real_escape_string($notes);
        $escapedPhoto = $conn->real_escape_string($odoPhotoUrl);

        $startKmSql = $startKm !== null ? "$startKm" : "NULL";
        $endKmSql = $endKm !== null ? "$endKm" : "NULL";
        $distKmSql = $distKm !== null ? "$distKm" : "NULL";

        $insertSql = "
            INSERT INTO fuel_logs (
                bus_id,
                driver_id,
                liters,
                cost,
                price_per_liter,
                starting_odometer,
                ending_odometer,
                start_km,
                end_km,
                distance_km,
                kpl,
                is_full_tank,
                notes,
                odo_photo_url,
                created_at
            ) VALUES (
                '$escapedBus',
                '$escapedDriver',
                $liters,
                $cost,
                $pricePerLiter,
                $startKmSql,
                $endKmSql,
                $startKmSql,
                $endKmSql,
                $distKmSql,
                $kpl,
                $isFullTank,
                '$escapedNotes',
                '$escapedPhoto',
                NOW()
            )
        ";

        if ($conn->query($insertSql)) {
            $insertedId = $conn->insert_id;
            echo json_encode([
                "success" => true,
                "message" => "New fuel record created successfully",
                "fuel_log_id" => $insertedId
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Insert error: " . $conn->error
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error saving fuel record: " . $e->getMessage()
    ]);
}
?>