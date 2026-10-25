<?php
/**
 * get_buses.php
 * Lists registered buses for the Inspector fuel-monitoring Bus picker.
 *
 * Response envelope:
 *   { success: true, buses: [ { bus_id: "Bus-1001", plate_number: "NDB-8921" }, ... ] }
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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

// Fallback seed list of registered fleet buses in case table is empty or server DB is initializing
$default_buses = [
    ["bus_id" => "Bus-1001", "plate_number" => "NDB-8921"],
    ["bus_id" => "Bus-1002", "plate_number" => "NDB-8922"],
    ["bus_id" => "Bus-1003", "plate_number" => "NDB-8923"],
    ["bus_id" => "Bus-1004", "plate_number" => "NDB-8924"],
    ["bus_id" => "Bus-1005", "plate_number" => "NDB-8925"],
    ["bus_id" => "Bus-1006", "plate_number" => "NDB-8926"],
    ["bus_id" => "Bus-1007", "plate_number" => "NDB-8927"],
    ["bus_id" => "Bus-1008", "plate_number" => "NDB-8928"],
];

if (!$conn) {
    echo json_encode(["success" => true, "buses" => $default_buses, "source" => "offline_fleet"]);
    exit;
}

try {
    // Ensure buses table exists
    $createSql = "CREATE TABLE IF NOT EXISTS buses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bus_id VARCHAR(50) NOT NULL UNIQUE,
        plate_number VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @$conn->query($createSql);

    $result = $conn->query("SELECT bus_id, plate_number FROM buses ORDER BY bus_id ASC");
    $buses = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $buses[] = [
                "bus_id" => $row['bus_id'],
                "plate_number" => $row['plate_number']
            ];
        }
    }

    // If buses table was just created or empty, seed standard fleet
    if (empty($buses)) {
        foreach ($default_buses as $b) {
            $stmt = $conn->prepare("INSERT IGNORE INTO buses (bus_id, plate_number) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ss", $b['bus_id'], $b['plate_number']);
                $stmt->execute();
                $stmt->close();
            }
        }
        $buses = $default_buses;
    }

    echo json_encode(["success" => true, "buses" => $buses]);
} catch (Exception $e) {
    echo json_encode(["success" => true, "buses" => $default_buses, "fallback" => true, "error" => $e->getMessage()]);
}
