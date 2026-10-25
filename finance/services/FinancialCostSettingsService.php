<?php

declare(strict_types=1);

namespace Finance\Services;

require_once __DIR__ . '/../models/FinancialCostSettingsModel.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../../app/Core/FinancialCosts.php';
require_once __DIR__ . '/../../app/Core/MailerService.php';

use App\Auth\Auth;
use App\Core\FinancialCosts;
use App\Core\MailerService;
use Finance\Models\AuditTrailModel;
use Finance\Models\FinancialCostSettingsModel;

/**
 * Business rules for the Financial Cost Settings module.
 *
 *  - Only finance_manager / admin / super_admin may save changes.
 *  - All saved values are validated and written to `financial_cost_settings`.
 *  - Every real change is written to the shared `audit_trail` with previous
 *    and new values, the editor (resolved from the session), timestamp and
 *    the required reason.
 *  - Historical financial records are never modified: settings only affect
 *    calculations made after the change.
 */
class FinancialCostSettingsService
{
    public const EDIT_ROLES = ['finance_manager', 'admin', 'super_admin'];
    public const VIEW_ROLES = ['finance_manager', 'finance_officer', 'admin', 'super_admin'];

    public const FIXED_METHODS = ['fixed_monthly', 'fixed_daily', 'per_trip', 'percent_gross'];
    public const UNITS = ['PHP/month', 'PHP/day', 'PHP/trip', '% of gross', 'PHP/liter'];
    public const MAX_AMOUNT = 10000000.0;
    public const MAX_RATE = 100.0;
    public const MAX_NOTES = 255;
    public const MIN_REASON = 5;

    /** Fuel prices are read and updated from the shared fuel_prices table. */
    public const MAX_FUEL_PRICE = 1000.0;

    /** Email verification code settings. */
    public const OTP_TTL = 300;
    public const OTP_MAX_ATTEMPTS = 3;
    public const OTP_RESEND_SECONDS = 60;

    /** How long a completed password + email verification stays valid. */
    public const IDENTITY_TTL = 600;

    /** Failed-attempt throttling for the verification factors. */
    public const MAX_AUTH_FAILURES = 5;
    public const AUTH_LOCKOUT_SECONDS = 900;

    public static function role(): string
    {
        return strtolower(trim((string) Auth::role()));
    }

    public static function canEdit(): bool
    {
        return in_array(self::role(), self::EDIT_ROLES, true);
    }

    public static function canView(): bool
    {
        return in_array(self::role(), self::VIEW_ROLES, true);
    }

    public static function methodLabels(): array
    {
        return [
            'fixed_monthly' => 'Fixed amount per month',
            'fixed_daily' => 'Fixed amount per day',
            'per_trip' => 'Fixed amount per trip',
            'percent_gross' => 'Percentage of gross income',
            'actual_fuel' => 'Actual fuel consumption × fuel price',
        ];
    }

    /**
     * Unit implied by a calculation method. The unit is never edited by the
     * user — it follows the method so the interface cannot desynchronise.
     */
    public static function unitForMethod(string $method): string
    {
        return [
            'fixed_monthly' => 'PHP/month',
            'fixed_daily' => 'PHP/day',
            'per_trip' => 'PHP/trip',
            'percent_gross' => '% of gross',
            'actual_fuel' => 'PHP/liter',
        ][strtolower(trim($method))] ?? 'PHP/month';
    }

    /**
     * Current settings keyed by cost_key (DB row merged over legacy defaults).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSettings(): array
    {
        $model = new FinancialCostSettingsModel();
        $model->ensureSchema();
        $rows = $model->getAll();

        $settings = [];
        foreach (FinancialCosts::DEFAULTS as $key => $def) {
            $settings[$key] = [
                'cost_key' => $key,
                'label' => $def['label'],
                'amount' => (float) $def['amount'],
                'rate' => (float) $def['rate'],
                'calc_method' => $def['method'],
                'unit' => $def['unit'],
                'effective_date' => date('Y-m-d'),
                'status' => 'Active',
                'notes' => '',
                'updated_by' => null,
                'updated_at' => null,
                'source' => 'default',
            ];
        }

        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row['cost_key'] ?? '')));
            if (!isset($settings[$key])) {
                continue;
            }
            $settings[$key] = [
                'cost_key' => $key,
                'label' => trim((string) ($row['label'] ?? '')) !== '' ? (string) $row['label'] : $settings[$key]['label'],
                'amount' => (float) ($row['amount'] ?? 0),
                'rate' => (float) ($row['rate'] ?? 0),
                'calc_method' => (string) ($row['calc_method'] ?? $settings[$key]['calc_method']),
                'unit' => (string) ($row['unit'] ?? $settings[$key]['unit']),
                'effective_date' => (string) ($row['effective_date'] ?? date('Y-m-d')),
                'status' => (string) ($row['status'] ?? 'Active'),
                'notes' => (string) ($row['notes'] ?? ''),
                'updated_by' => $row['updated_by'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'source' => 'database',
            ];
        }

        return $settings;
    }

    /**
     * Live fuel types and prices from the shared `fuel_prices` table.
     *
     * @return array<int, array{id: int, fuel_type: string, price_per_liter: float, updated_at: ?string}>
     */
    public static function getFuelPrices(): array
    {
        try {
            $db = \App\Core\Database::get();
            $stmt = $db->query("SELECT id, fuel_type, price_per_liter, updated_at FROM fuel_prices ORDER BY fuel_type ASC");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'fuel_type' => (string) ($row['fuel_type'] ?? ''),
                'price_per_liter' => round((float) ($row['price_per_liter'] ?? 0), 2),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);
    }

    /**
     * Validate submitted rows and return the fields that would change.
     * Nothing is written and no verification is required — this powers the
     * review-and-confirm step in the interface.
     *
     * @param array<string, array<string, mixed>> $rows keyed by cost_key
     * @param array<int|string, mixed> $fuelPrices keyed by fuel_prices id
     * @return array<string, array{key: string, label: string, fields: array<string, array{old: mixed, new: mixed}>}>
     */
    public static function preview(array $rows, array $fuelPrices = []): array
    {
        if (!self::canEdit()) {
            throw new \RuntimeException('Forbidden: your role is not authorized to update financial cost settings.');
        }

        if (!$rows && !$fuelPrices) {
            throw new \InvalidArgumentException('No settings were submitted.');
        }

        $current = self::getSettings();
        $changes = [];

        foreach ($rows as $key => $payload) {
            $key = strtolower(trim((string) $key));
            if (!isset(FinancialCosts::DEFAULTS[$key])) {
                throw new \InvalidArgumentException("Unknown cost setting: $key");
            }
            if (!is_array($payload)) {
                throw new \InvalidArgumentException("Invalid payload for cost setting: $key");
            }

            $payload = self::resolvePayload($key, $payload, $current[$key]);

            $new = self::validateRow($key, $payload);
            $fields = self::diffSnapshots(self::auditSnapshot($current[$key]), self::auditSnapshot($new));

            if ($fields) {
                $changes[$key] = [
                    'key' => $key,
                    'label' => (string) $new['label'],
                    'fields' => $fields,
                ];
            }
        }

        return array_merge($changes, self::previewFuelPrices($fuelPrices));
    }

    /**
     * @param array<int|string, mixed> $fuelPrices
     * @return array<string, array{key: string, label: string, fields: array<string, array{old: mixed, new: mixed}>}>
     */
    private static function previewFuelPrices(array $fuelPrices): array
    {
        if (!$fuelPrices) {
            return [];
        }

        $records = [];
        foreach (self::getFuelPrices() as $record) {
            $records[(int) $record['id']] = $record;
        }

        $changes = [];
        foreach ($fuelPrices as $id => $price) {
            $id = (int) $id;
            if (!isset($records[$id])) {
                throw new \InvalidArgumentException("Unknown fuel price record: $id");
            }
            if (!is_numeric($price)) {
                throw new \InvalidArgumentException($records[$id]['fuel_type'] . ': fuel price must be a number.');
            }

            $newPrice = round((float) $price, 2);
            if ($newPrice <= 0 || $newPrice > self::MAX_FUEL_PRICE) {
                throw new \InvalidArgumentException($records[$id]['fuel_type'] . ': fuel price must be between 0.01 and ' . number_format(self::MAX_FUEL_PRICE, 2) . '.');
            }

            $oldPrice = (float) $records[$id]['price_per_liter'];
            if (abs($newPrice - $oldPrice) > 0.0001) {
                $changes['fuel_price_' . $id] = [
                    'key' => 'fuel_price_' . $id,
                    'label' => 'Fuel Price — ' . ($records[$id]['fuel_type'] !== '' ? $records[$id]['fuel_type'] : 'Fuel'),
                    'fields' => [
                        'price_per_liter' => ['old' => $oldPrice, 'new' => $newPrice],
                    ],
                ];
            }
        }

        return $changes;
    }

    /**
     * Save one or more settings rows after the caller has verified their
     * account password AND their registered email. All rows are validated
     * before anything is written so a failure never leaves a partial save.
     *
     * @param array<string, array<string, mixed>> $rows keyed by cost_key
     * @param array<int|string, mixed> $fuelPrices keyed by fuel_prices id
     * @return array<string, array<string, mixed>> the rows that actually changed
     *
     * @throws \RuntimeException when the caller is not allowed to edit or has
     *                           not completed identity verification
     * @throws \InvalidArgumentException when validation fails
     */
    public static function save(array $rows, string $reason, array $fuelPrices = []): array
    {
        if (!self::canEdit()) {
            throw new \RuntimeException('Forbidden: your role is not authorized to update financial cost settings.');
        }

        if (!self::identityVerified()) {
            throw new \RuntimeException('Please verify your account password and the email verification code before saving.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < self::MIN_REASON) {
            throw new \InvalidArgumentException('Please provide a reason for the change (at least ' . self::MIN_REASON . ' characters).');
        }

        // Validates every setting and fuel price before any write happens.
        $changes = self::preview($rows, $fuelPrices);
        if (!$changes) {
            return [];
        }

        $model = new FinancialCostSettingsModel();
        $model->ensureSchema();

        $current = self::getSettings();
        $editor = self::editorName();
        $changed = [];

        foreach ($rows as $key => $payload) {
            $key = strtolower(trim((string) $key));
            if (!isset($changes[$key])) {
                continue;
            }

            if (!is_array($payload)) {
                throw new \InvalidArgumentException("Invalid payload for cost setting: $key");
            }

            $payload = self::resolvePayload($key, $payload, $current[$key]);
            $new = self::validateRow($key, $payload);
            $previous = self::auditSnapshot($current[$key]);
            $next = self::auditSnapshot($new);

            $model->updateRow($key, $new, $editor);

            AuditTrailModel::record(
                'Finance',
                'CostSettingUpdated',
                "Updated {$new['label']} ({$key}). Reason: {$reason}",
                'COST-' . strtoupper($key),
                $previous,
                array_merge($next, ['reason' => $reason, 'verified_via' => 'password+email']),
                'Success'
            );

            $changed[$key] = $new;
        }

        foreach (self::fuelPriceUpdates($fuelPrices) as $update) {
            self::writeFuelPrice($update['id'], $update['price'], $update['old'], $reason, $editor);
            $changed['fuel_price_' . $update['id']] = $update;
        }

        FinancialCosts::reset();
        self::clearVerification();

        return $changed;
    }

    /**
     * @param array<int|string, mixed> $fuelPrices
     * @return array<int, array{id: int, fuel_type: string, price: float, old: float}>
     */
    private static function fuelPriceUpdates(array $fuelPrices): array
    {
        if (!$fuelPrices) {
            return [];
        }

        // preview() has already validated the payload; rebuild the writes here.
        self::previewFuelPrices($fuelPrices);

        $records = [];
        foreach (self::getFuelPrices() as $record) {
            $records[(int) $record['id']] = $record;
        }

        $updates = [];
        foreach ($fuelPrices as $id => $price) {
            $id = (int) $id;
            $record = $records[$id];
            $old = (float) $record['price_per_liter'];
            $new = round((float) $price, 2);
            if (abs($new - $old) <= 0.0001) {
                continue;
            }
            $updates[] = [
                'id' => $id,
                'fuel_type' => (string) $record['fuel_type'],
                'price' => $new,
                'old' => $old,
            ];
        }

        return $updates;
    }

    private static function writeFuelPrice(int $id, float $price, float $old, string $reason, string $editor): void
    {
        $db = \App\Core\Database::get();
        $stmt = $db->prepare('UPDATE fuel_prices SET price_per_liter = :price WHERE id = :id');
        $stmt->execute([':price' => $price, ':id' => $id]);

        $fuelType = '';
        try {
            $lookup = $db->prepare('SELECT fuel_type FROM fuel_prices WHERE id = ? LIMIT 1');
            $lookup->execute([$id]);
            $fuelType = (string) $lookup->fetchColumn();
        } catch (\Throwable $e) {
            $fuelType = '';
        }

        AuditTrailModel::record(
            'Finance',
            'FuelPriceUpdated',
            'Updated ' . ($fuelType !== '' ? $fuelType : 'fuel') . ' price to ' . number_format($price, 2) . ". Reason: {$reason}",
            'FUEL-' . $id,
            ['fuel_type' => $fuelType, 'price_per_liter' => $old],
            [
                'fuel_type' => $fuelType,
                'price_per_liter' => $price,
                'updated_by' => $editor,
                'reason' => $reason,
                'verified_via' => 'password+email',
            ],
            'Success'
        );
    }

    /* =========================================================
     * CHANGE VERIFICATION (ACCOUNT PASSWORD + EMAIL CODE)
     * ========================================================= */

    public static function verificationState(): array
    {
        $state = self::verification();
        $email = self::userEmail();

        return [
            'password' => (bool) ($state['password'] ?? false),
            'otp' => (bool) ($state['otp'] ?? false),
            'ready' => self::identityVerified(),
            'has_email' => $email !== '',
            'email_masked' => $email !== '' ? self::maskEmail($email) : '',
        ];
    }

    /**
     * Verify the signed-in user's account password. The password is compared
     * against the stored hash and is never stored or logged.
     */
    public static function verifyPassword(string $password): bool
    {
        if (!self::canEdit()) {
            throw new \RuntimeException('Forbidden: your role is not authorized to update financial cost settings.');
        }
        self::assertNotLockedOut();

        $password = (string) $password;
        $ok = false;

        try {
            $db = \App\Core\Database::get();
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([self::currentUserId()]);
            $hash = (string) $stmt->fetchColumn();
            $ok = $hash !== '' && $password !== '' && password_verify($password, $hash);
        } catch (\Throwable $e) {
            $ok = false;
        }

        if (!$ok) {
            self::recordAuthFailure();
            AuditTrailModel::record(
                'Finance',
                'CostSettingVerification',
                'Failed password verification before a financial cost settings change.',
                'COST-SETTINGS',
                null,
                ['step' => 'password'],
                'Failed'
            );
            return false;
        }

        self::clearAuthFailures();
        self::markVerified('password');
        AuditTrailModel::record(
            'Finance',
            'CostSettingVerification',
            'Account password verified before a financial cost settings change.',
            'COST-SETTINGS',
            null,
            ['step' => 'password'],
            'Success'
        );

        return true;
    }

    /**
     * Send a 6-digit verification code to the registered email address.
     *
     * @return array{email_masked: string}
     */
    public static function sendOtp(): array
    {
        if (!self::canEdit()) {
            throw new \RuntimeException('Forbidden: your role is not authorized to update financial cost settings.');
        }
        self::assertNotLockedOut();

        $sentAt = (int) ($_SESSION['fcs_otp_sent_at'] ?? 0);
        if ($sentAt > 0 && (time() - $sentAt) < self::OTP_RESEND_SECONDS) {
            throw new \RuntimeException('Please wait ' . (self::OTP_RESEND_SECONDS - (time() - $sentAt)) . ' second(s) before requesting another code.');
        }

        $email = self::userEmail();
        if ($email === '') {
            throw new \RuntimeException('No email address is registered for your account.');
        }

        $otp = (string) random_int(100000, 999999);
        $_SESSION['fcs_otp'] = [
            'hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires' => time() + self::OTP_TTL,
            'attempts' => 0,
        ];
        $_SESSION['fcs_otp_sent_at'] = time();

        $mailer = new MailerService();
        if (!$mailer->sendOtp($email, $otp)) {
            unset($_SESSION['fcs_otp'], $_SESSION['fcs_otp_sent_at']);
            throw new \RuntimeException('We could not send the verification code. Please try again.');
        }

        AuditTrailModel::record(
            'Finance',
            'CostSettingVerification',
            'A verification code was sent to the registered email before a financial cost settings change.',
            'COST-SETTINGS',
            null,
            ['step' => 'otp_sent'],
            'Success'
        );

        return ['email_masked' => self::maskEmail($email)];
    }

    /**
     * Verify the 6-digit code that was sent to the registered email.
     */
    public static function verifyOtp(string $otp): bool
    {
        if (!self::canEdit()) {
            throw new \RuntimeException('Forbidden: your role is not authorized to update financial cost settings.');
        }
        self::assertNotLockedOut();

        $otp = trim($otp);
        $state = $_SESSION['fcs_otp'] ?? null;

        if (!is_array($state)) {
            throw new \RuntimeException('No verification code was requested. Send one to your email first.');
        }
        if (time() > (int) ($state['expires'] ?? 0)) {
            unset($_SESSION['fcs_otp']);
            throw new \RuntimeException('The verification code expired. Please request a new one.');
        }
        if ((int) ($state['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            unset($_SESSION['fcs_otp']);
            throw new \RuntimeException('Too many incorrect attempts. Please request a new code.');
        }

        $_SESSION['fcs_otp']['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        $ok = $otp !== '' && password_verify($otp, (string) $state['hash']);

        if (!$ok) {
            self::recordAuthFailure();
            AuditTrailModel::record(
                'Finance',
                'CostSettingVerification',
                'Failed email code verification before a financial cost settings change.',
                'COST-SETTINGS',
                null,
                ['step' => 'otp'],
                'Failed'
            );
            return false;
        }

        unset($_SESSION['fcs_otp'], $_SESSION['fcs_otp_sent_at']);
        self::clearAuthFailures();
        self::markVerified('otp');
        AuditTrailModel::record(
            'Finance',
            'CostSettingVerification',
            'Registered email verified before a financial cost settings change.',
            'COST-SETTINGS',
            null,
            ['step' => 'otp'],
            'Success'
        );

        return true;
    }

    public static function identityVerified(): bool
    {
        $state = self::verification();

        return self::currentUserId() > 0
            && (int) ($state['user_id'] ?? 0) === self::currentUserId()
            && !empty($state['password'])
            && !empty($state['otp'])
            && (time() - (int) ($state['at'] ?? 0)) <= self::IDENTITY_TTL;
    }

    private static function verification(): array
    {
        $state = $_SESSION['fcs_verify'] ?? null;

        return is_array($state) ? $state : [];
    }

    private static function markVerified(string $factor): void
    {
        $state = self::verification();
        $userId = self::currentUserId();

        if ((int) ($state['user_id'] ?? 0) !== $userId) {
            $state = ['user_id' => $userId, 'password' => false, 'otp' => false];
        }

        $state[$factor === 'password' ? 'password' : 'otp'] = true;
        $state['at'] = time();
        $_SESSION['fcs_verify'] = $state;
    }

    private static function clearVerification(): void
    {
        unset($_SESSION['fcs_verify'], $_SESSION['fcs_otp'], $_SESSION['fcs_otp_sent_at']);
    }

    private static function assertNotLockedOut(): void
    {
        $throttle = self::authThrottle();
        if ((int) ($throttle['blocked_until'] ?? 0) > time()) {
            $minutes = max(1, (int) ceil(((int) $throttle['blocked_until'] - time()) / 60));
            throw new \RuntimeException("Too many failed verification attempts. Please try again in $minutes minute(s).");
        }
    }

    private static function recordAuthFailure(): void
    {
        $throttle = self::authThrottle();
        $now = time();

        if ((int) ($throttle['first'] ?? 0) === 0 || ($now - (int) $throttle['first']) > self::AUTH_LOCKOUT_SECONDS) {
            $throttle = ['fails' => 0, 'first' => $now, 'blocked_until' => 0];
        }

        $throttle['fails'] = (int) ($throttle['fails'] ?? 0) + 1;
        if ($throttle['fails'] >= self::MAX_AUTH_FAILURES) {
            $throttle['blocked_until'] = $now + self::AUTH_LOCKOUT_SECONDS;
        }

        $_SESSION['fcs_auth_throttle'] = $throttle;
    }

    private static function clearAuthFailures(): void
    {
        unset($_SESSION['fcs_auth_throttle']);
    }

    private static function authThrottle(): array
    {
        $throttle = $_SESSION['fcs_auth_throttle'] ?? null;

        return is_array($throttle) ? $throttle : [];
    }

    private static function currentUserId(): int
    {
        $user = $_SESSION['user'] ?? Auth::user() ?? [];
        return (int) ($user['id'] ?? 0);
    }

    private static function userEmail(): string
    {
        $userId = self::currentUserId();
        if ($userId <= 0) {
            return '';
        }

        try {
            $db = \App\Core\Database::get();
            $stmt = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            return trim((string) $stmt->fetchColumn());
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return $email;
        }

        $name = $parts[0];
        return substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1)) . '@' . $parts[1];
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function diffSnapshots(array $previous, array $next): array
    {
        $fields = [];

        foreach ($next as $field => $value) {
            $oldValue = $previous[$field] ?? null;
            $changed = (is_float($value) || is_float($oldValue))
                ? abs((float) $value - (float) $oldValue) > 0.0001
                : (string) $value !== (string) $oldValue;

            if ($changed) {
                $fields[$field] = ['old' => $oldValue, 'new' => $value];
            }
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getRecentAudit(int $limit = 20): array
    {
        $model = new FinancialCostSettingsModel();
        return $model->getRecentAudit($limit);
    }

    /**
     * Log a failed save attempt (validation/permission/database errors).
     */
    public static function logFailedAttempt(string $reason, array $attempted = []): void
    {
        try {
            AuditTrailModel::record(
                'Finance',
                'CostSettingUpdated',
                'Failed to update financial cost settings: ' . $reason,
                'COST-SETTINGS',
                null,
                $attempted ?: null,
                'Failed'
            );
        } catch (\Throwable $e) {
            // Audit failures must never mask the original error.
        }
    }

    /**
     * Fill missing fields from the stored row and lock fields that are not
     * used by the selected calculation method, so only meaningful values can
     * ever change:
     *   - fixed amount methods use `amount` (a percentage rate is ignored)
     *   - percent_gross uses `rate` (a fixed amount is preserved, not applied)
     *   - gas keeps its stored amount; the live price comes from fuel_prices
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    private static function resolvePayload(string $key, array $payload, array $current): array
    {
        foreach (['amount', 'rate', 'effective_date', 'status', 'notes'] as $field) {
            if (!array_key_exists($field, $payload)) {
                $payload[$field] = $current[$field];
            }
        }

        if ($key === 'gas') {
            $payload['amount'] = $current['amount'];
            $payload['rate'] = $current['rate'];
            $payload['calc_method'] = 'actual_fuel';
            return $payload;
        }

        $method = strtolower(trim((string) ($payload['calc_method'] ?? $current['calc_method'])));
        if ($method === 'percent_gross') {
            $payload['amount'] = $current['amount'];
        } else {
            $payload['rate'] = $current['rate'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function validateRow(string $key, array $payload): array
    {
        $defaults = FinancialCosts::DEFAULTS[$key];

        $amount = self::decimal($payload['amount'] ?? $defaults['amount'], self::MAX_AMOUNT, 'Amount');
        $rate = self::decimal($payload['rate'] ?? 0, self::MAX_RATE, 'Rate');

        if ($key === 'gas') {
            $method = 'actual_fuel';
            $unit = 'PHP/liter';
            $rate = 0.0;
        } else {
            $method = strtolower(trim((string) ($payload['calc_method'] ?? $defaults['method'])));
            if (!in_array($method, self::FIXED_METHODS, true)) {
                throw new \InvalidArgumentException("{$defaults['label']}: invalid calculation method.");
            }
            $unit = self::unitForMethod($method);
        }

        $effectiveDate = trim((string) ($payload['effective_date'] ?? ''));
        $date = \DateTime::createFromFormat('Y-m-d', $effectiveDate);
        if (!$date || $date->format('Y-m-d') !== $effectiveDate) {
            throw new \InvalidArgumentException("{$defaults['label']}: effective date must be a valid date (YYYY-MM-DD).");
        }

        $status = ucfirst(strtolower(trim((string) ($payload['status'] ?? 'Active'))));
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            throw new \InvalidArgumentException("{$defaults['label']}: invalid status.");
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        if (mb_strlen($notes) > self::MAX_NOTES) {
            $notes = mb_substr($notes, 0, self::MAX_NOTES);
        }

        return [
            'cost_key' => $key,
            'label' => $defaults['label'],
            'amount' => $amount,
            'rate' => $rate,
            'method' => $method,
            'unit' => $unit,
            'effective_date' => $effectiveDate,
            'status' => $status,
            'notes' => $notes,
        ];
    }

    private static function decimal(mixed $value, float $max, string $field): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("$field must be a number.");
        }
        $number = round((float) $value, 2);
        if ($number < 0 || $number > $max) {
            throw new \InvalidArgumentException("$field must be between 0 and " . number_format($max, 2) . '.');
        }
        return $number;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function auditSnapshot(array $row): array
    {
        return [
            'amount' => (float) ($row['amount'] ?? 0),
            'rate' => (float) ($row['rate'] ?? 0),
            'calc_method' => (string) ($row['calc_method'] ?? $row['method'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'effective_date' => (string) ($row['effective_date'] ?? ''),
            'status' => (string) ($row['status'] ?? 'Active'),
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }

    private static function editorName(): string
    {
        $user = $_SESSION['user'] ?? Auth::user() ?? [];
        return (string) ($user['username'] ?? $user['name'] ?? 'System');
    }
}
