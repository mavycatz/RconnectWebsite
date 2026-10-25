<?php

/**
 * Shared driver license expiry rule for the create and edit driver forms.
 * The same system date drives the date-picker minimum and the server-side
 * check so both can never disagree.
 */

if (!function_exists('driverLicenseExpiryMinimumDate')) {
    function driverLicenseExpiryMinimumDate(): string
    {
        return date('Y-m-d', strtotime('+4 days'));
    }
}

if (!function_exists('driverLicenseExpiryErrorMessage')) {
    function driverLicenseExpiryErrorMessage(): string
    {
        return "Driver's license must be valid for more than 3 days.";
    }
}

if (!function_exists('driverLicenseExpiryIsValid')) {
    function driverLicenseExpiryIsValid(?string $date): bool
    {
        $date = trim((string) $date);

        if ($date === '') {
            return true;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return false;
        }

        return $parsed >= new DateTime(driverLicenseExpiryMinimumDate());
    }
}
