<?php

header('Content-Type: application/json');
/* State-bearing responses: never served from a browser/PWA cache. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['passenger_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
use App\Core\Database;

$conn = Database::tryGet();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'SERVICE_UNAVAILABLE', 'message' => 'Database connection temporarily unavailable']);
    exit;
}

// Auto-create table if it doesn't exist (one-time migration)
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'passenger_trip_state'");
    if (!$stmt->fetch()) {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS passenger_trip_state (
                id INT AUTO_INCREMENT PRIMARY KEY,
                passenger_id INT NOT NULL,
                state_key VARCHAR(64) NOT NULL,
                state_value TEXT,
                version BIGINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_passenger_state (passenger_id, state_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (\PDOException $e) {
    // Silently skip — migration may already exist
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'save':
        handleSave($conn, $passenger_id);
        break;

    case 'restore':
        handleRestore($conn, $passenger_id);
        break;

    case 'clear':
        handleClear($conn, $passenger_id);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

function handleSave($conn, $passenger_id)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $state = $input['state'] ?? null;

    if (!$state || !is_array($state)) {
        echo json_encode(['success' => false, 'message' => 'Invalid state data']);
        exit;
    }

    $version = (int)($input['version'] ?? round(microtime(true) * 1000));

    try {
        $stmt = $conn->prepare("
            INSERT INTO passenger_trip_state (passenger_id, state_key, state_value, version)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                state_value = VALUES(state_value),
                version = VALUES(version),
                updated_at = NOW()
        ");

        foreach ($state as $key => $value) {
            $stmt->execute([$passenger_id, $key, (string) $value, $version]);
        }

        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        error_log('Passenger trip state save failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to save trip state.']);
    }
    exit;
}

function handleRestore($conn, $passenger_id)
{
    try {
        $stmt = $conn->prepare("
            SELECT state_key, state_value, version
            FROM passenger_trip_state
            WHERE passenger_id = ?
        ");
        $stmt->execute([$passenger_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $state = [];
        foreach ($rows as $row) {
            $state[$row['state_key']] = $row['state_value'];
        }

        echo json_encode([
            'success' => true,
            'state' => $state,
            'hasState' => !empty($state)
        ]);
    } catch (\PDOException $e) {
        error_log('Passenger trip state restore failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to restore trip state.']);
    }
    exit;
}

function handleClear($conn, $passenger_id)
{
    try {
        $stmt = $conn->prepare("DELETE FROM passenger_trip_state WHERE passenger_id = ?");
        $stmt->execute([$passenger_id]);
        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        error_log('Passenger trip state clear failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to clear trip state.']);
    }
    exit;
}
