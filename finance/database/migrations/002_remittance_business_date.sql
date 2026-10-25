-- R-Connect Remittance Reconciliation
-- Adds the business date that a remittance settles, so overdue and late
-- processing can preserve the original service day (Asia/Manila).
--
-- The application also applies this column automatically at runtime
-- (Finance\Models\RemittanceModel::ensureBusinessDateColumn); this file
-- documents the change for deployers and can be applied manually.
--
-- Rollback: the additive column can remain harmlessly. If a full data
-- rollback is required, remove Overdue rows created by the closing job:
--   DELETE FROM remittance_logs WHERE LOWER(TRIM(status)) = 'overdue';
--   ALTER TABLE remittance_logs DROP COLUMN business_date;

ALTER TABLE `remittance_logs`
    ADD COLUMN IF NOT EXISTS `business_date` DATE NULL AFTER `driver_id`;


-- ---------------------------------------------------------------------------
-- Duplicate audit (run BEFORE adding the optional unique index)
-- ---------------------------------------------------------------------------
-- Expected: zero rows. Each row is a driver + service day that already has
-- more than one remittance log; those legacy duplicates must be reviewed
-- before the index can be enforced.

-- SELECT driver_id,
--        COALESCE(business_date, DATE(created_at)) AS business_day,
--        COUNT(*) AS records
-- FROM remittance_logs
-- GROUP BY driver_id, COALESCE(business_date, DATE(created_at))
-- HAVING records > 1;


-- ---------------------------------------------------------------------------
-- Optional uniqueness (apply ONLY when the audit above returns no rows)
-- ---------------------------------------------------------------------------
-- The application already upserts by (driver_id, business_date); this index
-- makes the guarantee structural. MariaDB/MySQL unique indexes allow multiple
-- NULL business_date rows, so legacy rows without the column are unaffected.

-- ALTER TABLE `remittance_logs`
--     ADD UNIQUE INDEX `uq_remittance_driver_business` (`driver_id`, `business_date`);
