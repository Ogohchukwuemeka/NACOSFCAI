-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2026-07-22 00:00:00
-- Server version: 8.0.35
-- PHP Version: 8.2
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS nacosfcai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nacosfcai;

-- Schema only: this file does not seed account data or drop existing tables.

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(180) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  guard_name VARCHAR(80) DEFAULT 'web',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  matric_number VARCHAR(80) DEFAULT NULL,
  department VARCHAR(120) DEFAULT NULL,
  level VARCHAR(30) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin','executive') NOT NULL DEFAULT 'student',
  status ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  community_username VARCHAR(20) DEFAULT NULL UNIQUE,
  registration_number VARCHAR(120) NOT NULL UNIQUE,
  course VARCHAR(150) DEFAULT NULL,
  semester VARCHAR(40) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  emergency_contact VARCHAR(120) DEFAULT NULL,
  blood_group VARCHAR(10) DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  session VARCHAR(40) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  session_token VARCHAR(255) NOT NULL UNIQUE,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code VARCHAR(10) NOT NULL,
  purpose VARCHAR(80) NOT NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  department VARCHAR(120) NOT NULL,
  level VARCHAR(40) NOT NULL,
  duration VARCHAR(60) NOT NULL,
  description TEXT NOT NULL,
  requirements TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE executives (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenure_id INT UNSIGNED DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  full_name VARCHAR(150) NOT NULL,
  position VARCHAR(120) NOT NULL,
  department VARCHAR(120) NOT NULL,
  academic_level VARCHAR(40) DEFAULT NULL,
  biography TEXT DEFAULT NULL,
  contact VARCHAR(120) DEFAULT NULL,
  passport_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE executive_tenures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  starts_on DATE DEFAULT NULL,
  ends_on DATE DEFAULT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE executives
  ADD CONSTRAINT fk_executives_tenure FOREIGN KEY (tenure_id) REFERENCES executive_tenures(id) ON DELETE SET NULL;

CREATE TABLE system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(120) NOT NULL UNIQUE,
  value_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(180) DEFAULT 'NACOS FCAI',
  department_logo VARCHAR(255) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  contact_email VARCHAR(150) DEFAULT 'nacosfcaishiagu@gmail.com',
  contact_phone VARCHAR(60) DEFAULT NULL,
  current_academic_session VARCHAR(40) DEFAULT NULL,
  registration_status VARCHAR(40) DEFAULT 'open',
  maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
  bank_name VARCHAR(120) DEFAULT NULL,
  account_name VARCHAR(150) DEFAULT NULL,
  account_number VARCHAR(60) DEFAULT NULL,
  payment_instructions TEXT DEFAULT NULL,
  smtp_host VARCHAR(120) DEFAULT NULL,
  smtp_port INT DEFAULT NULL,
  smtp_username VARCHAR(150) DEFAULT NULL,
  smtp_password VARCHAR(255) DEFAULT NULL,
  smtp_encryption VARCHAR(20) DEFAULT 'tls',
  facebook VARCHAR(255) DEFAULT NULL,
  tiktok VARCHAR(255) DEFAULT NULL,
  x_handle VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payment_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_name VARCHAR(120) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  account_number VARCHAR(60) NOT NULL,
  payment_instructions TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  qr_code_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  payment_account_id INT UNSIGNED DEFAULT NULL,
  payment_type VARCHAR(120) NOT NULL,
  session VARCHAR(40) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_reference VARCHAR(120) NOT NULL UNIQUE,
  payment_proof VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','rejected','verified') NOT NULL DEFAULT 'pending',
  membership_number VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id) ON DELETE SET NULL,
  INDEX idx_payments_status (status)
) ENGINE=InnoDB;

CREATE TABLE receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  receipt_number VARCHAR(120) NOT NULL UNIQUE,
  verification_code VARCHAR(120) NOT NULL UNIQUE,
  pdf_path VARCHAR(255) DEFAULT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(120) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description TEXT DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE student_bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  bill_id INT UNSIGNED NOT NULL,
  amount_due DECIMAL(10,2) NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('unpaid','paid','partial') NOT NULL DEFAULT 'unpaid',
  due_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  programme VARCHAR(120) NOT NULL,
  level VARCHAR(40) NOT NULL,
  semester VARCHAR(40) NOT NULL,
  course VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(60) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  venue VARCHAR(180) NOT NULL,
  event_date DATE NOT NULL,
  event_time TIME NOT NULL,
  description TEXT NOT NULL,
  registration_link VARCHAR(255) DEFAULT NULL,
  poster_path VARCHAR(255) DEFAULT NULL,
  is_upcoming TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE event_registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  sender VARCHAR(80) NOT NULL DEFAULT 'Student',
  content TEXT NOT NULL,
  likes INT UNSIGNED NOT NULL DEFAULT 0,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_community_posts_student (student_id),
  INDEX idx_community_posts_pinned (pinned)
) ENGINE=InnoDB;

CREATE TABLE community_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_community_comments_post (post_id),
  INDEX idx_community_comments_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE community_post_reactions (
  post_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  reaction VARCHAR(16) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, student_id),
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_post_hidden (
  post_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, student_id),
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE community_likes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_community_like (post_id, student_id),
  INDEX idx_community_likes_post (post_id),
  INDEX idx_community_likes_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE assistant_knowledge (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  keyword VARCHAR(255) NOT NULL,
  response TEXT NOT NULL,
  category VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_assistant_knowledge_keyword (keyword),
  INDEX idx_assistant_knowledge_category (category)
) ENGINE=InnoDB;

CREATE TABLE visitor_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  admin_reply TEXT DEFAULT NULL,
  status ENUM('new','pending','resolved') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visitor_messages_status (status),
  INDEX idx_visitor_messages_email (email)
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  category VARCHAR(80) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE support_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE certificate_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  programme VARCHAR(120) NOT NULL,
  department VARCHAR(120) NOT NULL,
  session VARCHAR(40) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  certificate_number VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  certificate_path VARCHAR(255) DEFAULT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES certificate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE idcard_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  session VARCHAR(40) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  card_number VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE idcards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  card_path VARCHAR(255) DEFAULT NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES idcard_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(180) NOT NULL,
  details TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  activity VARCHAR(180) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ========================================
-- MERGED FROM database_extensions.sql
-- (E-voting, certificate generation, gender field extensions)
-- ========================================

-- ===== MODULE 1: E-VOTING MANAGEMENT =====

-- Elections table
CREATE TABLE IF NOT EXISTS elections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  status ENUM('draft','scheduled','active','closed') DEFAULT 'draft',
  eligible_voter_count INT UNSIGNED DEFAULT NULL,
  created_by INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_dates (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Election positions table
CREATE TABLE IF NOT EXISTS election_positions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  position_name VARCHAR(100) NOT NULL,
  description TEXT,
  display_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  UNIQUE KEY unique_position_per_election (election_id, position_name),
  INDEX idx_election (election_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidates table
CREATE TABLE IF NOT EXISTS candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  manifesto TEXT,
  image_path VARCHAR(255),
  image_upload_date DATETIME,
  approved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  FOREIGN KEY (position_id) REFERENCES election_positions(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_candidate_per_position (position_id, student_id),
  INDEX idx_election (election_id),
  INDEX idx_position (position_id),
  INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes table
CREATE TABLE IF NOT EXISTS votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE RESTRICT,
  FOREIGN KEY (position_id) REFERENCES election_positions(id) ON DELETE RESTRICT,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE RESTRICT,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY unique_vote_per_student_position (student_id, position_id, election_id),
  INDEX idx_election (election_id),
  INDEX idx_candidate (candidate_id),
  INDEX idx_student (student_id),
  INDEX idx_voted_at (voted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== MODULE 2: CERTIFICATE GENERATION =====

-- Event participants table (extends existing events)
CREATE TABLE IF NOT EXISTS event_participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone_number VARCHAR(20),
  department VARCHAR(100),
  level VARCHAR(50),
  payment_status ENUM('pending','verified','approved','rejected') DEFAULT 'pending',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_participant_per_event (event_id, student_id),
  INDEX idx_event (event_id),
  INDEX idx_student (student_id),
  INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificates table
CREATE TABLE IF NOT EXISTS certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  event_participant_id INT UNSIGNED,
  certificate_number VARCHAR(100) UNIQUE,
  pdf_path VARCHAR(255),
  status ENUM('pending','generated','sent','viewed') DEFAULT 'pending',
  generated_at DATETIME,
  sent_at DATETIME,
  viewed_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (event_participant_id) REFERENCES event_participants(id) ON DELETE SET NULL,
  INDEX idx_event (event_id),
  INDEX idx_student (student_id),
  INDEX idx_status (status),
  INDEX idx_certificate_number (certificate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== MODULE 3: GENDER FIELD EXTENSION =====

-- Add gender field to students table if it doesn't exist
ALTER TABLE students
ADD COLUMN IF NOT EXISTS gender ENUM('Male','Female','Other','Prefer not to say') DEFAULT NULL AFTER level;

-- ========================================
-- INDEXES FOR PERFORMANCE
-- ========================================

ALTER TABLE elections ADD INDEX IF NOT EXISTS idx_created_by (created_by);
ALTER TABLE candidates ADD INDEX IF NOT EXISTS idx_approved (approved);
ALTER TABLE event_participants ADD INDEX IF NOT EXISTS idx_registered_at (registered_at);
ALTER TABLE certificates ADD INDEX IF NOT EXISTS idx_generated_at (generated_at);

-- ========================================
-- END OF MERGED EXTENSIONS
-- ========================================




