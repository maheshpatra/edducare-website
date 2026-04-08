-- ============================================================================
-- Migration: Decouple classes from academic sessions
-- 
-- REASON: Classes (e.g. "Class 5", "Class 6") are one-time creations and
-- should NOT be tied to a specific academic session. Only student enrollments
-- should be session-specific.
--
-- WHAT THIS DOES:
--   1. Makes academic_year_id NULLABLE on the classes table
--   2. Drops the foreign key constraint to academic_years
--   3. Sets existing academic_year_id values to NULL (optional cleanup)
--
-- HOW TO RUN: Execute this SQL on your production database (phpMyAdmin or CLI)
-- ============================================================================

-- Step 1: Drop the foreign key constraint
ALTER TABLE `classes` DROP FOREIGN KEY `classes_ibfk_2`;

-- Step 2: Make the column nullable (was NOT NULL before)
ALTER TABLE `classes` MODIFY COLUMN `academic_year_id` int(11) DEFAULT NULL;

-- Step 3 (OPTIONAL): Clear existing academic_year_id values since classes are 
-- no longer session-specific. You can keep them if you want historical reference.
-- Uncomment the line below to clear:
-- UPDATE `classes` SET `academic_year_id` = NULL;

-- Step 4: Verify the change
-- Run this to confirm: DESCRIBE `classes`;
-- The academic_year_id column should now show NULL in the Null column
