<?php declare(strict_types=1);

namespace Admin\Services\Bus;

/**
 * CSV shaping for the Bus Management directory export.
 *
 * Pure helpers so the column set and the spreadsheet-safety rule can be
 * verified without a database: the endpoint only feeds real directory rows in.
 */
final class BusListingCsv
{
    /**
     * @return string[]
     */
    public static function headers(): array
    {
        return [
            'Bus ID',
            'Plate Number',
            'Unit Number',
            'Bus Type',
            'Manufacturer',
            'Model',
            'Year Model',
            'Seats',
            'Standing Capacity',
            'Assigned Driver',
            'Assigned Route',
            'Terminal',
            'Schedule',
            'Status',
            'Registration Month',
            'Registration Expiry',
            'Insurance Expiry',
            'Next Inspection',
            'Date Registered',
        ];
    }

    /**
     * Map one normalized directory row to export cells.
     *
     * @param array<string,mixed> $row
     * @return string[]
     */
    public static function row(array $row): array
    {
        return array_map(
            static fn ($value): string => self::neutralize($value),
            [
                (string)($row['bus_id'] ?? ''),
                (string)($row['plate'] ?? ''),
                (string)($row['unit_number'] ?? ''),
                (string)($row['bus_type'] ?? ''),
                (string)($row['manufacturer'] ?? ''),
                (string)($row['model'] ?? ''),
                (string)($row['year_model'] ?? ''),
                (string)($row['seats'] ?? ''),
                (string)($row['standing_capacity'] ?? ''),
                (string)($row['driver_name'] ?? $row['driver_ref'] ?? ''),
                (string)($row['route_name'] ?? ''),
                (string)($row['terminal'] ?? ''),
                (string)($row['schedule'] ?? ''),
                (string)($row['classification_label'] ?? $row['raw_status'] ?? ''),
                (string)($row['registration_month'] ?? ''),
                (string)($row['registration_expiry'] ?? ''),
                (string)($row['insurance_expiry'] ?? ''),
                (string)($row['next_inspection_date'] ?? ''),
                (string)($row['created_at'] ?? ''),
            ]
        );
    }

    /**
     * Prefix a leading formula character so spreadsheet apps treat the value
     * as text instead of executing it.
     */
    public static function neutralize($value): string
    {
        $value = (string)($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }
}
