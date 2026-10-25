<?php

if (!function_exists('passenger_ensure_hardening_schema')) {
    function passenger_ensure_hardening_schema($conn = null)
    {
        if ($conn === null) {
            if (!class_exists('App\\Core\\Database')) {
                require_once dirname(__DIR__, 2) . '/app/Core/Database.php';
            }
            try {
                $conn = \App\Core\Database::tryGet();
            } catch (\Throwable $e) {
                return;
            }
        }

        if (!$conn) {
            return;
        }

        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS passenger_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    passenger_id INT NOT NULL,
                    session_hash CHAR(64) NOT NULL,
                    device_label VARCHAR(255) NULL,
                    ip_address VARCHAR(45) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    revoked_at DATETIME NULL,
                    revoked_reason VARCHAR(64) NULL,
                    UNIQUE KEY uq_passenger_session_hash (session_hash),
                    KEY idx_passenger_sessions_active (passenger_id, revoked_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS passenger_rate_limits (
                    bucket_key CHAR(64) NOT NULL PRIMARY KEY,
                    action VARCHAR(40) NOT NULL,
                    hits INT NOT NULL DEFAULT 0,
                    window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    locked_until DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'passenger_trips'
                  AND COLUMN_NAME = 'verified_cash_amount'
            ");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) {
                $conn->exec("ALTER TABLE passenger_trips ADD COLUMN verified_cash_amount DECIMAL(10,2) NULL DEFAULT NULL");
            }
        } catch (\Throwable $e) {
        }

        // Saved payment-method preference: set when the passenger confirms a
        // method with "Yes, use it next time". NULL means no preference yet.
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'passengers'
                  AND COLUMN_NAME = 'default_payment_method'
            ");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) {
                $conn->exec("ALTER TABLE passengers ADD COLUMN default_payment_method VARCHAR(20) NULL DEFAULT NULL");
            }
        } catch (\Throwable $e) {
        }
    }
}
