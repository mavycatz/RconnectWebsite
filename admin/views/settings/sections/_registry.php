<?php
declare(strict_types = 1);

/* Sections shown on the admin Settings page. Only controls that drive real
   system behavior are listed here:
   - General Settings: system_name is read by the driver AI report;
   - Maintenance Mode: enforced by app/Core/Bootstrap.php;
   - the remaining sections are read-only status information. */
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
            'id' => 'maintenance_mode',
            'label' => 'Maintenance Mode',
            'file' => __DIR__ . '/maintenance_mode.php',
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
    ],
];
