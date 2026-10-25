<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/FacebookService.php';

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;
use Admin\Services\FacebookService;

header('Content-Type: application/json');

Session::start();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}

$user = Auth::user();
if (!in_array($user['role'] ?? null, ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

try {

    $db = Database::get();

    $getSetting = function (string $key, string $default = '') use ($db): string {
        try {
            $s = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $s->execute([$key]);
            $r = $s->fetch(\PDO::FETCH_ASSOC);
            return $r ? (string)$r['setting_value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    };

    $pageId = $getSetting('facebook_page_id', '');
    $token  = $getSetting('facebook_page_access_token', '');

    /* Fallback to config file if DB values are empty */
    $fbConfig = require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/facebook.php';
    if (empty($pageId)) $pageId = (string)($fbConfig['page_id'] ?? '');
    if (empty($token))  $token  = (string)($fbConfig['access_token'] ?? '');

    if (empty($pageId) || empty($token)) {
        echo json_encode([
            'valid'      => false,
            'error_type' => 'not_configured',
            'page_id'    => $pageId ?: null,
            'page_name'  => null,
        ]);
        exit;
    }

    $apiVersion = (string)($fbConfig['api_version'] ?? 'v23.0');
    $service    = new FacebookService($pageId, $token, $apiVersion);

    echo json_encode($service->validateToken($pageId));

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'valid'      => false,
        'error_type' => 'other',
        'page_id'    => null,
        'page_name'  => null,
        'error'      => $e->getMessage(),
    ]);
}