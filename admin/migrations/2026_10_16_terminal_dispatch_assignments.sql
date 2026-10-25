-- Terminal-based dispatch queue: per-operating-date terminal assignments.
--
-- The Admin "Bus Dispatch Queue" page assigns each eligible bus and driver to
-- a terminal for an operating date. The bus assignment is stored on the queue
-- entry itself (`bus_dispatch_queue.terminal_id`), so assignment and queue
-- entry stay one record; driver assignments are independent and live in
-- `driver_terminal_assignments`.
--
-- Runtime application: Admin\Services\Dispatch\DispatchQueueService::ensureSchema()
-- applies the same additive changes lazily (attempting ADD COLUMN IF NOT EXISTS
-- first and falling back to a plain ADD COLUMN). This file is idempotent so it
-- can be re-run safely: the column add is guarded by information_schema.
--
-- This migration never touches bus_trips or any completed dispatch history.

SET @terminal_col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bus_dispatch_queue'
      AND COLUMN_NAME = 'terminal_id'
);
SET @terminal_col_sql := IF(
    @terminal_col_exists = 0,
    'ALTER TABLE bus_dispatch_queue ADD COLUMN terminal_id INT NULL',
    'SELECT ''bus_dispatch_queue.terminal_id already exists'' AS note'
);
PREPARE terminal_col_stmt FROM @terminal_col_sql;
EXECUTE terminal_col_stmt;
DEALLOCATE PREPARE terminal_col_stmt;

CREATE TABLE IF NOT EXISTS driver_terminal_assignments (
    operating_date DATE NOT NULL,
    driver_id VARCHAR(20) NOT NULL,
    terminal_id INT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (operating_date, driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
