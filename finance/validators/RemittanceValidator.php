<?php

namespace Finance\Validators;

class RemittanceValidator
{
    // ✅ CASH VALIDATION
    public static function validateCash($amount)
    {
        if (!is_numeric($amount)) {
            throw new \Exception("Invalid cash input");
        }

        if ($amount < 0) {
            throw new \Exception("Cash cannot be negative");
        }

        // optional: limit (safety)
        if ($amount > 1000000) {
            throw new \Exception("Cash value too large");
        }
    }

    // ✅ TRIP VALIDATION
    public static function validateRemittance($trip_no, $bus_no)
    {
        if (empty($trip_no) || empty($bus_no)) {
            throw new \Exception("Invalid remittance data");
        }
    }

    // ✅ DRIVER VALIDATION (FOR FINALIZE)
    public static function validateDriver($driver)
    {
        if (empty($driver)) {
            throw new \Exception("Invalid driver");
        }
    }

    // ✅ SECRET CODE VALIDATION
    public static function validateCode($code)
    {
        if (empty($code)) {
            throw new \Exception("Finance code is required");
        }

        if (strlen($code) < 4) {
            throw new \Exception("Invalid finance code");
        }
    }

    // ✅ PREVENT SUBMIT IF ALREADY FINALIZED
    public static function validateNotSubmitted($alreadySubmitted)
    {
        if ($alreadySubmitted) {
            throw new \Exception("Remittance already submitted");
        }
    }

    // ✅ ACTUAL CASH MAY EXCEED THE EXPECTED CASH
    //
    // Overage acceptance (discrepancy investigation workflow): an actual
    // amount above expected is recorded and automatically creates an Excess
    // Remittance case instead of being rejected. Only negative amounts are
    // invalid here; the amount ceiling is enforced by validateCash().
    public static function validateCashAgainstExpected($actual, $expected)
    {
        $actual = round((float) $actual, 2);
        $expected = round((float) $expected, 2);

        if ($actual < 0 || $expected < 0) {
            throw new \Exception("Cash amounts cannot be negative");
        }
    }
}