<?php declare(strict_types=1);

/**
 * Replace or revoke a bus's QR credential.
 *
 * Requires admin/super_admin, a reason, and security authorization for an
 * "edit" action bound to this bus. A replacement retires the old token (it
 * can never be reused), issues a new unique credential for the same bus, and
 * records the replacement date; a revocation retires the active credential
 * and immediately stops the code from resolving.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_security_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrService.php';

use Admin\Services\Bus\BusQrService;
use App\Core\Database;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'QR changes must be submitted with POST.']);
    exit;
}

$input = rmg_input();
$busRef = trim((string)($input['bus_ref'] ?? ''));
$busId = trim((string)($input['bus_id'] ?? ''));
$mode = strtolower(trim((string)($input['mode'] ?? 'replace')));
$reason = trim((string)($input['reason'] ?? ''));

if (!in_array($mode, ['replace', 'revoke'], true)) {
    rmg_fail(422, 'Unknown QR action.');
}
if ($busRef === '' && $busId === '') {
    rmg_fail(422, 'Missing bus reference.');
}
if (mb_strlen($reason) < 5) {
    rmg_fail(422, 'A reason for this change is required (at least 5 characters).');
}

try {
    $db = Database::get();
    if ($busRef !== '' && ctype_digit($busRef)) {
        $lookup = $db->prepare('SELECT id, bus_id FROM buses WHERE id = ? LIMIT 1');
        $lookup->execute([(int)$busRef]);
    } else {
        $lookup = $db->prepare('SELECT id, bus_id FROM buses WHERE bus_id = ? LIMIT 1');
        $lookup->execute([$busId]);
    }
    $bus = $lookup->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    rmg_fail(500, 'Unable to load the bus record. Please try again.');
}

if (!$bus) {
    rmg_fail(404, 'Bus not found.');
}

$busPk = (int)$bus['id'];
$busIdValue = (string)$bus['bus_id'];

// Security authorization last: a valid request consumes one code token.
rmg_guard_require('edit', 'bus', (string)$busPk);

$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$actorName = (string)($actor['user']['username'] ?? '');

try {
    BusQrService::ensureSchema($db);
    $qr = new BusQrService($db);

    if ($mode === 'revoke') {
        if ($qr->activeFor($busPk) === null) {
            rmg_fail(409, 'This bus has no active QR code to revoke.');
        }
        $qr->revoke($busPk, $actorName, $reason);
        $credential = null;
        $message = 'QR code for ' . $busIdValue . ' revoked. Its old code no longer resolves.';
    } else {
        $credential = $qr->replace($busPk, $busIdValue, $origin, $actorName, $reason);
        $message = 'QR code for ' . $busIdValue . ' replaced. The previous code no longer resolves.';
    }
} catch (Throwable $e) {
    error_log('QrReplace failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not update the bus QR code. Please try again.');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
    $audit = new \Finance\Models\AuditTrailModel();
    $audit->log(
        'Bus',
        $mode === 'revoke' ? 'QrRevoke' : 'QrReplace',
        ($mode === 'revoke' ? 'Revoked' : 'Replaced') . ' QR for bus ' . $busIdValue . ' by ' . ($actorName !== '' ? $actorName : 'unknown') . ' — Reason: ' . $reason,
        isset($actor['user']['id']) ? (int)$actor['user']['id'] : null,
        $actorName !== '' ? $actorName : null,
        $actor['role'],
        $busIdValue,
        null,
        ['reason' => $reason],
        'Success'
    );
} catch (Throwable $e) {
    error_log('QrReplace audit failed: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'mode' => $mode,
    'bus_id' => $busIdValue,
    'qr' => $credential === null ? null : [
        'token' => $credential['token'] ?? '',
        'payload_url' => $credential['payload_url'] ?? '',
        'status' => $credential['status'] ?? 'active',
        'created_at' => $credential['created_at'] ?? '',
        'replaced_at' => $credential['replaced_at'] ?? null,
    ],
    'message' => $message,
]);
