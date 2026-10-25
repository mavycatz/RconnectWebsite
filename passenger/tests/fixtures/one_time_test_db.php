<?php declare(strict_types=1);

/**
 * Shared isolated-SQLite setup for the One-Time HTTP fixtures.
 *
 * Never opens the application database. Provides the SQL translation layer,
 * the schema, the reference-derived fare/route stops, location seeding, and
 * injection into App\Core\Database so the real production code runs against
 * the fixture database unchanged.
 */

if (defined('ONE_TIME_TEST_DB_LOADED')) {
    return;
}
define('ONE_TIME_TEST_DB_LOADED', true);

/**
 * Translates the MySQL-only schema introspection and date functions the
 * production code uses into their SQLite equivalents. Everything else is
 * passed through unchanged so the real queries run unmodified.
 */
class OneTimeFixturePDO extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);

        /* MySQL functions the production lookups use that SQLite lacks. */
        @$this->sqliteCreateFunction('concat', function (...$parts) {
            return implode('', array_map(static fn ($part) => (string) $part, $parts));
        }, -1);
        @$this->sqliteCreateFunction('left', function ($value, $count) {
            return $value === null ? null : substr((string) $value, 0, (int) $count);
        }, 2);
        @$this->sqliteCreateFunction('substring_index', function ($value, $delimiter, $count) {
            if ($value === null || (string) $delimiter === '') {
                return $value;
            }
            $parts = explode((string) $delimiter, (string) $value);
            $count = (int) $count;
            if ($count > 0) {
                return implode((string) $delimiter, array_slice($parts, 0, $count));
            }
            if ($count < 0) {
                return implode((string) $delimiter, array_slice($parts, $count));
            }
            return $value;
        }, 3);
    }

    public static function translate(string $sql): string
    {
        $sql = preg_replace(
            '/DATE_SUB\(\s*CURDATE\(\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
            "datetime('now', '-$1 day')",
            $sql
        );
        $sql = preg_replace(
            '/DATE_ADD\(\s*CURDATE\(\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
            "datetime('now', '+$1 day')",
            $sql
        );
        $sql = preg_replace('/CURDATE\(\)/i', "date('now')", $sql);
        $sql = preg_replace('/NOW\(\)/i', "datetime('now')", $sql);
        $sql = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);

        if (preg_match('/ENGINE\s*=\s*\w+|AUTO_INCREMENT|\bUNIQUE\s+KEY\s+\w+\s*\(|,\s*(?:INDEX|KEY)\s+`?\w+`?\s*\(/i', $sql)) {
            $sql = self::normalizeMysqlDdl($sql);
        }

        return $sql;
    }

    /**
     * MySQL-only DDL constructs the account migration uses, translated to
     * their SQLite equivalents. Test infrastructure only; the production code
     * still executes the real 006 statements.
     */
    private static function normalizeMysqlDdl(string $sql): string
    {
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+(\s+DEFAULT\s+CHARSET\s*=\s*\w+)?/i', '', $sql);
        $sql = preg_replace('/\bBIGINT\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/,\s*(?:UNIQUE\s+KEY|UNIQUE\s+INDEX)\s+`?\w+`?\s*\(([^)]*)\)/i', ', UNIQUE($1)', $sql);
        $sql = preg_replace('/,\s*(?:INDEX|KEY)\s+`?\w+`?\s*\(([^)]*)\)/i', '', $sql);

        return $sql;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $trimmed = trim($query);

        if (preg_match('/^SHOW COLUMNS FROM `?(\w+)`? LIKE (.+)$/i', $trimmed, $m)) {
            $query = "SELECT name AS Field FROM pragma_table_info('" . $m[1] . "') WHERE name = " . $m[2];
        } elseif (preg_match('/^SHOW COLUMNS FROM `?(\w+)`?$/i', $trimmed, $m)) {
            $query = "SELECT name AS Field FROM pragma_table_info('" . $m[1] . "')";
        } elseif (preg_match('/^SHOW INDEX FROM `?(\w+)`? WHERE Key_name = (.+)$/i', $trimmed, $m)) {
            $query = "SELECT name FROM sqlite_master WHERE type='index' AND name = " . $m[2];
        }

        $query = self::translate($query);

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = self::translate($query);

        /* Native (non-emulated) prepared statements — the MySQL default — reject
           a named placeholder reused within one statement. Mirror that here so
           tests fail on the production failure mode instead of tolerating it. */
        if (preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $query, $names)) {
            foreach (array_count_values($names[1]) as $count) {
                if ($count > 1) {
                    throw new PDOException('SQLSTATE[HY093]: Invalid parameter number');
                }
            }
        }

        return parent::prepare($query, $options);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }
}

if (!function_exists('one_time_test_db')) {
    /**
     * Opens (or creates and seeds) the fixture database and registers it as
     * the app's shared connection.
     */
    function one_time_test_db(): PDO
    {
        $db = new OneTimeFixturePDO('sqlite:' . getenv('ONE_TIME_TEST_DB'));
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        (new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);

        if ($db->query("SELECT name FROM sqlite_master WHERE name='buses'")->fetchColumn()) {
            return $db;
        }

        $db->exec("
            CREATE TABLE buses (id INTEGER PRIMARY KEY, bus_id TEXT, plate_number TEXT, seats INT,
                standing_capacity INT, route_name TEXT, status TEXT, operational_status TEXT);
            INSERT INTO buses VALUES
                (1,'Bus-12','ABC-123',45,10,'Cabanatuan-Rizal','Active','Available'),
                (2,'Bus-13','DEF-456',45,10,'Rizal-Cabanatuan','Active','Available'),
                (3,'Bus-99','XYZ-999',45,10,'Cabanatuan-Rizal','Active','Available'),
                (4,'Bus-14','JKL-789',45,10,'Route X','Active','Available');

            CREATE TABLE drivers (driver_id TEXT PRIMARY KEY, full_name TEXT);
            INSERT INTO drivers VALUES ('DRV-1','Driver One'), ('DRV-2','Driver Two'), ('DRV-3','Driver Three');

            CREATE TABLE bus_trips (id INTEGER PRIMARY KEY, bus_id TEXT, driver_id TEXT, route_name TEXT,
                from_terminal_id INT, to_terminal_id INT, trip_date TEXT, trip_number TEXT,
                departure_time TEXT, status TEXT);
            INSERT INTO bus_trips VALUES
                (77,'Bus-12','DRV-1','Cabanatuan-Rizal',0,0,date('now'),'T-77','06:00:00','Departed'),
                (78,'Bus-13','DRV-2','Rizal-Cabanatuan',0,0,date('now'),'T-78','06:30:00','Departed'),
                (79,'Bus-14','DRV-3','Route X',0,0,date('now'),'T-79','07:00:00','Departed');

            CREATE TABLE passengers (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT, discount_type TEXT,
                discount_status TEXT, type TEXT DEFAULT 'passenger', space_occupied INT DEFAULT 1, registration_type TEXT DEFAULT 'regular',
                contact_number TEXT, declared_id_number TEXT, declared_id_status TEXT, latitude REAL, longitude REAL,
                passenger_code TEXT, personal_qr_seen INT DEFAULT 0,
                email TEXT, phone TEXT, password TEXT,
                student_id TEXT, discount_id TEXT, date_of_birth TEXT, privacy_accepted_at TEXT, verified_at TEXT,
                status TEXT DEFAULT 'Active', account_status TEXT DEFAULT 'ACTIVE',
                suspension_starts_at TEXT, suspension_ends_at TEXT, status_reason TEXT,
                login_locked_until TEXT, restriction_remarks TEXT,
                status_updated_at TEXT, status_updated_by INT,
                failed_login_attempts INT DEFAULT 0, blocked_until TEXT);

            CREATE TABLE passenger_id_sequences (prefix TEXT PRIMARY KEY, last_number INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE passenger_code_history (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_code TEXT,
                passenger_id INT, classification TEXT, assigned_at TEXT, retired_at TEXT);
            CREATE TABLE passenger_account_events (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
                action TEXT, old_status TEXT, new_status TEXT, reason TEXT, remarks TEXT, starts_at TEXT,
                ends_at TEXT, actor_id INT, actor_name TEXT, actor_role TEXT, created_at TEXT);
            CREATE TABLE passenger_account_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
                event_id INT, message TEXT, created_at TEXT, read_at TEXT);
            CREATE TABLE passenger_account_appeals (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
                event_id INT, message TEXT, status TEXT DEFAULT 'PENDING', review_note TEXT, reviewed_by INT,
                reviewed_at TEXT, created_at TEXT);
            CREATE TABLE passenger_identity_claims (identity_kind TEXT, identity_value TEXT, passenger_id INT,
                PRIMARY KEY (identity_kind, identity_value));
            CREATE TABLE passenger_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
                session_hash TEXT, device_label TEXT, ip_address TEXT, created_at TEXT, last_seen_at TEXT,
                revoked_at TEXT, revoked_reason TEXT);

            CREATE TABLE passenger_remember_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
                selector TEXT, token_hash TEXT, expires TEXT, device_label TEXT, user_agent TEXT,
                created_at TEXT, last_used_at TEXT, rotated_at TEXT, revoked_at TEXT);
            CREATE UNIQUE INDEX uq_passenger_remember_selector ON passenger_remember_tokens(selector);

            CREATE TABLE passenger_rate_limits (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket_key TEXT,
                action TEXT, hits INT, window_started_at TEXT, locked_until TEXT);

            CREATE TABLE audit_trail (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, username TEXT,
                user_role TEXT, module TEXT, action TEXT, record_id TEXT, description TEXT,
                ip_address TEXT, user_agent TEXT, status TEXT);

            CREATE TABLE passenger_location_history (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_trip_id INT,
                first_latitude REAL, first_longitude REAL, first_recorded_at TEXT, latitude REAL, longitude REAL,
                accuracy REAL, recorded_at TEXT);

            CREATE TABLE passenger_companions (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_passenger_id INT,
                passenger_id INT, full_name TEXT, companion_type TEXT, declared_id_number TEXT,
                declared_id_status TEXT, created_at TEXT, UNIQUE(owner_passenger_id, full_name));

            CREATE TABLE passenger_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT, bus_no TEXT,
                trip_no INT, driver_id TEXT, passenger_type TEXT, boarding_point TEXT, boarding_stop_id INT,
                boarding_km_marker INT, destination TEXT, destination_id INT, selected_destination TEXT,
                selected_destination_id INT, selected_km_marker INT, estimated_fare REAL, fare REAL, base_fare REAL,
                discount_amount REAL, payment_method TEXT, payment_status TEXT, trip_status TEXT, latitude REAL,
                longitude REAL, boarding_time TEXT, dropoff_time TEXT, identification_source TEXT,
                archived_status TEXT DEFAULT 'no',
                declared_id_number TEXT, declared_id_status TEXT, submission_token TEXT UNIQUE, companion_of INT,
                companion_profile_id INT, cash_amount REAL, gcash_amount REAL,
                paymongo_intent_id TEXT, paymongo_payment_id TEXT, paymongo_qr_expires_at TEXT, paymongo_qr_image TEXT,
                planned_dropoff_lat REAL, planned_dropoff_lng REAL, planned_dropoff_name TEXT, planned_dropoff_set_at TEXT,
                destination_near_alerted INT DEFAULT 0, destination_arrival_alerted INT DEFAULT 0,
                destination_passed_alerted INT DEFAULT 0, destination_passed_at TEXT,
                destination_last_distance_m REAL, destination_distance_trend INT DEFAULT 0,
                continuation_of_trip_id INT, continuation_token TEXT, carried_balance REAL, paid_amount REAL,
                scan_latitude REAL, scan_longitude REAL, scan_recorded_at TEXT, scan_stop_id INT, scan_location_source TEXT);
            CREATE UNIQUE INDEX uq_passenger_trips_continuation_token ON passenger_trips(continuation_token);
            CREATE UNIQUE INDEX uq_passenger_trips_continuation_of ON passenger_trips(continuation_of_trip_id);

            CREATE TABLE driver_location_history (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT,
                bus_id TEXT, latitude REAL, longitude REAL, speed REAL, created_at TEXT);
            CREATE TABLE driver_current_location (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT,
                bus_id TEXT, latitude REAL, longitude REAL, speed REAL, created_at TEXT, updated_at TEXT);
            CREATE TABLE simulation_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, driver_id TEXT,
                trip_id INTEGER, route_id INTEGER, status TEXT, current_lat REAL, current_lng REAL,
                speed_kmh REAL, progress_pct REAL, updated_at TEXT, last_tick_at TEXT, created_at TEXT);

            CREATE TABLE fare_pricing (id INTEGER PRIMARY KEY, route_name TEXT, destination TEXT, display_order INT,
                regular_fare REAL, student_fare REAL, special_fare REAL);
            CREATE TABLE route_stops (id INTEGER PRIMARY KEY, route_name TEXT, stop_name TEXT, sequence_order INT,
                km_marker INT, fare_pricing_id INT, latitude REAL, longitude REAL);
        ");

        // Fare stops and route stops built from the same centralized reference
        // the production matcher consumes, so fixture names/coordinates cannot drift.
        require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/DestinationRouteGeometry.php';

        $directions = [
            'cabanatuan-rizal' => ['Cabanatuan-Rizal', 'Cabanatuan to Rizal Route', 100, 1000],
            'rizal-cabanatuan' => ['Rizal-Cabanatuan', 'Rizal to Cabanatuan Route', 200, 2000],
        ];

        $db->exec("INSERT INTO fare_pricing (id, route_name, destination, display_order, regular_fare, student_fare, special_fare) VALUES (90, 'Route X', 'Route X Stop', 1, 10.0, 8.0, 7.0)");

        foreach ($directions as $directionKey => [$fareRoute, $stopsRoute, $fareBase, $stopBase]) {
            $fare = $db->prepare("INSERT INTO fare_pricing (id, route_name, destination, display_order, regular_fare, student_fare, special_fare) VALUES (?,?,?,?,?,?,?)");
            $stop = $db->prepare("INSERT INTO route_stops (id, route_name, stop_name, sequence_order, km_marker, fare_pricing_id, latitude, longitude) VALUES (?,?,?,?,?,?,?,?)");

            foreach (PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey] as $index => $entry) {
                $fareId = $fareBase + $index;
                $regular = 20.0 + ($index * 2.0);
                $fare->execute([$fareId, $fareRoute, $entry['name'], $index + 1, $regular, round($regular * 0.8, 2), round($regular * 0.7, 2)]);

                $coords = $entry['point'] ?? $entry['start'];
                $stop->execute([$stopBase + $index, $stopsRoute, $entry['name'], $index + 1, $index, $fareId, $coords[0], $coords[1]]);
            }
        }

        return $db;
    }
}

if (!function_exists('one_time_test_seed')) {
    /**
     * Applies a location/simulation seed case for the bus-position tests.
     *
     * @return array{ok:bool,error?:string}
     */
    function one_time_test_seed(PDO $db, array $input): array
    {
        $case = (string) ($input['case'] ?? '');

        $db->exec("DELETE FROM driver_location_history; DELETE FROM driver_current_location; DELETE FROM simulation_sessions;");

        if ($case === 'history') {
            $lat = isset($input['lat']) && is_numeric($input['lat']) ? (float) $input['lat'] : 15.548218;
            $lng = isset($input['lng']) && is_numeric($input['lng']) ? (float) $input['lng'] : 120.933455;
            $age = isset($input['age']) && is_numeric($input['age']) ? (int) $input['age'] : 10;
            $busId = (string) ($input['bus_id'] ?? 'Bus-12');
            $driverId = (string) ($input['driver_id'] ?? 'DRV-1');
            $when = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('-' . $age . ' seconds')->format('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO driver_location_history (driver_id, bus_id, latitude, longitude, speed, created_at) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$driverId, $busId, $lat, $lng, 40.0, $when]);
        } elseif ($case === 'snapshot') {
            $lat = isset($input['lat']) && is_numeric($input['lat']) ? (float) $input['lat'] : 15.548218;
            $lng = isset($input['lng']) && is_numeric($input['lng']) ? (float) $input['lng'] : 120.933455;
            $age = isset($input['age']) && is_numeric($input['age']) ? (int) $input['age'] : 10;
            $busId = (string) ($input['bus_id'] ?? 'Bus-12');
            $driverId = (string) ($input['driver_id'] ?? 'DRV-1');
            $when = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('-' . $age . ' seconds')->format('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, speed, created_at, updated_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$driverId, $busId, $lat, $lng, 40.0, $when, $when]);
        } elseif ($case === 'simulation') {
            $lat = isset($input['lat']) && is_numeric($input['lat']) ? (float) $input['lat'] : 15.566468;
            $lng = isset($input['lng']) && is_numeric($input['lng']) ? (float) $input['lng'] : 120.953142;
            $busId = (string) ($input['bus_id'] ?? 'Bus-12');
            $driverId = (string) ($input['driver_id'] ?? 'DRV-1');
            $tripId = isset($input['trip_id']) && is_numeric($input['trip_id']) ? (int) $input['trip_id'] : 77;
            $status = (string) ($input['status'] ?? 'running');
            $when = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('-2 seconds')->format('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO simulation_sessions (bus_id, driver_id, trip_id, route_id, status, current_lat, current_lng, speed_kmh, progress_pct, updated_at, last_tick_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$busId, $driverId, $tripId, 1, $status, $lat, $lng, 42.0, 25.0, $when, $when, $when]);
        } elseif ($case !== 'clear') {
            return ['ok' => false, 'error' => 'unknown case'];
        }

        return ['ok' => true];
    }
}
