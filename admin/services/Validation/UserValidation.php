<?php

/**
 * Shared server-side validation for the admin Create/Edit User flows
 * (Driver, Inspector, Dispatcher, Finance, Admin / Super Admin).
 *
 * Frontend validation is UX only; every rule here is enforced on the
 * server so it cannot be bypassed with DevTools or direct requests.
 * All duplicate checks use prepared statements.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

if (!function_exists('uv_trim')) {
    function uv_trim($value): string
    {
        return trim((string) ($value ?? ''));
    }
}

if (!function_exists('uv_required')) {
    function uv_required($value, string $label): ?string
    {
        return uv_trim($value) === '' ? $label . ' is required.' : null;
    }
}

if (!function_exists('uv_maxlen')) {
    function uv_maxlen($value, int $max, string $label): ?string
    {
        $value = uv_trim($value);
        if ($value !== '' && mb_strlen($value) > $max) {
            return $label . ' must not exceed ' . $max . ' characters.';
        }
        return null;
    }
}

if (!function_exists('uv_name')) {
    /**
     * Human name rule shared by every account type: letters (Unicode),
     * spaces, periods, commas, hyphens, and apostrophes. Periods and commas
     * are allowed because real names carry middle initials and suffixes such
     * as "D.", "Jr.", and "Cruz, Jr.". Punctuation must follow a letter so
     * junk like "Juan.." or "Cruz--" is still rejected; values are stored
     * through prepared statements and escaped on output regardless.
     */
    function uv_name($value, string $label, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? $label . ' is required.' : null;
        }
        if (mb_strlen($value) < 2) {
            return $label . ' must be at least 2 characters.';
        }
        if (mb_strlen($value) > 100) {
            return $label . ' must not exceed 100 characters.';
        }
        if (!preg_match("/^[\p{L}][\p{L} .,'\-]*$/u", $value)) {
            return $label . ' may only contain letters, spaces, periods, commas, hyphens, and apostrophes.';
        }
        if (preg_match("/(^|[^\p{L}])[.,'\-]/u", $value)) {
            return $label . ' uses punctuation incorrectly (for example "D.", "Jr.", or "Cruz, Jr.").';
        }
        if (preg_match("/[\s,'\-]$/u", $value)) {
            return $label . ' cannot end with a space or punctuation.';
        }
        return null;
    }
}

if (!function_exists('uv_username')) {
    function uv_username($value, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? 'Username is required.' : null;
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $value)) {
            return 'Username must be 3-30 characters and use only letters, numbers, dots, underscores, or hyphens.';
        }
        return null;
    }
}

if (!function_exists('uv_email')) {
    function uv_email($value, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? 'Email is required.' : null;
        }
        if (strlen($value) > 150) {
            return 'Email must not exceed 150 characters.';
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        return null;
    }
}

if (!function_exists('uv_phone')) {
    function uv_phone($value, string $label = 'Contact number', bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? $label . ' is required.' : null;
        }
        if (strlen($value) > 20) {
            return $label . ' must not exceed 20 characters.';
        }
        $clean = preg_replace('/[\s\-()]/', '', $value);
        if (!preg_match('/^09\d{9}$/', $clean) && !preg_match('/^(\+63|63)9\d{9}$/', $clean)) {
            return $label . ' must be a valid Philippine mobile number (e.g., 09171234567 or +639171234567).';
        }
        return null;
    }
}

if (!function_exists('uv_password')) {
    function uv_password($value, string $label = 'Password', bool $required = true): ?string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return $required ? $label . ' is required.' : null;
        }
        if (strlen($value) < 8) {
            return $label . ' must be at least 8 characters.';
        }
        if (strlen($value) > 72) {
            return $label . ' must not exceed 72 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            return $label . ' must include at least one letter and one number.';
        }
        return null;
    }
}

if (!function_exists('uv_password_confirm')) {
    function uv_password_confirm(array $data, string $password): ?string
    {
        $confirm = uv_trim($data['password_confirm'] ?? $data['confirm_password'] ?? '');
        if ($confirm !== '' && !hash_equals($password, $confirm)) {
            return 'Password confirmation does not match.';
        }
        return null;
    }
}

if (!function_exists('uv_date')) {
    function uv_date($value, string $label): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $label . ' is required.';
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            return $label . ' is not a valid date.';
        }
        return null;
    }
}

if (!function_exists('uv_int_range')) {
    function uv_int_range($value, int $min, int $max, string $label, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? $label . ' is required.' : null;
        }
        if (!preg_match('/^\d+$/', $value)) {
            return $label . ' must be a whole number.';
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            return $label . ' must be between ' . $min . ' and ' . $max . '.';
        }
        return null;
    }
}

if (!function_exists('uv_enum')) {
    function uv_enum($value, array $allowed, string $label, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? 'Select a valid ' . $label . '.' : null;
        }
        if (!in_array($value, $allowed, true)) {
            return 'Select a valid ' . $label . '.';
        }
        return null;
    }
}

if (!function_exists('uv_config')) {
    /**
     * Configurable option lists (admin/config/role_options.php).
     */
    function uv_config(): array
    {
        static $config = null;

        if ($config === null) {
            $loaded = @require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/role_options.php';
            $config = is_array($loaded) ? $loaded : [];
        }

        return $config;
    }
}

if (!function_exists('uv_date_not_future')) {
    /**
     * Optional calendar date that may not be in the future.
     */
    function uv_date_not_future($value, string $label): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return null;
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            return $label . ' is not a valid date.';
        }
        if ($parsed->format('Y-m-d') > date('Y-m-d')) {
            return $label . ' cannot be in the future.';
        }
        return null;
    }
}

if (!function_exists('uv_supervisor')) {
    /**
     * Optional supervisor reference: must be an existing user account.
     */
    function uv_supervisor($value): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return null;
        }
        if (!ctype_digit($value)) {
            return 'Select a valid supervisor.';
        }
        $conn = Database::tryGet();
        if ($conn === null) {
            return null;
        }
        try {
            $stmt = $conn->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$value]);
            return $stmt->fetchColumn() ? null : 'Select a valid supervisor.';
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('uv_employment_fields')) {
    /**
     * Shared checks for the structured employment fields.
     *
     * @return array<int,?string>
     */
    function uv_employment_fields(array $data): array
    {
        $config = uv_config();

        return [
            uv_date_not_future($data['hired_date'] ?? '', 'Date hired'),
            uv_enum($data['sex'] ?? '', $config['sex'] ?? [], 'sex', false),
            uv_enum($data['employment_type'] ?? '', $config['employment_types'] ?? [], 'employment type', false),
            uv_enum($data['employment_status'] ?? '', $config['employment_statuses'] ?? [], 'employment status', false),
            uv_maxlen($data['address'] ?? '', 500, 'Address'),
            uv_supervisor($data['supervisor_id'] ?? ''),
        ];
    }
}

if (!function_exists('uv_license_number')) {
    function uv_license_number($value, bool $required = true): ?string
    {
        $value = uv_trim($value);
        if ($value === '') {
            return $required ? 'License number is required.' : null;
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,29}$/', $value)) {
            return 'License number must be 5-30 characters using letters, numbers, or hyphens.';
        }
        return null;
    }
}

if (!function_exists('uv_duplicate_exists')) {
    function uv_duplicate_exists(string $table, string $column, $value, ?int $excludeId = null, string $idColumn = 'id'): bool
    {
        $value = uv_trim($value);
        if ($value === '') {
            return false;
        }

        $allowed = [
            'users' => ['username', 'email', 'employee_id'],
            'drivers' => ['email', 'license_number', 'driver_id'],
            'driver_accounts' => ['username', 'driver_id'],
            'inspectors' => ['email'],
            'inspector_accounts' => ['inspector_id'],
        ];

        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return false;
        }

        $idColumn = preg_replace('/[^a-z_]/', '', strtolower($idColumn));
        if ($idColumn === '') {
            $idColumn = 'id';
        }

        $conn = Database::tryGet();
        if ($conn === null) {
            return false;
        }

        $sql = 'SELECT 1 FROM `' . $table . '`'
            . ' WHERE LOWER(CONVERT(`' . $column . '` USING utf8mb4) COLLATE utf8mb4_general_ci) = LOWER(?)';
        $params = [$value];

        if ($excludeId !== null) {
            $sql .= ' AND `' . $idColumn . '` <> ?';
            $params[] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('uv_bus_exists')) {
    function uv_bus_exists($busId): bool
    {
        $busId = uv_trim($busId);
        if ($busId === '') {
            return true;
        }
        $conn = Database::tryGet();
        if ($conn === null) {
            return true;
        }
        try {
            $stmt = $conn->prepare('SELECT 1 FROM buses WHERE CONVERT(bus_id USING utf8mb4) COLLATE utf8mb4_general_ci = ? LIMIT 1');
            $stmt->execute([$busId]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return true;
        }
    }
}

/* ------------------------- per-flow validators ------------------------- */

if (!function_exists('uv_validate_driver_create')) {
    function uv_validate_driver_create(array $data): ?string
    {
        $fullName = uv_trim($data['full_name'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_number'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $licenseNumber = uv_trim($data['license_number'] ?? '');
        $licenseType = uv_trim($data['license_type'] ?? '');
        $experience = $data['experience_years'] ?? '';
        $busId = uv_trim($data['bus_id'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name'),
            uv_email($email),
            uv_duplicate_exists('drivers', 'email', $email) ? 'Email is already registered to another driver.' : null,
            uv_phone($contact, 'Contact number'),
            uv_password($password, 'Password', true),
            uv_password_confirm($data, $password),
            uv_license_number($licenseNumber),
            uv_duplicate_exists('drivers', 'license_number', $licenseNumber) ? 'License number is already in use.' : null,
            uv_enum($licenseType, ['Professional', 'Non-Professional'], 'license type'),
            uv_int_range($experience, 0, 80, 'Driving experience'),
            uv_maxlen($data['assigned_buses'] ?? '', 100, 'Assigned buses'),
            uv_bus_exists($busId) ? null : 'Select a valid bus.',
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['availability'] ?? '', uv_config()['availability'] ?? [], 'availability', false),
                uv_enum($data['work_shift'] ?? '', uv_config()['work_shifts'] ?? [], 'work shift', false),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_driver_update')) {
    function uv_validate_driver_update(array $data): ?string
    {
        $id = uv_trim($data['id'] ?? '');
        if ($id === '' || !ctype_digit($id)) {
            return 'Invalid driver reference.';
        }
        $id = (int) $id;

        $fullName = uv_trim($data['full_name'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_number'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $licenseNumber = uv_trim($data['license_number'] ?? '');
        $licenseType = uv_trim($data['license_type'] ?? '');
        $experience = $data['experience_years'] ?? '';
        $status = uv_trim($data['status'] ?? '');
        $approval = uv_trim($data['approval_status'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name', false),
            uv_email($email, false),
            uv_duplicate_exists('drivers', 'email', $email, $id) ? 'Email is already registered to another driver.' : null,
            uv_phone($contact, 'Contact number', false),
            uv_password($password, 'New password', false),
            uv_license_number($licenseNumber, false),
            uv_duplicate_exists('drivers', 'license_number', $licenseNumber, $id) ? 'License number is already in use.' : null,
            uv_enum($licenseType, ['Professional', 'Non-Professional'], 'license type', false),
            uv_int_range($experience, 0, 80, 'Driving experience', false),
            uv_enum($status, ['Active', 'Inactive', 'Pending', 'Archived'], 'status', false),
            uv_enum($approval, ['Pending', 'Approved', 'Rejected'], 'approval status', false),
            uv_maxlen($data['assigned_buses'] ?? '', 100, 'Assigned buses'),
            uv_maxlen($data['routes'] ?? '', 255, 'Routes'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['availability'] ?? '', uv_config()['availability'] ?? [], 'availability', false),
                uv_enum($data['work_shift'] ?? '', uv_config()['work_shifts'] ?? [], 'work shift', false),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_inspector_create')) {
    function uv_validate_inspector_create(array $data): ?string
    {
        $name = uv_trim($data['inspector_name'] ?? '');
        $identity = uv_trim($data['inspector_identity'] ?? '');
        $department = uv_trim($data['department'] ?? '');
        $experience = $data['experience_years'] ?? '';
        $assignment = uv_trim($data['operational_assignment'] ?? '');
        $routes = uv_trim($data['assigned_routes'] ?? '');
        $contact = uv_trim($data['contact_number'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $inspectorId = uv_trim($data['inspector_id'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $password = (string) ($data['password'] ?? '');

        foreach ([
            uv_name($name, 'Inspector name'),
            uv_required($identity, 'Inspector identity'),
            uv_maxlen($identity, 50, 'Inspector identity'),
            uv_required($department, 'Department'),
            uv_maxlen($department, 100, 'Department'),
            uv_int_range($experience, 0, 80, 'Experience years'),
            uv_maxlen($assignment, 150, 'Operational assignment'),
            uv_required($routes, 'Assigned routes'),
            uv_maxlen($routes, 100, 'Assigned routes'),
            uv_phone($contact, 'Contact number'),
            uv_email($email),
            uv_duplicate_exists('inspectors', 'email', $email) ? 'Email is already registered to another inspector.' : null,
            uv_enum($role, ['inspector', 'dispatcher', 'admin'], 'role'),
            uv_password($password, 'Password', true),
            uv_password_confirm($data, $password),
            $inspectorId !== '' && !preg_match('/^(INS|DIS)-\d{3,6}$/', $inspectorId)
                ? 'Inspector ID has an invalid format.'
                : null,
            $inspectorId !== '' && $role === 'dispatcher' && strpos($inspectorId, 'DIS-') !== 0
                ? 'Dispatcher ID must start with DIS-.'
                : null,
            $inspectorId !== '' && $role !== 'dispatcher' && strpos($inspectorId, 'INS-') !== 0
                ? 'Inspector ID must start with INS-.'
                : null,
            uv_duplicate_exists('inspector_accounts', 'inspector_id', $inspectorId) ? 'Inspector ID is already in use.' : null,
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['availability'] ?? '', uv_config()['availability'] ?? [], 'availability', false),
                uv_enum($data['work_shift'] ?? '', uv_config()['work_shifts'] ?? [], 'work shift', false),
                uv_maxlen($data['assigned_area'] ?? '', 120, 'Assigned bus or area'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_inspector_update')) {
    function uv_validate_inspector_update(array $data): ?string
    {
        $pk = uv_trim($data['inspector_pk'] ?? '');
        if ($pk === '' || !ctype_digit($pk)) {
            return 'Invalid inspector reference.';
        }
        $pk = (int) $pk;

        $name = uv_trim($data['inspector_name'] ?? '');
        $identity = uv_trim($data['inspector_identity'] ?? '');
        $department = uv_trim($data['department'] ?? '');
        $experience = $data['experience_years'] ?? '';
        $assignment = uv_trim($data['operational_assignment'] ?? '');
        $routes = uv_trim($data['assigned_routes'] ?? '');
        $contact = uv_trim($data['contact_number'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $password = (string) ($data['password'] ?? '');

        foreach ([
            uv_name($name, 'Inspector name', false),
            uv_maxlen($identity, 50, 'Inspector identity'),
            uv_maxlen($department, 100, 'Department'),
            uv_int_range($experience, 0, 80, 'Experience years', false),
            uv_maxlen($assignment, 150, 'Operational assignment'),
            uv_maxlen($routes, 100, 'Assigned routes'),
            uv_phone($contact, 'Contact number', false),
            uv_email($email, false),
            uv_duplicate_exists('inspectors', 'email', $email, $pk, 'inspector_pk') ? 'Email is already registered to another inspector.' : null,
            uv_enum($role, ['inspector', 'dispatcher', 'admin'], 'role'),
            uv_password($password, 'New password', false),
            uv_password_confirm($data, $password),
            uv_maxlen($data['additional_notes'] ?? '', 500, 'Additional notes'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['availability'] ?? '', uv_config()['availability'] ?? [], 'availability', false),
                uv_enum($data['work_shift'] ?? '', uv_config()['work_shifts'] ?? [], 'work shift', false),
                uv_maxlen($data['assigned_area'] ?? '', 120, 'Assigned bus or area'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_finance_create')) {
    function uv_validate_finance_create(array $data): ?string
    {
        $fullName = uv_trim($data['full_name'] ?? '');
        $username = uv_trim($data['username'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_no'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $status = uv_trim($data['status'] ?? '');
        $shift = uv_trim($data['shift_status'] ?? '');
        $access = uv_trim($data['access_level'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name'),
            uv_username($username),
            uv_duplicate_exists('users', 'username', $username) ? 'Username is already taken.' : null,
            uv_email($email),
            uv_duplicate_exists('users', 'email', $email) ? 'Email is already registered.' : null,
            uv_phone($contact, 'Contact number'),
            uv_password($password, 'Password', true),
            uv_password_confirm($data, $password),
            uv_enum($role, ['finance_manager', 'finance_officer'], 'role'),
            uv_enum($status, ['active', 'inactive', 'pending'], 'status'),
            uv_enum($shift, ['', 'Day Shift', 'Night Shift', 'Flexible'], 'shift status', false),
            uv_enum($access, ['1', '2', '3', '4'], 'access level'),
            uv_maxlen($data['assigned_area'] ?? '', 100, 'Assigned area'),
            uv_maxlen($data['shift_schedule'] ?? '', 100, 'Shift schedule'),
            uv_maxlen($data['address'] ?? '', 255, 'Address'),
            uv_maxlen($data['bio'] ?? '', 500, 'Bio'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['position_title'] ?? '', uv_config()['finance_positions'] ?? [], 'finance position', false),
                uv_maxlen($data['assigned_office'] ?? '', 120, 'Assigned office'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_finance_update')) {
    function uv_validate_finance_update(array $data): ?string
    {
        $id = uv_trim($data['id'] ?? '');
        if ($id === '' || !ctype_digit($id)) {
            return 'Invalid employee reference.';
        }
        $id = (int) $id;

        $fullName = uv_trim($data['full_name'] ?? '');
        $username = uv_trim($data['username'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_no'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $status = uv_trim($data['status'] ?? '');
        $shift = uv_trim($data['shift_status'] ?? '');
        $access = uv_trim($data['access_level'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name', false),
            uv_username($username, false),
            uv_duplicate_exists('users', 'username', $username, $id) ? 'Username is already taken.' : null,
            uv_email($email, false),
            uv_duplicate_exists('users', 'email', $email, $id) ? 'Email is already registered.' : null,
            uv_phone($contact, 'Contact number', false),
            uv_password($password, 'New password', false),
            uv_password_confirm($data, $password),
            uv_enum($role, ['finance_manager', 'finance_officer'], 'role'),
            uv_enum($status, ['active', 'inactive', 'pending'], 'status'),
            uv_enum($shift, ['', 'Day Shift', 'Night Shift', 'Flexible'], 'shift status', false),
            uv_enum($access, ['1', '2', '3', '4'], 'access level'),
            uv_maxlen($data['assigned_area'] ?? '', 100, 'Assigned area'),
            uv_maxlen($data['shift_schedule'] ?? '', 100, 'Shift schedule'),
            uv_maxlen($data['address'] ?? '', 255, 'Address'),
            uv_maxlen($data['bio'] ?? '', 500, 'Bio'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['position_title'] ?? '', uv_config()['finance_positions'] ?? [], 'finance position', false),
                uv_maxlen($data['assigned_office'] ?? '', 120, 'Assigned office'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_admin_create')) {
    function uv_validate_admin_create(array $data): ?string
    {
        $fullName = uv_trim($data['full_name'] ?? '');
        $username = uv_trim($data['username'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_no'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $status = uv_trim($data['status'] ?? '');
        $shift = uv_trim($data['shift_status'] ?? '');
        $access = uv_trim($data['access_level'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name'),
            uv_username($username),
            uv_duplicate_exists('users', 'username', $username) ? 'Username is already taken.' : null,
            uv_email($email),
            uv_duplicate_exists('users', 'email', $email) ? 'Email is already registered.' : null,
            uv_phone($contact, 'Contact number'),
            uv_password($password, 'Password', true),
            uv_password_confirm($data, $password),
            uv_enum($role, ['admin', 'super_admin'], 'role'),
            uv_enum($status, ['active', 'inactive', 'pending'], 'status'),
            uv_enum($shift, ['', 'Day Shift', 'Night Shift', 'Flexible'], 'shift status', false),
            uv_enum($access, ['1', '2', '3', '4', '5'], 'access level'),
            uv_maxlen($data['assigned_department'] ?? '', 100, 'Assigned department'),
            uv_maxlen($data['assigned_responsibility'] ?? '', 150, 'Assigned responsibility'),
            uv_maxlen($data['address'] ?? '', 255, 'Address'),
            uv_maxlen($data['bio'] ?? '', 500, 'Bio'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['position_title'] ?? '', uv_config()['admin_positions'] ?? [], 'administrator position', false),
                uv_maxlen($data['assigned_office'] ?? '', 120, 'Assigned office'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}

if (!function_exists('uv_validate_admin_update')) {
    function uv_validate_admin_update(array $data): ?string
    {
        $id = uv_trim($data['id'] ?? '');
        if ($id === '' || !ctype_digit($id)) {
            return 'Invalid administrator reference.';
        }
        $id = (int) $id;

        $fullName = uv_trim($data['full_name'] ?? '');
        $username = uv_trim($data['username'] ?? '');
        $email = uv_trim($data['email'] ?? '');
        $contact = uv_trim($data['contact_no'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $role = uv_trim($data['role'] ?? '');
        $status = uv_trim($data['status'] ?? '');
        $shift = uv_trim($data['shift_status'] ?? '');
        $access = uv_trim($data['access_level'] ?? '');

        foreach ([
            uv_name($fullName, 'Full name', false),
            uv_username($username, false),
            uv_duplicate_exists('users', 'username', $username, $id) ? 'Username is already taken.' : null,
            uv_email($email, false),
            uv_duplicate_exists('users', 'email', $email, $id) ? 'Email is already registered.' : null,
            uv_phone($contact, 'Contact number', false),
            uv_password($password, 'New password', false),
            uv_password_confirm($data, $password),
            uv_enum($role, ['admin', 'super_admin'], 'role', false),
            uv_enum($status, ['active', 'inactive', 'pending'], 'status'),
            uv_enum($shift, ['', 'Day Shift', 'Night Shift', 'Flexible'], 'shift status', false),
            uv_enum($access, ['1', '2', '3', '4', '5'], 'access level'),
            uv_maxlen($data['assigned_department'] ?? '', 100, 'Assigned department'),
            uv_maxlen($data['assigned_responsibility'] ?? '', 150, 'Assigned responsibility'),
            uv_maxlen($data['address'] ?? '', 255, 'Address'),
            uv_maxlen($data['bio'] ?? '', 500, 'Bio'),
        ] as $error) {
            if ($error !== null) {
                return $error;
            }
        }

        foreach (array_merge(
            uv_employment_fields($data),
            [
                uv_date_not_future($data['birth_date'] ?? '', 'Date of birth'),
                uv_enum($data['position_title'] ?? '', uv_config()['admin_positions'] ?? [], 'administrator position', false),
                uv_maxlen($data['assigned_office'] ?? '', 120, 'Assigned office'),
            ]
        ) as $extraError) {
            if ($extraError !== null) {
                return $extraError;
            }
        }

        return null;
    }
}
