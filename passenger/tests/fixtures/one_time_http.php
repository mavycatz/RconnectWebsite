<?php declare(strict_types=1);

// Isolated local test router for the One-Time GPS boarding flow. Never opens
// the application database; every request runs against a temp SQLite file.
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
        'snapshot' => $_SESSION['qr_location_snapshot'] ?? null,
        'boarding_lock' => $_SESSION['ot_boarding_lock'] ?? null,
        'qr_bus' => $_SESSION['qr_bus'] ?? null,
        'qr_trip' => $_SESSION['qr_trip'] ?? null,
        'passenger_id' => $_SESSION['passenger_id'] ?? null,
        'is_one_time' => $_SESSION['is_one_time'] ?? null,
    ]);
    exit;
}

if ($path === '/_test/reset-session') {
    header('Content-Type: application/json');
    passenger_session_start();
    $_SESSION = [];
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/_test/trip-row') {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $stmt = $db->prepare("SELECT * FROM passenger_trips WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['row' => $stmt->fetch() ?: null, 'companions' => $db->query("SELECT id, passenger_id, companion_of, boarding_point, destination, destination_id, fare, trip_status, identification_source FROM passenger_trips WHERE companion_of = " . $id)->fetchAll()]);
    exit;
}

if ($path === '/_test/trip-by-token') {
    header('Content-Type: application/json');
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $stmt = $db->prepare("SELECT * FROM passenger_trips WHERE submission_token = ?");
    $stmt->execute([$token]);
    $rows = $stmt->fetchAll();
    echo json_encode(['row' => $rows[0] ?? null, 'count' => count($rows)]);
    exit;
}

if ($path === '/passenger/scan.php') {
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/scan.php';
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/OneTimeController.php';

if ($path === '/passenger/one-time/trip') {
    passenger_session_start();
    (new OneTimeController())->trip();
    exit;
}

if ($path === '/passenger/one-time/register') {
    passenger_session_start();
    (new OneTimeController())->register();
    exit;
}

if ($path === '/passenger/one-time/fare') {
    passenger_session_start();
    (new OneTimeController())->fare();
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'path' => $path]);
