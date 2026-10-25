<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/Companion/CompanionSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerCodeService.php';

use App\Core\Database;

/**
 * Companion Registration support for the R-CONNECT passenger module.
 *
 * A companion is someone travelling without a cellphone who is registered by
 * the passenger they ride with. Companions always get their OWN passenger and
 * passenger_trips rows (so reporting, passenger counts and payment totals stay
 * correct) that are linked back to the primary passenger's trip by
 * passenger_trips.companion_of.
 *
 * Fare rules (eligibility follows the declared type):
 *   - child   -> student/child (discounted) fare
 *   - senior  -> senior (discounted) fare
 *   - other   -> regular (standard) fare
 * The optional declared ID is supporting/audit data only and does not gate the
 * discount.
 */
class CompanionService
{
    /** Allowed companion categories. */
    public const COMPANION_TYPES = ['child', 'senior', 'pwd', 'other'];

    /** Maximum companions a single passenger may attach to one trip. */
    public const MAX_PER_TRIP = 5;

    /**
     * Validate a single companion payload item.
     *
     * @return null|string An error message, or null when the item is valid.
     */
    public static function validateCompanion(array $c): ?string
    {
        $name = trim((string) ($c['full_name'] ?? ''));
        $type = strtolower(trim((string) ($c['companion_type'] ?? '')));

        if (class_exists('App\Passenger\PassengerValidator')) {
            $nameCheck = \App\Passenger\PassengerValidator::validateFullName($name);
            if (!$nameCheck['valid']) {
                return $nameCheck['error'];
            }
        } else {
            if ($name === '' || mb_strlen($name) < 2 || !preg_match("/^[\p{L}\p{M} .'\\-]+$/u", $name)) {
                return 'Please enter the companion\'s full name.';
            }
            if (mb_strlen($name) > 100) {
                return 'Companion name must be at most 100 characters.';
            }
        }

        if (!in_array($type, self::COMPANION_TYPES, true)) {
            return 'Please choose a valid companion type (Child/Student, Senior, PWD or Regular).';
        }

        $declaredId = trim((string) ($c['declared_id_number'] ?? ''));
        if ($type === 'senior' && $declaredId !== '') {
            if (class_exists('App\Passenger\PassengerValidator')) {
                $idCheck = \App\Passenger\PassengerValidator::validateIdNumber($declaredId, 'Senior', false);
                if (!$idCheck['valid']) {
                    return $idCheck['error'];
                }
            } else {
                $idLength = mb_strlen($declaredId);
                $idDigits = preg_match_all('/\d/', $declaredId);
                if (
                    $idLength < 3
                    || $idLength > 50
                    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/', $declaredId)
                    || $idDigits < 3
                ) {
                    return 'Please enter a valid Senior Citizen ID number containing numeric digits.';
                }
            }
        }

        return null;
    }

    /**
     * Validate a whole companions list, preserving the original order.
     *
     * @return array{companions:array,error:?string}
     */
    public static function normalizeCompanions(array $raw): array
    {
        if (!is_array($raw)) {
            return ['companions' => [], 'error' => 'Invalid companions payload.'];
        }

        $list = array_values($raw);
        if (count($list) > self::MAX_PER_TRIP) {
            return [
                'companions' => [],
                'error' => 'You can board with up to ' . self::MAX_PER_TRIP . ' companions at a time.',
            ];
        }

        $clean = [];
        foreach ($list as $index => $c) {
            if (!is_array($c)) {
                continue;
            }

            $name = trim((string) ($c['full_name'] ?? ''));
            $type = strtolower(trim((string) ($c['companion_type'] ?? '')));
            $declaredId = trim((string) ($c['declared_id_number'] ?? ''));

            $error = self::validateCompanion([
                'full_name' => $name,
                'companion_type' => $type,
                'declared_id_number' => $declaredId,
            ]);
            if ($error !== null) {
                return ['companions' => [], 'error' => $error];
            }

            $clean[] = [
                'full_name' => $name,
                'companion_type' => $type,
                'declared_id_number' => $type === 'senior' ? $declaredId : '',
            ];
        }

        return ['companions' => $clean, 'error' => null];
    }

    /**
     * Fare column key used by the fare engine for a companion type.
     *
     * Eligibility follows the declared type: a Child rides on the student/child
     * discount and a Senior Citizen on the senior discount. Regular rides on the
     * standard fare. The declared ID is supporting/audit data only and does not
     * gate the discount.
     */
    public static function fareKeyForType(string $type, string $declaredId = ''): string
    {
        $type = strtolower(trim($type));
        if ($type === 'child') {
            return 'student';
        }
        if ($type === 'senior') {
            return 'senior';
        }
        if ($type === 'pwd') { return 'pwd'; }
        return 'regular';
    }

    /**
     * discount_type label persisted on the companion passenger row.
     */
    public static function discountLabelForType(string $type, string $declaredId = ''): string
    {
        switch (self::fareKeyForType($type, $declaredId)) {
            case 'student':
                return 'Student';
            case 'senior':
                return 'Senior';
            case 'pwd':
                return 'PWD';
            default:
                return 'Regular';
        }
    }

    /**
     * Human-readable label for a companion type.
     */
    public static function companionTypeLabel(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'child') {
            return 'Child';
        }
        if ($type === 'senior') {
            return 'Senior Citizen';
        }
        if ($type === 'pwd') { return 'PWD'; }
        return 'Regular';
    }

    /**
     * Saved companion profiles for an owner passenger.
     */
    public static function profiles(PDO $conn, int $ownerPassengerId): array
    {
        try {
            $stmt = $conn->prepare("
                SELECT id, passenger_id, full_name, companion_type, declared_id_number, declared_id_status, created_at
                FROM passenger_companions
                WHERE owner_passenger_id = ?
                ORDER BY full_name ASC
            ");
            $stmt->execute([$ownerPassengerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Create or refresh a saved profile (deduplicated by owner + full name).
     *
     * @return array{success:bool,error?:string,profile?:array}
     */
    public static function saveProfile(PDO $conn, int $ownerPassengerId, string $name, string $type, string $declaredId = ''): array
    {
        $name = trim($name);
        $type = strtolower(trim($type));
        $declaredId = ($type === 'senior') ? trim($declaredId) : '';
        $declaredStatus = ($type === 'senior' && $declaredId !== '') ? 'self_declared' : null;

        try {
            $existing = self::profileByName($conn, $ownerPassengerId, $name);
            if ($existing) {
                $stmt = $conn->prepare("
                    UPDATE passenger_companions
                    SET companion_type = ?, declared_id_number = ?, declared_id_status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$type, $declaredId !== '' ? $declaredId : null, $declaredStatus, $existing['id']]);

                $existing['companion_type'] = $type;
                $existing['declared_id_number'] = $declaredId !== '' ? $declaredId : null;
                $existing['declared_id_status'] = $declaredStatus;

                return ['success' => true, 'profile' => $existing];
            }

            $stmt = $conn->prepare("
                INSERT INTO passenger_companions
                (owner_passenger_id, full_name, companion_type, declared_id_number, declared_id_status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ownerPassengerId,
                $name,
                $type,
                $declaredId !== '' ? $declaredId : null,
                $declaredStatus,
            ]);

            $profile = [
                'id' => (int) $conn->lastInsertId(),
                'passenger_id' => null,
                'full_name' => $name,
                'companion_type' => $type,
                'declared_id_number' => $declaredId !== '' ? $declaredId : null,
                'declared_id_status' => $declaredStatus,
            ];

            return ['success' => true, 'profile' => $profile];
        } catch (\PDOException $e) {
            return ['success' => false, 'error' => 'We could not save the companion. Please try again.'];
        }
    }

    /**
     * Remove a saved profile owned by this passenger.
     */
    public static function deleteProfile(PDO $conn, int $ownerPassengerId, int $profileId): bool
    {
        try {
            $stmt = $conn->prepare("DELETE FROM passenger_companions WHERE id = ? AND owner_passenger_id = ?");
            $stmt->execute([$profileId, $ownerPassengerId]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Find a profile by owner + case-insensitive full name.
     */
    private static function profileByName(PDO $conn, int $ownerPassengerId, string $name): ?array
    {
        try {
            $stmt = $conn->prepare("
                SELECT id, passenger_id, full_name, companion_type, declared_id_number, declared_id_status, created_at
                FROM passenger_companions
                WHERE owner_passenger_id = ? AND LOWER(full_name) = LOWER(?)
                LIMIT 1
            ");
            $stmt->execute([$ownerPassengerId, $name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Attach companions to a primary passenger trip.
     *
     * MUST be called inside an open transaction (the caller commits/rolls back).
     * Every companion receives its own passenger + passenger_trips row linked
     * by companion_of. Repeat attaches are idempotent per profile.
     *
     * @param PDO   $conn
     * @param array $primary           Primary passenger_trips row (id, passenger_id,
     *                                 bus_no, trip_no, driver_id, boarding_point,
     *                                 boarding_stop_id, destination / destination_id /
     *                                 selected_destination_id, payment_method,
     *                                 trip_status, latitude, longitude).
     * @param int   $ownerPassengerId  Passenger who is travelling with the companions.
     * @param array $companions        Normalized list [{full_name, companion_type, declared_id_number}].
     * @param array $ctx               {registration_type:'regular'|'one_time', token:string}
     * @return array{added:array,skipped:array,total_fare:float}
     */
    public static function attachToTrip(PDO $conn, array $primary, int $ownerPassengerId, array $companions, array $ctx = []): array
    {
        $registrationType = ($ctx['registration_type'] ?? 'regular') === 'one_time' ? 'one_time' : 'regular';
        $tokenBase = trim((string) ($ctx['token'] ?? ''));
        if ($tokenBase === '') {
            $tokenBase = 'companion-' . (int) ($primary['id'] ?? 0);
        }

        $boarding = trim((string) ($primary['boarding_point'] ?? ''));
        $boardingStopId = !empty($primary['boarding_stop_id']) ? (int) $primary['boarding_stop_id'] : null;

        // The destination (and therefore the fare) is only known when the
        // primary already locked one (for example a one-time booking). For the
        // regular flow the companion row starts with the primary's boarding
        // state; the destination/fare are mirrored later.
        $destination = trim((string) ($primary['destination'] ?? ($primary['selected_destination'] ?? '')));
        $destinationFareId = !empty($primary['selected_destination_id']) ? (int) $primary['selected_destination_id']
            : (!empty($primary['destination_id']) ? (int) $primary['destination_id'] : null);
        $destinationStopId = null;

        if ($destination !== '' && $destinationFareId !== null) {
            $destinationStopId = self::routeStopForFareId($conn, $destinationFareId);
        }

        $added = [];
        $skipped = [];
        $totalFare = 0.0;

        foreach (array_values($companions) as $index => $c) {
            $name = trim((string) ($c['full_name'] ?? ''));
            $type = strtolower(trim((string) ($c['companion_type'] ?? 'other')));
            $declaredId = trim((string) ($c['declared_id_number'] ?? ''));

            // 1. Reuse (or create) the saved profile for this owner.
            $profile = self::profileByName($conn, $ownerPassengerId, $name);
            if (!$profile) {
                $saved = self::saveProfile($conn, $ownerPassengerId, $name, $type, $declaredId);
                if (!$saved['success']) {
                    $skipped[] = ['name' => $name, 'reason' => 'profile_save_failed'];
                    continue;
                }
                $profile = $saved['profile'];
            } elseif ($profile['companion_type'] !== $type || (string) ($profile['declared_id_number'] ?? '') !== $declaredId) {
                $saved = self::saveProfile($conn, $ownerPassengerId, $name, $type, $declaredId);
                if ($saved['success']) {
                    $profile = $saved['profile'];
                }
            }
            $profileId = (int) $profile['id'];

            // 2. Idempotency: the same saved companion on the same trip must
            //    not be attached twice.
            if (self::profileAlreadyOnTrip($conn, (int) ($primary['id'] ?? 0), $profileId)) {
                $skipped[] = ['name' => $name, 'reason' => 'duplicate'];
                continue;
            }

            // 3. Create (once) the companion's own passenger row.
            $companionPassengerId = $profile['passenger_id'] ? (int) $profile['passenger_id'] : null;
            if (!$companionPassengerId) {
                $companionPassengerId = self::createPassengerRow($conn, $name, $type, $declaredId, $primary, $registrationType);
                if (!$companionPassengerId) {
                    $skipped[] = ['name' => $name, 'reason' => 'passenger_create_failed'];
                    continue;
                }
                try {
                    $upd = $conn->prepare("UPDATE passenger_companions SET passenger_id = ? WHERE id = ?");
                    $upd->execute([$companionPassengerId, $profileId]);
                } catch (\PDOException $e) {
                }
            }

            // 4. Fare mirrors the primary's shared boarding/destination under
            //    the companion's own fare rule.
            $fare = null;
            $baseFare = null;
            $discountAmount = null;
            if ($destination !== '' && $boarding !== '') {
                $fareType = self::fareKeyForType($type, $declaredId);
                $fareResult = self::computeFare($conn, $primary, $boarding, $destination, $fareType, $boardingStopId, $destinationStopId, $destinationFareId);
                $baseResult = self::computeFare($conn, $primary, $boarding, $destination, 'regular', $boardingStopId, $destinationStopId, $destinationFareId);
                $baseFare = (float) ($baseResult['fare'] ?? 0);
                $fare = (float) ($fareResult['fare'] ?? 0);
                if ($baseFare <= 0) {
                    $baseFare = $fare;
                }
                $discountAmount = max($baseFare - $fare, 0);
                if ($fare > 0) {
                    $totalFare += $fare;
                }
            }

            // 5. Write the companion's own trip row.
            $token = self::companionToken($tokenBase, $index, $name);

            try {
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
                        companion_of,
                        companion_profile_id
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        'pending', ?, ?, ?, NOW(), 'companion', ?, ?, ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $companionPassengerId,
                    trim((string) ($primary['bus_no'] ?? '')),
                    (int) ($primary['trip_no'] ?? 0),
                    !empty($primary['driver_id']) ? $primary['driver_id'] : null,
                    self::companionTypeLabel($type),
                    $boarding,
                    $boardingStopId !== null ? $boardingStopId : null,
                    !empty($primary['boarding_km_marker']) ? (int) $primary['boarding_km_marker'] : null,
                    $destination !== '' ? $destination : null,
                    $destinationFareId !== null ? $destinationFareId : null,
                    $destination !== '' ? $destination : null,
                    $destinationFareId !== null ? $destinationFareId : null,
                    !empty($primary['selected_km_marker']) ? $primary['selected_km_marker'] : null,
                    $fare !== null ? $fare : null,
                    $fare !== null ? $fare : null,
                    $baseFare !== null ? $baseFare : null,
                    $discountAmount !== null ? $discountAmount : null,
                    strtolower(trim((string) ($primary['payment_method'] ?? 'cash'))),
                    (string) ($primary['trip_status'] ?? 'In Progress'),
                    !empty($primary['latitude']) ? $primary['latitude'] : null,
                    !empty($primary['longitude']) ? $primary['longitude'] : null,
                    $declaredId !== '' ? $declaredId : null,
                    $declaredId !== '' ? 'self_declared' : null,
                    $token,
                    (int) $primary['id'],
                    $profileId,
                ]);

                $added[] = [
                    'id' => (int) $conn->lastInsertId(),
                    'passenger_id' => $companionPassengerId,
                    'name' => $name,
                    'companion_type' => $type,
                    'fare' => $fare !== null ? (float) $fare : null,
                    'profile_id' => $profileId,
                ];
            } catch (\PDOException $e) {
                // Unique-token race (duplicate submit) — treat as already added.
                if (strpos((string) $e->getCode(), '23000') !== false) {
                    $skipped[] = ['name' => $name, 'reason' => 'duplicate'];
                } else {
                    error_log('Companion attach failed: ' . $e->getMessage());
                    $skipped[] = ['name' => $name, 'reason' => 'save_failed'];
                }
            }
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
            'total_fare' => round($totalFare, 2),
        ];
    }

    /**
     * Mirror a locked destination (and its estimated fare) onto every companion
     * of the given primary trip. Fares are recomputed server-side per
     * companion rule using the same boarding/destination the primary got.
     *
     * @return array{updated:int,failed:int}
     */
    public static function mirrorDestination(
        PDO $conn,
        int $primaryTripId,
        int $tripNo,
        string $boardingFareName,
        ?int $boardingFareId,
        string $destinationName,
        int $destinationId,
        ?int $destinationKm
    ): array {
        $companions = self::companionRows($conn, $primaryTripId);
        $updated = 0;
        $failed = 0;

        foreach ($companions as $companion) {
            $declaredType = trim((string) ($companion['passenger_type'] ?? ''));
            $fareType = self::fareKeyForDiscount($declaredType !== '' ? $declaredType : (string) ($companion['discount_type'] ?? ''));
            try {
                $baseResult = calculateFare($tripNo, $boardingFareName, $destinationName, 'regular', $boardingFareId, $destinationId, null, null);
                $fareResult = calculateFare($tripNo, $boardingFareName, $destinationName, $fareType, $boardingFareId, $destinationId, null, null);

                $baseFare = (float) ($baseResult['fare'] ?? 0);
                $fare = (float) ($fareResult['fare'] ?? 0);
                if ($baseFare <= 0) {
                    $baseFare = $fare;
                }
                $discountAmount = max($baseFare - $fare, 0);

                $stmt = $conn->prepare("
                    UPDATE passenger_trips
                    SET selected_destination = ?,
                        selected_destination_id = ?,
                        selected_km_marker = ?,
                        estimated_fare = ?,
                        destination = ?,
                        destination_id = ?,
                        fare = ?,
                        base_fare = ?,
                        discount_amount = ?,
                        trip_status = 'In Progress'
                    WHERE id = ?
                ");
                $stmt->execute([
                    $destinationName,
                    $destinationId,
                    $destinationKm !== null ? $destinationKm : null,
                    $fare,
                    $destinationName,
                    $destinationId,
                    $fare,
                    $baseFare,
                    $discountAmount,
                    (int) $companion['id'],
                ]);
                $updated++;
            } catch (\Throwable $e) {
                error_log('Companion destination mirror failed: ' . $e->getMessage());
                $failed++;
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    /**
     * Mirror a completed dropoff (final destination + fare + payment) onto every
     * companion of the primary trip. Runs inside the caller's transaction.
     *
     * @return array{rows:array} Updated companion rows (fare/base_fare/discount
     *                          applied) so the caller can archive them.
     */
    public static function mirrorDropoff(
        PDO $conn,
        int $primaryTripId,
        int $tripNo,
        string $boarding,
        string $actualDestination,
        ?int $boardingStopId,
        int $actualStopId,
        string $paymentMethod,
        float $cashAmount,
        float $gcashAmount
    ): array {
        $companions = self::companionRows($conn, $primaryTripId);
        $rows = [];

        foreach ($companions as $companion) {
            $declaredType = trim((string) ($companion['passenger_type'] ?? ''));
            $fareType = self::fareKeyForDiscount($declaredType !== '' ? $declaredType : (string) ($companion['discount_type'] ?? ''));
            try {
                $baseResult = calculateFare($tripNo, $boarding, $actualDestination, 'regular', null, null, $boardingStopId, $actualStopId);
                $fareResult = calculateFare($tripNo, $boarding, $actualDestination, $fareType, null, null, $boardingStopId, $actualStopId);

                $baseFare = (float) ($baseResult['fare'] ?? 0);
                $fare = (float) ($fareResult['fare'] ?? 0);
                if ($baseFare <= 0) {
                    $baseFare = $fare;
                }
                $discountAmount = max($baseFare - $fare, 0);

                $stmt = $conn->prepare("
                    UPDATE passenger_trips
                    SET destination = ?,
                        actual_stop_id = ?,
                        actual_km_marker = ?,
                        fare = ?,
                        base_fare = ?,
                        discount_amount = ?,
                        payment_method = ?,
                        cash_amount = ?,
                        gcash_amount = ?,
                        dropoff_time = NOW(),
                        payment_status = 'pending',
                        trip_status = 'Completed'
                    WHERE id = ?
                ");
                $stmt->execute([
                    $actualDestination,
                    $actualStopId,
                    !empty($companion['actual_km_marker']) || isset($companion['actual_km_marker']) && $companion['actual_km_marker'] !== null ? (int) $companion['actual_km_marker'] : null,
                    $fare,
                    $baseFare,
                    $discountAmount,
                    $paymentMethod,
                    $cashAmount,
                    $gcashAmount,
                    (int) $companion['id'],
                ]);

                $companion['destination'] = $actualDestination;
                $companion['fare'] = (float) $fare;
                $companion['base_fare'] = (float) $baseFare;
                $companion['discount_amount'] = (float) $discountAmount;
                $rows[] = $companion;
            } catch (\Throwable $e) {
                error_log('Companion dropoff mirror failed: ' . $e->getMessage());
            }
        }

        return ['rows' => $rows];
    }

    /**
     * Total online balance (centavos) for a trip including its companions that
     * share the primary's payment method. Mirrors the same GCash/hybrid rule
     * used for a single trip row.
     */
    public static function partyOnlineAmountCentavos(PDO $conn, array $primary, ?array $companions = null): int
    {
        $fare = (float) ($primary['fare'] ?? 0);
        $method = strtolower((string) ($primary['payment_method'] ?? ''));

        $amount = 0.0;
        if ($method === 'hybrid') {
            $verifiedCash = (float) ($primary['verified_cash_amount'] ?? ($primary['cash_amount'] ?? 0));
            if ($verifiedCash < 0) {
                $verifiedCash = 0;
            }
            if ($verifiedCash > $fare) {
                $verifiedCash = $fare;
            }
            $amount = $fare - $verifiedCash;
        } else {
            $amount = $fare;
        }

        if ($companions === null) {
            $companions = self::companionRows($conn, (int) ($primary['id'] ?? 0));
        }

        foreach ($companions as $c) {
            if (strtolower((string) ($c['payment_status'] ?? 'pending')) === 'paid') {
                continue;
            }
            $cFare = (float) ($c['fare'] ?? 0);
            if ($cFare <= 0) {
                continue;
            }
            $cAmount = 0.0;
            if ($method === 'hybrid') {
                $cCash = (float) ($c['verified_cash_amount'] ?? ($c['cash_amount'] ?? 0));
                if ($cCash < 0) {
                    $cCash = 0;
                }
                if ($cCash > $cFare) {
                    $cCash = $cFare;
                }
                $cAmount = $cFare - $cCash;
            } else {
                $cAmount = $cFare;
            }
            $amount += $cAmount;
        }

        return (int) round($amount * 100);
    }

    /**
     * Mark a primary trip's companions as paid alongside the primary (called
     * when the primary settles online). Keeps the travelling party consistent;
     * the online amount already includes companion fares.
     */
    public static function markPartyPaid(PDO $conn, int $primaryTripId, string $source): void
    {
        try {
            $stmt = $conn->prepare("
                UPDATE passenger_trips
                SET payment_status = 'paid', paymongo_history = 'paid (companion)',
                    verified_at = NOW(), verified_by = ?
                WHERE companion_of = ? AND (payment_status IS NULL OR LOWER(payment_status) <> 'paid')
            ");
            $stmt->execute([$source, $primaryTripId]);
        } catch (\PDOException $e) {
            error_log('Companion paid mirror failed: ' . $e->getMessage());
        }
    }

    /**
     * Companion trip rows for a primary trip (with the companion's own fare type).
     */
    public static function companionRows(PDO $conn, int $primaryTripId): array
    {
        try {
            $stmt = $conn->prepare("
                SELECT pt.*, p.full_name AS passenger_name, p.discount_type
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE pt.companion_of = ?
                ORDER BY pt.id ASC
            ");
            $stmt->execute([$primaryTripId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Build the itemized fare breakdown for a travelling party: the primary
     * passenger plus every attached companion, with optional extra charges
     * (for example baggage) each counted exactly once.
     *
     * @param PDO        $conn
     * @param array      $primary       Primary passenger_trips row (id, fare,
     *                                  base_fare, discount_amount, boarding_point,
     *                                  destination, discount_type, passenger_name…).
     * @param array|null $companions    Rows from companionRows(); resolved when null.
     * @param array      $extraCharges  [{label, amount, owner?}] — owner matches a
     *                                  line name or 'primary'.
     * @return array{lines:array,primary_fare:float,companions_fare:float,extra_charges_total:float,party_total:float}
     */
    public static function partyBreakdown(PDO $conn, array $primary, ?array $companions = null, array $extraCharges = []): array
    {
        $primaryBase = (float) ($primary['base_fare'] ?? 0);
        $primaryFare = (float) ($primary['fare'] ?? 0);
        if ($primaryBase <= 0) {
            $primaryBase = $primaryFare;
        }
        $primaryDiscount = (float) ($primary['discount_amount'] ?? max($primaryBase - $primaryFare, 0));

        $primaryType = strtolower(trim((string) ($primary['discount_type'] ?? '')));
        $lines = [[
            'role' => 'primary',
            'passenger_id' => (int) ($primary['passenger_id'] ?? 0),
            'name' => (string) ($primary['passenger_name'] ?? ($primary['full_name'] ?? 'You')),
            'type' => $primaryType !== '' ? $primaryType : 'regular',
            'type_label' => self::labelForDiscountType($primaryType),
            'boarding_point' => (string) ($primary['boarding_point'] ?? ''),
            'destination' => (string) ($primary['destination'] ?? ($primary['selected_destination'] ?? '')),
            'base_fare' => round($primaryBase, 2),
            'discount_amount' => round($primaryDiscount, 2),
            'final_fare' => round($primaryFare, 2),
            'extra_charges' => [],
        ]];

        if ($companions === null) {
            $companions = self::companionRows($conn, (int) ($primary['id'] ?? 0));
        }

        $companionsFare = 0.0;
        foreach ($companions as $c) {
            $base = (float) ($c['base_fare'] ?? 0);
            $fare = (float) ($c['fare'] ?? 0);
            if ($base <= 0) {
                $base = $fare;
            }
            $discount = (float) ($c['discount_amount'] ?? max($base - $fare, 0));
            $typeLabel = (string) ($c['discount_type'] ?? 'Regular');
            $companionsFare += $fare;

            $lines[] = [
                'role' => 'companion',
                'passenger_id' => (int) ($c['passenger_id'] ?? 0),
                'name' => (string) ($c['passenger_name'] ?? ''),
                'type' => self::fareKeyForDiscount($typeLabel),
                'type_label' => self::labelForDiscountType($typeLabel),
                'boarding_point' => (string) ($c['boarding_point'] ?? ''),
                'destination' => (string) ($c['destination'] ?? ''),
                'base_fare' => round($base, 2),
                'discount_amount' => round($discount, 2),
                'final_fare' => round($fare, 2),
                'extra_charges' => [],
            ];
        }

        $extraTotal = 0.0;
        foreach ($extraCharges as $charge) {
            $label = trim((string) ($charge['label'] ?? 'Extra charge'));
            $amount = (float) ($charge['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $extraTotal += $amount;

            $owner = strtolower(trim((string) ($charge['owner'] ?? '')));
            $attached = false;
            foreach ($lines as $i => $line) {
                if ($owner !== '' && strtolower(trim((string) $line['name'])) === $owner) {
                    $lines[$i]['extra_charges'][] = ['label' => $label, 'amount' => round($amount, 2)];
                    $attached = true;
                    break;
                }
            }
            if (!$attached) {
                $lines[0]['extra_charges'][] = ['label' => $label, 'amount' => round($amount, 2)];
            }
        }

        $partyTotal = round($primaryFare + $companionsFare + $extraTotal, 2);

        return [
            'lines' => $lines,
            'primary_fare' => round($primaryFare, 2),
            'companions_fare' => round($companionsFare, 2),
            'extra_charges_total' => round($extraTotal, 2),
            'party_total' => $partyTotal,
        ];
    }

    /**
     * Map companion trip rows to the API payload consumed by the regular-flow
     * companion surfaces. Preserves the historical `companion_type` label and
     * adds the fare detail needed for the breakdown.
     */
    public static function companionsPayload(array $rows): array
    {
        return array_map(static function (array $row): array {
            $base = (float) ($row['base_fare'] ?? 0);
            $fare = isset($row['fare']) && $row['fare'] !== null ? (float) $row['fare'] : null;
            if ($base <= 0 && $fare !== null) {
                $base = $fare;
            }
            $discount = isset($row['discount_amount']) && $row['discount_amount'] !== null
                ? (float) $row['discount_amount']
                : ($fare !== null ? max($base - $fare, 0) : 0.0);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'passenger_id' => (int) ($row['passenger_id'] ?? 0),
                'name' => (string) ($row['passenger_name'] ?? ''),
                'companion_type' => (string) ($row['passenger_type'] ?? ''),
                'type_label' => self::labelForDiscountType((string) ($row['discount_type'] ?? 'Regular')),
                'base_fare' => round($base, 2),
                'discount_amount' => round($discount, 2),
                'fare' => $fare,
            ];
        }, array_values($rows));
    }

    /**
     * Price an extra (for example baggage) using the existing baggage pricing
     * rules. Returns null when the route stops cannot be resolved.
     */
    public static function priceBaggageExtra(PDO $conn, string $routeName, string $boardingPoint, string $destination, int $spaces = 1, string $fareType = 'regular'): ?float
    {
        try {
            require_once dirname(__DIR__, 3) . '/admin/services/Passenger/BaggagePricingService.php';
            $pricing = new \Admin\Services\Passenger\BaggagePricingService();
            return $pricing->fareForRoute($conn, $routeName, $boardingPoint, $destination, $spaces, $fareType);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    private static function labelForDiscountType(string $discountType): string
    {
        $type = strtolower(trim($discountType));
        if ($type === 'student' || $type === 'child') {
            return 'Student';
        }
        if ($type === 'senior' || $type === 'senior citizen') {
            return 'Senior Citizen';
        }
        if ($type === 'pwd') { return 'PWD'; }
        return 'Regular';
    }

    private static function fareKeyForDiscount(string $discountType): string
    {
        $type = strtolower(trim((string) $discountType));
        if ($type === 'student' || $type === 'child') {
            return 'student';
        }
        if ($type === 'senior' || $type === 'senior citizen') {
            return 'senior';
        }
        if ($type === 'pwd') { return 'pwd'; }
        return 'regular';
    }

    private static function routeStopForFareId(PDO $conn, int $farePricingId): ?int
    {
        if ($farePricingId <= 0) {
            return null;
        }
        try {
            $stmt = $conn->prepare("SELECT id FROM route_stops WHERE fare_pricing_id = ? LIMIT 1");
            $stmt->execute([$farePricingId]);
            $id = $stmt->fetchColumn();
            return $id !== false && $id !== null ? (int) $id : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    private static function profileAlreadyOnTrip(PDO $conn, int $primaryTripId, int $profileId): bool
    {
        if ($primaryTripId <= 0 || $profileId <= 0) {
            return false;
        }
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM passenger_trips WHERE companion_of = ? AND companion_profile_id = ?");
            $stmt->execute([$primaryTripId, $profileId]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    private static function createPassengerRow(PDO $conn, string $name, string $type, string $declaredId, array $primary, string $registrationType): ?int
    {
        $discountType = self::discountLabelForType($type, $declaredId);
        $declaredStatus = ($type === 'senior' && $declaredId !== '') ? 'self_declared' : null;

        try {
            $stmt = $conn->prepare("
                INSERT INTO passengers
                (
                    full_name,
                    discount_type,
                    type,
                    space_occupied,
                    registration_type,
                    declared_id_number,
                    declared_id_status,
                    latitude,
                    longitude
                )
                VALUES (?, ?, 'passenger', 1, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $discountType,
                $registrationType,
                $declaredId !== '' ? $declaredId : null,
                $declaredStatus,
                !empty($primary['latitude']) ? $primary['latitude'] : null,
                !empty($primary['longitude']) ? $primary['longitude'] : null,
            ]);

            $passengerId = (int) $conn->lastInsertId();

            try {
                PassengerCodeService::assignIfMissing($conn, $passengerId, $discountType, $registrationType);
            } catch (\Throwable $e) {
                error_log('Companion passenger code assignment failed: ' . $e->getMessage());
            }

            return $passengerId;
        } catch (\PDOException $e) {
            error_log('Companion passenger row failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function computeFare(
        PDO $conn,
        array $primary,
        string $boarding,
        string $destination,
        string $fareType,
        ?int $boardingStopId,
        ?int $destinationStopId,
        ?int $destinationFareId
    ): array {
        try {
            return calculateFare(
                (int) ($primary['trip_no'] ?? 0),
                $boarding,
                $destination,
                $fareType,
                null,
                $destinationFareId !== null ? $destinationFareId : null,
                $boardingStopId !== null ? $boardingStopId : null,
                $destinationStopId !== null ? $destinationStopId : null
            );
        } catch (\Throwable $e) {
            return ['fare' => 0];
        }
    }

    private static function companionToken(string $tokenBase, int $index, string $name): string
    {
        $safeBase = preg_replace('/[^A-Za-z0-9]/', '-', substr($tokenBase, 0, 44));
        if ($safeBase === '') {
            $safeBase = 'companion';
        }
        return strtolower(substr($safeBase, 0, 44)) . '-' . sprintf('%02d', $index) . '-' . substr(hash('md5', $name), 0, 6);
    }
}
