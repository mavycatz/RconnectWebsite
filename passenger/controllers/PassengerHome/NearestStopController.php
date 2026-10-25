<?php

if (!defined('APP_LOADED')) {
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/init.php';

use App\Core\Database;

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$lat = (float)($data['latitude'] ?? 0);
$lng = (float)($data['longitude'] ?? 0);

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT
        id,
        route_name,
        stop_name,
        latitude,
        longitude,
        sequence_order,
        (
            6371 * ACOS(
                COS(RADIANS(?))
                * COS(RADIANS(latitude))
                * COS(
                    RADIANS(longitude)
                    - RADIANS(?)
                )
                +
                SIN(RADIANS(?))
                * SIN(RADIANS(latitude))
            )
        ) AS distance
    FROM route_stops
    ORDER BY distance ASC
    LIMIT 1
");

$stmt->execute([
    $lat,
    $lng,
    $lat
]);

$stop = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'stop'    => $stop
]);