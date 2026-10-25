<?php declare(strict_types=1);

/**
 * Admin Settings page cleanup checks: only settings that drive real system
 * behavior remain (system_name for report branding, maintenance_mode enforced
 * by Bootstrap); the no-op hardcoded controls are removed. Static checks only.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$settingsDir = __DIR__ . '/../views/settings';
$sectionsDir = $settingsDir . '/sections';

/* --------------------- removed no-op sections --------------------- */

foreach (['security_settings.php', 'appearance_settings.php', 'quick_actions.php', 'quick_preferences.php'] as $removed) {
    check(!is_file($sectionsDir . '/' . $removed), 'The no-op section is removed: ' . $removed);
}

$registry = (string) file_get_contents($sectionsDir . '/_registry.php');
foreach (['security_settings', 'appearance_settings', 'quick_actions', 'quick_preferences'] as $gone) {
    check(!str_contains($registry, $gone), 'The registry no longer lists ' . $gone);
}
check(str_contains($registry, 'maintenance_mode.php'), 'The registry keeps the enforced maintenance mode section');
check(str_contains($registry, 'general_settings.php'), 'The registry keeps the general settings section');

/* ------------------- remaining controls are enforced ------------------- */

$general = (string) file_get_contents($sectionsDir . '/general_settings.php');
check(str_contains($general, 'name="system_name"'), 'General settings edits the consumed system_name');
check(!str_contains($general, 'support_email') && !str_contains($general, 'timezone'), 'General settings no longer offers unconsumed fields');

$maintenance = (string) file_get_contents($sectionsDir . '/maintenance_mode.php');
check(str_contains($maintenance, 'value="toggle_maintenance"'), 'Maintenance mode posts the enforced toggle action');
check(str_contains($maintenance, "maintenance_mode"), 'Maintenance mode shows the stored state');

$overview = (string) file_get_contents($sectionsDir . '/system_overview.php');
check(!str_contains($overview, 'environment') && !str_contains($overview, 'app_version'), 'The overview no longer shows unconsumed environment/version values');

/* ----------------------- no dead handlers remain ----------------------- */

$index = (string) file_get_contents($settingsDir . '/index.php');
foreach (['save_security', 'save_appearance', 'save_preferences', 'restart_system', 'backup_now', 'save_facebook'] as $handler) {
    check(!str_contains($index, $handler), 'The dead handler is removed: ' . $handler);
}
check(!str_contains($index, 'system_restart_requested') && !str_contains($index, 'last_backup'), 'No settings are written that nothing reads');
check(!str_contains($index, '$getPref') && !str_contains($index, '$preferences'), 'User preferences with no consumers are gone');
check(str_contains($index, "if (\$action === 'save_general')") && str_contains($index, "elseif (\$action === 'toggle_maintenance')"), 'Only the two enforced actions remain');

/* ---------------------- the kept settings are wired ---------------------- */

$bootstrap = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php');
check(str_contains($bootstrap, "setting_key = 'maintenance_mode'"), 'maintenance_mode is still enforced by the bootstrap');

$report = (string) file_get_contents(__DIR__ . '/../api/Driver/ai-driver-report.php');
check(str_contains($report, "setting_key = 'system_name'"), 'system_name is still consumed by the driver AI report');

echo "$checks admin settings cleanup checks passed.\n";
