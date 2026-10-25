<?php

// ── Forced startup marker (always writes, regardless of debug flag) ──
$STARTUP_MARKER = __DIR__ . '/../storage/logs/.startup_marker';
@mkdir(__DIR__ . '/../storage/logs', 0777, true);
@file_put_contents($STARTUP_MARKER, date('Y-m-d H:i:s') . ' bootstrap loaded' . PHP_EOL, FILE_APPEND | LOCK_EX);

// ── Fatal error catcher ──
function dispatcher_fatal_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $logDir = __DIR__ . '/../storage/logs';
        @mkdir($logDir, 0777, true);
        @file_put_contents(
            $logDir . '/fatal.log',
            date('Y-m-d H:i:s') . ' FATAL: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
register_shutdown_function('dispatcher_fatal_handler');

// ── Debug mode ──
$DEBUG_FILE = __DIR__ . '/../storage/.debug';
$DISPATCHER_DEBUG = file_exists($DEBUG_FILE);

if ($DISPATCHER_DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    @error_reporting(E_ALL);
}

// ── Diagnostic log ──
function dispatcher_log($msg) {
    global $DISPATCHER_DEBUG;
    if (!$DISPATCHER_DEBUG) return;
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/dispatcher.log';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ── Shared utility functions ──
function _isMobile(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/(android|iphone|ipad|ipod|mobile|tablet|kindle|silk|blackberry|opera mini|iemobile|wpdesktop)/i', $ua);
}

// ── Shared session save path ──
if (!defined('DISPATCHER_SESSION_PATH')) {
    define('DISPATCHER_SESSION_PATH', __DIR__ . '/../storage/sessions');
}

function dispatcher_init_session() {
    // Neutralize host-level session config that can kill long-lived sessions:
    // strict mode discards the session when its file is briefly missing,
    // and a broken hardcoded save_path makes every request a fresh session.
    @ini_set('session.use_strict_mode', '0');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_trans_sid', '0');
    // Long-lived sessions: dispatchers should stay logged in for the whole day
    @ini_set('session.gc_maxlifetime', '86400');
    @ini_set('session.cookie_lifetime', '86400');

    // Pick the FIRST writable session directory: custom path → system temp → host default
    $candidates = [DISPATCHER_SESSION_PATH];
    if (function_exists('sys_get_temp_dir') && sys_get_temp_dir()) {
        $candidates[] = sys_get_temp_dir();
    }
    if (ini_get('session.save_path')) {
        $candidates[] = ini_get('session.save_path');
    }
    $sessionPath = null;
    foreach ($candidates as $p) {
        if (!is_dir($p)) {
            @mkdir($p, 0777, true);
        }
        if (is_dir($p) && @is_writable($p)) {
            $sessionPath = $p;
            break;
        }
    }
    if ($sessionPath) {
        @ini_set('session.save_path', $sessionPath);
    }

    if (session_status() === PHP_SESSION_NONE) {
        try {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        } catch (\Throwable $e) {
            dispatcher_log("SESSION_START_FAILED: " . $e->getMessage());
        }
    }

    // Always-on session diagnostics (not gated by debug flag)
    $sessLog = __DIR__ . '/../storage/logs/session.log';
    @file_put_contents(
        $sessLog,
        date('Y-m-d H:i:s') . " start uri=" . ($_SERVER['REQUEST_URI'] ?? '') .
            " sid=" . session_id() .
            " path=" . (session_save_path() ?: '(none)') .
            " cookie=" . ($_COOKIE[session_name()] ?? '(none)') . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Persist the dispatcher bearer token in a long-lived HttpOnly cookie.
 * Used to auto-restore the session if the PHP session file is lost.
 */
function dispatcher_set_remember_cookie($token) {
    $secure = !empty($_SERVER['HTTPS']);
    setcookie('dispatcher_remember', $token, [
        'expires' => time() + 172800,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * If the PHP session died, silently rebuild it from the remember cookie.
 * Verifies the token signature and the dispatcher role server-side.
 * Returns true when the session is (or was already) authenticated.
 */
function dispatcher_restore_session() {
    if (isset($_SESSION['inspector_id'])) return true;
    $token = $_COOKIE['dispatcher_remember'] ?? '';
    if (empty($token)) return false;

    require_once __DIR__ . '/utils/token.php';
    $payload = verifyToken($token);
    if (!$payload || empty($payload['id'])) return false;

    require_once dirname(__DIR__) . '/mobile/config/database.php';
    try {
        $db = new Database();
        $conn = $db->connect();
        $stmt = $conn->prepare("SELECT ia.inspector_id, i.inspector_name, ia.role, ia.status FROM inspector_accounts ia LEFT JOIN inspectors i ON i.account_id = ia.account_id WHERE ia.inspector_id = ? AND ia.role = 'dispatcher' AND ia.status = 'active' LIMIT 1");
        if (!$stmt) {
            $conn->close();
            return false;
        }
        $stmt->bind_param("s", $payload['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
    } catch (\Throwable $e) {
        return false;
    }
    if (!$row) return false;

    $_SESSION['inspector_id'] = $row['inspector_id'];
    $_SESSION['inspector_name'] = $row['inspector_name'] ?? $row['inspector_id'];
    $_SESSION['dispatcher_token'] = $token;
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (empty($_SESSION['dashboard_secret'])) {
        $_SESSION['dashboard_secret'] = bin2hex(random_bytes(8));
    }
    dispatcher_log("SESSION_RESTORED: id=" . $row['inspector_id']);
    return true;
}
