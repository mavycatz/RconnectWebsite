<?php

if (function_exists('ensurePaymongoSchema')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

/**
 * Idempotent, additive schema additions for the PayMongo integration.
 * Follows the project's gated-column pattern (TripSchemaHelper /
 * gcashEnsureColumns): only ADDs, never alters or removes.
 *
 * Adds to `passenger_trips`:
 *   paymongo_intent_id    - PayMongo PaymentIntent id (pi_...)
 *   paymongo_payment_id   - PayMongo Payment id (pay_...), set on success
 *   paymongo_checkout_url - last redirect URL sent to the passenger
 *   paymongo_history      - latest outcome notes (success/failed/cancelled/mismatch)
 *   paymongo_qr_image     - Base64 QR Ph code (data URI) to re-render
 *   paymongo_qr_expires_at- when the shown QR code stops being valid
 *   paymongo_client_key   - the intent's client key (for diagnostics)
 *
 * Creates `paymongo_webhook_events` (event-id dedup ledger).
 */
function ensurePaymongoSchema($conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $columns = [
        "paymongo_intent_id VARCHAR(50) NULL",
        "paymongo_payment_id VARCHAR(50) NULL",
        "paymongo_checkout_url VARCHAR(500) NULL",
        "paymongo_history VARCHAR(1000) NULL",
        "paymongo_qr_image LONGTEXT NULL",
        "paymongo_qr_expires_at DATETIME NULL",
        "paymongo_client_key VARCHAR(255) NULL",
    ];

    foreach ($columns as $definition) {
        $columnName = preg_replace('/^([a-z_]+)\s.*$/i', '$1', $definition);
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE " . $conn->quote($columnName));
            if (!$stmt->fetch()) {
                $conn->exec("ALTER TABLE passenger_trips ADD COLUMN " . $definition);
            }
        } catch (\PDOException $e) {
            // Column may already exist or table locked — skip silently.
        }
    }

    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'paymongo_webhook_events'");
        if (!$stmt->fetch()) {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS paymongo_webhook_events (
                    event_id VARCHAR(80) NOT NULL PRIMARY KEY,
                    event_type VARCHAR(100) NULL,
                    payload LONGTEXT NULL,
                    received_at DATETIME NOT NULL,
                    processed_at DATETIME NULL
                )
            ");
        }
    } catch (\PDOException $e) {
        // Ignore — table may already exist.
    }
}
