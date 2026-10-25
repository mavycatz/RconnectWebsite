<?php declare(strict_types=1);

namespace Admin\Services\Bus;

require_once __DIR__ . '/BusFields.php';

/**
 * Field validation for bus registration and editing.
 *
 * Pure and database-free so every branch can be tested directly; database
 * checks (plate duplicates, driver eligibility, terminal existence) stay in
 * the endpoint where the connection lives.
 */
final class BusRegistrationValidator
{
    public const LEGACY_STATUSES = ['Active', 'Inactive', 'Pending QR'];

    public const DATE_FIELDS = [
        'registration_expiry' => 'Registration expiration',
        'insurance_expiry' => 'Insurance expiration',
        'inspection_date' => 'Inspection date',
        'next_inspection_date' => 'Next inspection date',
    ];

    /**
     * @param array<string,mixed> $input POST payload
     * @param array<string,mixed> $config bus_options.php configuration
     * @return string|null error message, or null when valid
     */
    public static function validate(array $input, array $config): ?string
    {
        $plate = trim((string)($input['plate_number'] ?? ''));
        if ($plate === '') {
            return 'Plate number is required.';
        }
        if (mb_strlen($plate) > 20) {
            return 'Plate number is too long.';
        }

        $busType = trim((string)($input['bus_type'] ?? ''));
        if (mb_strlen($busType) > 50) {
            return 'Select a valid bus type.';
        }

        $status = trim((string)($input['status'] ?? 'Active'));
        if (!in_array($status, self::LEGACY_STATUSES, true)) {
            return 'Select a valid bus status.';
        }

        $operational = trim((string)($input['operational_status'] ?? ''));
        if ($operational !== '' && !in_array($operational, $config['operational_statuses'] ?? [], true)) {
            return 'Select a valid operational status.';
        }

        $seats = (int)($input['seats'] ?? 0);
        if ($seats < 0 || $seats > 200) {
            return 'Seat capacity must be between 0 and 200.';
        }

        $standing = (int)($input['standing_capacity'] ?? 0);
        if ($standing < 0 || $standing > 100) {
            return 'Standing capacity must be between 0 and 100.';
        }

        $capacityRule = self::capacityRule($config, $busType);
        if ($capacityRule !== null) {
            $min = (int)$capacityRule['seats_min'];
            $max = (int)$capacityRule['seats_max'];
            if ($seats < $min || $seats > $max) {
                return self::seatRangeMessage((string)$capacityRule['label'], $min, $max);
            }
            $totalMax = (int)($capacityRule['total_max'] ?? 0);
            if ($totalMax > 0 && $seats + $standing > $totalMax) {
                return $capacityRule['label'] . ' total capacity (seats + standing) must not exceed ' . $totalMax . '.';
            }
        }

        foreach (self::DATE_FIELDS as $field => $label) {
            $raw = trim((string)($input[$field] ?? ''));
            if ($raw !== '' && BusFields::normalizeDate($raw) === null) {
                return $label . ' is not a valid date.';
            }
        }

        $inspector = trim((string)($input['assigned_inspector_id'] ?? ''));
        if ($inspector !== '' && !ctype_digit($inspector)) {
            return 'Select a valid inspector.';
        }

        $year = trim((string)($input['year_model'] ?? ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year) !== 1) {
            return 'Year model must be a four-digit year.';
        }

        return null;
    }

    /**
     * Capacity rule for a configured bus type, matched case-insensitively.
     * Returns null when the type has no rule (legacy or free-text types).
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>|null
     */
    public static function capacityRule(array $config, string $busType): ?array
    {
        $rules = $config['bus_type_capacities'] ?? [];
        if (!is_array($rules)) {
            return null;
        }
        $needle = strtolower(trim($busType));
        if ($needle === '') {
            return null;
        }
        foreach ($rules as $label => $rule) {
            if (strtolower(trim((string)$label)) !== $needle || !is_array($rule)) {
                continue;
            }
            $rule['label'] = (string)$label;

            return $rule;
        }

        return null;
    }

    /**
     * Seat/standing defaults for a configured bus type (case-insensitive),
     * or null when the type has no fleet standard.
     *
     * @param array<string,mixed> $config
     * @return array{seats:int,standing:int}|null
     */
    public static function capacityDefaults(array $config, string $busType): ?array
    {
        $rule = self::capacityRule($config, $busType);
        if ($rule === null) {
            return null;
        }

        return [
            'seats' => (int)($rule['seats_default'] ?? 0),
            'standing' => (int)($rule['standing_default'] ?? 0),
        ];
    }

    private static function seatRangeMessage(string $label, int $min, int $max): string
    {
        if ($min === $max) {
            return $label . ' seating capacity must be ' . $min . '.';
        }

        return $label . ' seating capacity must be between ' . $min . ' and ' . $max . '.';
    }

    /**
     * Normalize the date fields into storage values (Y-m-d or null).
     *
     * @param array<string,mixed> $input
     * @return array<string,?string>
     */
    public static function normalizedDates(array $input): array
    {
        $dates = [];
        foreach (array_keys(self::DATE_FIELDS) as $field) {
            $raw = trim((string)($input[$field] ?? ''));
            $dates[$field] = $raw === '' ? null : BusFields::normalizeDate($raw);
        }

        return $dates;
    }
}
