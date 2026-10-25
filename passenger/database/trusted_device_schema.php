<?php

if (!function_exists('passenger_ensure_trusted_device_schema')) {
    function passenger_ensure_trusted_device_schema($conn = null)
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
                CREATE TABLE IF NOT EXISTS passenger_remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    passenger_id INT NOT NULL,
                    selector CHAR(16) NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires DATETIME NOT NULL,
                    device_label VARCHAR(255) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    last_used_at DATETIME NULL,
                    rotated_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    UNIQUE KEY uq_passenger_remember_selector (selector),
                    KEY idx_passenger_remember_passenger (passenger_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
        }

        $columns = [
            'device_label' => "ALTER TABLE passenger_remember_tokens ADD COLUMN device_label VARCHAR(255) NULL",
            'user_agent' => "ALTER TABLE passenger_remember_tokens ADD COLUMN user_agent VARCHAR(255) NULL",
            'created_at' => "ALTER TABLE passenger_remember_tokens ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
            'last_used_at' => "ALTER TABLE passenger_remember_tokens ADD COLUMN last_used_at DATETIME NULL",
            'rotated_at' => "ALTER TABLE passenger_remember_tokens ADD COLUMN rotated_at DATETIME NULL",
            'revoked_at' => "ALTER TABLE passenger_remember_tokens ADD COLUMN revoked_at DATETIME NULL",
        ];

        foreach ($columns as $column => $sql) {
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'passenger_remember_tokens'
                      AND COLUMN_NAME = ?
                ");
                $stmt->execute([$column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $conn->exec($sql);
                }
            } catch (\Throwable $e) {
            }
        }
    }
}
