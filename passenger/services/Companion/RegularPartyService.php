<?php declare(strict_types=1);

require_once __DIR__ . '/CompanionService.php';

class RegularPartyException extends RuntimeException
{
    public string $errorCode;
    public int $httpStatus;

    public function __construct(string $code, string $message, int $status = 409)
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->httpStatus = $status;
    }
}

/** Regular journey selection; saved profiles are not automatically selected. */
class RegularPartyService
{
    public const DRAFT_KEY = 'regular_party';

    public static function ensureState(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            && $db->query("SHOW TABLES LIKE 'passenger_trip_state'")->fetchColumn()) {
            return; // Avoid MySQL DDL's implicit commit when restoring inside boarding.
        }
        $db->exec("CREATE TABLE IF NOT EXISTS passenger_trip_state (
            passenger_id INT NOT NULL, state_key VARCHAR(64) NOT NULL,
            state_value TEXT, version BIGINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (passenger_id, state_key)
        )");
    }

    private static function authorize(PDO $db, int $owner): void
    {
        if ($owner <= 0) {
            throw new RegularPartyException('AUTH_REQUIRED', 'Not logged in.', 401);
        }
        $stmt = $db->prepare('SELECT registration_type FROM passengers WHERE id = ?');
        $stmt->execute([$owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || strtolower((string) ($row['registration_type'] ?? '')) === 'one_time') {
            throw new RegularPartyException('REGULAR_ONLY', 'This party selection belongs to the regular passenger flow.', 403);
        }
    }

    private static function key(?int $tripId): string
    {
        return $tripId ? 'regular_party_' . $tripId : self::DRAFT_KEY;
    }

    public static function readDraft(PDO $db, int $owner): array
    {
        self::authorize($db, $owner);
        self::ensureState($db);
        return self::state($db, $owner, self::DRAFT_KEY);
    }

    private static function state(PDO $db, int $owner, string $key, bool $lock = false): array
    {
        $sql = 'SELECT state_value, version FROM passenger_trip_state WHERE passenger_id = ? AND state_key = ?';
        if ($lock && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$owner, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['revision' => 0, 'companions' => []];
        }
        $selection = json_decode((string) $row['state_value'], true);
        if (!is_array($selection) || !isset($selection['companions']) || !is_array($selection['companions'])) {
            throw new RegularPartyException('INVALID_PARTY_STATE', 'Saved companions could not be restored.', 500);
        }
        return ['revision' => (int) $row['version'], 'companions' => $selection['companions']];
    }

    private static function seedState(PDO $db, int $owner, string $key, array $initial = []): void
    {
        $verb = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $stmt = $db->prepare("$verb INTO passenger_trip_state (passenger_id, state_key, state_value, version) VALUES (?, ?, ?, 0)");
        $stmt->execute([$owner, $key, json_encode(['companions' => $initial], JSON_THROW_ON_ERROR)]);
    }

    private static function normalize(PDO $db, int $owner, array $raw): array
    {
        if (!array_is_list($raw) || count($raw) > CompanionService::MAX_PER_TRIP) {
            throw new RegularPartyException('INVALID_COMPANIONS', 'Choose up to five companions.', 422);
        }
        $clean = [];
        $names = [];
        $ids = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                throw new RegularPartyException('INVALID_COMPANIONS', 'Invalid companion.', 422);
            }
            $id = (int) ($item['profile_id'] ?? 0);
            if ($id) {
                $stmt = $db->prepare('SELECT * FROM passenger_companions WHERE id = ? AND owner_passenger_id = ?');
                $stmt->execute([$id, $owner]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$profile) {
                    throw new RegularPartyException('INVALID_PROFILE', 'Companion not found for this passenger.', 403);
                }
                $name = (string) $profile['full_name'];
                $type = strtolower(trim((string) ($item['companion_type'] ?? $profile['companion_type'])));
                $declared = (string) ($item['declared_id_number'] ?? $profile['declared_id_number'] ?? '');
            } else {
                $name = trim(preg_replace('/\s+/u', ' ', (string) ($item['full_name'] ?? '')) ?? '');
                $type = strtolower(trim((string) ($item['companion_type'] ?? '')));
                $declared = trim((string) ($item['declared_id_number'] ?? ''));
            }
            $error = CompanionService::validateCompanion(['full_name' => $name, 'companion_type' => $type, 'declared_id_number' => $declared]);
            if ($error !== null) {
                throw new RegularPartyException('INVALID_COMPANIONS', $error, 422);
            }
            $nameKey = mb_strtolower(trim($name));
            if (isset($names[$nameKey]) || ($id && isset($ids[$id]))) {
                throw new RegularPartyException('DUPLICATE_COMPANION', 'Each companion can be selected only once.', 422);
            }
            $names[$nameKey] = true;
            if (!$id) {
                $stmt = $db->prepare('SELECT id FROM passenger_companions WHERE owner_passenger_id = ? AND LOWER(full_name) = LOWER(?)');
                $stmt->execute([$owner, $name]);
                $id = (int) $stmt->fetchColumn();
                if (!$id) {
                    $stmt = $db->prepare('INSERT INTO passenger_companions (owner_passenger_id, full_name, companion_type, declared_id_number) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$owner, $name, $type, $type === 'senior' && $declared !== '' ? $declared : null]);
                    $id = (int) $db->lastInsertId();
                }
            }
            if (isset($ids[$id])) {
                throw new RegularPartyException('DUPLICATE_COMPANION', 'Each companion can be selected only once.', 422);
            }
            $ids[$id] = true;
            $clean[] = ['profile_id' => $id, 'full_name' => $name, 'companion_type' => $type, 'declared_id_number' => $type === 'senior' ? $declared : ''];
        }
        return $clean;
    }

    public static function saveDraft(PDO $db, int $owner, array $raw, int $revision): array
    {
        self::authorize($db, $owner);
        self::ensureState($db);
        $db->beginTransaction();
        try {
            if (self::lockBoardingOwner($db, $owner)) {
                throw new RegularPartyException('ACTIVE_TRIP', 'Boarding has completed. Restore the trip party before changing companions.');
            }
            $state = self::writeSelection($db, $owner, self::DRAFT_KEY, $raw, $revision);
            $db->commit();
            return $state;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function writeSelection(PDO $db, int $owner, string $key, array $raw, int $revision): array
    {
        self::seedState($db, $owner, $key);
        $current = self::state($db, $owner, $key, true);
        if ($current['revision'] !== $revision) {
            throw new RegularPartyException('STALE_PARTY', 'Your companions changed. Refresh the accepted party before trying again.');
        }
        $clean = self::normalize($db, $owner, $raw);
        if ($clean === $current['companions']) {
            return $current;
        }
        $stmt = $db->prepare('UPDATE passenger_trip_state SET state_value = ?, version = version + 1, updated_at = CURRENT_TIMESTAMP WHERE passenger_id = ? AND state_key = ? AND version = ?');
        $stmt->execute([json_encode(['companions' => $clean], JSON_THROW_ON_ERROR), $owner, $key, $revision]);
        if ($stmt->rowCount() !== 1) {
            throw new RegularPartyException('STALE_PARTY', 'Your companions changed. Please refresh.');
        }
        return ['revision' => $revision + 1, 'companions' => $clean];
    }

    public static function ownedTrip(PDO $db, int $owner, int $tripId, bool $lock = false): array
    {
        self::authorize($db, $owner);
        $sql = "SELECT pt.*, p.full_name AS passenger_name, p.discount_type,
                p.registration_type, bt.status AS bus_trip_status
            FROM passenger_trips pt JOIN passengers p ON p.id = pt.passenger_id
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            WHERE pt.id = ? AND pt.passenger_id = ? AND pt.companion_of IS NULL";
        if ($lock && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $sql .= ' FOR UPDATE'; }
        $stmt = $db->prepare($sql);
        $stmt->execute([$tripId, $owner]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$trip || strtolower((string) ($trip['identification_source'] ?? '')) === 'one_time') {
            throw new RegularPartyException('NOT_FOUND', 'Regular trip not found.', 404);
        }
        return $trip;
    }

    public static function travellerRows(PDO $db, int $tripId): array
    {
        $stmt = $db->prepare("SELECT pt.*, p.full_name AS passenger_name, p.discount_type
            FROM passenger_trips pt JOIN passengers p ON p.id = pt.passenger_id
            WHERE pt.companion_of = ? AND LOWER(COALESCE(p.type, 'passenger')) <> 'other'
            ORDER BY pt.id");
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function declaredType(array $row): string
    {
        $type = strtolower(trim((string) ($row['passenger_type'] ?? '')));
        if (in_array($type, ['child', 'student'], true)) { return 'child'; }
        if (in_array($type, ['senior', 'senior citizen'], true)) { return 'senior'; }
        if ($type === 'pwd') { return 'pwd'; }
        return 'other';
    }

    public static function read(PDO $db, int $owner, ?int $tripId = null, array $context = []): array
    {
        self::authorize($db, $owner);
        self::ensureState($db);
        if (!$tripId) { $tripId = self::activeTripId($db, $owner); }
        if (!$tripId) {
            $state = self::readDraft($db, $owner);
            return ['success' => true, 'mode' => 'draft', 'trip_id' => null] + $state + self::quote($db, $owner, $state['companions'], $context);
        }
        $trip = self::ownedTrip($db, $owner, $tripId);
        $rows = self::travellerRows($db, $tripId);
        $companions = array_map(static fn(array $row): array => [
            'profile_id' => (int) $row['companion_profile_id'],
            'full_name' => (string) $row['passenger_name'],
            'companion_type' => self::declaredType($row),
            'declared_id_number' => (string) ($row['declared_id_number'] ?? ''),
        ], $rows);
        $state = self::state($db, $owner, self::key($tripId));
        $destinationSelected = !empty(self::routeContext($trip)['destination_id']) || self::routeContext($trip)['destination'] !== '';
        $editable = true;
        try { self::assertEditable($trip, $rows); } catch (RegularPartyException $e) { $editable = false; }
        return ['success' => true, 'mode' => 'trip', 'trip_id' => $tripId,
            'revision' => $state['revision'], 'companions' => $companions, 'editable' => $editable && !$destinationSelected,
            'companions_locked' => $destinationSelected,
            'payment_method' => strtolower((string) ($trip['payment_method'] ?? 'cash')),
            'payment_status' => strtolower((string) ($trip['payment_status'] ?? 'pending')),
            'route' => self::routeContext($trip)] + self::storedQuote($db, $trip, $rows);
    }

    private static function routeContext(array $trip): array
    {
        $destination = trim((string) ($trip['destination'] ?? ''));
        if ($destination === '') { $destination = trim((string) ($trip['selected_destination'] ?? '')); }
        return ['trip_no' => (int) $trip['trip_no'], 'boarding_point' => (string) $trip['boarding_point'],
            'boarding_stop_id' => $trip['boarding_stop_id'] ?? null, 'destination' => $destination,
            'destination_id' => !empty($trip['destination_id']) ? (int) $trip['destination_id']
                : (!empty($trip['selected_destination_id']) ? (int) $trip['selected_destination_id'] : null)];
    }

    public static function assertEditable(array $trip, array $rows): void
    {
        if (!empty($trip['dropoff_time']) || in_array(strtolower((string) ($trip['trip_status'] ?? '')), ['completed', 'cancelled'], true)
            || in_array(strtolower((string) ($trip['bus_trip_status'] ?? '')), ['completed', 'cancelled'], true)
            || strtolower((string) ($trip['payment_status'] ?? '')) === 'paid'
            || (float) ($trip['verified_cash_amount'] ?? 0) > 0) {
            throw new RegularPartyException('PARTY_SETTLED', 'Completed or settled trips cannot change fares.');
        }
        if (!empty($trip['paymongo_intent_id']) && !empty($trip['paymongo_qr_expires_at'])
            && strtotime((string) $trip['paymongo_qr_expires_at']) > time()) {
            throw new RegularPartyException('CHECKOUT_ACTIVE', 'The payment QR is still active. The party cannot change until that checkout is resolved.');
        }
        foreach ($rows as $row) {
            if (strtolower((string) ($row['payment_status'] ?? '')) === 'paid' || (float) ($row['verified_cash_amount'] ?? $row['cash_amount'] ?? 0) > 0) {
                throw new RegularPartyException('PARTY_SETTLED', 'A companion has already settled part of this trip.');
            }
        }
    }

    public static function storedQuote(PDO $db, array $primary, array $rows): array
    {
        // Checkout/reconciliation callers may load a smaller primary projection.
        // Read optional server-owned charges rather than trust a request amount.
        if (!array_key_exists('carried_balance', $primary) && !empty($primary['id']) && !empty($primary['passenger_id'])) {
            $stmt = $db->prepare('SELECT * FROM passenger_trips WHERE id = ? AND passenger_id = ?');
            $stmt->execute([$primary['id'], $primary['passenger_id']]);
            $stored = $stmt->fetch(PDO::FETCH_ASSOC);
            $primary['carried_balance'] = $stored['carried_balance'] ?? 0;
        }
        if (!empty($primary['passenger_type'])) { $primary['discount_type'] = $primary['passenger_type']; }
        foreach (array_merge([$primary], $rows) as $row) {
            if (!isset($row['fare']) || (float) $row['fare'] <= 0) {
                return ['quote_available' => false, 'breakdown' => null, 'party_total' => null, 'online_amount_centavos' => null];
            }
        }
        foreach ($rows as &$row) {
            // Price and label from this trip's declaration, not a mutable profile.
            $row['discount_type'] = CompanionService::discountLabelForType(self::declaredType($row));
        }
        unset($row);
        $breakdown = CompanionService::partyBreakdown($db, $primary, $rows);
        $billingPrimary = $primary;
        $billingRows = $rows;
        foreach (array_merge([$primary], $rows) as $i => $traveller) {
            $carried = round(max(0, (float) ($traveller['carried_balance'] ?? 0)), 2);
            if ($carried <= 0) { continue; }
            $breakdown['lines'][$i]['extra_charges'][] = ['label' => 'Previous trip balance', 'amount' => $carried];
            $breakdown['extra_charges_total'] = round($breakdown['extra_charges_total'] + $carried, 2);
            $breakdown['party_total'] = round($breakdown['party_total'] + $carried, 2);
            if ($i === 0) { $billingPrimary['fare'] = (float) $primary['fare'] + $carried; }
            else { $billingRows[$i - 1]['fare'] = (float) $traveller['fare'] + $carried; }
        }
        $primaryType = strtolower(trim((string) ($primary['discount_type'] ?? 'regular')));
        $breakdown['lines'][0]['type_label'] = match ($primaryType) {
            'regular', 'other' => 'Regular', 'student', 'child' => 'Student',
            'senior', 'senior citizen' => 'Senior Citizen', 'pwd' => 'PWD',
            default => ucwords($primaryType),
        };
        foreach ($rows as $i => $row) {
            $type = self::declaredType($row);
            $breakdown['lines'][$i + 1]['declared_type'] = $type;
            $breakdown['lines'][$i + 1]['type_label'] = CompanionService::companionTypeLabel($type);
        }
        $online = strtolower((string) ($primary['payment_status'] ?? '')) === 'paid'
            || !in_array(strtolower((string) ($primary['payment_method'] ?? '')), ['gcash', 'hybrid'], true)
            ? 0 : CompanionService::partyOnlineAmountCentavos($db, $billingPrimary, $billingRows);
        return ['quote_available' => true, 'breakdown' => $breakdown,
            'party_total' => $breakdown['party_total'], 'online_amount_centavos' => $online];
    }

    public static function quote(PDO $db, int $owner, array $companions, array $context, ?string $primaryFareType = null): array
    {
        self::authorize($db, $owner);
        $stmt = $db->prepare('SELECT full_name, discount_type FROM passengers WHERE id = ?');
        $stmt->execute([$owner]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        $boarding = trim((string) ($context['boarding_point'] ?? ''));
        $destination = trim((string) ($context['destination'] ?? ''));
        $tripNo = (int) ($context['trip_no'] ?? 0);
        if ($boarding === '' || $destination === '' || $tripNo <= 0) {
            return ['quote_available' => false, 'breakdown' => null, 'party_total' => null, 'online_amount_centavos' => null];
        }
        $baseResult = calculateFare($tripNo, $boarding, $destination, 'regular',
            $context['boarding_fare_id'] ?? null, $context['destination_id'] ?? null,
            $context['boarding_stop_id'] ?? null, null);
        $base = (float) ($baseResult['fare'] ?? 0);
        if ($base <= 0) {
            return ['quote_available' => false, 'breakdown' => null, 'party_total' => null, 'online_amount_centavos' => null];
        }
        $types = ['regular' => $base];
        $price = static function (string $type) use (&$types, $tripNo, $boarding, $destination, $context): float {
            if (!isset($types[$type])) {
                $result = calculateFare($tripNo, $boarding, $destination, $type,
                    $context['boarding_fare_id'] ?? null, $context['destination_id'] ?? null,
                    $context['boarding_stop_id'] ?? null, null);
                $types[$type] = (float) ($result['fare'] ?? 0);
            }
            return $types[$type];
        };
        $primaryType = strtolower($primaryFareType ?? (string) ($person['discount_type'] ?? 'regular'));
        $primaryFare = $price($primaryType);
        $primary = ['passenger_id' => $owner, 'passenger_name' => $person['full_name'],
            'discount_type' => $primaryType, 'boarding_point' => $boarding, 'destination' => $destination,
            'fare' => $primaryFare, 'base_fare' => $base, 'discount_amount' => max(0, $base - $primaryFare),
            'payment_method' => 'gcash'];
        $rows = [];
        foreach ($companions as $companion) {
            $type = $companion['companion_type'];
            $fare = $price(CompanionService::fareKeyForType($type));
            $rows[] = ['passenger_name' => $companion['full_name'],
                'passenger_type' => CompanionService::companionTypeLabel($type),
                'discount_type' => CompanionService::discountLabelForType($type),
                'boarding_point' => $boarding, 'destination' => $destination,
                'fare' => $fare, 'base_fare' => $base, 'discount_amount' => max(0, $base - $fare)];
        }
        return self::storedQuote($db, $primary, $rows);
    }

    public static function save(PDO $db, int $owner, array $companions, int $revision, ?int $tripId = null, array $context = []): array
    {
        if (!$tripId) {
            self::authorize($db, $owner);
            if (self::activeTripId($db, $owner)) {
                throw new RegularPartyException('ACTIVE_TRIP', 'Boarding has completed. Restore the trip party before changing companions.');
            }
            self::saveDraft($db, $owner, $companions, $revision);
            return self::read($db, $owner, null, $context);
        }
        self::authorize($db, $owner);
        self::ensureState($db);
        $db->beginTransaction();
        try {
            $trip = self::ownedTrip($db, $owner, $tripId, true);
            $rows = self::travellerRows($db, $tripId);
            $route = self::routeContext($trip);
            if ($route['destination_id'] || $route['destination'] !== '') {
                throw new RegularPartyException('COMPANIONS_LOCKED', 'Your destination is selected. Companions can no longer be added, removed, or edited.');
            }
            self::assertEditable($trip, $rows);
            $initial = array_map(static fn(array $row): array => [
                'profile_id' => (int) $row['companion_profile_id'], 'full_name' => $row['passenger_name'],
                'companion_type' => self::declaredType($row), 'declared_id_number' => (string) ($row['declared_id_number'] ?? ''),
            ], $rows);
            self::seedState($db, $owner, self::key($tripId), $initial);
            $state = self::writeSelection($db, $owner, self::key($tripId), $companions, $revision);
            self::reconcileRows($db, $owner, $trip, $state['companions']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
        return self::read($db, $owner, $tripId);
    }

    private static function reconcileRows(PDO $db, int $owner, array $trip, array $selection): void
    {
        $rows = self::travellerRows($db, (int) $trip['id']);
        $selected = array_column($selection, null, 'profile_id');
        foreach ($rows as $row) {
            if (!isset($selected[$row['companion_profile_id']])) {
                $stmt = $db->prepare('DELETE FROM passenger_trips WHERE id = ? AND companion_of = ?');
                $stmt->execute([$row['id'], $trip['id']]);
            }
        }
        $context = self::routeContext($trip);
        $quote = self::quote($db, $owner, $selection, $context, !empty($trip['passenger_type']) ? (string) $trip['passenger_type'] : null);
        if ($context['destination'] !== '' && !$quote['quote_available']) {
            throw new RegularPartyException('FARE_UNAVAILABLE', 'Companion fares could not be calculated.', 422);
        }
        if ($quote['quote_available']) {
            // Older regular rows may hold only selected_destination/estimated_fare.
            // Promote the server-owned estimate so both checkout and companions
            // use the same route and amount; never replace a stored fare/charge
            // with the party total or with a client-submitted amount.
            $primaryLine = $quote['breakdown']['lines'][0];
            $primaryFare = (float) ($trip['fare'] ?? 0);
            if ($primaryFare <= 0) { $primaryFare = (float) ($trip['estimated_fare'] ?? 0); }
            if ($primaryFare <= 0) { $primaryFare = $primaryLine['final_fare']; }
            $base = (float) ($trip['base_fare'] ?? 0);
            if ($base <= 0) { $base = $primaryLine['base_fare']; }
            $stmt = $db->prepare('UPDATE passenger_trips SET destination = ?, destination_id = ?, selected_destination = ?, selected_destination_id = ?, fare = ?, estimated_fare = ?, base_fare = ?, discount_amount = ? WHERE id = ? AND passenger_id = ?');
            $stmt->execute([$context['destination'], $context['destination_id'], $context['destination'], $context['destination_id'],
                $primaryFare, $primaryFare, $base, $trip['discount_amount'] ?? $primaryLine['discount_amount'], $trip['id'], $owner]);
            $trip['destination'] = $context['destination'];
            $trip['destination_id'] = $context['destination_id'];
        }
        foreach ($selection as $i => $companion) {
            $existing = null;
            foreach ($rows as $row) {
                if ((int) $row['companion_profile_id'] === $companion['profile_id']) { $existing = $row; break; }
            }
            if (!$existing) {
                $attached = CompanionService::attachToTrip($db, $trip, $owner, [$companion], ['registration_type' => 'regular', 'token' => 'regular-party-' . $trip['id'] . '-' . $companion['profile_id']]);
                if (count($attached['added']) !== 1) {
                    throw new RegularPartyException('ATTACH_FAILED', 'The companion could not be attached; no party changes were saved.', 500);
                }
                $id = $attached['added'][0]['id'];
            } else {
                $id = $existing['id'];
            }
            $line = $quote['breakdown']['lines'][$i + 1] ?? null;
            $stmt = $db->prepare('UPDATE passenger_trips SET passenger_type = ?, declared_id_number = ?, fare = ?, estimated_fare = ?, base_fare = ?, discount_amount = ?, destination = ?, destination_id = ?, selected_destination = ?, selected_destination_id = ? WHERE id = ? AND companion_of = ?');
            $stmt->execute([CompanionService::companionTypeLabel($companion['companion_type']),
                $companion['declared_id_number'] ?: null, $line['final_fare'] ?? null, $line['final_fare'] ?? null,
                $line['base_fare'] ?? null, $line['discount_amount'] ?? null,
                $context['destination'] ?: null, $context['destination_id'], $context['destination'] ?: null, $context['destination_id'], $id, $trip['id']]);
        }
    }

    private static function activeTripId(PDO $db, int $owner, bool $currentRead = false): ?int
    {
        $sql = "SELECT pt.id FROM passenger_trips pt
            LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
            WHERE pt.passenger_id = ? AND pt.companion_of IS NULL AND pt.dropoff_time IS NULL
            AND LOWER(COALESCE(pt.trip_status, '')) NOT IN ('completed', 'cancelled')
            AND LOWER(COALESCE(bt.status, '')) NOT IN ('completed', 'cancelled')
            ORDER BY pt.id DESC LIMIT 1";
        if ($currentRead && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $sql .= ' FOR UPDATE'; }
        $stmt = $db->prepare($sql);
        $stmt->execute([$owner]);
        $id = (int) $stmt->fetchColumn();
        return $id ?: null;
    }

    public static function lockBoardingOwner(PDO $db, int $owner): ?int
    {
        if (!$db->inTransaction()) { throw new LogicException('Boarding owner lock requires a transaction.'); }
        self::authorize($db, $owner);
        $sql = 'SELECT id FROM passengers WHERE id = ?';
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $sql .= ' FOR UPDATE'; }
        $stmt = $db->prepare($sql); $stmt->execute([$owner]); $stmt->fetchColumn();
        return self::activeTripId($db, $owner, true);
    }

    /** Called after primary insertion and before draft cleanup, in the same transaction. */
    public static function boardDraft(PDO $db, int $owner, int $tripId): array
    {
        if (!$db->inTransaction()) { throw new LogicException('Party boarding requires a transaction.'); }
        $trip = self::ownedTrip($db, $owner, $tripId, true);
        if (self::travellerRows($db, $tripId)) { return self::read($db, $owner, $tripId); }
        if (empty($trip['passenger_type'])) {
            $stmt = $db->prepare('UPDATE passenger_trips SET passenger_type = ? WHERE id = ? AND passenger_id = ?');
            $stmt->execute([$trip['discount_type'], $tripId, $owner]);
        }
        self::seedState($db, $owner, self::DRAFT_KEY);
        $draft = self::state($db, $owner, self::DRAFT_KEY, true);
        $state = self::writeSelection($db, $owner, self::key($tripId), $draft['companions'], 0);
        self::reconcileRows($db, $owner, $trip, $state['companions']);
        $stmt = $db->prepare('DELETE FROM passenger_trip_state WHERE passenger_id = ? AND state_key = ?');
        $stmt->execute([$owner, self::DRAFT_KEY]);
        return self::read($db, $owner, $tripId);
    }

    public static function repriceTrip(PDO $db, int $owner, int $tripId): array
    {
        if (!$db->inTransaction()) { throw new LogicException('Party repricing requires a transaction.'); }
        $trip = self::ownedTrip($db, $owner, $tripId, true);
        $selection = self::read($db, $owner, $tripId)['companions'];
        $stmt = $db->prepare('UPDATE passenger_trips SET destination = ?, destination_id = ?, selected_destination = ?, selected_destination_id = ?, selected_km_marker = ? WHERE id = ? AND companion_of = ?');
        foreach (self::travellerRows($db, $tripId) as $row) {
            $stmt->execute([$trip['destination'], $trip['destination_id'], $trip['destination'], $trip['destination_id'], $trip['selected_km_marker'] ?? null, $row['id'], $tripId]);
        }
        self::reconcileRows($db, $owner, $trip, $selection);
        return self::read($db, $owner, $tripId);
    }

    public static function markTravellersPaid(PDO $db, int $tripId, string $source): void
    {
        $stmt = $db->prepare("UPDATE passenger_trips SET payment_status = 'paid',
            paymongo_history = 'paid (companion)', verified_at = CURRENT_TIMESTAMP, verified_by = ?
            WHERE companion_of = ? AND LOWER(COALESCE(payment_status, 'pending')) <> 'paid'
            AND passenger_id IN (SELECT id FROM passengers WHERE LOWER(COALESCE(type, 'passenger')) <> 'other')");
        $stmt->execute([$source, $tripId]);
    }
}
