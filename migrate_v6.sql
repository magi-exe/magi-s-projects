-- ============================================================
-- Comboni Library v6 – Migration from v5
-- Run this AFTER schema.sql (v5) has been applied
-- ============================================================
USE comboni_library;

-- ── Reading Club Members Table ──────────────────────────────
CREATE TABLE IF NOT EXISTS reading_club_members (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  class        VARCHAR(50)  DEFAULT NULL,
  borrow_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Overdue Blacklist ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS overdue_blacklist (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrower_type   ENUM('teacher','student','reading_club') NOT NULL,
  borrower_id     INT UNSIGNED DEFAULT NULL,
  borrower_name   VARCHAR(150) NOT NULL,
  reason          TEXT DEFAULT NULL,
  blacklisted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME DEFAULT NULL,
  created_by      VARCHAR(100) DEFAULT 'System',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_active (is_active, borrower_type),
  INDEX idx_borrower (borrower_type, borrower_id)
) ENGINE=InnoDB;

-- ── Overdue Notifications Log ───────────────────────────────
CREATE TABLE IF NOT EXISTS overdue_notifications (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrow_record_id  INT UNSIGNED NOT NULL,
  borrower_type     ENUM('teacher','student','reading_club') NOT NULL,
  borrower_id       INT UNSIGNED DEFAULT NULL,
  borrower_name     VARCHAR(150) NOT NULL,
  notification_type ENUM('approaching','overdue','blacklisted') NOT NULL DEFAULT 'overdue',
  message           TEXT DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_record (borrow_record_id),
  INDEX idx_type   (notification_type)
) ENGINE=InnoDB;

-- ── System Backups Log ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_backups (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename    VARCHAR(255) NOT NULL,
  size_bytes  BIGINT UNSIGNED DEFAULT 0,
  tables_included TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── New Settings ────────────────────────────────────────────
INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('screen_timeout_enabled',     '0'),
  ('screen_timeout_minutes',     '15'),
  ('blacklist_auto_ban_enabled', '0'),
  ('blacklist_auto_ban_days',    '60'),
  ('blacklist_ban_duration_days','30'),
  ('app_version',                '6.0.0'),
  ('schema_version',             '6');

-- Update version if already exists
UPDATE settings SET `value` = '6.0.0' WHERE `key` = 'app_version';
UPDATE settings SET `value` = '6' WHERE `key` = 'schema_version';

-- Remove guest_enabled setting
DELETE FROM settings WHERE `key` = 'guest_enabled';
