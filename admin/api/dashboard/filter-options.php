<?php

declare(strict_types=1);

require_once __DIR__ . '/context.php';

use App\Core\Database;

try {
    $db = Database::get();

    $routes = [];

    try {
        $rows = $db->query(
            "SELECT DISTINCT route_name
             FROM fare_pricing
             WHERE route_name IS NOT NULL AND TRIM(route_name) != ''
             ORDER BY route_name"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $route) {
            $routes[trim((string)$route)] = true;
        }
    } catch (\Throwable $e) {
        // fare_pricing may be unavailable; fall through to assigned routes.
    }

    try {
        $rows = $db->query(
            "SELECT DISTINCT route_name
             FROM buses
             WHERE route_name IS NOT NULL AND TRIM(route_name) != ''
               AND COALESCE(status, '') != 'Archived'
             ORDER BY route_name"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $route) {
            $routes[trim((string)$route)] = true;
        }
    } catch (\Throwable $e) {
        // buses table may be unavailable.
    }

    $routes = array_keys($routes);
    sort($routes);

    $buses = [];

    try {
        $stmt = $db->query(
            "SELECT bus_id, plate_number, route_name
             FROM buses
             WHERE bus_id IS NOT NULL AND TRIM(bus_id) != ''
               AND COALESCE(status, '') != 'Archived'
             ORDER BY bus_id"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($busId === '') {
                continue;
            }

            $plate = trim((string)($row['plate_number'] ?? ''));

            $buses[] = [
                'bus_id' => $busId,
                'label'  => $plate !== '' ? $plate : $busId,
                'route'  => trim((string)($row['route_name'] ?? '')),
            ];
        }
    } catch (\Throwable $e) {
        $buses = [];
    }

    dashboard_json([
        'success' => true,
        'routes'  => $routes,
        'buses'   => $buses,
    ]);
} catch (\Throwable $e) {
    dashboard_json([
        'success' => false,
        'message' => 'Unable to load filter options.',
    ]);
}
