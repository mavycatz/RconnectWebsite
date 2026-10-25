-- R-Connect Discrepancy Investigation Workflow
-- Adds the investigation approval columns to discrepancy_cases: who submitted
-- the findings, when, and the Finance Manager decision metadata.
--
-- The application also applies these columns automatically at runtime
-- (Finance\Models\DiscrepancyCaseModel::ensureCaseColumns); this file documents
-- the change for deployers and can be applied manually. Idempotent.
--
-- Rollback:
--   ALTER TABLE discrepancy_cases DROP COLUMN findings_submitted_by;
--   ALTER TABLE discrepancy_cases DROP COLUMN findings_submitted_at;
--   ALTER TABLE discrepancy_cases DROP COLUMN manager_decision;
--   ALTER TABLE discrepancy_cases DROP COLUMN manager_decision_by;
--   ALTER TABLE discrepancy_cases DROP COLUMN manager_decision_at;
--   ALTER TABLE discrepancy_cases DROP COLUMN manager_decision_reason;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `findings_submitted_by` VARCHAR(100) NULL;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `findings_submitted_at` DATETIME NULL;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `manager_decision` VARCHAR(20) NULL;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `manager_decision_by` VARCHAR(100) NULL;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `manager_decision_at` DATETIME NULL;

ALTER TABLE `discrepancy_cases`
    ADD COLUMN IF NOT EXISTS `manager_decision_reason` TEXT NULL;
