<?php

namespace Finance\Services;

use App\Core\Database;

class FleetPerformanceService
{
    public function getPerformanceData(): array
    {
        return [
            'stats' => $this->getStats(),
            'passenger_per_route' => $this->getPassengerPerRoute(),
            'revenue_per_bus' => $this->getRevenuePerBus(),
            'trip_completion' => $this->getTripCompletion(),
            'violations' => $this->getViolations(),
        ];
    }

    private function getStats(): array
    {
        $db = Database::get();

        $activeBuses = (int) $db->query("SELECT COUNT(*) FROM buses WHERE status NOT IN ('Archived','Inactive')")->fetchColumn();

        $monthStart = date('Y-m-01');
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
        $topRoute = $stmt->fetch();

        return [
            'active_buses' => $activeBuses,
            'month_trips' => $monthTrips,
            'avg_passengers_per_trip' => $avgPassengers,
            'top_route' => $topRoute ? $topRoute['route_name'] . ' (' . $topRoute['cnt'] . ' trips)' : 'N/A',
        ];
    }

    private function getPassengerPerRoute(): array
    {
        $db = Database::get();
        $stmt = $db->query("
            SELECT bt.route_name, SUM(bt.total_passengers) as passengers
            FROM bus_trips bt
            WHERE bt.route_name IS NOT NULL AND bt.route_name != ''
            GROUP BY bt.route_name
            ORDER BY passengers DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRevenuePerBus(): array
    {
        $db = Database::get();
        $stmt = $db->query("
            SELECT bt.bus_id, SUM(bt.total_revenue) as revenue
            FROM bus_trips bt
            WHERE bt.bus_id IS NOT NULL AND bt.bus_id != ''
            GROUP BY bt.bus_id
            ORDER BY revenue DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getTripCompletion(): array
    {
        $db = Database::get();
        $stmt = $db->query("
            SELECT bt.bus_id,
                   COUNT(*) as total_trips,
                   SUM(CASE WHEN bt.status = 'Completed' THEN 1 ELSE 0 END) as completed_trips
            FROM bus_trips bt
            GROUP BY bt.bus_id
            ORDER BY total_trips DESC
            LIMIT 15
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getViolations(): array
    {
        $db = Database::get();
        $stmt = $db->query("
            SELECT v.severity, COUNT(*) as count
            FROM violations v
            GROUP BY v.severity
            ORDER BY count DESC
        ");
        $bySeverity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt2 = $db->query("
            SELECT v.id, v.violation_name, v.severity, vc.name as category
            FROM violations v
            LEFT JOIN violation_categories vc ON v.category_id = vc.id
            ORDER BY FIELD(v.severity,'Critical','High','Medium','Low'), v.id DESC
            LIMIT 15
        ");
        $recent = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'by_severity' => $bySeverity,
            'recent' => $recent,
        ];
    }
}
