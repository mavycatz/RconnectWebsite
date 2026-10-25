-- =========================================================
-- 001_remittance_receipt_otp.sql
-- Adds audit columns to remittance_logs and creates the
-- receipt_otps table for OTP-gated receipt printing.
-- Idempotent: safe to run multiple times.
-- =========================================================

ALTER TABLE remittance_logs
    ADD COLUMN IF NOT EXISTS submitted_by VARCHAR(100) NULL AFTER confirmed_by,
    ADD COLUMN IF NOT EXISTS remitted_by VARCHAR(100) NULL AFTER submitted_by,
    ADD COLUMN IF NOT EXISTS reviewed_by VARCHAR(100) NULL AFTER remitted_by,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

ALTER TABLE passenger_trips
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

CREATE TABLE IF NOT EXISTS receipt_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    remittance_id INT NOT NULL,
    otp_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_receipt_otp_user (user_id),
    INDEX idx_receipt_otp_remittance (remittance_id),
    INDEX idx_receipt_otp_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
