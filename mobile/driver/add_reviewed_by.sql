SET @db_name = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db_name
       AND TABLE_NAME = 'passenger_trips'
       AND COLUMN_NAME = 'reviewed_by') = 0,
    'ALTER TABLE passenger_trips ADD COLUMN reviewed_by VARCHAR(100) NULL AFTER remitted_by',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
