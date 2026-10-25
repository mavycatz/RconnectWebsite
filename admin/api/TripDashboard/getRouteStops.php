<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once __DIR__ . '/live_map_helpers.php';

use App\Core\Database;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {

    $pdo = Database::get();

    // =========================
    // GET ROUTE NAME
    // =========================
    $routeName = trim(
        $_GET['route'] ??
        'Cabanatuan to Rizal Route'
    );

    // =========================
    // FETCH ROUTE STOPS (sequence_order ASC is the source of truth)
    // =========================
    $stmt = $pdo->prepare("
        SELECT
            id,
            route_name,
            stop_name,
            latitude,
            longitude,
            sequence_order
        FROM route_stops
        WHERE route_name = ?
        ORDER BY sequence_order ASC
    ");

    $stmt->execute([
        $routeName
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Validate every coordinate before it reaches the map.
    $valid = [];
    $rejected = 0;
    foreach (live_map_route_stops($rows) as $stop) {
        $valid[] = $stop;
    }
    $rejected = max(0, count($rows) - count($valid));

    $data = [];
    foreach ($rows as $row) {
        if (!live_map_valid_ph_coord($row['latitude'] ?? null, $row['longitude'] ?? null)) {
            continue;
        }
        $data[] = [
            'id' => (int)($row['id'] ?? 0),
            'route_name' => (string)($row['route_name'] ?? $routeName),
            'stop_name' => (string)($row['stop_name'] ?? ''),
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'sequence_order' => (int)($row['sequence_order'] ?? 0),
        ];
    }

    echo json_encode([
        'success' => true,
        'route'   => $routeName,
        'count'   => count($data),
        'rejected_invalid_coordinates' => $rejected,
        'data'    => $data
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}