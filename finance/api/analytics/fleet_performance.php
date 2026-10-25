<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

try {
    $db = Database::get();

    $routeDetail = $_GET['route'] ?? null;
    if ($routeDetail) {
        $stmt = $db->prepare("
            SELECT boarding_point, SUM(cnt) as cnt FROM (
                SELECT pt.boarding_point, COUNT(*) as cnt
                FROM passenger_trips pt
                JOIN bus_trips bt ON bt.bus_id = pt.bus_no AND bt.route_name = :r1a
                WHERE pt.boarding_point IS NOT NULL AND pt.boarding_point != ''
                GROUP BY pt.boarding_point
                UNION ALL
                SELECT pth.boarding_point, COUNT(*) as cnt
                FROM passenger_trip_history pth
                JOIN bus_trips bt ON bt.driver_id = pth.driver_id AND bt.route_name = :r1b
                WHERE pth.boarding_point IS NOT NULL AND pth.boarding_point != ''
                GROUP BY pth.boarding_point
            ) combined
            GROUP BY boarding_point
            ORDER BY cnt DESC
            LIMIT 5
        ");
        $stmt->execute([':r1a' => $routeDetail, ':r1b' => $routeDetail]);
        $boardingPoints = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT destination, SUM(cnt) as cnt FROM (
                SELECT pt.destination, COUNT(*) as cnt
                FROM passenger_trips pt
                JOIN bus_trips bt ON bt.bus_id = pt.bus_no AND bt.route_name = :r2a
                WHERE pt.destination IS NOT NULL AND pt.destination != ''
                GROUP BY pt.destination
                UNION ALL
                SELECT pth.destination, COUNT(*) as cnt
                FROM passenger_trip_history pth
                JOIN bus_trips bt ON bt.driver_id = pth.driver_id AND bt.route_name = :r2b
                WHERE pth.destination IS NOT NULL AND pth.destination != ''
                GROUP BY pth.destination
            ) combined
            GROUP BY destination
            ORDER BY cnt DESC
            LIMIT 5
        ");
        $stmt->execute([':r2a' => $routeDetail, ':r2b' => $routeDetail]);
        $destinations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'route' => $routeDetail, 'boarding_points' => $boardingPoints, 'destinations' => $destinations]);
        exit;
    }

    $monthStart = date('Y-m-01');

    $stmt = $db->query("SELECT COUNT(*) FROM buses WHERE status NOT IN ('Archived','Inactive')");
    $activeBuses = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM bus_trips WHERE trip_date >= :ms AND status = 'Completed'");
    $stmt->execute([':ms' => $monthStart]);
    $monthTrips = (int) $stmt->fetchColumn();

    $stmt = $db->query("SELECT COALESCE(AVG(total_passengers),0) FROM bus_trips WHERE total_passengers > 0");
    $avgPassengers = round((float) $stmt->fetchColumn(), 1);

    $stmt = $db->query("
        SELECT route_name, COUNT(*) as cnt
        FROM bus_trips
        WHERE status = 'Completed'
        GROUP BY route_name
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $topRouteRow = $stmt->fetch(\PDO::FETCH_ASSOC);
    $topRoute = $topRouteRow ? $topRouteRow['route_name'] . ' (' . $topRouteRow['cnt'] . ' trips)' : 'N/A';

    $stmt = $db->query("
        SELECT bt.route_name, SUM(bt.total_passengers) as passengers
        FROM bus_trips bt
        WHERE bt.route_name IS NOT NULL AND bt.route_name != ''
        GROUP BY bt.route_name
        ORDER BY passengers DESC
    ");
    $passengerPerRoute = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $stmt = $db->query("
        SELECT pt.bus_no AS bus_id, COALESCE(SUM(pt.fare), 0) AS revenue
        FROM passenger_trips pt
        WHERE pt.bus_no IS NOT NULL AND pt.bus_no != ''
        GROUP BY pt.bus_no
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $revenuePerBus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $stmt = $db->query("
        SELECT bt.bus_id,
               COUNT(*) as total_trips,
               SUM(CASE WHEN bt.status = 'Completed' THEN 1 ELSE 0 END) as completed_trips
        FROM bus_trips bt
        GROUP BY bt.bus_id
        ORDER BY total_trips DESC
        LIMIT 15
    ");
    $tripCompletion = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $stmt = $db->query("
        SELECT passenger_type, COUNT(*) as count
        FROM passenger_trip_history
        WHERE passenger_type IS NOT NULL AND passenger_type != ''
        GROUP BY passenger_type
        ORDER BY count DESC
    ");
    $passengerTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $stmt = $db->query("
        SELECT payment_method, COUNT(*) as count
        FROM passenger_trips
        WHERE payment_method IS NOT NULL AND payment_method != ''
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    $paymentMethods = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'active_buses' => $activeBuses,
        'month_trips' => $monthTrips,
        'avg_passengers_per_trip' => $avgPassengers,
        'top_route' => $topRoute,
        'passenger_per_route' => $passengerPerRoute,
        'revenue_per_bus' => $revenuePerBus,
        'trip_completion' => $tripCompletion,
        'passenger_types' => $passengerTypes,
        'payment_methods' => $paymentMethods,
    ]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
