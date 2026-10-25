<?php
/**
 * Shared security/DB helpers for the Dispatcher API.
 * Replaces direct middleware/auth.php usage in endpoints.
 */
require_once __DIR__ . "/../../mobile/config/database.php";
require_once __DIR__ . "/../utils/token.php";
require_once __DIR__ . "/../utils/i18n.php";

/**
 * Emit a safe JSON error envelope and exit.
 */
function dispatcher_json_error($status, $message)
{
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

global $dispatcher_last_sql_error;
$dispatcher_last_sql_error = null;

/**
 * Prepared-statement wrapper. Logs errors server-side, never crashes on exceptions.
 * Returns the executed mysqli_stmt, or false on failure.
 */
function dispatcher_statement($conn, $sql, $types = "", $params = [])
{
    global $dispatcher_last_sql_error;
    $dispatcher_last_sql_error = null;

    if (!$conn) {
        $dispatcher_last_sql_error = "Database connection is null";
        return false;
    }

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $dispatcher_last_sql_error = $conn->error ?: "Prepare failed";
            error_log("Dispatcher SQL prepare failed: " . $conn->error . " | SQL: " . $sql);
            return false;
        }
        if (!empty($types) && !empty($params)) {
            $bindArgs = [$types];
            foreach ($params as &$p) {
                $bindArgs[] = &$p;
            }
            unset($p);
            call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        }
        if (!$stmt->execute()) {
            $dispatcher_last_sql_error = $stmt->error ?: "Execute failed";
            error_log("Dispatcher SQL execute failed: " . $stmt->error . " | SQL: " . $sql);
            $stmt->close();
            return false;
        }
        return $stmt;
    } catch (\Throwable $e) {
        $dispatcher_last_sql_error = $e->getMessage();
        error_log("Dispatcher SQL exception: " . $e->getMessage() . " | SQL: " . $sql);
        return false;
    }
}

/**
 * Authenticate the request (session OR bearer token) and validate the
 * dispatcher role server-side. Exposes $dispatcher_user and legacy $user.
 * 401 when unauthenticated, 403 when the account is not an active dispatcher.
 */
function dispatcher_require_auth()
{
    global $dispatcher_user, $user;
    dispatcher_init_session();

    $authId = null;
    $authSource = null;
    if (!empty($_SESSION['inspector_id'])) {
        $authId = $_SESSION['inspector_id'];
        $authSource = 'session';
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader)) {
            dispatcher_json_error(401, "Authorization required");
        }
        $token = str_replace("Bearer ", "", $authHeader);
        $payload = verifyToken($token);
        if (!$payload || empty($payload['id'])) {
            dispatcher_json_error(401, "Invalid or expired token");
        }
        $authId = $payload['id'];
        $authSource = 'token';
    }

    // Server-side role/permission validation — never trust frontend claims.
    $db = new Database();
    $conn = $db->connect();
    $row = null;
    try {
        $stmt = dispatcher_statement(
            $conn,
            "SELECT ia.inspector_id, i.inspector_name, ia.role, ia.status FROM inspector_accounts ia LEFT JOIN inspectors i ON i.account_id = ia.account_id WHERE ia.inspector_id = ? AND LOWER(ia.role) = 'dispatcher' AND LOWER(ia.status) = 'active' LIMIT 1",
            "s",
            [$authId]
        );
        if ($stmt) {
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        // Fallback without join if inspectors table/column structure differs
        try {
            $stmt2 = dispatcher_statement(
                $conn,
                "SELECT inspector_id, role, status FROM inspector_accounts WHERE inspector_id = ? AND LOWER(role) = 'dispatcher' AND LOWER(status) = 'active' LIMIT 1",
                "s",
                [$authId]
            );
            if ($stmt2) {
                $row = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            }
        } catch (\Throwable $e2) {
            error_log("Dispatcher auth query error: " . $e2->getMessage());
        }
    }
    $conn->close();

    if (!$row) {
        dispatcher_json_error(403, "Forbidden");
    }

    $dispatcher_user = [
        'id' => $row['inspector_id'],
        'name' => $row['inspector_name'] ?? $_SESSION['inspector_name'] ?? $row['inspector_id'],
        'role' => $row['role'],
        'auth_source' => $authSource,
    ];
    $user = $dispatcher_user; // legacy compatibility for $user['id']
    return $dispatcher_user;
}

/**
 * CSRF protection for state-changing POST requests authenticated by session.
 * Bearer-token-only clients have no session cookie to abuse and skip the check.
 */
function dispatcher_require_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    global $dispatcher_user;
    if (($dispatcher_user['auth_source'] ?? '') !== 'session') return;

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headerMap = [];
    foreach ($headers as $k => $v) {
        $headerMap[strtolower($k)] = $v;
    }
    $sent = $headerMap['x-csrf-token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_SERVER['REDIRECT_HTTP_X_CSRF_TOKEN']
        ?? '';

    if (empty($sent)) {
        $raw = file_get_contents("php://input");
        if (!empty($raw)) {
            $parsed = @json_decode($raw, true);
            if (!empty($parsed['csrf_token'])) {
                $sent = $parsed['csrf_token'];
            }
        }
        if (empty($sent) && !empty($_POST['csrf_token'])) {
            $sent = $_POST['csrf_token'];
        }
    }

    $expected = $_SESSION['csrf_token'] ?? '';
    if (empty($expected) || empty($sent) || !hash_equals($expected, $sent)) {
        dispatcher_json_error(403, "Invalid CSRF token");
    }
}

/**
 * Check a bus_id exists and is Active.
 */
function dispatcher_valid_bus($conn, $bus_id)
{
    if ($bus_id === '' || $bus_id === null) return false;
    $stmt = dispatcher_statement($conn, "SELECT bus_id FROM buses WHERE bus_id = ? AND status = 'Active' LIMIT 1", "s", [$bus_id]);
    if (!$stmt) return false;
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Check a driver_id exists and is Active.
 */
function dispatcher_valid_driver($conn, $driver_id)
{
    if ($driver_id === '' || $driver_id === null) return false;
    $stmt = dispatcher_statement($conn, "SELECT driver_id FROM drivers WHERE driver_id = ? AND status = 'Active' LIMIT 1", "s", [$driver_id]);
    if (!$stmt) return false;
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Check a terminal id exists and is Active.
 */
function dispatcher_valid_terminal($conn, $terminal_id)
{
    if (!is_numeric($terminal_id) || (int)$terminal_id <= 0) return false;
    $stmt = dispatcher_statement($conn, "SELECT id FROM terminals WHERE id = ? AND status = 'Active' LIMIT 1", "i", [(int)$terminal_id]);
    if (!$stmt) return false;
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Cached inspection of fuel_logs columns. Returns array of lowercased column names as keys.
 */
function dispatcher_fuel_logs_columns($conn, $fresh = false)
{
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $cache = [];
    if (!$conn) return $cache;
    try {
        $res = $conn->query("SHOW COLUMNS FROM `fuel_logs`");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $cache[strtolower($r['Field'])] = true;
            }
            $res->close();
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_fuel_logs_columns: " . $e->getMessage());
    }
    return $cache;
}

/**
 * Primary-key column of fuel_logs across schema variants.
 * Some installs use `id`, others (inspector/finance) use `fuel_log_id`.
 */
function dispatcher_fuel_id_column($conn)
{
    $cols = dispatcher_fuel_logs_columns($conn);
    if (isset($cols['id'])) return 'id';
    if (isset($cols['fuel_log_id'])) return 'fuel_log_id';
    return null;
}

/**
 * COALESCE expression over the first existing columns from $candidates.
 * Returns $default when none of the candidate columns exist.
 */
function dispatcher_fuel_coalesce_expr(array $cols, array $candidates, $prefix = 'fl.', $default = 'NULL')
{
    $present = [];
    foreach ($candidates as $candidate) {
        if (isset($cols[$candidate])) {
            $present[] = $prefix . $candidate;
        }
    }
    if (empty($present)) return $default;
    return count($present) === 1 ? $present[0] : 'COALESCE(' . implode(', ', $present) . ')';
}

/**
 * Ensure fuel_logs and fuel_prices have necessary structure and seed data.
 * Safe to call idempotently on dispatch/fuel operations.
 */
function dispatcher_ensure_fuel_schema($conn)
{
    static $ensured = false;
    if ($ensured || !$conn) return;

    try {
        // 1. Ensure fuel_prices table & Diesel row
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `fuel_prices` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fuel_type` VARCHAR(50) NOT NULL,
                `price_per_liter` DECIMAL(10,2) NOT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_fuel_type` (`fuel_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $seedCheck = $conn->query("SELECT 1 FROM `fuel_prices` WHERE `fuel_type` = 'Diesel' LIMIT 1");
            if ($seedCheck) {
                if ($seedCheck->num_rows === 0) {
                    $conn->query("INSERT INTO `fuel_prices` (`fuel_type`, `price_per_liter`) VALUES ('Diesel', 65.55)");
                }
                $seedCheck->close();
            }
        } catch (\Throwable $e) {
            error_log("Fuel prices schema warning: " . $e->getMessage());
        }

        // 2. Ensure fuel_logs table
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `fuel_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `bus_id` VARCHAR(50) NOT NULL,
                `driver_id` VARCHAR(50) DEFAULT NULL,
                `trip_id` INT DEFAULT NULL,
                `liters` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `starting_odo` DECIMAL(10,2) DEFAULT NULL,
                `ending_odo` DECIMAL(10,2) DEFAULT NULL,
                `kpl` DECIMAL(10,2) DEFAULT NULL,
                `distance_traveled` DECIMAL(10,2) DEFAULT NULL,
                `expected_fuel` DECIMAL(10,2) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_fuel_bus_id` (`bus_id`),
                INDEX `idx_fuel_trip_id` (`trip_id`),
                INDEX `idx_fuel_driver_id` (`driver_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            error_log("Fuel logs table create warning: " . $e->getMessage());
        }

        // 3. Inspect existing columns
        $cols = dispatcher_fuel_logs_columns($conn, true);

        // Safely add missing columns WITHOUT fragile "AFTER" clauses
        if (!empty($cols)) {
            if (!isset($cols['trip_id'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `trip_id` INT DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['starting_odo'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `starting_odo` DECIMAL(10,2) DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['ending_odo'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `ending_odo` DECIMAL(10,2) DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['kpl'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `kpl` DECIMAL(10,2) DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['distance_traveled'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `distance_traveled` DECIMAL(10,2) DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['expected_fuel'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `expected_fuel` DECIMAL(10,2) DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['updated_at'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL"); } catch (\Throwable $e) {}
            }
            if (!isset($cols['price_per_liter'])) {
                try { $conn->query("ALTER TABLE `fuel_logs` ADD COLUMN `price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 0.00"); } catch (\Throwable $e) {}
            }

            // Refresh columns cache after migrations
            dispatcher_fuel_logs_columns($conn, true);

            // Safely add indexes if supported
            try {
                $idxRes = $conn->query("SHOW INDEX FROM `fuel_logs`");
                $indexes = [];
                if ($idxRes) {
                    while ($r = $idxRes->fetch_assoc()) {
                        $indexes[strtolower($r['Key_name'])] = true;
                    }
                    $idxRes->close();
                }
                if (!isset($indexes['idx_fuel_trip_id'])) {
                    @$conn->query("ALTER TABLE `fuel_logs` ADD INDEX `idx_fuel_trip_id` (`trip_id`)");
                }
            } catch (\Throwable $e) {}
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_ensure_fuel_schema global error: " . $e->getMessage());
    }

    $ensured = true;
}

/**
 * Idempotent schema self-heal for Bus Problem Reports (replacement assignment).
 * Mirrors the fuel_logs auto-migration pattern so the dispatcher UI works even
 * if migration 003 / 001 has not been applied via the driver app yet.
 */
function dispatcher_ensure_bpr_schema($conn)
{
    static $ensured = false;
    if ($ensured || !$conn) return $ensured;
    $ensured = true;
    try {
        if (!dispatcher_table_exists($conn, 'bus_problem_reports')) {
            return true;
        }

        // replacement columns on bus_problem_reports (migration 003)
        $cols = [];
        $res = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bus_problem_reports'");
        if ($res) {
            while ($row = $res->fetch_assoc()) { $cols[strtolower($row['COLUMN_NAME'])] = true; }
            $res->close();
        }
        $defs = [
            'replacement_bus_id'      => "VARCHAR(64) DEFAULT NULL",
            'replacement_driver_id'   => "VARCHAR(64) DEFAULT NULL",
            'replacement_assigned_by' => "VARCHAR(50) DEFAULT NULL",
            'replacement_assigned_at' => "DATETIME DEFAULT NULL",
        ];
        foreach ($defs as $col => $ddl) {
            if (!isset($cols[$col])) {
                try { $conn->query("ALTER TABLE `bus_problem_reports` ADD COLUMN `$col` $ddl"); } catch (\Throwable $e) {
                    error_log("dispatcher_ensure_bpr_schema ($col): " . $e->getMessage());
                }
            }
        }

        // bus_trips.replacement_required + 'Interrupted' status (migration 001)
        if (dispatcher_table_exists($conn, 'bus_trips')) {
            $tcols = [];
            $tres = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bus_trips'");
            if ($tres) {
                while ($row = $tres->fetch_assoc()) { $tcols[strtolower($row['COLUMN_NAME'])] = true; }
                $tres->close();
            }
            if (!isset($tcols['replacement_required'])) {
                try { $conn->query("ALTER TABLE `bus_trips` ADD COLUMN `replacement_required` TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
            }
            $enumRes = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bus_trips' AND COLUMN_NAME = 'status'");
            if ($enumRes) {
                $erow = $enumRes->fetch_assoc();
                $enumRes->close();
                $ct = $erow && isset($erow['COLUMN_TYPE']) ? strtolower($erow['COLUMN_TYPE']) : '';
                if (strpos($ct, 'interrupted') === false && $ct !== '') {
                    try {
                        $conn->query("ALTER TABLE `bus_trips` MODIFY COLUMN `status` ENUM('Scheduled','Departed','Completed','Cancelled','Started','Active','Ongoing','Interrupted') NOT NULL DEFAULT 'Scheduled'");
                    } catch (\Throwable $e) {
                        error_log("dispatcher_ensure_bpr_schema (bus_trips.status): " . $e->getMessage());
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_ensure_bpr_schema error: " . $e->getMessage());
    }
    return true;
}

/**
 * Get current diesel price from fuel_prices table.
 */
function dispatcher_get_current_diesel_price($conn)
{
    $price = 65.55; // fallback
    if (!$conn) return $price;
    try {
        dispatcher_ensure_fuel_schema($conn);
        $res = $conn->query("SELECT price_per_liter FROM fuel_prices WHERE fuel_type = 'Diesel' ORDER BY updated_at DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $price = (float)$row['price_per_liter'];
            $res->close();
        } else {
            $anyRes = $conn->query("SELECT price_per_liter FROM fuel_prices ORDER BY updated_at DESC LIMIT 1");
            if ($anyRes && $anyRes->num_rows > 0) {
                $price = (float)$anyRes->fetch_assoc()['price_per_liter'];
                $anyRes->close();
            }
        }
    } catch (\Throwable $e) {
        error_log("Diesel price fetch warning: " . $e->getMessage());
    }
    return $price;
}

/**
 * Cached table existence check for the shared dispatcher schema.
 */
function dispatcher_table_exists($conn, $table)
{
    static $cache = [];
    $key = strtolower((string)$table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $exists = false;
    if ($conn) {
        try {
            $safe = $conn->real_escape_string((string)$table);
            $res = $conn->query("SHOW TABLES LIKE '$safe'");
            $exists = $res && $res->num_rows > 0;
            if ($res) {
                $res->close();
            }
        } catch (\Throwable $e) {
            error_log("dispatcher_table_exists($table): " . $e->getMessage());
        }
    }
    $cache[$key] = $exists;
    return $exists;
}

/**
 * Cached column map (lowercased name => real name) for a table.
 */
function dispatcher_table_columns($conn, $table)
{
    static $cache = [];
    $key = strtolower((string)$table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $cols = [];
    if ($conn) {
        try {
            $safe = str_replace('`', '', (string)$table);
            $res = $conn->query("SHOW COLUMNS FROM `$safe`");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $cols[strtolower($r['Field'])] = $r['Field'];
                }
                $res->close();
            }
        } catch (\Throwable $e) {
            error_log("dispatcher_table_columns($table): " . $e->getMessage());
        }
    }
    $cache[$key] = $cols;
    return $cols;
}

/**
 * Cached bus_trips column map.
 */
function dispatcher_bus_trips_columns($conn)
{
    return dispatcher_table_columns($conn, 'bus_trips');
}

/**
 * Reject a request that claims a different dispatcher than the authenticated one.
 */
function dispatcher_assert_identity($payload, $dispatcher)
{
    if (!is_array($payload)) {
        return;
    }
    $claimed = trim((string)($payload['dispatcher_id'] ?? ''));
    $actual = (string)($dispatcher['id'] ?? '');
    if ($claimed !== '' && $claimed !== $actual) {
        dispatcher_json_error(403, "Dispatcher identity mismatch");
    }
}

/**
 * Write an accountability entry to audit_trail. Never fatals when the table or
 * optional columns are missing; only confirmed columns are written.
 */
function dispatcher_audit_log($conn, $dispatcher, $action, $recordId = null, $description = null, array $previous = [], array $new = [], $status = 'Success')
{
    if (!dispatcher_table_exists($conn, 'audit_trail')) {
        return false;
    }
    $cols = dispatcher_table_columns($conn, 'audit_trail');
    if (empty($cols)) {
        return false;
    }

    $dispatcher = is_array($dispatcher) ? $dispatcher : [];
    $actorId = $dispatcher['id'] ?? null;
    $userId = is_numeric($actorId) ? (int)$actorId : null;
    $username = (string)($dispatcher['name'] ?? ($actorId !== null ? (string)$actorId : ''));
    $role = (string)($dispatcher['role'] ?? 'dispatcher');

    $values = [
        'user_id'         => $userId,
        'username'        => $username !== '' ? $username : null,
        'user_role'       => $role !== '' ? $role : null,
        'module'          => 'Dispatcher',
        'action'          => (string)$action,
        'record_id'       => ($recordId !== null && $recordId !== '') ? (string)$recordId : null,
        'description'     => $description !== null ? (string)$description : null,
        'previous_values' => !empty($previous) ? json_encode($previous, JSON_UNESCAPED_UNICODE) : null,
        'new_values'      => !empty($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'      => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        'status'          => in_array($status, ['Success', 'Failed'], true) ? $status : 'Success',
        'is_read'         => 0,
    ];

    $insertCols = [];
    $placeholders = [];
    $params = [];
    $types = '';
    foreach ($values as $col => $val) {
        if (!isset($cols[strtolower($col)])) {
            continue;
        }
        $insertCols[] = '`' . $cols[strtolower($col)] . '`';
        $placeholders[] = '?';
        $params[] = $val;
        $types .= is_int($val) ? 'i' : 's';
    }
    if (empty($insertCols)) {
        return false;
    }

    $sql = 'INSERT INTO `audit_trail` (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = dispatcher_statement($conn, $sql, $types, $params);
    if (!$stmt) {
        return false;
    }
    $stmt->close();
    return true;
}

/**
 * Human-readable skip reason categories offered to dispatchers.
 */
function dispatcher_queue_skip_reasons()
{
    return [
        'bus_not_present'    => dispatcher_t('reasons.bus_not_present', 'Bus not present'),
        'driver_unavailable' => dispatcher_t('reasons.driver_unavailable', 'Driver unavailable'),
        'inspection_issue'   => dispatcher_t('reasons.inspection_issue', 'Inspection issue'),
        'fuel_issue'         => dispatcher_t('reasons.fuel_issue', 'Fuel issue'),
        'operational_delay'  => dispatcher_t('reasons.operational_delay', 'Operational delay'),
        'other'              => dispatcher_t('reasons.other', 'Other'),
    ];
}

/**
 * Parse the leading HH:MM from a free-text bus schedule. Returns minutes since
 * midnight, or null when the schedule cannot be interpreted as a time.
 */
function dispatcher_queue_schedule_minutes($schedule)
{
    if (!is_string($schedule)) {
        return null;
    }
    if (preg_match('/^\s*(\d{1,2}):(\d{2})/', $schedule, $m)) {
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
        if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
            return ($hours * 60) + $minutes;
        }
    }
    return null;
}

/**
 * Haversine distance between two coordinates in kilometers, or null when any
 * coordinate is not numeric.
 */
function dispatcher_queue_distance_km($lat1, $lng1, $lat2, $lng2)
{
    if (!is_numeric($lat1) || !is_numeric($lng1) || !is_numeric($lat2) || !is_numeric($lng2)) {
        return null;
    }
    $lat1 = (float)$lat1;
    $lng1 = (float)$lng1;
    $lat2 = (float)$lat2;
    $lng2 = (float)$lng2;
    $earthRadius = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function dispatcher_queue_proximity_km()
{
    return 0.5;
}

function dispatcher_queue_location_freshness_seconds()
{
    return 900;
}

/**
 * Terminal identity and coordinates for the queue, schema-adaptive.
 */
function dispatcher_queue_terminal($conn, $terminalId)
{
    $terminal = [
        'id' => (int)$terminalId,
        'terminal_name' => '',
        'latitude' => null,
        'longitude' => null,
        'has_coordinates' => false,
    ];
    if (!dispatcher_table_exists($conn, 'terminals')) {
        return $terminal;
    }
    $cols = dispatcher_table_columns($conn, 'terminals');
    if (!isset($cols['id'])) {
        return $terminal;
    }
    $nameExpr = isset($cols['terminal_name']) ? 'terminal_name' : "''";
    $latExpr = isset($cols['latitude']) ? 'latitude' : 'NULL';
    $lngExpr = isset($cols['longitude']) ? 'longitude' : 'NULL';
    $stmt = dispatcher_statement(
        $conn,
        "SELECT $nameExpr AS terminal_name, $latExpr AS latitude, $lngExpr AS longitude FROM terminals WHERE id = ? LIMIT 1",
        "i",
        [(int)$terminalId]
    );
    if ($stmt) {
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $terminal['terminal_name'] = (string)$row['terminal_name'];
            $terminal['latitude'] = $row['latitude'];
            $terminal['longitude'] = $row['longitude'];
            $terminal['has_coordinates'] = is_numeric($row['latitude']) && is_numeric($row['longitude']);
        }
    }
    return $terminal;
}

/**
 * Map of terminal id => terminal name.
 */
function dispatcher_queue_terminal_names($conn)
{
    $map = [];
    if (!dispatcher_table_exists($conn, 'terminals')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'terminals');
    if (!isset($cols['id'], $cols['terminal_name'])) {
        return $map;
    }
    try {
        $res = $conn->query("SELECT id, terminal_name FROM terminals");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['id']] = (string)$row['terminal_name'];
            }
            $res->close();
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_queue_terminal_names: " . $e->getMessage());
    }
    return $map;
}

/**
 * Active buses assigned to a terminal, schema-adaptive.
 */
function dispatcher_queue_bus_list($conn, $terminalId)
{
    if (!dispatcher_table_exists($conn, 'buses')) {
        return [];
    }
    $cols = dispatcher_table_columns($conn, 'buses');
    if (!isset($cols['bus_id'], $cols['from_terminal_id'])) {
        return [];
    }
    $select = ['bus_id'];
    foreach (['plate_number', 'bus_type', 'seats', 'route_name', 'schedule', 'status', 'driver', 'to_terminal_id', 'from_terminal_id'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }
    $where = "from_terminal_id = ?";
    if (isset($cols['status'])) {
        $where .= " AND status = 'Active'";
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM buses WHERE $where",
        "i",
        [(int)$terminalId]
    );
    if (!$stmt) {
        return [];
    }
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Deterministic terminal ordering: leading schedule time first, then bus id.
 */
function dispatcher_queue_sort_buses(array $buses)
{
    usort($buses, function ($a, $b) {
        $aMinutes = dispatcher_queue_schedule_minutes((string)($a['schedule'] ?? ''));
        $bMinutes = dispatcher_queue_schedule_minutes((string)($b['schedule'] ?? ''));
        if ($aMinutes !== null && $bMinutes !== null && $aMinutes !== $bMinutes) {
            return $aMinutes <=> $bMinutes;
        }
        if ($aMinutes !== null && $bMinutes === null) {
            return -1;
        }
        if ($aMinutes === null && $bMinutes !== null) {
            return 1;
        }
        return strcmp((string)($a['bus_id'] ?? ''), (string)($b['bus_id'] ?? ''));
    });
    return $buses;
}

/**
 * Latest driver assigned to each bus, schema-adaptive.
 */
function dispatcher_queue_driver_assignment_map($conn, array $busIds)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'driver_bus_assignment');
    if (!isset($cols['bus_id'], $cols['driver_id'])) {
        return $map;
    }
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT bus_id, driver_id FROM driver_bus_assignment WHERE bus_id IN ($placeholders)$order",
        str_repeat('s', count($busIds)),
        $busIds
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = (string)$row['bus_id'];
        if (!isset($map[$key])) {
            $map[$key] = (string)$row['driver_id'];
        }
    }
    $stmt->close();
    return $map;
}

/**
 * Driver rows keyed by driver_id.
 */
function dispatcher_queue_driver_rows($conn, array $driverIds)
{
    $rows = [];
    $driverIds = array_values(array_filter(array_map('strval', $driverIds), function ($v) {
        return $v !== '';
    }));
    if (empty($driverIds) || !dispatcher_table_exists($conn, 'drivers')) {
        return $rows;
    }
    $cols = dispatcher_table_columns($conn, 'drivers');
    if (!isset($cols['driver_id'])) {
        return $rows;
    }
    $select = ['driver_id'];
    foreach (['full_name', 'status'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }
    $placeholders = implode(',', array_fill(0, count($driverIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM drivers WHERE driver_id IN ($placeholders)",
        str_repeat('s', count($driverIds)),
        $driverIds
    );
    if (!$stmt) {
        return $rows;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[(string)$row['driver_id']] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Latest reported location per driver.
 */
function dispatcher_queue_location_map($conn, array $driverIds)
{
    $map = [];
    $driverIds = array_values(array_filter(array_map('strval', $driverIds), function ($v) {
        return $v !== '';
    }));
    if (empty($driverIds) || !dispatcher_table_exists($conn, 'driver_current_location')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'driver_current_location');
    if (!isset($cols['driver_id'])) {
        return $map;
    }
    $select = ['driver_id'];
    foreach (['bus_id', 'latitude', 'longitude', 'speed', 'updated_at'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }
    $order = isset($cols['updated_at']) ? ' ORDER BY updated_at DESC' : '';
    $placeholders = implode(',', array_fill(0, count($driverIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM driver_current_location WHERE driver_id IN ($placeholders)$order",
        str_repeat('s', count($driverIds)),
        $driverIds
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = (string)$row['driver_id'];
        if (!isset($map[$key])) {
            $map[$key] = $row;
        }
    }
    $stmt->close();
    return $map;
}

/**
 * Latest fuel/odometer reference per bus, preferring a row with a usable
 * starting ODO.
 */
function dispatcher_queue_fuel_map($conn, array $busIds)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'fuel_logs')) {
        return $map;
    }
    $cols = dispatcher_fuel_logs_columns($conn);
    if (!isset($cols['bus_id'])) {
        return $map;
    }
    $startExpr = dispatcher_fuel_coalesce_expr($cols, ['starting_odo', 'starting_odometer', 'start_km'], '', 'NULL');
    $idCol = dispatcher_fuel_id_column($conn);
    $select = ['bus_id', "$startExpr AS starting_odo"];
    if (isset($cols['kpl'])) {
        $select[] = 'kpl';
    }
    if (isset($cols['created_at'])) {
        $select[] = 'created_at';
    }
    $order = $idCol !== null ? " ORDER BY $idCol DESC" : '';
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM fuel_logs WHERE bus_id IN ($placeholders)$order",
        str_repeat('s', count($busIds)),
        $busIds
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = (string)$row['bus_id'];
        if (!isset($map[$key])) {
            $map[$key] = $row;
        } elseif (($map[$key]['starting_odo'] ?? null) === null && ($row['starting_odo'] ?? null) !== null) {
            $map[$key] = $row;
        }
    }
    $stmt->close();
    return $map;
}

/**
 * Current skip decision per bus for a date, derived from the accountability
 * trail (Queue Skipped / Queue Skip Cleared).
 */
function dispatcher_queue_skip_map($conn, $date)
{
    $map = [];
    if (!dispatcher_table_exists($conn, 'audit_trail')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'audit_trail');
    if (!isset($cols['module'], $cols['action'])) {
        return $map;
    }
    $select = ['action'];
    if (isset($cols['id'])) {
        $select[] = 'id';
    }
    if (isset($cols['new_values'])) {
        $select[] = 'new_values';
    }
    if (isset($cols['created_at'])) {
        $select[] = 'created_at';
    }
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $whereDate = isset($cols['created_at']) ? ' AND DATE(created_at) = ?' : '';
    $params = ['Dispatcher', 'Queue Skipped', 'Queue Skip Cleared'];
    $types = 'sss';
    if ($whereDate !== '') {
        $params[] = $date;
        $types = 'ssss';
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM audit_trail WHERE module = ? AND action IN (?,?)$whereDate$order",
        $types,
        $params
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payload = [];
        if (!empty($row['new_values'])) {
            $decoded = json_decode((string)$row['new_values'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $busId = (string)($payload['bus_id'] ?? '');
        if ($busId === '') {
            continue;
        }
        $map[$busId] = [
            'active' => (string)$row['action'] === 'Queue Skipped',
            'reason_category' => $payload['reason_category'] ?? null,
            'reason_label' => $payload['reason_label'] ?? ($payload['reason_category'] ?? null),
            'notes' => $payload['notes'] ?? null,
            'at' => $row['created_at'] ?? null,
        ];
    }
    $stmt->close();
    return $map;
}

/**
 * Today's trips for the terminal buses: active trip per bus/driver, the
 * dispatched list in actual dispatch-time order with derived order numbers,
 * dispatcher names, driver names, and recorded overtake reasons.
 */
function dispatcher_queue_trip_data($conn, array $busIds, $date, $terminalId)
{
    $result = [
        'by_bus' => [],
        'by_driver' => [],
        'dispatched' => [],
        'orders' => [],
        'dispatcher_names' => [],
        'driver_names' => [],
        'overtakes' => [],
        'recorded_positions' => [],
        'latest_driver_by_bus' => [],
    ];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'bus_trips')) {
        return $result;
    }
    $cols = dispatcher_table_columns($conn, 'bus_trips');
    if (!isset($cols['bus_id'], $cols['trip_date'])) {
        return $result;
    }
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $params = array_merge([$date], $busIds);
    $stmt = dispatcher_statement(
        $conn,
        "SELECT * FROM bus_trips WHERE trip_date = ? AND bus_id IN ($placeholders) ORDER BY id ASC",
        's' . str_repeat('s', count($busIds)),
        $params
    );
    if (!$stmt) {
        return $result;
    }
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    $dispatched = [];
    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? '');
        $busId = (string)($row['bus_id'] ?? '');
        $driverId = (string)($row['driver_id'] ?? '');
        if ($driverId !== '' && $status !== 'Cancelled') {
            $result['latest_driver_by_bus'][$busId] = $driverId;
        }
        if (in_array($status, ['Departed', 'Completed', 'Interrupted'], true) && !empty($row['departure_time'])) {
            $dispatched[] = $row;
        }
        if (in_array($status, ['Scheduled', 'Departed', 'Interrupted'], true)) {
            $current = $result['by_bus'][$busId] ?? null;
            if ($current === null || ($status === 'Departed' && (string)$current['status'] !== 'Departed')) {
                $result['by_bus'][$busId] = $row;
            }
            if ($driverId !== '') {
                $currentDriver = $result['by_driver'][$driverId] ?? null;
                if ($currentDriver === null || ($status === 'Departed' && (string)$currentDriver['status'] !== 'Departed')) {
                    $result['by_driver'][$driverId] = $row;
                }
            }
        }
    }

    usort($dispatched, function ($a, $b) {
        $timeA = strtotime((string)$a['departure_time']);
        $timeB = strtotime((string)$b['departure_time']);
        if ($timeA === $timeB) {
            return ((int)$a['id']) <=> ((int)$b['id']);
        }
        return $timeA <=> $timeB;
    });
    $order = 1;
    foreach ($dispatched as $row) {
        $result['orders'][(int)$row['id']] = $order++;
    }
    $result['dispatched'] = $dispatched;

    if (isset($cols['dispatcher_id'])) {
        $inspCols = dispatcher_table_columns($conn, 'inspectors');
        $accCols = dispatcher_table_columns($conn, 'inspector_accounts');
        if (isset($inspCols['account_id'], $inspCols['inspector_name']) && isset($accCols['inspector_id'], $accCols['account_id'])) {
            $ids = [];
            foreach ($rows as $row) {
                if (!empty($row['dispatcher_id'])) {
                    $ids[] = (string)$row['dispatcher_id'];
                }
            }
            $ids = array_values(array_unique($ids));
            if (!empty($ids)) {
                $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                $nameStmt = dispatcher_statement(
                    $conn,
                    "SELECT ia.inspector_id, insp.inspector_name FROM inspector_accounts ia LEFT JOIN inspectors insp ON insp.account_id = ia.account_id WHERE ia.inspector_id IN ($idPlaceholders)",
                    str_repeat('s', count($ids)),
                    $ids
                );
                if ($nameStmt) {
                    $nameRes = $nameStmt->get_result();
                    while ($nameRow = $nameRes->fetch_assoc()) {
                        $result['dispatcher_names'][(string)$nameRow['inspector_id']] = $nameRow['inspector_name'];
                    }
                    $nameStmt->close();
                }
            }
        }
    }

    $driverIds = [];
    foreach ($rows as $row) {
        if (!empty($row['driver_id'])) {
            $driverIds[] = (string)$row['driver_id'];
        }
    }
    $driverIds = array_values(array_unique($driverIds));
    if (!empty($driverIds)) {
        $driverRows = dispatcher_queue_driver_rows($conn, $driverIds);
        foreach ($driverRows as $driverId => $driverRow) {
            $result['driver_names'][(string)$driverId] = $driverRow['full_name'] ?? (string)$driverId;
        }
    }

    if (dispatcher_table_exists($conn, 'audit_trail') && !empty($dispatched)) {
        $auditCols = dispatcher_table_columns($conn, 'audit_trail');
        if (isset($auditCols['module'], $auditCols['action'], $auditCols['record_id'], $auditCols['new_values'])) {
            $tripIds = array_map(function ($row) {
                return (int)$row['id'];
            }, $dispatched);
            $tripIdPlaceholders = implode(',', array_fill(0, count($tripIds), '?'));
            $auditStmt = dispatcher_statement(
                $conn,
                "SELECT action, record_id, new_values FROM audit_trail WHERE module = 'Dispatcher' AND action IN ('Dispatch Created','Queue Overtake') AND record_id IN ($tripIdPlaceholders)",
                str_repeat('s', count($tripIds)),
                array_map('strval', $tripIds)
            );
            if ($auditStmt) {
                $auditRes = $auditStmt->get_result();
                while ($auditRow = $auditRes->fetch_assoc()) {
                    $payload = json_decode((string)($auditRow['new_values'] ?? ''), true);
                    if (!is_array($payload)) {
                        continue;
                    }
                    $tripId = (int)$auditRow['record_id'];
                    if ((string)$auditRow['action'] === 'Dispatch Created'
                        && isset($payload['queue_position']) && $payload['queue_position'] !== null) {
                        $result['recorded_positions'][$tripId] = (int)$payload['queue_position'];
                    }
                    if ((string)$auditRow['action'] === 'Queue Overtake' && !empty($payload['reason'])) {
                        $result['overtakes'][$tripId] = (string)$payload['reason'];
                    }
                }
                $auditStmt->close();
            }
        }
    }

    return $result;
}

/**
 * Bus id => 1-based planned queue position for a terminal.
 */
function dispatcher_queue_position_map($conn, $terminalId)
{
    $map = [];
    $buses = dispatcher_queue_sort_buses(dispatcher_queue_bus_list($conn, $terminalId));
    $index = 1;
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $map[$busId] = $index;
        }
        $index++;
    }
    return $map;
}

/**
 * Current queue position per bus for a terminal and date. First-trip entries
 * keep their planned position; a bus waiting for its next trip gets an appended
 * position (planned count + global completion rank). Positions are never
 * renumbered, so gaps appear while a bus is on route.
 */
function dispatcher_queue_entry_position_map($conn, $terminalId, $date, $tripData = null)
{
    $buses = dispatcher_queue_sort_buses(dispatcher_queue_bus_list($conn, $terminalId));
    $map = [];
    $plannedCount = 0;
    $index = 1;
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $map[$busId] = $index;
            $plannedCount = $index;
        }
        $index++;
    }
    $busIds = array_keys($map);
    if (empty($busIds)) {
        return $map;
    }
    if ($tripData === null) {
        $tripData = dispatcher_queue_trip_data($conn, array_map('strval', $busIds), $date, $terminalId);
    }
    $completed = [];
    foreach ($tripData['dispatched'] as $row) {
        if ((string)($row['status'] ?? '') === 'Completed') {
            $completed[] = $row;
        }
    }
    usort($completed, function ($a, $b) {
        $timeA = strtotime((string)(!empty($a['arrival_time']) ? $a['arrival_time'] : ($a['departure_time'] ?? '')));
        $timeB = strtotime((string)(!empty($b['arrival_time']) ? $b['arrival_time'] : ($b['departure_time'] ?? '')));
        if ($timeA === $timeB) {
            return ((int)$a['id']) <=> ((int)$b['id']);
        }
        return $timeA <=> $timeB;
    });
    $rankByTrip = [];
    $latestByBus = [];
    $rank = 1;
    foreach ($completed as $row) {
        $tripId = (int)$row['id'];
        $rankByTrip[$tripId] = $rank++;
        $latestByBus[(string)($row['bus_id'] ?? '')] = $tripId;
    }
    foreach ($busIds as $busId) {
        $busKey = (string)$busId;
        $active = $tripData['by_bus'][$busKey] ?? null;
        if ($active !== null) {
            $tripId = (int)$active['id'];
            if (isset($tripData['recorded_positions'][$tripId])) {
                $map[$busKey] = (int)$tripData['recorded_positions'][$tripId];
            }
            continue;
        }
        if (isset($latestByBus[$busKey])) {
            $tripId = $latestByBus[$busKey];
            if (isset($rankByTrip[$tripId])) {
                $map[$busKey] = $plannedCount + $rankByTrip[$tripId];
            }
        }
    }
    return $map;
}

/**
 * Recorded original queue position per trip, taken from the Dispatch Created
 * audit trail written when the trip was dispatched.
 */
function dispatcher_queue_recorded_positions($conn, array $tripIds)
{
    $map = [];
    $tripIds = array_values(array_filter(array_map('intval', $tripIds), function ($value) {
        return $value > 0;
    }));
    if (empty($tripIds) || !dispatcher_table_exists($conn, 'audit_trail')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'audit_trail');
    if (!isset($cols['module'], $cols['action'], $cols['record_id'], $cols['new_values'])) {
        return $map;
    }
    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT record_id, new_values FROM audit_trail WHERE module = 'Dispatcher' AND action = 'Dispatch Created' AND record_id IN ($placeholders)",
        str_repeat('s', count($tripIds)),
        array_map('strval', $tripIds)
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        if (is_array($payload) && isset($payload['queue_position']) && $payload['queue_position'] !== null) {
            $map[(int)$row['record_id']] = (int)$payload['queue_position'];
        }
    }
    $stmt->close();
    return $map;
}

/**
 * Actual dispatch order of a trip within its terminal's day, derived from the
 * recorded dispatch time (ties broken by trip id).
 */
function dispatcher_queue_dispatch_order($conn, $tripId, $terminalId, $date)
{
    $tripId = (int)$tripId;
    if ($tripId <= 0) {
        return null;
    }
    $cols = dispatcher_bus_trips_columns($conn);
    if (empty($cols)) {
        return null;
    }
    $stmt = dispatcher_statement($conn, "SELECT departure_time, status FROM bus_trips WHERE id = ? LIMIT 1", "i", [$tripId]);
    if (!$stmt) {
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['departure_time']) || !in_array((string)$row['status'], ['Departed', 'Completed', 'Interrupted'], true)) {
        return null;
    }
    $where = ["trip_date = ?"];
    $types = "s";
    $params = [$date];
    if (isset($cols['from_terminal_id'])) {
        $where[] = "from_terminal_id = ?";
        $types .= "i";
        $params[] = (int)$terminalId;
    }
    $where[] = "status IN ('Departed','Completed','Interrupted')";
    $where[] = "departure_time IS NOT NULL";
    $where[] = "(departure_time < ? OR (departure_time = ? AND id <= ?))";
    $types .= "ssi";
    $params[] = $row['departure_time'];
    $params[] = $row['departure_time'];
    $params[] = $tripId;
    $orderStmt = dispatcher_statement(
        $conn,
        "SELECT COUNT(*) + 1 AS dispatch_order FROM bus_trips WHERE " . implode(' AND ', $where),
        $types,
        $params
    );
    if (!$orderStmt) {
        return null;
    }
    $order = (int)$orderStmt->get_result()->fetch_assoc()['dispatch_order'];
    $orderStmt->close();
    return $order > 0 ? $order : null;
}

/**
 * Record a skip or skip-clear decision in the accountability trail.
 */
function dispatcher_queue_record_skip($conn, $dispatcher, $action, $busId, $queuePosition, $date, $reasonCategory, $notes)
{
    $reasons = dispatcher_queue_skip_reasons();
    $reasonLabel = $reasons[$reasonCategory] ?? ($reasonCategory !== '' ? $reasonCategory : '');
    if ($action === 'Queue Skipped') {
        $description = "Queue position #" . (int)$queuePosition . " (Bus $busId) skipped: " . ($reasonLabel !== '' ? $reasonLabel : 'reason recorded');
    } else {
        $description = "Queue skip cleared for Bus $busId (position #" . (int)$queuePosition . ")";
    }
    return dispatcher_audit_log(
        $conn,
        $dispatcher,
        $action,
        null,
        $description,
        [],
        [
            'bus_id' => (string)$busId,
            'queue_position' => (int)$queuePosition,
            'trip_date' => (string)$date,
            'reason_category' => $reasonCategory,
            'reason_label' => $reasonLabel,
            'notes' => $notes,
        ]
    );
}

/**
 * Latest dispatcher driver check-in per bus for a date, read from the
 * accountability trail (Driver Checked In).
 */
function dispatcher_queue_checkin_map($conn, $date, array $busIds)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($value) {
        return $value !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'audit_trail')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'audit_trail');
    if (!isset($cols['module'], $cols['action'], $cols['new_values'])) {
        return $map;
    }
    $select = ['new_values'];
    if (isset($cols['id'])) {
        $select[] = 'id';
    }
    if (isset($cols['created_at'])) {
        $select[] = 'created_at';
    }
    if (isset($cols['username'])) {
        $select[] = 'username';
    }
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $whereDate = isset($cols['created_at']) ? ' AND DATE(created_at) = ?' : '';
    $params = ['Dispatcher', 'Driver Checked In'];
    $types = 'ss';
    if ($whereDate !== '') {
        $params[] = $date;
        $types .= 's';
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM audit_trail WHERE module = ? AND action = ?$whereDate$order",
        $types,
        $params
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }
        $busId = (string)($payload['bus_id'] ?? '');
        if ($busId === '') {
            continue;
        }
        $map[$busId] = [
            'at' => $payload['checked_in_at'] ?? ($row['created_at'] ?? null),
            'by' => $payload['checked_in_by'] ?? 'dispatcher',
            'by_name' => $payload['checked_in_by_name'] ?? ($row['username'] ?? null),
            'driver_id' => $payload['driver_id'] ?? null,
            'driver_name' => $payload['driver_name'] ?? null,
        ];
    }
    $stmt->close();
    return $map;
}

/**
 * Record a dispatcher-performed driver check-in in the accountability trail.
 */
function dispatcher_queue_record_checkin($conn, $dispatcher, $busId, $driverId, $driverName, $date)
{
    return dispatcher_audit_log(
        $conn,
        $dispatcher,
        'Driver Checked In',
        null,
        "Driver $driverName checked in for Bus $busId by dispatcher",
        [],
        [
            'bus_id' => (string)$busId,
            'driver_id' => (string)$driverId,
            'driver_name' => (string)$driverName,
            'trip_date' => (string)$date,
            'checked_in_by' => 'dispatcher',
            'checked_in_by_name' => (string)($dispatcher['name'] ?? ''),
            'checked_in_at' => date('Y-m-d H:i:s'),
        ]
    );
}

/**
 * Prepared-trip confirmations per bus for a date (Dispatcher 'Trip Prepared').
 */
function dispatcher_queue_prepared_map($conn, $date, array $busIds)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($value) {
        return $value !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'audit_trail')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'audit_trail');
    if (!isset($cols['module'], $cols['action'], $cols['new_values'])) {
        return $map;
    }
    $select = ['new_values'];
    if (isset($cols['id'])) {
        $select[] = 'id';
    }
    if (isset($cols['created_at'])) {
        $select[] = 'created_at';
    }
    if (isset($cols['username'])) {
        $select[] = 'username';
    }
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $whereDate = isset($cols['created_at']) ? ' AND DATE(created_at) = ?' : '';
    $params = ['Dispatcher', 'Trip Prepared'];
    $types = 'ss';
    if ($whereDate !== '') {
        $params[] = $date;
        $types .= 's';
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM audit_trail WHERE module = ? AND action = ?$whereDate$order",
        $types,
        $params
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payload = json_decode((string)($row['new_values'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }
        $busId = (string)($payload['bus_id'] ?? '');
        if ($busId === '') {
            continue;
        }
        $map[$busId] = [
            'at' => $payload['prepared_at'] ?? ($row['created_at'] ?? null),
            'by_name' => $payload['prepared_by'] ?? ($row['username'] ?? null),
            'driver_id' => $payload['driver_id'] ?? null,
            'driver_name' => $payload['driver_name'] ?? null,
        ];
    }
    $stmt->close();
    return $map;
}

/**
 * Record the dispatcher's Prepare Trip confirmation in the accountability trail.
 */
function dispatcher_queue_record_prepared($conn, $dispatcher, $busId, $driverId, $driverName, $date)
{
    return dispatcher_audit_log(
        $conn,
        $dispatcher,
        'Trip Prepared',
        null,
        "Pre-trip preparation confirmed for Bus $busId (Driver $driverName)",
        [],
        [
            'bus_id' => (string)$busId,
            'driver_id' => (string)$driverId,
            'driver_name' => (string)$driverName,
            'trip_date' => (string)$date,
            'prepared_by' => (string)($dispatcher['name'] ?? ''),
            'prepared_at' => date('Y-m-d H:i:s'),
        ]
    );
}

/**
 * Resolve the driver assigned to a bus (driver_bus_assignment first, then the
 * buses.driver column, matching by id or name).
 */
function dispatcher_queue_bus_driver($conn, $busId)
{
    $busId = trim((string)$busId);
    if ($busId === '') {
        return null;
    }
    $driverRef = '';
    if (dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        $cols = dispatcher_table_columns($conn, 'driver_bus_assignment');
        if (isset($cols['bus_id'], $cols['driver_id'])) {
            $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
            $stmt = dispatcher_statement($conn, "SELECT driver_id FROM driver_bus_assignment WHERE bus_id = ?$order LIMIT 1", "s", [$busId]);
            if ($stmt) {
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $driverRef = (string)$row['driver_id'];
                }
            }
        }
    }
    if ($driverRef === '' && dispatcher_table_exists($conn, 'buses')) {
        $cols = dispatcher_table_columns($conn, 'buses');
        if (isset($cols['driver'])) {
            $stmt = dispatcher_statement($conn, "SELECT driver FROM buses WHERE bus_id = ? LIMIT 1", "s", [$busId]);
            if ($stmt) {
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $raw = trim((string)$row['driver']);
                    if ($raw !== '' && strcasecmp($raw, 'Unassigned') !== 0 && strcasecmp($raw, 'TBD') !== 0) {
                        $driverRef = $raw;
                    }
                }
            }
        }
    }
    if ($driverRef === '') {
        return null;
    }
    $driverRows = dispatcher_queue_driver_rows($conn, [$driverRef]);
    if (isset($driverRows[$driverRef])) {
        return [
            'driver_id' => $driverRef,
            'name' => (string)($driverRows[$driverRef]['full_name'] ?? $driverRef),
        ];
    }
    if (dispatcher_table_exists($conn, 'drivers')) {
        $cols = dispatcher_table_columns($conn, 'drivers');
        if (isset($cols['driver_id'], $cols['full_name'])) {
            $stmt = dispatcher_statement($conn, "SELECT driver_id, full_name FROM drivers WHERE LOWER(full_name) = LOWER(?) LIMIT 1", "s", [$driverRef]);
            if ($stmt) {
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return [
                        'driver_id' => (string)$row['driver_id'],
                        'name' => (string)$row['full_name'],
                    ];
                }
            }
        }
    }
    return ['driver_id' => $driverRef, 'name' => $driverRef];
}

/**
 * Resolve the driver who operates a bus for the day: explicit assignment
 * first, then the driver recorded on the bus's latest trip for the date.
 * Returns ['driver_id', 'name', 'source' => 'assignment'|'trip_record'] or null.
 */
function dispatcher_resolve_day_driver($conn, $busId, $date = null)
{
    $assigned = dispatcher_queue_bus_driver($conn, $busId);
    if ($assigned !== null && !empty($assigned['driver_id'])) {
        return [
            'driver_id' => (string)$assigned['driver_id'],
            'name' => (string)($assigned['name'] ?? $assigned['driver_id']),
            'source' => 'assignment',
        ];
    }

    $busId = trim((string)$busId);
    $date = $date ?: date('Y-m-d');
    if ($busId === '' || !dispatcher_table_exists($conn, 'bus_trips')) {
        return null;
    }
    $cols = dispatcher_table_columns($conn, 'bus_trips');
    if (!isset($cols['bus_id'], $cols['driver_id'])) {
        return null;
    }
    $where = "bus_id = ? AND driver_id IS NOT NULL AND driver_id <> ''";
    $types = "s";
    $params = [$busId];
    if (isset($cols['trip_date'])) {
        $where .= " AND trip_date = ?";
        $types .= "s";
        $params[] = $date;
    }
    if (isset($cols['status'])) {
        $where .= " AND status <> 'Cancelled'";
    }
    $order = isset($cols['id']) ? ' ORDER BY id DESC' : '';
    $stmt = dispatcher_statement($conn, "SELECT driver_id FROM bus_trips WHERE $where$order LIMIT 1", $types, $params);
    if (!$stmt) {
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || trim((string)$row['driver_id']) === '') {
        return null;
    }
    $driverRef = (string)$row['driver_id'];
    $name = $driverRef;
    $driverRows = dispatcher_queue_driver_rows($conn, [$driverRef]);
    if (isset($driverRows[$driverRef])) {
        $rowStatus = (string)($driverRows[$driverRef]['status'] ?? 'Active');
        if (strcasecmp($rowStatus, 'Active') !== 0) {
            return null;
        }
        $name = (string)($driverRows[$driverRef]['full_name'] ?? $driverRef);
    }
    return ['driver_id' => $driverRef, 'name' => $name, 'source' => 'trip_record'];
}

/**
 * Persist the day assignment for a bus (update the current row or insert one)
 * without touching other buses. Returns true when the row matches the driver.
 */
function dispatcher_ensure_bus_assignment($conn, $busId, $driverId)
{
    $busId = trim((string)$busId);
    $driverId = trim((string)$driverId);
    if ($busId === '' || $driverId === '' || !dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        return false;
    }
    $cols = dispatcher_table_columns($conn, 'driver_bus_assignment');
    if (!isset($cols['bus_id'], $cols['driver_id'])) {
        return false;
    }

    $currentRef = null;
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $stmt = dispatcher_statement($conn, "SELECT driver_id FROM driver_bus_assignment WHERE bus_id = ?$order LIMIT 1", "s", [$busId]);
    if ($stmt) {
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $currentRef = (string)$row['driver_id'];
            if ($currentRef === $driverId) {
                return true;
            }
        }
    }

    if ($currentRef !== null) {
        $upd = dispatcher_statement($conn, "UPDATE driver_bus_assignment SET driver_id = ? WHERE bus_id = ?", "ss", [$driverId, $busId]);
        if (!$upd) {
            return false;
        }
        $upd->close();
        return true;
    }

    $ins = dispatcher_statement($conn, "INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES (?, ?)", "ss", [$driverId, $busId]);
    if (!$ins) {
        return false;
    }
    $ins->close();
    return true;
}

/**
 * Active drivers available for a day assignment: Active, not assigned to any
 * bus through driver_bus_assignment, and not another bus's legacy buses.driver
 * value. A bus's current driver is therefore excluded as well, so the result is
 * the set of replacement/assignment candidates.
 */
function dispatcher_available_drivers($conn)
{
    if (!dispatcher_table_exists($conn, 'drivers')) {
        return [];
    }
    $cols = dispatcher_table_columns($conn, 'drivers');
    if (!isset($cols['driver_id'], $cols['full_name'])) {
        return [];
    }
    $sql = "SELECT driver_id, full_name FROM drivers";
    if (isset($cols['status'])) {
        $sql .= " WHERE status = 'Active'";
    }
    $sql .= " ORDER BY full_name ASC, driver_id ASC";

    $drivers = [];
    try {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $drivers[(string)$row['driver_id']] = trim((string)($row['full_name'] ?? ''));
            }
            $res->close();
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_available_drivers: " . $e->getMessage());
        return [];
    }
    if (empty($drivers)) {
        return [];
    }

    /* Bus status and legacy driver per bus, so archived buses and stale rows do
       not keep a driver off the availability list. */
    $busStatus = [];
    $legacyByBus = [];
    if (dispatcher_table_exists($conn, 'buses')) {
        $bcols = dispatcher_table_columns($conn, 'buses');
        if (isset($bcols['bus_id'])) {
            $select = ['bus_id'];
            if (isset($bcols['status'])) {
                $select[] = 'status';
            }
            if (isset($bcols['driver'])) {
                $select[] = 'driver';
            }
            try {
                $res = $conn->query("SELECT " . implode(', ', $select) . " FROM buses");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $busKey = (string)$row['bus_id'];
                        $busStatus[$busKey] = isset($row['status']) ? (string)$row['status'] : 'Active';
                        $ref = trim((string)($row['driver'] ?? ''));
                        if ($ref !== '' && strcasecmp($ref, 'Unassigned') !== 0 && strcasecmp($ref, 'TBD') !== 0) {
                            $legacyByBus[$busKey] = $ref;
                        }
                    }
                    $res->close();
                }
            } catch (\Throwable $e) {
                error_log("dispatcher_available_drivers buses: " . $e->getMessage());
            }
        }
    }

    /* Only the oldest (first) driver_bus_assignment row per bus is the current
       assignment; assignments on non-Active buses do not hold their driver. */
    $currentAssignments = [];
    if (dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        $acols = dispatcher_table_columns($conn, 'driver_bus_assignment');
        if (isset($acols['bus_id'], $acols['driver_id'])) {
            $order = isset($acols['id']) ? ' ORDER BY id ASC' : '';
            try {
                $res = $conn->query("SELECT bus_id, driver_id FROM driver_bus_assignment$order");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $busKey = (string)$row['bus_id'];
                        if (isset($currentAssignments[$busKey])) {
                            continue;
                        }
                        $currentAssignments[$busKey] = (string)$row['driver_id'];
                    }
                    $res->close();
                }
            } catch (\Throwable $e) {
                error_log("dispatcher_available_drivers assignment: " . $e->getMessage());
            }
        }
    }

    $busyIds = [];
    $busyNames = [];
    foreach ($currentAssignments as $busKey => $driverRef) {
        if (($busStatus[$busKey] ?? 'Active') !== 'Active') {
            continue;
        }
        $busyIds[$driverRef] = true;
    }
    foreach ($legacyByBus as $busKey => $ref) {
        if (($busStatus[$busKey] ?? 'Active') !== 'Active' || isset($currentAssignments[$busKey])) {
            continue;
        }
        $busyIds[$ref] = true;
        $busyNames[strtolower($ref)] = true;
    }

    $available = [];
    foreach ($drivers as $driverId => $name) {
        if (isset($busyIds[$driverId])) {
            continue;
        }
        if ($name !== '' && isset($busyNames[strtolower($name)])) {
            continue;
        }
        $available[] = [
            'driver_id' => $driverId,
            'full_name' => $name !== '' ? $name : $driverId,
        ];
    }
    return $available;
}

/**
 * Build the terminal dispatch queue with readiness, presence, skip state, and
 * dispatch history.
 */
function dispatcher_queue_build($conn, $terminalId, $date = null)
{
    $date = $date ?: date('Y-m-d');
    $terminal = dispatcher_queue_terminal($conn, $terminalId);
    $buses = dispatcher_queue_sort_buses(dispatcher_queue_bus_list($conn, $terminalId));

    $busIds = [];
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $busIds[] = $busId;
        }
    }
    $busIds = array_values(array_unique($busIds));

    $assignmentMap = dispatcher_queue_driver_assignment_map($conn, $busIds);

    $resolvedDrivers = [];
    $driverIds = [];
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        $assigned = trim((string)($assignmentMap[$busId] ?? ''));
        if ($assigned === '') {
            $raw = trim((string)($bus['driver'] ?? ''));
            if ($raw !== '' && strcasecmp($raw, 'Unassigned') !== 0 && strcasecmp($raw, 'TBD') !== 0) {
                $assigned = $raw;
            }
        }
        if ($assigned !== '') {
            $driverIds[] = $assigned;
        }
        $resolvedDrivers[$busId] = ['driver_id' => $assigned !== '' ? $assigned : null, 'name' => $assigned !== '' ? $assigned : null, 'active' => $assigned !== ''];
    }
    $driverIds = array_values(array_unique($driverIds));
    $driverRows = dispatcher_queue_driver_rows($conn, $driverIds);
    $driverByName = [];
    foreach ($driverRows as $driverId => $driverRow) {
        $driverByName[strtolower((string)($driverRow['full_name'] ?? ''))] = $driverRow;
    }
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        $assigned = $resolvedDrivers[$busId]['driver_id'] ?? null;
        if ($assigned === null) {
            continue;
        }
        if (isset($driverRows[$assigned])) {
            $driverRow = $driverRows[$assigned];
            $resolvedDrivers[$busId] = [
                'driver_id' => $assigned,
                'name' => $driverRow['full_name'] ?? $assigned,
                'active' => !isset($driverRow['status']) || strcasecmp((string)$driverRow['status'], 'Active') === 0,
                'source' => 'assignment',
            ];
        } elseif (isset($driverByName[strtolower($assigned)])) {
            $driverRow = $driverByName[strtolower($assigned)];
            $resolvedDrivers[$busId] = [
                'driver_id' => (string)($driverRow['driver_id'] ?? $assigned),
                'name' => $driverRow['full_name'] ?? $assigned,
                'active' => !isset($driverRow['status']) || strcasecmp((string)$driverRow['status'], 'Active') === 0,
                'source' => 'bus',
            ];
        } else {
            $resolvedDrivers[$busId] = [
                'driver_id' => $assigned,
                'name' => $assigned,
                'active' => true,
                'source' => 'bus',
            ];
        }
    }
    /* A bus with no explicit assignment inherits the driver of its latest trip
       today, so the queue auto-fills from the operating record. */
    $tripData = dispatcher_queue_trip_data($conn, $busIds, $date, $terminalId);
    foreach ($buses as $bus) {
        $busKey = (string)($bus['bus_id'] ?? '');
        if ($busKey === '') {
            continue;
        }
        $resolved = $resolvedDrivers[$busKey] ?? null;
        if ($resolved !== null && !empty($resolved['driver_id'])) {
            continue;
        }
        $tripDriverId = (string)($tripData['latest_driver_by_bus'][$busKey] ?? '');
        if ($tripDriverId === '') {
            continue;
        }
        $resolvedDrivers[$busKey] = [
            'driver_id' => $tripDriverId,
            'name' => (string)($tripData['driver_names'][$tripDriverId] ?? $tripDriverId),
            'active' => true,
            'source' => 'trip_record',
        ];
    }
    $allDriverIds = [];
    foreach ($resolvedDrivers as $resolved) {
        if (!empty($resolved['driver_id'])) {
            $allDriverIds[] = $resolved['driver_id'];
        }
    }
    $allDriverIds = array_values(array_unique($allDriverIds));

    $locationMap = dispatcher_queue_location_map($conn, $allDriverIds);
    $fuelMap = dispatcher_queue_fuel_map($conn, $busIds);
    $skipMap = dispatcher_queue_skip_map($conn, $date);
    $checkinMap = dispatcher_queue_checkin_map($conn, $date, $busIds);
    $preparedMap = dispatcher_queue_prepared_map($conn, $date, $busIds);
    $entryPositions = dispatcher_queue_entry_position_map($conn, $terminalId, $date, $tripData);
    $completedCountByBus = [];
    foreach ($tripData['dispatched'] as $tripRow) {
        if ((string)($tripRow['status'] ?? '') === 'Completed') {
            $completedBusId = (string)($tripRow['bus_id'] ?? '');
            $completedCountByBus[$completedBusId] = ($completedCountByBus[$completedBusId] ?? 0) + 1;
        }
    }
    $terminalNames = dispatcher_queue_terminal_names($conn);

    $now = time();
    $freshness = dispatcher_queue_location_freshness_seconds();
    $proximity = dispatcher_queue_proximity_km();
    $areaMap = [
        'bus_assigned' => 'Vehicle',
        'bus_present' => 'Vehicle',
        'driver_assigned' => 'Driver',
        'driver_checked_in' => 'Driver',
        'fuel_validated' => 'Fuel',
        'route_assigned' => 'Route',
        'trip_valid' => 'Trip',
    ];

    $entries = [];
    $positionMap = [];
    $summary = [
        'total' => 0,
        'active' => 0,
        'waiting_for_driver' => 0,
        'driver_assigned' => 0,
        'checked_in' => 0,
        'ready' => 0,
        'dispatched' => 0,
        'completed' => 0,
        'skipped' => 0,
    ];

    foreach ($buses as $index => $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        $position = isset($entryPositions[$busId]) ? (int)$entryPositions[$busId] : ($index + 1);
        $positionMap[$busId] = $position;
        $route = trim((string)($bus['route_name'] ?? ''));
        $schedule = trim((string)($bus['schedule'] ?? ''));
        $destinationId = isset($bus['to_terminal_id']) && $bus['to_terminal_id'] !== null && $bus['to_terminal_id'] !== ''
            ? (int)$bus['to_terminal_id']
            : null;
        $destinationName = ($destinationId !== null && isset($terminalNames[$destinationId])) ? $terminalNames[$destinationId] : null;

        $driver = $resolvedDrivers[$busId] ?? ['driver_id' => null, 'name' => null, 'active' => false, 'source' => 'none'];
        $location = (!empty($driver['driver_id']) && isset($locationMap[$driver['driver_id']])) ? $locationMap[$driver['driver_id']] : null;

        $presenceAvailable = false;
        $locationCheckedIn = false;
        $checkedIn = false;
        $checkedInBy = null;
        $checkedInAtRaw = null;
        $checkedInAtLabel = '';
        $atTerminal = false;
        $distanceKm = null;
        $locationUpdatedAt = null;
        $presenceDetail = '';
        $hasLocation = $location !== null
            && is_numeric($location['latitude'] ?? null)
            && is_numeric($location['longitude'] ?? null);
        if (!$hasLocation) {
            $presenceDetail = 'Driver location not reported';
        } else {
            $locationUpdatedAt = $location['updated_at'] ?? null;
            $locationTimestamp = $locationUpdatedAt ? strtotime((string)$locationUpdatedAt) : null;
            $locationCheckedIn = $locationTimestamp === null || ($now - $locationTimestamp) <= $freshness;
            if ($terminal['has_coordinates']) {
                $presenceAvailable = true;
                $distanceKm = dispatcher_queue_distance_km($location['latitude'], $location['longitude'], $terminal['latitude'], $terminal['longitude']);
                $atTerminal = $distanceKm !== null && $distanceKm <= $proximity;
                if (!$locationCheckedIn) {
                    $presenceDetail = 'Location report is old';
                } elseif ($atTerminal) {
                    $presenceDetail = 'At the terminal';
                } else {
                    $presenceDetail = 'Away from the terminal';
                }
            } else {
                $presenceDetail = 'Terminal location not configured';
            }
        }
        $dispatcherCheckin = $checkinMap[$busId] ?? null;
        $checkedInByName = null;
        $checkinMatchesAssignedDriver = $dispatcherCheckin !== null
            && (string)($dispatcherCheckin['driver_id'] ?? '') !== ''
            && (string)$dispatcherCheckin['driver_id'] === (string)($driver['driver_id'] ?? '');
        if ($checkinMatchesAssignedDriver) {
            $checkedIn = true;
            $checkedInBy = 'dispatcher';
            $checkedInAtRaw = $dispatcherCheckin['at'] ?? null;
            $checkedInByName = $dispatcherCheckin['by_name'] ?? null;
        } elseif ($locationCheckedIn) {
            $checkedIn = true;
            $checkedInBy = 'driver';
            $checkedInAtRaw = $locationUpdatedAt;
        }
        if ($checkedInAtRaw) {
            $checkedInTimestamp = strtotime((string)$checkedInAtRaw);
            if ($checkedInTimestamp !== null && $checkedInTimestamp !== false) {
                $checkedInAtLabel = date('g:i A', $checkedInTimestamp);
            }
        }
        $prepared = $preparedMap[$busId] ?? null;
        $preparedMatchesDriver = $prepared !== null
            && (string)($prepared['driver_id'] ?? '') !== ''
            && (string)$prepared['driver_id'] === (string)($driver['driver_id'] ?? '');

        $fuel = $fuelMap[$busId] ?? null;
        $fuelValidated = $fuel !== null && ($fuel['starting_odo'] ?? null) !== null && (float)$fuel['starting_odo'] > 0;
        $routeAssigned = $route !== '' && strcasecmp($route, 'TBD') !== 0;
        $activeTrip = $tripData['by_bus'][$busId] ?? null;
        $activeTripStatus = $activeTrip ? (string)($activeTrip['status'] ?? '') : '';
        $driverActiveTrip = (!empty($driver['driver_id']) && isset($tripData['by_driver'][$driver['driver_id']])) ? $tripData['by_driver'][$driver['driver_id']] : null;
        $driverActiveOnOtherBus = $driverActiveTrip !== null && (string)($driverActiveTrip['bus_id'] ?? '') !== $busId;
        $destinationValid = $destinationId !== null && $destinationId > 0 && isset($terminalNames[$destinationId]);

        $readiness = [];
        $addItem = function ($key, $label, $ok, $blocking, $detail, $minor = false) use (&$readiness) {
            $readiness[] = [
                'key' => $key,
                'label' => $label,
                'ok' => (bool)$ok,
                'blocking' => (bool)$blocking,
                'minor' => (bool)$minor,
                'detail' => $detail !== null ? (string)$detail : null,
            ];
        };

        $hasDriver = $driver['name'] !== null && $driver['name'] !== '';
        $addItem('bus_assigned', dispatcher_t('readiness.bus_assigned', 'Bus assigned'), true, false, dispatcher_t('readiness.bus_assigned.detail', 'Assigned to :terminal', ['terminal' => ($terminal['terminal_name'] !== '' ? $terminal['terminal_name'] : 'this terminal')]));
        $addItem('driver_assigned', dispatcher_t('readiness.driver_assigned', 'Driver assigned'), $hasDriver, false, $hasDriver ? dispatcher_t('readiness.driver_assigned.detail', 'Driver: :name', ['name' => $driver['name']]) : dispatcher_t('readiness.driver_assigned.missing', 'No driver assigned to this bus'));
        if (!$hasDriver) {
            $addItem('driver_checked_in', dispatcher_t('readiness.driver_check_in', 'Driver check-in'), false, false, dispatcher_t('readiness.driver_check_in.no_driver', 'No driver assigned'));
        } elseif ($checkedInBy === 'dispatcher') {
            $addItem('driver_checked_in', dispatcher_t('readiness.driver_check_in', 'Driver check-in'), true, false, dispatcher_t('readiness.driver_check_in.checked_in_by_dispatcher', 'Dispatcher :name checked in the driver at :time', ['name' => ($checkedInByName !== null && $checkedInByName !== '' ? $checkedInByName : 'Dispatcher'), 'time' => ($checkedInAtLabel !== '' ? $checkedInAtLabel : '—')]));
        } elseif ($checkedIn) {
            $addItem('driver_checked_in', dispatcher_t('readiness.driver_check_in', 'Driver check-in'), true, false, dispatcher_t('readiness.driver_check_in.checked_in', 'Driver checked in at :time', ['time' => ($checkedInAtLabel !== '' ? $checkedInAtLabel : '—')]));
        } else {
            $addItem('driver_checked_in', dispatcher_t('readiness.driver_check_in', 'Driver check-in'), false, false, dispatcher_t('readiness.driver_check_in.pending', 'Driver check-in required.'));
        }
        if ($preparedMatchesDriver) {
            $addItem('bus_present', dispatcher_t('readiness.bus_present', 'Bus present'), true, false, dispatcher_t('readiness.prepared_detail', 'Confirmed during Prepare Trip'));
        } elseif (!$hasLocation) {
            $addItem('bus_present', dispatcher_t('readiness.bus_present', 'Bus present'), false, false, dispatcher_t('readiness.bus_present.not_present', 'Bus is not present at the terminal'));
        } elseif (!$presenceAvailable) {
            $addItem('bus_present', dispatcher_t('readiness.bus_present', 'Bus present'), false, false, dispatcher_t('readiness.bus_present.no_terminal', 'Terminal location not configured'));
        } else {
            $presentDetail = $atTerminal
                ? dispatcher_t('readiness.bus_present.ok', 'At terminal')
                : dispatcher_t('readiness.bus_present.far', 'Bus is not present at the terminal (about :km km away)', ['km' => ($distanceKm !== null ? round($distanceKm, 1) : '?')]);
            $addItem('bus_present', dispatcher_t('readiness.bus_present', 'Bus present'), $atTerminal, false, $presentDetail);
        }
        $fuelGateOk = $fuelValidated || $preparedMatchesDriver;
        $addItem('fuel_validated', dispatcher_t('readiness.fuel_validated', 'Fuel validated'), $fuelGateOk, false, $fuelGateOk ? ($fuelValidated ? dispatcher_t('readiness.fuel_validated.ok', 'Odometer reference available') : dispatcher_t('readiness.prepared_detail', 'Confirmed during Prepare Trip')) : dispatcher_t('readiness.fuel_validated.missing', 'No fuel/odometer reference for this bus'), !$fuelGateOk);
        $addItem('route_assigned', dispatcher_t('readiness.route_assigned', 'Route assigned'), $routeAssigned, false, $routeAssigned ? dispatcher_t('readiness.route_assigned.detail', 'Route: :route', ['route' => $route]) : dispatcher_t('readiness.route_assigned.missing', 'No route assigned to this bus'));

        if ($activeTrip !== null && $activeTripStatus === 'Departed') {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.on_route', 'Bus is currently on route'));
        } elseif ($activeTrip !== null && $activeTripStatus === 'Scheduled') {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.scheduled', 'Bus is already assigned to a scheduled trip today'));
        } elseif ($driverActiveOnOtherBus) {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.driver_busy', 'Driver is already on another active trip today'));
        } elseif (!$destinationValid) {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.no_destination', 'Destination terminal is not set for this bus'));
        } else {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), true, false, dispatcher_t('readiness.trip_valid.ok', 'Ready to dispatch'));
        }

        $skip = $skipMap[$busId] ?? null;
        $skipActive = $skip !== null && !empty($skip['active']);

        $gateItems = array_values(array_filter($readiness, function ($item) {
            return !$item['ok'];
        }));
        $gatePriority = ['route_assigned', 'trip_valid', 'bus_present', 'fuel_validated'];
        $firstGate = null;
        foreach ($gatePriority as $gateKey) {
            foreach ($gateItems as $gateItem) {
                if ($gateItem['key'] === $gateKey) {
                    $firstGate = $gateItem;
                    break 2;
                }
            }
        }

        if ($activeTrip !== null && $activeTripStatus === 'Departed') {
            $status = 'departed';
            $nextAction = null;
            $shortReason = 'Departed' . (!empty($activeTrip['departure_time']) ? ' at ' . substr((string)$activeTrip['departure_time'], 0, 5) : '') . ' • on route';
        } elseif (!$hasDriver) {
            $status = 'waiting_for_driver';
            $nextAction = 'assign_driver';
            $shortReason = dispatcher_t('readiness.driver_assigned.missing', 'No driver assigned to this bus');
        } elseif (!$checkedIn) {
            $status = 'driver_assigned';
            $nextAction = 'check_in';
            $shortReason = dispatcher_t('readiness.driver_check_in.pending', 'Driver check-in required.');
        } elseif (!empty($gateItems)) {
            $status = 'checked_in';
            $nextAction = 'prepare';
            $shortReason = $firstGate !== null ? ($firstGate['detail'] ?: $firstGate['label']) : dispatcher_t('next.prepare_pending', 'Pre-trip requirements are not complete.');
        } else {
            $status = 'ready';
            $nextAction = 'dispatch';
            $shortReason = dispatcher_t('status.ready_reason', 'All required checks completed.');
        }

        $statusLabels = [
            'waiting_for_driver' => dispatcher_t('status.waiting_for_driver', 'WAITING FOR DRIVER'),
            'driver_assigned' => dispatcher_t('status.driver_assigned', 'DRIVER ASSIGNED'),
            'checked_in' => dispatcher_t('status.checked_in', 'CHECKED IN'),
            'ready' => dispatcher_t('status.ready', 'READY TO DISPATCH'),
            'departed' => dispatcher_t('status.on_route', 'ON ROUTE'),
        ];
        $statusLabel = $statusLabels[$status];

        if ($skipActive && $status !== 'departed') {
            $shortReason = dispatcher_t('card.skipped', 'Temporarily skipped: :reason', [
                'reason' => ($skip['reason_label'] !== '' && $skip['reason_label'] !== null ? $skip['reason_label'] : dispatcher_t('card.skipped_reason', 'reason recorded')),
            ]);
        }

        $grouped = [];
        foreach ($readiness as $item) {
            if ($item['ok']) {
                continue;
            }
            $area = $areaMap[$item['key']] ?? 'Other';
            $grouped[$area][] = $item['detail'] ?: $item['label'];
        }
        if ($skipActive) {
            $grouped['Queue'][] = dispatcher_t('card.skipped', 'Temporarily skipped: :reason', [
                'reason' => ($skip['reason_label'] !== '' && $skip['reason_label'] !== null ? $skip['reason_label'] : dispatcher_t('card.skipped_reason', 'reason recorded')),
            ]);
        }
        $reasons = [];
        foreach ($grouped as $area => $items) {
            $reasons[] = ['area' => $area, 'items' => array_values(array_unique($items))];
        }

        $tripInfo = null;
        if ($activeTrip !== null) {
            $tripId = (int)$activeTrip['id'];
            $tripInfo = [
                'id' => $tripId,
                'trip_number' => $activeTrip['trip_number'] ?? null,
                'status' => $activeTripStatus,
                'departure_time' => $activeTrip['departure_time'] ?? null,
                'arrival_time' => $activeTrip['arrival_time'] ?? null,
                'route_name' => $activeTrip['route_name'] ?? $route,
                'total_passengers' => isset($activeTrip['total_passengers']) ? (int)$activeTrip['total_passengers'] : null,
                'dispatcher_name' => $tripData['dispatcher_names'][(string)($activeTrip['dispatcher_id'] ?? '')] ?? null,
                'dispatch_order' => $tripData['orders'][$tripId] ?? null,
                'queue_position' => $position,
                'overtake_reason' => $tripData['overtakes'][$tripId] ?? null,
            ];
        }

        $entries[] = [
            'bus_id' => $busId,
            'plate_number' => $bus['plate_number'] ?? null,
            'bus_type' => $bus['bus_type'] ?? null,
            'seats' => isset($bus['seats']) ? (int)$bus['seats'] : null,
            'queue_position' => $position,
            'cycle' => ($completedCountByBus[$busId] ?? 0) + 1,
            'next_trip_number' => ($completedCountByBus[$busId] ?? 0) + 1,
            'next_action' => $nextAction,
            'passenger_count' => isset($activeTrip['total_passengers']) ? (int)$activeTrip['total_passengers'] : null,
            'schedule' => $schedule !== '' ? $schedule : null,
            'route_name' => $route !== '' ? $route : null,
            'destination' => [
                'terminal_id' => $destinationId,
                'terminal_name' => $destinationName,
            ],
            'driver_id' => $driver['driver_id'],
            'driver_name' => $driver['name'],
            'driver_source' => $driver['source'] ?? null,
            'status' => $status,
            'status_label' => $statusLabel,
            'short_reason' => $shortReason,
            'reasons' => $reasons,
            'readiness' => $readiness,
            'presence' => [
                'available' => $presenceAvailable,
                'checked_in' => $checkedIn,
                'checked_in_by' => $checkedInBy,
                'checked_in_by_name' => $checkedInByName,
                'checked_in_at' => $checkedInAtRaw,
                'at_terminal' => $atTerminal,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
                'updated_at' => $locationUpdatedAt,
                'detail' => $presenceDetail,
            ],
            'fuel_reference' => [
                'validated' => $fuelValidated,
                'starting_odo' => ($fuel !== null && isset($fuel['starting_odo']) && $fuel['starting_odo'] !== null) ? (float)$fuel['starting_odo'] : null,
                'detail' => $fuelValidated ? dispatcher_t('readiness.fuel_validated.ok', 'Odometer reference available') : dispatcher_t('readiness.fuel_validated.missing', 'No fuel/odometer reference for this bus'),
            ],
            'prepared' => [
                'ready' => $preparedMatchesDriver,
                'at' => $prepared['at'] ?? null,
                'by_name' => $prepared['by_name'] ?? null,
            ],
            'skip' => [
                'active' => $skipActive,
                'reason_category' => $skip['reason_category'] ?? null,
                'reason_label' => $skip['reason_label'] ?? null,
                'notes' => $skip['notes'] ?? null,
                'at' => $skip['at'] ?? null,
            ],
            'trip' => $tripInfo,
        ];

        $summary['total']++;
        if ($status === 'departed') {
            $summary['dispatched']++;
        } else {
            $summary['active']++;
            $summary[$status] = ($summary[$status] ?? 0) + 1;
            if ($skipActive) {
                $summary['skipped']++;
            }
        }
    }

    $history = [];
    foreach ($tripData['dispatched'] as $tripRow) {
        $tripId = (int)$tripRow['id'];
        $tripBusId = (string)($tripRow['bus_id'] ?? '');
        $history[] = [
            'trip_id' => $tripId,
            'trip_number' => $tripRow['trip_number'] ?? null,
            'order' => $tripData['orders'][$tripId] ?? null,
            'bus_id' => $tripBusId,
            'driver_id' => (string)($tripRow['driver_id'] ?? ''),
            'driver_name' => $tripData['driver_names'][(string)($tripRow['driver_id'] ?? '')] ?? ($tripRow['driver_id'] ?? null),
            'departure_time' => $tripRow['departure_time'] ?? null,
            'arrival_time' => $tripRow['arrival_time'] ?? null,
            'status' => (string)($tripRow['status'] ?? ''),
            'total_passengers' => isset($tripRow['total_passengers']) ? (int)$tripRow['total_passengers'] : null,
            'queue_position' => $tripData['recorded_positions'][$tripId] ?? ($positionMap[$tripBusId] ?? null),
            'dispatcher_name' => $tripData['dispatcher_names'][(string)($tripRow['dispatcher_id'] ?? '')] ?? null,
            'overtake_reason' => $tripData['overtakes'][$tripId] ?? null,
        ];
    }

    return [
        'terminal' => $terminal,
        'date' => $date,
        'entries' => $entries,
        'history' => $history,
        'summary' => $summary,
        'available_drivers' => dispatcher_available_drivers($conn),
        'server_time' => date('c'),
    ];
}

