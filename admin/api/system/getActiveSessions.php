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

$currentUser = Auth::user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentUsername = (string)($currentUser['username'] ?? '');

function systemSafeSubstr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return (string)mb_substr($value, $start, $length);
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function systemSafeStrlen(string $value): int
{
    return function_exists('mb_strlen') ? (int)mb_strlen($value) : strlen($value);
}

function formatUserRole(string $role): string {
    $r = strtolower(trim($role));
    return match ($r) {
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'dispatcher'  => 'Dispatcher',
        'inspector'   => 'Inspector',
        'finance'     => 'Finance',
        'driver'      => 'Driver',
        'passenger'   => 'Passenger',
        default       => ucwords(str_replace('_', ' ', $r ?: 'User'))
    };
}

function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $initials .= strtoupper(systemSafeSubstr($p, 0, 1));
        }
    }
    return systemSafeSubstr($initials, 0, 2) ?: 'U';
}

function formatTimeAgo(int $seconds): string {
    if ($seconds < 60) {
        return 'Now';
    }
    $minutes = (int)floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . 'm ago';
    }
    $hours = (int)floor($minutes / 60);
    return $hours . 'h ago';
}

try {
    $db = Database::get();

    // Query active sessions from sessions table (within 15 minutes / 900 seconds)
    $stmt = $db->prepare("
        SELECT
            s.id AS session_id,
            s.user_id,
            s.ip_address,
            s.user_agent,
            s.last_activity,
            u.username,
            u.role,
            u.full_name,
            u.first_name,
            u.last_name
        FROM sessions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.last_activity > UNIX_TIMESTAMP() - 900
        ORDER BY s.last_activity DESC
        LIMIT 25
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Latest audit activity per session username. Usernames are bound as
    // literals instead of comparing audit_trail.username with users.username:
    // the two columns use different collations on some deployments
    // (utf8mb4_uca1400_ai_ci vs utf8mb4_unicode_ci), which raises MySQL error
    // 1267 "Illegal mix of collations". Literals inherit the column collation,
    // so the indexed lookup stays valid.
    $sessionUsernames = [];
    foreach ($sessions as $row) {
        $uname = (string)($row['username'] ?? '');
        if ($uname !== '' && !in_array($uname, $sessionUsernames, true)) {
            $sessionUsernames[] = $uname;
        }
    }

    $activityByUser = [];
    if (!empty($sessionUsernames)) {
        $placeholders = implode(', ', array_fill(0, count($sessionUsernames), '?'));
        $activityStmt = $db->prepare("
            SELECT at.username,
                   COALESCE(NULLIF(at.action, ''), NULLIF(at.description, '')) AS activity
            FROM audit_trail at
            JOIN (
                SELECT username, MAX(id) AS max_id
                FROM audit_trail
                WHERE username IN ($placeholders)
                GROUP BY username
            ) latest ON latest.max_id = at.id
        ");
        $activityStmt->execute($sessionUsernames);
        foreach ($activityStmt->fetchAll(\PDO::FETCH_ASSOC) as $activityRow) {
            $activity = (string)($activityRow['activity'] ?? '');
            if ($activity !== '') {
                $activityByUser[strtolower((string)$activityRow['username'])] = $activity;
            }
        }
    }

    $now = time();
    $data = [];

    foreach ($sessions as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        $rawName = trim((string)($row['full_name'] ?? ''));
        if ($rawName === '') {
            $rawName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        }
        if ($rawName === '') {
            $rawName = (string)($row['username'] ?? 'User #' . ($userId ?: 'Guest'));
        }

        $secondsAgo = max(0, $now - (int)($row['last_activity'] ?? $now));
        $isSelf = ($userId > 0 && $userId === $currentUserId) || (strcasecmp((string)($row['username'] ?? ''), $currentUsername) === 0);

        // Activity text derived from audit trail or current page
        $usernameKey = strtolower((string)($row['username'] ?? ''));
        $activity = '';
        if ($isSelf) {
            $activity = 'Viewing System Logs';
        } elseif (isset($activityByUser[$usernameKey])) {
            $activity = $activityByUser[$usernameKey];
        } else {
            $activity = 'Active in System';
        }

        if (systemSafeStrlen($activity) > 40) {
            $activity = systemSafeSubstr($activity, 0, 38) . '...';
        }

        $data[] = [
            'user_id'          => $userId,
            'name'             => $rawName,
            'username'         => (string)($row['username'] ?? ''),
            'role'             => formatUserRole((string)($row['role'] ?? ($isSelf ? ($currentUser['role'] ?? 'User') : 'User'))),
            'activity'         => $activity,
            'last_active_time' => formatTimeAgo($secondsAgo),
            'seconds_ago'      => $secondsAgo,
            'ip_address'       => (string)($row['ip_address'] ?? '127.0.0.1'),
            'initials'         => getInitials($rawName),
            'is_current_user'  => $isSelf
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'total'   => count($data)
    ]);
} catch (\Throwable $e) {
    error_log('getActiveSessions error: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
