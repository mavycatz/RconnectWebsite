-- Additive tables only. PassengerAccountSchema adds missing passenger columns.
-- Existing passenger IDs, trip references and transaction rows are preserved.
CREATE TABLE IF NOT EXISTS passenger_id_sequences (
    prefix VARCHAR(8) PRIMARY KEY,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passenger_code_history (
    passenger_code VARCHAR(64) PRIMARY KEY,
    passenger_id INT NOT NULL,
    classification VARCHAR(64) NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retired_at DATETIME NULL,
    INDEX idx_code_account (passenger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passenger_account_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    action VARCHAR(32) NOT NULL,
    old_status VARCHAR(16) NOT NULL,
    new_status VARCHAR(16) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    remarks TEXT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    actor_id INT NULL,
    actor_name VARCHAR(100) NOT NULL,
    actor_role VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_account_history (passenger_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passenger_account_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    UNIQUE KEY uq_event_notification (event_id),
    INDEX idx_account_notice (passenger_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passenger_account_appeals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
    review_note TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_restriction_appeal (passenger_id, event_id),
    INDEX idx_pending_appeals (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS passenger_identity_claims (
    identity_kind VARCHAR(16) NOT NULL,
    identity_value VARCHAR(191) NOT NULL,
    passenger_id INT NOT NULL,
    PRIMARY KEY (identity_kind, identity_value),
    INDEX idx_identity_account (passenger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
