<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/passenger/services/PassengerCodeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerAccountService.php';

use App\Auth\Auth;
use App\Core\Database;
use Finance\Models\AuditTrailModel;
use Throwable;
use PDO;

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::get();
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$action = $_GET['action'] ?? '';
if (in_array($action, ['update_status', 'update_passenger', 'upload_discount_doc', 'verify_discount', 'update_discount', 'review_appeal'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']); exit; }
    $token = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['passenger_management_csrf']) || !hash_equals($_SESSION['passenger_management_csrf'], $token)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security check failed. Refresh Passenger Management.']); exit; }
}
function passenger_management_id($value): int {
    if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Invalid passenger account.');
    if (!preg_match('/^P?([1-9][0-9]*)$/', (string)$value, $match)) throw new InvalidArgumentException('Only a live passenger account can be changed.');
    return (int)$match[1];
}

try {

    PassengerAccountSchema::ensure($db);

    switch ($action) {

        case 'get_passengers':

            require_once __DIR__ . '/PassengerQuery.php';

            // Passenger codes: guarantee uniqueness once, then backfill a small
            // batch of empty codes (existing codes are never touched).
            try {
                PassengerCodeService::ensureUniqueIndex($db);
                PassengerCodeService::backfillMissing($db, 100);
            } catch (Throwable $e) {
                error_log('PassengerApi code backfill skipped: ' . $e->getMessage());
            }

            $filters = passenger_query_normalize_filters($_GET);
            $sortBy = $_GET['sort_by'] ?? 'p.id';
            $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
            $allowedSort = ['p.id','p.full_name','p.discount_type','p.discount_status','p.status','p.created_at','p.passenger_code','trip_count','last_trip'];
            if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'p.id';
            if (!in_array($sortDir, ['ASC','DESC'], true)) $sortDir = 'DESC';

            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 15);
            if (!in_array($perPage, [15, 25, 50, 100], true)) $perPage = 15;

            $total = 0;
            $allRows = passenger_query_run($db, $filters, $sortBy, $sortDir, $page, $perPage, $total);
            $pages = max(1, (int)ceil($total / $perPage));
            if ($page > $pages) {
                $page = $pages;
                $allRows = passenger_query_run($db, $filters, $sortBy, $sortDir, $page, $perPage, $total);
            }

            echo json_encode([
                'success' => true,
                'data' => $allRows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages
            ]);
            break;

        case 'get_passenger_detail':

            $id = ltrim($_GET['id'] ?? '', 'PHIST-');
            if (!is_numeric($id)) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); break; }
            $id = (int)$id;

            $stmt = $db->prepare("
                SELECT p.*,
                       COUNT(pt.id) AS trip_count,
                       SUM(pt.fare) AS total_fare,
                       MAX(pt.boarding_time) AS last_trip
                FROM passengers p
                LEFT JOIN passenger_trips pt ON pt.passenger_id = p.id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

            $trips = $db->prepare("SELECT pt.*, d.full_name AS driver_name FROM passenger_trips pt LEFT JOIN drivers d ON pt.driver_id = d.driver_id WHERE pt.passenger_id = ? ORDER BY pt.boarding_time DESC LIMIT 20");
            $trips->execute([$id]);

            echo json_encode([
                'success' => true,
                'data' => $passenger,
                'trips' => $trips->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'get_passenger_trips':

            $rawId = $_GET['id'] ?? '';
            if (!$rawId) { echo json_encode(['success'=>false,'message'=>'Invalid ID.','data'=>[]]); break; }

            /* Cap the fetch so a passenger with months of demo rides cannot
               stall the modal; the client paginates what is returned. */
            $limit = (int)($_GET['limit'] ?? 300);
            if ($limit < 1) { $limit = 300; }
            if ($limit > 1000) { $limit = 1000; }

            if (substr($rawId, 0, 5) === 'HIST-') {
                /* Imported passenger — look up in passenger_trip_history */
                $passengerName = substr($rawId, 5);
                $countStmt = $db->prepare("SELECT COUNT(*) FROM passenger_trip_history WHERE passenger_name = ?");
                $countStmt->execute([$passengerName]);
                $total = (int)$countStmt->fetchColumn();
                $stmt = $db->prepare("
                    SELECT
                        pth.trip_id AS trip_no,
                        pth.drivers_full_name AS driver_name,
                        pth.fare,
                        pth.payment_method,
                        pth.payment_status,
                        pth.remittance_status,
                        pth.trip_date AS boarding_time,
                        pth.trip_date AS dropoff_time,
                        pth.boarding_point,
                        pth.destination
                    FROM passenger_trip_history pth
                    WHERE pth.passenger_name = ?
                    ORDER BY pth.trip_date DESC
                    LIMIT $limit
                ");
                $stmt->execute([$passengerName]);
            } else {
                /* Live passenger — look up in passenger_trips */
                $id = ltrim($rawId, 'PHIST-');
                $id = (int)$id;
                if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.','data'=>[]]); break; }
                $countStmt = $db->prepare("SELECT COUNT(*) FROM passenger_trips WHERE passenger_id = ?");
                $countStmt->execute([$id]);
                $total = (int)$countStmt->fetchColumn();
                $stmt = $db->prepare("
                    SELECT pt.*, d.full_name AS driver_name
                    FROM passenger_trips pt
                    LEFT JOIN drivers d ON pt.driver_id = d.driver_id
                    WHERE pt.passenger_id = ?
                    ORDER BY pt.boarding_time DESC
                    LIMIT $limit
                ");
                $stmt->execute([$id]);
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => $total,
                'shown' => count($rows),
                'limit' => $limit,
            ]);
            break;

        case 'update_status':

            $rawId = $_POST['id'] ?? '';
            if (str_starts_with($rawId, 'HIST-')) {
                echo json_encode(['success'=>false,'message'=>'Cannot update status: this is a history record. Edit the live passenger record instead.']);
                break;
            }
            $id = passenger_management_id($rawId);
            $status = ucfirst(strtolower((string)($_POST['status'] ?? '')));
            $before = PassengerAccountService::state($db, $id);
            $operation = (string)($_POST['operation'] ?? '');
            if ($operation === '') $operation = match ($status) {
                'Suspended' => 'suspend', 'Blocked', 'Archived' => 'block',
                'Active' => $before['effective_account_status'] === 'BLOCKED' ? 'unblock' : 'reactivate',
                'Pending' => 'pending',
                default => throw new InvalidArgumentException('Choose Active, Suspended or Blocked.'),
            };
            $db->beginTransaction();
            if ($operation === 'pending') {
                // Legacy review state retained for existing workflows; it is
                // not a restriction and never grants a verified classification.
                $db->prepare("UPDATE passengers SET status = 'Pending', account_status = 'ACTIVE', suspension_starts_at = NULL, suspension_ends_at = NULL, status_reason = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ?")
                    ->execute([trim((string)($_POST['reason'] ?? '')) ?: 'Account under review', Auth::userId(), $id]);
            } else {
                PassengerAccountService::change($db, $id, $operation, $_POST, Auth::user());
                if ($status === 'Archived') $db->prepare("UPDATE passengers SET status = 'Archived' WHERE id = ?")->execute([$id]);
            }
            $db->commit();

            $audit = new AuditTrailModel();
            $audit->log('Passenger', 'Update', 'Updated passenger #' . $id . ' status to ' . $status,
                Auth::userId(), Auth::username(), Auth::role()
            );

            try {
                PassengerCodeService::assignIfMissing($db, $id, null);
            } catch (Throwable $e) {
            }

            echo json_encode(['success' => true, 'message' => 'Status updated to ' . $status . '.']);
            break;

        case 'update_passenger':

            $rawId = $_POST['id'] ?? '';
            $id = passenger_management_id($rawId);
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); break; }

            $adminId = Auth::user()['id'] ?? 0;

            $discountType = $_POST['discount_type'] ?? '';
            $discountStatus = $_POST['discount_status'] ?? 'Pending';
            $discountPct = !empty($_POST['discount_percent']) ? (int)$_POST['discount_percent'] : null;
            $voucherDesc = $_POST['voucher_description'] ?? '';

            if (preg_match('/Custom\s*-\s*(\d+)/i', $discountType, $m)) {
                $discountPct = (int)$m[1];
                $discountType = 'Custom';
            }

            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Enter valid contact details.');
            if ($discountPct !== null && ($discountPct < 0 || $discountPct > 100)) throw new InvalidArgumentException('Invalid discount percentage.');
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id FROM passengers WHERE id = ? FOR UPDATE'); $lock->execute([$id]);
            if (!$lock->fetchColumn()) throw new DomainException('Passenger not found.');
            PassengerAccountService::claimIdentity($db, $id, $email, $phone);
            $code = PassengerAccountService::classification($db, $id, $discountType, $discountStatus, Auth::user());
            $db->prepare('UPDATE passengers SET full_name = ?, email = ?, phone = ?, discount_percent = ?, voucher_description = ? WHERE id = ?')->execute([$name, $email, $phone, $discountPct, $voucherDesc, $id]);
            $db->commit();

            $audit = new AuditTrailModel();
            $audit->log('Passenger', 'Update', 'Updated passenger #' . $id . ' details',
                Auth::userId(), Auth::username(), Auth::role()
            );

            try {
                PassengerCodeService::assignIfMissing($db, $id, $discountType);
            } catch (Throwable $e) {
            }

            echo json_encode(['success' => true, 'message' => 'Passenger updated.']);
            break;

        case 'upload_discount_doc':

            $rawId = $_POST['id'] ?? '';
            if (!is_numeric($rawId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid passenger ID.']);
                break;
            }
            $id = (int)$rawId;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid passenger ID.']);
                break;
            }

            if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No file received.']);
                break;
            }

            $maxBytes = 3 * 1024 * 1024;
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf'];
            if ($_FILES['document']['size'] > $maxBytes) {
                echo json_encode(['success' => false, 'message' => 'File must be 3MB or smaller.']);
                break;
            }
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Only image (PNG, JPG, GIF, WEBP) and PDF files are allowed.']);
                break;
            }

            $dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/discount_ids/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $fileName = uniqid('discount_') . '.' . $ext;
            if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $fileName)) {
                echo json_encode(['success' => false, 'message' => 'Could not save the file.']);
                break;
            }

            $path = '/uploads/discount_ids/' . $fileName;

            $stmt = $db->prepare("UPDATE passengers SET discount_id = ? WHERE id = ?");
            $stmt->execute([$path, $id]);

            $audit = new AuditTrailModel();
            $audit->log('Passenger', 'Update', 'Uploaded discount document for passenger #' . $id,
                Auth::userId(), Auth::username(), Auth::role());

            echo json_encode(['success' => true, 'message' => 'Document uploaded.', 'path' => $path]);
            break;

        case 'get_discounts':

            $stmt = $db->query("
                SELECT p.id, p.full_name, p.email, p.discount_type, p.discount_status, p.verified_at, p.status
                FROM passengers p
                WHERE p.discount_type IS NOT NULL AND p.discount_type != '' AND LOWER(p.discount_type) != 'regular'
                ORDER BY p.id DESC
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'verify_discount':
            $id = passenger_management_id($_POST['id'] ?? '');
            $discountStatus = $_POST['discount_status'] ?? 'Verified';
            $adminId = Auth::user()['id'] ?? 0;
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid passenger ID.']); break; }
            $allowedStatus = ['Verified','Pending','Rejected','Expired','Needs_Review'];
            if (PassengerAccountService::normalizeVerification($discountStatus) === null) throw new InvalidArgumentException('Invalid verification status.');
            $db->beginTransaction();
            $current = $db->prepare('SELECT discount_type FROM passengers WHERE id = ? FOR UPDATE'); $current->execute([$id]);
            PassengerAccountService::classification($db, $id, (string)$current->fetchColumn(), $discountStatus, Auth::user());
            $db->commit();

            $audit = new AuditTrailModel();
            $audit->log('Passenger', 'Update', (ucfirst($discountStatus) . ' discount for passenger #' . $id),
                Auth::userId(), Auth::username(), Auth::role()
            );

            echo json_encode(['success'=>true,'message'=>'Discount '.strtolower($discountStatus).'.']);
            break;

        case 'update_discount':
            $id = passenger_management_id($_POST['id'] ?? '');
            $discountStatus = $_POST['discount_status'] ?? 'Pending';
            $adminId = Auth::user()['id'] ?? 0;
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid passenger ID.']); break; }

            $discountType = $_POST['discount_type'] ?? '';
            $discountPct = !empty($_POST['discount_percent']) ? (int)$_POST['discount_percent'] : null;
            $voucherDesc = $_POST['voucher_description'] ?? '';

            // Parse custom percentage
            if (preg_match('/Custom\s*-\s*(\d+)/i', $discountType, $m)) {
                $discountPct = (int)$m[1];
                $discountType = 'Custom';
            }

            if ($discountPct !== null && ($discountPct < 0 || $discountPct > 100)) throw new InvalidArgumentException('Invalid discount percentage.');
            $db->beginTransaction();
            PassengerAccountService::classification($db, $id, $discountType, $discountStatus, Auth::user());
            $db->prepare('UPDATE passengers SET discount_percent = ?, voucher_description = ? WHERE id = ?')->execute([$discountPct, $voucherDesc, $id]);
            $db->commit();

            $audit = new AuditTrailModel();
            $audit->log('Passenger', 'Update', 'Updated discount for passenger #' . $id,
                Auth::userId(), Auth::username(), Auth::role()
            );

            echo json_encode(['success'=>true,'message'=>'Discount updated.']);
            break;

        case 'restriction_history':
            $id = passenger_management_id($_GET['id'] ?? '');
            $appeals = $db->prepare('SELECT * FROM passenger_account_appeals WHERE passenger_id = ? ORDER BY id DESC LIMIT 100'); $appeals->execute([$id]);
            echo json_encode(['success'=>true, 'history'=>PassengerAccountService::history($db, $id), 'appeals'=>$appeals->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'review_appeal':
            $appealId = (int)($_POST['appeal_id'] ?? 0);
            $note = trim((string)($_POST['review_note'] ?? ''));
            if (!$appealId || $note === '' || mb_strlen($note) > 2000) throw new InvalidArgumentException('Provide an appeal review response.');
            $db->prepare("UPDATE passenger_account_appeals SET status = 'REVIEWED', review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'PENDING'")->execute([$note, Auth::userId(), $appealId]);
            echo json_encode(['success'=>true, 'message'=>'Appeal reviewed. Account restrictions remain until explicitly reactivated or unblocked.']);
            break;
        case 'export_passengers':

            require_once __DIR__ . '/PassengerQuery.php';

            $filters = passenger_query_normalize_filters($_GET);
            $sortBy = $_GET['sort_by'] ?? 'p.id';
            $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
            $allowedSort = ['p.id','p.full_name','p.discount_type','p.discount_status','p.status','p.created_at','trip_count'];
            if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'p.id';
            if (!in_array($sortDir, ['ASC','DESC'], true)) $sortDir = 'DESC';

            // Same fetch/filter/sort pipeline as get_passengers, so the CSV
            // always matches what the filtered table shows.
            $rows = passenger_query_run($db, $filters, $sortBy, $sortDir);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="passengers_export.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, passenger_query_export_header());
            foreach ($rows as $r) {
                fputcsv($out, passenger_query_export_row($r));
            }
            fclose($out);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code($e instanceof DomainException || $e instanceof InvalidArgumentException ? 422 : 500);
    error_log('Passenger Management: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e instanceof DomainException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to update the passenger account. Please retry.']);
}
