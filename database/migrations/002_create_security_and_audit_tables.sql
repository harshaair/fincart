-- Migration: 002_create_security_and_audit_tables.sql
-- Description: Rate limits, secure distributed sessions, password resets, and review ratings

CREATE TABLE IF NOT EXISTS rate_limits (
  id int NOT NULL AUTO_INCREMENT,
  action_key varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  attempts int NOT NULL DEFAULT '1',
  first_attempt_at int unsigned NOT NULL,
  last_attempt_at int unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY idx_action_last (action_key, last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_sessions (
  id varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  user_id int DEFAULT NULL,
  data mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  last_activity int unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id int NOT NULL AUTO_INCREMENT,
  email varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  token varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  expires_at datetime NOT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email (email),
  KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_ratings (
  id int NOT NULL AUTO_INCREMENT,
  professional_id int NOT NULL,
  user_id int NOT NULL,
  appointment_id int NOT NULL,
  rating tinyint unsigned NOT NULL,
  review_text text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_appointment_rating (appointment_id),
  KEY idx_prof_rating (professional_id, rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  id int NOT NULL AUTO_INCREMENT,
  admin_id int NOT NULL,
  action_type varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  entity_type varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  entity_id int NOT NULL,
  description text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  ip_address varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_action (admin_id, action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
