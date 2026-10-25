<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Auth;
use App\Core\Database;
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

try {

    switch ($action) {

        case 'getStats':

            // Non-archived passengers match the default table view, so the
            // summary counts and the clickable filters always agree.
            $totalPassengers = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE status IS NULL OR status != 'Archived'")->fetchColumn();

            $newToday = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE DATE(created_at) = CURDATE()")->fetchColumn();

            $tripsToday = (int)$db->query("SELECT COUNT(DISTINCT trip_no) FROM passenger_trips WHERE DATE(boarding_time) = CURDATE()")->fetchColumn();

            $boardedToday = (int)$db->query("
                SELECT COUNT(DISTINCT pt.passenger_id)
                FROM passenger_trips pt
                JOIN passengers p ON p.id = pt.passenger_id
                WHERE DATE(pt.boarding_time) = CURDATE()
                  AND (p.status IS NULL OR p.status != 'Archived')
            ")->fetchColumn();

            $active = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE status = 'Active' OR status IS NULL OR status = ''")->fetchColumn();

            $pending = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE status = 'Pending'")->fetchColumn();

            // Matches the list's Verification filter, which reads discount_status.
            $pendingVerification = (int)$db->query("
                SELECT COUNT(*)
                FROM passengers
                WHERE (status IS NULL OR status != 'Archived')
                  AND LOWER(COALESCE(discount_status, 'pending')) IN ('pending', '')
            ")->fetchColumn();

            $tripRecords = (int)$db->query("SELECT COUNT(*) FROM passenger_trip_history")->fetchColumn();

            $blocked = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE status = 'Blocked'")->fetchColumn();

            $archived = (int)$db->query("SELECT COUNT(*) FROM passengers WHERE status = 'Archived'")->fetchColumn();

            // Verified passengers match the list's Verification filter ('verified').
            $verified = (int)$db->query("
                SELECT COUNT(*)
                FROM passengers
                WHERE (status IS NULL OR status != 'Archived')
                  AND LOWER(COALESCE(discount_status, '')) = 'verified'
            ")->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_passengers' => $totalPassengers,
                    'new_today' => $newToday,
                    'trips_today' => $tripsToday,
                    'boarded_today' => $boardedToday,
                    'active' => $active,
                    'pending' => $pending,
                    'pending_verification' => $pendingVerification,
                    'trip_records' => $tripRecords,
                    'blocked' => $blocked,
                    'archived' => $archived,
                    'verified' => $verified
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
