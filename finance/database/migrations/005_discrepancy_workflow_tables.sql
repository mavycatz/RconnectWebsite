-- R-Connect Discrepancy Investigation Workflow
-- Additive workflow tables for discrepancy cases, status history, evidence,
-- the recovery/adjustment ledger, and approvals.
--
-- The application also creates these tables automatically at runtime
-- (Finance\Models\DiscrepancyCaseModel::initTables), which additionally
-- aligns existing tables to the legacy collation; this file documents the
-- change for deployers and can be applied manually. All statements are
-- CREATE TABLE IF NOT EXISTS and safe to run repeatedly.
--
-- COLLATION: newer MariaDB/MySQL servers default utf8mb4 to a UCA 14.0.0
-- collation (for example utf8mb4_uca1400_ai_ci) while the legacy R-Connect
-- tables use utf8mb4_unicode_ci. Creating these tables with the server
-- default makes driver/status joins fail with error 1267 "Illegal mix of
-- collations". The statements below pin the legacy collation. If the tables
-- already exist with the server default, convert them once:
--   ALTER TABLE discrepancy_cases CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   ALTER TABLE discrepancy_status_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   ALTER TABLE discrepancy_evidence CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   ALTER TABLE discrepancy_ledger_entries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   ALTER TABLE discrepancy_approvals CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--
-- Rollback (only if the workflow is fully abandoned; cases are not referenced
-- by any other feature):
--   DROP TABLE IF EXISTS discrepancy_approvals;
--   DROP TABLE IF EXISTS discrepancy_ledger_entries;
--   DROP TABLE IF EXISTS discrepancy_evidence;
--   DROP TABLE IF EXISTS discrepancy_status_history;
--   DROP TABLE IF EXISTS discrepancy_cases;
--
-- The backfill tool (finance/tools/backfill_discrepancy_cases.php) creates one
-- case per existing remittance_logs row. Dropping discrepancy_cases loses the
-- workflow state but never touches remittance data.

CREATE TABLE IF NOT EXISTS `discrepancy_cases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `remittance_log_id` INT NOT NULL,
    `case_ref` VARCHAR(20) NULL,
    `driver_id` VARCHAR(20) NULL,
    `business_date` DATE NULL,
    `case_type` VARCHAR(40) NOT NULL DEFAULT 'Cash Shortage',
    `status` VARCHAR(40) NOT NULL DEFAULT 'Open',
    `expected_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `actual_amount` DECIMAL(12,2) NULL,
    `discrepancy_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `excess_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `assigned_reviewer` VARCHAR(100) NULL,
    `assigned_at` DATETIME NULL,
    `due_date` DATE NULL,
    `findings` TEXT NULL,
    `resolution_reason` TEXT NULL,
    `opened_by` VARCHAR(100) NULL,
    `opened_at` DATETIME NULL,
    `resolved_at` DATETIME NULL,
    `closed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_disc_case_remittance` (`remittance_log_id`),
    KEY `idx_disc_case_status` (`status`),
    KEY `idx_disc_case_type` (`case_type`),
    KEY `idx_disc_case_driver` (`driver_id`),
    KEY `idx_disc_case_business` (`business_date`),
    KEY `idx_disc_case_reviewer` (`assigned_reviewer`),
    KEY `idx_disc_case_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discrepancy_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT NOT NULL,
    `from_status` VARCHAR(40) NULL,
    `to_status` VARCHAR(40) NULL,
    `event_type` VARCHAR(40) NOT NULL DEFAULT 'status_change',
    `description` TEXT NULL,
    `reason` TEXT NULL,
    `actor_id` INT NULL,
    `actor_username` VARCHAR(100) NULL,
    `actor_role` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_disc_hist_case` (`case_id`),
    KEY `idx_disc_hist_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discrepancy_evidence` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NULL,
    `mime` VARCHAR(100) NULL,
    `size` INT NULL,
    `uploaded_by` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_disc_ev_case` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discrepancy_ledger_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT NOT NULL,
    `entry_type` VARCHAR(40) NOT NULL,
    `recovery_class` VARCHAR(20) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `direction` VARCHAR(10) NOT NULL DEFAULT 'reduce',
    `state` VARCHAR(20) NOT NULL DEFAULT 'proposed',
    `reference` VARCHAR(190) NULL,
    `authorization_reference` VARCHAR(190) NULL,
    `notes` TEXT NULL,
    `proposed_by` VARCHAR(100) NULL,
    `proposed_at` DATETIME NULL,
    `decided_by` VARCHAR(100) NULL,
    `decided_at` DATETIME NULL,
    `decision_reason` TEXT NULL,
    `posted_by` VARCHAR(100) NULL,
    `posted_at` DATETIME NULL,
    `void_reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_disc_ledger_case` (`case_id`),
    KEY `idx_disc_ledger_state` (`state`),
    KEY `idx_disc_ledger_class` (`recovery_class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `discrepancy_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT NOT NULL,
    `ledger_entry_id` INT NULL,
    `action` VARCHAR(40) NOT NULL,
    `amount` DECIMAL(12,2) NULL,
    `reason` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `requested_by` VARCHAR(100) NULL,
    `requested_at` DATETIME NULL,
    `decided_by` VARCHAR(100) NULL,
    `decided_at` DATETIME NULL,
    `decision_reason` TEXT NULL,
    `config_reference` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_disc_appr_case` (`case_id`, `status`),
    KEY `idx_disc_appr_entry` (`ledger_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
