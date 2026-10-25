<?php

namespace Finance\Middleware;

class RateLimit
{
    private static array $limits = [
        'standard' => ['limit' => 60, 'window' => 60],
        'ai' => ['limit' => 10, 'window' => 60],
        'export' => ['limit' => 5, 'window' => 60],
    ];

    private static string $storageDir = '';

    public static function init(): void
    {
        self::$storageDir = __DIR__ . '/../storage/ratelimit';
        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }
    }

    public static function getLimit(string $group): array
    {
        return self::$limits[$group] ?? self::$limits['standard'];
    }

    public static function check(string $group = 'standard'): bool
    {
        self::init();

        $limit = self::getLimit($group);
        $key = self::buildKey($group);
        $state = self::readState($key);

        $now = time();
        $windowStart = $now - $limit['window'];

        $state = array_values(array_filter($state, fn($ts) => $ts > $windowStart));

        if (count($state) >= $limit['limit']) {
            $_SERVER['RATELIMIT_RETRY_AFTER'] = $state[0] + $limit['window'] - $now;
            self::logRateLimit($group);
            return false;
        }

        $state[] = $now;
        self::writeState($key, $state, $limit['window']);

        return true;
    }

    public static function retryAfter(): int
    {
        return max(1, (int)($_SERVER['RATELIMIT_RETRY_AFTER'] ?? 1));
    }

    private static function logRateLimit(string $group): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        error_log("Rate limit exceeded: group=$group ip=$ip uri=$uri");

        if (interface_exists('App\\Audit\\Audit', false) || class_exists('App\\Audit\\Audit')) {
            try {
                require_once __DIR__ . '/../../app/audit/Audit.php';
                \App\Audit\Audit::log(
                    'Finance',
                    'RateLimitExceeded',
                    "Rate limit '$group' exceeded from $ip for $uri",
                    $_SESSION['user']['id'] ?? null,
                    $_SESSION['user']['username'] ?? null,
                    $_SESSION['user']['role'] ?? null,
                    null,
                    'Failed'
                );
            } catch (\Throwable $e) {}
        }
    }

    private static function buildKey(string $group): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return hash('sha256', $ip . '|' . $group);
    }

    private static function readState(string $key): array
    {
        $path = self::$storageDir . '/' . $key . '.json';
        if (!file_exists($path)) {
            return [];
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return [];
        }
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function writeState(string $key, array $state, int $ttl): void
    {
        $path = self::$storageDir . '/' . $key . '.json';
        @file_put_contents($path, json_encode($state), LOCK_EX);
    }
}
