<?php

if (function_exists('ensurePassengerQrSchema')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

/**
 * Idempotent, additive schema addition for the passenger personal QR feature.
 * Follows the project's gated-column pattern (TripSchemaHelper /
 * ensurePaymongoSchema): only ADDs, never alters or removes existing columns.
 *
 * Adds to `passengers`:
 *   personal_qr_seen - 1 once the passenger has dismissed/downloaded/printed
 *                      the first-login QR intro so it never shows again.
 */
function ensurePassengerQrSchema($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $columns = [
        "personal_qr_seen TINYINT(1) NOT NULL DEFAULT 0",
        "passenger_code VARCHAR(50) DEFAULT NULL",
    ];

    foreach ($columns as $definition) {
        $columnName = preg_replace('/^([a-z_]+)\s.*$/i', '$1', $definition);
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM passengers LIKE " . $conn->quote($columnName));
            if (!$stmt->fetch()) {
                $conn->exec("ALTER TABLE passengers ADD COLUMN " . $definition);
            }
        } catch (\PDOException $e) {
            // Column may already exist or table locked — skip silently.
        }
    }

    try {
        $idxStmt = $conn->query("SHOW INDEX FROM passengers WHERE Key_name = 'idx_passengers_code'");
        if (!$idxStmt->fetch()) {
            $conn->exec("ALTER TABLE passengers ADD INDEX idx_passengers_code (passenger_code)");
        }
    } catch (\PDOException $e) {
        // Index may already exist or table locked — skip silently.
    }
}