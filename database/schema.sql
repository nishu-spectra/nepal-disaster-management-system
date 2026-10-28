CREATE DATABASE IF NOT EXISTS ndms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ndms;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','officer','organization','responder','citizen') NOT NULL DEFAULT 'citizen',
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  preferred_language ENUM('en','ne') NOT NULL DEFAULT 'en',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  disaster_type ENUM('flood','landslide','earthquake','fire','storm','other') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  province VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  municipality VARCHAR(150) NOT NULL,
  ward VARCHAR(30) NULL,
  address_details VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  description TEXT NOT NULL,
  status ENUM('open','under_review','confirmed','assigned','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_incident_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_incident_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_incidents_status_created (status,created_at),
  INDEX idx_incidents_location (province,district,municipality),
  INDEX idx_incidents_severity (severity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assistance_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  assistance_type ENUM('rescue','medical','shelter','food_water','supplies','other') NOT NULL,
  people_affected SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  province VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  municipality VARCHAR(150) NOT NULL,
  ward VARCHAR(30) NULL,
  details TEXT NOT NULL,
  status ENUM('open','under_review','assigned','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
  assigned_to BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_help_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_help_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_help_status_created (status,created_at),
  INDEX idx_help_owner (user_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_key CHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_time (email_key,attempted_at),
  INDEX idx_login_ip_time (ip_hash,attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_security_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_security_event_time (event_type,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type,entity_id),
  INDEX idx_audit_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  organization_type ENUM('ngo','hospital','government','shelter','other') NOT NULL DEFAULT 'ngo',
  registration_number VARCHAR(100) NULL,
  district VARCHAR(100) NOT NULL,
  status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_creator FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_org_status (status)
) ENGINE=InnoDB;
