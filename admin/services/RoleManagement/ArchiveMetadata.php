<?php declare(strict_types=1);
namespace Admin\Services\RoleManagement;

use PDO;

/** Read/write archive details in the existing audit history, never a copy table. */
final class ArchiveMetadata
{
    public static function details(PDO $db, string $source, string $ref, ?string $legacyKey = null): ?array
    {
        $module = match ($source) { 'bus' => 'Bus', 'driver' => 'Drivers', 'inspector' => 'Inspectors', 'finance' => 'Finance', default => 'Admin' };
        $legacyModule = match ($source) { 'driver' => 'Driver', 'inspector' => 'Inspector', default => $module };
        $stmt = $db->prepare("SELECT * FROM audit_trail WHERE module IN (?, ?) AND status = 'Success'
            AND record_id IN (?, ?) AND LOWER(action) IN ('archive', 'restore', 'unarchive') ORDER BY created_at DESC, id DESC");
        $stmt->execute([$module, $legacyModule, $source . ':' . $ref, $legacyKey ?? $ref]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $values = json_decode((string) ($event['new_values'] ?? ''), true) ?: [];
            if (isset($values['source']) && ($values['source'] !== $source || (string) ($values['ref'] ?? '') !== $ref)) { continue; }
            if (strtolower((string) $event['action']) !== 'archive') { return null; }
            return ['reason' => $values['reason'] ?? null, 'archived_at' => $event['created_at'],
                'archived_by' => $event['username'] ?? null, 'archived_by_id' => $event['user_id'] ?? null,
                'previous' => json_decode((string) ($event['previous_values'] ?? ''), true) ?: [], 'event_id' => $event['id']];
        }
        return null;
    }

    public static function record(PDO $db, string $source, string $ref, string $action, string $reason, array $actor, array $before, array $after): void
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
        $module = match ($source) { 'bus' => 'Bus', 'driver' => 'Drivers', 'inspector' => 'Inspectors', 'finance' => 'Finance', default => 'Admin' };
        (new \Finance\Models\AuditTrailModel())->log($module, ucfirst($action),
            ucfirst($action) . ' ' . $source . ' #' . $ref . ' — Reason: ' . $reason,
            (int) $actor['id'], (string) $actor['username'], (string) $actor['role'],
            $source . ':' . $ref, $before, ['source' => $source, 'ref' => $ref, 'reason' => $reason, 'state' => $after], 'Success', $db);
    }
}
