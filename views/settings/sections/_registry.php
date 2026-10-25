<?php
declare(strict_types = 1);

return [
'left_column' => [
[
'id' => 'admin_info',
'label' => 'Admin Info',
'file' => __DIR__ . '/admin_info.php',
'allowedRoles' => ['super_admin', 'admin', 'finance_manager', 'finance_officer', 'inspector', 'driver'],
'enabled' => true,
],
[
'id' => 'system_overview',
'label' => 'System Overview',
'file' => __DIR__ . '/system_overview.php',
'allowedRoles' => ['super_admin'],
'enabled' => true,
],
[
'id' => 'system_health',
'label' => 'System Health',
'file' => __DIR__ . '/system_health.php',
'allowedRoles' => ['super_admin'],
'enabled' => true,
],
[
'id' => 'activity_summary',
'label' => 'Activity Summary',
'file' => __DIR__ . '/activity_summary.php',
'allowedRoles' => ['super_admin', 'admin', 'finance_manager', 'finance_officer', 'inspector', 'driver'],
'enabled' => true,
],
[
'id' => 'quick_preferences',
'label' => 'Quick Preferences',
'file' => __DIR__ . '/quick_preferences.php',
'allowedRoles' => ['super_admin', 'admin', 'finance_manager', 'finance_officer', 'inspector', 'driver'],
'enabled' => true,
],
[
'id' => 'quick_actions',
'label' => 'Quick Actions',
'file' => __DIR__ . '/quick_actions.php',
'allowedRoles' => ['super_admin'],
'enabled' => true,
],
],
'right_column' => [
[
'id' => 'general_settings',
'label' => 'General Settings',
'file' => __DIR__ . '/general_settings.php',
'allowedRoles' => ['super_admin', 'admin', 'finance_manager', 'finance_officer', 'inspector', 'driver'],
'enabled' => true,
],
[
'id' => 'security_settings',
'label' => 'Security Settings',
'file' => __DIR__ . '/security_settings.php',
'allowedRoles' => ['super_admin'],
'enabled' => true,
],
[
'id' => 'appearance_settings',
'label' => 'Appearance',
'file' => __DIR__ . '/appearance_settings.php',
'allowedRoles' => ['super_admin', 'admin', 'finance_manager', 'finance_officer', 'inspector', 'driver'],
'enabled' => true,
],
],
];
