<?php declare(strict_types=1);

/**
 * Philippine address directory for the Role Management forms.
 *
 * Proxies the official PSA PSGC classification API (key stays server-side)
 * and falls back to the bundled PSGC snapshot whenever the API is
 * unreachable or returns no records.
 *
 *   GET ?level=provinces
 *   GET ?level=cities&province=<province code>
 *   GET ?level=barangays&city=<city/municipality code>
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/GeoDirectory.php';

use Admin\Services\RoleManagement\GeoDirectory;

rmg_actor();

header('Cache-Control: no-store');

$level = strtolower(trim((string)($_GET['level'] ?? '')));

try {
    $directory = new GeoDirectory();

    switch ($level) {
        case GeoDirectory::LEVEL_PROVINCES:
            $result = $directory->provinces();
            break;

        case GeoDirectory::LEVEL_CITIES:
            $province = trim((string)($_GET['province'] ?? ''));
            if ($province === '') {
                rmg_fail(400, 'Select a province first.');
            }
            $result = $directory->cities($province);
            break;

        case GeoDirectory::LEVEL_BARANGAYS:
            $city = trim((string)($_GET['city'] ?? ''));
            if ($city === '') {
                rmg_fail(400, 'Select a city or municipality first.');
            }
            $result = $directory->barangays($city);
            break;

        default:
            rmg_fail(400, 'Unknown address level.');
    }

    echo json_encode([
        'success' => true,
        'level' => $level,
        'source' => $result['source'],
        'options' => $result['options'],
    ]);
} catch (Throwable $e) {
    error_log('Geo directory failed: ' . $e->getMessage());
    rmg_fail(500, 'Address data is unavailable. Please type the address instead.');
}
