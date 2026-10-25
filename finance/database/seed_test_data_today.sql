-- =========================================================
-- seed_test_data_today.sql
-- Test data for trying out the remittance receipt + OTP print flow.
--
-- Creates:
--   * Bus BUS-1001 (plate ABC-1001) + Driver DRV-001 (Test Driver Uno)
--   * 2 bus_trips for TODAY (not yet submitted)
--   * 3 test passengers + 8 passenger_trips (6 cash, 2 gcash) TODAY
--   * 3 remittance_logs for history/print testing:
--       R1  Completed   (yesterday, full audit data)
--       R2  Discrepancy (2 days ago, NULL audit fields -> tests N/A fallback)
--       R3  No Remit    (3 days ago)
--
-- Notes:
--   * Run via phpMyAdmin / MySQL CLI on the server once.
--   * Safe to re-run: deletes the rows it creates before inserting.
--   * "Today" is computed as Asia/Manila time so it works even if the
--     MySQL server runs on UTC.
--   * After running: finance/remittance.php shows DRV-001 as Pending
--     (submit with finance code, default 123456), finance/history.php
--     shows the 3 seeded logs to view/print (OTP sent to the logged-in
--     finance user's email).
-- =========================================================

SET @today := DATE(NOW() + INTERVAL 8 HOUR);

-- ---------------------------------------------------------
-- MIGRATION (idempotent; same as migrations/001_remittance_receipt_otp.sql)
-- ---------------------------------------------------------
ALTER TABLE remittance_logs
    ADD COLUMN IF NOT EXISTS submitted_by VARCHAR(100) NULL AFTER confirmed_by,
    ADD COLUMN IF NOT EXISTS remitted_by VARCHAR(100) NULL AFTER submitted_by,
    ADD COLUMN IF NOT EXISTS reviewed_by VARCHAR(100) NULL AFTER remitted_by,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

ALTER TABLE passenger_trips
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

CREATE TABLE IF NOT EXISTS receipt_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    remittance_id INT NOT NULL,
    otp_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_receipt_otp_user (user_id),
    INDEX idx_receipt_otp_remittance (remittance_id),
    INDEX idx_receipt_otp_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- CLEANUP (only the rows this script creates, so re-runs are safe)
-- ---------------------------------------------------------
DELETE FROM passenger_trips
 WHERE bus_no = 'BUS-1001' AND driver_id = 'DRV-001' AND DATE(boarding_time) = @today;

DELETE FROM passengers
 WHERE phone IN ('09170000011', '09170000012', '09170000013');

DELETE FROM bus_trips
 WHERE bus_id = 'BUS-1001' AND driver_id = 'DRV-001' AND trip_date = @today;

DELETE FROM remittance_logs
 WHERE driver_id = 'DRV-001' AND bus_no = 'BUS-1001'
   AND created_at >= DATE_SUB(@today, INTERVAL 4 DAY);

DELETE FROM driver_bus_assignment WHERE driver_id = 'DRV-001';
DELETE FROM drivers WHERE driver_id = 'DRV-001';
DELETE FROM buses WHERE bus_id = 'BUS-1001';

-- ---------------------------------------------------------
-- 1. BUS + DRIVER + ASSIGNMENT
-- ---------------------------------------------------------
INSERT INTO buses (bus_id, plate_number, bus_type, seats, standing_capacity, status)
VALUES ('BUS-1001', 'ABC-1001', 'Regular', 45, 15, 'Active');

INSERT INTO drivers (driver_id, full_name, license_number, license_type, status, assigned_buses, email, contact_number)
VALUES ('DRV-001', 'Test Driver Uno', 'L-1001-001', 'Professional', 'Active', 'BUS-1001', 'drv001@test.local', '09170000001');

INSERT INTO driver_bus_assignment (driver_id, bus_id)
VALUES ('DRV-001', 'BUS-1001');

-- ---------------------------------------------------------
-- 2. TRIPS FOR TODAY (not submitted, so the flow can be tested)
-- ---------------------------------------------------------
INSERT INTO bus_trips (bus_id, driver_id, route_name, trip_date, trip_number, departure_time, arrival_time, status, submitted)
VALUES ('BUS-1001', 'DRV-001', 'Terminal A - Terminal B', @today, 1, '06:00:00', '08:30:00', 'Completed', 0);

SET @trip1 := LAST_INSERT_ID();

INSERT INTO bus_trips (bus_id, driver_id, route_name, trip_date, trip_number, departure_time, arrival_time, status, submitted)
VALUES ('BUS-1001', 'DRV-001', 'Terminal B - Terminal A', @today, 2, '09:00:00', '11:30:00', 'Completed', 0);

SET @trip2 := LAST_INSERT_ID();

-- ---------------------------------------------------------
-- 3. TEST PASSENGERS
-- ---------------------------------------------------------
INSERT INTO passengers (full_name, phone, status)
VALUES ('Test Passenger Uno', '09170000011', 'Active');

SET @pass1 := LAST_INSERT_ID();

INSERT INTO passengers (full_name, phone, status)
VALUES ('Test Passenger Dos', '09170000012', 'Active');

SET @pass2 := LAST_INSERT_ID();

INSERT INTO passengers (full_name, phone, status)
VALUES ('Test Passenger Tres', '09170000013', 'Active');

SET @pass3 := LAST_INSERT_ID();

-- ---------------------------------------------------------
-- 4. PASSENGER TRIPS TODAY (6 cash, 2 gcash, all paid)
--    Cash total: Trip1 = 370, Trip2 = 430 -> 800 expected cash
-- ---------------------------------------------------------
INSERT INTO passenger_trips
    (passenger_id, bus_no, trip_no, driver_id, boarding_point, destination, fare,
     payment_method, payment_status, cash_amount, gcash_amount, boarding_time)
VALUES
    (@pass1, 'BUS-1001', @trip1, 'DRV-001', 'Terminal A', 'Terminal B', 60.00, 'cash',  'paid', 60.00, 0.00,  CONCAT(@today, ' 06:10:00')),
    (@pass2, 'BUS-1001', @trip1, 'DRV-001', 'Terminal A', 'Terminal B', 60.00, 'cash',  'paid', 60.00, 0.00,  CONCAT(@today, ' 06:15:00')),
    (@pass3, 'BUS-1001', @trip1, 'DRV-001', 'Terminal A', 'Terminal B', 120.00, 'cash', 'paid', 120.00, 0.00,  CONCAT(@today, ' 06:20:00')),
    (@pass1, 'BUS-1001', @trip1, 'DRV-001', 'Terminal A', 'Terminal B', 130.00, 'cash', 'paid', 130.00, 0.00,  CONCAT(@today, ' 06:25:00')),
    (@pass2, 'BUS-1001', @trip1, 'DRV-001', 'Terminal A', 'Terminal B', 90.00,  'gcash', 'paid', 0.00, 90.00,  CONCAT(@today, ' 06:30:00')),
    (@pass3, 'BUS-1001', @trip2, 'DRV-001', 'Terminal B', 'Terminal A', 60.00,  'cash',  'paid', 60.00, 0.00,  CONCAT(@today, ' 09:10:00')),
    (@pass1, 'BUS-1001', @trip2, 'DRV-001', 'Terminal B', 'Terminal A', 120.00, 'cash', 'paid', 120.00, 0.00,  CONCAT(@today, ' 09:20:00')),
    (@pass2, 'BUS-1001', @trip2, 'DRV-001', 'Terminal B', 'Terminal A', 250.00, 'cash', 'paid', 250.00, 0.00,  CONCAT(@today, ' 09:30:00'));

-- ---------------------------------------------------------
-- 5. REMITTANCE LOGS FOR HISTORY / RECEIPT / PRINT TESTING
--    (Deliberately NOT dated today so DRV-001's submit flow stays open)
-- ---------------------------------------------------------

-- R1: Completed, full audit trail (yesterday)
INSERT INTO remittance_logs
    (driver_id, bus_no, total_collection, expected_cash, actual_cash, discrepancy_amount,
     status, finance_code, confirmed_by, submitted_by, remitted_by, reviewed_by, reviewed_at,
     trip_no, total_passengers, created_at)
VALUES
    ('DRV-001', 'BUS-1001', 800.00, 800.00, 800.00, 0.00,
     'Completed', '123456', 'finance.jane', 'finance.jane', 'Test Driver Uno', 'finance.jane',
     CONCAT(DATE_SUB(@today, INTERVAL 1 DAY), ' 14:00:00'),
     @trip1, 5, CONCAT(DATE_SUB(@today, INTERVAL 1 DAY), ' 13:55:00'));

-- R2: Discrepancy, NULL audit fields (2 days ago) -> receipt shows N/A
INSERT INTO remittance_logs
    (driver_id, bus_no, total_collection, expected_cash, actual_cash, discrepancy_amount,
     status, finance_code, confirmed_by, submitted_by, remitted_by, reviewed_by, reviewed_at,
     trip_no, total_passengers, created_at)
VALUES
    ('DRV-001', 'BUS-1001', 800.00, 800.00, 700.00, 100.00,
     'Discrepancy', '123456', 'Finance Manager', NULL, NULL, NULL, NULL,
     @trip1, 5, CONCAT(DATE_SUB(@today, INTERVAL 2 DAY), ' 10:30:00'));

-- R3: No Remit, NULL audit fields (3 days ago)
INSERT INTO remittance_logs
    (driver_id, bus_no, total_collection, expected_cash, actual_cash, discrepancy_amount,
     status, finance_code, confirmed_by, submitted_by, remitted_by, reviewed_by, reviewed_at,
     trip_no, total_passengers, created_at)
VALUES
    ('DRV-001', 'BUS-1001', 0.00, 0.00, 0.00, 0.00,
     'No Remit', '', 'Finance Manager', NULL, NULL, NULL, NULL,
     @trip1, 0, CONCAT(DATE_SUB(@today, INTERVAL 3 DAY), ' 18:00:00'));

-- ---------------------------------------------------------
-- DONE
-- ---------------------------------------------------------
SELECT 'Seed complete' AS status,
       @today AS seed_date,
       @trip1 AS trip_1_id,
       @trip2 AS trip_2_id;
