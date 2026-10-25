<?php declare(strict_types=1);

/**
 * Baggage Management API: list / detail / add / edit over the existing cargo
 * storage. Admin/super_admin only; add and edit are audited.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/Passenger/BaggageModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Passenger/BaggageSchema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Passenger/BaggagePricingService.php';

use Admin\Models\Passenger\BaggageModel;
use Admin\Services\Passenger\BaggagePricingService;
use Admin\Services\Passenger\BaggageSchema;
use App\Auth\Auth;
use App\Core\Database;
use Finance\Models\AuditTrailModel;

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::get();
BaggageSchema::ensureColumns($db);

$action = $_GET['action'] ?? 'list';
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST ?: $_GET;
}

function baggage_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function baggage_audit(string $action, string $description, array $extra = [], ?string $recordId = null, string $status = 'Success'): void
{
    try {
        $audit = new AuditTrailModel();
        $audit->log(
            'Passenger',
            $action,
            $description,
            Auth::userId(),
            Auth::username(),
            Auth::role(),
            $recordId,
            null,
            $extra,
            $status
        );
    } catch (Throwable $ignore) {
    }
}

try {
    $model = new BaggageModel();

    switch ($action) {
        case 'list':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 15);
            if (!in_array($perPage, [15, 25, 50, 100], true)) {
                $perPage = 15;
            }

            $filters = [
                'search' => $_GET['search'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'trip' => $_GET['trip'] ?? '',
                'category' => $_GET['category'] ?? '',
                'payment_status' => $_GET['payment_status'] ?? '',
            ];

            $result = $model->query($filters, $page, $perPage);
            $total = (int)$result['total'];
            $pages = max(1, (int)ceil($total / $perPage));
            $page = min($page, $pages);

            echo json_encode([
                'success' => true,
                'data' => $result['rows'],
                'errors' => $result['errors'],
                'categories' => BaggageSchema::CATEGORIES,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
            ]);
            break;

        case 'detail':
            $source = ($_GET['source'] ?? 'live') === 'history' ? 'history' : 'live';
            $ref = (int)($_GET['ref'] ?? 0);
            if ($ref <= 0) {
                baggage_fail('Invalid baggage reference.');
            }
            $row = $model->find($source, $ref);
            if ($row === null) {
                baggage_fail('Baggage record not found.', 404);
            }
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        case 'add':
            $description = trim((string)($input['description'] ?? ''));
            $spaces = (int)($input['spaces'] ?? 1);
            $routeName = trim((string)($input['route_name'] ?? ''));
            $boarding = trim((string)($input['boarding_point'] ?? ''));
            $destination = trim((string)($input['destination'] ?? ''));
            $tripNo = trim((string)($input['trip_no'] ?? ''));
            $busNo = trim((string)($input['bus_no'] ?? ''));
            $driverId = trim((string)($input['driver_id'] ?? ''));
            $category = BaggageSchema::normalizeCategory($input['category'] ?? null);

            if ($description === '') {
                baggage_fail('Item description is required.');
            }
            if ($spaces < 1 || $spaces > 50) {
                baggage_fail('Quantity (occupied spaces) must be between 1 and 50.');
            }
            if ($routeName === '' || $boarding === '' || $destination === '') {
                baggage_fail('Route, boarding point, and destination are required.');
            }

            $pricing = new BaggagePricingService();
            $fare = $pricing->fareForRoute($db, $routeName, $boarding, $destination, $spaces);
            if ($fare === null) {
                baggage_fail('The fare could not be determined from the route stops; nothing was added.');
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO passengers (full_name, type, space_occupied, baggage_category) VALUES (?, 'other', ?, ?)");
                $stmt->execute([$description, $spaces, $category]);
                $passengerId = (int)$db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO passenger_trips
                        (passenger_id, bus_no, trip_no, driver_id, boarding_point, destination, fare, payment_method, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Other', 'Other')
                ");
                $stmt->execute([$passengerId, $busNo, $tripNo, $driverId, $boarding, $destination, $fare]);
                $ref = (int)$db->lastInsertId();

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            baggage_audit(
                'Baggage Add',
                'Added baggage #' . $ref . ': ' . $description,
                ['trip_no' => $tripNo, 'spaces' => $spaces, 'fare' => $fare, 'category' => $category],
                (string)$ref
            );

            echo json_encode(['success' => true, 'data' => $model->find('live', $ref)]);
            break;

        case 'edit':
            $source = ($input['source'] ?? 'live') === 'history' ? 'history' : 'live';
            $ref = (int)($input['ref'] ?? 0);
            if ($source === 'history') {
                baggage_fail('Archived baggage records cannot be edited.');
            }
            if ($ref <= 0) {
                baggage_fail('Invalid baggage reference.');
            }

            $existing = $model->find('live', $ref);
            if ($existing === null) {
                baggage_fail('Baggage record not found.', 404);
            }

            $description = trim((string)($input['description'] ?? $existing['description']));
            $spaces = (int)($input['spaces'] ?? $existing['spaces']);
            $routeName = trim((string)($input['route_name'] ?? ''));
            $boarding = trim((string)($input['boarding_point'] ?? $existing['boarding_point']));
            $destination = trim((string)($input['destination'] ?? $existing['destination']));
            $category = BaggageSchema::normalizeCategory($input['category'] ?? $existing['category']);

            if ($description === '') {
                baggage_fail('Item description is required.');
            }
            if ($spaces < 1 || $spaces > 50) {
                baggage_fail('Quantity (occupied spaces) must be between 1 and 50.');
            }

            $fare = $existing['fare'];
            if ($routeName !== '') {
                $pricing = new BaggagePricingService();
                $recomputed = $pricing->fareForRoute($db, $routeName, $boarding, $destination, $spaces);
                if ($recomputed === null) {
                    baggage_fail('The fare could not be determined from the route stops; nothing was changed.');
                }
                $fare = $recomputed;
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE passengers SET full_name = ?, space_occupied = ?, baggage_category = ? WHERE id = ? AND LOWER(COALESCE(type,'')) = 'other'");
                $stmt->execute([$description, $spaces, $category, (int)$existing['passenger_id']]);

                $stmt = $db->prepare("UPDATE passenger_trips SET boarding_point = ?, destination = ?, fare = ? WHERE id = ?");
                $stmt->execute([$boarding, $destination, $fare, $ref]);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            baggage_audit(
                'Baggage Edit',
                'Edited baggage #' . $ref . ': ' . $description,
                ['spaces' => $spaces, 'fare' => $fare, 'category' => $category],
                (string)$ref
            );

            echo json_encode(['success' => true, 'data' => $model->find('live', $ref)]);
            break;

        default:
            baggage_fail('Invalid action.');
    }
} catch (Throwable $e) {
    error_log('BaggageApi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The baggage request could not be completed.']);
}
