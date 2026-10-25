<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

use Throwable;

/**
 * Philippine address directory (Provinces → Cities/Municipalities → Barangays).
 *
 * Source order:
 *   1. the official PSA classification API (admin/config/geo.php)
 *   2. the bundled PSGC snapshot (admin/assets/data/ph-locations.json)
 *
 * Results are cached on disk so the API is queried rarely. The API key is
 * only ever used server-side.
 */
final class GeoDirectory
{
    public const LEVEL_PROVINCES = 'provinces';
    public const LEVEL_CITIES = 'cities';
    public const LEVEL_BARANGAYS = 'barangays';

    private const PSA_ENDPOINTS = [
        'provinces' => ['provinces/provinces'],
        'cities' => ['municipalities/municipalities', 'cities/cities'],
        'barangays' => ['barangays/barangays'],
    ];

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? self::config();
    }

    /**
     * @return array{base_url:string,api_key:string,timeout:int,cache_ttl:int,fallback_ttl:int}
     */
    public static function config(): array
    {
        static $config = null;

        if ($config === null) {
            $loaded = @require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/geo.php';
            $config = is_array($loaded) ? $loaded : [];
        }

        return $config + [
            'base_url' => 'https://classification.psa.gov.ph/psgc/',
            'api_key' => '',
            'timeout' => 8,
            'cache_ttl' => 2592000,
            'fallback_ttl' => 3600,
            'cache_dir' => null,
        ];
    }

    /**
     * @return array{source:string,options:array<int,array{id:string,label:string}>}
     */
    public function provinces(): array
    {
        return $this->resolve(self::LEVEL_PROVINCES, null);
    }

    /**
     * @return array{source:string,options:array<int,array{id:string,label:string}>}
     */
    public function cities(?string $provinceCode): array
    {
        return $this->resolve(self::LEVEL_CITIES, $provinceCode);
    }

    /**
     * @return array{source:string,options:array<int,array{id:string,label:string}>}
     */
    public function barangays(?string $cityCode): array
    {
        return $this->resolve(self::LEVEL_BARANGAYS, $cityCode);
    }

    /**
     * @return array{source:string,options:array<int,array{id:string,label:string}>}
     */
    private function resolve(string $level, ?string $parentCode): array
    {
        $cacheKey = $level . ':' . (string)$parentCode;
        $cached = $this->cacheGet($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $options = $this->fromPsa($level, $parentCode);
        $source = 'psa';

        if (!$options) {
            $options = $this->fromBundled($level, $parentCode);
            $source = 'bundled';
        }

        $result = ['source' => $source, 'options' => $options];
        $this->cacheSet($cacheKey, $result, $source === 'psa'
            ? (int)$this->config['cache_ttl']
            : (int)$this->config['fallback_ttl']);

        return $result;
    }

    /* ----------------------------- PSA API ----------------------------- */

    /**
     * @return array<int,array{id:string,label:string}>
     */
    private function fromPsa(string $level, ?string $parentCode): array
    {
        $baseUrl = rtrim((string)$this->config['base_url'], '/') . '/';
        $options = [];

        foreach (self::PSA_ENDPOINTS[$level] ?? [] as $path) {
            $payload = $this->requestPsa($baseUrl . $path, $parentCode, $level);

            foreach ($this->normalizePayload($payload, $level, $parentCode) as $option) {
                $options[$option['id']] = $option;
            }
        }

        $options = array_values($options);
        usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    private function requestPsa(string $url, ?string $parentCode, string $level): array
    {
        $query = ['token' => (string)$this->config['api_key']];

        if ($parentCode !== null && $parentCode !== '') {
            // Harmless when unsupported; PSA ignores unknown filters.
            foreach ($level === self::LEVEL_CITIES ? ['province_code'] : ['municipality_code', 'city_code'] as $param) {
                $query[$param] = $parentCode;
            }
        }

        $requestUrl = $url . '?' . http_build_query($query);

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => (int)$this->config['timeout'],
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: RConnect/1.0\r\n",
                ],
            ]);

            $body = @file_get_contents($requestUrl, false, $context);
            if (!is_string($body) || trim($body) === '') {
                return [];
            }

            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Normalize a PSA (DRF-paginated or plain) payload into options.
     *
     * Field names are matched tolerantly so a schema change degrades to the
     * bundled dataset instead of breaking the form.
     *
     * @return array<int,array{id:string,label:string}>
     */
    public function normalizePayload(array $payload, string $level, ?string $parentCode): array
    {
        $rows = [];
        if (isset($payload['results']) && is_array($payload['results'])) {
            $rows = $payload['results'];
        } elseif (isset($payload[0]) || $payload === []) {
            $rows = array_values($payload);
        }

        $options = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $this->pick($row, ['code', 'psgc_code', 'geographic_code', 'id', 'value']);
            $name = $this->pick($row, ['name', 'description', 'geographic_name', 'label', 'text']);

            if ($code === '' || $name === '') {
                continue;
            }

            if ($parentCode !== null && $parentCode !== '' && $level !== self::LEVEL_PROVINCES) {
                $parent = $this->pick($row, [
                    'province_code', 'municipality_code', 'city_code',
                    'parent_code', 'parent', 'parent_id', 'region_code',
                ]);
                if (!$this->parentMatches($parent, $parentCode)) {
                    continue;
                }
            }

            $options[] = ['id' => $code, 'label' => $name];
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $row
     * @param string[] $keys
     */
    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && !is_array($row[$key])) {
                $value = trim((string)$row[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * PSGC codes exist in 9-digit and 10-digit (check-digit) forms; accept a
     * match when one is the other with a trailing check digit.
     */
    private function parentMatches(string $parent, string $filter): bool
    {
        if ($parent === '' || $filter === '') {
            return false;
        }
        if ($parent === $filter) {
            return true;
        }
        $longer = strlen($parent) >= strlen($filter) ? $parent : $filter;
        $shorter = strlen($parent) >= strlen($filter) ? $filter : $parent;

        return strlen($longer) - strlen($shorter) <= 1 && substr($longer, 0, strlen($shorter)) === $shorter;
    }

    /* --------------------------- bundled snapshot --------------------------- */

    /**
     * @return array<int,array{id:string,label:string}>
     */
    private function fromBundled(string $level, ?string $parentCode): array
    {
        $data = $this->bundledData();
        if ($data === null) {
            return [];
        }

        $options = [];

        if ($level === self::LEVEL_PROVINCES) {
            foreach (($data['p'] ?? []) as $code => $name) {
                $options[] = ['id' => (string)$code, 'label' => (string)$name];
            }
            // Regions that act as city parents (for example Metro Manila).
            $regions = $data['r'] ?? [];
            $regionParents = [];
            foreach (($data['c'] ?? []) as $city) {
                $parent = (string)($city[2] ?? '');
                if ($parent !== '' && !isset($data['p'][$parent]) && isset($regions[$parent])) {
                    $regionParents[$parent] = (string)$regions[$parent];
                }
            }
            foreach ($regionParents as $code => $name) {
                $options[] = ['id' => (string)$code, 'label' => $name];
            }
        } elseif ($level === self::LEVEL_CITIES) {
            foreach (($data['c'] ?? []) as $city) {
                if ($parentCode !== null && $parentCode !== '' && (string)($city[2] ?? '') !== $parentCode) {
                    continue;
                }
                $options[] = ['id' => (string)($city[0] ?? ''), 'label' => (string)($city[1] ?? '')];
            }
            if (!$options && $parentCode !== null && strlen($parentCode) === 10) {
                // A 10-digit code from the API may still map onto the bundled
                // 9-digit snapshot (the extra character is a check digit).
                foreach (($data['c'] ?? []) as $city) {
                    if ((string)($city[2] ?? '') !== substr($parentCode, 0, 9)) {
                        continue;
                    }
                    $options[] = ['id' => (string)($city[0] ?? ''), 'label' => (string)($city[1] ?? '')];
                }
            }
        } else {
            if ($parentCode === null || $parentCode === '') {
                return [];
            }
            foreach (($data['b'] ?? []) as $barangay) {
                if ((string)($barangay[2] ?? '') !== $parentCode) {
                    continue;
                }
                $options[] = ['id' => (string)($barangay[0] ?? ''), 'label' => (string)($barangay[1] ?? '')];
            }
            if (!$options && strlen($parentCode) === 10) {
                foreach (($data['b'] ?? []) as $barangay) {
                    if ((string)($barangay[2] ?? '') !== substr($parentCode, 0, 9)) {
                        continue;
                    }
                    $options[] = ['id' => (string)($barangay[0] ?? ''), 'label' => (string)($barangay[1] ?? '')];
                }
            }
        }

        $options = array_values(array_filter(
            $options,
            static fn (array $option): bool => $option['id'] !== '' && $option['label'] !== ''
        ));
        usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    private function bundledData(): ?array
    {
        static $data = null;
        static $loaded = false;

        if ($loaded) {
            return $data;
        }
        $loaded = true;

        $path = $_SERVER['DOCUMENT_ROOT'] . '/admin/assets/data/ph-locations.json';
        if (!is_file($path)) {
            return null;
        }

        try {
            $decoded = json_decode((string)file_get_contents($path), true);
            $data = (is_array($decoded) && isset($decoded['c'])) ? $decoded : null;
        } catch (Throwable $e) {
            $data = null;
        }

        return $data;
    }

    /* ------------------------------- cache ------------------------------- */

    private function cacheDir(): string
    {
        $dir = (string)($this->config['cache_dir'] ?? '');
        if ($dir === '') {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/storage/cache';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @return array{source:string,options:array}|null
     */
    private function cacheGet(string $key): ?array
    {
        $file = $this->cacheDir() . '/geo-' . md5($key) . '.json';
        if (!is_file($file)) {
            return null;
        }

        try {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (!is_array($decoded) || !isset($decoded['saved'], $decoded['options'], $decoded['ttl'])) {
                return null;
            }
            if ((int)$decoded['saved'] + (int)$decoded['ttl'] < time()) {
                return null;
            }

            return [
                'source' => (string)($decoded['source'] ?? 'bundled'),
                'options' => is_array($decoded['options']) ? $decoded['options'] : [],
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array{source:string,options:array} $result
     */
    private function cacheSet(string $key, array $result, int $ttl): void
    {
        try {
            $file = $this->cacheDir() . '/geo-' . md5($key) . '.json';
            @file_put_contents($file, json_encode([
                'saved' => time(),
                'ttl' => $ttl,
                'source' => $result['source'],
                'options' => $result['options'],
            ]));
        } catch (Throwable $e) {
            // Caching is best-effort only.
        }
    }
}
