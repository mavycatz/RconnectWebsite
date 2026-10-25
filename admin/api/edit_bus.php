<?php
declare(strict_types=1);

/**
 * Update a bus.
 *
 * The acting admin must hold admin or super_admin and pass security
 * authorization for an "edit" action bound to this bus. Only the fields the
 * request supplies are changed - the simplified Bus Management form sends
 * just the plate number and bus type, so specifications, assignments,
 * compliance dates, operational status, and QR data are preserved. The Bus ID
 * and the bus's QR credential are never changed here; an archived bus keeps
 * its archived status until it is explicitly restored.
 */

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../api/RoleManagement/_security_guard.php';
require_once __DIR__ . '/../services/Bus/BusFields.php';
require_once __DIR__ . '/../services/Bus/BusAssignmentService.php';
require_once __DIR__ . '/../services/Bus/BusRegistrationValidator.php';
require_once __DIR__ . '/../services/Bus/BusUpdatePayload.php';

use Admin\Services\Bus\BusAssignmentService;
use Admin\Services\Bus\BusFields;
use Admin\Services\Bus\BusRegistrationValidator;
use Admin\Services\Bus\BusUpdatePayload;
use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;
use App\Models\Bus;

Session::start();
header('Content-Type: application/json');

function edit_bus_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => $message]);
    exit;
}

if (!Auth::check()) {
    edit_bus_fail(403, 'Unauthorized');
}
$actorRole = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));
if (!in_array($actorRole, ['admin', 'super_admin'], true)) {
    edit_bus_fail(403, 'Unauthorized');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    edit_bus_fail(405, 'Invalid request method.');
}

$ref = trim((string)($_POST['bus_ref'] ?? ''));
$legacyRef = trim((string)($_POST['bus_id_hidden'] ?? $_POST['bus_id'] ?? ''));
if ($ref === '' && $legacyRef === '') {
    edit_bus_fail(422, 'Missing bus reference.');
}

$reason = trim((string)($_POST['reason'] ?? ''));

$config = @require __DIR__ . '/../config/bus_options.php';
$config = is_array($config) ? $config : [];

try {
    $db = Database::get();
} catch (Throwable $e) {
    edit_bus_fail(500, 'Unable to reach the database. Please try again.');
}

try {
    if ($ref !== '' && ctype_digit($ref)) {
        $lookup = $db->prepare('SELECT * FROM buses WHERE id = ? LIMIT 1');
        $lookup->execute([(int)$ref]);
    } else {
        $lookup = $db->prepare('SELECT * FROM buses WHERE bus_id = ? LIMIT 1');
        $lookup->execute([$legacyRef !== '' ? $legacyRef : $ref]);
    }
    $current = $lookup->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    edit_bus_fail(500, 'Unable to load the bus record. Please try again.');
}

if (!$current) {
    edit_bus_fail(404, 'Bus not found.');
}

$busPk = (int)$current['id'];
$busId = (string)$current['bus_id'];

$assignments = new BusAssignmentService($db);

/* Supplied keys win; everything else is preserved from the current record. */
$built = BusUpdatePayload::build($current, $_POST, $config);
$driver = (string)$built['driver'];
$driverPosted = (bool)$built['driver_posted'];
$fromTerminal = $built['from_terminal_id'];
$toTerminal = $built['to_terminal_id'];
$inspectorRef = $built['inspector'];
$route = (string)$built['route'];
$effectiveSeats = (int)$built['seats'];
$effectiveStanding = (int)$built['standing_capacity'];

/* Validate the values that will actually be saved (supplied ones plus the
   preserved effective capacity), not the zeros of a minimal form. */
$validationInput = $_POST;
$validationInput['seats'] = $effectiveSeats;
$validationInput['standing_capacity'] = $effectiveStanding;
$validationError = BusRegistrationValidator::validate($validationInput, $config);
if ($validationError !== null) {
    edit_bus_fail(422, $validationError);
}

/* Eligibility checks only for the fields the request changes. */
$eligibilityError = $driverPosted && $driver !== '' ? $assignments->validateDriver($driver) : null;
if ($eligibilityError === null && array_key_exists('assigned_inspector_id', $_POST)) {
    $eligibilityError = $assignments->validateInspector($inspectorRef !== null && $inspectorRef > 0 ? $inspectorRef : null);
}
if ($eligibilityError === null && array_key_exists('from_terminal_id', $_POST)) {
    $eligibilityError = $assignments->validateTerminal($fromTerminal);
}
if ($eligibilityError === null && array_key_exists('to_terminal_id', $_POST)) {
    $eligibilityError = $assignments->validateTerminal($toTerminal);
}
if ($eligibilityError === null && array_key_exists('route_name', $_POST) && $route !== '') {
    $eligibilityError = $assignments->validateRoute($route);
}
if ($eligibilityError !== null) {
    edit_bus_fail(422, $eligibilityError);
}

// Security authorization last: a valid request consumes one code token.
rmg_guard_require('edit', 'bus', (string)$busPk);

try {
    BusFields::ensureColumns($db);

    $bus = new Bus($db);
    $updated = $bus->update($busId, $built['data']);

    if (!$updated) {
        throw new RuntimeException('The bus record could not be updated.');
    }
} catch (Throwable $e) {
    error_log('edit_bus failed: ' . $e->getMessage());
    edit_bus_fail(500, 'Unable to update the bus. Please try again.');
}

/* --------------------------- assignment updates --------------------------- */

$movedFrom = null;
$previousDriver = trim((string)($current['driver'] ?? ''));
if (strcasecmp($previousDriver, 'Unassigned') === 0) {
    $previousDriver = '';
}
$allowMove = !empty($_POST['allow_move']);
try {
    if ($driverPosted) {
        if ($driver !== '' && $driver !== $previousDriver) {
            $assignment = $assignments->assign($busId, $driver, $allowMove, (string)($_SESSION['user']['username'] ?? ''));
            if (!$assignment['success']) {
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'code' => $assignment['conflict_bus'] !== null ? 'driver_conflict' : 'assignment_failed',
                    'conflict_bus' => $assignment['conflict_bus'],
                    'message' => $assignment['error'] ?? 'The driver assignment could not be saved.',
                ]);
                exit;
            }
            $movedFrom = $assignment['moved_from'] ?? null;
        } elseif ($driver === '' && $previousDriver !== '') {
            $assignments->unassign($busId);
        }
    }
} catch (Throwable $e) {
    error_log('edit_bus assignment failed: ' . $e->getMessage());
}

try {
    require_once __DIR__ . '/../../finance/models/AuditTrailModel.php';
    $audit = new \Finance\Models\AuditTrailModel();
    $audit->log(
        'Bus',
        'Update',
        'Updated bus ' . $busId . ' by ' . ($_SESSION['user']['username'] ?? 'unknown')
            . ($reason !== '' ? ' — Reason: ' . $reason : ''),
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null,
        $busId,
        null,
        [
            'reason' => $reason,
            'moved_from' => $movedFrom,
            'driver' => $driverPosted ? $driver : null,
            'operational_status' => array_key_exists('operational_status', $_POST) ? trim((string)$_POST['operational_status']) : null,
        ],
        'Success'
    );
} catch (Throwable $e) {
    error_log('edit_bus audit failed: ' . $e->getMessage());
}

echo json_encode([
    'status' => 'success',
    'success' => true,
    'bus_id' => $busId,
    'moved_from' => $movedFrom,
    'message' => 'Bus ' . $busId . ' updated successfully.',
]);
