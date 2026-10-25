<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Core\Database;

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

try {

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    $conn = Database::tryGet();
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection unavailable',
            'total' => 0,
            'completed' => 0,
            'departed' => 0,
            'scheduled' => 0,
            'total_buses' => 25
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | TODAY DATE
    |--------------------------------------------------------------------------
    */

    $today = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | TOTAL TRIPS
    |--------------------------------------------------------------------------
    */

    $totalTripsQuery = "
        SELECT COUNT(*) AS total
        FROM bus_trips
        WHERE trip_date = ?
    ";

    $stmt =
        $conn->prepare(
            $totalTripsQuery
        );

    $stmt->execute([$today]);

    $totalTrips =
        (int) (
            $stmt->fetch()['total']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | COMPLETED TRIPS
    |--------------------------------------------------------------------------
    */

    $completedQuery = "
        SELECT COUNT(*) AS completed
        FROM bus_trips
        WHERE status = 'Completed'
        AND trip_date = ?
    ";

    $stmt =
        $conn->prepare(
            $completedQuery
        );

    $stmt->execute([$today]);

    $completedTrips =
        (int) (
            $stmt->fetch()['completed']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | DEPARTED TRIPS
    |--------------------------------------------------------------------------
    */

    $departedQuery = "
        SELECT COUNT(*) AS departed
        FROM bus_trips
        WHERE status = 'Departed'
        AND trip_date = ?
    ";

    $stmt =
        $conn->prepare(
            $departedQuery
        );

    $stmt->execute([$today]);

    $departedTrips =
        (int) (
            $stmt->fetch()['departed']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | SCHEDULED TRIPS
    |--------------------------------------------------------------------------
    */

    $scheduledQuery = "
        SELECT COUNT(*) AS scheduled
        FROM bus_trips
        WHERE status = 'Scheduled'
        AND trip_date = ?
    ";

    $stmt =
        $conn->prepare(
            $scheduledQuery
        );

    $stmt->execute([$today]);

    $scheduledTrips =
        (int) (
            $stmt->fetch()['scheduled']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | TOTAL FLEET
    |--------------------------------------------------------------------------
    */

    $totalFleet = 25;
    try {
        $fleetStmt = $conn->query("SELECT COUNT(*) AS total FROM buses WHERE COALESCE(status, '') != 'Archived'");
        if ($fleetStmt) {
            $totalFleet = (int)($fleetStmt->fetch()['total'] ?? 25);
            if ($totalFleet <= 0) $totalFleet = 25;
        }
    } catch (Throwable $e) {
        $totalFleet = 25;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'total' => $totalTrips,

        'completed' => $completedTrips,

        'departed' => $departedTrips,

        'scheduled' => $scheduledTrips,

        'total_buses' => $totalFleet

    ]);

}
catch(Throwable $e){

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'error' => true,

        'message' => $e->getMessage(),

        'file' => $e->getFile(),

        'line' => $e->getLine()

    ]);

}
?>