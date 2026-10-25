<?php

namespace App\Passenger;

use PDO;

class RateLimiter
{
    public static function hit(PDO $conn, string $action, string $key, int $max, int $windowSeconds, int $lockSeconds = 0): bool
    {
        $max = max(1, $max);
        $windowSeconds = max(1, $windowSeconds);
        $lockSeconds = max(0, $lockSeconds);

        $bucket = hash('sha256', $action . '|' . $key);

        try {
            $stmt = $conn->prepare("
                INSERT INTO passenger_rate_limits (bucket_key, action, hits, window_started_at, locked_until)
                VALUES (?, ?, 1, NOW(), NULL)
                ON DUPLICATE KEY UPDATE
                    hits = IF(window_started_at < DATE_SUB(NOW(), INTERVAL ? SECOND), 1, hits + 1),
                    window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), window_started_at)
            ");
            $stmt->execute([$bucket, $action, $windowSeconds, $windowSeconds]);

            $stmt = $conn->prepare("
                SELECT hits, window_started_at, locked_until
                FROM passenger_rate_limits
                WHERE bucket_key = ?
                LIMIT 1
            ");
            $stmt->execute([$bucket]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return true;
            }

            if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
                return false;
            }

            if ((int) $row['hits'] > $max) {
                if ($lockSeconds > 0) {
                    $conn->prepare("UPDATE passenger_rate_limits SET locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE bucket_key = ?")
                        ->execute([$lockSeconds, $bucket]);
                }
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            error_log('Passenger rate limit failed: ' . $e->getMessage());
            return true;
        }
    }

    public static function reset(PDO $conn, string $action, string $key): void
    {
        try {
            $bucket = hash('sha256', $action . '|' . $key);
            $conn->prepare("DELETE FROM passenger_rate_limits WHERE bucket_key = ?")->execute([$bucket]);
        } catch (\Throwable $e) {
        }
    }
}
