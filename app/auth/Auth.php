<?php
namespace App\Auth;

require_once __DIR__ . '/../audit/Audit.php';

use App\Audit\Audit;
use App\Core\Database;

class Auth
{
    public static function login(int $userId, string $role, ?string $username = null): void
    {
        $_SESSION['user'] = [
            'id' => $userId,
            'role' => $role,
            'username' => $username,
            'logged_in' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        $_SESSION['role'] = $role;

        $_SESSION['last_activity'] = time();

        if (!isset($_SESSION['csrf_token'])) {
            $secret = getenv('FINANCE_CSRF_SECRET') ?: 'csrf_default_secret_change_me';
            $_SESSION['csrf_token'] = hash_hmac('sha256', session_id(), $secret);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        try {
            $db = Database::get();
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
        } catch (\Throwable $e) {}

        Audit::log('Login', 'Login', 'User logged in', $userId, $username, $role, null, 'Success');
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $username = $_SESSION['user']['username'] ?? null;
        $role = $_SESSION['user']['role'] ?? null;

        Audit::log('Login', 'Logout', 'User logged out', $userId, $username, $role, null, 'Success');

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();
        }
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user']['logged_in']);
    }

    public static function userId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function username(): ?string
    {
        return $_SESSION['user']['username'] ?? null;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireRole(string $role): void
    {
        if (!self::check() || self::role() !== $role) {
            header('Location: /public/login/index.php');
            exit;
        }
    }
}