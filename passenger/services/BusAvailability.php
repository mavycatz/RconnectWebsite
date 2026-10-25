<?php

/**
 * Boarding availability rules for a scanned bus.
 *
 * A bus may initiate passenger boarding only when its lifecycle status is
 * Active and its operational status is neither Under Maintenance nor Out of
 * Service. Archived, Inactive, and unknown lifecycle states are blocked by
 * the status check itself.
 */
final class BusAvailability
{
    public const BLOCKING_OPERATIONAL = ['Under Maintenance', 'Out of Service'];

    public static function boardingAllowed(?string $status, ?string $operationalStatus): bool
    {
        if (strcasecmp(trim((string)$status), 'Active') !== 0) {
            return false;
        }

        $operational = strtolower(trim((string)$operationalStatus));
        if ($operational === '') {
            return true;
        }

        foreach (self::BLOCKING_OPERATIONAL as $blocking) {
            if ($operational === strtolower($blocking)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Passenger-facing explanation for a refused boarding.
     */
    public static function reason(?string $status, ?string $operationalStatus): string
    {
        $operational = strtolower(trim((string)$operationalStatus));

        if ($operational === strtolower('Under Maintenance')) {
            return 'This bus is under maintenance and is not accepting passengers right now.';
        }
        if ($operational === strtolower('Out of Service')) {
            return 'This bus is out of service and is not accepting passengers right now.';
        }

        return 'This bus is not available for boarding right now.';
    }
}
