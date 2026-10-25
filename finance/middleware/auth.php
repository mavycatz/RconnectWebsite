<?php

namespace Finance\Middleware;

use App\Auth\Auth;

class AuthMiddleware
{
    public static function requireFinanceAccess(): void
    {
        if (!Auth::check()) {
            self::logFailedAccess('unauthenticated');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
            exit;
        }

        $user = Auth::user();
        $role = $user['role'] ?? '';

        if (!in_array($role, ['finance_manager', 'finance_officer', 'admin', 'super_admin'], true)) {
            self::logFailedAccess('forbidden');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: insufficient permissions.']);
            exit;
        }
    }

    private static function logFailedAccess(string $reason): void
    {
        if (interface_exists('App\\Audit\\Audit', false) || class_exists('App\\Audit\\Audit')) {
            try {
                require_once __DIR__ . '/../../app/audit/Audit.php';
                $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                \App\Audit\Audit::log(
                    'Finance',
                    'AccessDenied',
                    "Access denied ($reason) from $ip for $uri",
                    $_SESSION['user']['id'] ?? null,
                    $_SESSION['user']['username'] ?? null,
                    $_SESSION['user']['role'] ?? null,
                    null,
                    'Failed'
                );
            } catch (\Throwable $e) {}
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireFinanceAccess();

        $userRole = Auth::role();
        if ($userRole !== $role) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "Forbidden: requires role '$role'."]);
            exit;
        }
    }
}
