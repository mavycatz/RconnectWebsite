<?php
declare(strict_types=1);

namespace Admin\Services;

/**
 * Shared field contract for the offline passenger manifest.
 *
 * This file is the single source of truth for the standardized set of fields
 * printed on the trip sheet AND parsed by the import workflow. Keeping both
 * halves on the same contract keeps the paper form and the digital importer
 * consistent (Design D7).
 */
class PassengerManifestFields
{
    /**
     * Standardized passenger row fields (paper column order).
     * key => passenger_trip_history column mapping.
     */
    public const PASSENGER_COLUMNS = [
        'passenger_name'  => 'passenger_name',
        'passenger_type'  => 'passenger_type',
        'discount_type'   => 'discount_type',
        'passenger_id'    => 'student_id',
        'boarding_point'  => 'boarding_point',
        'destination'     => 'destination',
        'fare'            => 'fare',
        'payment_method'  => 'payment_method',
    ];

    /**
     * Human-readable headers, in print order, matching PASSENGER_COLUMNS order.
     */
    public const PASSENGER_HEADERS = [
        'passenger_name' => 'Passenger Name',
        'passenger_type' => 'Passenger Type',
        'discount_type'  => 'Discount Type',
        'passenger_id'   => 'ID',
        'boarding_point' => 'Boarding Point',
        'destination'    => 'Destination',
        'fare'           => 'Fare',
        'payment_method' => 'Payment',
    ];

    /**
     * Standardized header (trip-level) fields.
     * key => passenger_trip_history column mapping.
     */
    public const TRIP_COLUMNS = [
        'trip_date'    => 'trip_date',
        'route'        => null,
        'bus_number'   => 'bus_number',
        'driver'       => 'drivers_full_name',
        'trip_number'  => 'trip_id',
        'remarks'      => 'remarks',
    ];

    /**
     * Row-level DB columns (beyond the mapped fields above) that must be
     * resolved/generated internally during import (Design D5).
     */
    public const INTERNAL_COLUMNS = [
        'history_id',
        'driver_id',
        'trip_id',
        'archived_at',
        'beneficiary_id',
        'coop_disc_id',
    ];

    /** Allowed passenger types in canonical order. */
    public const PASSENGER_TYPES = ['Regular', 'Student', 'Senior', 'PWD', 'Nurse', 'Teacher', 'Other'];

    /** Allowed payment methods. */
    public const PAYMENT_METHODS = ['cash', 'gcash', 'hybrid'];

    /** Status values used by the importer preview. */
    public const STATUS_VALID = 'valid';
    public const STATUS_REVIEW = 'review';
    public const STATUS_INVALID = 'invalid';

    /** Default number of blank handwriting rows on the printed trip sheet. */
    public const DEFAULT_TRIP_SHEET_ROWS = 35;

    /** Minimum fare (mirrors FareCalculator::MINIMUM_FARE). */
    public const MINIMUM_FARE = 15.0;

    /**
     * Normalize a passenger type to a canonical value or a known synonym.
     */
    public static function normalizePassengerType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $t = strtolower(trim($type));
        switch ($t) {
            case 'student':
            case 'students':
                return 'Student';
            case 'senior':
            case 'senior citizen':
            case 'seniors':
                return 'Senior';
            case 'pwd':
            case 'person with disability':
                return 'PWD';
            case 'nurse':
                return 'Nurse';
            case 'teacher':
                return 'Teacher';
            case 'regular':
            case 'normal':
                return 'Regular';
            case 'other':
            case 'cargo':
                return 'Other';
            default:
                return null;
        }
    }

    /**
     * Normalize a payment method to a canonical lowercase value or null.
     */
    public static function normalizePayment(?string $payment): ?string
    {
        if ($payment === null) {
            return null;
        }
        $p = strtolower(trim($payment));
        if (in_array($p, self::PAYMENT_METHODS, true)) {
            return $p;
        }
        if (in_array($p, ['gcash', 'g-cash', 'g cash'], true)) {
            return 'gcash';
        }
        if (in_array($p, ['cash'], true)) {
            return 'cash';
        }
        if (in_array($p, ['hybrid', 'mixed', 'both'], true)) {
            return 'hybrid';
        }
        // Common OCR/typo variants.
        if (str_contains($p, 'g')) {
            return 'gcash';
        }
        if (str_contains($p, 'h')) {
            return 'hybrid';
        }
        return null;
    }

    /**
     * Extract an array-of-fields row into a canonical per-passenger structure.
     * Input uses the standardized field keys; fills mapped history columns.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function toCanonicalPassenger(array $row): array
    {
        $out = [];
        foreach (self::PASSENGER_COLUMNS as $key => $dbCol) {
            $val = $row[$key] ?? $row[$dbCol] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            $out[$dbCol] = ($val === '' ? null : $val);
        }
        if (($out['passenger_type'] ?? null) !== null) {
            $out['passenger_type'] = self::normalizePassengerType((string)$out['passenger_type']) ?? 'Regular';
        }
        if (($out['payment_method'] ?? null) !== null) {
            $out['payment_method'] = self::normalizePayment((string)$out['payment_method']) ?? 'cash';
        }
        if (($out['fare'] ?? null) !== null) {
            $out['fare'] = is_numeric($out['fare']) ? (float)$out['fare'] : null;
        }
        return $out;
    }

    /**
     * Normalize a parsed/extracted trip header into canonical keys.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function toCanonicalTrip(array $raw): array
    {
        $out = [];
        foreach (self::TRIP_COLUMNS as $key => $dbCol) {
            $val = $raw[$key] ?? $raw[$dbCol] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            $out[$key] = ($val === '' ? null : $val);
        }
        // Accept common aliases for heading fields.
        foreach (['trip_date', 'route', 'bus_number', 'driver', 'trip_number', 'remarks'] as $key) {
            if (($out[$key] ?? null) === null) {
                foreach ($raw as $rkey => $rval) {
                    if (strtolower((string)$rkey) === strtolower($key) && !is_array($rval)) {
                        $v = trim((string)$rval);
                        $out[$key] = $v === '' ? null : $v;
                        break;
                    }
                }
            }
        }
        if (($out['trip_date'] ?? null) !== null) {
            $d = date_create($out['trip_date']);
            if ($d === false) {
                unset($out['trip_date']);
            } else {
                $out['trip_date'] = $d->format('Y-m-d');
            }
        }
        if (($raw['payment_method'] ?? null) !== null) {
            $out['payment_method'] = self::normalizePayment((string)$raw['payment_method']) ?? 'cash';
        }
        return $out;
    }

    /**
     * Map a spreadsheet/CSV header row to canonical field keys using
     * case/space/punctuation-insensitive matching.
     *
     * @param array<int,string> $headers
     * @return array<int,string> header index => canonical field key
     */
    public static function mapSpreadsheetHeaders(array $headers): array
    {
        $aliases = [
            'passenger_name' => ['passenger_name', 'passenger name', 'name', 'paxname'],
            'passenger_type' => ['passenger_type', 'passenger type', 'type', 'passtype'],
            'discount_type'  => ['discount_type', 'discount type', 'discount', 'paxdiscount'],
            'passenger_id'   => ['passenger_id', 'passenger id', 'id', 'student_id', 'student id', 'idno'],
            'boarding_point' => ['boarding_point', 'boarding point', 'boarding', 'pickup', 'saan sumakay', 'origin'],
            'destination'    => ['destination', 'end', 'dropoff', 'saan papunta', 'to'],
            'fare'           => ['fare', 'fare amount', 'amount', 'price'],
            'payment_method' => ['payment_method', 'payment method', 'payment', 'mode'],
            'trip_date'      => ['trip_date', 'trip date', 'date'],
            'route'          => ['route', 'route name'],
            'bus_number'     => ['bus_number', 'bus number', 'bus no', 'bus'],
            'driver'         => ['driver', 'driver_full_name', 'drivers_full_name', 'driver name'],
            'trip_number'    => ['trip_number', 'trip number', 'trip id', 'trip_id', 'trip no', 'tripid'],
            'remarks'        => ['remarks', 'remark', 'notes', 'comment'],
        ];

        $map = [];
        foreach ($headers as $idx => $header) {
            $norm = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', trim((string)$header)));
            $norm = trim(preg_replace('/\s+/', ' ', $norm));
            foreach ($aliases as $key => $names) {
                foreach ($names as $name) {
                    if ($norm === strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name)))) {
                        $map[$idx] = $key;
                        break 2;
                    }
                }
            }
        }
        return $map;
    }
}
