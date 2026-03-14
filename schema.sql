-- ============================================================
-- Comboni Library v5 – Complete Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS comboni_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE comboni_library;

CREATE TABLE IF NOT EXISTS teachers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  department VARCHAR(100) DEFAULT NULL,
  phone      VARCHAR(30)  DEFAULT NULL,
  initials   VARCHAR(5)   DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  class        VARCHAR(50)  DEFAULT NULL,
  borrow_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  code           VARCHAR(80)  NOT NULL,
  author         VARCHAR(150) DEFAULT NULL,
  isbn           VARCHAR(25)  DEFAULT NULL,
  category       VARCHAR(100) DEFAULT NULL,
  shelf_location VARCHAR(50)  DEFAULT NULL,
  quantity       INT UNSIGNED NOT NULL DEFAULT 1,
  description    TEXT         DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_book_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS borrow_records (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  borrower_type ENUM('teacher','student','reading_club') NOT NULL,
  borrower_id   INT UNSIGNED DEFAULT NULL,
  borrower_name VARCHAR(150) NOT NULL,
  book_id       INT UNSIGNED DEFAULT NULL,
  book_title    VARCHAR(255) NOT NULL,
  book_code     VARCHAR(80)  NOT NULL,
  date_taken    DATETIME     NOT NULL,
  status        ENUM('taken','returned') NOT NULL DEFAULT 'taken',
  return_date   DATETIME DEFAULT NULL,
  return_notes  TEXT DEFAULT NULL,
  notes         TEXT DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME DEFAULT NULL,
  INDEX idx_borrower  (borrower_type, borrower_id),
  INDEX idx_status    (status),
  INDEX idx_date      (date_taken),
  INDEX idx_book_code (book_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS archive_records (
  id            INT UNSIGNED PRIMARY KEY,
  borrower_type ENUM('teacher','student','reading_club') NOT NULL,
  borrower_id   INT UNSIGNED DEFAULT NULL,
  borrower_name VARCHAR(150) NOT NULL,
  book_id       INT UNSIGNED DEFAULT NULL,
  book_title    VARCHAR(255) NOT NULL,
  book_code     VARCHAR(80)  NOT NULL,
  date_taken    DATETIME     NOT NULL,
  status        ENUM('taken','returned') NOT NULL DEFAULT 'taken',
  return_date   DATETIME DEFAULT NULL,
  return_notes  TEXT DEFAULT NULL,
  notes         TEXT DEFAULT NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  archived_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_id     INT UNSIGNED NOT NULL,
  field_changed VARCHAR(80)  NOT NULL,
  old_value     TEXT DEFAULT NULL,
  new_value     TEXT DEFAULT NULL,
  editor_name   VARCHAR(100) DEFAULT 'System',
  timestamp     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_record (record_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(80) NOT NULL PRIMARY KEY,
  `value`    TEXT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('school_name',              'Comboni Senior Secondary School'),
  ('school_logo',              ''),
  ('archive_days',             '30'),
  ('return_period',            '14'),
  ('return_period_teacher',    '30'),
  ('return_period_student',    '14'),
  ('return_period_reading_club','14'),
  ('editor_name',              'Librarian'),
  ('app_version',              '5.0.0'),
  ('schema_version',           '5'),
  ('app_language',             'en'),
  ('admin_password_hash',      ''),
  ('guest_enabled',            '1');
