<?php

declare(strict_types=1);

namespace App\Passenger;

use DateTime;

/**
 * PassengerValidator
 *
 * Centralized, consistent validation and sanitization service for all
 * passenger-submitted data across registration, login, profile, one-time
 * boarding, trips, and payments.
 *
 * Design principles:
 * 1. Strict validation with user-friendly error messages (no technical/DB leaks).
 * 2. Does not corrupt legitimate passenger names, IDs, or inputs.
 * 3. Consistent rules across both web forms and JSON API endpoints.
 */
class PassengerValidator
{
    /**
     * Supported passenger discount types and whether an ID is required.
     */
    public const PASSENGER_TYPES = [
        'Regular'          => ['requires_id' => false, 'label' => 'Regular'],
        'Student'          => ['requires_id' => true,  'label' => 'Student ID'],
        'Senior'           => ['requires_id' => true,  'label' => 'Senior Citizen ID / PhilSys National ID'],
        'Senior Citizen'   => ['requires_id' => true,  'label' => 'Senior Citizen ID / PhilSys National ID'],
        'PWD'              => ['requires_id' => true,  'label' => 'PWD ID'],
        'Special'          => ['requires_id' => true,  'label' => 'Teacher / Nurse ID'],
        'Teacher'          => ['requires_id' => true,  'label' => 'Teacher / School ID'],
        'Nurse'            => ['requires_id' => true,  'label' => 'PRC ID'],
        'Cooperative'      => ['requires_id' => true,  'label' => 'Cooperative Beneficiary ID'],
        'RPMVFMPC Member'  => ['requires_id' => true,  'label' => 'Cooperative Beneficiary ID'],
    ];

    /**
     * Supported discount verification ID types in R-CONNECT.
     * All generic, non-discount IDs (Driver's License, Passport, Barangay, Postal, UMID)
     * are strictly excluded.
     */
    public const SUPPORTED_ID_TYPES = [
        'student-id'          => 'Student ID',
        'senior-citizen-id'   => 'Senior Citizen ID',
        'philsys-id'          => 'PhilSys / National ID',
        'pwd-id'              => 'PWD ID',
        'teacher-id'          => 'Teacher / School ID',
        'prc-id'              => 'PRC ID',
        'coop-beneficiary-id' => 'Cooperative Beneficiary ID',
    ];

    /**
     * Context-dependent allowed ID types per Passenger Type.
     */
    public const PASSENGER_TYPE_ID_MAP = [
        'Regular'          => [],
        'Student'          => ['student-id'],
        'Senior Citizen'   => ['senior-citizen-id', 'philsys-id'],
        'Senior'           => ['senior-citizen-id', 'philsys-id'],
        'PWD'              => ['pwd-id'],
        'RPMVFMPC Member'  => ['coop-beneficiary-id'],
        'Cooperative'      => ['coop-beneficiary-id'],
        'Teacher'          => ['teacher-id'],
        'Nurse'            => ['prc-id'],
        'Special'          => ['teacher-id', 'prc-id'],
    ];

    /**
     * Validate full name:
     * - Required
     * - Letters, spaces, periods, hyphens, and apostrophes allowed
     * - Disallow numbers and special characters (@, !, <, >, etc.)
     * - Leading and trailing whitespace trimmed
     * - Multiple consecutive spaces collapsed into a single space
     * - Length between 2 and 100 characters
     * - Must contain at least two words (first name and last name)
     * - Reject repetitive characters (3+ identical characters in a row)
     * - Reject consonant clusters (5+ consecutive consonants)
     * - Reject keyboard mash sequences (asdf, qwerty, zxcv, etc.)
     * - Reject dummy words (test, dummy, sample, random, etc.)
     * - Require at least one vowel in each word (excluding standard abbreviations like Ma., St., Sr., Jr., Ng)
     * - Does not silently corrupt valid names
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validateFullName(?string $rawName, string $fieldLabel = 'Full name'): array
    {
        if ($rawName === null) {
            return ['valid' => false, 'value' => '', 'error' => "{$fieldLabel} is required."];
        }

        // Trim leading and trailing whitespace
        $trimmed = trim($rawName);

        // Collapse multiple consecutive whitespace characters to a single space
        $collapsed = (string) preg_replace('/\s+/u', ' ', $trimmed);

        if ($collapsed === '') {
            return ['valid' => false, 'value' => '', 'error' => "{$fieldLabel} is required."];
        }

        $length = mb_strlen($collapsed, 'UTF-8');
        if ($length < 2) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$fieldLabel} must be at least 2 characters long."];
        }

        if ($length > 100) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$fieldLabel} cannot exceed 100 characters."];
        }

        // Reject if contains any digits
        if (preg_match('/\d/', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$fieldLabel} must not contain numbers."];
        }

        // Must contain at least one Unicode letter
        if (!preg_match('/[\p{L}]/u', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
        }

        // Allowed characters: letters (any language/accent), spaces, periods, hyphens, apostrophes (standard & curved)
        if (!preg_match("/^[\p{L}\p{M} .\-'’]+$/u", $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$fieldLabel} may only contain letters, spaces, periods, apostrophes, and hyphens."];
        }

        // Require at least two words (given name and family name)
        $words = preg_split('/\s+/u', $collapsed);
        if (!$words || count($words) < 2) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter your full name (both first and last name)."];
        }

        // Check word lengths: at least one word must have 2+ characters
        $hasMultiCharWord = false;
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') >= 2) {
                $hasMultiCharWord = true;
                break;
            }
        }
        if (!$hasMultiCharWord) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
        }

        // Reject 3+ consecutive identical letters (e.g. 'aaaa', 'zzzzz')
        if (preg_match('/([\p{L}])\1{2,}/iu', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name without repeated letters."];
        }

        // Reject 5+ consecutive consonants (e.g. 'asdfghjk', 'sdfghjk', 'qwrtyp')
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/iu', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
        }

        // Reject common keyboard mash patterns
        $mashPattern = '/(?:asdf|sdfg|dfgh|fghj|ghjk|hjkl|qwerty|werty|ertyu|rtyui|tyuio|yuiop|zxcv|xcvb|cvbn|vbnm|qazw|wsxe|edcr)/i';
        if (preg_match($mashPattern, $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
        }

        // Common dummy words to reject
        $dummyWords = ['test', 'testing', 'dummy', 'sample', 'fake', 'asdf', 'qwerty', 'random', 'admin', 'unknown', 'none', 'null', 'na'];
        $cleanWords = [];
        foreach ($words as $w) {
            $lower = mb_strtolower(trim($w, " .\-'’"), 'UTF-8');
            if ($lower !== '') {
                $cleanWords[] = $lower;
                if (in_array($lower, $dummyWords, true)) {
                    return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
                }
            }
        }

        // Reject if all words are identical (e.g. 'Juan Juan' or 'Santos Santos' or 'Test Test')
        if (count($cleanWords) >= 2 && count(array_unique($cleanWords)) === 1) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter your full name (both first and last name)."];
        }

        // Check for vowels in words (Filipino, English, Spanish names)
        // Standard abbreviations/titles/surnames that may lack standard vowels:
        $acceptedVowelless = ['ma', 'st', 'sr', 'jr', 'dr', 'mr', 'ms', 'mrs', 'ng', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
        foreach ($words as $w) {
            // Split hyphenated subwords (e.g. 'Dela-Cruz' -> 'Dela', 'Cruz')
            $parts = preg_split('/[\-\'\’\.]+/u', $w);
            if (!$parts) continue;
            foreach ($parts as $part) {
                $cleanPart = mb_strtolower(trim($part), 'UTF-8');
                if (mb_strlen($cleanPart, 'UTF-8') < 2) {
                    continue; // Single-letter initials (e.g., 'J.', 'A.') are permitted
                }
                if (in_array($cleanPart, $acceptedVowelless, true)) {
                    continue;
                }
                // Vowels in Filipino/English/Spanish names: a, e, i, o, u, y, and accented vowels (á, é, í, ó, ú, ñ)
                if (!preg_match('/[aeiouyáéíóúàèìòùâêîôûñ]/iu', $cleanPart)) {
                    return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid real name."];
                }
            }
        }

        return ['valid' => true, 'value' => $collapsed, 'error' => null];
    }

    /**
     * Validate Philippine mobile numbers:
     * - Accept formats: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX
     * - Normalizes internally to canonical 11-digit format: 09XXXXXXXXX
     * - Reject letters, invalid special characters
     * - Reject numbers too short or too long
     * - Reject arbitrary patterns like 123456, 00000000000, abcdefghijk
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validatePhone(?string $rawPhone, bool $required = true): array
    {
        if ($rawPhone === null || trim($rawPhone) === '') {
            if ($required) {
                return ['valid' => false, 'value' => '', 'error' => 'Please enter a valid Philippine mobile number.'];
            }
            return ['valid' => true, 'value' => '', 'error' => null];
        }

        $trimmed = trim($rawPhone);

        // Reject obvious letters or disallowed characters immediately
        if (preg_match('/[^\d\s\-\+\(\)]/', $trimmed)) {
            return ['valid' => false, 'value' => $trimmed, 'error' => 'Please enter a valid Philippine mobile number.'];
        }

        // Strip allowed formatting characters (spaces, dashes, parentheses)
        $clean = (string) preg_replace('/[\s\-\(\)]/', '', $trimmed);

        // Standard Philippine mobile numbers:
        // - 09XXXXXXXXX (11 digits)
        // - +639XXXXXXXXX (13 chars)
        // - 639XXXXXXXXX (12 digits)
        if (!preg_match('/^(?:\+?63|0)9\d{9}$/', $clean)) {
            return ['valid' => false, 'value' => $clean, 'error' => 'Please enter a valid Philippine mobile number.'];
        }

        // Normalize to canonical 09XXXXXXXXX
        if (str_starts_with($clean, '+639')) {
            $normalized = '0' . substr($clean, 3);
        } elseif (str_starts_with($clean, '639')) {
            $normalized = '0' . substr($clean, 2);
        } else {
            $normalized = $clean;
        }

        // Reject arbitrary repeating patterns (e.g. 09000000000)
        if (preg_match('/^09(\d)\1{8}$/', $normalized)) {
            return ['valid' => false, 'value' => $normalized, 'error' => 'Please enter a valid Philippine mobile number.'];
        }

        return ['valid' => true, 'value' => $normalized, 'normalized' => $normalized, 'error' => null];
    }

    /**
     * Validate Email Address:
     * - RFC format
     * - Max length 100
     * - No HTML/script injection
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validateEmail(?string $rawEmail, bool $required = true): array
    {
        if ($rawEmail === null || trim($rawEmail) === '') {
            if ($required) {
                return ['valid' => false, 'value' => '', 'error' => 'A valid email address is required.'];
            }
            return ['valid' => true, 'value' => '', 'error' => null];
        }

        $trimmed = trim($rawEmail);

        if (strlen($trimmed) > 100) {
            return ['valid' => false, 'value' => $trimmed, 'error' => 'Email address is too long (maximum 100 characters).'];
        }

        // Reject obvious HTML tags or angle brackets
        if (preg_match('/[<>"\']/', $trimmed)) {
            return ['valid' => false, 'value' => $trimmed, 'error' => 'Please enter a valid email address.'];
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'value' => $trimmed, 'error' => 'Please enter a valid email address.'];
        }

        return ['valid' => true, 'value' => strtolower($trimmed), 'error' => null];
    }

    /**
     * Canonical Philippine mobile number (09XXXXXXXXX) used for duplicate /
     * identity comparisons, matching the normalization validatePhone() applies.
     */
    public static function normalizePhoneString(string $raw): string
    {
        if (trim($raw) === '') {
            return '';
        }
        $clean = (string) preg_replace('/[^0-9]/', '', $raw);
        if (str_starts_with($clean, '63') && strlen($clean) === 12) {
            $clean = '0' . substr($clean, 2);
        }
        return $clean;
    }

    /**
     * SQL expression that folds a stored phone column into the same canonical
     * 09XXXXXXXXX form produced by normalizePhoneString() — strips spaces,
     * dashes and parentheses and converts +63/639 prefixes to a leading 0.
     *
     * Usage: ... WHERE " . PassengerValidator::phoneColumnExpr('phone') . " = ?
     */
    public static function phoneColumnExpr(string $column = 'phone'): string
    {
        $c = "REPLACE(REPLACE(REPLACE(REPLACE($column,'+63','0'),' ',''),'-',''),'(','')";
        return "CASE WHEN LENGTH($c) = 12 AND LEFT($c, 2) = '63' THEN CONCAT('0', SUBSTRING($c, 3)) ELSE $c END";
    }

    /**
     * Canonical email (trimmed + lowercased) for duplicate/identity comparisons.
     */
    public static function normalizeEmailString(string $raw): string
    {
        return strtolower(trim($raw));
    }

    /**
     * Whether a table/column exists in the connected schema (cached). Used to
     * keep duplicate checks schema-tolerant on deployments that have not yet
     * added the one-time `contact_number` column.
     */
    public static function tableHasColumn(\PDO $conn, string $table, string $column): bool
    {
        static $cache = array();

        $key = strtolower($table . '.' . $column);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $safeTable = str_replace('`', '', $table);
            $stmt = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE " . $conn->quote($column));
            $found = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            $found = false;
        }

        $cache[$key] = $found;

        return $found;
    }

    /**
     * Validate Password:
     * - Required
     * - Minimum 8 characters
     * - Maximum 72 characters
     * - Reject empty/whitespace-only
     * - Confirm password must match if provided
     * - Does not expose the password in error messages
     *
     * @return array{valid: bool, error: ?string}
     */
    public static function validatePassword(
        ?string $password,
        ?string $confirmPassword = null,
        int $minLength = 8,
        int $maxLength = 72
    ): array {
        if ($password === null || $password === '' || trim($password) === '') {
            return ['valid' => false, 'error' => 'Password is required and cannot be empty or whitespace only.'];
        }

        $len = strlen($password);
        if ($len < $minLength) {
            return ['valid' => false, 'error' => "Password must be at least {$minLength} characters long."];
        }

        if ($len > $maxLength) {
            return ['valid' => false, 'error' => "Password cannot exceed {$maxLength} characters."];
        }

        if ($confirmPassword !== null) {
            if (!hash_equals($password, $confirmPassword)) {
                return ['valid' => false, 'error' => 'Passwords do not match.'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate Passenger Type:
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validatePassengerType(?string $rawType): array
    {
        $trimmed = trim((string) ($rawType ?? ''));
        if ($trimmed === '') {
            return ['valid' => true, 'value' => 'Regular', 'error' => null];
        }

        foreach (self::PASSENGER_TYPES as $type => $info) {
            if (strcasecmp($trimmed, $type) === 0) {
                return ['valid' => true, 'value' => $type, 'error' => null];
            }
        }

        return ['valid' => false, 'value' => 'Regular', 'error' => 'Please select a valid passenger type.'];
    }

    /**
     * Whether a passenger type requires an ID.
     */
    public static function requiresId(string $passengerType): bool
    {
        foreach (self::PASSENGER_TYPES as $type => $info) {
            if (strcasecmp($passengerType, $type) === 0) {
                return (bool) $info['requires_id'];
            }
        }
        return false;
    }

    /**
     * Get user-friendly label for passenger type ID.
     */
    public static function idLabelForType(string $passengerType): string
    {
        foreach (self::PASSENGER_TYPES as $type => $info) {
            if (strcasecmp($passengerType, $type) === 0) {
                return (string) $info['label'];
            }
        }
        return 'ID Number';
    }

    /**
     * Normalize passenger type to canonical name.
     */
    public static function normalizePassengerType(?string $rawType): string
    {
        $trimmed = trim((string) ($rawType ?? ''));
        if ($trimmed === '') {
            return 'Regular';
        }

        foreach (self::PASSENGER_TYPES as $type => $info) {
            if (strcasecmp($trimmed, $type) === 0) {
                return $type;
            }
        }

        return 'Regular';
    }

    /**
     * Validate ID Type selection against selected Passenger Type.
     *
     * Rules:
     * - Regular: no ID allowed or required (returns valid with null value).
     * - Discount: must provide an accepted ID type.
     * - Strictly reject generic/unrelated IDs (Driver's License, Passport, Barangay, Postal, UMID).
     * - Student: only accepts 'student-id'.
     * - Senior Citizen / Senior: only accepts 'senior-citizen-id' or 'philsys-id'.
     * - PWD: only accepts 'pwd-id'.
     * - Teacher: only accepts 'teacher-id'.
     * - Nurse: only accepts 'prc-id'.
     * - Cooperative / RPMVFMPC Member: only accepts 'coop-beneficiary-id'.
     *
     * @return array{valid: bool, value: ?string, error: ?string}
     */
    public static function validatePassengerIdType(string $passengerType, ?string $rawIdType): array
    {
        $normType = self::normalizePassengerType($passengerType);
        $trimmedIdType = strtolower(trim((string) ($rawIdType ?? '')));

        if ($normType === 'Regular') {
            return ['valid' => true, 'value' => null, 'error' => null];
        }

        if ($trimmedIdType === '') {
            $expectedLabel = self::idLabelForType($normType);
            return [
                'valid' => false,
                'value' => null,
                'error' => "Please select or provide an accepted ID ({$expectedLabel}) for {$normType} verification."
            ];
        }

        // Check if ID type is completely unsupported (e.g. driver's license, passport, barangay, postal, umid)
        if (!array_key_exists($trimmedIdType, self::SUPPORTED_ID_TYPES)) {
            return [
                'valid' => false,
                'value' => $trimmedIdType,
                'error' => "This ID type is not supported for passenger discount verification. Please provide an accepted discount ID."
            ];
        }

        // Check if ID type is allowed for this specific passenger type
        $allowed = self::PASSENGER_TYPE_ID_MAP[$normType] ?? [];
        if (!in_array($trimmedIdType, $allowed, true)) {
            $contextErrors = [
                'Student'         => 'Student accounts only accept a valid Student ID.',
                'Senior Citizen'  => 'Senior Citizen accounts only accept a Senior Citizen ID or PhilSys / National ID.',
                'Senior'          => 'Senior Citizen accounts only accept a Senior Citizen ID or PhilSys / National ID.',
                'PWD'             => 'PWD accounts only accept a valid PWD ID.',
                'Teacher'         => 'Teacher accounts only accept a valid Teacher / School ID.',
                'Nurse'           => 'Nurse accounts only accept a valid PRC ID.',
                'Special'         => 'Special discount accounts only accept a Teacher / School ID or PRC ID.',
                'RPMVFMPC Member' => 'Cooperative Member accounts only accept a valid Cooperative Beneficiary ID.',
                'Cooperative'     => 'Cooperative Member accounts only accept a valid Cooperative Beneficiary ID.',
            ];
            $errorMsg = $contextErrors[$normType] ?? "Selected ID type is not valid for {$normType}.";

            return [
                'valid' => false,
                'value' => $trimmedIdType,
                'error' => $errorMsg
            ];
        }

        return ['valid' => true, 'value' => $trimmedIdType, 'error' => null];
    }

    /**
     * Validate ID Number (Student ID, Senior ID, PWD ID, etc.):
     * - Required when passenger type requires an ID
     * - Not required for Regular
     * - Allows alphanumeric, spaces, periods, hyphens, forward slashes
     * - Length between 3 and 50 characters
     * - Rejects HTML/script tags or malicious symbols
     * - Must contain numeric digits (at least 3 digits) for discount verification
     * - Rejects purely alphabetic strings (e.g. 'asdfghjk', 'student', 'letters')
     * - Rejects repetitive dummy digits (e.g. '000000', '111111')
     * - Rejects sequential dummy digits (e.g. '123456', '123456789')
     * - Rejects keyboard mash sequences
     * - Preserves valid IDs exactly as entered (consecutive extra spaces collapsed)
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validateIdNumber(?string $rawId, string $passengerType, bool $strictRequired = true): array
    {
        $requiresId = self::requiresId($passengerType) || (strcasecmp(trim($passengerType), 'Regular') !== 0 && $strictRequired);
        $trimmed = trim((string) ($rawId ?? ''));
        $collapsed = (string) preg_replace('/\s+/u', ' ', $trimmed);

        if (!$requiresId) {
            // Not required for Regular: if empty, perfectly valid
            if ($collapsed === '') {
                return ['valid' => true, 'value' => '', 'error' => null];
            }
        } else {
            // Required for discount types
            if ($strictRequired && $collapsed === '') {
                $label = self::idLabelForType($passengerType);
                return ['valid' => false, 'value' => '', 'error' => "Please enter your valid {$label}."];
            }
        }

        if ($collapsed === '') {
            return ['valid' => true, 'value' => '', 'error' => null];
        }

        $label = self::idLabelForType($passengerType);
        $length = mb_strlen($collapsed, 'UTF-8');
        if ($length < 3) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$label} must be at least 3 characters."];
        }

        if ($length > 50) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$label} is too long (maximum 50 characters)."];
        }

        // Reject script tags, HTML tags, quotes, or dangerous shell/SQL characters
        if (preg_match('/[<>"`\$\\\\]/', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid {$label} without special characters."];
        }

        // Allow alphanumeric, spaces, hyphens, periods, and slashes
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "{$label} may only contain letters, numbers, hyphens, slashes, and periods."];
        }

        // Reject keyboard mash
        if (preg_match('/(?:asdf|ghjk|qwerty|zxcv)/i', $collapsed)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid {$label} without random keyboard letters."];
        }

        // Reject dummy words (e.g. 'student', 'senior', 'pwd', 'id number')
        $lower = strtolower($collapsed);
        $dummyIds = ['student', 'senior', 'pwd', 'special', 'id', 'id number', 'none', 'na', 'n/a', 'test', 'sample', 'asdf', 'qwerty', 'letters', 'random'];
        if (in_array($lower, $dummyIds, true)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter your actual {$label}."];
        }

        // Require numeric digits in ID numbers (Philippine institutional IDs always contain digits)
        $digitMatches = [];
        preg_match_all('/\d/', $collapsed, $digitMatches);
        $digitCount = isset($digitMatches[0]) ? count($digitMatches[0]) : 0;

        if ($digitCount === 0) {
            return [
                'valid' => false,
                'value' => $collapsed,
                'error' => "{$label} must contain numeric digits (e.g., 2024-12345 or OSCA-12345). Letters alone are not accepted."
            ];
        }

        if ($digitCount < 3) {
            return [
                'valid' => false,
                'value' => $collapsed,
                'error' => "{$label} must contain at least 3 numeric digits."
            ];
        }

        // Reject repeated dummy digits (e.g. 000000, 111111)
        $digitsOnly = (string) preg_replace('/\D/', '', $collapsed);
        if (preg_match('/^(\d)\1{3,}$/', $digitsOnly)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid, authentic {$label}."];
        }

        // Reject purely sequential digits when no letters are present (e.g. '123456', '123456789')
        $lettersOnly = (string) preg_replace('/[^A-Za-z]/', '', $collapsed);
        if ($lettersOnly === '' && in_array($digitsOnly, ['123456', '1234567', '12345678', '123456789', '0123456', '987654321'], true)) {
            return ['valid' => false, 'value' => $collapsed, 'error' => "Please enter a valid, authentic {$label}."];
        }

        return ['valid' => true, 'value' => $collapsed, 'error' => null];
    }

    /**
     * Validate Date of Birth:
     * - Must be valid calendar date (YYYY-MM-DD)
     * - Not in the future
     * - On or after 1900-01-01
     * - Age <= 120
     *
     * @return array{valid: bool, value: ?string, error: ?string}
     */
    public static function validateDateOfBirth(?string $rawDob, bool $required = true): array
    {
        if ($rawDob === null || trim($rawDob) === '') {
            if ($required) {
                return ['valid' => false, 'value' => null, 'error' => 'Date of birth is required.'];
            }
            return ['valid' => true, 'value' => null, 'error' => null];
        }

        $normalized = str_replace('/', '-', trim($rawDob));
        $dob = DateTime::createFromFormat('!Y-m-d', $normalized);

        if (!$dob || $dob->format('Y-m-d') !== $normalized) {
            return ['valid' => false, 'value' => null, 'error' => 'Date of birth must be a valid date in YYYY-MM-DD format.'];
        }

        $today = new DateTime('today');
        $earliest = new DateTime('1900-01-01');

        if ($dob > $today) {
            return ['valid' => false, 'value' => null, 'error' => 'Date of birth cannot be in the future.'];
        }

        if ($dob < $earliest) {
            return ['valid' => false, 'value' => null, 'error' => 'Date of birth must be on or after 1900-01-01.'];
        }

        if ($dob->diff($today)->y > 120) {
            return ['valid' => false, 'value' => null, 'error' => 'Please enter a valid date of birth.'];
        }

        return ['valid' => true, 'value' => $dob->format('Y-m-d'), 'error' => null];
    }

    /**
     * Validate Geographic Coordinates:
     *
     * @return array{valid: bool, lat: ?float, lng: ?float, error: ?string}
     */
    public static function validateCoordinates($rawLat, $rawLng): array
    {
        if ($rawLat === null || $rawLng === null || $rawLat === '' || $rawLng === '') {
            return ['valid' => true, 'lat' => null, 'lng' => null, 'error' => null];
        }

        if (!is_numeric($rawLat) || !is_numeric($rawLng)) {
            return ['valid' => false, 'lat' => null, 'lng' => null, 'error' => 'Coordinates must be valid numbers.'];
        }

        $lat = (float) $rawLat;
        $lng = (float) $rawLng;

        if (!is_finite($lat) || !is_finite($lng)) {
            return ['valid' => false, 'lat' => null, 'lng' => null, 'error' => 'Coordinates must be finite numbers.'];
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return ['valid' => false, 'lat' => null, 'lng' => null, 'error' => 'Coordinates are outside valid geographic range.'];
        }

        return ['valid' => true, 'lat' => $lat, 'lng' => $lng, 'error' => null];
    }

    /**
     * Validate Payment Method:
     *
     * @return array{valid: bool, value: string, error: ?string}
     */
    public static function validatePaymentMethod(?string $rawMethod, array $allowed = ['cash', 'gcash', 'hybrid']): array
    {
        $clean = strtolower(trim((string) ($rawMethod ?? '')));
        if (!in_array($clean, $allowed, true)) {
            return ['valid' => false, 'value' => '', 'error' => 'Please choose a valid payment method.'];
        }
        return ['valid' => true, 'value' => $clean, 'error' => null];
    }

    /**
     * Sanitize general text (e.g. address, search terms) by trimming, collapsing
     * excess whitespace, and limiting length without corrupting content.
     */
    public static function sanitizeString(?string $str, int $maxLength = 255): string
    {
        if ($str === null) {
            return '';
        }
        $trimmed = trim($str);
        $collapsed = (string) preg_replace('/\s+/u', ' ', $trimmed);
        if (mb_strlen($collapsed, 'UTF-8') > $maxLength) {
            $collapsed = mb_substr($collapsed, 0, $maxLength, 'UTF-8');
        }
        return $collapsed;
    }

    /**
     * Safe HTML output escaping helper.
     */
    public static function escape(?string $val): string
    {
        return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
