<?php
declare(strict_types=1);

/**
 * Restore an archived bus.
 *
 * Requires admin/super_admin, a reason of at least five characters, and
 * security authorization for a "restore" action bound to this bus. The
 * request may additionally supply bus detail fields (the same set the edit
 * form offers); the shared guard applies the supplied details and performs
 * the restore in one authorized transaction, preserving every field not
 * supplied plus the original Bus ID, QR credential, assignments, archive
 * history, and operational status.
 */

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../api/RoleManagement/_security_guard.php';

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;

Session::start();
header('Content-Type: application/json');

function restore_bus_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => $message]);
    exit;
}

if (!Auth::check()) {
    restore_bus_fail(403, 'Unauthorized');
}
$actorRole = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));
if (!in_array($actorRole, ['admin', 'super_admin'], true)) {
    restore_bus_fail(403, 'Unauthorized');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    restore_bus_fail(405, 'Invalid request method.');
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;
$busRef = trim((string)($input['bus_ref'] ?? ''));
$busId = trim((string)($input['bus_id'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));

if ($busRef === '' && $busId === '') {
    restore_bus_fail(422, 'Missing bus reference.');
}
if (mb_strlen($reason) < 5) {
    restore_bus_fail(422, 'A reason for reactivating this bus is required (at least 5 characters).');
}

try {
    $db = Database::get();
    if ($busRef !== '' && ctype_digit($busRef)) {
        $lookup = $db->prepare('SELECT id, bus_id, status, operational_status FROM buses WHERE id = ? LIMIT 1');
        $lookup->execute([(int)$busRef]);
    } else {
        $lookup = $db->prepare('SELECT id, bus_id, status, operational_status FROM buses WHERE bus_id = ? LIMIT 1');
        $lookup->execute([$busId]);
    }
    $bus = $lookup->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    restore_bus_fail(500, 'Unable to load the bus record. Please try again.');
}

if (!$bus) {
    restore_bus_fail(404, 'Bus not found.');
}
if (strtolower((string)$bus['status']) !== 'archived') {
    restore_bus_fail(409, 'This bus is not archived.');
}

// Security authorization last: a valid request consumes one code token. The
// shared guard performs the restore transition (including any supplied bus
// detail fields) inside one transaction and returns the JSON response; a
// failed transition leaves the bus archived with its stored details intact.
rmg_guard_require('restore', 'bus', (string)$bus['id']);
