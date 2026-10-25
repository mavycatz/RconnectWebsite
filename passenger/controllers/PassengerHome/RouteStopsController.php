<?php

if (!defined('APP_LOADED')) {
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/init.php';

use App\Core\Database;

$db = Database::getInstance()->getConnection();

$stmt = $db->query("
    SELECT
        id,
        route_name,
        stop_name,
        latitude,
        longitude,
        sequence_order
    FROM route_stops
    ORDER BY sequence_order ASC
");

$stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'stops'   => $stops
]);