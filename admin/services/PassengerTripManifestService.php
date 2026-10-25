<?php
declare(strict_types=1);

namespace Admin\Services;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/FareCalculator.php';
require_once __DIR__ . '/PassengerManifestFields.php';

use App\Core\Database;
use PDO;

/**
 * PassengerTripManifestService
 *
 * Centralizes the offline manifest business rules so that the paper import
 * uses the SAME fare/route/passenger-type logic as the existing trip flow
 * (Design D4). It delegates stop resolution and fare-rule computation to the
 * existing fare-engine rather than inventing new rules for paper imports.
 *
 * The validation methods mirror the logic in the existing trip flow
 * (mobile/driver/save_manual_passenger.php + fare-engine): boarding at the
 * route terminal charges the destination fare; boarding at a later stop
 * charges destination minus boarding fare, never below the ₱15 minimum; the
 * passenger type selects the fare column (regular/student/special).
 */
class PassengerTripManifestService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::get();
    }

    /**
     * Resolve the ordered list of fare stops for a route from fare_pricing.
     * If $routeName is provided, restrict to that route; otherwise return all.
     *
     * @return array<int,array<string,mixed>>
     */
    public function resolveRouteStops(?string $routeName = null): array
    {
        $sql = "SELECT id, route_name, destination, display_order, regular_fare, student_fare, special_fare
                FROM fare_pricing";
        $params = [];
        if ($routeName) {
            $sql .= " WHERE LOWER(TRIM(route_name)) = ?";
            $params[] = strtolower(trim($routeName));
        }
        $sql .= " ORDER BY display_order ASC, destination ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch available routes (route names) for pre-fill.
     *
     * @return array<int,string>
     */
    public function listRoutes(): array
    {
        try {
            $rows = $this->db->query("SELECT DISTINCT route_name FROM fare_pricing ORDER BY route_name ASC")
                ->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_filter(array_map('trim', $rows ?: [])));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Match a handwritten stop text against fare stops, returning the closest
     * stop row or null. Reuses the fare-engine's string normalization and
     * fuzzy matching approach.
     *
     * @param array<int,array<string,mixed>> $stops
     * @return array<string,mixed>|null
     */
    public function matchStop(array $stops, ?string $text): ?array
    {
        $value = trim((string)$text);
        if ($value === '') {
            return null;
        }
        $original = strtolower($value);

        $normalize = function ($s) {
            $v = strtolower((string)$s);
            $v = str_replace([
                ', nueva ecija', ', cabanatuan city', ', cabanatuan', ' city',
            ], '', $v);
            $v = preg_replace('/[^a-z0-9 ]/', ' ', $v);
            return trim((string)preg_replace('/\s+/', ' ', $v));
        };

        $needle = $normalize($value);

        // Exact match
        foreach ($stops as $stop) {
            if ($normalize($stop['destination']) === $needle) {
                return $stop;
            }
        }
        // Partial match (either direction)
        foreach ($stops as $stop) {
            $stopNorm = $normalize($stop['destination']);
            if ($stopNorm !== '' && (strpos($needle, $stopNorm) !== false || strpos($stopNorm, $needle) !== false)) {
                return $stop;
            }
        }
        // Contains-match on raw text
        foreach ($stops as $stop) {
            $stopLower = strtolower((string)$stop['destination']);
            if ($stopLower !== '' && strpos($original, $stopLower) !== false) {
                return $stop;
            }
        }
        return null;
    }

    /**
     * Compute the expected fare for a boarding/destination stop pair given a
     * passenger type, per the existing fare rule.
     *
     * @param array<string,mixed>|null $boardingStop
     * @param array<string,mixed>|null $destinationStop
     */
    public function computeExpectedFare(
        ?array $boardingStop,
        ?array $destinationStop,
        ?string $passengerType = 'Regular'
    ): ?float {
        if (!$boardingStop || !$destinationStop) {
            return null;
        }
        if ((int)$boardingStop['id'] === (int)$destinationStop['id']) {
            return PassengerManifestFields::MINIMUM_FARE;
        }

        $column = $this->fareColumn($passengerType);
        $boardingFare = (float)($boardingStop[$column] ?? 0);
        $destinationFare = (float)($destinationStop[$column] ?? 0);

        if ((int)$boardingStop['display_order'] === 1) {
            $fare = $destinationFare;
        } else {
            $fare = $destinationFare - $boardingFare;
            if ($fare < PassengerManifestFields::MINIMUM_FARE) {
                $fare = PassengerManifestFields::MINIMUM_FARE;
            }
        }
        return round($fare, 2);
    }

    /**
     * Which fare_pricing column applies for the passenger type.
     */
    public function fareColumn(?string $passengerType): string
    {
        $t = strtolower((string)($passengerType ?: 'Regular'));
        if (in_array($t, ['student', 'senior', 'pwd'], true)) {
            return 'student_fare';
        }
        if (in_array($t, ['nurse', 'teacher', 'special'], true)) {
            return 'special_fare';
        }
        return 'regular_fare';
    }

    /**
     * Validate the trip-level header fields.
     *
     * @param array<string,mixed> $trip
     * @return array{status:string, reasons:array<int,string>}
     */
    public function validateTrip(array $trip): array
    {
        $reasons = [];
        $status = PassengerManifestFields::STATUS_VALID;

        $tripDate = trim((string)($trip['trip_date'] ?? ''));
        if ($tripDate === '') {
            $reasons[] = 'Trip date is required';
        } elseif ($this->normalizeDate($tripDate) === null) {
            $reasons[] = 'Trip date is invalid';
        }

        $route = trim((string)($trip['route'] ?? ''));
        if ($route === '') {
            $reasons[] = 'Route is required';
        }

        $driver = trim((string)($trip['driver'] ?? ''));
        if ($driver === '') {
            $reasons[] = 'Driver is required';
        }

        $bus = trim((string)($trip['bus_number'] ?? ''));
        if ($bus === '') {
            $reasons[] = 'Bus number is required';
        }

        $payment = trim((string)($trip['payment_method'] ?? ''));
        if ($payment !== '' && PassengerManifestFields::normalizePayment($payment) === null) {
            $reasons[] = "Payment method '{$payment}' is not one of cash/gcash/hybrid";
        }

        if (!empty($reasons)) {
            $status = PassengerManifestFields::STATUS_REVIEW;
        }
        return ['status' => $status, 'reasons' => $reasons];
    }

    /**
     * Validate a single passenger row against the existing business rules.
     *
     * Returns status valid/review/invalid plus a status label and reasons.
     *
     * @param array<string,mixed> $p  canonical passenger row
     * @param array<string,mixed> $trip
     * @return array<string,mixed>
     */
    public function validatePassenger(array $p, array $trip): array
    {
        $reasons = [];
        $status = PassengerManifestFields::STATUS_VALID;

        // Required fields
        $name = trim((string)($p['passenger_name'] ?? ''));
        if ($name === '') {
            $reasons[] = 'Passenger name is required';
            $status = PassengerManifestFields::STATUS_INVALID;
        } elseif ($p['name_generated'] ?? false) {
            $reasons[] = 'Passenger name could not be confidently detected';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }

        $passengerType = PassengerManifestFields::normalizePassengerType((string)($p['passenger_type'] ?? ''));
        if ($passengerType === null) {
            $reasons[] = 'Passenger type is unrecognized';
            $status = PassengerManifestFields::STATUS_INVALID;
        } else {
            $p['passenger_type'] = $passengerType;
            // Student ID required when applicable
            if ($passengerType === 'Student' && trim((string)($p['student_id'] ?? '')) === '') {
                $reasons[] = 'Student ID is required for a Student passenger';
                $status = PassengerManifestFields::STATUS_REVIEW;
            }
        }

        $boarding = trim((string)($p['boarding_point'] ?? ''));
        $destination = trim((string)($p['destination'] ?? ''));
        if ($boarding === '') {
            $reasons[] = 'Boarding point is required';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }
        if ($destination === '') {
            $reasons[] = 'Destination is required';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }
        if (($p['stop_confident'] ?? true) === false) {
            $reasons[] = 'Boarding/Destination could not be confidently read — please verify';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }

        // Route stop resolution and fare check (reuse fare rules)
        $route = trim((string)($trip['route'] ?? ''));
        $stops = $this->resolveRouteStops($route ?: null);
        $boardingStop = $this->matchStop($stops, $boarding);
        $destStop = $this->matchStop($stops, $destination);

        if ($boarding !== '' && !$boardingStop) {
            $reasons[] = 'Boarding point is not recognized on the route';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }
        if ($destination !== '' && !$destStop) {
            $reasons[] = 'Destination needs review (not matched to the route)';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }

        if ($boardingStop && $destStop) {
            $boardOrder = (int)$boardingStop['display_order'];
            $destOrder = (int)$destStop['display_order'];
            if ($destOrder <= $boardOrder) {
                $reasons[] = 'Destination must be ahead of boarding point on the route';
                $status = PassengerManifestFields::STATUS_REVIEW;
            } else {
                $expected = $this->computeExpectedFare($boardingStop, $destStop, $passengerType ?? 'Regular');
                $extractedFare = isset($p['fare']) && $p['fare'] !== '' && is_numeric($p['fare'])
                    ? (float)$p['fare']
                    : null;
                if ($expected !== null && $extractedFare !== null) {
                    if (abs($expected - $extractedFare) > 0.01) {
                        $reasons[] = sprintf('Fare does not match the configured fare rules (expected ₱%.2f)', $expected);
                        $status = PassengerManifestFields::STATUS_REVIEW;
                    }
                }
            }
        }

        $payment = trim((string)($p['payment_method'] ?? ($trip['payment_method'] ?? '')));
        if ($payment !== '' && PassengerManifestFields::normalizePayment($payment) === null) {
            $reasons[] = 'Payment method must be one of cash/gcash/hybrid';
            $status = PassengerManifestFields::STATUS_REVIEW;
        }

        return [
            'status' => $status,
            'label' => $this->statusLabel($status),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * Look for possible duplicates of a row already in passenger_trip_history.
     * Uses trip date + trip id/number + passenger + bus/driver/destination.
     *
     * @param array<string,mixed> $p
     * @param array<string,mixed> $trip
     * @return bool
     */
    public function isPossibleDuplicate(array $p, array $trip): bool
    {
        $name = trim((string)($p['passenger_name'] ?? ''));
        $tripDate = $this->normalizeDate((string)($trip['trip_date'] ?? '')) ?? (string)($trip['trip_date'] ?? '');
        $destination = trim((string)($p['destination'] ?? ''));
        $driver = trim((string)($trip['driver'] ?? ''));
        $bus = trim((string)($trip['bus_number'] ?? ''));
        $tripId = trim((string)($trip['trip_number'] ?? ''));

        if ($name === '' && $destination === '') {
            return false;
        }

        $cond = [];
        $params = [];
        if ($name !== '') {
            $cond[] = "LOWER(TRIM(passenger_name)) = ?";
            $params[] = strtolower($name);
        }
        if ($tripDate !== '') {
            $cond[] = "COALESCE(DATE(trip_date), DATE(date_booked)) = ?";
            $params[] = $tripDate;
        }
        if ($destination !== '') {
            $cond[] = "LOWER(TRIM(destination)) = ?";
            $params[] = strtolower($destination);
        }
        if ($driver !== '') {
            $cond[] = "LOWER(TRIM(drivers_full_name)) = ?";
            $params[] = strtolower($driver);
        }
        if ($bus !== '') {
            $cond[] = "LOWER(TRIM(bus_number)) = ?";
            $params[] = strtolower($bus);
        }
        if ($tripId !== '') {
            $cond[] = "LOWER(TRIM(trip_id)) = ?";
            $params[] = strtolower($tripId);
        }

        if (empty($cond)) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM passenger_trip_history WHERE " . implode(' AND ', $cond) . " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Resolve a driver_id from the drivers table by fuzzy name match.
     */
    public function resolveDriverId(?string $driverName): ?string
    {
        $name = trim((string)$driverName);
        if ($name === '') {
            return null;
        }
        try {
            $stmt = $this->db->prepare("SELECT driver_id FROM drivers WHERE LOWER(TRIM(full_name)) = ? LIMIT 1");
            $stmt->execute([strtolower($name)]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (string)$id;
            }
            $stmt = $this->db->prepare("SELECT driver_id FROM drivers WHERE LOWER(TRIM(full_name)) LIKE ? LIMIT 1");
            $stmt->execute(['%' . strtolower($name) . '%']);
            $id = $stmt->fetchColumn();
            return $id ? (string)$id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Discover whether a bus_number exists in the buses table.
     */
    public function busExists(?string $busNumber): bool
    {
        $bus = trim((string)$busNumber);
        if ($bus === '') {
            return false;
        }
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM buses WHERE LOWER(TRIM(bus_id)) = ? OR LOWER(TRIM(plate_number)) = ? LIMIT 1");
            $stmt->execute([strtolower($bus), strtolower($bus)]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Normalize a user-provided date into Y-m-d or null.
     */
    public function normalizeDate(?string $val): ?string
    {
        if ($val === null || $val === '' || $val === 'null') {
            return null;
        }
        $v = trim($val);
        $fmts = ['Y-m-d', 'n/j/Y', 'm/d/Y', 'F j, Y', 'F d, Y', 'd/m/Y'];
        foreach ($fmts as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d) {
                return $d->format('Y-m-d');
            }
        }
        $ts = strtotime($v);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    /**
     * Generate the next trip_id following the TRIP-<n> convention used by the
     * existing trip history (mirrors the trip-history generator's numbering).
     */
    public function generateTripId(): string
    {
        try {
            $max = $this->db->query(
                "SELECT MAX(CAST(SUBSTRING_INDEX(trip_id, '-', -1) AS UNSIGNED)) FROM passenger_trip_history WHERE trip_id LIKE 'TRIP-%'"
            )->fetchColumn();
            $next = max(3000, ((int)$max) + 1);
            return 'TRIP-' . $next;
        } catch (\Throwable $e) {
            return 'TRIP-' . time();
        }
    }

    /**
     * Human-readable status label.
     */
    public function statusLabel(string $status): string
    {
        return match ($status) {
            PassengerManifestFields::STATUS_VALID => 'Valid',
            PassengerManifestFields::STATUS_REVIEW => 'Needs Review',
            PassengerManifestFields::STATUS_INVALID => 'Invalid',
            default => $status,
        };
    }
}
