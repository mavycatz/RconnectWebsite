<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {

    $db = Database::get();

    $hasProblems = false;

    try {
        $hasProblems = (bool)$db->query("SHOW TABLES LIKE 'bus_problem_reports'")->fetchColumn();
    } catch(Throwable $e){
        $hasProblems = false;
    }

    $problemSelect = '';
    $problemJoin = '';

    if ($hasProblems) {

        $problemSelect = ",
            op.report_id AS open_problem_id,
            op.problem_type AS open_problem_type,
            op.report_status AS open_problem_status";

        $problemJoin = "
        LEFT JOIN (
            SELECT
                bpr.id AS report_id,
                bpr.bus_id,
                bpr.problem_type,
                bpr.status AS report_status
            FROM bus_problem_reports bpr
            INNER JOIN (
                SELECT
                    bus_id,
                    MAX(id) AS latest_id
                FROM bus_problem_reports
                WHERE status IN ('waiting', 'acknowledged')
                GROUP BY bus_id
            ) latest
                ON latest.latest_id = bpr.id
        ) op
            ON op.bus_id = b.bus_id";

    }

    $stmt = $db->query("
        SELECT
            b.bus_id,
            b.plate_number,
            b.bus_type,
            b.seats,
            b.standing_capacity,
            b.accessible_features,
            b.driver,
            b.status,
            b.qr_code,
            b.qr_data,
            b.qr_status,

            ft.terminal_name AS from_terminal,
            tt.terminal_name AS to_terminal
            {$problemSelect}

        FROM buses b

        LEFT JOIN terminals ft
            ON ft.id = b.from_terminal_id

        LEFT JOIN terminals tt
            ON tt.id = b.to_terminal_id
        {$problemJoin}

        ORDER BY b.bus_id ASC
    ");

    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch(Throwable $e){

    http_response_code(500);

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);

}