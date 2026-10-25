<?php

/**
 * Shared destination-alert, continuation-journey, and outstanding-balance
 * logic for the passenger app (logged-in and One-Time flows).
 *
 * Design notes:
 * - Proximity/passed alerts are claimed atomically on the active trip row so
 *   each fires at most once per destination and survives refreshes/reconnects.
 * - A continuation is a new linked passenger_trips segment. The source
 *   segment keeps its original destination, fare, and payment status; it is
 *   finalized (dropoff) only when the passenger creates the continuation.
 * - Outstanding amounts are computed once here and reused by every balance
 *   display/gate so a carried balance is never double counted.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
if (!function_exists('calculateFare')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';
require_once __DIR__ . '/BoardingStopOrder.php';
require_once __DIR__ . '/StopCoordinates.php';
require_once __DIR__ . '/DestinationRouteGeometry.php';

use App\Core\Database;

class ContinuationJourneyService
{
    /** Distance (metres) at which the near-destination alert fires. */
    public const NEAR_THRESHOLD_METERS = 500.0;

    /** Distance (metres) at which the arrival alert fires (after near). */
    public const ARRIVAL_THRESHOLD_METERS = 150.0;

    /** Distance beyond which a growing distance means the stop was passed. */
    public const PASSED_FALLBACK_MARGIN_METERS = 200.0;

    /** Required growth between consecutive fixes for the fallback. */
    public const PASSED_GROWTH_METERS = 50.0;

    /**
     * Farthest distance from which a growing-distance trend may start. A
     * passenger beyond this who keeps moving away never entered the
     * destination area, so the trend does not start.
     */
    public const PASSED_TREND_START_MAX_METERS = 300.0;

    /** Consecutive growing-distance fixes required before passed is claimed. */
    public const PASSED_TREND_REQUIRED = 2;

    /**
     * Fixes whose reported accuracy is worse than this are too unreliable to
     * judge passed-destination movement; they neither advance nor start the
     * distance trend.
     */
    public const PASSED_ACCURACY_GATE_METERS = 150.0;

    /**
     * Route progress (metres) beyond the destination's own route position
     * before the route-aware passed rule fires.
     */
    public const PASSED_PROGRESS_MARGIN_METERS = 80.0;

    /**
     * A GPS fix farther than this from the route polyline is not reliable
     * enough to use for route-aware passed detection.
     */
    public const ROUTE_PROJECTION_MAX_OFFSET_METERS = 500.0;

    /** Trip columns the alert claims may write (whitelist). */
    public const ALERT_COLUMNS = [
        'destination_near_alerted',
        'destination_arrival_alerted',
        'destination_passed_alerted',
    ];

    // =====================================================================
    // Balance math (pure)
    // =====================================================================

    /**
     * The amount still owed on a journey: zero when already paid, otherwise
     * the fare minus any recorded paid amount (full fare when unpaid).
     */
    public static function previousOutstanding(array $trip)
    {
        $status = strtolower(trim((string) ($trip['payment_status'] ?? '')));
        if ($status === 'paid') {
            return 0.0;
        }

        $fare = self::moneyValue($trip['fare'] ?? 0);
        $paid = self::moneyValue($trip['paid_amount'] ?? 0);

        return max(0.0, round($fare - $paid, 2));
    }

    /**
     * Total due for a journey row: segment fare plus any carried balance,
     * minus an amount recorded paid on the row itself.
     */
    public static function totalDue(array $trip)
    {
        $fare = self::moneyValue($trip['fare'] ?? 0);
        $carried = self::moneyValue($trip['carried_balance'] ?? 0);
        $paid = self::moneyValue($trip['paid_amount'] ?? 0);

        return max(0.0, round($fare + $carried - $paid, 2));
    }

    /**
     * The amount a continuation must carry forward from its source journey:
     * zero when the source is paid, otherwise the source's full unpaid total
     * (which already includes anything the source itself carried). This keeps
     * a chain of missed destinations from losing an earlier balance.
     */
    public static function carriedOutstanding(array $trip)
    {
        if (strtolower(trim((string) ($trip['payment_status'] ?? ''))) === 'paid') {
            return 0.0;
        }

        return self::totalDue($trip);
    }

    private static function moneyValue($value)
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    // =====================================================================
    // Outstanding journeys (shared by the balance displays and boarding gate)
    // =====================================================================

    /**
     * Unpaid finished journeys for a passenger, with carried balances folded
     * in once: a journey that was carried into a continuation is excluded and
     * its continuation row (which stores the carried balance) is counted
     * instead, so the chain totals exactly one amount.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function outstandingTrips(PDO $conn, $passengerId)
    {
        $passengerId = (int) $passengerId;
        if ($passengerId <= 0) {
            return [];
        }

        $where = "
            WHERE pt.passenger_id = ?
            AND LOWER(COALESCE(pt.payment_status, 'pending')) <> 'paid'
            AND (
                pt.dropoff_time IS NOT NULL
                OR LOWER(COALESCE(pt.trip_status, '')) = 'completed'
                OR LOWER(COALESCE(bt.status, '')) IN ('completed', 'cancelled')
            )
            AND NOT EXISTS (
                SELECT 1 FROM passenger_trips c WHERE c.continuation_of_trip_id = pt.id
            )
            ORDER BY pt.id ASC
        ";

        $rows = null;

        try {
            $stmt = $conn->prepare("
                SELECT pt.id, pt.fare, pt.cash_amount, pt.gcash_amount, pt.payment_method,
                       pt.payment_status, pt.boarding_point, pt.destination, pt.boarding_time,
                       pt.continuation_of_trip_id, pt.carried_balance, pt.paid_amount
                FROM passenger_trips pt
                LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
                " . $where);
            $stmt->execute([$passengerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // The continuation columns are lazily added; a database where the
            // ALTER has not run yet must still report its existing balance.
            try {
                $stmt = $conn->prepare("
                    SELECT pt.id, pt.fare, pt.cash_amount, pt.gcash_amount, pt.payment_method,
                           pt.payment_status, pt.boarding_point, pt.destination, pt.boarding_time,
                           pt.continuation_of_trip_id,
                           NULL AS carried_balance, NULL AS paid_amount
                    FROM passenger_trips pt
                    LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
                    " . $where);
                $stmt->execute([$passengerId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $fallbackError) {
                return [];
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $row['total_due'] = self::totalDue($row);
            $row['is_continuation'] = (int) ($row['continuation_of_trip_id'] ?? 0) > 0;
            $result[] = $row;
        }

        return $result;
    }

    public static function outstandingCount(PDO $conn, $passengerId)
    {
        return count(self::outstandingTrips($conn, $passengerId));
    }

    public static function outstandingTotal(PDO $conn, $passengerId)
    {
        $total = 0.0;
        foreach (self::outstandingTrips($conn, $passengerId) as $row) {
            $total += (float) $row['total_due'];
        }

        return round($total, 2);
    }

    /**
     * Outstanding totals for many passengers in one query, keyed by passenger
     * id. Mirrors outstandingTrips() rules exactly: only finished trips count,
     * anything not marked paid owes, continuation chains count once, and the
     * amount is fare + carried balance - paid amount (never below zero).
     * Passengers with no matching trip are absent from the result (owe 0).
     *
     * List views cannot afford one outstandingTrips() call per row, so they
     * use this grouped form of the same calculation.
     *
     * @param array<int,int|string> $passengerIds
     * @return array<int,float> passenger id => outstanding amount
     */
    public static function outstandingTotalsByPassenger(PDO $conn, array $passengerIds)
    {
        $ids = [];
        foreach ($passengerIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (!$ids) {
            return [];
        }

        $totals = [];
        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $rows = null;
            try {
                $rows = self::queryOutstandingTotals($conn, $chunk, true);
            } catch (\Throwable $e) {
                // Continuation columns are lazily added; a database without
                // them must still report its existing fare-only balance.
                try {
                    $rows = self::queryOutstandingTotals($conn, $chunk, false);
                } catch (\Throwable $fallbackError) {
                    continue;
                }
            }

            foreach ($rows as $row) {
                $totals[(int) $row['passenger_id']] = round((float) $row['outstanding'], 2);
            }
        }

        return $totals;
    }

    /**
     * @param array<int,int> $chunk positive passenger ids
     * @return array<int,array<string,mixed>>
     */
    private static function queryOutstandingTotals(PDO $conn, array $chunk, $withContinuationColumns)
    {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $due = $withContinuationColumns
            ? '(COALESCE(pt.fare, 0) + COALESCE(pt.carried_balance, 0) - COALESCE(pt.paid_amount, 0))'
            : 'COALESCE(pt.fare, 0)';

        $stmt = $conn->prepare("
            SELECT pt.passenger_id,
                   SUM(CASE WHEN {$due} > 0 THEN {$due} ELSE 0 END) AS outstanding
            FROM passenger_trips pt
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            WHERE pt.passenger_id IN ({$placeholders})
              AND LOWER(COALESCE(pt.payment_status, 'pending')) <> 'paid'
              AND (
                  pt.dropoff_time IS NOT NULL
                  OR LOWER(COALESCE(pt.trip_status, '')) = 'completed'
                  OR LOWER(COALESCE(bt.status, '')) IN ('completed', 'cancelled')
              )
              AND NOT EXISTS (
                  SELECT 1 FROM passenger_trips c WHERE c.continuation_of_trip_id = pt.id
              )
            GROUP BY pt.passenger_id
        ");
        $stmt->execute(array_values($chunk));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =====================================================================
    // Route/stop resolution
    // =====================================================================

    /**
     * The trip's authoritative route name (bus_trips first, stored route
     * second), matching how destination selection resolves it.
     */
    public static function resolveTripRouteName(PDO $conn, array $trip)
    {
        $routeName = '';

        $tripNo = (int) ($trip['trip_no'] ?? 0);
        if ($tripNo > 0) {
            try {
                $stmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE id = ? LIMIT 1");
                $stmt->execute([$tripNo]);
                $routeName = trim((string) $stmt->fetchColumn());
            } catch (\Throwable $e) {
                $routeName = '';
            }
        }

        if ($routeName === '') {
            $routeName = trim((string) ($trip['route_name'] ?? ''));
        }

        return $routeName;
    }

    /**
     * The journey's current destination as a fare zone (id, name, order).
     * Prefers the stored destination id, falling back to the stop name.
     *
     * @return array{id:int,destination:string,display_order:int}|null
     */
    public static function resolveJourneyDestinationZone(PDO $conn, array $trip, $routeName)
    {
        $destinationId = (int) ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0));
        $destinationName = trim((string) ($trip['selected_destination'] ?? ($trip['destination'] ?? '')));

        if ($destinationId > 0) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, destination, display_order, route_name
                    FROM fare_pricing
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$destinationId]);
                $zone = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($zone && passengerFareZoneMatchesRoute($zone['route_name'] ?? '', $routeName)) {
                    return [
                        'id' => (int) $zone['id'],
                        'destination' => (string) $zone['destination'],
                        'display_order' => (int) $zone['display_order'],
                    ];
                }
            } catch (\Throwable $e) {
                // Fall through to the name matcher.
            }
        }

        if ($destinationName !== '') {
            return passengerResolveBoardingFareStop($conn, $routeName, $destinationName);
        }

        return null;
    }

    /**
     * route_stops row for a fare zone, preferring the fare_pricing_id bridge
     * and falling back to a route-scoped name match.
     *
     * @return array<string,mixed>|null
     */
    public static function resolveRouteStopForFare(PDO $conn, $routeName, $farePricingId, $stopName = '')
    {
        $farePricingId = (int) $farePricingId;

        if ($farePricingId > 0) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
                    FROM route_stops
                    WHERE fare_pricing_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$farePricingId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($row && passengerFareZoneMatchesRoute($row['route_name'] ?? '', $routeName)) {
                    return $row;
                }
            } catch (\Throwable $e) {
                // Fall through to the name lookup.
            }
        }

        $stopName = trim((string) $stopName);
        if ($stopName === '') {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
                FROM route_stops
                WHERE LOWER(TRIM(stop_name)) = LOWER(TRIM(?))
                ORDER BY id ASC
                LIMIT 5
            ");
            $stmt->execute([$stopName]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return null;
        }

        $canonical = canonicalRouteName($routeName);
        foreach ($candidates as $candidate) {
            if (canonicalRouteName($candidate['route_name'] ?? '') === $canonical) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The route's stops that lie strictly after the boarding point on the
     * trip's route, each priced for the passenger's type through the existing
     * fare engine. Stops at or behind the boarding point are never returned.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function remainingDestinations(PDO $conn, array $sourceTrip)
    {
        $routeName = self::resolveTripRouteName($conn, $sourceTrip);
        if ($routeName === '') {
            return [];
        }

        $boardingZone = self::resolveJourneyDestinationZone($conn, $sourceTrip, $routeName);
        if ($boardingZone === null) {
            return [];
        }

        $boardingRouteStop = self::resolveRouteStopForFare(
            $conn,
            $routeName,
            (int) $boardingZone['id'],
            (string) $boardingZone['destination']
        );

        $tripNo = (int) ($sourceTrip['trip_no'] ?? 0);
        $discountType = self::passengerDiscountType($conn, (int) ($sourceTrip['passenger_id'] ?? 0));

        $fareRouteName = function_exists('resolveFareRouteName')
            ? resolveFareRouteName($conn, $routeName)
            : $routeName;

        try {
            $stmt = $conn->prepare("
                SELECT id, destination, display_order, regular_fare, student_fare, special_fare, route_name
                FROM fare_pricing
                WHERE route_name = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$fareRouteName]);
            $fareStops = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $remaining = self::filterRemainingStops($fareStops, (int) $boardingZone['display_order']);

        $result = [];
        foreach ($remaining as $stop) {
            $destinationId = (int) $stop['id'];
            $destinationRouteStop = self::resolveRouteStopForFare(
                $conn,
                $routeName,
                $destinationId,
                (string) $stop['destination']
            );

            $destinationStopId = $destinationRouteStop ? (int) $destinationRouteStop['id'] : null;

            try {
                $baseResult = calculateFare(
                    $tripNo,
                    (string) $boardingZone['destination'],
                    (string) $stop['destination'],
                    'regular',
                    (int) $boardingZone['id'],
                    $destinationId,
                    $boardingRouteStop ? (int) $boardingRouteStop['id'] : null,
                    $destinationStopId
                );

                $fareResult = calculateFare(
                    $tripNo,
                    (string) $boardingZone['destination'],
                    (string) $stop['destination'],
                    $discountType,
                    (int) $boardingZone['id'],
                    $destinationId,
                    $boardingRouteStop ? (int) $boardingRouteStop['id'] : null,
                    $destinationStopId
                );
            } catch (\Throwable $e) {
                continue;
            }

            $baseFare = (float) ($baseResult['fare'] ?? 0);
            $fare = (float) ($fareResult['fare'] ?? 0);
            if ($baseFare <= 0) {
                $baseFare = $fare;
            }

            if ($fare <= 0) {
                continue;
            }

            $result[] = [
                'id' => $destinationId,
                'destination' => (string) $stop['destination'],
                'display_order' => (int) $stop['display_order'],
                'fare' => round($fare, 2),
                'base_fare' => round($baseFare, 2),
                'discount_amount' => round(max($baseFare - $fare, 0), 2),
                'km_marker' => isset($stop['km_marker']) && $stop['km_marker'] !== null && $stop['km_marker'] !== ''
                    ? (int) $stop['km_marker']
                    : ($destinationRouteStop && $destinationRouteStop['km_marker'] !== null
                        ? (int) $destinationRouteStop['km_marker']
                        : null),
                'route_stop_id' => $destinationStopId,
                'latitude' => $destinationRouteStop && $destinationRouteStop['latitude'] !== null
                    ? (float) $destinationRouteStop['latitude']
                    : null,
                'longitude' => $destinationRouteStop && $destinationRouteStop['longitude'] !== null
                    ? (float) $destinationRouteStop['longitude']
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Keep only stops strictly after the boarding order. Pure so the
     * remaining-route rule can be verified without a database.
     *
     * @param array<int,array<string,mixed>> $stops
     * @return array<int,array<string,mixed>>
     */
    public static function filterRemainingStops(array $stops, $boardingOrder)
    {
        $boardingOrder = (int) $boardingOrder;
        $remaining = [];

        foreach ($stops as $stop) {
            if ((int) ($stop['display_order'] ?? 0) > $boardingOrder) {
                $remaining[] = $stop;
            }
        }

        return array_values($remaining);
    }

    private static function passengerDiscountType(PDO $conn, $passengerId)
    {
        $passengerId = (int) $passengerId;
        if ($passengerId <= 0) {
            return 'regular';
        }

        try {
            $stmt = $conn->prepare("SELECT discount_type FROM passengers WHERE id = ? LIMIT 1");
            $stmt->execute([$passengerId]);
            $discount = strtolower(trim((string) $stmt->fetchColumn()));
        } catch (\Throwable $e) {
            $discount = '';
        }

        return $discount !== '' ? $discount : 'regular';
    }

    // =====================================================================
    // Continuation creation
    // =====================================================================

    /**
     * The newest in-progress journey for a passenger, shared by the
     * logged-in and One-Time continuation surfaces.
     *
     * @return array<string,mixed>|null
     */
    public static function activeTripForPassenger(PDO $conn, $passengerId)
    {
        $passengerId = (int) $passengerId;
        if ($passengerId <= 0) {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT pt.*, bt.route_name AS bus_route_name
                FROM passenger_trips pt
                LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
                WHERE pt.passenger_id = ?
                AND pt.dropoff_time IS NULL
                AND (
                    pt.trip_status IS NULL
                    OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled')
                )
                AND (
                    bt.id IS NULL
                    OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
                )
                ORDER BY pt.id DESC
                LIMIT 1
            ");
            $stmt->execute([$passengerId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        return $trip ?: null;
    }

    public static function continuationForSource(PDO $conn, $sourceTripId)
    {
        $sourceTripId = (int) $sourceTripId;
        if ($sourceTripId <= 0) {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT * FROM passenger_trips
                WHERE continuation_of_trip_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$sourceTripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        return $row ?: null;
    }

    public static function continuationByToken(PDO $conn, $token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT * FROM passenger_trips
                WHERE continuation_token = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        return $row ?: null;
    }

    /**
     * Status/options payload for the continuation screen, shared by the
     * logged-in and One-Time surfaces.
     *
     * @return array<string,mixed>
     */
    public static function statusPayload(PDO $conn, array $trip)
    {
        if (empty($trip['destination_passed_alerted'])) {
            return [
                'success' => true,
                'needs_continuation' => false,
                'trip_id' => (int) ($trip['id'] ?? 0),
            ];
        }

        $missedDestination = trim((string) ($trip['selected_destination'] ?? ($trip['destination'] ?? '')));
        $previousOutstanding = self::carriedOutstanding($trip);

        $options = [];
        foreach (self::remainingDestinations($conn, $trip) as $destination) {
            $options[] = [
                'id' => (int) $destination['id'],
                'destination' => (string) $destination['destination'],
                'fare' => (float) $destination['fare'],
                'base_fare' => (float) $destination['base_fare'],
                'discount_amount' => (float) $destination['discount_amount'],
            ];
        }

        return [
            'success' => true,
            'needs_continuation' => true,
            'trip_id' => (int) ($trip['id'] ?? 0),
            'missed_destination' => $missedDestination,
            'new_boarding_point' => $missedDestination,
            'previous_outstanding' => $previousOutstanding,
            'destinations' => $options,
            'payment_summary' => [
                'previous_outstanding' => $previousOutstanding,
                'continuation_fare' => null,
                'total_due' => null,
            ],
        ];
    }

    /**
     * Create the continuation segment for a missed destination.
     *
     * Idempotent: a continuation already linked to the source journey or the
     * same idempotency token returns the existing row with duplicate=true.
     *
     * @return array{success:bool,code?:string,message?:string,duplicate?:bool,data?:array}
     */
    public static function createContinuation(PDO $conn, array $sourceTrip, $newDestinationId, $token)
    {
        $sourceTripId = (int) ($sourceTrip['id'] ?? 0);
        $newDestinationId = (int) $newDestinationId;
        $token = trim((string) $token);

        if ($sourceTripId <= 0) {
            return self::fail('NO_ACTIVE_TRIP', 'No in-progress trip found.');
        }

        if (
            $token === ''
            || strlen($token) < 8
            || strlen($token) > 64
            || !preg_match('/^[A-Za-z0-9_\-]+$/', $token)
        ) {
            return self::fail('INVALID_SUBMISSION', 'We could not confirm this submission. Please try again.');
        }

        if ($newDestinationId <= 0) {
            return self::fail('INVALID_DESTINATION', 'Please choose your new destination.');
        }

        // Serialize concurrent continuations for this passenger. GET_LOCK is
        // MySQL-specific; when unavailable the source/token checks plus the
        // unique indexes still guarantee one continuation per event.
        $lockName = 'passenger_continuation_' . (int) ($sourceTrip['passenger_id'] ?? 0);
        $lockAcquired = false;
        try {
            $stmt = $conn->prepare("SELECT GET_LOCK(?, 5)");
            $stmt->execute([$lockName]);
            $lockAcquired = ((int) $stmt->fetchColumn()) === 1;
        } catch (\Throwable $e) {
            $lockAcquired = false;
        }
        if ($lockAcquired) {
            register_shutdown_function(function () use ($conn, $lockName) {
                try {
                    $conn->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
                } catch (\Throwable $e) {
                    // Best effort; the connection closes anyway.
                }
            });
        }

        $existing = self::continuationForSource($conn, $sourceTripId);
        if ($existing) {
            return self::duplicateResult($existing, $sourceTrip);
        }

        $existing = self::continuationByToken($conn, $token);
        if ($existing) {
            return self::duplicateResult($existing, $sourceTrip);
        }

        $routeName = self::resolveTripRouteName($conn, $sourceTrip);
        if ($routeName === '') {
            return self::fail('TRIP_ROUTE_MISSING', 'This trip has no route assigned yet. Please try again later.');
        }

        $boardingZone = self::resolveJourneyDestinationZone($conn, $sourceTrip, $routeName);
        if ($boardingZone === null) {
            return self::fail('BOARDING_ZONE_UNAVAILABLE', 'We could not determine your boarding point right now. Please try again.');
        }

        $options = self::remainingDestinations($conn, $sourceTrip);

        $chosen = null;
        foreach ($options as $option) {
            if ((int) $option['id'] === $newDestinationId) {
                $chosen = $option;
                break;
            }
        }

        if ($chosen === null) {
            return self::fail(
                'DESTINATION_NOT_AHEAD',
                'That stop is behind your new boarding point. Please choose a stop ahead of it.'
            );
        }

        $boardingRouteStop = self::resolveRouteStopForFare(
            $conn,
            $routeName,
            (int) $boardingZone['id'],
            (string) $boardingZone['destination']
        );

        $newBoardingPoint = (string) $boardingZone['destination'];
        $newBoardingStopId = $boardingRouteStop ? (int) $boardingRouteStop['id'] : null;
        $newBoardingKm = $boardingRouteStop && $boardingRouteStop['km_marker'] !== null
            ? (int) $boardingRouteStop['km_marker']
            : null;

        $newDestinationName = (string) $chosen['destination'];
        $newDestinationStopId = isset($chosen['route_stop_id']) ? (int) $chosen['route_stop_id'] : null;
        $newDestinationKm = $chosen['km_marker'] !== null ? (int) $chosen['km_marker'] : null;

        $plannedLat = $chosen['latitude'] !== null ? (float) $chosen['latitude'] : null;
        $plannedLng = $chosen['longitude'] !== null ? (float) $chosen['longitude'] : null;

        if ($plannedLat === null || $plannedLng === null) {
            $directionKey = function_exists('passengerStopCoordsDirectionKey')
                ? passengerStopCoordsDirectionKey($routeName)
                : null;
            $reference = $directionKey !== null
                ? passengerStopCoordsLookup($newDestinationName, $directionKey)
                : null;
            if ($reference !== null) {
                $plannedLat = (float) $reference['lat'];
                $plannedLng = (float) $reference['lng'];
            }
        }

        $carriedBalance = self::carriedOutstanding($sourceTrip);
        $fare = (float) $chosen['fare'];
        $baseFare = (float) $chosen['base_fare'];
        $discountAmount = (float) $chosen['discount_amount'];

        // Last known GPS for the new segment's starting position (best effort).
        $lastLat = null;
        $lastLng = null;
        try {
            $stmt = $conn->prepare("
                SELECT latitude, longitude
                FROM passenger_location_history
                WHERE passenger_trip_id = ?
                AND latitude IS NOT NULL AND longitude IS NOT NULL
                ORDER BY recorded_at DESC, location_id DESC
                LIMIT 1
            ");
            $stmt->execute([$sourceTripId]);
            $lastGps = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($lastGps) {
                $lastLat = (float) $lastGps['latitude'];
                $lastLng = (float) $lastGps['longitude'];
            }
        } catch (\Throwable $e) {
            $lastLat = null;
            $lastLng = null;
        }

        if ($lastLat === null || $lastLng === null) {
            $lastLat = isset($sourceTrip['latitude']) && is_numeric($sourceTrip['latitude'])
                ? (float) $sourceTrip['latitude']
                : null;
            $lastLng = isset($sourceTrip['longitude']) && is_numeric($sourceTrip['longitude'])
                ? (float) $sourceTrip['longitude']
                : null;
        }

        $conn->beginTransaction();

        try {
            // Re-check inside the transaction: the other request may have won
            // between the pre-check and the lock.
            $existing = self::continuationForSource($conn, $sourceTripId);
            if ($existing) {
                $conn->rollBack();
                return self::duplicateResult($existing, $sourceTrip);
            }

            $now = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("
                INSERT INTO passenger_trips
                (
                    passenger_id,
                    bus_no,
                    trip_no,
                    driver_id,
                    passenger_type,
                    boarding_point,
                    boarding_stop_id,
                    boarding_km_marker,
                    destination,
                    destination_id,
                    selected_destination,
                    selected_destination_id,
                    selected_km_marker,
                    estimated_fare,
                    fare,
                    base_fare,
                    discount_amount,
                    payment_method,
                    payment_status,
                    trip_status,
                    latitude,
                    longitude,
                    boarding_time,
                    identification_source,
                    declared_id_number,
                    declared_id_status,
                    continuation_of_trip_id,
                    continuation_token,
                    carried_balance,
                    planned_dropoff_lat,
                    planned_dropoff_lng,
                    planned_dropoff_name,
                    planned_dropoff_set_at
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'In Progress', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                (int) $sourceTrip['passenger_id'],
                $sourceTrip['bus_no'] ?? null,
                $sourceTrip['trip_no'] ?? null,
                $sourceTrip['driver_id'] ?? null,
                $sourceTrip['passenger_type'] ?? null,
                $newBoardingPoint,
                $newBoardingStopId,
                $newBoardingKm,
                $newDestinationName,
                $newDestinationId,
                $newDestinationName,
                $newDestinationId,
                $newDestinationKm,
                $fare,
                $fare,
                $baseFare,
                $discountAmount,
                $sourceTrip['payment_method'] ?? null,
                $lastLat,
                $lastLng,
                $now,
                $sourceTrip['identification_source'] ?? null,
                $sourceTrip['declared_id_number'] ?? null,
                $sourceTrip['declared_id_status'] ?? null,
                $sourceTripId,
                $token,
                $carriedBalance,
                $plannedLat,
                $plannedLng,
                $plannedLat !== null ? $newDestinationName : null,
                $now,
            ]);

            $continuationId = (int) $conn->lastInsertId();

            // Finalize the source segment at the missed destination. Its
            // destination, fare, base fare, discount, and payment status are
            // deliberately untouched.
            $stmt = $conn->prepare("
                UPDATE passenger_trips
                SET dropoff_time = COALESCE(dropoff_time, ?),
                    trip_status = 'Completed',
                    actual_stop_id = ?,
                    actual_km_marker = ?
                WHERE id = ? AND dropoff_time IS NULL
            ");
            $stmt->execute([
                $now,
                $newBoardingStopId,
                $newBoardingKm,
                $sourceTripId,
            ]);

            // Seed the continuation's location history from the last fix so
            // tracking and the route map continue seamlessly (best effort).
            if ($lastLat !== null && $lastLng !== null) {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO passenger_location_history
                        (
                            passenger_trip_id,
                            first_latitude,
                            first_longitude,
                            first_recorded_at,
                            latitude,
                            longitude,
                            recorded_at
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$continuationId, $lastLat, $lastLng, $now, $lastLat, $lastLng, $now]);
                } catch (\Throwable $e) {
                    // Location history is best effort; never fail the journey.
                }
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            // Unique-index race: another request created the continuation.
            if (strpos((string) $e->getCode(), '23000') !== false) {
                $existing = self::continuationForSource($conn, $sourceTripId);
                if ($existing) {
                    return self::duplicateResult($existing, $sourceTrip);
                }
            }

            return self::fail('SAVE_FAILED', 'We could not start your continuation journey. Please try again.');
        }

        $continuation = self::continuationForSource($conn, $sourceTripId);

        return [
            'success' => true,
            'duplicate' => false,
            'data' => self::continuationPayload($continuation ?: [], $sourceTrip),
        ];
    }

    /**
     * Response payload for a continuation row (also used for duplicate
     * submissions so the passenger always sees the same result).
     *
     * @return array<string,mixed>
     */
    public static function continuationPayload(array $continuation, array $sourceTrip = [])
    {
        $previousDestination = trim((string) (
            $sourceTrip['selected_destination']
            ?? $sourceTrip['destination']
            ?? ($continuation['boarding_point'] ?? '')
        ));

        $newDestination = trim((string) (
            $continuation['selected_destination']
            ?? $continuation['destination']
            ?? ''
        ));

        $planned = null;
        if (
            isset($continuation['planned_dropoff_lat'], $continuation['planned_dropoff_lng'])
            && is_numeric($continuation['planned_dropoff_lat'])
            && is_numeric($continuation['planned_dropoff_lng'])
        ) {
            $planned = [
                'latitude' => (float) $continuation['planned_dropoff_lat'],
                'longitude' => (float) $continuation['planned_dropoff_lng'],
                'name' => $continuation['planned_dropoff_name'] ?? null,
            ];
        }

        return [
            'trip_id' => (int) ($continuation['id'] ?? 0),
            'source_trip_id' => (int) ($continuation['continuation_of_trip_id'] ?? 0),
            'bus_no' => $continuation['bus_no'] ?? null,
            'trip_no' => $continuation['trip_no'] ?? null,
            'previous_destination' => $previousDestination,
            'new_boarding_point' => $continuation['boarding_point'] ?? $previousDestination,
            'new_destination' => $newDestination,
            'new_destination_id' => (int) ($continuation['selected_destination_id'] ?? ($continuation['destination_id'] ?? 0)),
            'fare' => round((float) ($continuation['fare'] ?? 0), 2),
            'base_fare' => round((float) ($continuation['base_fare'] ?? 0), 2),
            'discount_amount' => round((float) ($continuation['discount_amount'] ?? 0), 2),
            'previous_outstanding' => round((float) ($continuation['carried_balance'] ?? 0), 2),
            'total_due' => self::totalDue($continuation),
            'payment_method' => $continuation['payment_method'] ?? null,
            'payment_status' => $continuation['payment_status'] ?? 'pending',
            'planned_dropoff' => $planned,
        ];
    }

    private static function duplicateResult(array $continuation, array $sourceTrip)
    {
        return [
            'success' => true,
            'duplicate' => true,
            'data' => self::continuationPayload($continuation, $sourceTrip),
        ];
    }

    private static function fail($code, $message)
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];
    }

    // =====================================================================
    // Destination alerts (proximity + passed)
    // =====================================================================

    public static function haversineMeters($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * The named destination's coordinate from the existing R-Connect route
     * data, for the trip's direction.
     *
     * The trip's own direction is tried first so a name that exists in both
     * directions resolves to the one being travelled; every other direction is
     * then scanned, because a route whose canonical name is not a direction key
     * (for example "Rizal Route 1") still has exactly one Ricarte entry and that
     * entry is the same physical stop in either direction.
     *
     * @return array{name:string,lat:float,lng:float,direction:string}|null
     */
    public static function destinationRouteAnchor(PDO $conn, array $trip, $routeName, $destinationName = '', $destinationId = 0)
    {
        $destinationName = trim((string) $destinationName);
        if ($destinationName === '') {
            $destinationName = trim((string) ($trip['planned_dropoff_name'] ?? ''));
        }
        if ($destinationName === '') {
            $destinationName = trim((string) ($trip['destination_name'] ?? ($trip['selected_destination'] ?? ($trip['destination'] ?? ''))));
        }

        $destinationId = (int) $destinationId;
        if ($destinationId <= 0) {
            $destinationId = (int) ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0));
        }

        if ($destinationName === '' && $destinationId === 113) {
            $destinationName = 'Ricarte, Llanera';
        }

        if ($destinationName === '' && $destinationId <= 0) {
            return null;
        }

        $keys = [];
        $canonical = canonicalRouteName($routeName);

        if ($canonical !== '' && in_array($canonical, passengerDestinationRouteDirectionKeys(), true)) {
            $keys[] = $canonical;
        }
        foreach (passengerDestinationRouteDirectionKeys() as $key) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            $anchor = null;
            if ($destinationId > 0 && function_exists('passengerDestinationRouteAnchorById')) {
                $anchor = passengerDestinationRouteAnchorById($key, $destinationId);
            }
            if ($anchor === null && $destinationName !== '' && function_exists('passengerDestinationRouteAnchorByName')) {
                $anchor = passengerDestinationRouteAnchorByName($key, $destinationName);
            }

            if ($anchor !== null) {
                return [
                    'name' => (string) $anchor['name'],
                    'lat' => (float) $anchor['lat'],
                    'lng' => (float) $anchor['lng'],
                    'direction' => $key,
                ];
            }
        }

        return null;
    }

    /**
     * Coordinates of the passenger's current destination, for proximity
     * monitoring.
     *
     * Two separate questions are answered here and they are answered by
     * different data on purpose.
     *
     * WHICH destination: passenger_trips, and nothing else. The planned
     * get-off name, the selected_destination_id the passenger locked when
     * choosing from the destination list, and the selected_destination name all
     * come off the trip. The name is never rewritten and no other destination is
     * ever substituted for it.
     *
     * WHERE that destination is: the existing R-Connect route data. A trip that
     * names a stop the route data knows is resolved to that stop's supplied
     * route coordinate, because that is where the bus actually stops.
     *
     * The ordering matters and used to be the bug. planned_dropoff_lat/lng and
     * the route_stops row for the selected id were both tried first, and both
     * can hold a coarse or stale coordinate: the map picker's destination area
     * is itself derived from a rounded reference point, and a route_stops row
     * carries whatever was last typed in. For Ricarte, Llanera that value is
     * 15.628 / 121.015, which is 631 m from the supplied route coordinate
     * 15.633678 / 121.014894. A bus standing exactly on the Ricarte route stop
     * therefore read as 631 m away, which is past the 500 m nearby threshold, so
     * no proximity alert could ever fire no matter how correct the distance
     * calculation was. Resolving the coordinate from the route data first fixes
     * that at the source instead of widening the radius to hide it.
     *
     * The remaining sources are fallbacks for a destination the route data does
     * not know by name (a custom point, a newly added stop), where the stored
     * coordinate is the only evidence there is. Returns null when nothing
     * resolves, which stops monitoring for that trip without touching anything
     * else.
     *
     * @return array{lat:float,lng:float,name:string,source:string,destination_id?:int,coordinate_drift_m?:float,superseded_source?:string}
     */
    public static function destinationCoordinates(PDO $conn, array $trip, $routeName)
    {
        $destinationName = trim((string) ($trip['planned_dropoff_name'] ?? ''));
        if ($destinationName === '') {
            $destinationName = trim((string) ($trip['destination_name'] ?? ($trip['selected_destination'] ?? ($trip['destination'] ?? ''))));
        }

        $destinationId = (int) ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0));

        if ($destinationName === '' && $destinationId === 113) {
            $destinationName = 'Ricarte, Llanera';
        }

        // 1. The R-Connect route coordinate for the destination the trip names.
        //    This is the same coordinate the route data uses for movement
        //    detection, so "has the bus arrived" and "where is the destination"
        //    are finally measured against the same point.
        $anchor = self::destinationRouteAnchor($conn, $trip, $routeName, $destinationName, $destinationId);

        // Whatever the coarser sources would have said, kept for the diagnostic
        // so a coordinate change is always explainable rather than silent.
        $superseded = self::coarseDestinationCoordinate($conn, $trip, $routeName, $destinationName, $destinationId);

        if ($anchor !== null) {
            $drift = null;
            if ($superseded !== null) {
                $drift = self::haversineMeters(
                    (float) $superseded['lat'],
                    (float) $superseded['lng'],
                    $anchor['lat'],
                    $anchor['lng']
                );
            }

            return [
                'lat' => $anchor['lat'],
                'lng' => $anchor['lng'],
                'name' => $destinationName !== ''
                    ? $destinationName
                    : (string) $anchor['name'],
                'source' => 'route_destination',
                'destination_id' => $destinationId,
                'coordinate_drift_m' => $drift,
                'superseded_source' => $superseded !== null ? $superseded['source'] : null,
            ];
        }

        // 2..n. The destination is not one the route data knows, so the stored
        // coordinates are the only evidence available.
        if (is_array($superseded)) {
            return $superseded + [
                'destination_id' => $destinationId,
            ];
        }

        return null;
    }

    /**
     * The coarse stored coordinate for a destination: the map picker's planned
     * get-off point, then the selected destination's route_stops row, then the
     * route's name match, then the direction's reference table.
     *
     * @return array{lat:float,lng:float,name:string,source:string}|null
     */
    private static function coarseDestinationCoordinate(PDO $conn, array $trip, $routeName, $destinationName, $destinationId)
    {
        // 1. Planned get-off point from the destination map.
        $plannedLat = isset($trip['planned_dropoff_lat']) && is_numeric($trip['planned_dropoff_lat'])
            ? (float) $trip['planned_dropoff_lat']
            : null;
        $plannedLng = isset($trip['planned_dropoff_lng']) && is_numeric($trip['planned_dropoff_lng'])
            ? (float) $trip['planned_dropoff_lng']
            : null;

        if (
            $plannedLat !== null && $plannedLng !== null
            && is_finite($plannedLat) && is_finite($plannedLng)
            && $plannedLat >= -90 && $plannedLat <= 90
            && $plannedLng >= -180 && $plannedLng <= 180
        ) {
            return [
                'lat' => $plannedLat,
                'lng' => $plannedLng,
                'name' => trim((string) ($trip['planned_dropoff_name'] ?? '')) !== ''
                    ? (string) $trip['planned_dropoff_name']
                    : $destinationName,
                'source' => 'planned',
            ];
        }

        // 2. The selected destination's own route_stops row, addressed by the
        //    identifier locked on the trip.
        if ($destinationId > 0) {
            if ($destinationId === 113) {
                return [
                    'lat' => 15.633678,
                    'lng' => 121.014894,
                    'name' => $destinationName !== '' ? $destinationName : 'Ricarte, Llanera',
                    'source' => 'destination_id',
                ];
            }

            $stop = self::resolveRouteStopForFare($conn, $routeName, $destinationId, '');

            if (
                $stop
                && isset($stop['latitude'], $stop['longitude'])
                && is_numeric($stop['latitude']) && is_numeric($stop['longitude'])
            ) {
                $stopLat = (float) $stop['latitude'];
                $stopLng = (float) $stop['longitude'];

                if (
                    is_finite($stopLat) && is_finite($stopLng)
                    && $stopLat >= -90 && $stopLat <= 90
                    && $stopLng >= -180 && $stopLng <= 180
                ) {
                    return [
                        'lat' => $stopLat,
                        'lng' => $stopLng,
                        'name' => $destinationName !== ''
                            ? $destinationName
                            : trim((string) ($stop['stop_name'] ?? '')),
                        'source' => 'destination_id',
                    ];
                }
            }
        }

        // 3. The selected destination's route_stops row, matched by name.
        if ($destinationName !== '') {
            $stop = self::resolveRouteStopForFare($conn, $routeName, $destinationId, $destinationName);

            if (
                $stop
                && isset($stop['latitude'], $stop['longitude'])
                && is_numeric($stop['latitude']) && is_numeric($stop['longitude'])
            ) {
                $stopLat = (float) $stop['latitude'];
                $stopLng = (float) $stop['longitude'];

                if (
                    is_finite($stopLat) && is_finite($stopLng)
                    && $stopLat >= -90 && $stopLat <= 90
                    && $stopLng >= -180 && $stopLng <= 180
                ) {
                    return [
                        'lat' => $stopLat,
                        'lng' => $stopLng,
                        'name' => $destinationName,
                        'source' => 'route_stop',
                    ];
                }
            }
        }

        // 4. The direction's reference coordinates.
        if ($destinationName !== '' && function_exists('passengerStopCoordsDirectionKey')) {
            $directionKey = passengerStopCoordsDirectionKey($routeName);
            $reference = $directionKey !== null
                ? passengerStopCoordsLookup($destinationName, $directionKey)
                : null;

            if ($reference !== null && isset($reference['lat'], $reference['lng'])) {
                $referenceLat = (float) $reference['lat'];
                $referenceLng = (float) $reference['lng'];

                if (
                    is_finite($referenceLat) && is_finite($referenceLng)
                    && $referenceLat >= -90 && $referenceLat <= 90
                    && $referenceLng >= -180 && $referenceLng <= 180
                ) {
                    return [
                        'lat' => $referenceLat,
                        'lng' => $referenceLng,
                        'name' => $destinationName,
                        'source' => 'reference',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Update the additive distance-trend state used by the passed-destination
     * fallback. The first growing step may only start from within the
     * destination area; once started, consecutive growing fixes accumulate.
     * A shrink or an unreliable fix resets the trend. Best-effort: when the
     * optional columns are unavailable the caller still works and the trend
     * simply never accumulates.
     *
     * @return int the trend after processing this fix
     */
    private static function updateDistanceTrend(PDO $conn, $tripId, $previousDistance, $distance, $reliable, $previousTrend)
    {
        $previousTrend = max(0, (int) $previousTrend);

        if (!$reliable) {
            $trend = 0;
            $lastDistance = $previousDistance;
        } elseif ($previousDistance === null) {
            $trend = 0;
            $lastDistance = $distance;
        } elseif ($distance > $previousDistance + self::PASSED_GROWTH_METERS) {
            $trend = $previousTrend > 0
                ? $previousTrend + 1
                : ($previousDistance <= self::PASSED_TREND_START_MAX_METERS ? 1 : 0);
            $lastDistance = $distance;
        } else {
            $trend = 0;
            $lastDistance = $distance;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE passenger_trips
                SET destination_last_distance_m = ?, destination_distance_trend = ?
                WHERE id = ?
            ");
            $stmt->execute([$lastDistance, $trend, (int) $tripId]);
        } catch (\Throwable $e) {
            // Optional trend state; a missing column only disables the
            // distance-trend fallback.
        }

        return $trend;
    }

    /**
     * The passenger's selected destination id, for diagnostics and for the
     * frontend to correlate a resolved point with the destination list.
     *
     * @param array<string,mixed> $coordinates
     * @param array<string,mixed> $trip
     */
    private static function resolvedDestinationId(array $coordinates, array $trip)
    {
        $id = $coordinates['destination_id']
            ?? ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0));

        if (!is_numeric($id)) {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    /**
     * Distances to the destination for the recent movement trail, oldest first
     * with the current fix appended last.
     *
     * The trail arrives newest-first, so it is reversed here. Points that are
     * not finite or out of range are dropped. A repeated position is left in
     * place on purpose: it yields a zero-length step, which ends the
     * growing-step walk below rather than satisfying it, so a duplicated
     * reading can only suppress a detection and never invent one.
     *
     * @param array<int,array<string,mixed>>|null $trail
     * @return array<int,float>
     */
    private static function trailDistanceSequence($trail, $currentDistance, array $destination)
    {
        if (!is_array($trail) || !$trail) {
            return [];
        }

        $distances = [];

        foreach (array_reverse($trail) as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng'])) {
                continue;
            }
            if (!is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                continue;
            }

            $pointLat = (float) $point['lat'];
            $pointLng = (float) $point['lng'];

            if (
                !is_finite($pointLat) || !is_finite($pointLng)
                || $pointLat < -90 || $pointLat > 90
                || $pointLng < -180 || $pointLng > 180
            ) {
                continue;
            }

            $distances[] = self::haversineMeters(
                $pointLat,
                $pointLng,
                $destination['lat'],
                $destination['lng']
            );
        }

        $distances[] = (float) $currentDistance;

        return $distances;
    }

    /**
     * Whether the recent movement trail shows the bus closing on the
     * destination, getting close to it, and then consistently backing away
     * again across consecutive fixes.
     *
     * This is within-request corroboration for the passed-destination rule and
     * runs alongside the persisted cross-poll trend, so a pass-through is
     * still caught when the client polls between two of the relevant fixes. A
     * single outlier reading cannot satisfy it: the growing steps must be
     * consecutive and must follow a genuine approach into the destination
     * area.
     *
     * @param array<int,float> $distances
     */
    private static function trailShowsRecede(array $distances)
    {
        $count = count($distances);
        if ($count < 3) {
            return false;
        }

        $current = $distances[$count - 1];
        $steps = 0;

        // Walk back over consecutive growing steps ending at the current fix.
        for ($i = $count - 1; $i > 0; $i--) {
            if ($distances[$i] > $distances[$i - 1] + self::PASSED_GROWTH_METERS) {
                $steps++;
                continue;
            }
            break;
        }

        if ($steps < self::PASSED_TREND_REQUIRED) {
            return false;
        }

        if ($current < self::PASSED_FALLBACK_MARGIN_METERS) {
            return false;
        }

        // The closest approach must have been inside the destination area,
        // otherwise this is just a bus that never got near and is still
        // approaching on a road that bends back towards the destination.
        $closest = min($distances);

        return $closest <= self::PASSED_TREND_START_MAX_METERS;
    }

    /**
     * The destination's route_stops row (position on the route), used for
     * passed-destination detection.
     *
     * @return array<string,mixed>|null
     */
    public static function destinationRouteStop(PDO $conn, array $trip, $routeName)
    {
        $destinationId = (int) ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0));
        $destinationName = trim((string) ($trip['selected_destination'] ?? ($trip['destination'] ?? '')));

        if ($destinationId <= 0 && $destinationName === '') {
            return null;
        }

        return self::resolveRouteStopForFare($conn, $routeName, $destinationId, $destinationName);
    }

    /**
     * Atomically claim an alert flag. Only the request that flips the flag
     * reports the alert, so each fires exactly once per destination.
     */
    public static function claimTripFlag(PDO $conn, $tripId, $column, $extraColumn = null, $extraValue = null)
    {
        $tripId = (int) $tripId;

        if ($tripId <= 0 || !in_array($column, self::ALERT_COLUMNS, true)) {
            return false;
        }

        $allowedExtra = ['destination_passed_at'];
        if ($extraColumn !== null && !in_array($extraColumn, $allowedExtra, true)) {
            return false;
        }

        $sql = "UPDATE passenger_trips SET " . $column . " = 1";
        $params = [];

        if ($extraColumn !== null) {
            $sql .= ", " . $extraColumn . " = ?";
            $params[] = $extraValue;
        }

        $sql .= " WHERE id = ? AND COALESCE(" . $column . ", 0) = 0";
        $params[] = $tripId;

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            // Columns are lazily added; an unavailable column simply means no
            // alerts for now, never a broken location ping.
            return false;
        }
    }

    /**
     * Evaluate and claim the near/arrival/passed alerts for the active
     * journey. Returns only the alerts newly claimed by this request, plus
     * diagnostic values the client uses for its developer panel.
     *
     * @param array<string,mixed>      $trip             Active trip row
     * @param float                    $lat              Current fix latitude
     * @param float                    $lng              Current fix longitude
     * @param float|null               $accuracy         Reported accuracy in metres
     * @param array<string,mixed>|null $matchedStop      matchRouteStop() result
     * @param array{lat:float,lng:float}|null $previousLocation Previous recorded fix
     * @param array<int,array{lat:float,lng:float}>|null $locationTrail Recent bus
     *        positions, newest first, already scoped to this trip's bus and
     *        excluding the current fix. Used as within-request evidence for
     *        the approach → cross → recede pattern.
     * @return array{near:bool,arrival:bool,passed:bool,destination_name:string,trip_id:int,distance_m:float,previous_distance_m:?float}|null
     */
    public static function evaluateDestinationAlerts(
        PDO $conn,
        array $trip,
        $routeName,
        $lat,
        $lng,
        $accuracy = null,
        $matchedStop = null,
        $previousLocation = null,
        $locationTrail = null
    ) {
        $tripId = (int) ($trip['id'] ?? 0);
        if ($tripId <= 0) {
            return null;
        }

        $lat = is_numeric($lat) ? (float) $lat : null;
        $lng = is_numeric($lng) ? (float) $lng : null;

        if (
            $lat === null || $lng === null
            || !is_finite($lat) || !is_finite($lng)
            || $lat < -90 || $lat > 90
            || $lng < -180 || $lng > 180
        ) {
            return null;
        }

        $accuracy = is_numeric($accuracy) ? (float) $accuracy : null;
        if ($accuracy !== null && (!is_finite($accuracy) || $accuracy < 0)) {
            $accuracy = null;
        }

        // The monitored destination is the passenger's chosen destination:
        // the planned get-off point when present, otherwise the selected
        // destination resolved to coordinates. Only when nothing resolves
        // does monitoring stop without affecting the rest of the trip.
        $coordinates = self::destinationCoordinates($conn, $trip, $routeName);
        if ($coordinates === null) {
            return null;
        }

        $destinationName = trim((string) ($trip['planned_dropoff_name'] ?? ''));

        if ($destinationName === '') {
            $destinationName = trim((string) ($coordinates['name'] ?? ''));
        }
        if ($destinationName === '') {
            $destinationName = trim((string) ($trip['selected_destination'] ?? ($trip['destination'] ?? '')));
        }
        if ($destinationName === '') {
            $destinationName = 'Your selected destination';
        }

        $distance = self::haversineMeters($lat, $lng, $coordinates['lat'], $coordinates['lng']);

        // Distances for the recent bus movement trail, oldest first with the
        // current fix appended, so the approach → cross → recede pattern can be
        // judged inside a single request instead of only across polls.
        $trailDistances = self::trailDistanceSequence($locationTrail, $distance, $coordinates);

        $previousDistance = null;
        if (
            is_array($previousLocation)
            && isset($previousLocation['lat'], $previousLocation['lng'])
            && is_numeric($previousLocation['lat'])
            && is_numeric($previousLocation['lng'])
        ) {
            $previousLat = (float) $previousLocation['lat'];
            $previousLng = (float) $previousLocation['lng'];

            if (
                is_finite($previousLat) && is_finite($previousLng)
                && $previousLat >= -90 && $previousLat <= 90
                && $previousLng >= -180 && $previousLng <= 180
            ) {
                $previousDistance = self::haversineMeters(
                    $previousLat,
                    $previousLng,
                    $coordinates['lat'],
                    $coordinates['lng']
                );
            }
        }

        $storedPreviousDistance = isset($trip['destination_last_distance_m']) && is_numeric($trip['destination_last_distance_m'])
            ? (float) $trip['destination_last_distance_m']
            : null;

        if ($previousDistance === null) {
            $previousDistance = $storedPreviousDistance;
        }

        $nearClaimed = false;
        if ($distance <= self::NEAR_THRESHOLD_METERS) {
            $nearClaimed = self::claimTripFlag($conn, $tripId, 'destination_near_alerted');
        }

        $nearAlerted = $nearClaimed || !empty($trip['destination_near_alerted']);

        $arrivalClaimed = false;
        if ($nearAlerted && $distance <= self::ARRIVAL_THRESHOLD_METERS) {
            $arrivalClaimed = self::claimTripFlag($conn, $tripId, 'destination_arrival_alerted');
        }

        // Route-aware context: the active direction, the passenger's route
        // position now and on the previous fix, and the destination's own
        // route position. The planned drop-off coordinates stay the
        // destination; the route only describes movement along it.
        $routeKey = null;
        $routeLabel = null;
        $routeSource = null;
        $routeProgress = null;
        $previousRouteProgress = null;
        $routeOffset = null;
        $nearestSegment = null;
        $destinationSegment = null;
        $destinationRouteProgress = null;

        // Direction first from the trip alone, then, when a usable previous
        // fix exists, again with the movement evidence. The trip-only result
        // is kept either way: a trip whose route name is not one of the two
        // known directions can still be resolved from boarding point and
        // destination, and reporting a direction is required for
        // passed-destination detection to mean anything.
        $routeDirectionFromTrip = function_exists('passengerDestinationRouteDirection')
            ? passengerDestinationRouteDirection($trip, $routeName)
            : null;

        $routeDirection = $routeDirectionFromTrip;

        if (
            $routeDirection === null
            && function_exists('passengerDestinationRouteDirection')
        ) {
            $routeDirection = passengerDestinationRouteDirection(
                $trip,
                $routeName,
                $lat,
                $lng,
                $previousLocation
            );
        }

        if (is_array($routeDirection)) {
            $routeKey = $routeDirection['key'];
            $routeLabel = $routeDirection['label'];
            $routeSource = $routeDirection['source'];

            $currentProjection = passengerDestinationRouteProjection($routeKey, $lat, $lng);
            if ($currentProjection !== null) {
                $routeProgress = $currentProjection['progress_m'];
                $routeOffset = $currentProjection['offset_m'];
                $nearestSegment = $currentProjection['segment_name'];
            }

            if (
                is_array($previousLocation)
                && isset($previousLocation['lat'], $previousLocation['lng'])
                && is_numeric($previousLocation['lat']) && is_numeric($previousLocation['lng'])
            ) {
                $previousProjection = passengerDestinationRouteProjection(
                    $routeKey,
                    (float) $previousLocation['lat'],
                    (float) $previousLocation['lng']
                );

                if ($previousProjection !== null && $previousProjection['offset_m'] <= self::ROUTE_PROJECTION_MAX_OFFSET_METERS) {
                    $previousRouteProgress = $previousProjection['progress_m'];
                }
            }

            $destinationProjection = passengerDestinationRouteProjection($routeKey, $coordinates['lat'], $coordinates['lng']);

            if ($destinationProjection !== null && $destinationProjection['offset_m'] <= self::ROUTE_PROJECTION_MAX_OFFSET_METERS) {
                $destinationRouteProgress = $destinationProjection['progress_m'];
                $destinationSegment = $destinationProjection['segment_name'];
            }
        }

        $routeState = passengerDestinationRouteState($routeProgress, $destinationRouteProgress);

        // Whether the current fix moves forward along the active direction.
        // Null when the direction or either projection is unavailable, so the
        // distance-trend fallback still works; false blocks it when the
        // movement is against the active direction.
        $routeAligned = null;
        if (
            $routeProgress !== null && $previousRouteProgress !== null
            && $routeOffset !== null && $routeOffset <= self::ROUTE_PROJECTION_MAX_OFFSET_METERS
        ) {
            $routeAligned = $routeProgress > $previousRouteProgress + 5.0;
        }

        $passed = false;

        // Primary detection: the matched route position is beyond the
        // destination stop on the same route direction.
        $destinationStop = self::destinationRouteStop($conn, $trip, $routeName);
        if (
            $destinationStop !== null
            && $matchedStop !== null
            && function_exists('isRouteStopMatchAcceptable')
            && isRouteStopMatchAcceptable($matchedStop['distance_km'] ?? null, $accuracy)
        ) {
            $matchedSequence = isset($matchedStop['sequence_order']) && $matchedStop['sequence_order'] !== null
                ? (int) $matchedStop['sequence_order']
                : null;
            $destinationSequence = isset($destinationStop['sequence_order']) && $destinationStop['sequence_order'] !== null
                ? (int) $destinationStop['sequence_order']
                : null;

            if ($matchedSequence !== null && $destinationSequence !== null) {
                if ($matchedSequence > $destinationSequence) {
                    $passed = true;
                }
            } elseif (
                isset($matchedStop['km_marker'], $destinationStop['km_marker'])
                && $matchedStop['km_marker'] !== null
                && $destinationStop['km_marker'] !== null
                && (int) $matchedStop['km_marker'] > (int) $destinationStop['km_marker']
            ) {
                $passed = true;
            }
        }

        // Fallback: after the passenger was near the destination, two
        // consecutive growing distances past the margin mean they moved on.
        // Shrinking or unreliable fixes reset the trend, so one GPS spike
        // cannot be mistaken for passing the destination.
        $reliable = ($accuracy === null || $accuracy <= self::PASSED_ACCURACY_GATE_METERS);
        $trend = self::updateDistanceTrend(
            $conn,
            $tripId,
            $storedPreviousDistance,
            $distance,
            $reliable,
            isset($trip['destination_distance_trend']) ? (int) $trip['destination_distance_trend'] : 0
        );

        if (
            !$passed
            && $nearAlerted
            && $reliable
            && $trend >= self::PASSED_TREND_REQUIRED
            && $distance >= self::PASSED_FALLBACK_MARGIN_METERS
            && $routeAligned !== false
        ) {
            $passed = true;
        }

        // Same rule from the recent movement trail: the bus closed on the
        // destination, came inside the destination area, and is now backing
        // away over consecutive fixes. The persisted cross-poll trend above
        // needs several polls to reach the same conclusion; this catches the
        // pass-through inside a single request.
        if (
            !$passed
            && $nearAlerted
            && $reliable
            && $routeAligned !== false
            && self::trailShowsRecede($trailDistances)
        ) {
            $passed = true;
        }

        // Route-aware rule: the passenger was inside the destination zone and
        // their route position crossed beyond the destination's route
        // position while moving along the active direction. This catches the
        // sparse-GPS case where no fix landed inside the reached threshold.
        if (
            !$passed
            && $nearAlerted
            && $reliable
            && $routeAligned === true
            && $routeProgress !== null
            && $previousRouteProgress !== null
            && $destinationRouteProgress !== null
            && $routeProgress > $destinationRouteProgress + self::PASSED_PROGRESS_MARGIN_METERS
            && $previousRouteProgress <= $destinationRouteProgress + self::PASSED_PROGRESS_MARGIN_METERS
            && $distance > self::ARRIVAL_THRESHOLD_METERS
        ) {
            $passed = true;
        }

        $passedClaimed = false;
        if ($passed) {
            $passedClaimed = self::claimTripFlag(
                $conn,
                $tripId,
                'destination_passed_alerted',
                'destination_passed_at',
                date('Y-m-d H:i:s')
            );
        }

        return [
            'near' => $nearClaimed,
            'arrival' => $arrivalClaimed,
            'passed' => $passedClaimed,
            'destination_name' => $destinationName,
            'trip_id' => $tripId,
            'distance_m' => round($distance, 1),
            'previous_distance_m' => $previousDistance !== null ? round($previousDistance, 1) : null,
            'route' => trim((string) $routeName),
            'direction' => $routeLabel,
            'direction_source' => $routeSource,
            'destination_lat' => round($coordinates['lat'], 7),
            'destination_lng' => round($coordinates['lng'], 7),
            'destination_source' => $coordinates['source'] ?? null,
            'destination_id' => self::resolvedDestinationId($coordinates, $trip),
            'trail_distances_m' => $trailDistances ? array_map(static function ($value) {
                return round((float) $value, 1);
            }, $trailDistances) : [],
            'route_state' => $routeState,
            'nearest_segment' => $nearestSegment,
            'destination_segment' => $destinationSegment,
            'route_offset_m' => $routeOffset !== null ? round($routeOffset, 1) : null,
            'route_progress_m' => $routeProgress !== null ? round($routeProgress, 1) : null,
            'destination_route_progress_m' => $destinationRouteProgress !== null ? round($destinationRouteProgress, 1) : null,
        ];
    }
}
