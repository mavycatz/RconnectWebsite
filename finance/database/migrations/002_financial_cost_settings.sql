-- ============================================================
-- R-CONNECT TMS — Financial Cost Settings
-- Migration 002
--
-- Creates the editable driver-deduction settings used by the
-- Financial Performance calculations (amortization, insurance,
-- GPS, OPTR) and the Gas fallback fuel price.
--
-- The application also creates/seeds this table lazily via
-- Finance\Models\FinancialCostSettingsModel::ensureSchema().
-- This file mirrors that schema for manual deployments.
-- ============================================================

CREATE TABLE IF NOT EXISTS `financial_cost_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cost_key` VARCHAR(50) NOT NULL UNIQUE,
    `label` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `calc_method` VARCHAR(40) NOT NULL DEFAULT 'fixed_monthly',
    `unit` VARCHAR(40) NOT NULL DEFAULT 'PHP/month',
    `effective_date` DATE NOT NULL,
    `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `notes` VARCHAR(255) NULL,
    `updated_by` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the legacy hardcoded values so calculations stay identical
-- until an authorized user changes them.
INSERT INTO `financial_cost_settings`
    (`cost_key`, `label`, `amount`, `rate`, `calc_method`, `unit`, `effective_date`, `status`, `notes`)
VALUES
    ('amortization', 'Amortization', 1098.00, 0.0000, 'fixed_monthly', 'PHP/month', CURDATE(), 'Active', 'Monthly bus amortization share per driver.'),
    ('insurance',    'Insurance',    192.89,  0.0000, 'fixed_monthly', 'PHP/month', CURDATE(), 'Active', 'Monthly fleet insurance share per driver.'),
    ('gps',          'GPS',          6.25,    0.0000, 'fixed_monthly', 'PHP/month', CURDATE(), 'Active', 'Monthly GPS tracking subscription per driver.'),
    ('optr',         'OPTR',         500.00,  0.0000, 'fixed_monthly', 'PHP/month', CURDATE(), 'Active', 'Other operating expenses / permit and regulatory fees.'),
    ('gas',          'Gas (Fuel)',   65.55,   0.0000, 'actual_fuel',   'PHP/liter', CURDATE(), 'Active', 'Fallback fuel price per liter; live price comes from Fuel Prices.')
ON DUPLICATE KEY UPDATE `cost_key` = `cost_key`;
