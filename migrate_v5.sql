-- ============================================================
-- Comboni Library v5 – Migration from v4
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================================

-- 1. Add 'reading_club' to borrower_type ENUM
ALTER TABLE borrow_records MODIFY borrower_type ENUM('teacher','student','reading_club') NOT NULL;
ALTER TABLE archive_records MODIFY borrower_type ENUM('teacher','student','reading_club') NOT NULL;

-- 2. Add per-type return period settings
INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('return_period_teacher', '30'),
  ('return_period_student', '14'),
  ('return_period_reading_club', '14');

-- 3. Update schema version
UPDATE settings SET `value` = '5' WHERE `key` = 'schema_version';
UPDATE settings SET `value` = '5.0.0' WHERE `key` = 'app_version';
