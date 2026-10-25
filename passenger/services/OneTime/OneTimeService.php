<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymongoSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerCodeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/BusBoardingService.php';

use App\Core\Database;

/**
 * Shared resolution + registration logic for the One-Time Passenger flow.
 *
 * All decisions that matter (which trip, which route, which stops, which fare)
 * are resolved here from the database; client-supplied values are only ever
 * used as identifiers to look up, never as the source of truth.
 */
class OneTimeService
{
    /**
     * Statuses treated as "currently active" by the rest of the app. The
     * driver app (mobile/driver/getActiveTrip.php) accepts Scheduled/Departed/
     * On Route, while other driver endpoints also accept the legacy
     * Started/Active/Ongoing values, so all of them count here.
     */
    public const ACTIVE_TRIP_STATUSES = [
        'Scheduled',
        'Departed',
        'On Route',
        'Started',
        'Active',
        'Ongoing',
    ];

    /** Payment methods a one-time passenger may choose. */
    public const PAYMENT_METHODS = ['cash', 'gcash'];

    /**
     * Public trip payload used by the landing/registration pages.
     *
     * @param array<string,mixed>|null $boardingLock Boarding point registered
     *   when the passenger entered the flow; when it matches this bus/trip it
     *   is authoritative and the live position can no longer move it.
     * @return array{success:bool,code?:string,message?:string,data?:array}
     */
    public static function tripInfo(PDO $conn, string $busId, ?array $boardingLock = null): array
    {
        $busId = trim($busId);

        if ($busId === '') {
            return self::fail(
                'INVALID_BUS',
                'This QR code is not a valid Bus QR. Please scan the Bus QR again.'
            );
        }

        $bus = self::resolveBus($conn, $busId);
        if (!$bus) {
            return self::fail(
                'BUS_NOT_FOUND',
                'This bus is not registered in R-CONNECT. Please contact the driver.'
            );
        }

        $trip = self::resolveActiveTrip($conn, $busId);
        if (!$trip) {
            self::logNoActiveTrip($conn, $busId);

            return self::fail(
                'NO_ACTIVE_TRIP',
                'This bus currently has no active trip. Please try again later or contact the driver.'
            );
        }

        $existingTrip = null;
        $passengerId = (int) ($_SESSION['passenger_id'] ?? ($_COOKIE['ot_passenger_id'] ?? 0));
        if ($passengerId > 0) {
            $existingTrip = self::tripForPassengerAndTrip(
                $conn,
                $passengerId,
                (int) $trip['id'],
                $trip['trip_number'] ?? null
            );
        }

        $routeStops = self::resolveRouteStops($conn, (string) $trip['route_name']);
        $lock = self::matchBoardingLock($trip, $busId, $boardingLock);
        $boarding = $lock !== null
            ? self::lockedBoardingState($conn, $trip, $routeStops, $lock)
            : passengerResolveBusBoarding($conn, $trip, $routeStops);

        return [
            'success' => true,
            'data' => [
                'bus' => [
                    'bus_id' => (string) $bus['bus_id'],
                    'plate_number' => $bus['plate_number'] ?? null,
                ],
                'trip' => [
                    'id' => (int) $trip['id'],
                    'trip_number' => $trip['trip_number'] ?? null,
                    'route_name' => (string) $trip['route_name'],
                    'direction' => self::directionLabel($conn, $trip),
                    'trip_date' => $trip['trip_date'] ?? null,
                    'departure_time' => $trip['departure_time'] ?? null,
                    'status' => (string) ($trip['status'] ?? ''),
                ],
                'driver' => [
                    'driver_id' => $trip['driver_id'] ?? null,
                    'driver_name' => self::resolveDriverName($conn, $trip, $busId),
                ],
                'stops' => $routeStops,
                'bus_position' => $boarding['position'],
                'boarding' => $boarding['boarding'],
                'destinations' => $boarding['destinations'],
                'boarding_error' => $boarding['error'],
                'direction_key' => $boarding['direction'],
                'boarding_locked' => $lock !== null && $boarding['boarding'] !== null,
                'has_existing_trip' => $existingTrip !== null,
                'existing_trip_id' => $existingTrip ? (int) $existingTrip['id'] : null,
                'existing_payment_status' => $existingTrip ? (string) ($existingTrip['payment_status'] ?? 'pending') : null,
            ],
        ];
    }

    /**
     * The boarding point registered when the passenger entered the One-Time
     * flow is authoritative for that entry: refreshes, live bus movement and
     * re-opened forms must not move it. Only a lock for the same bus and the
     * same active trip is honored; anything else falls back to the live
     * resolution.
     */
    private static function matchBoardingLock(array $trip, string $busId, ?array $lock): ?array
    {
        if (!is_array($lock) || (int) ($lock['boarding_id'] ?? 0) <= 0) {
            return null;
        }

        $lockBus = trim((string) ($lock['bus'] ?? ''));
        if ($lockBus !== '' && strcasecmp($lockBus, trim($busId)) !== 0) {
            return null;
        }

        $lockTrip = $lock['trip_id'] ?? null;
        if ($lockTrip !== null && (int) $lockTrip > 0 && (int) $lockTrip !== (int) $trip['id']) {
            return null;
        }

        return $lock;
    }

    /**
     * The boarding/destination payload for a registered (locked) boarding
     * point: the stop itself from the trip's fare list, and the stops after
     * it in display order. At the last stop the same-stop fallback applies.
     *
     * @return array{position:array<string,mixed>,boarding:?array<string,mixed>,destinations:list<array<string,mixed>>,direction:?string,error:?array{code:string,message:string}}
     */
    private static function lockedBoardingState(PDO $conn, array $trip, array $routeStops, array $lock): array
    {
        $direction = passengerBusDirectionKey((string) ($trip['route_name'] ?? ''));
        $boardingStop = self::findFareStop($routeStops, (int) ($lock['boarding_id'] ?? 0));

        if ($boardingStop === null) {
            return [
                'position' => self::lockedPositionPayload($lock),
                'boarding' => null,
                'destinations' => [],
                'direction' => $direction,
                'error' => [
                    'code' => 'INVALID_BOARDING',
                    'message' => 'Your registered boarding point is no longer part of this trip. Please scan the Bus QR again.',
                ],
            ];
        }

        $routeStop = self::resolveRouteStop($conn, (string) $trip['route_name'], (string) $boardingStop['destination'], (int) $boardingStop['id']);
        $boarding = [
            'id' => (int) $boardingStop['id'],
            'stop_id' => $routeStop ? (int) $routeStop['id'] : null,
            'destination' => (string) $boardingStop['destination'],
            'display_order' => (int) $boardingStop['display_order'],
            'km_marker' => $routeStop && $routeStop['km_marker'] !== null ? (int) $routeStop['km_marker'] : null,
            'latitude' => null,
            'longitude' => null,
        ];

        $boardingOrder = (int) $boardingStop['display_order'];
        $busOrder = self::busProgressOrder($conn, $trip, $routeStops);

        // Stops the bus has already passed are not offered: the effective
        // threshold is the further of the registered boarding stop and the
        // bus's current stop along the trip's display_order.
        $progressOrder = $boardingOrder;
        if ($busOrder !== null && $busOrder > $progressOrder) {
            $progressOrder = $busOrder;
        }

        $destinations = self::fareStopsAfter($routeStops, $progressOrder);

        // The local option (boarding stop itself, same-stop minimum fare) is
        // offered while the bus has not passed the registered stop, exactly
        // like the regular flow; it is the only option at the end of the trip
        // and is then flagged for preselection.
        $localAvailable = $busOrder === null || $busOrder <= $boardingOrder;
        if ($localAvailable) {
            $localOption = [
                'id' => $boarding['id'],
                'destination' => $boarding['destination'],
                'display_order' => $boarding['display_order'],
                'km_marker' => $boarding['km_marker'],
                'latitude' => null,
                'longitude' => null,
                'is_boarding' => true,
            ];

            if ($destinations === []) {
                $localOption['same_as_boarding'] = true;
            }

            array_unshift($destinations, $localOption);
        } elseif ($destinations === []) {
            // The bus already left the last stop; the same-stop fallback is
            // the only way to complete a booking.
            $destinations[] = [
                'id' => $boarding['id'],
                'destination' => $boarding['destination'],
                'display_order' => $boarding['display_order'],
                'km_marker' => $boarding['km_marker'],
                'latitude' => null,
                'longitude' => null,
                'same_as_boarding' => true,
            ];
        }

        return [
            'position' => self::lockedPositionPayload($lock),
            'boarding' => $boarding,
            'destinations' => $destinations,
            'direction' => $direction,
            'error' => null,
        ];
    }

    /**
     * The display_order of the stop the bus is currently at on the trip's own
     * ordered stop list, or null when the position cannot be resolved. The
     * pass/not-pass decision is order-based (route sequence), never a raw
     * proximity guess.
     */
    private static function busProgressOrder(PDO $conn, array $trip, array $routeStops): ?int
    {
        try {
            $state = passengerResolveBusBoarding($conn, $trip, $routeStops);
            if ($state['error'] === null && !empty($state['boarding'])) {
                return (int) $state['boarding']['display_order'];
            }
        } catch (\Throwable $e) {
            // Unknown position: callers fall back to the boarding order.
        }

        return null;
    }

    /**
     * The fare stops after a display order, in trip order. Used by the
     * registered boarding point so its destination list stays fixed.
     *
     * @return list<array<string,mixed>>
     */
    private static function fareStopsAfter(array $routeStops, int $afterOrder): array
    {
        $stops = [];
        foreach ($routeStops as $stop) {
            if ((int) $stop['display_order'] > $afterOrder) {
                $stops[] = [
                    'id' => (int) $stop['id'],
                    'destination' => (string) $stop['destination'],
                    'display_order' => (int) $stop['display_order'],
                    'km_marker' => null,
                    'latitude' => null,
                    'longitude' => null,
                ];
            }
        }

        return $stops;
    }

    /**
     * @return array<string,mixed>
     */
    private static function lockedPositionPayload(array $lock): array
    {
        return [
            'available' => true,
            'usable' => true,
            'reason' => null,
            'source' => $lock['source'] ?? null,
            'latitude' => $lock['latitude'] ?? null,
            'longitude' => $lock['longitude'] ?? null,
            'speed' => null,
            'updated_at' => $lock['recorded_at'] ?? null,
            'age_seconds' => null,
            'freshness' => $lock['freshness'] ?? null,
            'matched_distance_km' => null,
            'locked' => true,
        ];
    }

    /**
     * Registers a one-time passenger and attaches them to the bus's current
     * active trip. Everything is revalidated inside the transaction.
     *
     * @param array $input Decoded JSON body from the client.
     * @param array<string,mixed>|null $boardingLock Boarding point registered
     *   when the passenger entered the flow; when it matches this bus/trip the
     *   submitted boarding must equal it instead of the live stop.
     * @return array{success:bool,code?:string,message?:string,data?:array,session_started?:bool}
     */
    public static function registerBooking(PDO $conn, array $input, ?array $boardingLock = null): array
    {
        // ---------------------------------------------------------------
        // 1. Basic field validation
        // ---------------------------------------------------------------
        $busNo = trim((string) ($input['bus_no'] ?? ''));
        if ($busNo === '') {
            return self::fail('INVALID_BUS', 'This QR code is not a valid Bus QR. Please scan the Bus QR again.');
        }

        $submittedTripNo = trim((string) ($input['trip_no'] ?? ''));
        if ($submittedTripNo === '') {
            return self::fail('INVALID_TRIP', 'We could not identify the current trip. Please scan the Bus QR again.');
        }

        $fullName = trim((string) ($input['full_name'] ?? ''));
        if (class_exists('App\Passenger\PassengerValidator')) {
            $nameCheck = \App\Passenger\PassengerValidator::validateFullName($fullName);
            if (!$nameCheck['valid']) {
                return self::fail('INVALID_NAME', $nameCheck['error']);
            }
            $fullName = $nameCheck['value'];
        } else {
            if ($fullName === '' || mb_strlen($fullName) < 2) {
                return self::fail('INVALID_NAME', 'Please enter the passenger\'s full name.');
            }
            if (mb_strlen($fullName) > 100) {
                $fullName = mb_substr($fullName, 0, 100);
            }
        }

        $type = self::mapPassengerType((string) ($input['passenger_type'] ?? ''));
        if ($type === null) {
            return self::fail('INVALID_PASSENGER_TYPE', 'Please select a valid passenger type.');
        }

        $paymentMethod = strtolower(trim((string) ($input['payment_method'] ?? '')));
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            return self::fail('INVALID_PAYMENT_METHOD', 'Please choose how you would like to pay.');
        }

        $submissionToken = trim((string) ($input['submission_token'] ?? ''));
        if (
            $submissionToken === ''
            || strlen($submissionToken) < 8
            || strlen($submissionToken) > 64
            || !preg_match('/^[A-Za-z0-9_\-]+$/', $submissionToken)
        ) {
            return self::fail('INVALID_SUBMISSION', 'We could not confirm this submission. Please refresh the page and try again.');
        }

        $contactNumber = trim((string) ($input['contact_number'] ?? ''));
        if ($contactNumber !== '') {
            if (class_exists('App\Passenger\PassengerValidator')) {
                $phoneCheck = \App\Passenger\PassengerValidator::validatePhone($contactNumber, false);
                if (!$phoneCheck['valid']) {
                    return self::fail('INVALID_PHONE', $phoneCheck['error']);
                }
                $contactNumber = $phoneCheck['normalized'];
            } elseif (mb_strlen($contactNumber) > 20) {
                $contactNumber = mb_substr($contactNumber, 0, 20);
            }
        }

        // Declared ID number: required for discounted types, ignored otherwise.
        $declaredIdNumber = trim((string) ($input['declared_id_number'] ?? ''));
        if (!empty($type['requires_id'])) {
            if (class_exists('App\Passenger\PassengerValidator')) {
                $idCheck = \App\Passenger\PassengerValidator::validateIdNumber($declaredIdNumber, (string) ($type['discount_type'] ?? $type['label'] ?? 'Student'), true);
                if (!$idCheck['valid']) {
                    $errCode = ($declaredIdNumber === '') ? 'ID_NUMBER_REQUIRED' : 'INVALID_ID_NUMBER';
                    return self::fail($errCode, $idCheck['error']);
                }
                $declaredIdNumber = $idCheck['value'];
            } else {
                if ($declaredIdNumber === '') {
                    return self::fail('ID_NUMBER_REQUIRED', 'Please enter your ' . (string) $type['id_label'] . '.');
                }
                $idLength = mb_strlen($declaredIdNumber);
                $idDigits = preg_match_all('/\d/', $declaredIdNumber);
                if (
                    $idLength < 3
                    || $idLength > 50
                    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/', $declaredIdNumber)
                    || $idDigits < 3
                ) {
                    return self::fail('INVALID_ID_NUMBER', 'Please enter a valid ' . (string) $type['id_label'] . ' containing numeric digits.');
                }
            }
        } else {
            $declaredIdNumber = '';
        }
        $declaredIdStatus = $declaredIdNumber !== '' ? 'self_declared' : null;

        // Companions travelling with this passenger (children/seniors/others
        // without a cellphone). Optional; each one becomes its own passenger
        // + trip record linked to the primary booking.
        $normalizedCompanions = [];
        if (!empty($input['companions'])) {
            $normalized = CompanionService::normalizeCompanions($input['companions']);
            if ($normalized['error'] !== null) {
                return self::fail('INVALID_COMPANION', $normalized['error']);
            }
            $normalizedCompanions = $normalized['companions'];
        }

        // GPS is supporting data only: invalid/incomplete coordinates are
        // stored as unavailable instead of blocking registration.
        $latitude = self::normalizeCoordinate($input['latitude'] ?? null, -90.0, 90.0);
        $longitude = self::normalizeCoordinate($input['longitude'] ?? null, -180.0, 180.0);
        if ($latitude === null || $longitude === null) {
            $latitude = null;
            $longitude = null;
        }
        $accuracy = self::normalizeCoordinate($input['accuracy'] ?? null, 0.0, 1000000.0);

        // ---------------------------------------------------------------
        // 2. Bus + active trip resolution (client values are hints only)
        // ---------------------------------------------------------------
        $bus = self::resolveBus($conn, $busNo);
        if (!$bus) {
            return self::fail('BUS_NOT_FOUND', 'This bus is not registered in R-CONNECT. Please contact the driver.');
        }

        $trip = self::resolveActiveTrip($conn, $busNo);
        if (!$trip) {
            return self::fail('NO_ACTIVE_TRIP', 'This bus no longer has an active trip. Please scan the Bus QR again.');
        }

        if ((string) $trip['id'] !== $submittedTripNo) {
            return self::fail('TRIP_CHANGED', 'The trip has changed since you started. Please scan the Bus QR again.');
        }

        // ---------------------------------------------------------------
        // 3. Route stops and the bus-location boarding gate
        // ---------------------------------------------------------------
        $routeName = trim((string) $trip['route_name']);
        $routeStops = self::resolveRouteStops($conn, $routeName);
        if (!$routeStops) {
            return self::fail('NO_ROUTE_STOPS', 'No stops are available for this trip. Please contact the driver.');
        }

        $boardingId = (int) ($input['boarding_id'] ?? 0);
        $destinationId = (int) ($input['destination_id'] ?? 0);

        // ---------------------------------------------------------------
        // 3a. Idempotency first: a replayed confirmation returns the original
        // trip even after the bus has moved past the selected stops.
        // ---------------------------------------------------------------
        $existing = self::findBySubmissionToken($conn, $submissionToken);
        if ($existing) {
            return self::duplicatePayload($existing);
        }

        $sessionPassengerId = (int) ($_SESSION['passenger_id'] ?? ($_COOKIE['ot_passenger_id'] ?? 0));
        if ($sessionPassengerId > 0) {
            $existingTrip = self::tripForPassengerAndTrip(
                $conn,
                $sessionPassengerId,
                $submittedTripNo,
                $trip['trip_number'] ?? null
            );
            if ($existingTrip) {
                return self::duplicatePayload($existingTrip);
            }
        }

        // ---------------------------------------------------------------
        // 3b. Validate the boarding point: a boarding registered when the
        // passenger entered the flow is authoritative and the live position
        // can no longer move it; without a lock the submitted boarding must
        // be the stop the bus is at now. Either way the destination must be
        // ahead of that boarding point.
        // ---------------------------------------------------------------
        $lock = self::matchBoardingLock($trip, $busNo, $boardingLock);
        $lockedStop = $lock !== null ? self::findFareStop($routeStops, (int) ($lock['boarding_id'] ?? 0)) : null;

        if ($lockedStop !== null) {
            if ($boardingId !== (int) $lockedStop['id']) {
                return self::fail(
                    'INVALID_BOARDING',
                    'Your boarding point is registered for this entry and cannot be changed. Please refresh the page and use the registered boarding point.'
                );
            }
            $lockedOrder = (int) $lockedStop['display_order'];
            $busOrder = self::busProgressOrder($conn, $trip, $routeStops);
            $progressOrder = $busOrder !== null && $busOrder > $lockedOrder ? $busOrder : $lockedOrder;

            // Stops the bus has already passed are not accepted, mirroring the
            // destination list; the local option is only valid while the bus
            // has not passed the registered stop.
            $allowedDestinations = array_map('intval', array_column(self::fareStopsAfter($routeStops, $progressOrder), 'id'));

            if ($busOrder === null || $busOrder <= $lockedOrder) {
                $allowedDestinations[] = (int) $lockedStop['id'];
            } elseif ($allowedDestinations === []) {
                // The bus already left the last stop: the same-stop fallback
                // offered by tripInfo() is the only valid choice.
                $allowedDestinations = [$boardingId];
            }

            if (!in_array($destinationId, $allowedDestinations, true)) {
                $destinationRow = self::findFareStop($routeStops, $destinationId);
                $destinationOrder = $destinationRow !== null ? (int) $destinationRow['display_order'] : 0;

                if ($destinationOrder < $lockedOrder) {
                    return self::fail(
                        'INVALID_DESTINATION',
                        'That stop is behind your boarding point. Please choose a stop ahead of your boarding point.'
                    );
                }

                return self::fail(
                    'DESTINATION_PASSED',
                    'That stop has already been passed by the bus. Please choose a destination from the remaining stops.'
                );
            }
            $confirmPosition = self::lockedPositionPayload($lock);
            $fallbackBoardingStopId = $lockedStop;
        } else {
            $boardingState = passengerResolveBusBoarding($conn, $trip, $routeStops);
            if ($boardingState['error'] !== null) {
                return self::fail($boardingState['error']['code'], $boardingState['error']['message']);
            }

            $currentBoarding = $boardingState['boarding'];
            if (!$currentBoarding || (int) $currentBoarding['id'] !== $boardingId) {
                return self::fail(
                    'INVALID_BOARDING',
                    'Your boarding point changed because the bus moved. Please go back and try again.'
                );
            }

            $allowedDestinations = array_map('intval', array_column($boardingState['destinations'], 'id'));
            if (!in_array($destinationId, $allowedDestinations, true)) {
                return self::fail(
                    'INVALID_DESTINATION',
                    'That stop is no longer ahead of the bus. Please choose a remaining destination.'
                );
            }
            $confirmPosition = $boardingState['position'];
            $fallbackBoardingStopId = $currentBoarding;
        }

        $boardingStop = self::findFareStop($routeStops, $boardingId);
        $destinationStop = self::findFareStop($routeStops, $destinationId);
        if (!$boardingStop || !$destinationStop) {
            return self::fail('INVALID_DESTINATION', 'Please choose a remaining destination.');
        }

        // The local option (boarding = destination) is offered by the
        // destination list; the fare engine charges the minimum fare for the
        // zero-distance ride. A destination strictly behind the boarding point
        // is still refused.
        if ((int) $destinationStop['display_order'] < (int) $boardingStop['display_order']) {
            return self::fail('INVALID_DESTINATION', 'That stop is behind your boarding point. Please choose a stop ahead of your boarding point.');
        }

        // Scan snapshot for the boarding record: the position captured when
        // the QR was scanned (session), falling back to the confirm-time
        // position when the session snapshot is gone.
        $scanSnapshot = (isset($_SESSION['qr_location_snapshot']) && is_array($_SESSION['qr_location_snapshot']))
            ? $_SESSION['qr_location_snapshot']
            : null;

        $scanLatitude = ($scanSnapshot && isset($scanSnapshot['latitude']) && is_numeric($scanSnapshot['latitude']))
            ? (float) $scanSnapshot['latitude'] : null;
        $scanLongitude = ($scanSnapshot && isset($scanSnapshot['longitude']) && is_numeric($scanSnapshot['longitude']))
            ? (float) $scanSnapshot['longitude'] : null;
        $scanRecordedAt = ($scanSnapshot && !empty($scanSnapshot['recorded_at']))
            ? (string) $scanSnapshot['recorded_at'] : null;
        $scanStopId = ($scanSnapshot && isset($scanSnapshot['matched_stop_id']) && is_numeric($scanSnapshot['matched_stop_id']))
            ? (int) $scanSnapshot['matched_stop_id'] : null;
        $scanLocationSource = ($scanSnapshot && !empty($scanSnapshot['source']))
            ? (string) $scanSnapshot['source'] : null;

        if ($scanLocationSource === null) {
            $scanLocationSource = !empty($confirmPosition['source']) ? (string) $confirmPosition['source'] : null;

            if ($scanLatitude === null && isset($confirmPosition['latitude']) && is_numeric($confirmPosition['latitude'])) {
                $scanLatitude = (float) $confirmPosition['latitude'];
            }
            if ($scanLongitude === null && isset($confirmPosition['longitude']) && is_numeric($confirmPosition['longitude'])) {
                $scanLongitude = (float) $confirmPosition['longitude'];
            }
            if ($scanRecordedAt === null && !empty($confirmPosition['updated_at'])) {
                $scanRecordedAt = (string) $confirmPosition['updated_at'];
            }
            if ($scanStopId === null && !empty($fallbackBoardingStopId['stop_id'])) {
                $scanStopId = (int) $fallbackBoardingStopId['stop_id'];
            }
        }

        $boardingRouteStop = self::resolveRouteStop($conn, $routeName, (string) $boardingStop['destination'], $boardingId);
        $destinationRouteStop = self::resolveRouteStop($conn, $routeName, (string) $destinationStop['destination'], $destinationId);

        $boardingStopId = $boardingRouteStop ? (int) $boardingRouteStop['id'] : null;
        $destinationStopId = $destinationRouteStop ? (int) $destinationRouteStop['id'] : null;
        $boardingKm = self::kmMarker($boardingRouteStop);
        $destinationKm = self::kmMarker($destinationRouteStop);

        // ---------------------------------------------------------------
        // 4. Fare — existing engine, recomputed server-side
        // ---------------------------------------------------------------
        $breakdown = self::fareBreakdown(
            $conn,
            (int) $trip['id'],
            $boardingStop,
            $destinationStop,
            $type,
            $destinationId,
            $boardingStopId,
            $destinationStopId
        );

        $baseFare = $breakdown['base_fare'];
        $totalFare = $breakdown['fare'];
        $discountAmount = $breakdown['discount_amount'];

        if ($totalFare <= 0) {
            return self::fail(
                'NO_FARE',
                'We could not compute the fare for this trip. Please select a different destination or contact the driver.'
            );
        }

        $driverName = self::resolveDriverName($conn, $trip, $busNo);
        $direction = self::directionLabel($conn, $trip);

        // ---------------------------------------------------------------
        // 6. Attach the passenger to the active trip (single transaction)
        // ---------------------------------------------------------------
        $conn->beginTransaction();

        try {
            // Re-resolve the active trip inside the transaction: a trip that
            // ended or changed while the form was open must never be attached.
            $freshTrip = self::resolveActiveTrip($conn, $busNo);
            if (!$freshTrip || (string) $freshTrip['id'] !== $submittedTripNo) {
                $conn->rollBack();
                return self::fail('TRIP_CHANGED', 'The trip is no longer active. Please scan the Bus QR again.');
            }

            $existing = self::findBySubmissionToken($conn, $submissionToken);
            if ($existing) {
                $conn->rollBack();
                return self::duplicatePayload($existing);
            }

            if ($sessionPassengerId > 0) {
                $existingTrip = self::tripForPassengerAndTrip(
                    $conn,
                    $sessionPassengerId,
                    $submittedTripNo,
                    $freshTrip['trip_number'] ?? null
                );
                if ($existingTrip) {
                    $conn->rollBack();
                    return self::duplicatePayload($existingTrip);
                }
            }

            $passengerId = $sessionPassengerId;
            $passengerExists = false;
            if ($passengerId > 0) {
                $checkStmt = $conn->prepare("SELECT id FROM passengers WHERE id = ? LIMIT 1");
                $checkStmt->execute([$passengerId]);
                $passengerExists = (bool) $checkStmt->fetchColumn();
            }

            if ($passengerExists) {
                // Update passenger details for this new trip (preserves passenger identity & all past trips)
                $stmt = $conn->prepare("
                    UPDATE passengers
                    SET full_name = ?,
                        discount_type = ?,
                        contact_number = COALESCE(?, contact_number),
                        declared_id_number = COALESCE(?, declared_id_number),
                        declared_id_status = COALESCE(?, declared_id_status),
                        latitude = COALESCE(?, latitude),
                        longitude = COALESCE(?, longitude)
                    WHERE id = ?
                ");
                $stmt->execute([
                    $fullName,
                    $type['discount_type'],
                    $contactNumber !== '' ? $contactNumber : null,
                    $declaredIdNumber !== '' ? $declaredIdNumber : null,
                    $declaredIdStatus,
                    $latitude,
                    $longitude,
                    $passengerId,
                ]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO passengers
                    (
                        full_name,
                        discount_type,
                        type,
                        space_occupied,
                        registration_type,
                        contact_number,
                        declared_id_number,
                        declared_id_status,
                        latitude,
                        longitude
                    )
                    VALUES (?, ?, 'passenger', 1, 'one_time', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $fullName,
                    $type['discount_type'],
                    $contactNumber !== '' ? $contactNumber : null,
                    $declaredIdNumber !== '' ? $declaredIdNumber : null,
                    $declaredIdStatus,
                    $latitude,
                    $longitude,
                ]);

                $passengerId = (int) $conn->lastInsertId();
            }

            // Central prefix passenger ID (backfills one-time passengers that
            // were created before the scheme existed).
            try {
                PassengerCodeService::assignIfMissing(
                    $conn,
                    (int)$passengerId,
                    (string)($type['discount_type'] ?? ''),
                    'one_time'
                );
            } catch (\Throwable $e) {
                error_log('one_time passenger code assignment failed: ' . $e->getMessage());
            }

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
                    submission_token,
                    scan_latitude,
                    scan_longitude,
                    scan_recorded_at,
                    scan_stop_id,
                    scan_location_source
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'In Progress', ?, ?, NOW(), 'one_time', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $passengerId,
                $busNo,
                $submittedTripNo,
                $trip['driver_id'] ?? null,
                ($type['label'] ?? $type['discount_type']),
                $boardingStop['destination'],
                $boardingStopId,
                $boardingKm,
                $destinationStop['destination'],
                $destinationId,
                $destinationStop['destination'],
                $destinationId,
                $destinationKm,
                $totalFare,
                $totalFare,
                $baseFare,
                $discountAmount,
                $paymentMethod,
                $latitude,
                $longitude,
                $declaredIdNumber !== '' ? $declaredIdNumber : null,
                $declaredIdStatus,
                $submissionToken,
                $scanLatitude,
                $scanLongitude,
                $scanRecordedAt,
                $scanStopId,
                $scanLocationSource,
            ]);

            $tripId = (int) $conn->lastInsertId();

            // Seed the location history exactly like the existing boarding
            // flow so later GPS tracking keeps working (best-effort).
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
                        accuracy,
                        recorded_at
                    )
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW())
                ");
                $stmt->execute([$tripId, $latitude, $longitude, $latitude, $longitude, $accuracy]);
            } catch (\PDOException $e) {
                // Location history is best-effort; never fail the boarding.
            }

            // Attach any companions inside the same transaction. They share the
            // primary's bus/trip/boarding/destination and get their own fare
            // and trip records (companion_of = primary trip).
            $companionAttach = [];
            if ($normalizedCompanions !== []) {
                $companionAttach = CompanionService::attachToTrip($conn, [
                    'id' => $tripId,
                    'passenger_id' => $passengerId,
                    'bus_no' => $busNo,
                    'trip_no' => $submittedTripNo,
                    'driver_id' => $trip['driver_id'] ?? null,
                    'boarding_point' => $boardingStop['destination'],
                    'boarding_stop_id' => $boardingStopId,
                    'boarding_km_marker' => $boardingKm,
                    'destination' => $destinationStop['destination'],
                    'destination_id' => $destinationId,
                    'selected_destination' => $destinationStop['destination'],
                    'selected_destination_id' => $destinationId,
                    'selected_km_marker' => $destinationKm,
                    'payment_method' => $paymentMethod,
                    'trip_status' => 'In Progress',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ], $passengerId, $normalizedCompanions, [
                    'registration_type' => 'one_time',
                    'token' => $submissionToken,
                ]);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            // Unique-token race (two identical confirmations at once): the
            // other request won, so return its result instead of an error.
            if (strpos((string) $e->getCode(), '23000') !== false) {
                $existing = self::findBySubmissionToken($conn, $submissionToken);
                if ($existing) {
                    return self::duplicatePayload($existing);
                }
                if ($sessionPassengerId > 0) {
                    $existing = self::tripForPassengerAndTrip($conn, $sessionPassengerId, $submittedTripNo, $trip['trip_number'] ?? null);
                    if ($existing) {
                        return self::duplicatePayload($existing);
                    }
                }
            }

            return self::fail('SAVE_FAILED', 'We could not save your registration. Please try again.');
        }

        $party = CompanionService::partyBreakdown($conn, [
            'id' => $tripId,
            'passenger_id' => $passengerId,
            'passenger_name' => $fullName,
            'discount_type' => $type['discount_type'],
            'boarding_point' => $boardingStop['destination'],
            'destination' => $destinationStop['destination'],
            'fare' => $totalFare,
            'base_fare' => $baseFare,
            'discount_amount' => $discountAmount,
        ], CompanionService::companionRows($conn, $tripId), []);

        return [
            'success' => true,
            'duplicate' => false,
            'data' => [
                'trip_id' => $tripId,
                'passenger_id' => $passengerId,
                'passenger_name' => $fullName,
                'passenger_type' => $type['key'],
                'passenger_type_label' => ($type['label'] ?? $type['discount_type']),
                'discount_type' => $type['discount_type'],
                'declared_id_number' => $declaredIdNumber !== '' ? $declaredIdNumber : null,
                'declared_id_status' => $declaredIdStatus,
                'bus_no' => $busNo,
                'trip_no' => $submittedTripNo,
                'trip_number' => $trip['trip_number'] ?? null,
                'driver_id' => $trip['driver_id'] ?? null,
                'driver_name' => $driverName,
                'route_name' => $routeName,
                'direction' => $direction,
                'boarding_point' => $boardingStop['destination'],
                'boarding_id' => $boardingId,
                'boarding_km_marker' => $boardingKm,
                'destination' => $destinationStop['destination'],
                'destination_id' => $destinationId,
                'destination_km_marker' => $destinationKm,
                'fare' => $totalFare,
                'base_fare' => $baseFare,
                'discount_amount' => $discountAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'trip_status' => 'In Progress',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'gps_available' => $latitude !== null && $longitude !== null,
                'companions' => $companionAttach['added'] ?? [],
                'party_fare' => round($totalFare + (float) ($companionAttach['total_fare'] ?? 0), 2),
                'party_total' => $party['party_total'],
                'breakdown' => $party,
            ],
        ];
    }

    /**
     * Fare quote for the registration page. Uses the exact same trip/stop
     * resolution and fare engine as the booking itself and returns the
     * breakdown (base fare, passenger-type discount, total) so the passenger
     * can see the discounted fare that will be stored.
     *
     * @return array{success:bool,code?:string,message?:string,data?:array}
     */
    public static function quoteFare(PDO $conn, array $input): array
    {
        $busNo = trim((string) ($input['bus_no'] ?? ''));
        if ($busNo === '') {
            return self::fail('INVALID_BUS', 'This QR code is not a valid Bus QR. Please scan the Bus QR again.');
        }

        $tripNo = trim((string) ($input['trip_no'] ?? ''));
        if ($tripNo === '') {
            return self::fail('INVALID_TRIP', 'We could not identify the current trip. Please scan the Bus QR again.');
        }

        $type = self::mapPassengerType((string) ($input['passenger_type'] ?? ''));
        if ($type === null) {
            return self::fail('INVALID_PASSENGER_TYPE', 'Please select a valid passenger type.');
        }

        $bus = self::resolveBus($conn, $busNo);
        if (!$bus) {
            return self::fail('BUS_NOT_FOUND', 'This bus is not registered in R-CONNECT. Please contact the driver.');
        }

        $trip = self::resolveActiveTrip($conn, $busNo);
        if (!$trip) {
            return self::fail('NO_ACTIVE_TRIP', 'This bus no longer has an active trip. Please scan the Bus QR again.');
        }
        if ((string) $trip['id'] !== $tripNo) {
            return self::fail('TRIP_CHANGED', 'The trip has changed since you started. Please scan the Bus QR again.');
        }

        $routeName = trim((string) $trip['route_name']);
        $routeStops = self::resolveRouteStops($conn, $routeName);
        if (!$routeStops) {
            return self::fail('NO_ROUTE_STOPS', 'No stops are available for this trip. Please contact the driver.');
        }

        $boardingId = (int) ($input['boarding_id'] ?? 0);
        $destinationId = (int) ($input['destination_id'] ?? 0);

        // Price the pair the passenger already chose from the scanned trip's
        // boarding/destination list. The amount always comes from the original
        // fare_pricing rows through the fare engine; the live bus position is
        // deliberately not re-read here, because the bus moves while the
        // passenger fills the form and a moved bus must not turn a valid
        // selection into "Fare unavailable". The final registration
        // (registerBooking()) still revalidates the boarding stop and the
        // remaining destinations against the current position.
        $boardingStop = self::findFareStop($routeStops, $boardingId);
        if (!$boardingStop) {
            return self::fail(
                'INVALID_BOARDING',
                'Your boarding point is no longer part of this trip. Please go back and try again.'
            );
        }

        $destinationStop = self::findFareStop($routeStops, $destinationId);
        if (!$destinationStop) {
            return self::fail('INVALID_DESTINATION', 'Please choose a destination from the remaining stops.');
        }

        // The local option (boarding = destination) is offered by the
        // destination list; the fare engine charges the minimum fare for the
        // zero-distance ride. A destination strictly behind the boarding point
        // is still refused.
        if ((int) $destinationStop['display_order'] < (int) $boardingStop['display_order']) {
            return self::fail('INVALID_DESTINATION', 'That stop is behind your boarding point. Please choose a stop ahead of your boarding point.');
        }

        $boardingRouteStop = self::resolveRouteStop($conn, $routeName, (string) $boardingStop['destination'], $boardingId);
        $destinationRouteStop = self::resolveRouteStop($conn, $routeName, (string) $destinationStop['destination'], $destinationId);

        $breakdown = self::fareBreakdown(
            $conn,
            (int) $trip['id'],
            $boardingStop,
            $destinationStop,
            $type,
            $destinationId,
            $boardingRouteStop ? (int) $boardingRouteStop['id'] : null,
            $destinationRouteStop ? (int) $destinationRouteStop['id'] : null
        );

        if ($breakdown['fare'] <= 0) {
            return self::fail(
                'NO_FARE',
                'We could not compute the fare for this trip. Please select a different destination or contact the driver.'
            );
        }

        $primaryLine = [
            'id' => 0,
            'passenger_id' => 0,
            'passenger_name' => trim((string) ($input['full_name'] ?? '')) !== '' ? (string) $input['full_name'] : 'You',
            'discount_type' => $type['discount_type'],
            'boarding_point' => $boardingStop['destination'],
            'destination' => $destinationStop['destination'],
            'fare' => $breakdown['fare'],
            'base_fare' => $breakdown['base_fare'],
            'discount_amount' => $breakdown['discount_amount'],
        ];

        $companionRows = [];
        $rawCompanions = $input['companions'] ?? [];
        if (is_array($rawCompanions) && $rawCompanions !== []) {
            $normalized = CompanionService::normalizeCompanions($rawCompanions);
            if ($normalized['error'] !== null) {
                return self::fail('INVALID_COMPANION', $normalized['error']);
            }
            foreach ($normalized['companions'] as $c) {
                $fareKey = CompanionService::fareKeyForType($c['companion_type'], $c['declared_id_number']);
                $companionFare = self::fareBreakdown(
                    $conn,
                    (int) $trip['id'],
                    $boardingStop,
                    $destinationStop,
                    ['key' => $fareKey],
                    $destinationId,
                    $boardingRouteStop ? (int) $boardingRouteStop['id'] : null,
                    $destinationRouteStop ? (int) $destinationRouteStop['id'] : null
                );
                $companionRows[] = [
                    'id' => 0,
                    'passenger_id' => 0,
                    'passenger_name' => $c['full_name'],
                    'discount_type' => CompanionService::discountLabelForType($c['companion_type'], $c['declared_id_number']),
                    'boarding_point' => $boardingStop['destination'],
                    'destination' => $destinationStop['destination'],
                    'fare' => $companionFare['fare'],
                    'base_fare' => $companionFare['base_fare'],
                    'discount_amount' => $companionFare['discount_amount'],
                ];
            }
        }

        $party = CompanionService::partyBreakdown($conn, $primaryLine, $companionRows, []);

        return [
            'success' => true,
            'data' => [
                'passenger_type' => $type['key'],
                'discount_type' => $type['discount_type'],
                'boarding_point' => $boardingStop['destination'],
                'destination' => $destinationStop['destination'],
                'fare' => $breakdown['fare'],
                'base_fare' => $breakdown['base_fare'],
                'discount_amount' => $breakdown['discount_amount'],
                'fare_column' => $breakdown['fare_column'],
                'party_total' => $party['party_total'],
                'companions' => array_slice($party['lines'], 1),
                'breakdown' => $party,
            ],
        ];
    }

    // =====================================================================
    // Resolution helpers
    // =====================================================================

    public static function resolveBus(PDO $conn, string $busId): ?array
    {
        $stmt = $conn->prepare("
            SELECT id, bus_id, plate_number, seats, standing_capacity, route_name, status
            FROM buses
            WHERE LOWER(TRIM(bus_id)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$busId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * The bus's current active trip.
     *
     * Matches the driver app's notion of an active trip (Scheduled/Departed/
     * On Route/Started/Active/Ongoing) and deliberately does NOT require
     * `trip_date = CURDATE()`: dispatcher-created trips use the Asia/Manila
     * date while MySQL's CURDATE() follows the database server timezone, so a
     * strict "today" filter hides a departed trip around the clock boundary.
     * A +/- 1 day window keeps clock/timezone skew and trips running past
     * midnight working while still excluding genuinely old rows.
     */
    public static function resolveActiveTrip(PDO $conn, string $busId): ?array
    {
        $statuses = self::ACTIVE_TRIP_STATUSES;
        $statusPlaceholders = implode(', ', array_fill(0, count($statuses), '?'));

        $stmt = $conn->prepare("
            SELECT
                id,
                bus_id,
                driver_id,
                route_name,
                from_terminal_id,
                to_terminal_id,
                trip_date,
                trip_number,
                departure_time,
                status
            FROM bus_trips
            WHERE LOWER(TRIM(bus_id)) = LOWER(TRIM(?))
              AND status IN ($statusPlaceholders)
              AND trip_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ORDER BY
                CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END ASC,
                id DESC
            LIMIT 1
        ");

        $stmt->execute(array_merge([$busId], $statuses));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Support diagnostics: when no active trip is found, record what the bus
     * actually has so the cause (bus id, date, or status mismatch) is visible
     * in the PHP error log.
     */
    private static function logNoActiveTrip(PDO $conn, string $busId): void
    {
        try {
            $stmt = $conn->prepare("
                SELECT id, trip_date, departure_time, status
                FROM bus_trips
                WHERE LOWER(TRIM(bus_id)) = LOWER(TRIM(?))
                ORDER BY id DESC
                LIMIT 3
            ");
            $stmt->execute([$busId]);

            error_log(
                '[OneTime] no active trip for bus "' . $busId . '"; recent trips: '
                . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC))
            );
        } catch (\Throwable $e) {
            // Diagnostics only - never affects the response.
        }
    }

    /**
     * Resolves both the numeric id and alphanumeric trip_number for a bus trip identifier.
     *
     * @param PDO $conn
     * @param string|int|null $tripIdentifier
     * @return array{0:?string,1:?string} [id, trip_number]
     */
    public static function resolveTripIdentifiers(PDO $conn, $tripIdentifier): array
    {
        $id = null;
        $number = null;

        if ($tripIdentifier === null || trim((string) $tripIdentifier) === '') {
            return [$id, $number];
        }

        $ident = trim((string) $tripIdentifier);

        try {
            $stmt = $conn->prepare("
                SELECT id, trip_number
                FROM bus_trips
                WHERE id = ? OR LOWER(TRIM(trip_number)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$ident, $ident]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $id = (string) $row['id'];
                $number = !empty($row['trip_number']) ? (string) $row['trip_number'] : null;
            } else {
                $id = $ident;
            }
        } catch (\Throwable $e) {
            $id = $ident;
        }

        return [$id, $number];
    }

    /**
     * Finds an existing one-time passenger trip specifically for a given passenger and bus trip.
     *
     * @param PDO $conn
     * @param int $passengerId
     * @param string|int $tripIdentifier (bus_trips id or trip_number)
     * @param string|null $tripNumber (optional direct trip_number)
     * @return array|null
     */
    public static function tripForPassengerAndTrip(PDO $conn, int $passengerId, $tripIdentifier, ?string $tripNumber = null): ?array
    {
        if ($passengerId <= 0 || empty($tripIdentifier)) {
            return null;
        }

        [$resolvedId, $resolvedNumber] = self::resolveTripIdentifiers($conn, $tripIdentifier);
        if ($tripNumber !== null && trim($tripNumber) !== '') {
            $resolvedNumber = trim($tripNumber);
        }

        try {
            $stmt = $conn->prepare("
                SELECT
                    pt.id,
                    pt.passenger_id,
                    pt.bus_no,
                    pt.trip_no,
                    pt.driver_id,
                    pt.passenger_type,
                    pt.boarding_point,
                    pt.destination,
                    pt.fare,
                    pt.base_fare,
                    pt.discount_amount,
                    pt.payment_method,
                    pt.payment_status,
                    pt.trip_status,
                    pt.declared_id_number,
                    pt.declared_id_status,
                    pt.submission_token,
                    p.full_name AS passenger_name,
                    p.discount_type AS passenger_discount_type
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE pt.passenger_id = ?
                  AND pt.identification_source = 'one_time'
                  AND (
                      pt.trip_no = ?
                      OR (? IS NOT NULL AND pt.trip_no = ?)
                  )
                ORDER BY pt.id DESC
                LIMIT 1
            ");
            $stmt->execute([
                $passengerId,
                $resolvedId,
                $resolvedNumber,
                $resolvedNumber
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * The passenger's LIVE one-time trip for a scanned bus trip, or null.
     *
     * Same passenger/source match as tripForPassengerAndTrip plus the live
     * filter the scan gate needs: no terminal dropoff, trip_status not
     * completed/cancelled, not archived, and the bus trip itself not
     * completed/cancelled. Completed or ended rows therefore never force the
     * receipt screen; a new scan starts the new-trip flow instead.
     *
     * Matching is bus-trip-id-primary: when the identifier resolves to a
     * bus_trips row, only pt.trip_no = <that id> matches, so a different trip
     * whose trip_number equals this id can never be picked up. The
     * alphanumeric number is only used when no bus trip resolves at all.
     *
     * @param PDO $conn
     * @param int $passengerId
     * @param string|int $tripIdentifier (bus_trips id or trip_number)
     * @param string|null $tripNumber (optional direct trip_number)
     * @return array|null
     */
    public static function liveTripForPassengerAndTrip(PDO $conn, int $passengerId, $tripIdentifier, ?string $tripNumber = null): ?array
    {
        if ($passengerId <= 0 || empty($tripIdentifier)) {
            return null;
        }

        $identifier = trim((string) $tripIdentifier);
        $idPrimary = null;
        $numberFallback = null;

        try {
            $stmt = $conn->prepare("
                SELECT id, trip_number
                FROM bus_trips
                WHERE id = ? OR LOWER(TRIM(trip_number)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $idPrimary = (string) $row['id'];
                $numberFallback = !empty($row['trip_number']) ? trim((string) $row['trip_number']) : null;
            }
        } catch (\Throwable $e) {
            $idPrimary = null;
        }

        if ($idPrimary === null && $tripNumber !== null && trim($tripNumber) !== '') {
            $numberFallback = trim($tripNumber);
        }

        $matchValue = $idPrimary !== null
            ? $idPrimary
            : ($numberFallback !== null && $numberFallback !== '' ? $numberFallback : $identifier);

        if ($matchValue === '') {
            return null;
        }

        /* PDO errors deliberately propagate: the scan gate must be able to tell
           "no live trip" from "the status check failed" and keep the trip open
           for recovery instead of starting a fresh booking. */
        $stmt = $conn->prepare("
            SELECT
                pt.id,
                pt.passenger_id,
                pt.bus_no,
                pt.trip_no,
                pt.driver_id,
                pt.passenger_type,
                pt.boarding_point,
                pt.destination,
                pt.fare,
                pt.base_fare,
                pt.discount_amount,
                pt.payment_method,
                pt.payment_status,
                pt.trip_status,
                pt.declared_id_number,
                pt.declared_id_status,
                pt.submission_token,
                p.full_name AS passenger_name,
                p.discount_type AS passenger_discount_type
            FROM passenger_trips pt
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            WHERE pt.passenger_id = ?
              AND pt.identification_source = 'one_time'
              AND pt.trip_no = ?
              AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '')
              AND (pt.trip_status IS NULL OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled'))
              AND LOWER(COALESCE(pt.archived_status, 'no')) <> 'yes'
              AND (bt.id IS NULL OR LOWER(COALESCE(bt.status, '')) NOT IN ('completed', 'cancelled', 'interrupted'))
            ORDER BY pt.id DESC
            LIMIT 1
        ");
        $stmt->execute([$passengerId, $matchValue]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * The passenger's LIVE one-time row on a bus whose current trip cannot be
     * resolved at all, or null.
     *
     * Used by the scan gate when the scanned bus reports no active trip: a
     * row still in progress on that bus is an unresolved previous trip and is
     * kept open for recovery instead of starting a fresh booking. Rows whose
     * bus trip is resolvable and terminal are excluded, so a genuinely
     * completed trip never matches.
     *
     * @return array|null
     */
    public static function liveTripForPassengerAndBus(PDO $conn, int $passengerId, $busNo): ?array
    {
        $busNo = trim((string) $busNo);

        if ($passengerId <= 0 || $busNo === '') {
            return null;
        }

        /* PDO errors deliberately propagate (see liveTripForPassengerAndTrip). */
        $stmt = $conn->prepare("
            SELECT
                pt.id,
                pt.passenger_id,
                pt.bus_no,
                pt.trip_no,
                pt.driver_id,
                pt.passenger_type,
                pt.boarding_point,
                pt.destination,
                pt.fare,
                pt.base_fare,
                pt.discount_amount,
                pt.payment_method,
                pt.payment_status,
                pt.trip_status,
                pt.declared_id_number,
                pt.declared_id_status,
                pt.submission_token,
                p.full_name AS passenger_name,
                p.discount_type AS passenger_discount_type
            FROM passenger_trips pt
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            WHERE pt.passenger_id = ?
              AND pt.identification_source = 'one_time'
              AND LOWER(TRIM(pt.bus_no)) = LOWER(TRIM(?))
              AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '')
              AND (pt.trip_status IS NULL OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled'))
              AND LOWER(COALESCE(pt.archived_status, 'no')) <> 'yes'
              AND (bt.id IS NULL OR LOWER(COALESCE(bt.status, '')) NOT IN ('completed', 'cancelled', 'interrupted'))
            ORDER BY pt.id DESC
            LIMIT 1
        ");
        $stmt->execute([$passengerId, $busNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * The passenger's latest one-time trip. Optionally scoped to a specific bus trip.
     * Used to re-open the in-flow receipt/payment screen.
     */
    public static function latestTripForPassenger(PDO $conn, int $passengerId, $tripIdentifier = null): ?array
    {
        if ($passengerId <= 0) {
            return null;
        }

        if ($tripIdentifier !== null && trim((string) $tripIdentifier) !== '') {
            return self::tripForPassengerAndTrip($conn, $passengerId, $tripIdentifier);
        }

        try {
            $stmt = $conn->prepare("
                SELECT pt.id,
                       pt.passenger_id,
                       pt.bus_no,
                       pt.trip_no,
                       pt.payment_method,
                       pt.payment_status,
                       pt.passenger_type,
                       pt.declared_id_number,
                       pt.declared_id_status,
                       pt.boarding_point,
                       pt.destination,
                       pt.fare,
                       pt.submission_token,
                       p.full_name AS passenger_name
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE pt.passenger_id = ?
                  AND pt.identification_source = 'one_time'
                ORDER BY pt.id DESC
                LIMIT 1
            ");
            $stmt->execute([$passengerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return null;
        }

        return $row ?: null;
    }

    /**
     * Switch a one-time trip's payment method (cash <-> gcash) from the
     * receipt screen. Only the passenger's own unpaid one-time trip can be
     * changed, and switching away from an active QR Ph code is refused so a
     * passenger can never pay both the QR and the driver.
     */
    public static function updatePaymentMethod(PDO $conn, int $passengerId, int $tripId, string $method): array
    {
        $method = strtolower(trim($method));

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            return self::fail('INVALID_PAYMENT_METHOD', 'Please choose how you would like to pay.');
        }

        if ($passengerId <= 0 || $tripId <= 0) {
            return self::fail('INVALID_TRIP', 'We could not find your trip. Please scan the Bus QR again.');
        }

        try {
            ensurePaymongoSchema($conn);

            $stmt = $conn->prepare("
                SELECT id, payment_method, payment_status,
                       paymongo_intent_id, paymongo_qr_expires_at
                FROM passenger_trips
                WHERE id = ? AND passenger_id = ?
                  AND identification_source = 'one_time'
                LIMIT 1
            ");
            $stmt->execute([$tripId, $passengerId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return self::fail('SERVER_ERROR', 'Something went wrong. Please try again.');
        }

        if (!$trip) {
            return self::fail('TRIP_NOT_FOUND', 'We could not find your trip. Please scan the Bus QR again.');
        }

        if (strtolower((string) ($trip['payment_status'] ?? '')) === 'paid') {
            return self::fail('ALREADY_PAID', 'This trip is already paid.');
        }

        $current = strtolower((string) ($trip['payment_method'] ?? ''));

        if ($current === $method) {
            return [
                'success' => true,
                'changed' => false,
                'payment_method' => $current,
                'payment_status' => (string) ($trip['payment_status'] ?? 'pending'),
            ];
        }

        // A still-active QR Ph code could be paid after switching to cash,
        // which would charge the passenger twice.
        if ($current === 'gcash' && $method === 'cash') {
            $intentId = trim((string) ($trip['paymongo_intent_id'] ?? ''));
            $expiresRaw = trim((string) ($trip['paymongo_qr_expires_at'] ?? ''));
            $expiresTs = $expiresRaw !== '' ? strtotime($expiresRaw) : 0;

            if ($intentId !== '' && $expiresTs > time()) {
                return self::fail(
                    'ACTIVE_QR',
                    'Your GCash code is still active. Pay it with your app, or wait for it to expire before switching to cash.'
                );
            }
        }

        try {
            if ($method === 'cash') {
                // Remove the stale code so the receipt cannot show an
                // unusable QR. The intent id is kept for traceability.
                $stmt = $conn->prepare("
                    UPDATE passenger_trips
                    SET payment_method = 'cash',
                        paymongo_qr_image = NULL,
                        paymongo_qr_expires_at = NULL
                    WHERE id = ? AND passenger_id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    UPDATE passenger_trips
                    SET payment_method = 'gcash'
                    WHERE id = ? AND passenger_id = ?
                ");
            }

            $stmt->execute([$tripId, $passengerId]);
        } catch (\PDOException $e) {
            return self::fail('SERVER_ERROR', 'We could not update your payment method. Please try again.');
        }

        return [
            'success' => true,
            'changed' => true,
            'payment_method' => $method,
            'payment_status' => (string) ($trip['payment_status'] ?? 'pending'),
        ];
    }

    /**
     * Driver of the trip, falling back to the bus assignment when the trip
     * row has no driver.
     */
    public static function resolveDriverName(PDO $conn, array $trip, string $busId): ?string
    {
        $driverId = trim((string) ($trip['driver_id'] ?? ''));

        if ($driverId !== '') {
            try {
                $stmt = $conn->prepare("SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1");
                $stmt->execute([$driverId]);
                $name = $stmt->fetchColumn();
                if ($name !== false && trim((string) $name) !== '') {
                    return trim((string) $name);
                }
            } catch (\PDOException $e) {
                // Fall through to the bus assignment lookup.
            }
        }

        try {
            $stmt = $conn->prepare("
                SELECT d.full_name
                FROM driver_bus_assignment dba
                LEFT JOIN drivers d ON dba.driver_id = d.driver_id
                WHERE LOWER(TRIM(dba.bus_id)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$busId]);
            $name = $stmt->fetchColumn();
            if ($name !== false && trim((string) $name) !== '') {
                return trim((string) $name);
            }
        } catch (\PDOException $e) {
            // No driver name available.
        }

        return $driverId !== '' ? $driverId : null;
    }

    /**
     * Human-readable direction label from the trip's terminals, falling back
     * to the route name (e.g. "Rizal → Cabanatuan").
     */
    public static function directionLabel(PDO $conn, array $trip): string
    {
        $routeName = trim((string) ($trip['route_name'] ?? ''));
        $fromId = (int) ($trip['from_terminal_id'] ?? 0);
        $toId = (int) ($trip['to_terminal_id'] ?? 0);

        if ($fromId <= 0 || $toId <= 0) {
            return $routeName;
        }

        try {
            $stmt = $conn->prepare("
                SELECT id, terminal_name, terminal_code
                FROM terminals
                WHERE id IN (?, ?)
            ");
            $stmt->execute([$fromId, $toId]);

            $names = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $label = trim((string) ($row['terminal_name'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($row['terminal_code'] ?? ''));
                }
                $names[(int) $row['id']] = $label;
            }

            $from = $names[$fromId] ?? '';
            $to = $names[$toId] ?? '';

            if ($from !== '' && $to !== '') {
                return $from . ' → ' . $to;
            }
        } catch (\PDOException $e) {
            // Fall back to the route name.
        }

        return $routeName;
    }

    /**
     * The route's stops in fare/route order (fare_pricing is the authoritative
     * stop list used by the existing fare engine).
     */
    public static function resolveRouteStops(PDO $conn, string $routeName): array
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return [];
        }

        // The trip's route_name may use the route_stops convention
        // ("Cabanatuan to Rizal Route") while fare_pricing uses
        // "Cabanatuan-Rizal"; resolve the canonical match before querying.
        $fareRouteName = function_exists('resolveFareRouteName')
            ? resolveFareRouteName($conn, $routeName)
            : $routeName;

        try {
            $stmt = $conn->prepare("
                SELECT id, destination, display_order, regular_fare, student_fare, special_fare
                FROM fare_pricing
                WHERE route_name = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$fareRouteName]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'destination' => (string) $row['destination'],
                'display_order' => (int) $row['display_order'],
                'regular_fare' => (float) $row['regular_fare'],
                'student_fare' => (float) $row['student_fare'],
                'special_fare' => (float) $row['special_fare'],
            ];
        }, $rows);
    }

    /**
     * Route-stop metadata (id / km marker) for a fare stop, preferring the
     * route_stops.fare_pricing_id bridge and falling back to the stop name
     * restricted to the same route.
     */
    public static function resolveRouteStop(
        PDO $conn,
        string $routeName,
        string $stopName,
        ?int $farePricingId = null
    ): ?array {
        $row = null;

        if ($farePricingId !== null && $farePricingId > 0) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, route_name, stop_name, sequence_order, km_marker, fare_pricing_id
                    FROM route_stops
                    WHERE fare_pricing_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$farePricingId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\PDOException $e) {
                $row = null;
            }
        }

        if (!$row && trim($stopName) !== '') {
            try {
                $stmt = $conn->prepare("
                    SELECT id, route_name, stop_name, sequence_order, km_marker, fare_pricing_id
                    FROM route_stops
                    WHERE LOWER(TRIM(stop_name)) = LOWER(TRIM(?))
                    ORDER BY id ASC
                    LIMIT 5
                ");
                $stmt->execute([$stopName]);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e) {
                $candidates = [];
            }

            $canonicalRoute = self::canonicalRoute($routeName);
            foreach ($candidates as $candidate) {
                if (self::canonicalRoute((string) ($candidate['route_name'] ?? '')) === $canonicalRoute) {
                    $row = $candidate;
                    break;
                }
            }
        }

        return $row;
    }

    /**
     * Maps the UI passenger type to the stored discount type and the fare
     * engine's type key. Returns null for unknown types.
     */
    public static function mapPassengerType(string $type): ?array
    {
        $normalized = strtolower(trim($type));

        switch ($normalized) {
            case 'regular':
                return ['key' => 'regular', 'discount_type' => 'Regular', 'label' => 'Regular', 'requires_id' => false, 'id_label' => null];
            case 'student':
                return ['key' => 'student', 'discount_type' => 'Student', 'label' => 'Student', 'requires_id' => true, 'id_label' => 'Student ID Number'];
            case 'senior':
            case 'senior citizen':
                return ['key' => 'senior', 'discount_type' => 'Senior', 'label' => 'Senior Citizen', 'requires_id' => true, 'id_label' => 'Senior Citizen ID Number'];
            case 'pwd':
                return ['key' => 'pwd', 'discount_type' => 'Senior', 'label' => 'PWD', 'requires_id' => true, 'id_label' => 'PWD ID Number'];
            case 'special':
            case 'teacher':
            case 'nurse':
                return ['key' => 'special', 'discount_type' => 'Special', 'label' => 'Teacher / Nurse', 'requires_id' => true, 'id_label' => 'Teacher / Nurse ID Number'];
            default:
                return null;
        }
    }

    // =====================================================================
    // Internal helpers
    // =====================================================================

    /**
     * Run the existing fare engine for the booking's stops, once at the
     * regular rate (base) and once at the passenger's own rate, so the quote,
     * the confirmation and the stored trip share one computation.
     *
     * @return array{base_fare:float,fare:float,discount_amount:float,fare_column:?string}
     */
    private static function fareBreakdown(
        PDO $conn,
        int $tripId,
        array $boardingStop,
        array $destinationStop,
        array $type,
        ?int $destinationId,
        ?int $boardingStopId,
        ?int $destinationStopId
    ): array {
        $baseFareResult = calculateFare(
            $tripId,
            $boardingStop['destination'],
            $destinationStop['destination'],
            'regular',
            null,
            $destinationId,
            $boardingStopId,
            $destinationStopId
        );

        $fareResult = calculateFare(
            $tripId,
            $boardingStop['destination'],
            $destinationStop['destination'],
            $type['key'],
            null,
            $destinationId,
            $boardingStopId,
            $destinationStopId
        );

        $baseFare = (float) ($baseFareResult['fare'] ?? 0);
        $fare = (float) ($fareResult['fare'] ?? 0);

        if ($baseFare <= 0) {
            $baseFare = $fare;
        }

        return [
            'base_fare' => $baseFare,
            'fare' => $fare,
            'discount_amount' => max($baseFare - $fare, 0),
            'fare_column' => $fareResult['_fare_column'] ?? null,
        ];
    }

    private static function findFareStop(array $routeStops, int $farePricingId): ?array
    {
        if ($farePricingId <= 0) {
            return null;
        }

        foreach ($routeStops as $stop) {
            if ((int) $stop['id'] === $farePricingId) {
                return $stop;
            }
        }

        return null;
    }

    private static function findBySubmissionToken(PDO $conn, string $token): ?array
    {
        try {
            $stmt = $conn->prepare("
                SELECT
                    pt.id,
                    pt.passenger_id,
                    pt.bus_no,
                    pt.trip_no,
                    pt.driver_id,
                    pt.boarding_point,
                    pt.destination,
                    pt.fare,
                    pt.base_fare,
                    pt.discount_amount,
                    pt.payment_method,
                    pt.payment_status,
                    pt.trip_status,
                    pt.declared_id_number,
                    pt.declared_id_status,
                    pt.submission_token,
                    p.full_name AS passenger_name,
                    p.discount_type AS passenger_discount_type
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE pt.submission_token = ?
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Public lookup used to recover a one-time receipt when the lightweight
     * session is gone: the trip id and the client's submission token must match.
     *
     * @return array{id:int,passenger_id:int,payment_method:string,payment_status:string,submission_token:string,passenger_name:?string}|null
     */
    public static function tripBySubmissionToken(PDO $conn, int $tripId, string $token): ?array
    {
        $tripId = (int) $tripId;
        $token = trim($token);

        if ($tripId <= 0 || $token === '') {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT
                    pt.id,
                    pt.passenger_id,
                    pt.payment_method,
                    pt.payment_status,
                    pt.submission_token,
                    p.full_name AS passenger_name
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE pt.id = ?
                  AND pt.submission_token = ?
                  AND pt.identification_source = 'one_time'
                LIMIT 1
            ");
            $stmt->execute([$tripId, $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Response for a repeated submission: report the already-created trip
     * instead of attaching the passenger again.
     */
    private static function duplicatePayload(array $existing): array
    {
        return [
            'success' => true,
            'duplicate' => true,
            'data' => [
                'trip_id' => (int) $existing['id'],
                'passenger_id' => (int) ($existing['passenger_id'] ?? 0),
                'passenger_name' => $existing['passenger_name'] ?? null,
                'discount_type' => $existing['passenger_discount_type'] ?? null,
                'bus_no' => $existing['bus_no'] ?? null,
                'trip_no' => $existing['trip_no'] ?? null,
                'boarding_point' => $existing['boarding_point'] ?? null,
                'destination' => $existing['destination'] ?? null,
                'fare' => (float) ($existing['fare'] ?? 0),
                'base_fare' => (float) ($existing['base_fare'] ?? 0),
                'discount_amount' => (float) ($existing['discount_amount'] ?? 0),
                'payment_method' => $existing['payment_method'] ?? null,
                'payment_status' => $existing['payment_status'] ?? 'pending',
                'trip_status' => $existing['trip_status'] ?? 'In Progress',
                'declared_id_number' => $existing['declared_id_number'] ?? null,
                'declared_id_status' => $existing['declared_id_status'] ?? null,
            ],
        ];
    }

    private static function kmMarker(?array $routeStop): ?int
    {
        if (!$routeStop || !isset($routeStop['km_marker']) || $routeStop['km_marker'] === null || $routeStop['km_marker'] === '') {
            return null;
        }

        return (int) $routeStop['km_marker'];
    }

    private static function canonicalRoute(string $routeName): string
    {
        if (function_exists('canonicalRouteName')) {
            return canonicalRouteName($routeName);
        }

        return strtolower(trim($routeName));
    }

    private static function normalizeCoordinate($value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (!is_finite($number) || $number < $min || $number > $max) {
            return null;
        }

        return $number;
    }

    private static function fail(string $code, string $message): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
