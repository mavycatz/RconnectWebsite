-- Two-step arrival lifecycle: report markers on bus_trips.
--
-- A reported arrival (receiving-terminal staff from the dispatcher app, or the
-- driver app on GPS proximity / end-trip) records when, from which source, and
-- by whom the arrival was reported, while the trip stays in status 'Departed'.
-- Only receiving-terminal staff confirmation completes the trip and stamps
-- arrival_confirmed_at / arrival_confirmed_by. No status enum change is needed,
-- so no existing status query or driver-app lookup changes behavior.
--
-- Runtime application: dispatcher_ensure_arrival_schema() in
-- dispatcher/api/helpers.php applies the same additive columns lazily. This
-- file is idempotent (guarded by information_schema) and can be re-run safely.
--
-- Additive only: this migration never rewrites or removes bus_trips history.

SET @arrival_reported_at_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_trips'
      AND COLUMN_NAME = 'arrival_reported_at'
);
SET @arrival_reported_at_sql := IF(
    @arrival_reported_at_exists = 0,
    'ALTER TABLE bus_trips ADD COLUMN arrival_reported_at DATETIME NULL',
    'SELECT ''bus_trips.arrival_reported_at already exists'' AS note'
);
PREPARE arrival_reported_at_stmt FROM @arrival_reported_at_sql;
EXECUTE arrival_reported_at_stmt;
DEALLOCATE PREPARE arrival_reported_at_stmt;

SET @arrival_report_source_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_trips'
      AND COLUMN_NAME = 'arrival_report_source'
);
SET @arrival_report_source_sql := IF(
    @arrival_report_source_exists = 0,
    'ALTER TABLE bus_trips ADD COLUMN arrival_report_source VARCHAR(32) NULL',
    'SELECT ''bus_trips.arrival_report_source already exists'' AS note'
);
PREPARE arrival_report_source_stmt FROM @arrival_report_source_sql;
EXECUTE arrival_report_source_stmt;
DEALLOCATE PREPARE arrival_report_source_stmt;

SET @arrival_reported_by_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_trips'
      AND COLUMN_NAME = 'arrival_reported_by'
);
SET @arrival_reported_by_sql := IF(
    @arrival_reported_by_exists = 0,
    'ALTER TABLE bus_trips ADD COLUMN arrival_reported_by VARCHAR(64) NULL',
    'SELECT ''bus_trips.arrival_reported_by already exists'' AS note'
);
PREPARE arrival_reported_by_stmt FROM @arrival_reported_by_sql;
EXECUTE arrival_reported_by_stmt;
DEALLOCATE PREPARE arrival_reported_by_stmt;

SET @arrival_confirmed_at_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_trips'
      AND COLUMN_NAME = 'arrival_confirmed_at'
);
SET @arrival_confirmed_at_sql := IF(
    @arrival_confirmed_at_exists = 0,
    'ALTER TABLE bus_trips ADD COLUMN arrival_confirmed_at DATETIME NULL',
    'SELECT ''bus_trips.arrival_confirmed_at already exists'' AS note'
);
PREPARE arrival_confirmed_at_stmt FROM @arrival_confirmed_at_sql;
EXECUTE arrival_confirmed_at_stmt;
DEALLOCATE PREPARE arrival_confirmed_at_stmt;

SET @arrival_confirmed_by_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_trips'
      AND COLUMN_NAME = 'arrival_confirmed_by'
);
SET @arrival_confirmed_by_sql := IF(
    @arrival_confirmed_by_exists = 0,
    'ALTER TABLE bus_trips ADD COLUMN arrival_confirmed_by VARCHAR(64) NULL',
    'SELECT ''bus_trips.arrival_confirmed_by already exists'' AS note'
);
PREPARE arrival_confirmed_by_stmt FROM @arrival_confirmed_by_sql;
EXECUTE arrival_confirmed_by_stmt;
DEALLOCATE PREPARE arrival_confirmed_by_stmt;
