-- ============================================
-- MIGRATION: Add Cancellation Fields
-- Version: 003
-- Date: 2025-10-02
-- Description: Adds cancellation_reason column to consultations table
-- ============================================

-- Check if column exists before adding
SET @dbname = DATABASE();
SET @tablename = 'consultations';
SET @columnname = 'cancellation_reason';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1', -- Column exists, do nothing
  'ALTER TABLE consultations ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER status' -- Add column
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- VERIFICATION
-- ============================================

-- Check if column was added
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'consultations' 
AND COLUMN_NAME = 'cancellation_reason';
