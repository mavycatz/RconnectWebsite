<?php

namespace Finance\Services;

require_once __DIR__ . '/../models/AuditTrailModel.php';

use Finance\Models\AuditTrailModel;

/**
 * Configurable authorization rules for material discrepancy actions.
 *
 * Rules live as a versioned JSON document in system_settings under the
 * `discrepancy_auth_rules` key; when absent the defaults below apply.
 * Every endpoint consults this service server-side before acting.
 */
class DiscrepancyAuthorizationService
{
    public const SETTING_KEY = 'discrepancy_auth_rules';

    public const ACTION_LABELS = [
        'additional_payment' => 'Additional payment',
        'source_correction' => 'Verified source-record correction',
        'accounting_adjustment' => 'Accounting adjustment',
        'payroll_recovery' => 'Authorized recovery (payroll)',
        'waiver' => 'Waiver',
        'reopen' => 'Reopen or amend a closed case',
        'reclassify' => 'Reclassify a case',
    ];

    /** Roles allowed to edit the rules document itself. */
    public const RULES_EDIT_ROLES = ['finance_manager', 'admin', 'super_admin'];

    private ?\PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db;
    }

    private function db(): \PDO
    {
        if ($this->db === null) {
            $this->db = \App\Core\Database::get();
        }
        return $this->db;
    }

    /**
     * Default rules (design D5): finance officers and managers propose,
     * finance managers approve, self-approval is off, every material action
     * requires approval.
     */
    public static function defaultRules(): array
    {
        $proposeRoles = ['finance_officer', 'finance_manager'];
        $approveRoles = ['finance_manager'];

        $actions = [];
        foreach (array_keys(self::ACTION_LABELS) as $action) {
            $actions[$action] = [
                'propose_roles' => $proposeRoles,
                'approve_roles' => $approveRoles,
                'requires_approval' => in_array($action, ['reopen', 'reclassify'], true) ? false : true,
                'self_approval' => false,
                'propose_amount_limit' => 0,
            ];
        }

        return [
            'version' => 1,
            'actions' => $actions,
        ];
    }

    /**
     * Effective rules: stored document when valid, defaults otherwise.
     */
    public function rules(): array
    {
        try {
            $stmt = $this->db()->prepare(
                "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1"
            );
            $stmt->execute([self::SETTING_KEY]);
            $raw = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return self::defaultRules();
        }

        if (!$raw) {
            return self::defaultRules();
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !isset($decoded['actions']) || !is_array($decoded['actions'])) {
            return self::defaultRules();
        }

        $defaults = self::defaultRules();
        $defaults['version'] = (int) ($decoded['version'] ?? $defaults['version']);

        foreach ($defaults['actions'] as $action => $rule) {
            $stored = $decoded['actions'][$action] ?? [];
            if (!is_array($stored)) {
                continue;
            }
            if (isset($stored['propose_roles']) && is_array($stored['propose_roles'])) {
                $defaults['actions'][$action]['propose_roles'] = array_values(array_filter(
                    array_map('strval', $stored['propose_roles'])
                ));
            }
            if (isset($stored['approve_roles']) && is_array($stored['approve_roles'])) {
                $defaults['actions'][$action]['approve_roles'] = array_values(array_filter(
                    array_map('strval', $stored['approve_roles'])
                ));
            }
            if (array_key_exists('requires_approval', $stored)) {
                $defaults['actions'][$action]['requires_approval'] = (bool) $stored['requires_approval'];
            }
            if (array_key_exists('self_approval', $stored)) {
                $defaults['actions'][$action]['self_approval'] = (bool) $stored['self_approval'];
            }
            if (array_key_exists('propose_amount_limit', $stored)) {
                $defaults['actions'][$action]['propose_amount_limit'] = round((float) $stored['propose_amount_limit'], 2);
            }
        }

        return $defaults;
    }

    public function ruleFor(string $action): ?array
    {
        $rules = $this->rules();
        return $rules['actions'][$action] ?? null;
    }

    public function canPropose(string $action, float $amount, ?string $role): bool
    {
        $rule = $this->ruleFor($action);
        if ($rule === null || $role === null) {
            return false;
        }
        if (!in_array($role, $rule['propose_roles'], true)) {
            return false;
        }
        $limit = (float) ($rule['propose_amount_limit'] ?? 0);
        if ($limit > 0 && round($amount, 2) > $limit) {
            return false;
        }
        return true;
    }

    public function canApprove(string $action, float $amount, ?string $role): bool
    {
        $rule = $this->ruleFor($action);
        if ($rule === null || $role === null) {
            return false;
        }
        return in_array($role, $rule['approve_roles'], true);
    }

    public function allowSelfApproval(string $action, float $amount): bool
    {
        $rule = $this->ruleFor($action);
        return (bool) ($rule['self_approval'] ?? false);
    }

    public function requiresApproval(string $action, float $amount): bool
    {
        $rule = $this->ruleFor($action);
        return (bool) ($rule['requires_approval'] ?? true);
    }

    /**
     * Actions performed directly (not routed through an approval request),
     * such as reopening a closed case.
     */
    public function canPerform(string $action, float $amount, ?string $role): bool
    {
        return $this->canPropose($action, $amount, $role);
    }

    public function canEditRules(?string $role): bool
    {
        return $role !== null && in_array($role, self::RULES_EDIT_ROLES, true);
    }

    /**
     * Persist a rules document after validating its shape. Only manager-level
     * roles may save; structural violations are refused.
     */
    public function saveRules(array $rules, ?array $actor = null): array
    {
        $actor = DiscrepancyCaseService::currentActor($actor);

        if (!$this->canEditRules($actor['role'])) {
            AuditTrailModel::record(
                'Finance',
                'Discrepancy Rules Update',
                'Refused: role ' . ($actor['role'] ?? 'unknown') . ' may not edit discrepancy authorization rules.',
                'DIS-RULES',
                null,
                null,
                'Failed',
                $this->db
            );
            throw new \UnexpectedValueException('Your role is not authorized to edit discrepancy authorization rules.');
        }

        $validated = $this->validateRules($rules);
        $json = json_encode($validated, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('The authorization rules could not be encoded.');
        }

        $exists = false;
        try {
            $stmt = $this->db()->prepare(
                "SELECT COUNT(*) FROM system_settings WHERE setting_key = ?"
            );
            $stmt->execute([self::SETTING_KEY]);
            $exists = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $exists = false;
        }

        if ($exists) {
            $stmt = $this->db()->prepare(
                "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?"
            );
            $stmt->execute([$json, self::SETTING_KEY]);
        } else {
            $stmt = $this->db()->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)"
            );
            $stmt->execute([self::SETTING_KEY, $json]);
        }

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Rules Updated',
            'Discrepancy authorization rules updated by ' . $actor['username'] . '.',
            'DIS-RULES',
            null,
            ['version' => $validated['version'], 'actions' => array_keys($validated['actions'])],
            'Success',
            $this->db
        );

        return $validated;
    }

    /**
     * Validate and normalize a submitted rules document.
     */
    public function validateRules(array $rules): array
    {
        $defaults = self::defaultRules();
        $actions = $rules['actions'] ?? null;

        if (!is_array($actions)) {
            throw new \InvalidArgumentException('The rules document must contain an actions object.');
        }

        foreach ($defaults['actions'] as $action => $rule) {
            if (!isset($actions[$action]) || !is_array($actions[$action])) {
                throw new \InvalidArgumentException('Missing authorization rule for ' . $action . '.');
            }

            $propose = $actions[$action]['propose_roles'] ?? null;
            $approve = $actions[$action]['approve_roles'] ?? null;

            if (!is_array($propose) || !is_array($approve) || !$propose || !$approve) {
                throw new \InvalidArgumentException('Rules for ' . $action . ' require non-empty propose_roles and approve_roles arrays.');
            }

            foreach (array_merge($propose, $approve) as $role) {
                if (!is_string($role) || trim($role) === '') {
                    throw new \InvalidArgumentException('Rules for ' . $action . ' contain an invalid role.');
                }
            }

            $limit = round((float) ($actions[$action]['propose_amount_limit'] ?? 0), 2);
            if ($limit < 0) {
                throw new \InvalidArgumentException('Rules for ' . $action . ' contain a negative amount limit.');
            }

            $defaults['actions'][$action] = [
                'propose_roles' => array_values(array_unique(array_map('strval', $propose))),
                'approve_roles' => array_values(array_unique(array_map('strval', $approve))),
                'requires_approval' => (bool) ($actions[$action]['requires_approval'] ?? $rule['requires_approval']),
                'self_approval' => (bool) ($actions[$action]['self_approval'] ?? $rule['self_approval']),
                'propose_amount_limit' => $limit,
            ];
        }

        return [
            'version' => max(1, (int) ($rules['version'] ?? 1)),
            'actions' => $defaults['actions'],
        ];
    }
}
