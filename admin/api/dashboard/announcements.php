<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/Controllers/Announcement/AnnouncementController.php';

use App\Core\Database;
use Admin\Controllers\Announcement\AnnouncementController;

header('Content-Type: application/json');

try {

    $db = Database::get();

    $fromDate = $_GET['fromDate'] ?? '';
    $toDate = $_GET['toDate'] ?? '';

    /* Period params from the dashboard filters: day/month/year. Translate
       them into an explicit date range so announcements respect the D/M/Y
       KPI toggle, the sticky filter, and the default "This Year" view. */
    $day = (int)($_GET['day'] ?? 0);
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);

    if ($day > 0 && $month > 0 && $year > 0){
        $fromDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $toDate = $fromDate;
    } elseif ($month > 0 && $year > 0){
        $fromDate = sprintf('%04d-%02d-01', $year, $month);
        $toDate = date('Y-m-t', strtotime($fromDate));
    } elseif ($year > 0){
        $fromDate = $year . '-01-01';
        $toDate = $year . '-12-31';
    }

    $params = [];
    $whereExtra = '';
    if ($fromDate) { $whereExtra .= ' AND DATE(created_at) >= ?'; $params[] = $fromDate; }
    if ($toDate)   { $whereExtra .= ' AND DATE(created_at) <= ?'; $params[] = $toDate; }

    $sql = "
    SELECT
        title,
        message,
        DATE(created_at) AS date,
        TIME(created_at) AS time,
        reported_by,
        visibility,
        target_roles
    FROM announcements
    WHERE status='Active' $whereExtra
    ORDER BY created_at DESC
    LIMIT 10
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Audience enforcement: Public Updates reach everyone, Team posts only
       the resolved role, and unidentified callers receive public only. */
    $user = \App\Auth\Auth::user() ?? [];
    $visible = AnnouncementController::filterVisibleForUser(
        $rows,
        (string)($user['role'] ?? ''),
        (string)($user['username'] ?? '')
    );

    $data = array_map(static fn (array $row): array => [
        'title'   => $row['title'],
        'message' => $row['message'],
        'date'    => $row['date'],
        'time'    => $row['time'],
    ], $visible);

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);

} catch(Throwable $e){

    echo json_encode([
        "success" => false
    ]);

}
