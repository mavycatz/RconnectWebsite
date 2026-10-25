<?php
declare(strict_types=1);

/**
 * Archive a bus.
 *
 * Requires admin/super_admin, a reason, and security authorization for an
 * "archive" action bound to this bus. Archiving preserves the Bus ID, the QR
 * credential history, and all historical records; the bus can no longer be
 * assigned to new trips and its QR cannot initiate boarding.
 */

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../api/RoleManagement/_security_guard.php';

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;

Session::start();
header('Content-Type: application/json');

function archive_bus_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => $message]);
    exit;
}

if (!Auth::check()) {
    archive_bus_fail(403, 'Unauthorized');
}
$actorRole = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));
if (!in_array($actorRole, ['admin', 'super_admin'], true)) {
    archive_bus_fail(403, 'Unauthorized');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    archive_bus_fail(405, 'Invalid request method.');
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;
$busRef = trim((string)($input['bus_ref'] ?? ''));
$busId = trim((string)($input['bus_id'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));

if ($busRef === '' && $busId === '') {
    archive_bus_fail(422, 'Missing bus reference.');
}
if (mb_strlen($reason) < 5) {
    archive_bus_fail(422, 'A reason for deactivating this bus is required (at least 5 characters).');
}

try {
    $db = Database::get();
    if ($busRef !== '' && ctype_digit($busRef)) {
        $lookup = $db->prepare('SELECT id, bus_id, status FROM buses WHERE id = ? LIMIT 1');
        $lookup->execute([(int)$busRef]);
    } else {
        $lookup = $db->prepare('SELECT id, bus_id, status FROM buses WHERE bus_id = ? LIMIT 1');
        $lookup->execute([$busId]);
    }
    $bus = $lookup->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    archive_bus_fail(500, 'Unable to load the bus record. Please try again.');
}

if (!$bus) {
    archive_bus_fail(404, 'Bus not found.');
}
if (strtolower((string)$bus['status']) === 'archived') {
    archive_bus_fail(409, 'This bus is already archived.');
}

// Security authorization last: a valid request consumes one code token.
rmg_guard_require('archive', 'bus', (string)$bus['id']);

try {
    $stmt = $db->prepare(
        "UPDATE buses SET status = 'Archived'"
        . " WHERE id = ?"
    );
    $stmt->execute([(int)$bus['id']]);
} catch (Throwable $e) {
    error_log('archive_bus failed: ' . $e->getMessage());
    archive_bus_fail(500, 'Unable to archive the bus. Please try again.');
}

try {
    require_once __DIR__ . '/../../finance/models/AuditTrailModel.php';
    $audit = new \Finance\Models\AuditTrailModel();
    $audit->log(
        'Bus',
        'Archive',
        'Archived bus ' . $bus['bus_id'] . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null,
        (string)$bus['bus_id'],
        null,
        ['reason' => $reason],
        'Success'
    );
} catch (Throwable $e) {
    error_log('archive_bus audit failed: ' . $e->getMessage());
}

echo json_encode([
    'status' => 'success',
    'success' => true,
    'bus_id' => (string)$bus['bus_id'],
    'message' => 'Bus ' . $bus['bus_id'] . ' archived. Its QR no longer initiates boarding.',
]);
