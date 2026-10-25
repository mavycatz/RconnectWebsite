-- R-CONNECT shared GPS route simulation - additive schema.
--
-- Creates the simulation tables and, when upgrading an earlier install, adds the
-- new simulation-only columns. No existing table is altered destructively and no
-- row of an operational table is touched by this script. Run this once on the
-- live database:
--   mysql -u <user> -p <db> < simulation_tables.sql
--
-- `simulation_sessions` holds the one controllable session per bus run,
-- including its generated passenger manifest (waiting/onboard/alighted state).
-- `simulation_stop_events` logs one row per designated-stop arrival.
-- Passengers themselves are written to the REAL `passengers` accounts and
-- `passenger_trips` boarding records (with `SIM-` account ids and
-- `scan_location_source = 'simulation'`), so the driver and inspector apps see
-- the simulated riders while the `SIM-` prefix keeps them apart from real
-- passenger activity.
-- `simulation_inspector_state` holds the one simulated inspector per inspector.

CREATE TABLE IF NOT EXISTS `simulation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_id` varchar(20) NOT NULL,
  `driver_id` varchar(20) DEFAULT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `route_name` varchar(150) DEFAULT NULL,
  `route_direction` varchar(40) NOT NULL,
  `status` enum('idle','running','paused','completed','disabled') NOT NULL DEFAULT 'idle',
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  `current_point_index` int(11) NOT NULL DEFAULT 0,
  `progress_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `speed_kmh` decimal(6,2) DEFAULT NULL,
  `speed_preset` enum('slow','normal','fast','custom') NOT NULL DEFAULT 'normal',
  `speed_multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
  `dwell_ms` int(11) NOT NULL DEFAULT 0,
  `passenger_demand` varchar(10) NOT NULL DEFAULT 'normal',
  `passenger_capacity` int(11) NOT NULL DEFAULT 0,
  `passenger_manifest` mediumtext DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `paused_ms` bigint(20) NOT NULL DEFAULT 0,
  `last_tick_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_simulation_sessions_bus` (`bus_id`),
  KEY `idx_simulation_sessions_driver` (`driver_id`),
  KEY `idx_simulation_sessions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `simulation_stop_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `stop_name` varchar(255) NOT NULL,
  `stop_sequence` int(11) NOT NULL,
  `stop_lat` decimal(10,8) NOT NULL,
  `stop_lng` decimal(11,8) NOT NULL,
  `arrived_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_simulation_stop_events_session` (`session_id`),
  UNIQUE KEY `uniq_simulation_stop_event` (`session_id`, `stop_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `simulation_inspector_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspector_id` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `onboard_bus_id` varchar(20) DEFAULT NULL,
  `onboard_session_id` int(11) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_simulation_inspector` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Upgrades for installs created before this change.
--
-- `CREATE TABLE IF NOT EXISTS` does not add columns to an existing table, so the
-- new session columns are added idempotently in the house style of
-- scripts/sql/add_*.sql (information_schema guard + prepared statement).
-- ---------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'speed_preset') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `speed_preset` enum(''slow'',''normal'',''fast'',''custom'') NOT NULL DEFAULT ''normal'' COMMENT ''Selectable simulation speed tier.''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'speed_multiplier') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `speed_multiplier` decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT ''Multiplier for the custom speed tier; other tiers use their preset multiplier.''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'dwell_ms') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `dwell_ms` int(11) NOT NULL DEFAULT 0 COMMENT ''Stop dwell time in milliseconds; 0 means use the configured default.''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'passenger_demand') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_demand` varchar(10) NOT NULL DEFAULT ''normal'' COMMENT ''Simulated waiting-passenger demand: off, low, normal, high.''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'passenger_capacity') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_capacity` int(11) NOT NULL DEFAULT 0 COMMENT ''Seat capacity used by the passenger simulation; 0 means resolve from the bus record.''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulation_sessions'
       AND COLUMN_NAME = 'passenger_manifest') = 0,
  'ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_manifest` mediumtext NULL COMMENT ''JSON manifest of the simulated journeys (waiting/onboard/alighted).''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Verification queries
-- ---------------------------------------------------------------------------
--
-- Confirm the additive objects exist and no existing table was altered:
--   SHOW TABLES LIKE 'simulation%';
--   DESCRIBE simulation_sessions;        -- expect speed_preset, speed_multiplier,
--                                        --        dwell_ms, passenger_demand,
--                                        --        passenger_capacity, passenger_manifest
--   DESCRIBE simulation_inspector_state;
--
-- Confirm the operational tables the simulation READS are untouched:
--   DESCRIBE route_stops;                -- published per-stop path + geofence columns
--   DESCRIBE fare_pricing;               -- the fare matrix the simulation reads
--   DESCRIBE driver_current_location;
--   DESCRIBE driver_location_history;
--   DESCRIBE inspectors_location;
--
-- Simulated boardings are written to the real `passenger_trips` table with
-- `SIM-` passenger accounts and `scan_location_source = 'simulation'`; find
-- them with:
--   SELECT * FROM passenger_trips WHERE passenger_id LIKE 'SIM-%';
--   SELECT * FROM passengers WHERE id LIKE 'SIM-%';
