<?php declare(strict_types=1);

/**
 * Address directory checks: PSA payload normalization, bundled PSGC
 * fallback, and disk caching. No network access is required — the PSA
 * endpoint is pointed at an unreachable address so the fallback path runs.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/RoleManagement/GeoDirectory.php';

use Admin\Services\RoleManagement\GeoDirectory;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$cacheDir = sys_get_temp_dir() . '/rconnect-geo-test-' . getmypid();
@mkdir($cacheDir, 0777, true);

function makeService(string $cacheDir): GeoDirectory
{
    return new GeoDirectory([
        'base_url' => 'http://127.0.0.1:9/psgc/',
        'api_key' => 'test-key',
        'timeout' => 1,
        'cache_ttl' => 60,
        'fallback_ttl' => 60,
        'cache_dir' => $cacheDir,
    ]);
}

/* ------------------------- PSA payload normalization ------------------------- */

$service = makeService($cacheDir);

$payload = [
    'count' => 2,
    'results' => [
        ['code' => 'A1', 'name' => 'Alpha', 'province_code' => 'P1'],
        ['code' => 'A2', 'name' => 'Beta', 'province_code' => 'P2'],
    ],
];
check(
    $service->normalizePayload($payload, GeoDirectory::LEVEL_CITIES, 'P1') === [['id' => 'A1', 'label' => 'Alpha']],
    'PSA records are filtered by their parent code'
);

$altPayload = [
    'results' => [
        ['psgc_code' => 'B1', 'description' => 'Gamma', 'parent_code' => 'P2'],
    ],
];
check(
    $service->normalizePayload($altPayload, GeoDirectory::LEVEL_CITIES, 'P2') === [['id' => 'B1', 'label' => 'Gamma']],
    'Alternative PSA field names are mapped'
);

$orphanPayload = [
    'results' => [
        ['code' => 'C1', 'name' => 'NoParent'],
    ],
];
check(
    $service->normalizePayload($orphanPayload, GeoDirectory::LEVEL_CITIES, 'P1') === [],
    'Records without a parent are excluded from filtered levels'
);
check(
    count($service->normalizePayload($orphanPayload, GeoDirectory::LEVEL_PROVINCES, null)) === 1,
    'Unfiltered levels keep parent-less records'
);
check(
    $service->normalizePayload(['results' => [['code' => '', 'name' => 'X'], ['code' => 'D1']]], GeoDirectory::LEVEL_PROVINCES, null) === [],
    'Incomplete PSA records are skipped'
);

/* ------------------------- bundled fallback + caching ------------------------- */

$provinces = $service->provinces();
check($provinces['source'] === 'bundled', 'The bundled dataset is used when the PSA API is unreachable');
check(count($provinces['options']) > 70, 'Bundled provinces are loaded');
check(
    in_array(['id' => '012800000', 'label' => 'Ilocos Norte'], $provinces['options'], true),
    'Ilocos Norte is present with its PSGC code'
);
check(
    count(array_filter($provinces['options'], static fn ($o) => stripos($o['label'], 'Metro Manila') !== false || stripos($o['label'], 'Capital Region') !== false || $o['label'] === 'NCR')) > 0,
    'Region-level city parents (NCR) are offered as top level'
);

$cities = $service->cities('012800000');
check($cities['source'] === 'bundled', 'City fallback comes from the bundled dataset');
check(
    in_array(['id' => '012801000', 'label' => 'Adams'], $cities['options'], true),
    'Cities are filtered by province'
);
check(count($cities['options']) > 20, 'Ilocos Norte cities/municipalities are loaded');

$barangays = $service->barangays('012801000');
check(
    in_array(['id' => '012801001', 'label' => 'Adams (Pob.)'], $barangays['options'], true),
    'Barangays are filtered by city/municipality'
);
check($service->barangays('')['options'] === [], 'Barangay level requires a parent');

// 10-digit API codes map onto the 9-digit bundled snapshot.
$citiesFromTen = $service->cities('0128000000');
check(count($citiesFromTen['options']) > 20, '10-digit parent codes fall back to the 9-digit snapshot');

$cacheFiles = glob($cacheDir . '/geo-*.json') ?: [];
check(count($cacheFiles) >= 3, 'Each level is cached on disk');

$cachedAgain = makeService($cacheDir)->provinces();
check($cachedAgain['source'] === 'bundled' && count($cachedAgain['options']) === count($provinces['options']), 'Cached results are served on the next call');

$options = $provinces['options'];
$codes = array_column($options, 'id');
check(count($codes) === count(array_unique($codes)), 'Province codes are unique');
check(($options[0]['label'] ?? '') <= ($options[count($options) - 1]['label'] ?? ''), 'Options are sorted by name');

foreach ($cacheFiles as $file) {
    @unlink($file);
}
@rmdir($cacheDir);

echo "$checks geo directory checks passed.\n";
