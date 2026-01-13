-- Add consultation_id column to notification_queue table
-- This allows us to link notifications back to specific consultations for DOCX generation

-- Check if column exists first, then add if needed
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'notification_queue' 
    AND COLUMN_NAME = 'consultation_id'
);

-- Add column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE notification_queue ADD COLUMN consultation_id INT(11) NULL AFTER notification_type',
    'SELECT "Column consultation_id already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if column was added
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'notification_queue' 
    AND CONSTRAINT_NAME = 'fk_notification_consultation'
);

SET @fk_sql = IF(@fk_exists = 0 AND @column_exists = 0,
    'ALTER TABLE notification_queue ADD CONSTRAINT fk_notification_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists or column was not added" as message'
);

PREPARE fk_stmt FROM @fk_sql;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;

-- Show result
SELECT 
    CASE 
        WHEN @column_exists = 0 THEN 'consultation_id column added successfully'
        ELSE 'consultation_id column already existed'
    END as result;
