<?php

declare(strict_types=1);

/**
 * Lightweight File-Based Caching for Admin Dashboard APIs
 * 
 * Provides fast 30-60 second caching for heavy analytical queries.
 * No external dependencies, Redis, or paid infrastructure.
 */

if (!function_exists('dashboard_cache_dir')) {
    function dashboard_cache_dir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_dash_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('dashboard_cache_key')) {
    function dashboard_cache_key(string $prefix, array $params = []): string
    {
        // Normalize params: sort keys and remove null/empty strings to ensure consistent keys
        ksort($params);
        $normalized = [];
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null) {
                $normalized[$k] = (string)$v;
            }
        }
        return $prefix . '_' . md5(serialize($normalized));
    }
}

if (!function_exists('dashboard_cache_get')) {
    function dashboard_cache_get(string $key, int $ttl = 45): ?array
    {
        // Check for manual cache bypass via GET parameter
        if (isset($_GET['_nocache']) && in_array(strtolower((string)$_GET['_nocache']), ['1', 'true', 'yes'], true)) {
            return null;
        }

        $dir = dashboard_cache_dir();
        $file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $key) . '.json';

        if (!file_exists($file)) {
            return null;
        }

        $mtime = @filemtime($file);
        if (!$mtime || (time() - $mtime) > $ttl) {
            @unlink($file);
            return null;
        }

        $content = @file_get_contents($file);
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            @unlink($file);
            return null;
        }

        // Lightweight browser caching header
        $remaining = max(10, $ttl - (time() - $mtime));
        if (!headers_sent()) {
            header('Cache-Control: private, max-age=' . $remaining);
            header('X-Dashboard-Cache: HIT');
        }

        return $data;
    }
}

if (!function_exists('dashboard_cache_save')) {
    function dashboard_cache_save(string $key, array $data, int $ttl = 45): bool
    {
        $dir = dashboard_cache_dir();
        $file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $key) . '.json';
        $tmp = $file . '.' . uniqid('tmp', true);

        $json = json_encode($data);
        if ($json === false) {
            return false;
        }

        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }

        if (!headers_sent()) {
            header('Cache-Control: private, max-age=' . $ttl);
            header('X-Dashboard-Cache: MISS');
        }

        return @rename($tmp, $file);
    }
}

if (!function_exists('dashboard_cache_clear')) {
    function dashboard_cache_clear(): void
    {
        $dir = dashboard_cache_dir();
        if (!is_dir($dir)) return;
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}
