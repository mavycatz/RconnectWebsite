<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

try{

    $bus_id =
        $_GET['bus_id']
        ?? '';

    if(!$bus_id){

        echo json_encode([
            'success' => false,
            'message' => 'Missing bus_id'
        ]);

        exit;

    }

    $conn =
        Database::get();

    // =========================
    // BUS INFO
    // =========================

    $stmt =
        $conn->prepare("
            SELECT
                bus_id,
                plate_number,
                seats,
                standing_capacity,
                driver,
                route_name,
                qr_code,
                qr_data
            FROM buses
            WHERE bus_id = :bus_id
            LIMIT 1
        ");

    $stmt->execute([
        ':bus_id' => $bus_id
    ]);

    $bus =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if(!$bus){

        echo json_encode([
            'success' => false,
            'message' => 'Bus not found'
        ]);

        exit;

    }

    // =========================
    // ACTIVE TRIP
    // =========================
    // The QR must carry the bus's currently active trip, never a stale
    // completed/cancelled row. Mirrors the passenger active-trip resolver:
    // active statuses only, with a +/-1 day window for timezone skew.
    // =========================

    $tripStmt =
        $conn->prepare("
            SELECT
                id,
                route_name,
                trip_date,
                departure_time,
                status
            FROM bus_trips
            WHERE bus_id = :bus_id
              AND status IN ('Scheduled','Departed','On Route','Started','Active','Ongoing')
              AND trip_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ORDER BY
                CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END ASC,
                id DESC
            LIMIT 1
        ");

    $tripStmt->execute([
        ':bus_id' => $bus_id
    ]);

    $trip =
        $tripStmt->fetch(
            PDO::FETCH_ASSOC
        );

    $bus['current_trip_id'] =
        $trip['id']
        ?? null;

    $bus['trip_status'] =
        $trip['status']
        ?? null;

    $bus['trip_route_name'] =
        $trip['route_name']
        ?? null;

    $bus['trip_date'] =
        $trip['trip_date']
        ?? null;

    $bus['departure_time'] =
        $trip['departure_time']
        ?? null;

    echo json_encode([
        'success' => true,
        'data' => $bus
    ]);

}catch(Throwable $e){

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}