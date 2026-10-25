<?php

declare(strict_types=1);

/**
 * Server-side authorization for the administrator account endpoints.
 *
 * This file is included before any controller is wired up, so an account can
 * never be created or modified without a verified session and a role the
 * actor is actually permitted to grant.
 *
 * Nothing here trusts a value supplied by the client. The role written to the
 * database is decided from the session, and a submitted role is only accepted
 * when it appears in the allow-list and the actor is allowed to grant it.
 *
 * Error output is suppressed and buffered so a rejection can never leak a PHP
 * notice, an SQL error or a filesystem path.
 */

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

/**
 * Resolve a file inside app/ without assuming a directory casing.
 *
 * The app/auth directory is stored in lowercase but is referenced as 'Auth' in
 * a number of places in this codebase, and the filesystem is case-sensitive, so
 * a hard-coded casing can fail to open a file that plainly exists. Checking the
 * known variants keeps this guard working regardless of how the tree is stored.
 */
function admin_api_app_file(string $relative): string
{
    $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/';

    $candidates = [
        $root . 'app/' . $relative,
        $root . 'app/Auth/' . basename($relative),
        $root . 'app/AUTH/' . basename($relative),
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    // Nothing matched: return the plain path so the resulting error names the
    // file that was expected.
    return $root . 'app/' . $relative;
}

require_once admin_api_app_file('auth/Session.php');
require_once admin_api_app_file('auth/Auth.php');
require_once admin_api_app_file('Core/Database.php');

/**
 * Emit a clean JSON rejection and stop. The message is a fixed string so no
 * internal detail can escape.
 */
function admin_api_fail(int $code, string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($code);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}

/**
 * Canonical role policy, shared with the models that write users.role.
 */
function admin_api_role_config(): array
{
    static $config = null;

    if ($config === null) {
        $loaded = require $_SERVER['DOCUMENT_ROOT']
            . '/admin/config/admin_roles.php';

        if (
            !is_array($loaded)
            || empty($loaded['assignable'])
            || !is_array($loaded['assignable'])
            || empty($loaded['privileged'])
        ) {
            error_log('Admin API role configuration is missing or malformed.');

            admin_api_fail(500, 'Authorization is unavailable.');
        }

        $config = $loaded;
    }

    return $config;
}

/**
 * Roles an administrator account is allowed to hold.
 */
function admin_api_assignable_roles(): array
{
    return admin_api_role_config()['assignable'];
}

/**
 * The privileged role. Only a holder of this role may grant or change it.
 */
function admin_api_privileged_role(): string
{
    return (string) admin_api_role_config()['privileged'];
}

/**
 * Resolve the caller's role from the session, or reject the request.
 */
function admin_api_actor_role(): string
{
    if (!Auth::check()) {
        admin_api_fail(403, 'Forbidden');
    }

    $role = strtolower(trim((string) (Auth::user()['role'] ?? '')));

    if (!in_array($role, admin_api_assignable_roles(), true)) {
        admin_api_fail(403, 'Forbidden');
    }

    return $role;
}

/**
 * True when the actor holds the privileged role.
 */
function admin_api_is_super(string $actorRole): bool
{
    return $actorRole === admin_api_privileged_role();
}

/**
 * Normalise a submitted role against the allow-list.
 *
 * Returns '' when the value is not a role this system supports.
 */
function admin_api_clean_role($raw): string
{
    $role = strtolower(trim((string) $raw));

    return in_array($role, admin_api_assignable_roles(), true) ? $role : '';
}

/**
 * Decide whether the actor may grant the requested role.
 *
 * Administrator accounts are strictly Super-Admin-only: a normal admin can
 * never create or promote another administrator. Drivers, inspectors and
 * finance accounts are managed through Role Management (which additionally
 * requires Security Authorization Code verification).
 */
function admin_api_can_assign(string $actorRole, string $requestedRole): bool
{
    if ($requestedRole === '') {
        return false;
    }

    if (!in_array($requestedRole, admin_api_assignable_roles(), true)) {
        return false;
    }

    return admin_api_is_super($actorRole);
}

/**
 * Read an account's current role straight from the database, so an update is
 * judged against the role the target actually holds rather than anything the
 * request claims.
 */
function admin_api_current_role(int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    try {
        $stmt = Database::get()->prepare(
            'SELECT role FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return strtolower(trim((string) ($row['role'] ?? '')));
    } catch (Throwable $e) {
        error_log('Admin API role lookup failed: ' . $e->getMessage());

        // Fail closed: an unverifiable target must not be writable.
        admin_api_fail(500, 'Unable to verify the requested account.');
    }

    return '';
}
