<?php
declare(strict_types=1);

final class PassengerAccountSchema
{
    private static array $ready = [];

    public static function ensure(PDO $db): void
    {
        $key = spl_object_id($db);
        if (isset(self::$ready[$key])) return;

        // Cheap probe first: when the schema is already current this is a few
        // metadata-only statements instead of a full SHOW COLUMNS + DDL pass.
        try {
            $db->query('SELECT account_status, suspension_starts_at, suspension_ends_at, restriction_remarks, login_locked_until FROM passengers LIMIT 0');
            $db->query('SELECT prefix FROM passenger_id_sequences LIMIT 0');
            $db->query('SELECT id FROM passenger_account_events LIMIT 0');
            $db->query('SELECT id FROM passenger_code_history LIMIT 0');
            $db->query('SELECT id, passenger_id, read_at FROM passenger_account_notifications LIMIT 0');
            $db->query('SELECT id, passenger_id, status FROM passenger_account_appeals LIMIT 0');
            $db->query('SELECT identity_kind, identity_value, passenger_id FROM passenger_identity_claims LIMIT 0');
            self::$ready[$key] = true;
            return;
        } catch (\Throwable $e) {
            // Fall through to the additive migration path below.
        }

        // Never implicitly commit an account creation or status transaction.
        if ($db->inTransaction()) {
            throw new RuntimeException('Passenger account storage is not ready. Please retry in a moment.');
        }

        try {
            $columns = array_column($db->query('SHOW COLUMNS FROM passengers')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            foreach ([
                'account_status' => 'VARCHAR(16) NULL',
                'suspension_starts_at' => 'DATETIME NULL',
                'suspension_ends_at' => 'DATETIME NULL',
                'restriction_remarks' => 'TEXT NULL',
                'login_locked_until' => 'DATETIME NULL',
                'status_reason' => 'VARCHAR(255) NULL',
                'status_updated_at' => 'DATETIME NULL',
                'status_updated_by' => 'INT NULL',
            ] as $column => $definition) {
                if (!in_array($column, $columns, true)) {
                    $db->exec("ALTER TABLE passengers ADD COLUMN `$column` $definition");
                }
            }

            // An account table that predates a later migration addition cannot
            // be fixed by CREATE TABLE IF NOT EXISTS; add the columns the
            // account surfaces read, per table, best-effort. A missing table
            // falls through to the migration below, which creates it whole.
            foreach ([
                'passenger_account_notifications' => [
                    'event_id' => 'BIGINT UNSIGNED NULL',
                    'message' => 'TEXT NULL',
                    'read_at' => 'DATETIME NULL',
                ],
                'passenger_account_appeals' => [
                    'status' => "VARCHAR(16) NOT NULL DEFAULT 'PENDING'",
                    'review_note' => 'TEXT NULL',
                    'reviewed_by' => 'INT NULL',
                    'reviewed_at' => 'DATETIME NULL',
                ],
            ] as $table => $tableColumns) {
                try {
                    $existing = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
                    foreach ($tableColumns as $column => $definition) {
                        if (!in_array($column, $existing, true)) {
                            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                        }
                    }
                } catch (\Throwable $e) {
                    // Best-effort provisioning: a database that rejects the
                    // statement must not fail the request.
                    error_log('PassengerAccountSchema column provisioning skipped for ' . $table . ': ' . $e->getMessage());
                }
            }

            $migration = __DIR__ . '/../database/migrations/006_passenger_account_management.sql';
            if (is_file($migration)) {
                $sql = (string) file_get_contents($migration);
                foreach (explode(';', $sql) as $statement) {
                    if (trim($statement) !== '') {
                        $db->exec($statement);
                    }
                }
            }

            self::$ready[$key] = true;
        } catch (\Throwable $e) {
            // DDL is additive and best-effort: a database that rejects it must
            // not break requests. Callers that need the columns will surface a
            // clear JSON error through their own error handling.
            error_log('PassengerAccountSchema migration failed: ' . $e->getMessage());
        }
    }
}
