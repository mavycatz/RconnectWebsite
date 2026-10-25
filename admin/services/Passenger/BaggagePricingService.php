<?php declare(strict_types=1);

namespace Admin\Services\Passenger;

use PDO;

/**
 * Existing baggage/cargo fare rule, extracted so the driver flow and the
 * admin Baggage page cannot drift apart.
 *
 * The segment fare is the route's regular (or type-specific) fare between the
 * boarding stop and the destination: minimum fare for the same stop, the
 * destination fare when boarding at the first stop, otherwise the difference —
 * floored at the minimum — then multiplied by the occupied spaces.
 */
class BaggagePricingService
{
    public const MINIMUM_FARE = 15.0;

    public static function sameStopFare(): float
    {
        return self::MINIMUM_FARE;
    }

    public static function segmentFare(float $destinationFare, float $boardingFare, bool $boardingIsFirstStop): float
    {
        if ($boardingIsFirstStop) {
            return round(max(self::MINIMUM_FARE, $destinationFare), 2);
        }
        return round(max($destinationFare - $boardingFare, self::MINIMUM_FARE), 2);
    }

    public static function multiply(float $segmentFare, int $spaces): float
    {
        return round($segmentFare * max(1, $spaces), 2);
    }

    /**
     * Route stops used for fare lookup (fare_pricing), ordered by the route.
     */
    public function stopsForRoute(PDO $db, string $routeName): array
    {
        try {
            $stmt = $db->prepare("
                SELECT id, destination, display_order, regular_fare, student_fare, special_fare
                FROM fare_pricing
                WHERE route_name = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$routeName]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('BaggagePricingService stops query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function findStop(array $stops, string $point): ?array
    {
        $needle = strtolower(trim(preg_replace('/\s+/', ' ', $point) ?? $point));
        if ($needle === '') {
            return null;
        }
        foreach ($stops as $stop) {
            $name = strtolower(trim(preg_replace('/\s+/', ' ', (string)$stop['destination']) ?? (string)$stop['destination']));
            if ($name === $needle) {
                return $stop;
            }
        }
        return null;
    }

    /**
     * Full baggage fare for a route/boarding/destination/spaces combination,
     * or null when the route stops cannot be resolved (never fabricate).
     */
    public function fareForRoute(PDO $db, string $routeName, string $boardingPoint, string $destination, int $spaces = 1, string $fareType = 'regular'): ?float
    {
        $stops = $this->stopsForRoute($db, $routeName);
        if (!$stops) {
            return null;
        }

        $board = $this->findStop($stops, $boardingPoint);
        $dest = $this->findStop($stops, $destination);
        if ($board === null || $dest === null) {
            return null;
        }

        $fareKey = match (strtolower($fareType)) {
            'student', 'senior', 'pwd' => 'student_fare',
            'special', 'teacher', 'nurse' => 'special_fare',
            default => 'regular_fare',
        };

        if ((int)$board['id'] === (int)$dest['id']) {
            $base = self::sameStopFare();
        } else {
            $base = self::segmentFare(
                (float)$dest[$fareKey],
                (float)$board[$fareKey],
                ((int)$board['display_order']) === 1
            );
        }

        return self::multiply($base, $spaces);
    }
}
