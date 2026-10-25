<?php

declare(strict_types=1);

namespace Finance\Services;

use DateTime;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Trip-history CSV import shared by the admin passenger import and the
 * Financial Reports "Import Data" component.
 *
 * The CSV structure, column mapping, date/time handling and validation rules
 * mirror the existing Trip History CSV Import
 * (admin/api/Passenger/importTripHistory.php): the same headers, the same
 * required columns, the same accepted date and time formats, the same
 * payment methods, and the same driver lookup. Financial Reports reuses this
 * service so both surfaces accept one format.
 */
class TripHistoryCsvImportService
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    /** CSV header (or documented alias) => passenger_trip_history column. */
    public const COLUMN_MAP = [
        'passenger_name' => 'passenger_name',
        'passenger_type' => 'passenger_type',
        'student_id' => 'student_id',
        'boarding_point' => 'boarding_point',
        'destination' => 'destination',
        'drivers_full_name' => 'drivers_full_name',
        'bus_number' => 'bus_number',
        'trip_id' => 'trip_id',
        'fare' => 'fare',
        'payment_method' => 'payment_method',
        'date_booked' => 'date_booked',
        'trip_date' => 'trip_date',
        'senior_pwd_id' => 'senior_pwd_id',
        'Senior/PWD_id' => 'senior_pwd_id',
        'beneficiary_id' => 'beneficiary_id',
        'benefeciary_id' => 'beneficiary_id',
        'coop_disc_id' => 'coop_disc_id',
        'coop_disk_id' => 'coop_disc_id',
        'remarks' => 'remarks',
        'time_of_departure' => 'time_of_departure',
        'time_of_arrival' => 'time_of_arrival',
    ];

    /** Required CSV columns (the admin importer's rule). */
    public const REQUIRED_COLUMNS = ['passenger_name', 'boarding_point', 'destination', 'drivers_full_name'];

    /** Columns that must carry a value in every row. */
    public const REQUIRED_FIELDS = ['passenger_name', 'boarding_point', 'destination', 'drivers_full_name'];

    /** Payment methods accepted by the trip-history format. */
    public const PAYMENT_METHODS = ['cash', 'gcash', 'hybrid'];

    /** Row status values used by preview()/import(). */
    public const STATUS_VALID = 'valid';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_ERROR = 'error';

    /**
     * Validate an uploaded CSV file and return [tmpPath, fileName].
     *
     * @param array<string,mixed> $file One entry from $_FILES.
     * @return array{0:string,1:string}
     */
    public static function validateUpload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please upload a valid CSV file.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The file could not be written to disk.',
                UPLOAD_ERR_EXTENSION => 'The upload was stopped by the server.',
            ];
            throw new RuntimeException($messages[$error] ?? ('Upload failed (code ' . $error . ').'));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $fileName = (string) ($file['name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('Invalid upload.');
        }
        if (filesize($tmpPath) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('CSV file is too large. Maximum is 10MB.');
        }
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('Only CSV files are supported.');
        }

        return [$tmpPath, $fileName];
    }

    /**
     * Parse and validate the file without writing anything. The returned
     * structure drives the preview table, the column mapping, and the
     * success/warning/error counts.
     *
     * @return array<string,mixed>
     */
    public static function preview(PDO $db, string $tmpPath, string $fileName): array
    {
        $analysis = self::analyse($db, $tmpPath, $fileName);
        $analysis['success'] = true;

        return $analysis;
    }

    /**
     * Import the valid rows inside one transaction. Duplicate records and
     * invalid rows are skipped and counted; a database failure rolls the
     * whole import back so no partial set is written.
     *
     * @return array<string,mixed>
     */
    public static function import(PDO $db, string $tmpPath, string $fileName): array
    {
        $analysis = self::analyse($db, $tmpPath, $fileName);

        if (!empty($analysis['missing_required'])) {
            throw new RuntimeException(
                'Missing required column(s): ' . implode(', ', $analysis['missing_required'])
            );
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $dateFrom = null;
        $dateTo = null;

        $driverStmt = $db->prepare('SELECT driver_id, assigned_buses FROM drivers WHERE full_name = ? LIMIT 1');
        $insertStmt = $db->prepare(self::insertSql());

        $db->beginTransaction();

        try {
            foreach ($analysis['rows'] as $row) {
                if ($row['status'] === self::STATUS_DUPLICATE) {
                    $skipped++;
                    continue;
                }
                if ($row['status'] !== self::STATUS_VALID) {
                    $failed++;
                    foreach ($row['errors'] as $error) {
                        $errors[] = 'Row ' . $row['line'] . ': ' . $error;
                    }
                    continue;
                }

                try {
                    $insertStmt->execute(self::insertParams($db, $row['data'], $driverStmt));
                    $imported++;

                    $rowDate = $row['data']['trip_date'] ?? ($row['data']['date_booked'] ?? null);
                    if (is_string($rowDate) && $rowDate !== '') {
                        if ($dateFrom === null || $rowDate < $dateFrom) {
                            $dateFrom = $rowDate;
                        }
                        if ($dateTo === null || $rowDate > $dateTo) {
                            $dateTo = $rowDate;
                        }
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = 'Row ' . $row['line'] . ': ' . $e->getMessage();
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($analysis['rows']),
            'errors' => array_slice($errors, 0, 20),
            'file' => $fileName,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'message' => self::summaryMessage($imported, $skipped, $failed),
        ];
    }

    /**
     * Parse the CSV and classify every data row.
     *
     * @return array<string,mixed>
     */
    private static function analyse(PDO $db, string $tmpPath, string $fileName): array
    {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open the CSV file.');
        }

        try {
            $rawHeader = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$rawHeader) {
                throw new RuntimeException('CSV header is missing.');
            }

            $headers = [];
            foreach ($rawHeader as $column) {
                $headers[] = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column));
            }

            if (count(array_filter($headers, static fn(string $h): bool => $h !== '')) === 0) {
                throw new RuntimeException('CSV has no valid headers.');
            }

            $colIndex = [];
            $foundColumns = [];
            foreach ($headers as $index => $header) {
                if ($header === '' || isset($foundColumns[$header])) {
                    continue;
                }
                $foundColumns[$header] = true;
                if (isset(self::COLUMN_MAP[$header]) && !isset($colIndex[self::COLUMN_MAP[$header]])) {
                    $colIndex[self::COLUMN_MAP[$header]] = $index;
                }
            }

            $missingRequired = [];
            foreach (self::REQUIRED_COLUMNS as $required) {
                if (!in_array($required, $headers, true)) {
                    $missingRequired[] = $required;
                }
            }

            $columns = [];
            foreach ($headers as $header) {
                if ($header === '') {
                    continue;
                }
                if (isset($columns[$header])) {
                    continue;
                }
                $columns[$header] = [
                    'name' => $header,
                    'mapped' => isset(self::COLUMN_MAP[$header]),
                    'required' => in_array($header, self::REQUIRED_COLUMNS, true),
                ];
            }

            // The documented column list (canonical names only, aliases folded)
            // drives the mapping chips; anything else is reported as ignored.
            $knownGroups = [];
            foreach (self::COLUMN_MAP as $csvName => $dbColumn) {
                if (!isset($knownGroups[$dbColumn])) {
                    $knownGroups[$dbColumn] = ['name' => $csvName, 'aliases' => []];
                }
                $knownGroups[$dbColumn]['aliases'][] = $csvName;
            }

            $knownColumns = [];
            foreach ($knownGroups as $group) {
                $found = false;
                foreach ($group['aliases'] as $alias) {
                    if (in_array($alias, $headers, true)) {
                        $found = true;
                        break;
                    }
                }
                $knownColumns[] = [
                    'name' => $group['name'],
                    'required' => in_array($group['name'], self::REQUIRED_COLUMNS, true),
                    'found' => $found,
                ];
            }

            $ignoredHeaders = array_values(array_filter(
                $headers,
                static fn(string $header): bool => $header !== '' && !isset(self::COLUMN_MAP[$header])
            ));

            $rows = [];
            $counts = ['valid' => 0, 'duplicate' => 0, 'error' => 0, 'total' => 0];
            $line = 1;

            while (($raw = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;
                if (count(array_filter($raw, static fn($v): bool => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $counts['total']++;
                [$data, $errors] = self::validateRow($raw, $colIndex);

                $status = self::STATUS_VALID;
                if ($errors !== []) {
                    $status = self::STATUS_ERROR;
                } elseif (self::isDuplicate($db, $data)) {
                    $status = self::STATUS_DUPLICATE;
                    $errors[] = 'Duplicate of an existing trip-history record';
                }

                $counts[$status]++;

                $values = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $values[$header] = isset($raw[$index]) ? trim((string) $raw[$index]) : '';
                }

                $rows[] = [
                    'line' => $line,
                    'values' => $values,
                    'status' => $status,
                    'errors' => $errors,
                    'data' => $data,
                ];
            }

            return [
                'file' => $fileName,
                'columns' => array_values($columns),
                'known_columns' => $knownColumns,
                'ignored_headers' => array_values(array_unique($ignoredHeaders)),
                'missing_required' => $missingRequired,
                'rows' => $rows,
                'counts' => $counts,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int,string|null> $raw
     * @param array<string,int> $colIndex
     * @return array{0:array<string,mixed>,1:list<string>}
     */
    private static function validateRow(array $raw, array $colIndex): array
    {
        $get = static function (string $field) use ($raw, $colIndex): ?string {
            if (!isset($colIndex[$field])) {
                return null;
            }
            $index = $colIndex[$field];
            if (!array_key_exists($index, $raw)) {
                return null;
            }
            $value = trim((string) $raw[$index]);
            return $value === '' ? null : $value;
        };

        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($get($field) === null) {
                $errors[] = $field . ' is required';
            }
        }

        $fareRaw = $get('fare');
        if ($fareRaw !== null && !is_numeric($fareRaw)) {
            $errors[] = 'fare must be a numeric value';
        }

        $tripDateRaw = $get('trip_date');
        $tripDate = self::convertDate($tripDateRaw);
        if ($tripDateRaw !== null && $tripDate === null) {
            $errors[] = "invalid trip_date format '{$tripDateRaw}'";
        }

        $dateBookedRaw = $get('date_booked');
        $dateBooked = self::convertDate($dateBookedRaw);
        if ($dateBookedRaw !== null && $dateBooked === null) {
            $errors[] = "invalid date_booked format '{$dateBookedRaw}'";
        }

        $departureRaw = $get('time_of_departure');
        $departure = self::convertTime($departureRaw);
        if ($departureRaw !== null && $departure === null) {
            $errors[] = "invalid time_of_departure format '{$departureRaw}' (expected HH:MM:SS)";
        }

        $arrivalRaw = $get('time_of_arrival');
        $arrival = self::convertTime($arrivalRaw);
        if ($arrivalRaw !== null && $arrival === null) {
            $errors[] = "invalid time_of_arrival format '{$arrivalRaw}' (expected HH:MM:SS)";
        }

        $paymentMethod = $get('payment_method');
        if ($paymentMethod !== null) {
            $paymentMethod = strtolower($paymentMethod);
            if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
                $errors[] = 'payment_method must be one of: ' . implode(', ', self::PAYMENT_METHODS);
            }
        }

        $tripIdRaw = $get('trip_id');
        $tripId = $tripIdRaw !== null ? trim($tripIdRaw) : null;
        if ($tripId === '') {
            $tripId = null;
        }

        $data = [
            'passenger_name' => $get('passenger_name'),
            'passenger_type' => $get('passenger_type'),
            'student_id' => $get('student_id'),
            'boarding_point' => $get('boarding_point'),
            'destination' => $get('destination'),
            'drivers_full_name' => $get('drivers_full_name'),
            'bus_number' => $get('bus_number'),
            'trip_id' => $tripId,
            'fare' => $fareRaw !== null ? (float) $fareRaw : 0.0,
            'payment_method' => $paymentMethod,
            'date_booked' => $dateBooked,
            'trip_date' => $tripDate,
            'senior_pwd_id' => $get('senior_pwd_id'),
            'beneficiary_id' => $get('beneficiary_id'),
            'coop_disc_id' => $get('coop_disc_id'),
            'remarks' => $get('remarks'),
            'time_of_departure' => $departure,
            'time_of_arrival' => $arrival,
        ];

        return [$data, $errors];
    }

    /**
     * Duplicate identity follows the existing financial import: the same
     * passenger on the same trip id and trip date is the same record.
     *
     * @param array<string,mixed> $data
     */
    private static function isDuplicate(PDO $db, array $data): bool
    {
        $passengerName = trim((string) ($data['passenger_name'] ?? ''));
        if ($passengerName === '') {
            return false;
        }

        $tripId = $data['trip_id'] ?? null;
        $tripDate = $data['trip_date'] ?? null;

        try {
            if ($tripId === null) {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM passenger_trip_history
                    WHERE passenger_name = ?
                      AND (trip_id IS NULL OR TRIM(trip_id) = '' OR TRIM(trip_id) = '0')
                      AND (trip_date = ? OR (? IS NULL AND trip_date IS NULL))
                ");
                $stmt->execute([$passengerName, $tripDate, $tripDate]);
            } else {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM passenger_trip_history
                    WHERE passenger_name = ?
                      AND TRIM(trip_id) = ?
                      AND (trip_date = ? OR (? IS NULL AND trip_date IS NULL))
                ");
                $stmt->execute([$passengerName, $tripId, $tripDate, $tripDate]);
            }

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            // A duplicate check that cannot run must not mark valid rows as
            // duplicates.
            return false;
        }
    }

    private static function insertSql(): string
    {
        return 'INSERT INTO passenger_trip_history (
            passenger_name, passenger_type, student_id,
            boarding_point, destination, drivers_full_name,
            driver_id, bus_number, trip_id, fare, payment_method,
            date_booked, trip_date,
            senior_pwd_id, beneficiary_id, coop_disc_id,
            remarks, archived, archived_at,
            time_of_departure, time_of_arrival
        ) VALUES (
            :passenger_name, :passenger_type, :student_id,
            :boarding_point, :destination, :drivers_full_name,
            :driver_id, :bus_number, :trip_id, :fare, :payment_method,
            :date_booked, :trip_date,
            :senior_pwd_id, :beneficiary_id, :coop_disc_id,
            :remarks, 0, NULL,
            :time_of_departure, :time_of_arrival
        )';
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function insertParams(PDO $db, array $data, \PDOStatement $driverStmt): array
    {
        $driverId = null;
        $busNumber = $data['bus_number'];

        $driversFullName = $data['drivers_full_name'];
        if ($driversFullName !== null && trim((string) $driversFullName) !== '') {
            try {
                $driverStmt->execute([$driversFullName]);
                $driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($driverRow['driver_id'])) {
                    $driverId = $driverRow['driver_id'];
                    if (($busNumber === null || trim((string) $busNumber) === '') && !empty($driverRow['assigned_buses'])) {
                        $assigned = explode(',', (string) $driverRow['assigned_buses']);
                        $busNumber = trim((string) $assigned[0]);
                    }
                }
            } catch (Throwable $e) {
                // Driver enrichment is optional; the row still imports.
            }
        }

        return [
            ':passenger_name' => $data['passenger_name'],
            ':passenger_type' => $data['passenger_type'],
            ':student_id' => $data['student_id'],
            ':boarding_point' => $data['boarding_point'],
            ':destination' => $data['destination'],
            ':drivers_full_name' => $driversFullName,
            ':driver_id' => $driverId,
            ':bus_number' => $busNumber ?? '',
            ':trip_id' => $data['trip_id'],
            ':fare' => $data['fare'],
            ':payment_method' => $data['payment_method'],
            ':date_booked' => $data['date_booked'],
            ':trip_date' => $data['trip_date'],
            ':senior_pwd_id' => $data['senior_pwd_id'],
            ':beneficiary_id' => $data['beneficiary_id'],
            ':coop_disc_id' => $data['coop_disc_id'],
            ':remarks' => $data['remarks'],
            ':time_of_departure' => $data['time_of_departure'],
            ':time_of_arrival' => $data['time_of_arrival'],
        ];
    }

    private static function convertDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        foreach (['n/j/Y', 'm/d/Y', 'Y-m-d', 'Y/m/d', 'F d,Y', 'F j,Y', 'M d,Y', 'M j,Y'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private static function convertTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        foreach (['H:i:s', 'H:i', 'His', 'h:i A', 'h:iA'] as $format) {
            $time = DateTime::createFromFormat($format, $value);
            if ($time !== false) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    private static function summaryMessage(int $imported, int $skipped, int $failed): string
    {
        $parts = [];
        $parts[] = $imported . ' row(s) imported';
        if ($skipped > 0) {
            $parts[] = $skipped . ' duplicate row(s) skipped';
        }
        if ($failed > 0) {
            $parts[] = $failed . ' invalid row(s)';
        }

        return implode(', ', $parts);
    }
}
