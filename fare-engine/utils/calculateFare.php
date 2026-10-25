<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once __DIR__ . '/canonicalRouteName.php';

use App\Core\Database;

function calculateFare(
    $tripNo,
    $from,
    $to,
    $type = "regular",
    $fromId = null,
    $toId = null,
    $fromStopId = null,
    $toStopId = null
){

    $conn = Database::get();

    // Source of truth: route_name from bus_trips
    $stmt = $conn->prepare("
        SELECT route_name
        FROM bus_trips
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$tripNo]);
    $routeName = trim($stmt->fetchColumn());

    if($routeName){

        // Fare is computed from fare_pricing only (same route_name as bus_trips)
        $stmt = $conn->prepare("
            SELECT
                id,
                destination,
                display_order,
                regular_fare,
                student_fare,
                special_fare,
                route_name
            FROM fare_pricing
            WHERE route_name = ?
            ORDER BY display_order ASC
        ");
        $stmt->execute([$routeName]);
        $farePricing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {

        $farePricing = [];

    }

    // Fallback if no fare_pricing for route: find route from stop IDs
    if(empty($farePricing)){

        $fallbackId = $toId ?: $fromId;

        if($fallbackId){
            $stmt = $conn->prepare("
                SELECT route_name FROM fare_pricing WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$fallbackId]);
            $fallbackRoute = $stmt->fetchColumn();

            if($fallbackRoute){
                $stmt = $conn->prepare("
                    SELECT id, destination, display_order, regular_fare, student_fare, special_fare, route_name
                    FROM fare_pricing
                    WHERE route_name = ?
                    ORDER BY display_order ASC
                ");
                $stmt->execute([$fallbackRoute]);
                $farePricing = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if(empty($farePricing)){
                return ['fare' => 0, '_debug_why' => 'fallback_route_empty', '_debug_fallbackId' => $fallbackId, '_debug_fallbackRoute' => $fallbackRoute ?: 'NULL'];
            }
        } else {
            return ['fare' => 0, '_debug_why' => 'no_fallbackId'];
        }
    }

    $minimumFare = 15;

    $normalize = function($value){

        $value =
            strtolower(
                (string)$value
            );

$value = str_replace([
    ', nueva ecija',
    ', cabanatuan city',
    ', cabanatuan',
    ' city'
], '', $value);

        $value =
            preg_replace(
                '/[^a-z0-9 ]/',
                ' ',
                $value
            );

        $value =
            preg_replace(
                '/\s+/',
                ' ',
                $value
            );

        return trim($value);

    };

$findFareByDestination = function($location) use ($farePricing, $normalize){

    // Keep the original location for fallback checks
    $originalValue = strtolower(trim((string)$location));

    // Normalized version for matching
    $value = $normalize($location);

    // =========================
    // EXACT MATCH
    // =========================
    foreach($farePricing as $fp){

        if($normalize($fp['destination']) === $value){
            return $fp;
        }

    }

    // =========================
    // PARTIAL MATCH
    // =========================
    foreach($farePricing as $fp){

        $fpNorm = $normalize($fp['destination']);

        if(
            strpos($value, $fpNorm) !== false ||
            strpos($fpNorm, $value) !== false
        ){
            return $fp;
        }

    }

    // =========================
    // DESTINATION WORD MATCH
    // =========================
    foreach($farePricing as $fp){

        $fpNorm = $normalize($fp['destination']);
        $words = explode(' ', $fpNorm);

        foreach($words as $word){

            if(strlen($word) < 4){
                continue;
            }

            if(strpos($value, $word) !== false){
                return $fp;
            }

        }

    }

    // =========================
    // LOCATION WORD MATCH
    // =========================
    foreach($farePricing as $fp){

        $fpNorm = $normalize($fp['destination']);
        $words = explode(' ', $value);

        foreach($words as $word){

            if(strlen($word) < 4){
                continue;
            }

            if(strpos($fpNorm, $word) !== false){
                return $fp;
            }

        }

    }

    // =========================
    // CABANATUAN TERMINAL FALLBACK
    // =========================

    if (
        strpos($originalValue, 'cabanatuan') !== false
    ) {

        foreach($farePricing as $fp){

            $dest = strtolower($fp['destination']);

            if(
                strpos($dest, 'cabanatuan central terminal') !== false
            ){
                return $fp;
            }

        }

    }

    return null;

};


    $boardingStop =
        $findFareByDestination($from);

    $destinationStop =
        $findFareByDestination($to);

    // =========================
    // ROUTE STOP ID RESOLUTION
    // route_stops.fare_pricing_id is the preferred bridge (GPS → stop → fare).
    // If a stop id is provided and its fare_pricing_id is set, use it directly;
    // otherwise fall back to the stop name so matching still works.
    // =========================
    if (!$boardingStop && $fromStopId) {
        $boardingStop = resolveStopByRouteStopId($conn, $fromStopId, $farePricing, $findFareByDestination, $from);
    }
    if (!$destinationStop && $toStopId) {
        $destinationStop = resolveStopByRouteStopId($conn, $toStopId, $farePricing, $findFareByDestination, $to);
    }

    // Fallback: use IDs if name-based matching failed
    if(!$boardingStop && $fromId){
        foreach($farePricing as $fp){
            if((int)$fp['id'] === (int)$fromId){
                $boardingStop = $fp;
                break;
            }
        }
    }
    if(!$destinationStop && $toId){
        foreach($farePricing as $fp){
            if((int)$fp['id'] === (int)$toId){
                $destinationStop = $fp;
                break;
            }
        }
    }

    // Fallback: if one stop isn't found but names overlap, treat as same stop
    $fromNorm = $normalize($from);
    $toNorm = $normalize($to);

    if (
        !$boardingStop && $destinationStop &&
        (strpos($fromNorm, $toNorm) !== false || strpos($toNorm, $fromNorm) !== false)
    ) {
        $boardingStop = $destinationStop;
    } elseif (
        $boardingStop && !$destinationStop &&
        (strpos($fromNorm, $toNorm) !== false || strpos($toNorm, $fromNorm) !== false)
    ) {
        $destinationStop = $boardingStop;
    }

    // Same stop — no distance traveled, charge minimum fare
    if (
        $boardingStop &&
        $destinationStop &&
        (int) $boardingStop['id'] === (int) $destinationStop['id']
    ) {
        return ['fare' => $minimumFare];
    }

    // Same route_stops id — charge minimum fare
    if (
        $fromStopId &&
        $toStopId &&
        (int) $fromStopId === (int) $toStopId
    ) {
        return ['fare' => $minimumFare];
    }

    if(
        !$boardingStop ||
        !$destinationStop
    ){
        return [
            'fare' => 0,
            '_debug_why' => 'stop_not_found',
            '_debug_boarding_found' => $boardingStop ? $boardingStop['destination'] : 'NULL',
            '_debug_dest_found' => $destinationStop ? $destinationStop['destination'] : 'NULL',
            '_debug_farePricing_count' => count($farePricing),
            '_debug_toId' => $toId,
        ];
    }

    $fareColumn = match (strtolower($type)) {
        'student', 'senior', 'senior citizen', 'pwd' => 'student_fare',
        'teacher', 'nurse', 'special' => 'special_fare',
        default => 'regular_fare'
    };

    $boardingFare =
        (float)(
            $boardingStop[$fareColumn]
            ?? $minimumFare
        );

    $destinationFare =
        (float)(
            $destinationStop[$fareColumn]
            ?? $minimumFare
        );

    // Rule 1: Boarding at terminal (display_order = 1) → fare is destination fare.
    // The destination fare still respects the minimum fare: an unpopulated or
    // zero fare column must never surface as a zero fare (the passenger flow
    // reports a zero result as "fare unavailable").
    if((int)$boardingStop['display_order'] === 1){
        return [
            'fare' => max($destinationFare, $minimumFare),
            '_fare_column' => $fareColumn,
            '_boarding_fare' => $boardingFare,
            '_destination_fare' => $destinationFare,
            '_fare_rule' => 'terminal_origin',
        ];
    }

    // Rule 2: Boarding after terminal → fare = destination - boarding (min ₱15)
    $baseFare =
        $destinationFare -
        $boardingFare;

    return [
        'fare' =>
            max($baseFare, $minimumFare),
        '_fare_column' => $fareColumn,
        '_boarding_fare' => $boardingFare,
        '_destination_fare' => $destinationFare,
        '_fare_rule' => 'difference',
    ];
}

/**
 * Resolve a fare_pricing record from a route_stops id.
 *
 * Preference order:
 *   1. route_stops.fare_pricing_id (> 0) — the authoritative bridge.
 *   2. Stop name fuzzy matching via the fare engine's matcher.
 *   3. null (caller falls back to id/name matching as before).
 */
function resolveStopByRouteStopId($conn, $stopId, $farePricing, $findFareByDestination, $fallbackName)
{
    try {
        $stmt = $conn->prepare("
            SELECT id, route_name, stop_name, fare_pricing_id, sequence_order
            FROM route_stops
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$stopId]);
        $stop = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return null;
    }

    if (!$stop) {
        return null;
    }

    // Preferred bridge: fare_pricing_id directly points at a fare record.
    if ((int)$stop['fare_pricing_id'] > 0) {
        foreach ($farePricing as $fp) {
            if ((int)$fp['id'] === (int)$stop['fare_pricing_id']) {
                return $fp;
            }
        }
    }

    // Fallback: fuzzy match by stop name (same route only).
    $stopRoute = canonicalRouteName($stop['route_name'] ?? '');
    if ($stopRoute === '') {
        return null;
    }

    $sameRouteFares = array_values(array_filter($farePricing, function ($fp) use ($stopRoute) {
        return canonicalRouteName($fp['route_name'] ?? '') === $stopRoute;
    }));

    $matcher = function ($location) use ($sameRouteFares) {
        if (empty($sameRouteFares)) {
            return null;
        }

        $normalize = function ($value) {
            $value = strtolower((string)$value);
            $value = str_replace([
                ', nueva ecija',
                ', cabanatuan city',
                ', cabanatuan',
                ' city'
            ], '', $value);
            $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
            $value = preg_replace('/\s+/', ' ', $value);
            return trim($value);
        };

        $value = $normalize($location);

        foreach ($sameRouteFares as $fp) {
            if ($normalize($fp['destination']) === $value) {
                return $fp;
            }
        }

        foreach ($sameRouteFares as $fp) {
            $fpNorm = $normalize($fp['destination']);
            if (
                strpos($value, $fpNorm) !== false ||
                strpos($fpNorm, $value) !== false
            ) {
                return $fp;
            }
        }

        return null;
    };

    return $matcher($stop['stop_name']);
}
