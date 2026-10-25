<?php

/**
 * Companion Registration schema (additive, idempotent).
 *
 * Adds the saved-companion profile table and the trip-linkage columns used by
 * the Companion Registration feature. The primary passenger keeps their own
 * passenger_trips row; every companion travelled-with gets its OWN row (so
 * driver counts, fare fields, payment totals and reports stay accurate) linked
 * back to the primary via companion_of.
 *
 * Both DDL helpers are safe to run on every request: they check the current DB
 * state first and only apply missing additions, exactly like the other
 * passenger schema helpers.
 */

if (!function_exists('ensureCompanionSchema')) {

    function ensureCompanionSchema(PDO $conn)
    {
        $dbName = $conn->query('SELECT DATABASE()')->fetchColumn();

        $tableExists = false;
        try {
            $stmt = $conn->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
            );
            $stmt->execute([$dbName, 'passenger_companions']);
            $tableExists = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $tableExists = false;
        }

        if (!$tableExists) {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS passenger_companions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    owner_passenger_id INT NOT NULL,
                    passenger_id INT NULL,
                    full_name VARCHAR(150) NOT NULL,
                    companion_type ENUM('child','senior','pwd','other') NOT NULL DEFAULT 'other',
                    declared_id_number VARCHAR(50) NULL,
                    declared_id_status VARCHAR(20) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_pc_owner (owner_passenger_id),
                    UNIQUE KEY uq_pc_owner_name (owner_passenger_id, full_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // Older profile tables must accept the same types as both passenger forms.
        $typeColumn = $conn->query("SHOW COLUMNS FROM passenger_companions LIKE 'companion_type'")->fetch(PDO::FETCH_ASSOC);
        if ($typeColumn && stripos((string) ($typeColumn['Type'] ?? ''), "'pwd'") === false) {
            $conn->exec("ALTER TABLE passenger_companions MODIFY companion_type ENUM('child','senior','pwd','other') NOT NULL DEFAULT 'other'");
        }

        $columns = [];
        try {
            $stmt = $conn->prepare(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?'
            );
            $stmt->execute([$dbName, 'passenger_trips']);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            $columns = [];
        }

        $columns = array_map('strtolower', $columns);

        if (!in_array('companion_of', $columns, true)) {
            $conn->exec('ALTER TABLE passenger_trips ADD COLUMN companion_of INT NULL');
        }

        if (!in_array('companion_profile_id', $columns, true)) {
            $conn->exec('ALTER TABLE passenger_trips ADD COLUMN companion_profile_id INT NULL');
        }

        try {
            $hasIndex = false;
            $stmt = $conn->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND column_name = ?'
            );
            $stmt->execute([$dbName, 'passenger_trips', 'companion_of']);
            $hasIndex = ((int) $stmt->fetchColumn()) > 0;
            if (!$hasIndex) {
                $conn->exec('ALTER TABLE passenger_trips ADD INDEX idx_pt_companion_of (companion_of)');
            }
        } catch (\Throwable $e) {
            // Non-critical: the column still works without the index.
        }
    }
}
