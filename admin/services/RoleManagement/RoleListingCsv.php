<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

/**
 * CSV shaping for the Role Management directory export.
 *
 * Pure helpers so the column set and the spreadsheet-safety rule can be
 * verified without a database: the endpoint only feeds real directory rows in.
 */
final class RoleListingCsv
{
    /**
     * @return string[]
     */
    public static function headers(): array
    {
        return [
            'User ID',
            'Full Name',
            'Role',
            'Email',
            'Contact',
            'Status',
            'Department',
            'Assignment',
            'Date Created',
        ];
    }

    /**
     * Map one normalized directory row to export cells.
     *
     * @param array<string,mixed> $row
     * @return string[]
     */
    public static function row(array $row): array
    {
        return array_map(
            static fn ($value): string => self::neutralize($value),
            [
                (string)($row['user_id'] ?? ''),
                (string)($row['name'] ?? ''),
                (string)($row['role_label'] ?? ''),
                (string)($row['email'] ?? ''),
                (string)($row['contact'] ?? ''),
                (string)($row['status_label'] ?? ''),
                (string)($row['department'] ?? ''),
                (string)($row['assignment'] ?? ''),
                (string)($row['created_at'] ?? ''),
            ]
        );
    }

    /**
     * Prefix values that a spreadsheet would treat as a formula so they are
     * displayed as text instead of being executed.
     */
    public static function neutralize($value): string
    {
        $value = (string)$value;
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    }
}
