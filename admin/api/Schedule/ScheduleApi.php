<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Auth;
use App\Core\Database;
use Throwable;
use PDO;

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$db = Database::get();
$action = $_GET['action'] ?? '';

try {

    switch ($action) {

        // ==========================================================
        // GET ALL BUS SCHEDULES
        // ==========================================================

        case 'get_schedules':

            $stmt = $db->query("
                SELECT
                    b.bus_id,
                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,

                    b.from_terminal_id,
                    b.to_terminal_id,

                    ft.terminal_name AS from_terminal,
                    tt.terminal_name AS to_terminal,

                    d.driver_id,
                    d.full_name,
                    d.contact_number,
                    d.license_number

                FROM buses b

                LEFT JOIN terminals ft
                    ON ft.id = b.from_terminal_id

                LEFT JOIN terminals tt
                    ON tt.id = b.to_terminal_id

                LEFT JOIN driver_bus_assignment dba
                    ON dba.bus_id = b.bus_id

                LEFT JOIN drivers d
                    ON d.driver_id = dba.driver_id

                WHERE b.status <> 'Archived'

                ORDER BY b.bus_id ASC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;

        // ==========================================================
        // GET ONE BUS SCHEDULE
        // ==========================================================

        case 'get_schedule':

            $busId = $_GET['bus_id'] ?? '';

            $stmt = $db->prepare("
                SELECT
                    b.bus_id,
                    b.plate_number,
                    b.bus_type,
                    b.schedule,
                    b.route_name,
                    b.status,

                    b.from_terminal_id,
                    b.to_terminal_id,

                    ft.terminal_name AS from_terminal,
                    tt.terminal_name AS to_terminal,

                    d.driver_id,
                    d.full_name

                FROM buses b

                LEFT JOIN terminals ft
                    ON ft.id = b.from_terminal_id

                LEFT JOIN terminals tt
                    ON tt.id = b.to_terminal_id

                LEFT JOIN driver_bus_assignment dba
                    ON dba.bus_id = b.bus_id

                LEFT JOIN drivers d
                    ON d.driver_id = dba.driver_id

                WHERE b.bus_id = ?
            ");

            $stmt->execute([$busId]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetch(PDO::FETCH_ASSOC)
            ]);

            break;

        // ==========================================================
        // UPDATE SCHEDULE
        // ==========================================================

        case 'update_schedule':

            $busId = $_POST['bus_id'] ?? '';

            $schedule = $_POST['schedule'] ?? '';

            $driverId = $_POST['driver_id'] ?? '';

            $fromTerminal = (int)($_POST['from_terminal_id'] ?? 0);
            $toTerminal = (int)($_POST['to_terminal_id'] ?? 0);

            if (!$busId || !$schedule || !$driverId || !$fromTerminal || !$toTerminal) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields.'
                ]);
                break;
            }

            // Get terminal names
            $stmt = $db->prepare("
                SELECT id, terminal_name
                FROM terminals
                WHERE id IN (?, ?)
            ");

            $stmt->execute([$fromTerminal, $toTerminal]);

            $terminals = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $terminals[$row['id']] = $row['terminal_name'];
            }

            $routeName =
                ($terminals[$fromTerminal] ?? 'Unknown')
                . ' - ' .
                ($terminals[$toTerminal] ?? 'Unknown');

            // Update bus
            $stmt = $db->prepare("
                UPDATE buses
                SET
                    schedule = ?,
                    from_terminal_id = ?,
                    to_terminal_id = ?,
                    route_name = ?
                WHERE bus_id = ?
            ");

            $stmt->execute([
                $schedule,
                $fromTerminal,
                $toTerminal,
                $routeName,
                $busId
            ]);

            // Update driver assignment
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM driver_bus_assignment
                WHERE bus_id = ?
            ");

            $stmt->execute([$busId]);

            if ($stmt->fetchColumn()) {

                $stmt = $db->prepare("
                    UPDATE driver_bus_assignment
                    SET driver_id = ?
                    WHERE bus_id = ?
                ");

                $stmt->execute([
                    $driverId,
                    $busId
                ]);

            } else {

                $stmt = $db->prepare("
                    INSERT INTO driver_bus_assignment
                    (
                        driver_id,
                        bus_id
                    )
                    VALUES
                    (
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $driverId,
                    $busId
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully.'
            ]);

            break;

        // ==========================================================
        // DROPDOWNS
        // ==========================================================

        case 'get_buses':

            $stmt = $db->query("
                SELECT
                    bus_id,
                    plate_number
                FROM buses
                WHERE status='Active'
                ORDER BY bus_id
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;

        case 'get_drivers':

            $stmt = $db->query("
                SELECT
                    driver_id,
                    full_name
                FROM drivers
                WHERE status='Active'
                ORDER BY full_name
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;

        case 'get_terminals':

            $stmt = $db->query("
                SELECT
                    id,
                    terminal_name
                FROM terminals
                WHERE status='Active'
                ORDER BY terminal_name
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;

        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);

            break;
    }

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
