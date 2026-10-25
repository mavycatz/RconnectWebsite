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
        if ($conn instanceof \PDO) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $dispatcher_last_sql_error = "Prepare failed";
                return false;
            }
            if (!$stmt->execute(array_values($params))) {
                $dispatcher_last_sql_error = "Execute failed";
                return false;
            }
            return new class($stmt, $conn) {
                private \PDOStatement $stmt;
                private \PDO $pdo;
                private ?array $rows = null;
                public int $affected_rows = 0;
                public int $num_rows = 0;

                public function __construct(\PDOStatement $stmt, \PDO $pdo) {
                    $this->stmt = $stmt;
                    $this->pdo = $pdo;
                    $this->affected_rows = $stmt->rowCount();
                }

                public function get_result() {
                    if ($this->rows === null) {
                        $this->rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        $this->num_rows = count($this->rows);
                    }
                    return new class($this->rows) {
                        private array $rows;
                        private int $index = 0;
                        public int $num_rows;

                        public function __construct(array $rows) {
                            $this->rows = $rows;
                            $this->num_rows = count($rows);
                        }

                        public function fetch_assoc() {
                            if ($this->index < count($this->rows)) {
                                return $this->rows[$this->index++];
                            }
                            return null;
                        }

                        public function fetch_all($mode = null) {
                            return $this->rows;
                        }

                        public function close() {}
                    };
                }

                public function close() {}
            };
        }

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
 * PDO (test) connections delegate to the per-connection column map so separate
 * SQLite fixtures never share a stale answer.
 */
function dispatcher_fuel_logs_columns($conn, $fresh = false)
{
    if ($conn instanceof \PDO) {
        $cols = [];
        foreach (array_keys(dispatcher_table_columns($conn, 'fuel_logs', $fresh)) as $name) {
            $cols[$name] = true;
        }
        return $cols;
    }
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
 * Latest valid historical KPL for a bus from the fuel_logs measurement ledger.
 *
 * A row only counts when its KPL is recorded (> 0) AND the row carries
 * measurement evidence: a recorded ending odometer, a recorded driving
 * distance, a recorded fuel quantity, or a recorded expected consumption.
 * This keeps legacy rows whose only value is the 9.0 dispatch fallback - the
 * value the old dispatch form wrote when no fuel data existed - from being
 * presented as a historical measurement. Returns
 * ['kpl' => float, 'recorded_at' => ?string, 'fuel_log_id' => ?int] or null
 * when no valid historical KPL exists.
 */
function dispatcher_bus_historical_kpl($conn, $bus_id)
{
    $bus_id = trim((string)$bus_id);
    if ($bus_id === '' || !$conn || !dispatcher_table_exists($conn, 'fuel_logs')) {
        return null;
    }
    try {
        $cols = dispatcher_fuel_logs_columns($conn);
        if (empty($cols) || !isset($cols['kpl'])) {
            return null;
        }
        $idCol = dispatcher_fuel_id_column($conn);
        if ($idCol !== null) {
            $orderCol = "`$idCol`";
        } elseif (isset($cols['created_at'])) {
            $orderCol = '`created_at`';
        } else {
            return null;
        }

        $endExpr = dispatcher_fuel_coalesce_expr($cols, ['ending_odo', 'ending_odometer', 'end_km'], '');
        $distanceExpr = dispatcher_fuel_coalesce_expr($cols, ['distance_traveled', 'distance_km'], '', '0');
        $litersExpr = isset($cols['liters']) ? '`liters`' : null;
        $expectedExpr = isset($cols['expected_fuel']) ? '`expected_fuel`' : null;

        $evidence = [];
        if ($endExpr !== 'NULL') {
            $evidence[] = "$endExpr IS NOT NULL";
        }
        $evidence[] = "$distanceExpr > 0";
        if ($litersExpr !== null) {
            $evidence[] = "$litersExpr > 0";
        }
        if ($expectedExpr !== null) {
            $evidence[] = "$expectedExpr IS NOT NULL";
        }

        $idSelect = $idCol !== null ? "`$idCol` AS fuel_log_id" : 'NULL AS fuel_log_id';
        $createdSelect = isset($cols['created_at']) ? '`created_at` AS recorded_at' : 'NULL AS recorded_at';

        $stmt = dispatcher_statement(
            $conn,
            "SELECT kpl, $idSelect, $createdSelect
             FROM fuel_logs
             WHERE bus_id = ? AND kpl > 0 AND (" . implode(' OR ', $evidence) . ")
             ORDER BY $orderCol DESC
             LIMIT 1",
            's',
            [$bus_id]
        );
        if (!$stmt) {
            return null;
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !isset($row['kpl']) || (float)$row['kpl'] <= 0) {
            return null;
        }
        return [
            'kpl' => (float)$row['kpl'],
            'recorded_at' => $row['recorded_at'] ?? null,
            'fuel_log_id' => isset($row['fuel_log_id']) ? (int)$row['fuel_log_id'] : null,
        ];
    } catch (\Throwable $e) {
        error_log("dispatcher_bus_historical_kpl ($bus_id): " . $e->getMessage());
        return null;
    }
}

/**
 * Normalize a dispatch starting-odometer input. Returns the reading as a float
 * when it is a positive number, otherwise null. Dispatch requires a positive
 * reading entered from the bus's actual odometer; a missing, non-numeric,
 * zero, or negative value is never accepted and never defaulted.
 */
function dispatcher_normalize_starting_odo($raw)
{
    if (is_int($raw) || is_float($raw)) {
        $value = (float)$raw;
    } elseif (is_string($raw) && trim($raw) !== '' && is_numeric(trim($raw))) {
        $value = (float)trim($raw);
    } else {
        return null;
    }
    return $value > 0 ? $value : null;
}

/**
 * Value to write into fuel_logs.kpl for a dispatch that recorded no KPL
 * measurement. NULL when the column accepts NULL, otherwise 0. The historical
 * KPL lookup treats both as "no measurement"; the 9.0 fallback is never
 * written.
 */
function dispatcher_fuel_kpl_write_value($conn)
{
    if (!$conn) return 0;
    try {
        if ($conn instanceof \PDO) {
            $driver = (string)$conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $conn->query("PRAGMA table_info(`fuel_logs`)");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    if (strtolower((string)($r['name'] ?? '')) === 'kpl') {
                        return ((int)($r['notnull'] ?? 0) === 0) ? null : 0;
                    }
                }
                return 0;
            }
            $stmt = $conn->query("SHOW COLUMNS FROM `fuel_logs` LIKE 'kpl'");
            if ($stmt && ($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
                return (strtoupper((string)($row['Null'] ?? '')) === 'YES') ? null : 0;
            }
            return 0;
        }
        $res = $conn->query("SHOW COLUMNS FROM `fuel_logs` LIKE 'kpl'");
        if ($res && ($row = $res->fetch_assoc())) {
            $res->close();
            return (strtoupper((string)($row['Null'] ?? '')) === 'YES') ? null : 0;
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_fuel_kpl_write_value: " . $e->getMessage());
    }
    return 0;
}

/**
 * Create the fuel_logs row linked to a dispatch. Records the starting odometer
 * and zero fuel quantities; the ending odometer stays unrecorded until the
 * receiving terminal confirms arrival. Never writes a KPL measurement (NULL
 * where the column accepts it, otherwise 0). Returns the new row id or null.
 */
function dispatcher_insert_dispatch_fuel_log($conn, $bus_id, $driver_id, $trip_id, $starting_odo, $price_per_liter = 0.0)
{
    if (!$conn || !dispatcher_table_exists($conn, 'fuel_logs')) {
        return null;
    }
    try {
        dispatcher_ensure_fuel_schema($conn);
        $cols = dispatcher_fuel_logs_columns($conn);
        if (empty($cols)) {
            return null;
        }

        $columns = [];
        $placeholders = [];
        $types = '';
        $params = [];
        $add = function ($col, $value, $type) use (&$cols, &$columns, &$placeholders, &$types, &$params) {
            if (!isset($cols[$col])) return;
            $columns[] = "`$col`";
            $placeholders[] = '?';
            $params[] = $value;
            $types .= $type;
        };

        $add('bus_id', (string)$bus_id, 's');
        $add('driver_id', (string)$driver_id, 's');
        $add('trip_id', (int)$trip_id, 'i');
        $add('liters', 0.0, 'd');
        $add('cost', 0.0, 'd');
        $add('price_per_liter', (float)$price_per_liter, 'd');

        $startCol = null;
        foreach (['starting_odo', 'starting_odometer', 'start_km'] as $candidate) {
            if (isset($cols[$candidate])) {
                $startCol = $candidate;
                break;
            }
        }
        if ($startCol !== null) {
            $add($startCol, (float)$starting_odo, 'd');
        }

        if (isset($cols['kpl'])) {
            $kplValue = dispatcher_fuel_kpl_write_value($conn);
            $columns[] = '`kpl`';
            $placeholders[] = '?';
            $params[] = $kplValue;
            $types .= 's';
        }

        $add('created_at', date('Y-m-d H:i:s'), 's');

        if (empty($columns)) {
            return null;
        }
        $stmt = dispatcher_statement(
            $conn,
            'INSERT INTO `fuel_logs` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $types,
            $params
        );
        if (!$stmt) {
            return null;
        }
        $insertId = 0;
        if ($conn instanceof \PDO) {
            $insertId = (int)$conn->lastInsertId();
        } else {
            $insertId = (int)$conn->insert_id;
        }
        $stmt->close();
        return $insertId;
    } catch (\Throwable $e) {
        error_log("dispatcher_insert_dispatch_fuel_log ($bus_id): " . $e->getMessage());
        return null;
    }
}

/**
 * Fuel and odometer reference payload for one bus, used by the Dispatch Trip
 * form. `historical_kpl` is the bus's latest valid recorded KPL or null - the
 * form shows "No historical KPL available" and never a fabricated fallback.
 * `suggested_starting_odo` remains a convenience from the last recorded
 * reading; the dispatcher still enters the bus's actual odometer.
 */
function dispatcher_bus_fuel_reference($conn, $bus_id)
{
    $bus_id = trim((string)$bus_id);
    dispatcher_ensure_fuel_schema($conn);
    $price_per_liter = dispatcher_get_current_diesel_price($conn);

    // 1. Latest fuel log for this bus (last known odometer reading).
    $lastLog = null;
    try {
        $fuelCols = dispatcher_fuel_logs_columns($conn);
        $fuelIdCol = dispatcher_fuel_id_column($conn);
        $idSelect = $fuelIdCol !== null ? "$fuelIdCol AS id" : "0 AS id";
        $orderCol = $fuelIdCol !== null ? $fuelIdCol : 'created_at';
        $startExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['starting_odo', 'starting_odometer', 'start_km'], '');
        $endExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['ending_odo', 'ending_odometer', 'end_km'], '');
        $kplExpr = isset($fuelCols['kpl']) ? 'kpl' : 'NULL';
        $litersExpr = isset($fuelCols['liters']) ? 'liters' : '0';
        $costExpr = isset($fuelCols['cost']) ? 'cost' : '0';
        $priceExpr = isset($fuelCols['price_per_liter']) ? 'price_per_liter' : '0';
        $stmt = dispatcher_statement(
            $conn,
            "SELECT $idSelect, $startExpr AS starting_odo, $endExpr AS ending_odo, $kplExpr AS kpl,
                    $litersExpr AS liters, $costExpr AS cost, $priceExpr AS price_per_liter, created_at
             FROM fuel_logs
             WHERE bus_id = ?
             ORDER BY $orderCol DESC LIMIT 1",
            "s",
            [$bus_id]
        );
        if ($stmt) {
            $lastLog = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        $lastLog = null;
    }

    // Suggested starting odometer: the last trip's ending ODO becomes the next
    // starting ODO suggestion (the dispatcher confirms it against the bus).
    $suggested_starting_odo = null;
    if ($lastLog) {
        if (isset($lastLog['ending_odo']) && $lastLog['ending_odo'] !== null && (float)$lastLog['ending_odo'] > 0) {
            $suggested_starting_odo = (float)$lastLog['ending_odo'];
        } elseif (isset($lastLog['starting_odo']) && $lastLog['starting_odo'] !== null && (float)$lastLog['starting_odo'] > 0) {
            $suggested_starting_odo = (float)$lastLog['starting_odo'];
        }
    }

    // Historical KPL: the latest valid recorded measurement, or nothing. The
    // 9.0 dispatch fallback is never presented as a historical value.
    $historical = dispatcher_bus_historical_kpl($conn, $bus_id);
    $historical_kpl = $historical !== null ? (float)$historical['kpl'] : null;
    $historical_kpl_recorded_at = $historical !== null ? ($historical['recorded_at'] ?? null) : null;

    // Current / today's fuel logged for this bus.
    $today = date('Y-m-d');
    $todaySummary = ['total_liters' => 0.0, 'total_cost' => 0.0, 'log_count' => 0];
    try {
        $todayStmt = dispatcher_statement(
            $conn,
            "SELECT COALESCE(SUM(liters), 0) AS total_liters, COALESCE(SUM(cost), 0) AS total_cost, COUNT(*) AS log_count
             FROM fuel_logs
             WHERE bus_id = ? AND DATE(created_at) = ?",
            "ss",
            [$bus_id, $today]
        );
        if ($todayStmt) {
            $r = $todayStmt->get_result()->fetch_assoc();
            $todaySummary = [
                'total_liters' => (float)$r['total_liters'],
                'total_cost' => (float)$r['total_cost'],
                'log_count' => (int)$r['log_count']
            ];
            $todayStmt->close();
        }
    } catch (\Throwable $e) {}

    return [
        'bus_id' => $bus_id,
        'fuel_type' => 'Diesel',
        'price_per_liter' => $price_per_liter,
        'suggested_starting_odo' => $suggested_starting_odo,
        'historical_kpl' => $historical_kpl,
        'historical_kpl_recorded_at' => $historical_kpl_recorded_at,
        // Deprecated alias kept for any cached client code; same value as
        // historical_kpl, never the 9.0 fallback.
        'suggested_kpl' => $historical_kpl,
        'last_log' => $lastLog ? [
            'id' => (int)$lastLog['id'],
            'starting_odo' => $lastLog['starting_odo'] !== null ? (float)$lastLog['starting_odo'] : null,
            'ending_odo' => $lastLog['ending_odo'] !== null ? (float)$lastLog['ending_odo'] : null,
            'kpl' => $lastLog['kpl'] !== null ? (float)$lastLog['kpl'] : null,
            'liters' => (float)$lastLog['liters'],
            'created_at' => $lastLog['created_at']
        ] : null,
        'today_summary' => $todaySummary,
    ];
}

/**
 * Record an arrival report on a Departed trip. The first report wins: later
 * reports are no-ops, so a driver-app GPS report and a staff report cannot
 * overwrite each other. The report never completes the trip and records no
 * odometer. Returns true when this call recorded the report.
 */
function dispatcher_report_trip_arrival($conn, $trip_id, $source = 'staff', $reported_by = null, $reported_at = null)
{
    $trip_id = (int)$trip_id;
    if ($trip_id <= 0 || !$conn || !dispatcher_table_exists($conn, 'bus_trips')) {
        return false;
    }
    try {
        $cols = dispatcher_table_columns($conn, 'bus_trips');
        if (!isset($cols['arrival_reported_at'])) {
            return false;
        }
        $reported_at = $reported_at !== null ? (string)$reported_at : date('Y-m-d H:i:s');
        $stmt = dispatcher_statement(
            $conn,
            "UPDATE bus_trips
             SET arrival_reported_at = ?, arrival_report_source = ?, arrival_reported_by = ?
             WHERE id = ? AND status = 'Departed' AND arrival_reported_at IS NULL",
            'sssi',
            [$reported_at, (string)$source, $reported_by !== null ? (string)$reported_by : null, $trip_id]
        );
        if (!$stmt) {
            return false;
        }
        $recorded = $stmt->affected_rows > 0;
        $stmt->close();
        return $recorded;
    } catch (\Throwable $e) {
        error_log("dispatcher_report_trip_arrival ($trip_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Actual KPL from recorded fuel consumption: distance traveled divided by the
 * recorded liters. Returns null when either figure is missing or not positive,
 * so a KPL is never derived from odometer readings alone.
 */
function dispatcher_derive_actual_kpl($distance, $liters)
{
    $distance = is_numeric($distance) ? (float)$distance : null;
    $liters = is_numeric($liters) ? (float)$liters : null;
    if ($distance === null || $liters === null || $distance <= 0 || $liters <= 0) {
        return null;
    }
    return round($distance / $liters, 2);
}

/**
 * Confirm an arrival: complete the Departed trip and record the actual ending
 * odometer with the distance traveled on its linked fuel record.
 *
 * The ending odometer must be positive and never lower than the recorded
 * starting odometer. Distance is ending minus starting; when the trip has no
 * recorded starting odometer the distance stays unavailable and no starting
 * value is invented. When the linked fuel record already carries recorded
 * liters, the actual KPL (distance / liters) is computed and stored so it
 * becomes the bus's latest KPL reference; expected consumption stays an
 * estimate from the bus's historical KPL only when no actual consumption is
 * recorded.
 *
 * Returns ['success' => bool, 'code' => ?string, 'error' => ?string,
 * 'fuel_data' => ?array].
 */
function dispatcher_confirm_trip_arrival($conn, $trip, $ending_odo, $arrival_time, $total_passengers, $duration_minutes, $confirmed_by, $confirmed_at = null)
{
    if (!is_array($trip) || !$conn) {
        return ['success' => false, 'code' => 'invalid_trip', 'error' => 'Trip record is not available', 'fuel_data' => null];
    }
    $tripId = (int)($trip['id'] ?? 0);
    $busId = (string)($trip['bus_id'] ?? '');
    $driverId = (string)($trip['driver_id'] ?? '');
    if ($tripId <= 0) {
        return ['success' => false, 'code' => 'invalid_trip', 'error' => 'Trip record is not available', 'fuel_data' => null];
    }
    if ((string)($trip['status'] ?? '') !== 'Departed') {
        $alreadyConfirmed = (string)($trip['status'] ?? '') === 'Completed';
        return [
            'success' => false,
            'code' => $alreadyConfirmed ? 'already_confirmed' : 'not_on_route',
            'error' => $alreadyConfirmed
                ? 'This arrival is already confirmed.'
                : 'Only on-route trips can be marked as arrived',
            'fuel_data' => null,
        ];
    }

    $ending = null;
    if (is_int($ending_odo) || is_float($ending_odo)) {
        $ending = (float)$ending_odo;
    } elseif (is_string($ending_odo) && trim($ending_odo) !== '' && is_numeric(trim($ending_odo))) {
        $ending = (float)trim($ending_odo);
    }
    if ($ending === null || $ending <= 0) {
        return ['success' => false, 'code' => 'ending_odo_required', 'error' => 'A positive ending odometer (km) is required to confirm the arrival.', 'fuel_data' => null];
    }

    $inTransaction = false;
    try {
        dispatcher_ensure_fuel_schema($conn);
        $fuelCols = dispatcher_fuel_logs_columns($conn);
        $fuelIdCol = dispatcher_fuel_id_column($conn);
        $startExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['starting_odo', 'starting_odometer', 'start_km'], '');
        $endCol = null;
        foreach (['ending_odo', 'ending_odometer', 'end_km'] as $candidate) {
            if (isset($fuelCols[$candidate])) { $endCol = $candidate; break; }
        }
        $distCol = null;
        foreach (['distance_traveled', 'distance_km'] as $candidate) {
            if (isset($fuelCols[$candidate])) { $distCol = $candidate; break; }
        }
        $expectedCol = isset($fuelCols['expected_fuel']) ? 'expected_fuel' : null;

        $linked = null;
        if ($fuelIdCol !== null && isset($fuelCols['trip_id'])) {
            $litersExpr = isset($fuelCols['liters']) ? '`liters`' : 'NULL';
            $stmt = dispatcher_statement(
                $conn,
                "SELECT `$fuelIdCol` AS fuel_log_id, $startExpr AS starting_odo, $litersExpr AS liters FROM fuel_logs WHERE trip_id = ? ORDER BY `$fuelIdCol` DESC LIMIT 1",
                'i',
                [$tripId]
            );
            if ($stmt) {
                $linked = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
        $starting = null;
        if ($linked !== null && isset($linked['starting_odo']) && $linked['starting_odo'] !== null && is_numeric($linked['starting_odo'])) {
            $starting = (float)$linked['starting_odo'];
            if ($starting <= 0) {
                $starting = null;
            }
        }
        $linkedLiters = null;
        if ($linked !== null && isset($linked['liters']) && $linked['liters'] !== null && is_numeric($linked['liters'])) {
            $linkedLiters = (float)$linked['liters'];
            if ($linkedLiters <= 0) {
                $linkedLiters = null;
            }
        }

        if ($starting !== null && $ending < $starting) {
            return [
                'success' => false,
                'code' => 'ending_odo_below_starting',
                'error' => 'The ending odometer cannot be lower than the recorded starting odometer ('
                    . rtrim(rtrim(number_format($starting, 2, '.', ''), '0'), '.') . ' km).',
                'fuel_data' => null,
            ];
        }

        $distance = $starting !== null ? round($ending - $starting, 2) : null;
        $actualKpl = dispatcher_derive_actual_kpl($distance, $linkedLiters);
        $historical = $busId !== '' ? dispatcher_bus_historical_kpl($conn, $busId) : null;
        $historicalKpl = $historical !== null ? (float)$historical['kpl'] : null;
        $expectedFuel = ($actualKpl === null && $distance !== null && $historicalKpl !== null && $historicalKpl > 0)
            ? round($distance / $historicalKpl, 2)
            : null;

        /* Only the record writes are transactional: schema self-healing above
           may issue DDL, which implicitly commits on MySQL. */
        if (method_exists($conn, 'begin_transaction')) {
            try {
                $conn->begin_transaction();
                $inTransaction = true;
            } catch (\Throwable $e) {
                $inTransaction = false;
            }
        }

        if ($linked !== null && $fuelIdCol !== null) {
            $sets = [];
            $updateTypes = '';
            $updateParams = [];
            if ($endCol !== null) { $sets[] = "`$endCol` = ?"; $updateParams[] = $ending; $updateTypes .= 'd'; }
            if ($distCol !== null) { $sets[] = "`$distCol` = ?"; $updateParams[] = $distance; $updateTypes .= 'd'; }
            if ($expectedCol !== null) { $sets[] = "`$expectedCol` = ?"; $updateParams[] = $expectedFuel; $updateTypes .= 'd'; }
            if ($actualKpl !== null && isset($fuelCols['kpl'])) { $sets[] = '`kpl` = ?'; $updateParams[] = $actualKpl; $updateTypes .= 'd'; }
            if (!empty($sets)) {
                $updateParams[] = (int)$linked['fuel_log_id'];
                $updateTypes .= 'i';
                $stmt = dispatcher_statement(
                    $conn,
                    'UPDATE fuel_logs SET ' . implode(', ', $sets) . " WHERE `$fuelIdCol` = ?",
                    $updateTypes,
                    $updateParams
                );
                if (!$stmt) {
                    if ($inTransaction) { $conn->rollback(); }
                    return ['success' => false, 'code' => 'fuel_update_failed', 'error' => 'The arrival odometer could not be recorded.', 'fuel_data' => null];
                }
                $stmt->close();
            }
        } elseif (!empty($fuelCols)) {
            /* Trips that started outside the dispatcher flow have no linked
               fuel record; create one so the recorded arrival odometer is
               persisted. No starting value is invented. */
            $columns = [];
            $placeholders = [];
            $insertTypes = '';
            $insertParams = [];
            $insert = function ($col, $value, $type) use (&$fuelCols, &$columns, &$placeholders, &$insertTypes, &$insertParams) {
                if (!isset($fuelCols[$col])) return;
                $columns[] = "`$col`";
                $placeholders[] = '?';
                $insertParams[] = $value;
                $insertTypes .= $type;
            };
            $insert('bus_id', $busId, 's');
            $insert('driver_id', $driverId, 's');
            $insert('trip_id', $tripId, 'i');
            $insert('liters', 0.0, 'd');
            $insert('cost', 0.0, 'd');
            if ($endCol !== null) { $insert($endCol, $ending, 'd'); }
            if ($distCol !== null) { $insert($distCol, $distance, 'd'); }
            if ($expectedCol !== null) { $insert($expectedCol, $expectedFuel, 'd'); }
            if (isset($fuelCols['kpl'])) {
                $columns[] = '`kpl`';
                $placeholders[] = '?';
                $insertParams[] = dispatcher_fuel_kpl_write_value($conn);
                $insertTypes .= 's';
            }
            $insert('created_at', date('Y-m-d H:i:s'), 's');
            if (!empty($columns)) {
                $stmt = dispatcher_statement(
                    $conn,
                    'INSERT INTO `fuel_logs` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
                    $insertTypes,
                    $insertParams
                );
                if (!$stmt) {
                    if ($inTransaction) { $conn->rollback(); }
                    return ['success' => false, 'code' => 'fuel_update_failed', 'error' => 'The arrival odometer could not be recorded.', 'fuel_data' => null];
                }
                $stmt->close();
            }
        }

        /* Complete the trip with the confirmation stamps. The status guard
           makes a concurrent duplicate confirmation a conflict, not a race. */
        $tripCols = dispatcher_table_columns($conn, 'bus_trips');
        $confirmed_at = $confirmed_at !== null ? (string)$confirmed_at : date('Y-m-d H:i:s');
        $sets = ["status = 'Completed'"];
        $completeTypes = '';
        $completeParams = [];
        if (isset($tripCols['arrival_time'])) { $sets[] = 'arrival_time = ?'; $completeParams[] = (string)$arrival_time; $completeTypes .= 's'; }
        if (isset($tripCols['total_passengers'])) { $sets[] = 'total_passengers = ?'; $completeParams[] = (int)$total_passengers; $completeTypes .= 'i'; }
        if (isset($tripCols['duration_minutes'])) { $sets[] = 'duration_minutes = ?'; $completeParams[] = (int)$duration_minutes; $completeTypes .= 'i'; }
        if (isset($tripCols['arrival_confirmed_at'])) { $sets[] = 'arrival_confirmed_at = ?'; $completeParams[] = $confirmed_at; $completeTypes .= 's'; }
        if (isset($tripCols['arrival_confirmed_by'])) { $sets[] = 'arrival_confirmed_by = ?'; $completeParams[] = $confirmed_by !== null ? (string)$confirmed_by : null; $completeTypes .= 's'; }
        $completeParams[] = $tripId;
        $completeTypes .= 'i';
        $stmt = dispatcher_statement(
            $conn,
            'UPDATE bus_trips SET ' . implode(', ', $sets) . " WHERE id = ? AND status = 'Departed'",
            $completeTypes,
            $completeParams
        );
        if (!$stmt || $stmt->affected_rows === 0) {
            if ($stmt) { $stmt->close(); }
            if ($inTransaction) { $conn->rollback(); }
            return ['success' => false, 'code' => 'already_confirmed', 'error' => 'This arrival is already confirmed.', 'fuel_data' => null];
        }
        $stmt->close();

        if ($inTransaction) {
            $conn->commit();
        }

        return [
            'success' => true,
            'code' => null,
            'error' => null,
            'fuel_data' => [
                'starting_odo' => $starting,
                'ending_odo' => $ending,
                'kpl' => $historicalKpl,
                'actual_kpl' => $actualKpl,
                'liters' => $linkedLiters,
                'distance_traveled' => $distance,
                'expected_fuel' => $expectedFuel,
            ],
        ];
    } catch (\Throwable $e) {
        if ($inTransaction) {
            try { $conn->rollback(); } catch (\Throwable $e2) {}
        }
        error_log("dispatcher_confirm_trip_arrival ($tripId): " . $e->getMessage());
        return ['success' => false, 'code' => 'confirm_failed', 'error' => 'The arrival could not be confirmed.', 'fuel_data' => null];
    }
}

/**
 * Derived arrival status for a trip record:
 * - 'confirmed': the arrival is confirmed (completed trip);
 * - 'arrived_awaiting_confirmation': a Departed trip with a reported arrival;
 * - 'on_route': a Departed trip without a report;
 * - 'not_on_route': any other state.
 */
function dispatcher_trip_arrival_status($trip)
{
    if (!is_array($trip)) {
        return 'not_on_route';
    }
    $status = (string)($trip['status'] ?? '');
    if ($status === 'Completed' || !empty($trip['arrival_confirmed_at'])) {
        return 'confirmed';
    }
    if ($status !== 'Departed') {
        return 'not_on_route';
    }
    return !empty($trip['arrival_reported_at']) ? 'arrived_awaiting_confirmation' : 'on_route';
}

/**
 * Compute an audited fuel/odometer correction for a trip's fuel record.
 *
 * Odometer-only corrections are supported: the starting odometer is required,
 * the ending odometer is optional but never lower than the starting reading,
 * liters and KPL are optional, and no KPL is inferred when neither is
 * provided - an existing recorded KPL is retained, otherwise no measurement
 * value is written (never the 9.0 fallback). The edit reason stays mandatory
 * when editing an existing record. Returns
 * ['success' => bool, 'code' => ?string, 'error' => ?string,
 * 'reason' => ?string, 'values' => ?array].
 */
function dispatcher_trip_fuel_correction($conn, $existing, array $data)
{
    $price_per_liter = ($existing && isset($existing['price_per_liter']) && (float)$existing['price_per_liter'] > 0)
        ? (float)$existing['price_per_liter']
        : dispatcher_get_current_diesel_price($conn);

    $starting_odo = (isset($data['starting_odo']) && $data['starting_odo'] !== '')
        ? (float)$data['starting_odo']
        : ($existing ? (float)($existing['starting_odo'] ?? $existing['starting_odometer'] ?? $existing['start_km'] ?? 0) : 0.0);

    $existingEnd = $existing ? ($existing['ending_odo'] ?? $existing['ending_odometer'] ?? $existing['end_km'] ?? null) : null;
    $ending_odo = (isset($data['ending_odo']) && $data['ending_odo'] !== '')
        ? (float)$data['ending_odo']
        : ($existing && $existingEnd !== null ? (float)$existingEnd : null);

    $existingKpl = ($existing && isset($existing['kpl']) && (float)$existing['kpl'] > 0) ? (float)$existing['kpl'] : 0.0;
    $kplProvided = isset($data['kpl']) && $data['kpl'] !== '' && (float)$data['kpl'] > 0;

    $actual_liters = (isset($data['actual_liters']) && $data['actual_liters'] !== '')
        ? max(0.0, (float)$data['actual_liters'])
        : ($existing ? (float)($existing['liters'] ?? 0) : 0.0);

    if ($starting_odo <= 0) {
        return ['success' => false, 'code' => 'starting_odo_required', 'error' => 'Starting ODO is required', 'reason' => null, 'values' => null];
    }

    $reason = trim((string)($data['reason'] ?? ''));
    if ($existing && $reason === '') {
        return ['success' => false, 'code' => 'reason_required', 'error' => 'A reason is required when editing a fuel record', 'reason' => null, 'values' => null];
    }

    $distance_traveled = null;
    if ($ending_odo !== null) {
        if ($ending_odo < $starting_odo) {
            return [
                'success' => false,
                'code' => 'ending_odo_below_starting',
                'error' => "Ending ODO ($ending_odo KM) cannot be less than Starting ODO ($starting_odo KM)",
                'reason' => null,
                'values' => null,
            ];
        }
        $distance_traveled = round($ending_odo - $starting_odo, 2);
    }

    /* KPL: an explicit value wins; recorded liters with a recorded distance
       produce the actual KPL (distance / liters); otherwise the existing
       recorded KPL is retained and no measurement is inferred. Expected
       consumption is an estimate and is only produced when no actual
       consumption is recorded. */
    $actualKpl = dispatcher_derive_actual_kpl($distance_traveled, $actual_liters);
    $kplBasis = 0.0;
    $kpl = dispatcher_fuel_kpl_write_value($conn);
    if ($kplProvided) {
        $kpl = (float)$data['kpl'];
        $kplBasis = $kpl;
    } elseif ($actualKpl !== null) {
        $kpl = $actualKpl;
    } elseif ($existingKpl > 0) {
        $kpl = $existingKpl;
        $kplBasis = $existingKpl;
    }
    $expected_fuel = ($kplBasis > 0 && $distance_traveled !== null)
        ? round($distance_traveled / $kplBasis, 2)
        : null;

    return [
        'success' => true,
        'code' => null,
        'error' => null,
        'reason' => $reason,
        'values' => [
            'starting_odo' => $starting_odo,
            'ending_odo' => $ending_odo,
            'kpl' => $kpl,
            'kpl_basis' => $kplBasis,
            'actual_kpl' => $actualKpl,
            'distance_traveled' => $distance_traveled,
            'expected_fuel' => $expected_fuel,
            'liters' => $actual_liters,
            'cost' => round($actual_liters * $price_per_liter, 2),
            'price_per_liter' => $price_per_liter,
        ],
    ];
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
 * Idempotent schema self-heal for the two-step arrival lifecycle. The arrival
 * report and confirmation markers are additive `bus_trips` columns; a reported
 * arrival leaves the trip in `Departed` and only staff confirmation completes
 * it, so no status enum change is involved. Safe to call on every request:
 * a no-op once the columns exist.
 */
function dispatcher_ensure_arrival_schema($conn)
{
    static $ensured = false;
    if ($ensured || !$conn) return $ensured;
    $ensured = true;
    try {
        if (!dispatcher_table_exists($conn, 'bus_trips')) {
            return true;
        }
        $defs = [
            'arrival_reported_at'   => 'DATETIME DEFAULT NULL',
            'arrival_report_source' => 'VARCHAR(32) DEFAULT NULL',
            'arrival_reported_by'   => 'VARCHAR(64) DEFAULT NULL',
            'arrival_confirmed_at'  => 'DATETIME DEFAULT NULL',
            'arrival_confirmed_by'  => 'VARCHAR(64) DEFAULT NULL',
        ];
        $cols = dispatcher_table_columns($conn, 'bus_trips');
        $added = false;
        foreach ($defs as $col => $ddl) {
            if (!isset($cols[$col])) {
                try {
                    $conn->query("ALTER TABLE `bus_trips` ADD COLUMN `$col` $ddl");
                    $added = true;
                } catch (\Throwable $e) {
                    error_log("dispatcher_ensure_arrival_schema ($col): " . $e->getMessage());
                }
            }
        }
        if ($added) {
            dispatcher_table_columns($conn, 'bus_trips', true);
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_ensure_arrival_schema error: " . $e->getMessage());
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
 * Cached table existence check for the shared dispatcher schema. The cache is
 * per connection (WeakMap), so separate connections - as used by tests and
 * multi-database setups - never share a stale answer.
 */
function dispatcher_table_exists($conn, $table)
{
    static $cache = null;
    if ($cache === null) {
        $cache = new \WeakMap();
    }
    $key = strtolower((string)$table);
    if (!is_object($conn)) {
        return false;
    }
    $perConn = isset($cache[$conn]) ? $cache[$conn] : [];
    if (array_key_exists($key, $perConn)) {
        return $perConn[$key];
    }
    $exists = false;
    try {
        if ($conn instanceof \PDO) {
            $driver = (string)$conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([(string)$table]);
                $exists = (bool)$stmt->fetchColumn();
            } else {
                $exists = (bool)$conn->query("SHOW TABLES LIKE " . $conn->quote((string)$table))->fetchColumn();
            }
        } else {
            $safe = method_exists($conn, 'real_escape_string') ? $conn->real_escape_string((string)$table) : addslashes((string)$table);
            $res = $conn->query("SHOW TABLES LIKE '$safe'");
            $exists = $res && $res->num_rows > 0;
            if ($res && method_exists($res, 'close')) {
                $res->close();
            }
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_table_exists($table): " . $e->getMessage());
    }
    $perConn[$key] = $exists;
    $cache[$conn] = $perConn;
    return $exists;
}

/**
 * Cached column map (lowercased name => real name) for a table, cached per
 * connection (WeakMap) so separate connections never share a stale map.
 * Pass $fresh = true to re-read after an additive schema change on the same
 * connection (the cache would otherwise keep the pre-migration map).
 */
function dispatcher_table_columns($conn, $table, $fresh = false)
{
    static $cache = null;
    if ($cache === null) {
        $cache = new \WeakMap();
    }
    $key = strtolower((string)$table);
    if (!is_object($conn)) {
        return [];
    }
    $perConn = isset($cache[$conn]) ? $cache[$conn] : [];
    if ($fresh) {
        unset($perConn[$key]);
    }
    if (array_key_exists($key, $perConn)) {
        return $perConn[$key];
    }
    $cols = [];
    try {
        if ($conn instanceof \PDO) {
            $driver = (string)$conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cleanTable = str_replace(['`', '"', "'"], '', (string)$table);
                $stmt = $conn->query("PRAGMA table_info(`$cleanTable`)");
                if ($stmt) {
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $cols[strtolower((string)($r['name'] ?? ''))] = (string)($r['name'] ?? '');
                    }
                }
            } else {
                $cleanTable = str_replace('`', '', (string)$table);
                $stmt = $conn->query("SHOW COLUMNS FROM `$cleanTable`");
                if ($stmt) {
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $cols[strtolower((string)($r['Field'] ?? ''))] = (string)($r['Field'] ?? '');
                    }
                }
            }
        } else {
            $safe = str_replace('`', '', (string)$table);
            $res = $conn->query("SHOW COLUMNS FROM `$safe`");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $cols[strtolower($r['Field'])] = $r['Field'];
                }
                if (method_exists($res, 'close')) {
                    $res->close();
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_table_columns($table): " . $e->getMessage());
    }
    $perConn[$key] = $cols;
    $cache[$conn] = $perConn;
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
    /* Arrival radius shared with the driver app's GPS arrival report: a bus
       whose last reported location is within 3 km of a terminal is treated as
       being at that terminal. */
    return 3.0;
}

/**
 * Nearest terminal to a point within a radius, from terminal rows carrying
 * coordinates. Pure function so the radius behavior is unit-testable.
 *
 * @param array<int,array<string,mixed>> $terminals Rows with id, latitude,
 *        longitude and an optional terminal_name.
 * @return array{terminal_id:int,terminal_name:string,distance_km:float}|null
 */
function dispatcher_nearest_terminal_within_km(array $terminals, $latitude, $longitude, $radiusKm)
{
    if (!is_numeric($latitude) || !is_numeric($longitude) || !is_numeric($radiusKm)) {
        return null;
    }

    $best = null;
    foreach ($terminals as $terminal) {
        $terminalId = (int)($terminal['id'] ?? 0);
        $distance = dispatcher_queue_distance_km(
            $latitude,
            $longitude,
            $terminal['latitude'] ?? null,
            $terminal['longitude'] ?? null
        );
        if ($terminalId <= 0 || $distance === null || $distance > (float)$radiusKm) {
            continue;
        }
        if ($best === null || $distance < $best['distance_km']) {
            $best = [
                'terminal_id' => $terminalId,
                'terminal_name' => (string)($terminal['terminal_name'] ?? ''),
                'distance_km' => $distance,
            ];
        }
    }

    return $best;
}

/**
 * Active terminals that carry coordinates (schema-adaptive).
 *
 * @return array<int,array<string,mixed>>
 */
function dispatcher_terminals_with_coordinates($conn)
{
    $terminals = [];
    if (!dispatcher_table_exists($conn, 'terminals')) {
        return $terminals;
    }
    $cols = dispatcher_table_columns($conn, 'terminals');
    if (!isset($cols['id'], $cols['latitude'], $cols['longitude'])) {
        return $terminals;
    }
    $nameExpr = isset($cols['terminal_name']) ? 'terminal_name' : "''";
    $where = 'latitude IS NOT NULL AND longitude IS NOT NULL';
    if (isset($cols['status'])) {
        $where = "status = 'Active' AND " . $where;
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT id, $nameExpr AS terminal_name, latitude, longitude FROM terminals WHERE $where"
    );
    if ($stmt) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $terminals[] = $row;
        }
        $stmt->close();
    }

    return $terminals;
}

/**
 * Terminal a bus is physically at, from its driver's latest reported location
 * within the arrival radius. 0 when no usable location or no terminal is near.
 */
function dispatcher_arrival_terminal_from_location($conn, $driverId)
{
    $driverId = trim((string)$driverId);
    if ($driverId === '') {
        return 0;
    }

    $locations = dispatcher_queue_location_map($conn, [$driverId]);
    $location = $locations[$driverId] ?? null;
    if (!is_array($location)
        || !is_numeric($location['latitude'] ?? null)
        || !is_numeric($location['longitude'] ?? null)) {
        return 0;
    }

    $nearest = dispatcher_nearest_terminal_within_km(
        dispatcher_terminals_with_coordinates($conn),
        $location['latitude'],
        $location['longitude'],
        dispatcher_queue_proximity_km()
    );

    return $nearest !== null ? (int)$nearest['terminal_id'] : 0;
}

/**
 * Whether a trip row is inbound to a terminal: its recorded destination is
 * that terminal, or - when no destination is recorded - the trip is on route
 * (Departed) and can be received by any terminal. A trip whose destination is
 * a different terminal is never inbound.
 *
 * @param array<string,mixed> $trip
 */
function dispatcher_trip_is_inbound(array $trip, $terminalId)
{
    $terminalId = (int)$terminalId;
    if ($terminalId <= 0) {
        return false;
    }

    $destination = (int)($trip['to_terminal_id'] ?? 0);
    if ($destination === $terminalId) {
        return true;
    }

    return $destination <= 0 && (string)($trip['status'] ?? '') === 'Departed';
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
        $stmt = dispatcher_statement($conn, "SELECT id, terminal_name FROM terminals");
        if ($stmt) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['id']] = (string)$row['terminal_name'];
            }
            $stmt->close();
        }
    } catch (\Throwable $e) {
        error_log("dispatcher_queue_terminal_names: " . $e->getMessage());
    }
    return $map;
}

/**
 * Cached set of active terminal ids (id => true), keyed by connection. Empty
 * when the terminals table is unavailable, in which case callers treat any
 * recorded terminal id as valid and behavior is unchanged.
 *
 * @return array<int,bool>
 */
function dispatcher_active_terminal_ids($conn)
{
    static $cache = [];
    $key = is_object($conn) ? spl_object_id($conn) : 0;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $ids = [];
    if (dispatcher_table_exists($conn, 'terminals')) {
        $cols = dispatcher_table_columns($conn, 'terminals');
        if (isset($cols['id'])) {
            $where = isset($cols['status']) ? " WHERE status = 'Active'" : '';
            $stmt = dispatcher_statement($conn, "SELECT id FROM terminals$where");
            if ($stmt) {
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        if ($id > 0) {
                            $ids[$id] = true;
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
    $cache[$key] = $ids;

    return $ids;
}

/**
 * The terminal a bus currently belongs to, constrained to a real active
 * terminal: the trip-derived terminal when it is an active terminal, otherwise
 * the bus's configured origin when that is active, otherwise 0 (unknown). A
 * stale terminal id (for example a retired Del Pilar/Valdefuente terminal) is
 * never used to place the bus outside the configured terminals.
 */
function dispatcher_bus_current_terminal_id($conn, array $bus, $busId, array $tripData)
{
    $terminalId = dispatcher_queue_bus_terminal_id($bus, $busId, $tripData);
    $active = dispatcher_active_terminal_ids($conn);
    if (empty($active)) {
        return $terminalId;
    }
    if ($terminalId > 0 && isset($active[$terminalId])) {
        return $terminalId;
    }
    $configuredOrigin = (int)($bus['from_terminal_id'] ?? 0);
    if ($configuredOrigin > 0 && isset($active[$configuredOrigin])) {
        return $configuredOrigin;
    }

    return 0;
}

/**
 * Whether a bus currently belongs to a terminal's queue: at its derived
 * current terminal, or - when no active terminal can be derived - at the
 * starting terminal, the only terminal that holds unassigned buses.
 */
function dispatcher_bus_belongs_to_terminal($conn, array $bus, $busId, array $tripData, $terminalId, $startTerminal = 0)
{
    $terminalId = (int)$terminalId;
    $startTerminal = (int)$startTerminal;
    $busTerminal = dispatcher_bus_current_terminal_id($conn, $bus, $busId, $tripData);
    if ($busTerminal <= 0) {
        return $startTerminal > 0 && $terminalId === $startTerminal;
    }

    return $busTerminal === $terminalId;
}

/**
 * The terminal a bus has actually entered for the operating date: the origin
 * of an active trip, the receiving terminal of a confirmed arrival today, its
 * operating-date queue assignment, or the last trip's terminal on any date.
 * 0 when the bus has never entered a queue - only its configured origin is
 * known - so dispatch can require an explicit terminal assignment first.
 */
function dispatcher_bus_confirmed_terminal_id(array $bus, $busId, array $tripData)
{
    $busId = (string)$busId;
    $active = $tripData['by_bus'][$busId] ?? null;
    if (is_array($active)) {
        $from = (int)($active['from_terminal_id'] ?? 0);
        if ($from > 0) {
            return $from;
        }
    }
    $completed = $tripData['last_completed_by_bus'][$busId] ?? null;
    if (is_array($completed)) {
        $to = (int)($completed['to_terminal_id'] ?? 0);
        if ($to > 0) {
            return $to;
        }
    }
    $assigned = (int)($tripData['assigned_terminal_by_bus'][$busId] ?? 0);
    if ($assigned > 0) {
        return $assigned;
    }
    $latest = $tripData['latest_trip_by_bus'][$busId] ?? null;
    if (is_array($latest)) {
        $status = strtolower((string)($latest['status'] ?? ''));
        $terminal = $status === 'completed'
            ? (int)($latest['to_terminal_id'] ?? 0)
            : (int)($latest['from_terminal_id'] ?? 0);
        if ($terminal > 0) {
            return $terminal;
        }
    }

    return 0;
}

/**
 * The driver's effective terminal for the operating date: the origin of an
 * active trip, otherwise the per-date driver terminal assignment, otherwise
 * the terminal of the bus the driver is dispatched with. 0 = not determinable.
 */
function dispatcher_driver_effective_terminal_id($conn, $driverId, array $busRow, $busId, array $tripData, $date = null)
{
    $driverId = trim((string)$driverId);
    if ($driverId === '') {
        return 0;
    }
    $active = $tripData['by_driver'][$driverId] ?? null;
    if (is_array($active)) {
        $from = (int)($active['from_terminal_id'] ?? 0);
        if ($from > 0) {
            return $from;
        }
    }
    $assigned = dispatcher_driver_day_terminal_id($conn, $driverId, $date);
    if ($assigned > 0) {
        return $assigned;
    }

    return dispatcher_bus_current_terminal_id($conn, $busRow, $busId, $tripData);
}

/**
 * The driver's per-operating-date terminal assignment, 0 when none exists or
 * the table/columns are unavailable (schema-adaptive).
 */
function dispatcher_driver_day_terminal_id($conn, $driverId, $date = null)
{
    $driverId = trim((string)$driverId);
    $date = (string)($date ?: date('Y-m-d'));
    if ($driverId === '' || $date === '' || !dispatcher_table_exists($conn, 'driver_terminal_assignments')) {
        return 0;
    }
    $cols = dispatcher_table_columns($conn, 'driver_terminal_assignments');
    if (!isset($cols['driver_id'], $cols['terminal_id'], $cols['operating_date'])) {
        return 0;
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = ? AND driver_id = ? LIMIT 1",
        'ss',
        [$date, $driverId]
    );
    if (!$stmt) {
        return 0;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? max(0, (int)($row['terminal_id'] ?? 0)) : 0;
}

/**
 * Buses for the dispatcher queue, schema-adaptive.
 *
 * With an Admin-published master queue for the date this returns exactly the
 * published buses in the published order, regardless of the bus's home
 * terminal - one master queue for both terminals (Rizal and Cabanatuan), so
 * the two dispatchers never see competing orders. Without a publication the
 * legacy terminal-scoped active-bus list is used.
 */
function dispatcher_queue_bus_list($conn, $terminalId, $date = null)
{
    if (!dispatcher_table_exists($conn, 'buses')) {
        return [];
    }
    $date = $date ?: date('Y-m-d');
    $published = dispatcher_queue_published_positions($conn, $date);

    $cols = dispatcher_table_columns($conn, 'buses');
    if (!isset($cols['bus_id'])) {
        return [];
    }
    $select = ['bus_id'];
    foreach (['plate_number', 'bus_type', 'seats', 'route_name', 'schedule', 'status', 'driver', 'to_terminal_id', 'from_terminal_id', 'operational_status'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }

    /* Published master queue: the fleet order is authoritative across both
       terminals, so the home terminal is not used to filter it. */
    if (!empty($published)) {
        $busIds = array_values(array_filter(array_map('strval', array_keys($published)), function ($value) {
            return $value !== '';
        }));
        if (empty($busIds)) {
            return [];
        }
        $where = 'bus_id IN (' . implode(',', array_fill(0, count($busIds), '?')) . ')';
        if (isset($cols['status'])) {
            /* Only clearly retired buses are excluded; NULL/empty and legacy
               statuses are dispatchable, matching the Admin queue. */
            $where .= " AND (status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) NOT IN ('archived','inactive'))";
        }
        $stmt = dispatcher_statement(
            $conn,
            'SELECT ' . implode(', ', $select) . " FROM buses WHERE $where",
            str_repeat('s', count($busIds)),
            $busIds
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
        usort($rows, function ($a, $b) use ($published) {
            $aPos = (int)($published[(string)($a['bus_id'] ?? '')] ?? 0);
            $bPos = (int)($published[(string)($b['bus_id'] ?? '')] ?? 0);

            return $aPos <=> $bPos;
        });

        return $rows;
    }

    /* Legacy mode (no published order for the date): terminal-scoped list. */
    if (!isset($cols['from_terminal_id'])) {
        return [];
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
 * Every dispatchable fleet row (schema-adaptive). Archived/inactive buses are
 * excluded; NULL/empty and legacy statuses stay dispatchable like the Admin
 * queue.
 *
 * @return array<int,array<string,mixed>>
 */
function dispatcher_queue_fleet_rows($conn)
{
    if (!dispatcher_table_exists($conn, 'buses')) {
        return [];
    }
    $cols = dispatcher_table_columns($conn, 'buses');
    if (!isset($cols['bus_id'])) {
        return [];
    }
    $select = ['bus_id'];
    foreach (['plate_number', 'bus_type', 'seats', 'route_name', 'schedule', 'status', 'driver', 'to_terminal_id', 'from_terminal_id', 'operational_status'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }
    $where = '1=1';
    if (isset($cols['status'])) {
        $where .= " AND (status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) NOT IN ('archived','inactive'))";
    }
    $stmt = dispatcher_statement($conn, 'SELECT ' . implode(', ', $select) . " FROM buses WHERE $where");
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
 * The starting terminal for initial trips (Rizal / Poblacion). Falls back to
 * the lowest terminal id; 0 when terminals are unavailable. Used for the
 * home-terminal fallback of buses with no recorded terminal.
 */
function dispatcher_queue_start_terminal($conn)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 0;
    if (!dispatcher_table_exists($conn, 'terminals')) {
        return $cached;
    }
    $cols = dispatcher_table_columns($conn, 'terminals');
    if (!isset($cols['id'])) {
        return $cached;
    }
    $select = isset($cols['terminal_name']) ? 'id, terminal_name' : 'id';
    $stmt = dispatcher_statement($conn, "SELECT $select FROM terminals ORDER BY id ASC");
    if (!$stmt) {
        return $cached;
    }
    $res = $stmt->get_result();
    $first = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if ($first === 0) {
            $first = $id;
        }
        $name = strtolower(trim((string)($row['terminal_name'] ?? '')));
        if ($name !== '' && (strpos($name, 'rizal') !== false || strpos($name, 'poblacion') !== false)) {
            $cached = $id;
            $stmt->close();
            return $cached;
        }
    }
    $stmt->close();
    $cached = $first;

    return $cached;
}

/**
 * The Admin-published order when it is official for a terminal. The master
 * queue spans both terminals, so the published sequence is official at every
 * terminal: waiting buses there follow the Admin's order, while returning
 * buses still join the back of their terminal's queue FIFO.
 *
 * @return array{published_at:string,published_by:string}|null
 */
function dispatcher_queue_admin_lock($conn, $terminalId, $date)
{
    /* $terminalId kept for call-site compatibility; the master order is not
       terminal-scoped. */
    return dispatcher_queue_admin_publication($conn, $date);
}

/**
 * The terminal a bus currently belongs to for the operating date: its active
 * trip's origin, otherwise its last completed trip's arrival terminal,
 * otherwise the most recent trip on any date (an in-progress trip's origin or
 * a completed trip's arrival), otherwise its configured home terminal.
 */
function dispatcher_queue_bus_terminal_id(array $bus, $busId, array $tripData)
{
    $busId = (string)$busId;
    $active = $tripData['by_bus'][$busId] ?? null;
    if (is_array($active)) {
        $from = (int)($active['from_terminal_id'] ?? 0);
        if ($from > 0) {
            return $from;
        }
    }
    $completed = $tripData['last_completed_by_bus'][$busId] ?? null;
    if (is_array($completed)) {
        $to = (int)($completed['to_terminal_id'] ?? 0);
        if ($to > 0) {
            return $to;
        }
        /* The arrival terminal is missing from the completed row. The bus's
           configured destination is where it is going next, not where it is, so
           never use it as the current terminal - fall through to the origin. */
    }

    /* The operating-date terminal assignment seeds the day: a bus that has not
       moved yet is at its assigned terminal. A confirmed arrival today (above)
       overrides it; the assignment is consulted before any earlier date's trip
       so the day's declared terminal is authoritative. */
    $assigned = (int)($tripData['assigned_terminal_by_bus'][$busId] ?? 0);
    if ($assigned > 0) {
        return $assigned;
    }

    /* A prior operating date's completion/arrival is invisible to today's trip
       data, so consult the latest trip on any date before the static home
       terminal. Absent the map this keeps the original today-only behavior. */
    $latest = $tripData['latest_trip_by_bus'][$busId] ?? null;
    if (is_array($latest)) {
        $status = strtolower((string)($latest['status'] ?? ''));
        if ($status === 'completed') {
            $to = (int)($latest['to_terminal_id'] ?? 0);
            if ($to > 0) {
                return $to;
            }
            /* Arrival terminal missing: fall through to the configured origin
               rather than the configured destination (see above). */
        } else {
            $from = (int)($latest['from_terminal_id'] ?? 0);
            if ($from > 0) {
                return $from;
            }
        }
    }

    return (int)($bus['from_terminal_id'] ?? 0);
}

/**
 * The most recent bus_trips row per bus id across ALL operating dates, so a
 * completion or in-progress trip from a prior date is not lost. Returns
 * bus_id => row, selecting only the columns the schema actually provides.
 *
 * @param array<string,string>|null $cols Optional bus_trips column map.
 * @return array<string,array<string,mixed>>
 */
function dispatcher_queue_latest_trip_map($conn, array $busIds, $cols = null)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'bus_trips')) {
        return $map;
    }
    $cols = is_array($cols) ? $cols : dispatcher_table_columns($conn, 'bus_trips');
    if (!isset($cols['bus_id'], $cols['id'])) {
        return $map;
    }
    $select = [$cols['bus_id'], $cols['id']];
    foreach (['status', 'from_terminal_id', 'to_terminal_id', 'trip_date'] as $candidate) {
        if (isset($cols[$candidate]) && !in_array($cols[$candidate], $select, true)) {
            $select[] = $cols[$candidate];
        }
    }
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT " . implode(', ', $select) . " FROM bus_trips t
          WHERE t.bus_id IN ($placeholders)
            AND t.id = (SELECT MAX(t2.id) FROM bus_trips t2 WHERE t2.bus_id = t.bus_id)",
        str_repeat('s', count($busIds)),
        $busIds
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $busId = (string)($row['bus_id'] ?? '');
        if ($busId !== '') {
            $map[$busId] = $row;
        }
    }
    $stmt->close();
    return $map;
}

/**
 * FIFO queue-entry timestamp (unix seconds) per bus: a returning bus is keyed
 * by its latest completed trip's arrival time, a waiting bus by its driver
 * check-in time, then its prepared time. NULL when no timestamp exists.
 */
function dispatcher_queue_entry_timestamp($busId, array $tripData, array $checkinMap, array $preparedMap)
{
    $busId = (string)$busId;
    $completed = $tripData['last_completed_by_bus'][$busId] ?? null;
    if (is_array($completed)) {
        foreach (['arrival_time', 'departure_time'] as $field) {
            if (!empty($completed[$field])) {
                $timestamp = strtotime((string)$completed[$field]);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }
    }
    foreach ([$checkinMap[$busId] ?? null, $preparedMap[$busId] ?? null] as $record) {
        if (is_array($record) && !empty($record['at'])) {
            $timestamp = strtotime((string)$record['at']);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }
    }

    return null;
}

/**
 * Queue order for one terminal. When the Admin-published master order is
 * official, EVERY bus present at the terminal - still waiting or already back
 * from a trip - takes its published position, so the dispatcher's list always
 * matches the Admin's order; unpublished buses follow in queue-entry order.
 * Without a publication, waiting buses order by queue-entry time and buses
 * that completed a trip join the back by arrival time.
 *
 * @param array<int,array{bus_id:string,returning:bool,entry_time:?int,bus:array<string,mixed>}> $candidates
 * @param array<string,int> $publishedPositions
 * @return array<int,array{bus_id:string,returning:bool,entry_time:?int,bus:array<string,mixed>}>
 */
function dispatcher_queue_order_buses(array $candidates, array $publishedPositions, $adminLocked)
{
    $start = [];
    $returns = [];
    foreach ($candidates as $candidate) {
        if (!empty($candidate['returning'])) {
            $returns[] = $candidate;
        } else {
            $start[] = $candidate;
        }
    }
    $entryTime = static function (array $candidate): int {
        return $candidate['entry_time'] !== null ? (int)$candidate['entry_time'] : PHP_INT_MAX;
    };
    $busName = static function (array $candidate): string {
        return strtoupper((string)$candidate['bus_id']);
    };
    if ($adminLocked && !empty($publishedPositions)) {
        /* Match published positions case/whitespace-insensitively so the
           Admin's master order always binds to the fleet's bus ids. */
        $publishedNorm = [];
        foreach ($publishedPositions as $publishedBusId => $publishedPos) {
            $key = strtoupper(trim((string)$publishedBusId));
            if ($key !== '') {
                $publishedNorm[$key] = (int)$publishedPos;
            }
        }
        $ordered = array_merge($start, $returns);
        usort($ordered, static function (array $a, array $b) use ($publishedNorm, $entryTime, $busName): int {
            $aPos = (int)($publishedNorm[strtoupper(trim((string)$a['bus_id']))] ?? 0);
            $bPos = (int)($publishedNorm[strtoupper(trim((string)$b['bus_id']))] ?? 0);
            if ($aPos > 0 && $bPos > 0 && $aPos !== $bPos) {
                return $aPos <=> $bPos;
            }
            if ($aPos > 0 && $bPos <= 0) {
                return -1;
            }
            if ($aPos <= 0 && $bPos > 0) {
                return 1;
            }
            $aTime = $entryTime($a);
            $bTime = $entryTime($b);
            if ($aTime !== $bTime) {
                return $aTime <=> $bTime;
            }

            return strcmp($busName($a), $busName($b));
        });

        return $ordered;
    }
    usort($start, static function (array $a, array $b) use ($entryTime, $busName): int {
        $aTime = $entryTime($a);
        $bTime = $entryTime($b);
        if ($aTime !== $bTime) {
            return $aTime <=> $bTime;
        }

        return strcmp($busName($a), $busName($b));
    });
    usort($returns, static function (array $a, array $b) use ($entryTime, $busName): int {
        $aTime = $entryTime($a);
        $bTime = $entryTime($b);
        if ($aTime !== $bTime) {
            return $aTime <=> $bTime;
        }

        return strcmp($busName($a), $busName($b));
    });

    return array_merge($start, $returns);
}

/**
 * Ordered queue state for one terminal and date: the fleet filtered to the
 * buses currently at that terminal, FIFO ordered, plus the shared maps the
 * queue build needs.
 *
 * @return array{buses:array<int,array<string,mixed>>,trip_data:array<string,mixed>,checkin_map:array<string,mixed>,prepared_map:array<string,mixed>,published:array<string,int>,start_terminal:int,admin_locked:bool}
 */
function dispatcher_queue_terminal_state($conn, $terminalId, $date)
{
    $date = $date ?: date('Y-m-d');
    $fleet = dispatcher_queue_fleet_rows($conn);
    $busIds = [];
    foreach ($fleet as $bus) {
        $busId = trim((string)($bus['bus_id'] ?? ''));
        if ($busId !== '') {
            $busIds[$busId] = true;
        }
    }
    $busIds = array_keys($busIds);
    $tripData = dispatcher_queue_trip_data($conn, $busIds, $date, $terminalId);
    $checkinMap = dispatcher_queue_checkin_map($conn, $date, $busIds);
    $preparedMap = dispatcher_queue_prepared_map($conn, $date, $busIds);
    $published = dispatcher_queue_published_positions($conn, $date);
    $startTerminal = dispatcher_queue_start_terminal($conn);
    $adminLocked = dispatcher_queue_admin_lock($conn, $terminalId, $date) !== null;

    $candidates = [];
    if (!empty($published)) {
        $fleetById = [];
        foreach ($fleet as $bus) {
            $bId = trim((string)($bus['bus_id'] ?? ''));
            if ($bId !== '') {
                $fleetById[$bId] = $bus;
                $fleetById[strtoupper($bId)] = $bus;
            }
        }
        foreach ($published as $busId => $pos) {
            $bId = trim((string)$busId);
            $busRow = $fleetById[$bId] ?? ($fleetById[strtoupper($bId)] ?? ['bus_id' => $bId]);
            /* The master publication is not terminal-scoped - it lists every
               bus for the operating date. Only buses actually at this terminal
               may appear, so a bus elsewhere is hidden instead of being shown
               with a destination it cannot be dispatched to. */
            if (!dispatcher_bus_belongs_to_terminal($conn, $busRow, $bId, $tripData, $terminalId, $startTerminal)) {
                continue;
            }
            $candidates[] = [
                'bus_id' => $bId,
                'bus' => $busRow,
                'returning' => isset($tripData['last_completed_by_bus'][$bId]),
                'entry_time' => dispatcher_queue_entry_timestamp($bId, $tripData, $checkinMap, $preparedMap),
            ];
        }
    } else {
        foreach ($fleet as $bus) {
            $busId = trim((string)($bus['bus_id'] ?? ''));
            if ($busId === '') {
                continue;
            }
            if (!dispatcher_bus_belongs_to_terminal($conn, $bus, $busId, $tripData, $terminalId, $startTerminal)) {
                continue;
            }
            $candidates[] = [
                'bus_id' => $busId,
                'bus' => $bus,
                'returning' => isset($tripData['last_completed_by_bus'][$busId]),
                'entry_time' => dispatcher_queue_entry_timestamp($busId, $tripData, $checkinMap, $preparedMap),
            ];
        }
    }
    $ordered = dispatcher_queue_order_buses($candidates, $published, $adminLocked);
    $buses = [];
    foreach ($ordered as $candidate) {
        $buses[] = $candidate['bus'];
    }

    return [
        'buses' => $buses,
        'trip_data' => $tripData,
        'checkin_map' => $checkinMap,
        'prepared_map' => $preparedMap,
        'published' => $published,
        'start_terminal' => dispatcher_queue_start_terminal($conn),
        'admin_locked' => $adminLocked,
    ];
}

/**
 * Whether a bus currently belongs to a terminal's queue: at its home terminal
 * while it has no trips today, at its active trip's departure terminal while
 * on route, and at the arrival terminal of its last completed trip afterwards.
 */
function dispatcher_bus_in_terminal_queue($conn, $busId, $terminalId, $date = null)
{
    $busId = trim((string)$busId);
    if ($busId === '') {
        return false;
    }
    $state = dispatcher_queue_terminal_state($conn, (int)$terminalId, $date ?: date('Y-m-d'));
    foreach ($state['buses'] as $bus) {
        if (strcasecmp((string)($bus['bus_id'] ?? ''), $busId) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * The configured opposite-terminal pair for return trips:
 * Rizal / Poblacion <-> Cabanatuan Central. NULL unless both are found.
 *
 * @return array{rizal:int,cabanatuan:int}|null
 */
function dispatcher_terminal_pair($conn)
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!dispatcher_table_exists($conn, 'terminals')) {
        return $cache;
    }
    $cols = dispatcher_table_columns($conn, 'terminals');
    if (!isset($cols['id'], $cols['terminal_name'])) {
        return $cache;
    }
    $stmt = dispatcher_statement($conn, 'SELECT id, terminal_name FROM terminals ORDER BY id ASC');
    if (!$stmt) {
        return $cache;
    }
    $rizal = 0;
    $cabanatuan = 0;
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $name = strtolower(trim((string)($row['terminal_name'] ?? '')));
            if ($id <= 0 || $name === '') {
                continue;
            }
            if ($rizal === 0 && (strpos($name, 'rizal') !== false || strpos($name, 'poblacion') !== false)) {
                $rizal = $id;
            }
            if ($cabanatuan === 0 && strpos($name, 'cabanatuan') !== false) {
                $cabanatuan = $id;
            }
        }
    }
    $stmt->close();
    if ($rizal > 0 && $cabanatuan > 0) {
        $cache = ['rizal' => $rizal, 'cabanatuan' => $cabanatuan];
    }

    return $cache;
}

/**
 * The opposite terminal of the origin for the configured pair; otherwise the
 * bus's configured destination when it is valid and different from the origin.
 * Pure so it can be tested; 0 means the destination cannot be determined.
 *
 * @param array{rizal:int,cabanatuan:int}|null $pair
 */
function dispatcher_destination_from_pair($pair, $originTerminalId, $configuredDestination)
{
    $originTerminalId = (int)$originTerminalId;
    $configuredDestination = (int)$configuredDestination;
    if (is_array($pair) && $originTerminalId > 0) {
        if ($originTerminalId === (int)($pair['rizal'] ?? 0)) {
            return (int)($pair['cabanatuan'] ?? 0);
        }
        if ($originTerminalId === (int)($pair['cabanatuan'] ?? 0)) {
            return (int)($pair['rizal'] ?? 0);
        }
    }
    if ($configuredDestination > 0 && $configuredDestination !== $originTerminalId) {
        return $configuredDestination;
    }

    return 0;
}

/**
 * Destination terminal for the bus's next trip: the opposite of the terminal
 * the bus is currently at. Returns an error instead of guessing when the
 * current terminal cannot be verified.
 *
 * @param array<string,mixed>|null $busRow
 * @param array<string,mixed>|null $tripData
 * @param array<int,string>|null   $terminalNames
 * @return array{terminal_id:?int,terminal_name:?string,current_terminal_id:int,source:string,error:?string}
 */
function dispatcher_resolve_destination($conn, $busId, $currentTerminalId, $date = null, $busRow = null, $tripData = null, $terminalNames = null)
{
    $date = $date ?: date('Y-m-d');
    $busId = trim((string)$busId);
    $currentTerminalId = (int)$currentTerminalId;
    $resolution = [
        'terminal_id' => null,
        'terminal_name' => null,
        'current_terminal_id' => $currentTerminalId,
        'source' => 'none',
        'error' => null,
    ];
    if ($busId === '') {
        $resolution['error'] = 'Select a bus before determining its destination terminal.';

        return $resolution;
    }
    if ($busRow === null) {
        $cols = dispatcher_table_columns($conn, 'buses');
        if (!isset($cols['bus_id'])) {
            $resolution['error'] = 'The bus records are unavailable, so the destination terminal cannot be determined.';

            return $resolution;
        }
        $select = ['bus_id'];
        foreach (['from_terminal_id', 'to_terminal_id', 'route_name', 'status'] as $candidate) {
            if (isset($cols[$candidate])) {
                $select[] = $candidate;
            }
        }
        $stmt = dispatcher_statement($conn, 'SELECT ' . implode(', ', $select) . ' FROM buses WHERE bus_id = ? LIMIT 1', 's', [$busId]);
        if (!$stmt) {
            $resolution['error'] = 'The bus records are unavailable, so the destination terminal cannot be determined.';

            return $resolution;
        }
        $busRow = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    if (!is_array($busRow)) {
        $resolution['error'] = 'Bus ' . $busId . ' does not exist, so its destination terminal cannot be determined.';

        return $resolution;
    }
    if ($tripData === null) {
        $tripData = dispatcher_queue_trip_data($conn, [$busId], $date, $currentTerminalId);
    }

    /* The bus must be at this terminal for its return destination to be the
       opposite terminal; a bus recorded elsewhere is never dispatched from here.
       The terminal is constrained to a real active terminal, so a stale id from
       a retired terminal falls back to the bus's configured origin. */
    $actualTerminal = dispatcher_bus_current_terminal_id($conn, $busRow, $busId, $tripData);
    if ($actualTerminal > 0 && $currentTerminalId > 0 && $actualTerminal !== $currentTerminalId) {
        $resolution['error'] = 'Bus ' . $busId . ' is recorded at another terminal, so its destination terminal cannot be set from this terminal.';

        return $resolution;
    }

    $originTerminal = $actualTerminal > 0 ? $actualTerminal : $currentTerminalId;
    $destinationId = dispatcher_destination_from_pair(
        dispatcher_terminal_pair($conn),
        $originTerminal,
        (int)($busRow['to_terminal_id'] ?? 0)
    );
    if ($destinationId <= 0) {
        if ($currentTerminalId <= 0 && $actualTerminal <= 0) {
            $resolution['error'] = 'The bus\'s current terminal cannot be verified, so its destination terminal cannot be determined.';
        } elseif (dispatcher_terminal_pair($conn) === null) {
            $resolution['error'] = 'The Rizal / Poblacion and Cabanatuan Central terminals are not configured, so the destination terminal cannot be determined automatically.';
        } else {
            $resolution['error'] = 'The bus\'s current terminal is not part of the configured return route, so its destination terminal cannot be determined.';
        }

        return $resolution;
    }

    if ($terminalNames === null) {
        $terminalNames = dispatcher_queue_terminal_names($conn);
    }
    $resolution['terminal_id'] = $destinationId;
    $resolution['terminal_name'] = $terminalNames[$destinationId] ?? null;
    $resolution['current_terminal_id'] = $originTerminal;
    $resolution['source'] = 'opposite-terminal';

    return $resolution;
}

/**
 * Persist a bus's verified current and destination terminal on its record.
 * Column-guarded (installs may lack the terminal columns) and idempotent
 * (conditional WHERE), so retries and no-ops never flap the row. Returns true
 * when either column actually changed.
 */
function dispatcher_persist_bus_terminal($conn, $busId, $originTerminalId, $destinationTerminalId = 0)
{
    $busId = trim((string)$busId);
    if ($busId === '' || !$conn) {
        return false;
    }
    $cols = dispatcher_table_columns($conn, 'buses');
    if (!isset($cols['bus_id'])) {
        return false;
    }
    $originTerminalId = (int)$originTerminalId;
    $destinationTerminalId = (int)$destinationTerminalId;
    $updated = false;
    if ($originTerminalId > 0 && isset($cols['from_terminal_id'])) {
        $stmt = dispatcher_statement(
            $conn,
            'UPDATE buses SET from_terminal_id = ? WHERE bus_id = ? AND (from_terminal_id IS NULL OR from_terminal_id <> ?)',
            'isi',
            [$originTerminalId, $busId, $originTerminalId]
        );
        if ($stmt !== false) {
            $updated = $stmt->affected_rows > 0 || $updated;
            $stmt->close();
        }
    }
    if ($destinationTerminalId > 0 && isset($cols['to_terminal_id'])) {
        $stmt = dispatcher_statement(
            $conn,
            'UPDATE buses SET to_terminal_id = ? WHERE bus_id = ? AND (to_terminal_id IS NULL OR to_terminal_id <> ?)',
            'isi',
            [$destinationTerminalId, $busId, $destinationTerminalId]
        );
        if ($stmt !== false) {
            $updated = $stmt->affected_rows > 0 || $updated;
            $stmt->close();
        }
    }

    return $updated;
}

/**
 * Move the bus record to its arrival terminal once a trip completes:
 * from_terminal_id becomes the arrival terminal and to_terminal_id the
 * opposite (next departure) terminal. Column-guarded and idempotent.
 */
function dispatcher_sync_bus_after_arrival($conn, $busId, $arrivalTerminalId)
{
    $arrivalTerminalId = (int)$arrivalTerminalId;
    if ($arrivalTerminalId <= 0) {
        return false;
    }
    $nextDestination = dispatcher_destination_from_pair(
        dispatcher_terminal_pair($conn),
        $arrivalTerminalId,
        0
    );

    return dispatcher_persist_bus_terminal($conn, $busId, $arrivalTerminalId, $nextDestination);
}

/**
 * Move a bus's operating-date queue entry to its arrival terminal once a trip
 * completes, so the bus joins the receiving terminal's queue automatically;
 * the operating driver's per-date assignment moves with it. Positions are
 * never renumbered: an existing entry keeps its position and a bus without one
 * (legacy dispatch) joins at the end with null publication stamps. Automatic
 * membership sync only - never a manual queue edit. Returns true when a row
 * actually changed.
 */
function dispatcher_sync_queue_after_arrival($conn, $busId, $date, $arrivalTerminalId, $driverId = null)
{
    $busId = trim((string)$busId);
    $date = (string)$date;
    $arrivalTerminalId = (int)$arrivalTerminalId;
    if (!$conn || $busId === '' || $date === '' || $arrivalTerminalId <= 0) {
        return false;
    }
    $changed = false;

    if (dispatcher_table_exists($conn, 'bus_dispatch_queue')) {
        $cols = dispatcher_table_columns($conn, 'bus_dispatch_queue');
        if (isset($cols['bus_id'], $cols['operating_date'], $cols['terminal_id'])) {
            $update = dispatcher_statement(
                $conn,
                'UPDATE bus_dispatch_queue SET terminal_id = ? WHERE operating_date = ? AND bus_id = ? AND (terminal_id IS NULL OR terminal_id <> ?)',
                'issi',
                [$arrivalTerminalId, $date, $busId, $arrivalTerminalId]
            );
            if ($update !== false) {
                $changed = $update->affected_rows > 0 || $changed;
                $update->close();
            }

            $exists = dispatcher_statement(
                $conn,
                'SELECT bus_id FROM bus_dispatch_queue WHERE operating_date = ? AND bus_id = ? LIMIT 1',
                'ss',
                [$date, $busId]
            );
            $rowExists = false;
            if ($exists) {
                $rowExists = $exists->get_result()->num_rows > 0;
                $exists->close();
            }
            if (!$rowExists && isset($cols['queue_position'])) {
                $position = 1;
                $positionStmt = dispatcher_statement(
                    $conn,
                    'SELECT COALESCE(MAX(queue_position), 0) + 1 AS next_position FROM bus_dispatch_queue WHERE operating_date = ?',
                    's',
                    [$date]
                );
                if ($positionStmt) {
                    $positionRow = $positionStmt->get_result()->fetch_assoc();
                    $position = max(1, (int)($positionRow['next_position'] ?? 1));
                    $positionStmt->close();
                }
                $insertCols = 'operating_date, bus_id, queue_position, terminal_id';
                $insertVals = '?, ?, ?, ?';
                $types = 'ssii';
                $params = [$date, $busId, $position, $arrivalTerminalId];
                foreach (['published_at', 'published_by'] as $stamp) {
                    if (isset($cols[$stamp])) {
                        $insertCols .= ', ' . $stamp;
                        $insertVals .= ', ?';
                        $types .= 's';
                        $params[] = null;
                    }
                }
                foreach (['created_at', 'updated_at'] as $stamp) {
                    if (isset($cols[$stamp])) {
                        $insertCols .= ', ' . $stamp;
                        $insertVals .= ', ?';
                        $types .= 's';
                        $params[] = date('Y-m-d H:i:s');
                    }
                }
                $insert = dispatcher_statement($conn, "INSERT INTO bus_dispatch_queue ($insertCols) VALUES ($insertVals)", $types, $params);
                if ($insert) {
                    $changed = true;
                    $insert->close();
                }
            }
        }
    }

    $driverId = $driverId !== null ? trim((string)$driverId) : '';
    if ($driverId !== '' && dispatcher_table_exists($conn, 'driver_terminal_assignments')) {
        $driverCols = dispatcher_table_columns($conn, 'driver_terminal_assignments');
        if (isset($driverCols['driver_id'], $driverCols['operating_date'], $driverCols['terminal_id'])) {
            $timestampUpdates = isset($driverCols['updated_at']) ? ', updated_at = ?' : '';
            $updateTypes = isset($driverCols['updated_at']) ? 'isssi' : 'issi';
            $updateParams = isset($driverCols['updated_at'])
                ? [$arrivalTerminalId, date('Y-m-d H:i:s'), $date, $driverId, $arrivalTerminalId]
                : [$arrivalTerminalId, $date, $driverId, $arrivalTerminalId];
            $update = dispatcher_statement(
                $conn,
                'UPDATE driver_terminal_assignments SET terminal_id = ?' . $timestampUpdates
                . ' WHERE operating_date = ? AND driver_id = ? AND (terminal_id IS NULL OR terminal_id <> ?)',
                $updateTypes,
                $updateParams
            );
            if ($update !== false) {
                $changed = $update->affected_rows > 0 || $changed;
                $update->close();
            }

            $exists = dispatcher_statement(
                $conn,
                'SELECT driver_id FROM driver_terminal_assignments WHERE operating_date = ? AND driver_id = ? LIMIT 1',
                'ss',
                [$date, $driverId]
            );
            $rowExists = false;
            if ($exists) {
                $rowExists = $exists->get_result()->num_rows > 0;
                $exists->close();
            }
            if (!$rowExists) {
                $insertCols = 'operating_date, driver_id, terminal_id';
                $insertVals = '?, ?, ?';
                $types = 'ssi';
                $params = [$date, $driverId, $arrivalTerminalId];
                foreach (['created_at', 'updated_at'] as $stamp) {
                    if (isset($driverCols[$stamp])) {
                        $insertCols .= ', ' . $stamp;
                        $insertVals .= ', ?';
                        $types .= 's';
                        $params[] = date('Y-m-d H:i:s');
                    }
                }
                $insert = dispatcher_statement($conn, "INSERT INTO driver_terminal_assignments ($insertCols) VALUES ($insertVals)", $types, $params);
                if ($insert) {
                    $changed = true;
                    $insert->close();
                }
            }
        }
    }

    return $changed;
}

/**
 * Resolve and persist the destination terminal on the bus record before a
 * prepare or dispatch step, so every later consumer (dispatch trip row,
 * driver app, GPS tracking, arrival detection) agrees on it.
 *
 * @return array{success:bool,terminal_id:?int,terminal_name:?string,updated:bool,error:?string}
 */
function dispatcher_ensure_bus_destination($conn, $busId, $currentTerminalId, $date = null)
{
    $resolution = dispatcher_resolve_destination($conn, $busId, $currentTerminalId, $date);
    if ($resolution['error'] !== null || $resolution['terminal_id'] === null) {
        return [
            'success' => false,
            'terminal_id' => null,
            'terminal_name' => null,
            'updated' => false,
            'error' => $resolution['error'] ?: 'The destination terminal could not be determined.',
        ];
    }

    $destinationId = (int)$resolution['terminal_id'];
    $originId = (int)$resolution['current_terminal_id'];
    $updated = dispatcher_persist_bus_terminal($conn, $busId, $originId, $destinationId);

    return [
        'success' => true,
        'terminal_id' => $destinationId,
        'terminal_name' => $resolution['terminal_name'],
        'updated' => $updated,
        'error' => null,
    ];
}

/**
 * First word of a terminal name, matching the dispatcher's default-route
 * convention (Rizal / Poblacion Terminal -> "Rizal").
 */
function dispatcher_terminal_short_name($terminalName)
{
    $parts = explode(' ', trim((string)$terminalName));

    return trim((string)($parts[0] ?? ''));
}

/**
 * Route label for a direction from terminal names, e.g. "Rizal-Cabanatuan".
 * Pure so it can be tested; '' when either terminal cannot be named.
 */
function dispatcher_route_from_terminal_names($fromName, $toName)
{
    $from = dispatcher_terminal_short_name($fromName);
    $to = dispatcher_terminal_short_name($toName);
    if ($from === '' || $to === '') {
        return '';
    }

    return $from . '-' . $to;
}

/**
 * A route name already recorded for the terminal direction (most recent trip
 * first, then the fleet), so automatic assignment reuses the naming the
 * system already uses whenever possible.
 */
function dispatcher_recorded_route_name($conn, $fromTerminalId, $toTerminalId)
{
    static $cache = [];
    $fromTerminalId = (int)$fromTerminalId;
    $toTerminalId = (int)$toTerminalId;
    $key = $fromTerminalId . '-' . $toTerminalId;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $cache[$key] = '';
    if ($fromTerminalId <= 0 || $toTerminalId <= 0 || $fromTerminalId === $toTerminalId) {
        return $cache[$key];
    }
    $valid = "route_name IS NOT NULL AND TRIM(route_name) <> '' AND UPPER(TRIM(route_name)) <> 'TBD'";
    if (dispatcher_table_exists($conn, 'bus_trips')) {
        $cols = dispatcher_table_columns($conn, 'bus_trips');
        if (isset($cols['route_name'], $cols['from_terminal_id'], $cols['to_terminal_id'])) {
            $stmt = dispatcher_statement(
                $conn,
                "SELECT route_name FROM bus_trips WHERE from_terminal_id = ? AND to_terminal_id = ? AND $valid ORDER BY id DESC LIMIT 1",
                'ii',
                [$fromTerminalId, $toTerminalId]
            );
            if ($stmt) {
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && trim((string)$row['route_name']) !== '') {
                    $cache[$key] = trim((string)$row['route_name']);

                    return $cache[$key];
                }
            }
        }
    }
    if (dispatcher_table_exists($conn, 'buses')) {
        $cols = dispatcher_table_columns($conn, 'buses');
        if (isset($cols['route_name'], $cols['from_terminal_id'], $cols['to_terminal_id'])) {
            $stmt = dispatcher_statement(
                $conn,
                "SELECT route_name FROM buses WHERE from_terminal_id = ? AND to_terminal_id = ? AND $valid ORDER BY bus_id ASC LIMIT 1",
                'ii',
                [$fromTerminalId, $toTerminalId]
            );
            if ($stmt) {
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && trim((string)$row['route_name']) !== '') {
                    $cache[$key] = trim((string)$row['route_name']);
                }
            }
        }
    }

    return $cache[$key];
}

/**
 * Default route name for a direction: the first words of the two terminal
 * names joined with a dash, matching the existing dispatcher default-route
 * logic. '' when the terminals cannot be named.
 */
function dispatcher_default_route_name($conn, $fromTerminalId, $toTerminalId)
{
    $fromTerminalId = (int)$fromTerminalId;
    $toTerminalId = (int)$toTerminalId;
    if ($fromTerminalId <= 0 || $toTerminalId <= 0 || $fromTerminalId === $toTerminalId) {
        return '';
    }
    $names = dispatcher_queue_terminal_names($conn);

    return dispatcher_route_from_terminal_names($names[$fromTerminalId] ?? '', $names[$toTerminalId] ?? '');
}

/**
 * Route for the bus's next trip: its stored route when valid, the dispatcher's
 * selected route, a route already recorded for the terminal direction, or the
 * default route built from the terminal names. Never invents a route when the
 * terminals cannot be named.
 *
 * @param array<string,mixed>|null $busRow
 * @return array{route:?string,source:string,error:?string}
 */
function dispatcher_resolve_route($conn, $busId, $fromTerminalId, $toTerminalId, $busRow = null, $preferredRoute = '')
{
    $busId = trim((string)$busId);
    if ($busRow === null && $busId !== '') {
        $cols = dispatcher_table_columns($conn, 'buses');
        if (isset($cols['bus_id'])) {
            $select = ['bus_id'];
            if (isset($cols['route_name'])) {
                $select[] = 'route_name';
            }
            $stmt = dispatcher_statement($conn, 'SELECT ' . implode(', ', $select) . ' FROM buses WHERE bus_id = ? LIMIT 1', 's', [$busId]);
            if ($stmt) {
                $busRow = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }
        }
    }
    $stored = is_array($busRow) ? trim((string)($busRow['route_name'] ?? '')) : '';
    if ($stored !== '' && strcasecmp($stored, 'TBD') !== 0) {
        return ['route' => $stored, 'source' => 'bus', 'error' => null];
    }
    $preferred = trim((string)$preferredRoute);
    if ($preferred !== '' && strcasecmp($preferred, 'TBD') !== 0) {
        return ['route' => $preferred, 'source' => 'selected', 'error' => null];
    }
    $recorded = dispatcher_recorded_route_name($conn, $fromTerminalId, $toTerminalId);
    if ($recorded !== '') {
        return ['route' => $recorded, 'source' => 'recorded', 'error' => null];
    }
    $default = dispatcher_default_route_name($conn, $fromTerminalId, $toTerminalId);
    if ($default !== '') {
        return ['route' => $default, 'source' => 'terminal-pair', 'error' => null];
    }

    return [
        'route' => null,
        'source' => 'none',
        'error' => 'No route is assigned to this bus and the route for this terminal direction cannot be determined. Assign a route before dispatching.',
    ];
}

/**
 * Resolve and persist the route on the bus when it has none, so the queue,
 * prepare, and dispatch all use the same route name.
 *
 * @return array{success:bool,route:?string,updated:bool,error:?string}
 */
function dispatcher_ensure_bus_route($conn, $busId, $fromTerminalId, $toTerminalId, $preferredRoute = '')
{
    $resolution = dispatcher_resolve_route($conn, $busId, $fromTerminalId, $toTerminalId, null, $preferredRoute);
    if ($resolution['error'] !== null || $resolution['route'] === null) {
        return [
            'success' => false,
            'route' => null,
            'updated' => false,
            'error' => $resolution['error'] ?: 'The route for this trip could not be determined.',
        ];
    }
    $route = (string)$resolution['route'];
    $updated = false;
    $cols = dispatcher_table_columns($conn, 'buses');
    if (isset($cols['bus_id'], $cols['route_name'])) {
        $stmt = dispatcher_statement(
            $conn,
            "UPDATE buses SET route_name = ? WHERE bus_id = ? AND (route_name IS NULL OR TRIM(route_name) = '' OR UPPER(TRIM(route_name)) = 'TBD')",
            'ss',
            [$route, $busId]
        );
        if ($stmt !== false) {
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
        }
    }

    return [
        'success' => true,
        'route' => $route,
        'updated' => $updated,
        'error' => null,
    ];
}

/**
 * Published Admin dispatch order for an operating date: bus_id => position.
 *
 * The Admin "Bus Assignment & Dispatch Queue" page publishes this order; when
 * rows exist for the date they take precedence over the schedule-based
 * fallback in dispatcher_queue_sort_buses(). Schema-adaptive: missing table or
 * rows simply return an empty map and the queue keeps its legacy ordering.
 */
function dispatcher_queue_published_positions($conn, $date)
{
    $map = [];
    $date = (string)$date;
    if ($date === '' || !dispatcher_table_exists($conn, 'bus_dispatch_queue')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'bus_dispatch_queue');
    if (!isset($cols['bus_id'], $cols['queue_position'], $cols['operating_date'])) {
        return $map;
    }
    $publishedClause = isset($cols['published_at']) ? " AND published_at IS NOT NULL" : '';
    $stmt = dispatcher_statement(
        $conn,
        "SELECT bus_id, queue_position FROM bus_dispatch_queue WHERE operating_date = ?$publishedClause ORDER BY queue_position ASC",
        "s",
        [$date]
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $busId = (string)($row['bus_id'] ?? '');
            if ($busId !== '') {
                $map[$busId] = (int)$row['queue_position'];
            }
        }
    }
    $stmt->close();
    return $map;
}

/**
 * The published Admin dispatch order for a date, if one exists.
 *
 * @return array{published_at:string,published_by:string}|null
 */
function dispatcher_queue_admin_publication($conn, $date)
{
    $date = (string)$date;
    if ($date === '' || !dispatcher_table_exists($conn, 'bus_dispatch_queue')) {
        return null;
    }
    $cols = dispatcher_table_columns($conn, 'bus_dispatch_queue');
    if (!isset($cols['operating_date'], $cols['published_at'])) {
        return null;
    }
    $select = 'MAX(published_at) AS published_at';
    if (isset($cols['published_by'])) {
        $select .= ', MAX(published_by) AS published_by';
    }
    $stmt = dispatcher_statement(
        $conn,
        "SELECT $select FROM bus_dispatch_queue WHERE operating_date = ? AND published_at IS NOT NULL",
        "s",
        [$date]
    );
    if (!$stmt) {
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['published_at'])) {
        return null;
    }

    return [
        'published_at' => (string)$row['published_at'],
        'published_by' => (string)($row['published_by'] ?? ''),
    ];
}

/**
 * Whether any saved (not yet published) Admin queue rows exist for the date,
 * so the dispatcher can be told a draft is waiting to be published.
 */
function dispatcher_queue_has_saved_rows($conn, $date)
{
    $date = (string)$date;
    if ($date === '' || !dispatcher_table_exists($conn, 'bus_dispatch_queue')) {
        return false;
    }
    $cols = dispatcher_table_columns($conn, 'bus_dispatch_queue');
    if (!isset($cols['operating_date'], $cols['bus_id'])) {
        return false;
    }
    $stmt = dispatcher_statement(
        $conn,
        'SELECT COUNT(*) AS rows_count FROM bus_dispatch_queue WHERE operating_date = ?',
        "s",
        [$date]
    );
    if (!$stmt) {
        return false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row && (int)($row['rows_count'] ?? 0) > 0;
}

/**
 * Whether an operational status blocks dispatch.
 */
function dispatcher_operational_blocked($status)
{
    $status = strtolower(trim((string)$status));

    return $status === 'under maintenance' || $status === 'out of service';
}

/**
 * Gate for the Admin-published queue: once the Admin order is official the
 * dispatcher may only dispatch the next ready bus. Pure so it can be tested;
 * returns null when the dispatch is allowed.
 *
 * @return array{code:string,message:string,next_bus_id:string,next_position:int}|null
 */
function dispatcher_admin_dispatch_gate($published, $targetBusId, $nextReadyBusId, $nextReadyPosition)
{
    if (!$published) {
        return null;
    }
    $nextReadyBusId = trim((string)$nextReadyBusId);
    if ($nextReadyBusId === '') {
        return null;
    }
    if (strcasecmp(trim((string)$targetBusId), $nextReadyBusId) === 0) {
        return null;
    }

    return [
        'code' => 'admin_queue_locked',
        'message' => 'The Admin dispatch order is official. Dispatch bus ' . $nextReadyBusId . ' (position #' . (int)$nextReadyPosition . ') next.',
        'next_bus_id' => $nextReadyBusId,
        'next_position' => (int)$nextReadyPosition,
    ];
}

/**
 * Open problem reports (waiting/acknowledged) per bus. Schema-adaptive.
 *
 * @return array<string,array{status:string,problem_type:string,created_at:string}>
 */
function dispatcher_queue_open_problems($conn, array $busIds)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($value) {
        return $value !== '';
    }));
    if (empty($busIds) || !dispatcher_table_exists($conn, 'bus_problem_reports')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'bus_problem_reports');
    if (!isset($cols['bus_id'], $cols['status'])) {
        return $map;
    }
    $select = ['bus_id', 'status'];
    foreach (['problem_type', 'created_at'] as $candidate) {
        if (isset($cols[$candidate])) {
            $select[] = $candidate;
        }
    }
    $order = isset($cols['id']) ? ' ORDER BY id ASC' : '';
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        'SELECT ' . implode(', ', $select) . " FROM bus_problem_reports"
        . " WHERE status IN ('waiting','acknowledged') AND bus_id IN ($placeholders)$order",
        str_repeat('s', count($busIds)),
        $busIds
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $busId = (string)($row['bus_id'] ?? '');
            if ($busId === '' || isset($map[$busId])) {
                continue;
            }
            $map[$busId] = [
                'status' => (string)($row['status'] ?? ''),
                'problem_type' => (string)($row['problem_type'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
    }
    $stmt->close();

    return $map;
}

/**
 * Deterministic terminal ordering: the published Admin order first when it
 * exists for the date, then leading schedule time, then bus id.
 */
function dispatcher_queue_sort_buses(array $buses, array $publishedPositions = [])
{
    $hasPublished = !empty($publishedPositions);
    usort($buses, function ($a, $b) use ($publishedPositions, $hasPublished) {
        if ($hasPublished) {
            $aPos = $publishedPositions[(string)($a['bus_id'] ?? '')] ?? null;
            $bPos = $publishedPositions[(string)($b['bus_id'] ?? '')] ?? null;
            if ($aPos !== null && $bPos !== null && $aPos !== $bPos) {
                return $aPos <=> $bPos;
            }
            if ($aPos !== null && $bPos === null) {
                return -1;
            }
            if ($aPos === null && $bPos !== null) {
                return 1;
            }
        }
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
 *
 * When a simulation session is active for a driver (or their bus), the row is
 * replaced by the simulated coordinates and flagged `simulation => true`. The
 * shared engine reports active=false when the tables are absent or no session
 * exists, so the real map is untouched otherwise.
 */
function dispatcher_queue_location_map($conn, array $driverIds)
{
    $map = [];
    $driverIds = array_values(array_filter(array_map('strval', $driverIds), function ($v) {
        return $v !== '';
    }));
    if (empty($driverIds)) {
        return $map;
    }

    if (dispatcher_table_exists($conn, 'driver_current_location')) {
        $cols = dispatcher_table_columns($conn, 'driver_current_location');
        if (isset($cols['driver_id'])) {
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
            if ($stmt) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $key = (string)$row['driver_id'];
                    if (!isset($map[$key])) {
                        $map[$key] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }

    $simulationEngine = __DIR__ . '/../../mobile/simulation/lib/simulation_engine.php';

    if (file_exists($simulationEngine)) {
        require_once $simulationEngine;

        if (function_exists('simulation_override_for')) {
            foreach ($driverIds as $driverId) {
                $existing = isset($map[$driverId]) && is_array($map[$driverId]) ? $map[$driverId] : null;
                $busId = $existing !== null && isset($existing['bus_id']) ? (string)$existing['bus_id'] : null;
                $override = simulation_override_for($conn, $busId, (string)$driverId);

                if (($override['active'] ?? false) !== true) {
                    continue;
                }

                $row = $existing !== null ? $existing : ['driver_id' => (string)$driverId];
                $row['bus_id'] = isset($override['bus_id']) && $override['bus_id'] !== null
                    ? $override['bus_id']
                    : ($row['bus_id'] ?? null);
                $row['latitude'] = $override['latitude'];
                $row['longitude'] = $override['longitude'];
                if (isset($override['speed_kmh'])) {
                    $row['speed'] = $override['speed_kmh'];
                }
                $row['updated_at'] = isset($override['updated_at']) ? $override['updated_at'] : ($row['updated_at'] ?? null);
                $row['simulation'] = true;
                $map[(string)$driverId] = $row;
            }
        }
    }

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
    $endExpr = dispatcher_fuel_coalesce_expr($cols, ['ending_odo', 'ending_odometer', 'end_km'], '', 'NULL');
    $idCol = dispatcher_fuel_id_column($conn);
    $select = ['bus_id', "$startExpr AS starting_odo", "$endExpr AS ending_odo"];
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
 * The operating-date terminal assignments for buses (Admin queue entries with a
 * stored terminal). Schema-adaptive: installations without the column return an
 * empty map and keep the trip-derived behavior.
 *
 * @return array<string,int> bus_id => terminal_id
 */
function dispatcher_queue_day_terminal_map($conn, array $busIds, $date)
{
    $map = [];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    $date = (string)$date;
    if (empty($busIds) || $date === '' || !dispatcher_table_exists($conn, 'bus_dispatch_queue')) {
        return $map;
    }
    $cols = dispatcher_table_columns($conn, 'bus_dispatch_queue');
    if (!isset($cols['bus_id'], $cols['operating_date'], $cols['terminal_id'])) {
        return $map;
    }
    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $stmt = dispatcher_statement(
        $conn,
        "SELECT bus_id, terminal_id FROM bus_dispatch_queue WHERE operating_date = ? AND terminal_id IS NOT NULL AND bus_id IN ($placeholders)",
        's' . str_repeat('s', count($busIds)),
        array_merge([$date], $busIds)
    );
    if (!$stmt) {
        return $map;
    }
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $busId = (string)($row['bus_id'] ?? '');
            $terminalId = (int)($row['terminal_id'] ?? 0);
            if ($busId !== '' && $terminalId > 0) {
                $map[$busId] = $terminalId;
            }
        }
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
        'last_completed_by_bus' => [],
        'latest_trip_by_bus' => [],
        'assigned_terminal_by_bus' => [],
    ];
    $busIds = array_values(array_filter(array_map('strval', $busIds), function ($v) {
        return $v !== '';
    }));
    /* The operating-date terminal assignments seed each bus's location even
       when the trips table is unavailable (schema-adaptive). */
    $result['assigned_terminal_by_bus'] = dispatcher_queue_day_terminal_map($conn, $busIds, $date);
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
        if ($status === 'Completed') {
            // Rows are ordered by id, so the latest completed trip wins.
            $result['last_completed_by_bus'][$busId] = $row;
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

    $result['latest_trip_by_bus'] = dispatcher_queue_latest_trip_map($conn, $busIds, $cols);

    return $result;
}

/**
 * Bus id => 1-based planned queue position for a terminal.
 */
function dispatcher_queue_position_map($conn, $terminalId, $date = null)
{
    $date = $date ?: date('Y-m-d');
    $published = dispatcher_queue_published_positions($conn, $date);
    if (!empty($published)) {
        return $published;
    }
    $state = dispatcher_queue_terminal_state($conn, $terminalId, $date);
    $map = [];
    $index = 1;
    foreach ($state['buses'] as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $map[$busId] = $index;
        }
        $index++;
    }
    return $map;
}

/**
 * Current FIFO queue position per bus for a terminal and date. Positions are
 * renumbered 1..n in queue order: returning buses always take the positions at
 * the back, after every bus already waiting at the terminal.
 */
function dispatcher_queue_entry_position_map($conn, $terminalId, $date, $tripData = null)
{
    $date = $date ?: date('Y-m-d');
    $published = dispatcher_queue_published_positions($conn, $date);
    if (!empty($published)) {
        return $published;
    }
    $state = dispatcher_queue_terminal_state($conn, $terminalId, $date);
    $map = [];
    $index = 1;
    foreach ($state['buses'] as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $map[$busId] = $index;
        }
        $index++;
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
 * Recorded driver check-outs per bus for a date (Dispatcher 'Driver Checked Out').
 */
function dispatcher_queue_checkout_map($conn, $date, array $busIds)
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
    $params = ['Dispatcher', 'Driver Checked Out'];
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
            'at' => $payload['checked_out_at'] ?? ($row['created_at'] ?? null),
            'by' => $payload['checked_out_by'] ?? 'dispatcher',
            'by_name' => $payload['checked_out_by_name'] ?? ($row['username'] ?? null),
            'driver_id' => $payload['driver_id'] ?? null,
            'driver_name' => $payload['driver_name'] ?? null,
        ];
    }
    $stmt->close();
    return $map;
}

/**
 * Record a dispatcher-performed driver check-out in the accountability trail.
 * Attendance information only: trip, queue, and dispatch records are untouched.
 */
function dispatcher_queue_record_checkout($conn, $dispatcher, $busId, $driverId, $driverName, $date)
{
    return dispatcher_audit_log(
        $conn,
        $dispatcher,
        'Driver Checked Out',
        null,
        "Driver $driverName checked out for Bus $busId by dispatcher",
        [],
        [
            'bus_id' => (string)$busId,
            'driver_id' => (string)$driverId,
            'driver_name' => (string)$driverName,
            'trip_date' => (string)$date,
            'checked_out_by' => 'dispatcher',
            'checked_out_by_name' => (string)($dispatcher['name'] ?? ''),
            'checked_out_at' => date('Y-m-d H:i:s'),
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
    /* Never inherit a driver who now belongs to (or is driving) another bus:
       the bus waits for an explicit assignment instead of showing a conflict. */
    if (dispatcher_driver_busy_elsewhere($conn, $driverRef, $busId, $date)) {
        return null;
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
        $stmt = dispatcher_statement($conn, $sql);
        if ($stmt) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $drivers[(string)$row['driver_id']] = trim((string)($row['full_name'] ?? ''));
            }
            $stmt->close();
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
                $stmt = dispatcher_statement($conn, "SELECT " . implode(', ', $select) . " FROM buses");
                if ($stmt) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $busKey = (string)$row['bus_id'];
                        $busStatus[$busKey] = isset($row['status']) ? (string)$row['status'] : 'Active';
                        $ref = trim((string)($row['driver'] ?? ''));
                        if ($ref !== '' && strcasecmp($ref, 'Unassigned') !== 0 && strcasecmp($ref, 'TBD') !== 0) {
                            $legacyByBus[$busKey] = $ref;
                        }
                    }
                    $stmt->close();
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
                $stmt = dispatcher_statement($conn, "SELECT bus_id, driver_id FROM driver_bus_assignment$order");
                if ($stmt) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $busKey = (string)$row['bus_id'];
                        if (isset($currentAssignments[$busKey])) {
                            continue;
                        }
                        $currentAssignments[$busKey] = (string)$row['driver_id'];
                    }
                    $stmt->close();
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

    /* Drivers currently on an active trip today are not assignable to a second
       bus, even when they have no standing assignment row. */
    $activeTripDriverIds = [];
    if (dispatcher_table_exists($conn, 'bus_trips')) {
        $tcols = dispatcher_table_columns($conn, 'bus_trips');
        if (isset($tcols['driver_id'], $tcols['status'])) {
            $where = "status IN ('Scheduled','Departed','Interrupted') AND driver_id IS NOT NULL AND driver_id <> ''";
            $types = '';
            $params = [];
            if (isset($tcols['trip_date'])) {
                $where .= ' AND trip_date = ?';
                $types = 's';
                $params[] = date('Y-m-d');
            }
            $stmt = dispatcher_statement($conn, "SELECT DISTINCT driver_id FROM bus_trips WHERE $where", $types, $params);
            if ($stmt) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $activeTripDriverIds[(string)$row['driver_id']] = true;
                }
                $stmt->close();
            }
        }
    }

    $available = [];
    foreach ($drivers as $driverId => $name) {
        if (isset($busyIds[$driverId]) || isset($activeTripDriverIds[$driverId])) {
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
 * Pure: whether the driver's active trip today belongs to a different bus.
 *
 * @param array<string,mixed> $tripData
 */
function dispatcher_driver_active_on_other_bus(array $tripData, $driverId, $busId)
{
    $driverId = trim((string)$driverId);
    if ($driverId === '') {
        return false;
    }
    $active = $tripData['by_driver'][$driverId] ?? null;
    if (!is_array($active)) {
        return false;
    }
    $activeBus = (string)($active['bus_id'] ?? '');

    return $activeBus !== '' && $activeBus !== trim((string)$busId);
}

/**
 * Whether a driver is already committed to a different bus today: bound to
 * another bus through the day assignment or the legacy buses.driver reference,
 * or currently on an active trip with another bus. Fallback driver inheritance
 * uses this so a busy driver is never handed to a second bus (which would
 * surface as "driver is already on another active trip today").
 *
 * @param array<string,mixed>|null $tripData
 */
function dispatcher_driver_busy_elsewhere($conn, $driverId, $busId, $date = null, $tripData = null)
{
    $driverId = trim((string)$driverId);
    $busId = trim((string)$busId);
    if ($driverId === '') {
        return false;
    }
    if (dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        $cols = dispatcher_table_columns($conn, 'driver_bus_assignment');
        if (isset($cols['driver_id'], $cols['bus_id'])) {
            $stmt = dispatcher_statement($conn, 'SELECT bus_id FROM driver_bus_assignment WHERE driver_id = ? AND bus_id <> ? LIMIT 1', 'ss', [$driverId, $busId]);
            if ($stmt) {
                $found = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($found) {
                    return true;
                }
            }
        }
    }
    if (dispatcher_table_exists($conn, 'buses')) {
        $cols = dispatcher_table_columns($conn, 'buses');
        if (isset($cols['bus_id'], $cols['driver'])) {
            $where = 'bus_id <> ? AND (driver = ? OR LOWER(driver) = LOWER(?))';
            $types = 'sss';
            $params = [$busId, $driverId, $driverId];
            if (isset($cols['status'])) {
                $where .= " AND (status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) NOT IN ('archived','inactive'))";
            }
            $stmt = dispatcher_statement($conn, 'SELECT bus_id FROM buses WHERE ' . $where . ' LIMIT 1', $types, $params);
            if ($stmt) {
                $found = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($found) {
                    return true;
                }
            }
        }
    }
    if (is_array($tripData)) {
        if (dispatcher_driver_active_on_other_bus($tripData, $driverId, $busId)) {
            return true;
        }
    } elseif (dispatcher_table_exists($conn, 'bus_trips')) {
        $cols = dispatcher_table_columns($conn, 'bus_trips');
        if (isset($cols['driver_id'], $cols['bus_id'], $cols['status'])) {
            $where = "driver_id = ? AND bus_id <> ? AND status IN ('Scheduled','Departed','Interrupted')";
            $types = 'ss';
            $params = [$driverId, $busId];
            if (isset($cols['trip_date'])) {
                $where .= ' AND trip_date = ?';
                $types .= 's';
                $params[] = $date ?: date('Y-m-d');
            }
            $stmt = dispatcher_statement($conn, 'SELECT bus_id FROM bus_trips WHERE ' . $where . ' LIMIT 1', $types, $params);
            if ($stmt) {
                $found = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($found) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Build the terminal dispatch queue with readiness, presence, skip state, and
 * dispatch history.
 */
function dispatcher_queue_build($conn, $terminalId, $date = null)
{
    $date = $date ?: date('Y-m-d');
    $terminal = dispatcher_queue_terminal($conn, $terminalId);
    $adminPublication = dispatcher_queue_admin_publication($conn, $date);
    $state = dispatcher_queue_terminal_state($conn, $terminalId, $date);
    $buses = $state['buses'];

    $busIds = [];
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        if ($busId !== '') {
            $busIds[] = $busId;
        }
    }
    $busIds = array_values(array_unique($busIds));

    $assignmentMap = dispatcher_queue_driver_assignment_map($conn, $busIds);
    $openProblems = dispatcher_queue_open_problems($conn, $busIds);

    $resolvedDrivers = [];
    $driverIds = [];
    foreach ($buses as $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        $assigned = trim((string)($assignmentMap[$busId] ?? ''));
        if ($assigned === '') {
            $raw = trim((string)($bus['driver'] ?? ''));
            if ($raw !== '' && strcasecmp($raw, 'Unassigned') !== 0 && strcasecmp($raw, 'TBD') !== 0
                && !dispatcher_driver_busy_elsewhere($conn, $raw, $busId, $date, $state['trip_data'])) {
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
            $resolvedId = (string)($driverRow['driver_id'] ?? $assigned);
            if (dispatcher_driver_busy_elsewhere($conn, $resolvedId, $busId, $date, $state['trip_data'])) {
                $resolvedDrivers[$busId] = ['driver_id' => null, 'name' => null, 'active' => false, 'source' => 'none'];
                continue;
            }
            $resolvedDrivers[$busId] = [
                'driver_id' => $resolvedId,
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
    $tripData = $state['trip_data'];
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
        if ($tripDriverId === '' || dispatcher_driver_busy_elsewhere($conn, $tripDriverId, $busKey, $date, $tripData)) {
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
    $checkinMap = $state['checkin_map'];
    $preparedMap = $state['prepared_map'];
    $checkoutMap = dispatcher_queue_checkout_map($conn, $date, $busIds);
    $completedCountByBus = [];
    $lastCompletedTripByBus = [];
    foreach ($tripData['dispatched'] as $tripRow) {
        if ((string)($tripRow['status'] ?? '') === 'Completed') {
            $completedBusId = (string)($tripRow['bus_id'] ?? '');
            $completedCountByBus[$completedBusId] = ($completedCountByBus[$completedBusId] ?? 0) + 1;
            // Rows are ordered by id, so the latest completed trip wins.
            $lastCompletedTripByBus[$completedBusId] = $tripRow;
        }
    }
    $terminalNames = dispatcher_queue_terminal_names($conn);

    $now = time();
    $freshness = dispatcher_queue_location_freshness_seconds();
    $proximity = dispatcher_queue_proximity_km();
    $areaMap = [
        'bus_assigned' => 'Vehicle',
        'operational_valid' => 'Vehicle',
        'no_open_problem' => 'Vehicle',
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

    $publishedPositions = !empty($state['published']) ? $state['published'] : dispatcher_queue_published_positions($conn, $date);

    foreach ($buses as $index => $bus) {
        $busId = (string)($bus['bus_id'] ?? '');
        $position = isset($publishedPositions[$busId]) ? (int)$publishedPositions[$busId] : ($index + 1);
        $positionMap[$busId] = $position;
        $schedule = trim((string)($bus['schedule'] ?? ''));
        /* The destination is derived from the bus's current terminal (the
           opposite of the configured Rizal <-> Cabanatuan pair) instead of
           relying on the stored value alone, so a missing bus record never
           blocks a valid trip. Unverifiable terminals block with a clear reason. */
        $destinationResolution = dispatcher_resolve_destination($conn, $busId, $terminalId, $date, $bus, $tripData, $terminalNames);
        $destinationId = $destinationResolution['terminal_id'] !== null ? (int)$destinationResolution['terminal_id'] : null;
        $destinationName = ($destinationId !== null && $destinationId > 0)
            ? ($terminalNames[$destinationId] ?? $destinationResolution['terminal_name'])
            : null;
        /* The route is derived the same way - stored route, a route already
           recorded for this terminal direction, or the default terminal-pair
           label - so a bus without a stored route is never blocked. */
        $routeResolution = dispatcher_resolve_route($conn, $busId, $terminalId, $destinationId !== null ? $destinationId : 0, $bus);
        $route = $routeResolution['route'] !== null ? (string)$routeResolution['route'] : '';

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
        $checkout = $checkoutMap[$busId] ?? null;
        $checkoutMatchesDriver = $checkout !== null
            && (string)($checkout['driver_id'] ?? '') !== ''
            && (string)$checkout['driver_id'] === (string)($driver['driver_id'] ?? '');

        $fuel = $fuelMap[$busId] ?? null;
        $fuelValidated = $fuel !== null && ($fuel['starting_odo'] ?? null) !== null && (float)$fuel['starting_odo'] > 0;
        $routeAssigned = $route !== '' && strcasecmp($route, 'TBD') !== 0;
        $operationalStatus = trim((string)($bus['operational_status'] ?? ''));
        $operationalBlocked = dispatcher_operational_blocked($operationalStatus);
        $openProblem = $openProblems[$busId] ?? null;
        $activeTrip = $tripData['by_bus'][$busId] ?? null;
        $activeTripStatus = $activeTrip ? (string)($activeTrip['status'] ?? '') : '';
        $driverActiveTrip = (!empty($driver['driver_id']) && isset($tripData['by_driver'][$driver['driver_id']])) ? $tripData['by_driver'][$driver['driver_id']] : null;
        $driverActiveOnOtherBus = $driverActiveTrip !== null && (string)($driverActiveTrip['bus_id'] ?? '') !== $busId;
        $destinationValid = $destinationId !== null && $destinationId > 0 && $destinationName !== null && $destinationName !== '';
        $terminalsConfigured = !empty(dispatcher_active_terminal_ids($conn));
        $confirmedTerminalId = dispatcher_bus_confirmed_terminal_id($bus, $busId, $tripData);
        $terminalEntryOk = !$terminalsConfigured || $confirmedTerminalId === (int)$terminalId;

        /* Actual terminal/location: the bus's home terminal while parked, the
           real departure/arrival terminals while on or after a trip. */
        $originTerminalId = isset($bus['from_terminal_id']) && $bus['from_terminal_id'] !== null && $bus['from_terminal_id'] !== ''
            ? (int)$bus['from_terminal_id']
            : null;
        $originTerminalName = ($originTerminalId !== null && isset($terminalNames[$originTerminalId])) ? $terminalNames[$originTerminalId] : null;
        $lastCompletedTrip = $lastCompletedTripByBus[$busId] ?? null;
        $locationLabel = null;
        $locationTerminalId = null;
        if ($activeTrip !== null) {
            $tripFromId = isset($activeTrip['from_terminal_id']) && $activeTrip['from_terminal_id'] !== '' ? (int)$activeTrip['from_terminal_id'] : $originTerminalId;
            $tripToId = isset($activeTrip['to_terminal_id']) && $activeTrip['to_terminal_id'] !== '' ? (int)$activeTrip['to_terminal_id'] : $destinationId;
            $tripFromName = ($tripFromId !== null && isset($terminalNames[$tripFromId])) ? $terminalNames[$tripFromId] : null;
            $tripToName = ($tripToId !== null && isset($terminalNames[$tripToId])) ? $terminalNames[$tripToId] : null;
            if ($tripFromName !== null || $tripToName !== null) {
                $statusWord = $activeTripStatus === 'Interrupted' ? 'Interrupted en route' : 'En route';
                $locationLabel = $statusWord . ': ' . ($tripFromName !== null ? $tripFromName : '—') . ' → ' . ($tripToName !== null ? $tripToName : '—');
            }
        } elseif ($lastCompletedTrip !== null) {
            $arrivalId = isset($lastCompletedTrip['to_terminal_id']) && $lastCompletedTrip['to_terminal_id'] !== ''
                ? (int)$lastCompletedTrip['to_terminal_id']
                : $destinationId;
            if ($arrivalId !== null && isset($terminalNames[$arrivalId])) {
                $locationTerminalId = $arrivalId;
                $locationLabel = 'At ' . $terminalNames[$arrivalId];
            }
        } elseif ($originTerminalName !== null) {
            $locationTerminalId = $originTerminalId;
            $locationLabel = 'At ' . $originTerminalName;
        }

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
        /* Bus presence at the terminal is informational only. The trip was
           already prepared from the dispatch schedule, so it becomes ready
           to dispatch in queue order even when the GPS location is stale or
           away from the terminal. */
        $fuelGateOk = $fuelValidated || $preparedMatchesDriver;
        $addItem('fuel_validated', dispatcher_t('readiness.fuel_validated', 'Fuel validated'), $fuelGateOk, false, $fuelGateOk ? ($fuelValidated ? dispatcher_t('readiness.fuel_validated.ok', 'Odometer reference available') : dispatcher_t('readiness.prepared_detail', 'Confirmed during Prepare Trip')) : dispatcher_t('readiness.fuel_validated.missing', 'No fuel/odometer reference for this bus'), !$fuelGateOk);
        $addItem('route_assigned', dispatcher_t('readiness.route_assigned', 'Route assigned'), $routeAssigned, false, $routeAssigned ? dispatcher_t('readiness.route_assigned.detail', 'Route: :route', ['route' => $route]) : ($routeResolution['error'] ?: dispatcher_t('readiness.route_assigned.missing', 'No route assigned to this bus')));
        $addItem(
            'operational_valid',
            dispatcher_t('readiness.operational_valid', 'Operational status'),
            !$operationalBlocked,
            true,
            $operationalBlocked
                ? 'Bus is "' . $operationalStatus . '" and cannot be dispatched'
                : ($operationalStatus !== '' ? 'Operational status: ' . $operationalStatus : 'Operational status: Available')
        );
        $problemDetail = null;
        if ($openProblem !== null) {
            $problemLabel = $openProblem['problem_type'] !== '' ? $openProblem['problem_type'] : 'bus problem';
            $problemDetail = 'Open problem report: ' . $problemLabel . ' (' . $openProblem['status'] . ')';
        }
        $addItem(
            'no_open_problem',
            dispatcher_t('readiness.no_open_problem', 'No open problem report'),
            $openProblem === null,
            true,
            $problemDetail !== null ? $problemDetail : 'No open problem reports'
        );
        $addItem(
            'terminal_entry',
            dispatcher_t('readiness.terminal_entry', 'Terminal entry'),
            $terminalEntryOk,
            true,
            $terminalEntryOk
                ? dispatcher_t('readiness.terminal_entry.ok', 'Entered this terminal\'s queue')
                : dispatcher_t('readiness.terminal_entry.missing', 'Bus must be assigned to a terminal before it can be dispatched')
        );

        if ($activeTrip !== null && $activeTripStatus === 'Departed') {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.on_route', 'Bus is currently on route'));
        } elseif ($activeTrip !== null && $activeTripStatus === 'Scheduled') {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.scheduled', 'Bus is already assigned to a scheduled trip today'));
        } elseif ($driverActiveOnOtherBus) {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, dispatcher_t('readiness.trip_valid.driver_busy', 'Driver is already on another active trip today'));
        } elseif (!$destinationValid) {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), false, true, $destinationResolution['error'] ?: dispatcher_t('readiness.trip_valid.no_destination', 'Destination terminal is not set for this bus'));
        } else {
            $addItem('trip_valid', dispatcher_t('readiness.trip_valid', 'Trip valid'), true, false, dispatcher_t('readiness.trip_valid.ok', 'Ready to dispatch'));
        }

        $skip = $skipMap[$busId] ?? null;
        $skipActive = $skip !== null && !empty($skip['active']);

        $gateItems = array_values(array_filter($readiness, function ($item) {
            return !$item['ok'];
        }));
        $gatePriority = ['operational_valid', 'no_open_problem', 'terminal_entry', 'route_assigned', 'trip_valid', 'fuel_validated'];
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
        } elseif ($operationalBlocked || $openProblem !== null) {
            $status = 'blocked';
            $nextAction = null;
            $shortReason = $operationalBlocked
                ? 'Bus is "' . $operationalStatus . '" and cannot be dispatched'
                : ('Open problem report: ' . ($openProblem['problem_type'] !== '' ? $openProblem['problem_type'] : 'bus problem') . ' (' . $openProblem['status'] . ')');
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
            $nextAction = ($firstGate !== null && $firstGate['key'] === 'terminal_entry') ? null : 'prepare';
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
            'blocked' => dispatcher_t('status.blocked', 'UNAVAILABLE'),
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

        $actualTripNumber = null;
        if ($activeTrip !== null && isset($activeTrip['trip_number']) && $activeTrip['trip_number'] !== '') {
            $actualTripNumber = $activeTrip['trip_number'];
        } elseif ($lastCompletedTrip !== null && isset($lastCompletedTrip['trip_number']) && $lastCompletedTrip['trip_number'] !== '') {
            $actualTripNumber = $lastCompletedTrip['trip_number'];
        }

        $dispatchStatus = 'Idle';
        if ($activeTrip !== null) {
            $dispatchStatus = (string)($activeTrip['status'] ?? 'Departed');
        } elseif ($lastCompletedTrip !== null || ($completedCountByBus[$busId] ?? 0) > 0) {
            $dispatchStatus = 'Completed';
        }

        $entries[] = [
            'bus_id' => $busId,
            'plate_number' => $bus['plate_number'] ?? null,
            'bus_type' => $bus['bus_type'] ?? null,
            'seats' => isset($bus['seats']) ? (int)$bus['seats'] : null,
            'queue_position' => $position,
            'cycle' => ($completedCountByBus[$busId] ?? 0) + 1,
            'next_trip_number' => ($completedCountByBus[$busId] ?? 0) + 1,
            'actual_trip_number' => $actualTripNumber,
            'dispatch_status' => $dispatchStatus,
            'next_action' => $nextAction,
            'passenger_count' => isset($activeTrip['total_passengers']) ? (int)$activeTrip['total_passengers'] : null,
            'schedule' => $schedule !== '' ? $schedule : null,
            'route' => $route !== '' ? $route : null,
            'route_name' => $route !== '' ? $route : null,
            'destination' => [
                'terminal_id' => $destinationId,
                'terminal_name' => $destinationName,
            ],
            'origin_terminal' => [
                'terminal_id' => $originTerminalId,
                'terminal_name' => $originTerminalName,
            ],
            'current_location' => $locationLabel,
            'current_terminal_id' => $locationTerminalId,
            'driver_id' => $driver['driver_id'],
            'driver_name' => $driver['name'],
            'driver_source' => $driver['source'] ?? null,
            'operational_status' => $operationalStatus !== '' ? $operationalStatus : null,
            'problem' => $openProblem,
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
                'fresh' => $locationCheckedIn,
                'detail' => $presenceDetail,
                'checkout' => [
                    'recorded' => $checkoutMatchesDriver,
                    'at' => $checkout !== null ? ($checkout['at'] ?? null) : null,
                    'by' => $checkout !== null ? ($checkout['by'] ?? null) : null,
                    'by_name' => $checkout !== null ? ($checkout['by_name'] ?? null) : null,
                    'driver_id' => $checkout !== null ? ($checkout['driver_id'] ?? null) : null,
                    'driver_name' => $checkout !== null ? ($checkout['driver_name'] ?? null) : null,
                ],
            ],
            'fuel_reference' => [
                'validated' => $fuelValidated,
                'starting_odo' => ($fuel !== null && isset($fuel['starting_odo']) && $fuel['starting_odo'] !== null) ? (float)$fuel['starting_odo'] : null,
                'ending_odo' => ($fuel !== null && isset($fuel['ending_odo']) && $fuel['ending_odo'] !== null) ? (float)$fuel['ending_odo'] : null,
                'historical_kpl' => (function () use ($conn, $busId) {
                    $historical = dispatcher_bus_historical_kpl($conn, $busId);
                    return $historical !== null ? (float)$historical['kpl'] : null;
                })(),
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
            'queue_position' => $tripData['recorded_positions'][$tripId] ?? ($positionMap[$tripBusId] ?? ($publishedPositions[$tripBusId] ?? null)),
            'dispatcher_name' => $tripData['dispatcher_names'][(string)($tripRow['dispatcher_id'] ?? '')] ?? null,
            'overtake_reason' => $tripData['overtakes'][$tripId] ?? null,
        ];
    }

    /* Queue head: the first bus in the official order that has not departed.
       The dispatcher may only dispatch this bus next; when it is not ready,
       the readiness checklist explains what to clear first. */
    $nextBusId = null;
    $nextBusPosition = null;
    $nextReadyBusId = null;
    $nextReadyPosition = null;
    foreach ($entries as $entry) {
        $entryStatus = (string)($entry['status'] ?? '');
        if ($nextBusId === null && $entryStatus !== 'departed') {
            $nextBusId = (string)$entry['bus_id'];
            $nextBusPosition = (int)$entry['queue_position'];
        }
        if ($nextReadyBusId === null && $entryStatus === 'ready') {
            $nextReadyBusId = (string)$entry['bus_id'];
            $nextReadyPosition = (int)$entry['queue_position'];
        }
        if ($nextBusId !== null && $nextReadyBusId !== null) {
            break;
        }
    }

    $draftPending = false;
    if ($adminPublication === null) {
        $draftPending = dispatcher_queue_has_saved_rows($conn, $date);
    }

    return [
        'terminal' => $terminal,
        'date' => $date,
        'entries' => $entries,
        'history' => $history,
        'summary' => $summary,
        'admin_queue' => [
            'published' => $adminPublication !== null,
            'locked' => $state['admin_locked'],
            'published_at' => $adminPublication['published_at'] ?? null,
            'published_by' => $adminPublication['published_by'] ?? null,
            'next_bus_id' => $nextBusId,
            'next_position' => $nextBusPosition,
            'next_ready_bus_id' => $nextReadyBusId,
            'next_ready_position' => $nextReadyPosition,
            'draft_pending' => $draftPending,
        ],
        'available_drivers' => dispatcher_available_drivers($conn),
        'server_time' => date('c'),
    ];
}

