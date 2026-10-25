-- Additive migration: birth date and hired date for every account source.
-- The runtime helper (Admin\Services\RoleManagement\PersonnelFields) applies the
-- same columns automatically; this file documents the change for deployers.

ALTER TABLE `drivers`    ADD COLUMN `birth_date` DATE NULL, ADD COLUMN `hired_date` DATE NULL;
ALTER TABLE `inspectors` ADD COLUMN `birth_date` DATE NULL, ADD COLUMN `hired_date` DATE NULL;
ALTER TABLE `users`      ADD COLUMN `birth_date` DATE NULL, ADD COLUMN `hired_date` DATE NULL;
