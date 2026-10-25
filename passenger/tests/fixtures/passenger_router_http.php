<?php declare(strict_types=1);

// Router-level fixture: every /passenger/ URL runs through the real front
// controller (passenger/index.php) or scan.php against an isolated SQLite DB.
if (PHP_SAPI !== 'cli-server' || !getenv('ONE_TIME_TEST_DB')) {
    http_response_code(403);
    exit;
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/bootstrap.php';
require_once __DIR__ . '/one_time_test_db.php';

$db = one_time_test_db();

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/_test/ping') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/seed' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $result = one_time_test_seed($db, is_array($input) ? $input : []);

    if (empty($result['ok'])) {
        http_response_code(400);
    }

    echo json_encode($result);
    exit;
}

if ($path === '/_test/session') {
    header('Content-Type: application/json');
    passenger_session_start();
    echo json_encode([
        'passenger_id' => $_SESSION['passenger_id'] ?? null,
        'is_one_time' => $_SESSION['is_one_time'] ?? null,
        'qr_bus' => $_SESSION['qr_bus'] ?? null,
        'qr_trip' => $_SESSION['qr_trip'] ?? null,
        'csrf' => passenger_csrf_token(),
        'session' => $_SESSION,
    ]);
    exit;
}

if ($path === '/_test/session-set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    passenger_session_start();
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $_SESSION = [];

    if (!empty($input['passenger_id'])) {
        $_SESSION['passenger_id'] = (int) $input['passenger_id'];
    }
    if (!empty($input['is_one_time'])) {
        $_SESSION['is_one_time'] = true;
    }
    if (!empty($input['qr_bus'])) {
        $_SESSION['qr_bus'] = (string) $input['qr_bus'];
    }
    if (!empty($input['qr_trip'])) {
        $_SESSION['qr_trip'] = (string) $input['qr_trip'];
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/session-put' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    passenger_session_start();
    $input = json_decode((string) file_get_contents('php://input'), true);
    foreach (is_array($input) ? $input : [] as $key => $value) {
        $_SESSION[$key] = $value;
    }
    echo json_encode(['ok' => true, 'session' => array_keys($_SESSION)]);
    exit;
}

if ($path === '/_test/passenger-row' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $stmt = $db->prepare(
        "INSERT INTO passengers (id, full_name, discount_type, registration_type, status, account_status, suspension_starts_at, suspension_ends_at, status_reason, email, phone, password, login_locked_until, contact_number)"
        . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        (int) ($input['id'] ?? 1),
        (string) ($input['full_name'] ?? 'Existing Passenger'),
        (string) ($input['discount_type'] ?? 'Regular'),
        (string) ($input['registration_type'] ?? 'regular'),
        (string) ($input['status'] ?? 'Active'),
        (string) ($input['account_status'] ?? 'ACTIVE'),
        $input['suspension_starts_at'] ?? null,
        $input['suspension_ends_at'] ?? null,
        $input['status_reason'] ?? null,
        $input['email'] ?? null,
        $input['phone'] ?? null,
        isset($input['password']) && $input['password'] !== null && $input['password'] !== ''
            ? password_hash((string) $input['password'], PASSWORD_DEFAULT)
            : null,
        $input['login_locked_until'] ?? null,
        $input['contact_number'] ?? null,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/remember-row' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $stmt = $db->prepare(
        "INSERT INTO passenger_remember_tokens (passenger_id, selector, token_hash, expires, device_label, created_at)"
        . " VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([
        (int) ($input['passenger_id'] ?? 1),
        (string) ($input['selector'] ?? 'aaaaaaaaaaaaaaaa'),
        (string) ($input['token_hash'] ?? hash('sha256', 'fixture-token')),
        (string) ($input['expires'] ?? date('Y-m-d H:i:s', time() + 86400)),
        'fixture',
        date('Y-m-d H:i:s'),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/auth-report') {
    header('Content-Type: application/json');
    echo json_encode([
        'remember' => $db->query("SELECT passenger_id, selector, revoked_at FROM passenger_remember_tokens ORDER BY id")->fetchAll(),
        'sessions' => $db->query("SELECT passenger_id, session_hash, revoked_at, revoked_reason FROM passenger_sessions ORDER BY id")->fetchAll(),
    ]);
    exit;
}

if ($path === '/_test/trip-insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $allowed = ['passenger_id', 'bus_no', 'trip_no', 'driver_id', 'passenger_type', 'boarding_point',
        'destination', 'fare', 'payment_status', 'payment_method', 'trip_status', 'identification_source',
        'dropoff_time', 'archived_status', 'submission_token'];
    $columns = [];
    $values = [];
    foreach ($allowed as $column) {
        if (array_key_exists($column, $input)) {
            $columns[] = $column;
            $values[] = $input[$column];
        }
    }
    if ($columns === []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'no allowed columns']);
        exit;
    }
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $db->prepare("INSERT INTO passenger_trips (" . implode(', ', $columns) . ") VALUES ($placeholders)");
    $stmt->execute($values);
    echo json_encode(['ok' => true, 'id' => (int) $db->lastInsertId()]);
    exit;
}

if ($path === '/_test/trip-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $id = (int) ($input['id'] ?? 0);
    unset($input['id']);
    $allowed = ['passenger_id', 'bus_no', 'trip_no', 'driver_id', 'passenger_type', 'boarding_point',
        'destination', 'fare', 'payment_status', 'payment_method', 'trip_status', 'identification_source',
        'dropoff_time', 'archived_status', 'submission_token'];
    $sets = [];
    $values = [];
    foreach ($allowed as $column) {
        if (array_key_exists($column, $input)) {
            $sets[] = $column . ' = ?';
            $values[] = $input[$column];
        }
    }
    if ($id <= 0 || $sets === []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'id and at least one column are required']);
        exit;
    }
    $values[] = $id;
    $stmt = $db->prepare("UPDATE passenger_trips SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->execute($values);
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/bus-trip-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $stmt = $db->prepare("UPDATE bus_trips SET status = ? WHERE id = ?");
    $stmt->execute([(string) ($input['status'] ?? 'Departed'), (int) ($input['id'] ?? 77)]);
    echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
    exit;
}

if ($path === '/_test/trip-table-swap' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $mode = (string) ($input['mode'] ?? 'fail');

    if ($mode === 'fail') {
        $db->exec("DROP TABLE IF EXISTS passenger_trips_stub");
        $db->exec("CREATE TABLE passenger_trips_stub (id INTEGER PRIMARY KEY)");
        $db->exec("ALTER TABLE passenger_trips RENAME TO passenger_trips_backup");
        $db->exec("ALTER TABLE passenger_trips_stub RENAME TO passenger_trips");
    } else {
        $db->exec("DROP TABLE IF EXISTS passenger_trips");
        $db->exec("ALTER TABLE passenger_trips_backup RENAME TO passenger_trips");
    }

    echo json_encode(['ok' => true, 'mode' => $mode]);
    exit;
}

if ($path === '/_test/trip-list') {
    header('Content-Type: application/json');
    $rows = $db->query("
        SELECT id, passenger_id, bus_no, trip_no, trip_status, payment_status, payment_method, fare,
               dropoff_time, archived_status, identification_source, submission_token
        FROM passenger_trips ORDER BY id
    ")->fetchAll();
    echo json_encode(['rows' => $rows, 'count' => count($rows)]);
    exit;
}

if ($path === '/_test/passenger-list') {
    header('Content-Type: application/json');
    $rows = $db->query("
        SELECT id, full_name, email, phone, contact_number, registration_type, status, account_status, passenger_code
        FROM passengers ORDER BY id
    ")->fetchAll();
    echo json_encode(['rows' => $rows, 'count' => count($rows)]);
    exit;
}

if ($path === '/_test/session-row' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    passenger_session_start();
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $passengerId = (int) ($input['passenger_id'] ?? ($_SESSION['passenger_id'] ?? 0));
    $revoked = !empty($input['revoked']);
    $hash = hash('sha256', (string) session_id());
    $now = date('Y-m-d H:i:s');

    $db->prepare("DELETE FROM passenger_sessions WHERE session_hash = ?")->execute([$hash]);
    $stmt = $db->prepare(
        "INSERT INTO passenger_sessions (passenger_id, session_hash, device_label, ip_address, created_at, last_seen_at, revoked_at, revoked_reason)"
        . " VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $passengerId,
        $hash,
        'fixture',
        '127.0.0.1',
        $now,
        $now,
        $revoked ? $now : null,
        $revoked ? 'login' : null,
    ]);

    echo json_encode(['ok' => true, 'session_hash' => $hash]);
    exit;
}

if ($path === '/_test/state-fail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Replace the device-session table with an incompatible one so the
    // session-state read fails, exercising the "unverifiable" classification.
    $db->exec("DROP TABLE IF EXISTS passenger_sessions");
    $db->exec("CREATE TABLE passenger_sessions (id INTEGER PRIMARY KEY)");
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/state-restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $db->exec("DROP TABLE IF EXISTS passenger_sessions");
    $db->exec(
        "CREATE TABLE passenger_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,"
        . " session_hash TEXT, device_label TEXT, ip_address TEXT, created_at TEXT, last_seen_at TEXT,"
        . " revoked_at TEXT, revoked_reason TEXT)"
    );
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/account-notifications-drop' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_account_notifications'")->fetchColumn();
    if ($object === 'view') {
        $db->exec('DROP VIEW passenger_account_notifications');
    } elseif ($object === 'table') {
        $db->exec('DROP TABLE passenger_account_notifications');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/account-notifications-break' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Replace the notification storage with an incompatible view: reads fail
    // and the additive schema heal cannot repair it, so the page-side guard is
    // the only thing keeping the homepage alive.
    $object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_account_notifications'")->fetchColumn();
    if ($object === 'view') {
        $db->exec('DROP VIEW passenger_account_notifications');
    } elseif ($object === 'table') {
        $db->exec('DROP TABLE passenger_account_notifications');
    }
    $db->exec("CREATE VIEW passenger_account_notifications AS SELECT 1 AS id, 'fixture-notification' AS message");
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/account-notifications-state') {
    header('Content-Type: application/json');
    $row = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_account_notifications'")->fetch();
    echo json_encode(['object' => $row ? $row['type'] : null]);
    exit;
}

if ($path === '/_test/remember-tokens-break' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Incompatible view: the remember-token write fails and schema healing
    // cannot repair it, so the login-side guard must keep the flow alive.
    $object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_remember_tokens'")->fetchColumn();
    if ($object === 'view') {
        $db->exec('DROP VIEW passenger_remember_tokens');
    } elseif ($object === 'table') {
        $db->exec('DROP TABLE passenger_remember_tokens');
    }
    $db->exec("CREATE VIEW passenger_remember_tokens AS SELECT 1 AS id");
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/identity-claims-break' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_identity_claims'")->fetchColumn();
    if ($object === 'view') {
        $db->exec('DROP VIEW passenger_identity_claims');
    } elseif ($object === 'table') {
        $db->exec('DROP TABLE passenger_identity_claims');
    }
    $db->exec("CREATE VIEW passenger_identity_claims AS SELECT 1 AS identity_kind");
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/identity-claim-put' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $db->prepare('INSERT OR REPLACE INTO passenger_identity_claims (identity_kind, identity_value, passenger_id) VALUES (?, ?, ?)')
        ->execute([
            (string) ($input['identity_kind'] ?? 'email'),
            (string) ($input['identity_value'] ?? ''),
            (int) ($input['passenger_id'] ?? 0),
        ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/claim-list') {
    header('Content-Type: application/json');
    echo json_encode([
        'claims' => $db->query('SELECT identity_kind, identity_value, passenger_id FROM passenger_identity_claims ORDER BY identity_kind, identity_value')->fetchAll(),
    ]);
    exit;
}

if ($path === '/_test/audit-trail-drop' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $db->exec('DROP TABLE IF EXISTS audit_trail');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/passengers-break' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // The live table moves aside so the login lookup fails like a database
    // problem; used to prove the login path degrades with a friendly redirect.
    $db->exec('DROP TABLE IF EXISTS passengers_hidden');
    $db->exec('ALTER TABLE passengers RENAME TO passengers_hidden');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/state') {
    header('Content-Type: application/json');
    passenger_session_start();
    $hash = hash('sha256', (string) session_id());

    $sessionRow = null;
    try {
        $stmt = $db->prepare("SELECT * FROM passenger_sessions WHERE session_hash = ?");
        $stmt->execute([$hash]);
        $sessionRow = $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
        $sessionRow = null;
    }

    $passengerRow = null;
    $id = isset($_GET['passenger_id']) ? (int) $_GET['passenger_id'] : (int) ($_SESSION['passenger_id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $db->prepare("SELECT * FROM passengers WHERE id = ?");
            $stmt->execute([$id]);
            $passengerRow = $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            $passengerRow = null;
        }
    }

    echo json_encode([
        'ok' => true,
        'session_id' => session_id(),
        'session_hash' => $hash,
        'session_row' => $sessionRow,
        'passenger' => $passengerRow,
    ]);
    exit;
}

if ($path === '/_test/trip-by-token') {
    header('Content-Type: application/json');
    $stmt = $db->prepare("SELECT * FROM passenger_trips WHERE submission_token = ?");
    $stmt->execute([(string) ($_GET['token'] ?? '')]);
    $rows = $stmt->fetchAll();
    echo json_encode(['row' => $rows[0] ?? null, 'count' => count($rows)]);
    exit;
}

if ($path === '/_test/trip-row') {
    header('Content-Type: application/json');
    $stmt = $db->prepare("SELECT * FROM passenger_trips WHERE id = ?");
    $stmt->execute([isset($_GET['id']) ? (int) $_GET['id'] : 0]);
    echo json_encode(['row' => $stmt->fetch() ?: null]);
    exit;
}

if ($path === '/passenger/scan.php') {
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/scan.php';
    exit;
}

if (strpos($path, '/passenger') === 0) {
    $route = ltrim(substr($path, strlen('/passenger')), '/');
    $_GET['route'] = $route === '' ? 'login' : $route;
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/index.php';
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'path' => $path]);
