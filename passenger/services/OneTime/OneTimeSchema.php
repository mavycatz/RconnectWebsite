<?php

if (function_exists('ensureOneTimeSchema')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

/**
 * Idempotent additive schema additions for the One-Time Passenger flow.
 *
 * Only ADDs columns/indexes; never alters or removes existing ones. Follows
 * the same guards as TripSchemaHelper.php / PassengerQRSchema.php so a
 * deployment needs no manual migration step.
 */
function ensureOneTimeSchema($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $columnAdditions = [
        'passengers' => [
            "registration_type ENUM('regular','one_time') NOT NULL DEFAULT 'regular'",
            "contact_number VARCHAR(20) NULL",
            "declared_id_number VARCHAR(50) NULL",
            "declared_id_status VARCHAR(20) NULL",
        ],
        'passenger_trips' => [
            "submission_token VARCHAR(64) NULL",
            "passenger_type VARCHAR(50) NULL",
            "declared_id_number VARCHAR(50) NULL",
            "declared_id_status VARCHAR(20) NULL",
            // Scan-time bus location snapshot for the GPS-driven One-Time
            // boarding flow: where the scanned bus was when the QR was
            // validated, for tracking and verification. Additive and optional.
            "scan_latitude DECIMAL(10,7) NULL",
            "scan_longitude DECIMAL(10,7) NULL",
            "scan_recorded_at DATETIME NULL",
            "scan_stop_id INT NULL",
            "scan_location_source VARCHAR(20) NULL",
        ],
    ];

    foreach ($columnAdditions as $table => $definitions) {
        foreach ($definitions as $definition) {
            $columnName = preg_replace('/^([a-z_]+)\s.*$/i', '$1', $definition);
            try {
                $stmt = $conn->query(
                    "SHOW COLUMNS FROM `" . $table . "` LIKE " . $conn->quote($columnName)
                );
                if (!$stmt->fetch()) {
                    $conn->exec("ALTER TABLE `" . $table . "` ADD COLUMN " . $definition);
                }
            } catch (\PDOException $e) {
                // Silently skip — column may already exist or table locked.
            }
        }
    }

    // Unique index backing the per-submission idempotency guard. The column is
    // nullable, so existing rows are unaffected and multiple NULLs stay allowed.
    try {
        $stmt = $conn->query("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'passenger_trips'
              AND INDEX_NAME = 'uq_passenger_trips_submission_token'
            LIMIT 1
        ");
        if (!$stmt->fetchColumn()) {
            $conn->exec("CREATE UNIQUE INDEX uq_passenger_trips_submission_token ON passenger_trips (submission_token)");
        }
    } catch (\PDOException $e) {
        // Silently skip — index may already exist or table locked.
    }
}
