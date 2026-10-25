-- R-Connect Discrepancy Investigation Workflow
-- Adds the resolution columns that the legacy discrepancy page read and wrote
-- behind a runtime information_schema check but that no migration ever
-- created. Additive only: existing remittance rows are untouched.
--
-- The application also applies these columns automatically at runtime
-- (Finance\Models\DiscrepancyCaseModel::ensureResolutionColumns); this file
-- documents the change for deployers and can be applied manually. Both were
-- written to be safe to run repeatedly.
--
-- Rollback: the additive columns can remain harmlessly. To remove them:
--   ALTER TABLE remittance_logs DROP COLUMN resolution_type;
--   ALTER TABLE remittance_logs DROP COLUMN resolution_notes;
--   ALTER TABLE remittance_logs DROP COLUMN resolved_by;
--   ALTER TABLE remittance_logs DROP COLUMN resolved_at;
--   ALTER TABLE remittance_logs DROP COLUMN supporting_document;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `resolution_type` VARCHAR(100) NULL;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `resolution_notes` TEXT NULL;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `resolved_by` VARCHAR(100) NULL;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `resolved_at` DATETIME NULL;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `supporting_document` VARCHAR(255) NULL;
