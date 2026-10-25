<?php
declare(strict_types=1);

/**
 * Register a bus.
 *
 * Authorization and the security authorization code are verified here, before
 * any write: the acting admin must hold admin or super_admin, and the managed
 * Security Authorization Code must be configured and verified for an "add"
 * action bound to this exact payload.
 *
 * The Bus ID is allocated atomically inside the transaction (never typed by
 * hand, never reused from an archived bus), and the bus's unique QR
 * credential is created in the same transaction, so a registered bus is
 * immediately QR-usable.
 */

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../api/RoleManagement/_security_guard.php';
require_once __DIR__ . '/../services/Bus/BusFields.php';
require_once __DIR__ . '/../services/Bus/BusDuplicates.php';
require_once __DIR__ . '/../services/Bus/BusQrService.php';
require_once __DIR__ . '/../services/Bus/BusRegistrationValidator.php';
require_once __DIR__ . '/../services/Bus/BusAssignmentService.php';
require_once __DIR__ . '/../services/RoleManagement/UserIdService.php';

use Admin\Services\Bus\BusAssignmentService;
use Admin\Services\Bus\BusDuplicates;
use Admin\Services\Bus\BusFields;
use Admin\Services\Bus\BusQrService;
use Admin\Services\Bus\BusRegistrationValidator;
use Admin\Services\RoleManagement\UserIdService;
use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;
use App\Models\Bus;

Session::start();

header('Content-Type: application/json');

function add_bus_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => $message,
    ]);
    exit;
}

if (!Auth::check()) {
    add_bus_fail(403, 'Unauthorized');
}

$actorRole = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));
if (!in_array($actorRole, ['admin', 'super_admin'], true)) {
    add_bus_fail(403, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    add_bus_fail(405, 'Invalid request method.');
}

/* ------------------------------ validation ------------------------------ */

$config = @require __DIR__ . '/../config/bus_options.php';
$config = is_array($config) ? $config : [];

$plate = trim((string)($_POST['plate_number'] ?? ''));
$busType = trim((string)($_POST['bus_type'] ?? '')) ?: null;

/* The simplified form only sends the plate number and bus type: fill the
   fleet capacity standard for the chosen type so validation has real
   numbers instead of zeros. */
$capacityDefaults = $busType !== null ? BusRegistrationValidator::capacityDefaults($config, $busType) : null;
$seatsProvided = array_key_exists('seats', $_POST) && trim((string)$_POST['seats']) !== '';
$standingProvided = array_key_exists('standing_capacity', $_POST) && trim((string)$_POST['standing_capacity']) !== '';
$seats = $seatsProvided ? (int)$_POST['seats'] : (int)($capacityDefaults['seats'] ?? 0);
$standing = $standingProvided ? (int)$_POST['standing_capacity'] : (int)($capacityDefaults['standing'] ?? 0);

$validationInput = $_POST;
$validationInput['seats'] = $seats;
$validationInput['standing_capacity'] = $standing;
$validationError = BusRegistrationValidator::validate($validationInput, $config);
if ($validationError !== null) {
    add_bus_fail(422, $validationError);
}

$status = trim((string)($_POST['status'] ?? 'Active'));
$operationalStatus = trim((string)($_POST['operational_status'] ?? ''));
$dates = BusRegistrationValidator::normalizedDates($_POST);

$fromTerminal = !empty($_POST['from_terminal_id']) ? (int)$_POST['from_terminal_id'] : null;
$toTerminal = !empty($_POST['to_terminal_id']) ? (int)$_POST['to_terminal_id'] : null;

$assignee = trim((string)($_POST['driver'] ?? ''));
if ($assignee === 'Unassigned') {
    $assignee = '';
}

$inspectorRef = !empty($_POST['assigned_inspector_id']) ? (int)$_POST['assigned_inspector_id'] : null;

try {
    $db = Database::get();
} catch (Throwable $e) {
    add_bus_fail(500, 'Unable to reach the database. Please try again.');
}

try {
    $duplicates = new BusDuplicates($db);
    $plateMatches = $duplicates->find($plate);
    if ($plateMatches) {
        $conflict = $plateMatches[0];
        add_bus_fail(409, 'Plate number ' . $conflict['plate'] . ' is already used by bus ' . ($conflict['bus_id'] !== '' ? $conflict['bus_id'] : '#' . $conflict['ref']) . '.');
    }

    if ($assignee !== '') {
        $driverCheck = $db->prepare(
            "SELECT 1 FROM drivers WHERE driver_id = ?"
            . " AND CONVERT(status USING utf8mb4) COLLATE utf8mb4_general_ci = 'Active' LIMIT 1"
        );
        $driverCheck->execute([$assignee]);
        if (!$driverCheck->fetchColumn()) {
            add_bus_fail(422, 'Select an eligible active driver.');
        }
    }
} catch (Throwable $e) {
    error_log('add_bus validation failed: ' . $e->getMessage());
    add_bus_fail(500, 'Unable to validate the bus details. Please try again.');
}

// Security authorization last: a valid request consumes one code token.
rmg_guard_require('add', 'bus', null);

/* --------------------------------- write --------------------------------- */

$busPk = 0;
$busId = '';
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

try {
    BusFields::ensureColumns($db);
    BusQrService::ensureSchema($db);
    UserIdService::ensureSchema($db);
} catch (Throwable $e) {
    error_log('add_bus schema prep failed: ' . $e->getMessage());
    add_bus_fail(500, 'Unable to prepare the bus records. Please try again.');
}

try {
    $db->beginTransaction();

    $userIdService = new UserIdService($db);
    $busId = $userIdService->allocateAvailable('bus');

    $bus = new Bus($db);
    $inserted = $bus->insert([
        'bus_id' => $busId,
        'plate_number' => $plate,
        'bus_type' => $busType,
        'seats' => $seats,
        'standing_capacity' => $standing,
        'accessible_features' => trim((string)($_POST['accessible_features'] ?? '')),
        'driver' => $assignee !== '' ? $assignee : 'Unassigned',
        'from_terminal_id' => $fromTerminal,
        'to_terminal_id' => $toTerminal,
        'route_name' => trim((string)($_POST['route_name'] ?? '')),
        'terminal' => trim((string)($_POST['terminal'] ?? '')),
        'schedule' => trim((string)($_POST['schedule'] ?? '')),
        'status' => $status,
        'qr_code' => 'Pending',
        'unit_number' => trim((string)($_POST['unit_number'] ?? '')),
        'manufacturer' => trim((string)($_POST['manufacturer'] ?? '')),
        'model' => trim((string)($_POST['model'] ?? '')),
        'year_model' => trim((string)($_POST['year_model'] ?? '')),
        'engine_number' => trim((string)($_POST['engine_number'] ?? '')),
        'chassis_number' => trim((string)($_POST['chassis_number'] ?? '')),
        'fuel_type' => trim((string)($_POST['fuel_type'] ?? '')),
        'transmission_type' => trim((string)($_POST['transmission_type'] ?? '')),
        'air_conditioning' => trim((string)($_POST['air_conditioning'] ?? '')),
        'vehicle_ownership' => trim((string)($_POST['vehicle_ownership'] ?? '')),
        'assigned_inspector_id' => $inspectorRef,
        'operational_status' => $operationalStatus !== '' ? $operationalStatus : null,
        'registration_month' => trim((string)($_POST['registration_month'] ?? '')),
        'registration_expiry' => $dates['registration_expiry'],
        'insurance_expiry' => $dates['insurance_expiry'],
        'inspection_date' => $dates['inspection_date'],
        'next_inspection_date' => $dates['next_inspection_date'],
        'created_by' => (string)($_SESSION['user']['username'] ?? ''),
    ]);

    if (!$inserted) {
        throw new RuntimeException('The bus record could not be stored.');
    }

    $busPk = (int)$db->lastInsertId();

    $qrService = new BusQrService($db);
    $credential = $qrService->ensureForBus(
        $busPk,
        $busId,
        $origin,
        (string)($_SESSION['user']['username'] ?? '')
    );

    // Canonical driver assignment, mirrored on the legacy column for readers
    // that still use buses.driver.
    if ($assignee !== '') {
        $assignments = new BusAssignmentService($db);
        $assignment = $assignments->assign($busId, $assignee, false, (string)($_SESSION['user']['username'] ?? ''));
        if (!$assignment['success']) {
            throw new RuntimeException($assignment['error'] ?? 'The driver assignment could not be saved.');
        }
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('add_bus failed: ' . $e->getMessage());
    add_bus_fail(500, 'Unable to add the bus. Please try again.');
}

// Seat layout is created after the transaction (it owns its own transaction).
try {
    (new Bus($db))->createSeatsForBus($busId);
} catch (Throwable $e) {
    error_log('add_bus seat creation failed: ' . $e->getMessage());
}

try {
    require_once __DIR__ . '/../../finance/models/AuditTrailModel.php';
    $audit = new \Finance\Models\AuditTrailModel();
    $audit->log(
        'Bus',
        'Add',
        'Registered bus ' . $busId . ' (' . $plate . ')',
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null,
        $busId,
        null,
        [
            'bus_id' => $busId,
            'plate_number' => $plate,
            'bus_type' => $busType,
            'status' => $status,
        ],
        'Success'
    );
} catch (Throwable $e) {
    error_log('add_bus audit failed: ' . $e->getMessage());
}

echo json_encode([
    'status' => 'success',
    'success' => true,
    'bus_id' => $busId,
    'assigned_user_id' => $busId,
    'qr' => [
        'token' => $credential['token'] ?? '',
        'payload_url' => $credential['payload_url'] ?? '',
        'status' => $credential['status'] ?? 'active',
        'created_at' => $credential['created_at'] ?? '',
    ],
    'message' => 'Bus ' . $busId . ' registered successfully with its QR code.',
]);
