<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

if (!headers_sent()) {
    header('Content-Type: application/json');
}

Session::start();

if (!Auth::check()) {
    if (!headers_sent()) {
        http_response_code(401);
    }
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

/**
 * Normalized log classification used for both the returned `type` column and
 * the status filter. Precedence: error -> warning -> success -> info.
 * Mirrors the client fallback classifier in admin/views/system/layout/scripts.php.
 */
function systemLogTypeExpression(): string
{
    return "
        CASE
            WHEN LOWER(COALESCE(status, '')) IN ('failed', 'error')
                OR LOWER(COALESCE(description, '')) LIKE '%error%'
                OR LOWER(COALESCE(description, '')) LIKE '%failed%'
                THEN 'error'
            WHEN LOWER(COALESCE(status, '')) = 'warning'
                OR LOWER(COALESCE(description, '')) LIKE '%warning%'
                OR LOWER(COALESCE(description, '')) LIKE '%advisory%'
                OR LOWER(COALESCE(description, '')) LIKE '%variance%'
                OR LOWER(COALESCE(description, '')) LIKE '%discrepancy%'
                THEN 'warning'
            WHEN LOWER(COALESCE(status, '')) = 'success'
                OR LOWER(COALESCE(description, '')) LIKE '%success%'
                OR LOWER(COALESCE(description, '')) LIKE '%confirmed%'
                OR LOWER(COALESCE(description, '')) LIKE '%dispatched%'
                OR LOWER(COALESCE(description, '')) LIKE '%completed%'
                THEN 'success'
            ELSE 'info'
        END
    ";
}

try {
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $module = isset($_GET['module']) ? trim((string)$_GET['module']) : '';
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $userParam = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
    $dateRange = isset($_GET['date']) ? trim((string)$_GET['date']) : 'all';
    $limit = isset($_GET['limit']) ? max(10, min(200, (int)$_GET['limit'])) : 50;

    $db = Database::get();
    $typeExpr = systemLogTypeExpression();

    $grandTotal = (int)$db->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();

    // Build filtered query
    $conditions = [];
    $params = [];

    if ($search !== '') {
        $conditions[] = "(username LIKE :s1 OR description LIKE :s2 OR action LIKE :s3 OR module LIKE :s4 OR record_id LIKE :s5 OR ip_address LIKE :s6)";
        $term = '%' . $search . '%';
        $params[':s1'] = $term;
        $params[':s2'] = $term;
        $params[':s3'] = $term;
        $params[':s4'] = $term;
        $params[':s5'] = $term;
        $params[':s6'] = $term;
    }

    if ($module !== '') {
        $conditions[] = "module = :mod";
        $params[':mod'] = $module;
    }

    if ($userParam !== '') {
        $conditions[] = "username = :usr";
        $params[':usr'] = $userParam;
    }

    if ($status !== '') {
        $statusLower = strtolower($status);
        if ($statusLower === 'error' || $statusLower === 'failed') {
            $conditions[] = "($typeExpr) = 'error'";
        } elseif ($statusLower === 'success') {
            $conditions[] = "($typeExpr) = 'success'";
        } elseif ($statusLower === 'warning') {
            $conditions[] = "($typeExpr) = 'warning'";
        } elseif ($statusLower === 'monitor' || $statusLower === 'info') {
            $conditions[] = "($typeExpr) = 'info'";
        }
    }

    if ($dateRange === 'today') {
        $conditions[] = "created_at >= CURDATE()";
    } elseif ($dateRange === 'yesterday') {
        $conditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()";
    } elseif ($dateRange === '7days') {
        $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateRange === '30days') {
        $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Count matching rows
    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_trail $where");
    $countStmt->execute($params);
    $totalMatching = (int)$countStmt->fetchColumn();

    // Fetch matching rows with the authoritative normalized type
    $fetchStmt = $db->prepare("SELECT *, ($typeExpr) AS type FROM audit_trail $where ORDER BY created_at DESC, id DESC LIMIT :limit");
    foreach ($params as $k => $v) {
        $fetchStmt->bindValue($k, $v);
    }
    $fetchStmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $fetchStmt->execute();
    $data = $fetchStmt->fetchAll(\PDO::FETCH_ASSOC);

    // Dynamic distinct users & modules from database
    $usersList = $db->query("
        SELECT DISTINCT username 
        FROM audit_trail 
        WHERE username IS NOT NULL AND username != '' 
        ORDER BY username ASC
    ")->fetchAll(\PDO::FETCH_COLUMN);

    $modulesList = $db->query("
        SELECT DISTINCT module 
        FROM audit_trail 
        WHERE module IS NOT NULL AND module != '' 
        ORDER BY module ASC
    ")->fetchAll(\PDO::FETCH_COLUMN);

    echo json_encode([
        'success'      => true,
        'data'         => $data,
        'total'        => $totalMatching,
        'grand_total'  => $grandTotal,
        'users_list'   => $usersList,
        'modules_list' => $modulesList
    ]);
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
