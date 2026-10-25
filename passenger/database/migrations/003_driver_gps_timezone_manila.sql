-- Driver GPS timestamps: one time basis, Asia/Manila (UTC+8).
--
-- Audit only. This script REPORTS. It does not write, and there is deliberately
-- no backfill in it. See "WHY THERE IS NO BACKFILL" below.
--
-- WHY
-- The driver writer and the passenger reader each had their own idea of what
-- "now" meant, and the two were never pinned to anything:
--
--   mobile/config/database.php   pinned the writer session to +08:00
--   app/Core/Database.php        set no session time_zone, so the reader
--                                 inherited the server global, which is SYSTEM
--                                 = whatever the host OS is set to, often UTC
--
-- The stored bytes are identical in both cases. Only the reader's clock
-- differed, so a live fix written at 09:38:34 was differenced against
-- NOW() = 01:38:34:
--
--   created_at 2026-09-29 09:38:34   (Philippine, written by the driver app)
--   NOW()      2026-09-29 01:38:34   (UTC, reader's session)
--   age_seconds  -> -28800           (8 hours in the FUTURE, not 8 hours old)
--
-- Note the sign. The row reads as eight hours AHEAD of the reader's clock, not
-- behind it, which is why it never looked like an ordinary stale fix. A
-- materially future-dated row is reported as clock_skew and treated as STALE,
-- so live buses were dropped from passenger alerts as blocked_stale_location.
--
-- That is a read-side misreading. The rows on disk are correct. The fix pins
-- both ends to Asia/Manila and has the writer store the value explicitly
-- rather than trusting a column default or a session clock.
--
-- Apply with:
--   mysql -h <host> -u <user> -p <database> < 003_driver_gps_timezone_manila.sql
--
-- Run the session on the app's basis so NOW() here means Philippine time:
--   mysql ... --init-command="SET time_zone='+08:00'" < ...


-- ==========================================================================
-- STEP 1 - CONFIRM THE SESSION BASIS
-- ==========================================================================
--
-- +08:00 is the expected result. 'Asia/Manila' only works on a MySQL that has
-- had the timezone tables loaded; without them it fails with:
--
--   ERROR 1298 (HY000): Unknown or incorrect time zone: 'Asia/Manila'
--
-- The application treats that as expected and uses the numeric offset, which is
-- exactly equivalent: the Philippines is a fixed UTC+8 with no daylight saving.

SELECT
    @@global.time_zone      AS global_tz,
    @@session.time_zone     AS session_tz,
    @@system_time_zone      AS system_tz,
    NOW()                   AS session_now,
    UTC_TIMESTAMP()         AS session_utc,
    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS session_offset_seconds;


-- ==========================================================================
-- STEP 2 - INVENTORY
-- ==========================================================================
--
-- The distribution of stored timestamps, to see whether the data is contiguous
-- and plausible. Gaps or clusters near the deploy time are the thing to look
-- for, not anything in particular.

SELECT
    COUNT(*)                        AS total_rows,
    MIN(id)                         AS oldest_id,
    MAX(id)                         AS newest_id,
    MIN(created_at)                 AS oldest_stored,
    MAX(created_at)                 AS newest_stored,
    TIMESTAMPDIFF(HOUR, MIN(created_at), MAX(created_at)) AS span_hours
FROM driver_location_history;

SELECT
    driver_id,
    COUNT(*)            AS rows_written,
    MAX(created_at)     AS newest_stored,
    TIMESTAMPDIFF(MINUTE, NOW(), MAX(created_at)) AS minutes_vs_server_now
FROM driver_location_history
GROUP BY driver_id
ORDER BY minutes_vs_server_now ASC
LIMIT 50;


-- ==========================================================================
-- STEP 3 - CLASSIFY
-- ==========================================================================
--
-- Read this against a session already pinned to +08:00.
--
-- On the correct basis a live driver's newest row is FRESH (0-30s) or AGING
-- (31-120s). Only the single most recent row per driver can be fresh, because
-- the app only reads the newest fix; everything older is genuinely_old by
-- design and is not a defect.
--
-- future_dated is the bucket that reproduces the original symptom. A row
-- materially ahead of the server clock cannot be a correct Philippine
-- timestamp, and it is exactly what the buggy reader produced.

SELECT
    id,
    driver_id,
    created_at,
    NOW()                                    AS session_now,
    TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds,
    CASE
        WHEN created_at IS NULL              THEN 'missing'
        WHEN created_at > NOW()              THEN 'future_dated'
        WHEN TIMESTAMPDIFF(SECOND, created_at, NOW()) <= 30   THEN 'fresh'
        WHEN TIMESTAMPDIFF(SECOND, created_at, NOW()) <= 120  THEN 'aging'
        ELSE 'genuinely_old'
    END AS classification
FROM driver_location_history
ORDER BY id DESC
LIMIT 200;

-- The same for the snapshot table.

SELECT
    driver_id,
    bus_id,
    updated_at,
    TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age_seconds,
    CASE
        WHEN updated_at IS NULL             THEN 'missing'
        WHEN updated_at > NOW()             THEN 'future_dated'
        WHEN TIMESTAMPDIFF(SECOND, updated_at, NOW()) <= 30   THEN 'fresh'
        WHEN TIMESTAMPDIFF(SECOND, updated_at, NOW()) <= 120  THEN 'aging'
        ELSE 'genuinely_old'
    END AS classification
FROM driver_current_location;

-- Only the newest fix per driver is ever read, so only that one can be live.
-- This is the set the passenger API actually consumes.

SELECT h.id, h.driver_id, h.created_at,
       TIMESTAMPDIFF(SECOND, h.created_at, NOW()) AS age_seconds
FROM driver_location_history h
JOIN (
    SELECT driver_id, MAX(id) AS newest_id
    FROM driver_location_history
    GROUP BY driver_id
) newest ON newest.newest_id = h.id
ORDER BY h.driver_id;


-- ==========================================================================
-- WHY THERE IS NO BACKFILL
-- ==========================================================================
--
-- An earlier draft of this file carried an UPDATE that added 8 hours to every
-- row stamped ahead of the clock. It was wrong in both directions and has been
-- removed rather than left for someone to run.
--
-- It could not fix the rows it was meant to fix. A row genuinely stamped on a
-- UTC session reads as 8 hours in the PAST once the session is correctly
-- pinned to +08:00, so "created_at > NOW()" never matched it. It was already
-- classified genuinely_old, indistinguishable from a row that is simply an
-- hour old.
--
-- It corrupted the rows it did match. "created_at > NOW()" does match a row
-- written by a device with a wrong clock, and that is the one case that must
-- never be shifted: 10:30:00 becomes 18:30:00, a plausible-looking timestamp
-- that is wrong and now sits close enough to a real fix to be used.
--
-- The arithmetic, with a server clock of 09:38:34 Manila:
--
--   01:38:34   legacy UTC-stamped    ahead? NO    bucket genuinely_old
--   09:38:34   correct Philippine    ahead? NO    bucket fresh
--   10:30:00   bad device clock      ahead? YES   bucket future_dated
--                                                  -> would become 18:30:00
--
-- Distinguishing the first from an ordinary old row needs a reference outside
-- the database, such as a driver-reported device timestamp or a deployment
-- log. If any rows are in fact UTC-stamped, identify them that way and correct
-- them individually. Do not do it in bulk with a timestamp comparison, because
-- no timestamp comparison can tell those rows apart from old ones.
--
-- Note also that the original symptom was a read-side misreading, not corrupt
-- storage, and the code fix removes it without touching any row.


-- ==========================================================================
-- STEP 4 - POST-DEPLOY CHECK
-- ==========================================================================
--
-- After deploying the code fix, a fresh driver update should immediately show
-- up here as fresh on a +08:00 session, with the same value the driver app
-- reported. This is the only step that requires new data.

-- SELECT id, driver_id, created_at, NOW() AS session_now,
--        TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
-- FROM driver_location_history
-- ORDER BY id DESC
-- LIMIT 5;

-- Expected: age_seconds between 0 and a few seconds, and created_at equal to
-- the server's own Philippine wall-clock time.


-- ==========================================================================
-- THE CODE FIX THIS AUDIT ACCOMPANIES
-- ==========================================================================
--
--   app/config/database.php            APP_TIMEZONE = 'Asia/Manila'
--                                      APP_UTC_OFFSET = '+08:00'
--   app/Core/Database.php              reader session pinned to the same basis
--   app/Core/Bootstrap.php             PHP default timezone set to match
--   mobile/config/database.php         writer session pinned to +08:00
--   mobile/driver/lib/location_helpers.php  update_location_now() on the
--                                           same basis
--   mobile/driver/updateLocation.php   created_at and updated_at written
--                                       explicitly, not left to a default
--   passenger/services/DriverLocationService.php
--                                      single basis for the age calculation,
--                                      with the 5s skew tolerance
--
-- The stale window is unchanged at 120 s. No threshold was altered and stale
-- protection still blocks; a fresh fix now simply stops being misread as one
-- that is eight hours off.
