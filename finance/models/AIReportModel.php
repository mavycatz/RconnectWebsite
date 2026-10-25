<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;
use PDO;

class AIReportModel
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn =
            Database::get();

        $this->conn->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET FINANCIAL SUMMARY
    |--------------------------------------------------------------------------
    */

    public function getFinancialSummary(): array
    {

        /*
        |--------------------------------------------------------------------------
        | REVENUE
        |--------------------------------------------------------------------------
        */

        $revenueQuery = "

            SELECT

                COALESCE(
                    SUM(fare),
                    0
                ) AS total_revenue,

                COUNT(*) AS total_passengers,

                COUNT(
                    DISTINCT bus_no
                ) AS active_buses,

                COUNT(
                    DISTINCT trip_no
                ) AS total_trips

            FROM passenger_trips

            WHERE payment_status = 'paid'

        ";

        $revenueStmt =
            $this->conn->query(
                $revenueQuery
            );

        $revenue =
            $revenueStmt->fetch(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | EXPENSES
        |--------------------------------------------------------------------------
        */

        $expenseQuery = "

            SELECT

                COALESCE(
                    SUM(fuel_cost),
                    0
                ) AS total_expenses

            FROM bus_trips

        ";

        $expenseStmt =
            $this->conn->query(
                $expenseQuery
            );

        $expenses =
            $expenseStmt->fetch(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | TOP ROUTES
        |--------------------------------------------------------------------------
        */

        $routeQuery = "

            SELECT

                destination,

                COUNT(*) AS total_trips

            FROM passenger_trips

            GROUP BY destination

            ORDER BY total_trips DESC

            LIMIT 5

        ";

        $routeStmt =
            $this->conn->query(
                $routeQuery
            );

        $topRoutes =
            $routeStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHODS
        |--------------------------------------------------------------------------
        */

        $paymentQuery = "

            SELECT

                payment_method,

                COUNT(*) AS total

            FROM passenger_trips

            GROUP BY payment_method

            ORDER BY total DESC

        ";

        $paymentStmt =
            $this->conn->query(
                $paymentQuery
            );

        $paymentMethods =
            $paymentStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | TOP DRIVER
        |--------------------------------------------------------------------------
        */

        $topDriverQuery = "

            SELECT

                submitted_by AS driver_name,

                COUNT(id) AS total

            FROM remittance_logs

            GROUP BY submitted_by

            ORDER BY total DESC

            LIMIT 1

        ";

        $topDriverStmt =
            $this->conn->query(
                $topDriverQuery
            );

        $topDriver =
            $topDriverStmt->fetch(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | TOP BUS
        |--------------------------------------------------------------------------
        */

        $topBusQuery = "

            SELECT

                bus_no AS bus_number,

                COUNT(id) AS total

            FROM remittance_logs

            GROUP BY bus_no

            ORDER BY total DESC

            LIMIT 1

        ";

        $topBusStmt =
            $this->conn->query(
                $topBusQuery
            );

        $topBus =
            $topBusStmt->fetch(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | NET PROFIT
        |--------------------------------------------------------------------------
        */

        $netProfit =

            (float)(
                $revenue['total_revenue']
                ?? 0
            )

            -

            (float)(
                $expenses['total_expenses']
                ?? 0
            );

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return [

            'total_revenue' =>

                (float)(
                    $revenue['total_revenue']
                    ?? 0
                ),

            'total_passengers' =>

                (int)(
                    $revenue['total_passengers']
                    ?? 0
                ),

            'active_buses' =>

                (int)(
                    $revenue['active_buses']
                    ?? 0
                ),

            'total_trips' =>

                (int)(
                    $revenue['total_trips']
                    ?? 0
                ),

            'total_expenses' =>

                (float)(
                    $expenses['total_expenses']
                    ?? 0
                ),

            'net_profit' =>

                $netProfit,

            'top_routes' =>

                $topRoutes,

            'payment_methods' =>

                $paymentMethods,

            'top_driver' =>

                $topDriver['driver_name']
                ?? 'Unknown',

            'top_bus' =>

                $topBus['bus_number']
                ?? 'Unknown'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE AI REPORT
    |--------------------------------------------------------------------------
    */

    public function saveAIReport(
        string $title,
        string $content,
        string $reportType = 'financial_ai'
    ): bool {

        $sql = "

            INSERT INTO ai_reports (

                report_title,
                report_content,
                report_type

            )

            VALUES (

                :title,
                :content,
                :report_type

            )

        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        return $stmt->execute([

            ':title' =>

                $title,

            ':content' =>

                $content,

            ':report_type' =>

                $reportType
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET LATEST REPORTS
    |--------------------------------------------------------------------------
    */

    public function getLatestReports(
        int $limit = 10
    ): array {

        $limit =

            max(
                1,
                min($limit, 50)
            );

        $sql = "

            SELECT

                id,

                report_title,

                report_content,

                report_type,

                generated_at AS created_at,

                'financial_ai'
                AS source_type

            FROM ai_reports

            ORDER BY generated_at DESC

            LIMIT {$limit}

        ";

        $stmt =
            $this->conn->query(
                $sql
            );

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT BUS TRIPS
    |--------------------------------------------------------------------------
    */

    public function getRecentBusTrips(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM bus_trips

                ORDER BY created_at DESC

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT BUSES
    |--------------------------------------------------------------------------
    */

    public function getRecentBuses(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM buses

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT DRIVERS
    |--------------------------------------------------------------------------
    */

    public function getRecentDrivers(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM drivers

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    public function getRecentAssignments(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM driver_bus_assignment

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT INSPECTORS
    |--------------------------------------------------------------------------
    */

    public function getRecentInspectors(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM inspectors

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT ROUTE STOPS
    |--------------------------------------------------------------------------
    */

    public function getRecentRouteStops(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM route_stops

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT REMITTANCES
    |--------------------------------------------------------------------------
    */

    public function getRecentRemittances(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM remittance_logs

                ORDER BY created_at DESC

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT PASSENGERS
    |--------------------------------------------------------------------------
    */

    public function getRecentPassengers(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM passengers

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | RECENT PASSENGER TRIPS
    |--------------------------------------------------------------------------
    */

    public function getRecentPassengerTrips(): array
    {
        return $this->conn
            ->query("

                SELECT *

                FROM passenger_trips

                ORDER BY boarding_time DESC

                LIMIT 10

            ")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | HAS TODAY REPORTS
    |--------------------------------------------------------------------------
    */

    public function hasTodayReports(): bool
    {

        $sql = "

            SELECT

                COUNT(*) AS total

            FROM ai_reports

            WHERE DATE(generated_at) = CURDATE()

        ";

        $stmt =
            $this->conn->query(
                $sql
            );

        $result =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return
            (int)(
                $result['total']
                ?? 0
            ) >= 10;
    }

    /*
    |--------------------------------------------------------------------------
    | GET TODAY REPORT COUNT
    |--------------------------------------------------------------------------
    */

    public function getTodayReportCount(): int
    {

        $sql = "

            SELECT

                COUNT(*) AS total

            FROM ai_reports

            WHERE DATE(generated_at) = CURDATE()

        ";

        $stmt =
            $this->conn->query(
                $sql
            );

        $result =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return
            (int)(
                $result['total']
                ?? 0
            );
    }
}