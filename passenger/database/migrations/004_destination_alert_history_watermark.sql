-- Destination alert: remember how much driver GPS history has been processed.
--
-- OPTIONAL. The destination alert works without this column: the evidence is
-- derived cumulatively from the whole trip trail, and the existing
-- destination_near_alerted / destination_arrival_alerted /
-- destination_passed_alerted flags already guarantee each alert fires once per
-- trip. Adding this column just makes the progress explicit and monotonic, and
-- lets the API report processed_history_id across requests.
--
-- The code reads and writes it inside try/catch. On a database where the column
-- is absent, history_watermark_persisted is false and everything else is
-- unchanged. Nothing breaks by skipping this file.
--
-- Apply with:
--   mysql -h <host> -u <user> -p <database> < 004_destination_alert_history_watermark.sql
--
-- Idempotent: safe to run more than once. It checks information_schema first
-- rather than relying on ADD COLUMN IF NOT EXISTS, which only some MySQL
-- builds accept.

SET @schema_name = DATABASE();

SELECT COUNT(*) AS column_already_present
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME   = 'passenger_trips'
  AND COLUMN_NAME  = 'destination_alert_processed_history_id';

-- Runs only when the column is missing, so a re-run is a no-op.
SET @ddl = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME   = 'passenger_trips'
          AND COLUMN_NAME  = 'destination_alert_processed_history_id'
    ),
    'SELECT ''destination_alert_processed_history_id already present, nothing to do'' AS result',
    'ALTER TABLE passenger_trips
        ADD COLUMN destination_alert_processed_history_id BIGINT UNSIGNED NULL
        COMMENT ''Highest driver_location_history.id already analysed for this trip'''
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Confirm the result.
SHOW COLUMNS FROM passenger_trips LIKE 'destination_alert_processed_history_id';
