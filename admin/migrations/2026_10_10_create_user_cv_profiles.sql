-- Additive migration: shared CV/resume profiles for the unified Role Management page.
-- Keyed by subject type (driver|inspector|dispatcher|finance|admin) and the source record reference.
-- No existing table is altered.

CREATE TABLE IF NOT EXISTS `user_cv_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_type` VARCHAR(20) NOT NULL,
    `subject_ref` VARCHAR(64) NOT NULL,
    `resume_file` VARCHAR(255) NULL,
    `resume_text` MEDIUMTEXT NULL,
    `education` JSON NULL,
    `experience` JSON NULL,
    `skills` JSON NULL,
    `extracted_at` DATETIME NULL,
    `updated_by` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cv_subject` (`subject_type`, `subject_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
