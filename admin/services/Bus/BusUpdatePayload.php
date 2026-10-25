<?php declare(strict_types=1);

namespace Admin\Services\Bus;

require_once __DIR__ . '/BusRegistrationValidator.php';

/**
 * Builds the field set for a bus update from the current row and the request.
 *
 * The simplified Bus Management form only sends the plate number and bus type:
 * every key the request does not supply is preserved from the current record,
 * and the additive columns are only included when supplied so they are never
 * blanked by a minimal edit. Pure and side-effect free so it can be tested
 * directly.
 */
final class BusUpdatePayload
{
    /**
     * @param array<string,mixed> $current current buses row
     * @param array<string,mixed> $post request payload ($_POST)
     * @param array<string,mixed> $config bus options configuration
     * @return array<string,mixed> built data plus the derived values the
     *                             endpoint needs for eligibility checks
     */
    public static function build(array $current, array $post, array $config): array
    {
        $posted = static fn (string $key): bool => array_key_exists($key, $post);
        $currentText = static fn (string $key): string => trim((string)($current[$key] ?? ''));
        $currentInt = static function (string $key) use ($current): ?int {
            $value = $current[$key] ?? null;

            return ($value === null || $value === '') ? null : (int)$value;
        };
        $postedInt = static function (string $key) use ($posted, $post): ?int {
            if (!$posted($key)) {
                return null;
            }

            return !empty($post[$key]) ? (int)$post[$key] : null;
        };

        $driverPosted = $posted('driver');
        if ($driverPosted) {
            $driver = trim((string)$post['driver']);
            if ($driver === 'Unassigned') {
                $driver = '';
            }
        } else {
            $driver = $currentText('driver');
            if (strcasecmp($driver, 'Unassigned') === 0 || strcasecmp($driver, 'TBD') === 0) {
                $driver = '';
            }
        }

        $fromTerminal = $posted('from_terminal_id') ? $postedInt('from_terminal_id') : $currentInt('from_terminal_id');
        $toTerminal = $posted('to_terminal_id') ? $postedInt('to_terminal_id') : $currentInt('to_terminal_id');
        $inspectorRef = $posted('assigned_inspector_id') ? $postedInt('assigned_inspector_id') : $currentInt('assigned_inspector_id');
        $route = $posted('route_name') ? trim((string)$post['route_name']) : $currentText('route_name');

        $effectiveSeats = $posted('seats') && trim((string)$post['seats']) !== ''
            ? (int)$post['seats']
            : (int)($current['seats'] ?? 0);
        $effectiveStanding = $posted('standing_capacity') && trim((string)$post['standing_capacity']) !== ''
            ? (int)$post['standing_capacity']
            : (int)($current['standing_capacity'] ?? 0);

        $preservedType = trim((string)($current['bus_type'] ?? ''));
        $typeToSave = $posted('bus_type')
            ? (trim((string)$post['bus_type']) ?: null)
            : ($preservedType !== '' ? $preservedType : null);

        /* Keep the stored capacity valid for the type that will be saved.
           The simplified form no longer types capacity, so a type change
           applies the fleet standard instead of failing the save with an
           out-of-range preserved value, and a standing value that would
           exceed the type's cap is brought back within it. */
        $capacityRule = $typeToSave !== null ? BusRegistrationValidator::capacityRule($config, $typeToSave) : null;
        if ($capacityRule !== null) {
            $seatsPosted = $posted('seats') && trim((string)$post['seats']) !== '';
            $standingPosted = $posted('standing_capacity') && trim((string)$post['standing_capacity']) !== '';
            $min = (int)$capacityRule['seats_min'];
            $max = (int)$capacityRule['seats_max'];
            if (!$seatsPosted && ($effectiveSeats < $min || $effectiveSeats > $max)) {
                $effectiveSeats = (int)($capacityRule['seats_default'] ?? $effectiveSeats);
                if (!$standingPosted) {
                    $effectiveStanding = (int)($capacityRule['standing_default'] ?? $effectiveStanding);
                }
            }
            $totalMax = (int)($capacityRule['total_max'] ?? 0);
            if (!$standingPosted && $totalMax > 0 && ($effectiveSeats + $effectiveStanding) > $totalMax) {
                $effectiveStanding = max(0, $totalMax - $effectiveSeats);
            }
        }

        $status = $posted('status') ? trim((string)$post['status']) : $currentText('status');
        if ($status === '') {
            $status = 'Active';
        }
        if (strtolower((string)($current['status'] ?? '')) === 'archived') {
            // Archived buses only come back through the restore action.
            $status = 'Archived';
        }

        $data = [
            'plate_number' => trim((string)($post['plate_number'] ?? '')),
            'bus_type' => $typeToSave,
            'seats' => $effectiveSeats,
            'standing_capacity' => $effectiveStanding,
            'accessible_features' => $posted('accessible_features') ? trim((string)$post['accessible_features']) : (string)($current['accessible_features'] ?? ''),
            'driver' => $driver !== '' ? $driver : 'Unassigned',
            'from_terminal_id' => $fromTerminal,
            'to_terminal_id' => $toTerminal,
            'route_name' => $route !== '' ? $route : 'TBD',
            'terminal' => $posted('terminal') ? trim((string)$post['terminal']) : (string)($current['terminal'] ?? ''),
            'schedule' => $posted('schedule') ? trim((string)$post['schedule']) : (string)($current['schedule'] ?? ''),
            'status' => $status,
            // Preserve the existing QR mirror; editing never resets the QR.
            'qr_code' => (string)($current['qr_code'] ?? 'Pending'),
        ];

        /* Additive columns are only written when supplied, so the simplified
           form never blanks specifications, operational status, or compliance
           dates. */
        foreach ([
            'unit_number', 'manufacturer', 'model', 'year_model', 'engine_number',
            'chassis_number', 'fuel_type', 'transmission_type', 'air_conditioning',
            'vehicle_ownership', 'assigned_inspector_id', 'operational_status',
            'registration_month',
        ] as $extraKey) {
            if ($posted($extraKey)) {
                $data[$extraKey] = trim((string)($post[$extraKey] ?? ''));
            }
        }
        $dateFields = array_keys(BusRegistrationValidator::DATE_FIELDS);
        if (array_intersect($dateFields, array_keys($post)) !== []) {
            $dates = BusRegistrationValidator::normalizedDates($post);
            foreach ($dateFields as $dateKey) {
                $data[$dateKey] = $dates[$dateKey];
            }
        }

        return [
            'data' => $data,
            'driver' => $driver,
            'driver_posted' => $driverPosted,
            'from_terminal_id' => $fromTerminal,
            'to_terminal_id' => $toTerminal,
            'inspector' => $inspectorRef,
            'route' => $route,
            'seats' => $effectiveSeats,
            'standing_capacity' => $effectiveStanding,
        ];
    }
}
