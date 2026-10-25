-- Official admin dispatch queue: the ordered bus list for an operating date.
--
-- The Admin "Bus Assignment & Dispatch Queue" page arranges this order and
-- publishes it; the dispatcher queue then follows the published order
-- (dispatcher_queue_sort_buses reads published rows for the operating date).
--
-- Runtime application: Admin\Services\Dispatch\DispatchQueueService::ensureSchema()
-- applies the same idempotent CREATE TABLE IF NOT EXISTS lazily.
--
-- Reordering only ever touches this table - it never creates bus_trips rows
-- or modifies trip history.

CREATE TABLE IF NOT EXISTS bus_dispatch_queue (
    operating_date DATE NOT NULL,
    bus_id VARCHAR(20) NOT NULL,
    queue_position INT NOT NULL,
    published_at DATETIME NULL,
    published_by VARCHAR(120) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (operating_date, bus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
