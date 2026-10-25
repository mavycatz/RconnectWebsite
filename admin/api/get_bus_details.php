<?php
require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$busId = $_GET['bus_id'] ?? '';

if (empty($busId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing bus ID']);
    exit;
}

try {
    $db = Database::get();
    $stmt = $db->prepare("
        SELECT 
            b.bus_id,
            b.plate_number,
            b.bus_type,
            b.seats,
            b.standing_capacity,
            b.accessible_features,
            b.driver,
            b.route_name,
            b.terminal,
            b.schedule,
            b.status,
            b.qr_code,
            b.qr_data,
            b.created_at,
            rt.from_terminal_id,
            rt.to_terminal_id,
            d.driver_id,
            d.full_name AS driver_name
        FROM buses b
        LEFT JOIN (
            SELECT bus_id,
                   SUBSTRING_INDEX(route_name, ' - ', 1) AS from_terminal_id,
                   SUBSTRING_INDEX(route_name, ' - ', -1) AS to_terminal_id
            FROM buses
        ) rt ON rt.bus_id = b.bus_id
        LEFT JOIN drivers d ON d.driver_id = b.driver
        WHERE b.bus_id = :bus_id
        LIMIT 1
    ");
    $stmt->execute([':bus_id' => $busId]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bus) {
        echo json_encode(['status' => 'error', 'message' => 'Bus not found']);
        exit;
    }

    echo json_encode(['status' => 'success', 'data' => $bus]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
