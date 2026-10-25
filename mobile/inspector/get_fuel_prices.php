<?php
/**
 * get_fuel_prices.php
 * Fetches existing fuel prices from the `fuel_prices` table.
 * This endpoint does NOT create or insert any records.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

            if ($conn) {
                break;
            }
        }
    }
}

if (!$conn && file_exists(__DIR__ . "/../../../scripts/db_config.php")) {
    require_once __DIR__ . "/../../../scripts/db_config.php";
}

if (!$conn) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}

try {

    // Only FETCH existing records.
    $result = $conn->query("
        SELECT 
            id,
            fuel_type,
            price_per_liter,
            updated_at
        FROM fuel_prices
        ORDER BY fuel_type ASC
    ");

    if (!$result) {
        throw new Exception("Failed to fetch fuel prices: " . $conn->error);
    }

    $prices = [];
    $dieselPrice = null;

    while ($row = $result->fetch_assoc()) {

        $val = (float) $row['price_per_liter'];
        $type = (string) $row['fuel_type'];

        $prices[] = [
            "id" => (int) $row['id'],
            "fuel_type" => $type,
            "price_per_liter" => $val,
            "updated_at" => (string) ($row['updated_at'] ?? '')
        ];

        if (stripos($type, 'diesel') !== false) {
            $dieselPrice = $val;
        }
    }

    echo json_encode([
        "success" => true,
        "diesel_price" => $dieselPrice,
        "prices" => $prices
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "prices" => []
    ]);
}

?>