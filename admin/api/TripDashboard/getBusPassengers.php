<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Core\Database;

header('Content-Type: application/json');

try {

    $pdo = Database::get();

    $trip = null;

    $tripId = $_GET['trip_id'] ?? '';

    if ($tripId) {
        $tripStmt = $pdo->prepare("
            SELECT id, route_name, status, bus_id
            FROM bus_trips
            WHERE id = :id AND status IN ('Departed', 'Scheduled')
            LIMIT 1
        ");
        $tripStmt->execute([':id' => $tripId]);
        $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$trip) {
        $busId = $_GET['bus_id'] ?? '';
        if ($busId) {
            $tripStmt = $pdo->prepare("
                SELECT id, route_name, status, bus_id
                FROM bus_trips
                WHERE bus_id = :bus_id AND status IN ('Departed', 'Scheduled')
                ORDER BY id DESC
                LIMIT 1
            ");
            $tripStmt->execute([':bus_id' => $busId]);
            $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    $passengers = [];
    $boardedCount = 0;
    $droppedOffCount = 0;
    $totalRevenue = 0;
    $paymentBreakdown = [];
    $boardingPoints = [];

    if ($trip) {
        $passStmt = $pdo->prepare("
            SELECT
                pt.id,
                pt.passenger_id,
                COALESCE(p.full_name, 'Passenger') AS passenger_name,
                pt.boarding_point,
                pt.destination,
                pt.fare,
                pt.payment_method,
                pt.payment_status,
                pt.boarding_time,
                pt.dropoff_time,
                pt.latitude,
                pt.longitude,
                p.discount_type,
                pt.gcash_amount
            FROM passenger_trips pt
            LEFT JOIN passengers p ON pt.passenger_id = p.id
            WHERE pt.trip_no = :trip_no
            ORDER BY pt.boarding_time DESC
        ");
        $passStmt->execute([':trip_no' => $trip['id']]);
        $passengers = $passStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($passengers as &$p) {
            $p['fare'] = (float)($p['fare'] ?? 0);
            $p['gcash_amount'] = (float)($p['gcash_amount'] ?? 0);
            $p['is_dropped_off'] = $p['dropoff_time'] !== null;
            $p['boarding_lat'] = $p['latitude'] ? (float)$p['latitude'] : null;
            $p['boarding_lng'] = $p['longitude'] ? (float)$p['longitude'] : null;

            if ($p['is_dropped_off']) {
                $droppedOffCount++;
            } else {
                $boardedCount++;
            }

            $totalRevenue += $p['fare'];

            $method = strtolower($p['payment_method'] ?? 'unknown');
            if (!isset($paymentBreakdown[$method])) {
                $paymentBreakdown[$method] = 0;
            }
            $paymentBreakdown[$method]++;

            $bp = $p['boarding_point'] ?? 'Unknown';
            if (!isset($boardingPoints[$bp])) {
                $boardingPoints[$bp] = 0;
            }
            $boardingPoints[$bp]++;
        }
        unset($p);
    }

    $boardingPointCoords = [];
    foreach ($passengers as $p) {
        $bp = $p['boarding_point'] ?? 'Unknown';
        if ($p['boarding_lat'] !== null && $p['boarding_lng'] !== null && !isset($boardingPointCoords[$bp])) {
            $boardingPointCoords[$bp] = [
                'lat' => $p['boarding_lat'],
                'lng' => $p['boarding_lng']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'trip' => $trip,
            'passengers' => $passengers,
            'stats' => [
                'boarded' => $boardedCount,
                'dropped_off' => $droppedOffCount,
                'total' => $boardedCount + $droppedOffCount,
                'total_revenue' => round($totalRevenue, 2),
                'payment_breakdown' => $paymentBreakdown,
                'boarding_points' => $boardingPoints
            ],
            'boarding_point_coords' => $boardingPointCoords
        ]
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
