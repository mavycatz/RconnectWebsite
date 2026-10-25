<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

// Declared before the file's guard: conditional declarations are not hoisted,
// so a guard return above this block would leave the logger undefined while
// the hoisted schema helpers still ran.
if (!function_exists('tripSchemaLogSkippedColumn')) {
    /**
     * Record a column the lazy schema helper could not add. Never throws and
     * never alters a response; falls back to the PHP error log when the
     * diagnostic logger is unavailable (for example in CLI scripts).
     */
    function tripSchemaLogSkippedColumn($table, $column, $error)
    {
        $entry = [
            'ts' => date('c'),
            'route' => 'schema',
            'type' => 'schema_column',
            'code' => 'SCHEMA_COLUMN_SKIPPED',
            'table' => $table,
            'column' => $column,
            'error' => (string) $error,
        ];

        if (function_exists('passenger_qr_scan_log')) {
            passenger_qr_scan_log($entry);
            return;
        }

        error_log('[passenger_schema] ' . $table . '.' . $column . ' skipped: ' . $entry['error']);
    }
}

if (function_exists('ensureTripFlowSchema') && function_exists('ensureLocationHistorySchema')) {
    return;
}

/**
 * Idempotent additive schema additions for the passenger trip flow.
 * Only ADDs columns; never alters or removes existing ones.
 */
function ensureTripFlowSchema($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $columns = [
        "trip_status ENUM('In Progress','Completed','Cancelled') NULL AFTER dropoff_time",
        "selected_destination VARCHAR(150) NULL AFTER destination_id",
        "selected_destination_id INT NULL AFTER selected_destination",
        "selected_km_marker INT NULL AFTER selected_destination_id",
        "estimated_fare DECIMAL(10,2) NULL AFTER selected_km_marker",
        "boarding_stop_id INT NULL AFTER boarding_point",
        "boarding_km_marker INT NULL AFTER boarding_stop_id",
        "actual_stop_id INT NULL AFTER destination_id",
        "actual_km_marker INT NULL AFTER actual_stop_id",
        "base_fare DECIMAL(10,2) NULL AFTER fare",
        "discount_amount DECIMAL(10,2) NULL AFTER base_fare",
        // Planned get-off point chosen on the destination map. Additive and
        // optional; the official destination, fare, and drop-off logic never
        // read these columns.
        "planned_dropoff_lat DECIMAL(10,7) NULL",
        "planned_dropoff_lng DECIMAL(10,7) NULL",
        "planned_dropoff_name VARCHAR(150) NULL",
        "planned_dropoff_set_at DATETIME NULL",
        // Destination proximity/continuation states. Additive and optional;
        // they only drive the once-per-destination alerts and the missed
        // destination continuation flow.
        "destination_near_alerted TINYINT(1) NOT NULL DEFAULT 0",
        "destination_arrival_alerted TINYINT(1) NOT NULL DEFAULT 0",
        "destination_passed_alerted TINYINT(1) NOT NULL DEFAULT 0",
        "destination_passed_at DATETIME NULL",
        // Distance-trend state for passed-destination detection. Additive and
        // optional; only the destination alert evaluator reads and maintains
        // these values.
        "destination_last_distance_m DECIMAL(10,2) NULL",
        "destination_distance_trend TINYINT(1) NOT NULL DEFAULT 0",
        "continuation_of_trip_id INT NULL",
        "continuation_token VARCHAR(64) NULL",
        "carried_balance DECIMAL(10,2) NULL",
        "paid_amount DECIMAL(10,2) NULL",
    ];

    foreach ($columns as $definition) {
        $columnName = preg_replace('/^([a-z_]+)\s.*$/i', '$1', $definition);
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE " . $conn->quote($columnName));
            if (!$stmt->fetch()) {
                $conn->exec("ALTER TABLE passenger_trips ADD COLUMN " . $definition);
            }
        } catch (\Throwable $e) {
            // Column may already exist, the table may be locked, or the
            // database user may lack ALTER privileges. Record it so the
            // skipped field is visible instead of silently missing.
            tripSchemaLogSkippedColumn('passenger_trips', $columnName, $e->getMessage());
        }
    }

    ensureTripContinuationIndexes($conn);
}

/**
 * Unique-index backstops for idempotent continuation creation. MySQL allows
 * multiple NULLs in a unique index, so existing rows and non-continuation
 * trips are unaffected. Index creation is best-effort: a locked table or a
 * database user without ALTER privileges is logged and skipped, and the
 * application-level lock + source-journey check still prevent duplicates.
 */
function ensureTripContinuationIndexes($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $indexes = [
        'uq_passenger_trips_continuation_token' => 'continuation_token',
        'uq_passenger_trips_continuation_of' => 'continuation_of_trip_id',
    ];

    foreach ($indexes as $indexName => $columnName) {
        try {
            $stmt = $conn->query(
                "SHOW INDEX FROM passenger_trips WHERE Key_name = " . $conn->quote($indexName)
            );
            if ($stmt->fetch()) {
                continue;
            }

            // A missing column makes the index impossible; skip quietly and
            // let the column loop above record the real cause.
            $stmt = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE " . $conn->quote($columnName));
            if (!$stmt->fetch()) {
                continue;
            }

            $conn->exec(
                "ALTER TABLE passenger_trips ADD UNIQUE KEY " . $indexName . " (" . $columnName . ")"
            );
        } catch (\Throwable $e) {
            tripSchemaLogSkippedColumn('passenger_trips', $indexName, $e->getMessage());
        }
    }
}

/**
 * Idempotent additive schema additions for passenger location history.
 * passenger_location_history keeps ONE row per trip: first_latitude/
 * first_longitude (boarding GPS) and latitude/longitude (last known GPS,
 * updated while the trip is in progress).
 */
function ensureLocationHistorySchema($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $columns = [
        "first_latitude DECIMAL(10,7) NULL AFTER passenger_trip_id",
        "first_longitude DECIMAL(10,7) NULL AFTER first_latitude",
        "first_recorded_at DATETIME NULL AFTER first_longitude",
    ];

    foreach ($columns as $definition) {
        $columnName = preg_replace('/^([a-z_]+)\s.*$/i', '$1', $definition);
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM passenger_location_history LIKE " . $conn->quote($columnName));
            if (!$stmt->fetch()) {
                $conn->exec("ALTER TABLE passenger_location_history ADD COLUMN " . $definition);
            }
        } catch (\Throwable $e) {
            // Column may already exist, the table may be locked, or the
            // database user may lack ALTER privileges. Record it so the
            // skipped field is visible instead of silently missing.
            tripSchemaLogSkippedColumn('passenger_location_history', $columnName, $e->getMessage());
        }
    }
}
