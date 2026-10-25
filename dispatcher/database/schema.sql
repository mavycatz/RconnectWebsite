-- Dispatcher Accounts table
CREATE TABLE IF NOT EXISTS `dispatcher_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password: admin123)
INSERT INTO `dispatcher_accounts` (`username`, `password`, `full_name`, `status`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'Active')
ON DUPLICATE KEY UPDATE `full_name` = `full_name`;

-- Bus Inspections table
CREATE TABLE IF NOT EXISTS `bus_inspections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bus_id` VARCHAR(50) NOT NULL,
    `inspector_id` VARCHAR(50) NOT NULL,
    `result` ENUM('Passed', 'Failed') NOT NULL,
    `remarks` TEXT,
    `issues` TEXT,
    `inspection_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bus_id` (`bus_id`),
    INDEX `idx_inspector_id` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
