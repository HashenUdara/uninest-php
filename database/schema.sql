-- Uninest Kuppi Platform Database Schema
-- Run this file against your MySQL database to set up the tables.

CREATE DATABASE IF NOT EXISTS uninest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE uninest;

-- ──────────────────────────────────────
-- Universities (Sri Lanka)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS universities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Users
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'coordinator', 'moderator', 'admin') DEFAULT 'student',
    academic_year TINYINT UNSIGNED NULL,
    university_id INT NULL,
    batch_id INT NULL,
    first_approved_batch_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_university (university_id),
    INDEX idx_users_batch (batch_id),
    INDEX idx_users_first_approved_batch (first_approved_batch_id),
    CONSTRAINT fk_users_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Password Reset Tokens
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_lookup (email, token_hash, used_at, expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Batches (moderator-owned)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(20) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    program VARCHAR(150) NOT NULL,
    intake_year SMALLINT UNSIGNED NOT NULL,
    university_id INT NOT NULL,
    moderator_user_id INT NOT NULL UNIQUE,
    status ENUM('pending', 'approved', 'rejected', 'inactive') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batches_status (status),
    INDEX idx_batches_university (university_id),
    CONSTRAINT fk_batches_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE RESTRICT,
    CONSTRAINT fk_batches_moderator FOREIGN KEY (moderator_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_batches_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add users -> batches FK after batches exists (avoids circular create-order issues)
SET @has_fk_users_batch = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_users_batch'
);
SET @sql_users_batch_fk = IF(
    @has_fk_users_batch = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_users_batch_fk FROM @sql_users_batch_fk;
EXECUTE stmt_users_batch_fk;
DEALLOCATE PREPARE stmt_users_batch_fk;

SET @has_users_first_approved_batch_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'first_approved_batch_id'
);
SET @sql_users_first_approved_batch_column = IF(
    @has_users_first_approved_batch_column = 0,
    'ALTER TABLE users ADD COLUMN first_approved_batch_id INT NULL AFTER batch_id',
    'SELECT 1'
);
PREPARE stmt_users_first_approved_batch_column FROM @sql_users_first_approved_batch_column;
EXECUTE stmt_users_first_approved_batch_column;
DEALLOCATE PREPARE stmt_users_first_approved_batch_column;

SET @has_idx_users_first_approved_batch = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_first_approved_batch'
);
SET @sql_users_first_approved_batch_index = IF(
    @has_idx_users_first_approved_batch = 0,
    'ALTER TABLE users ADD INDEX idx_users_first_approved_batch (first_approved_batch_id)',
    'SELECT 1'
);
PREPARE stmt_users_first_approved_batch_index FROM @sql_users_first_approved_batch_index;
EXECUTE stmt_users_first_approved_batch_index;
DEALLOCATE PREPARE stmt_users_first_approved_batch_index;

SET @has_fk_users_first_approved_batch = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_users_first_approved_batch'
);
SET @sql_users_first_approved_batch_fk = IF(
    @has_fk_users_first_approved_batch = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_first_approved_batch FOREIGN KEY (first_approved_batch_id) REFERENCES batches(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_users_first_approved_batch_fk FROM @sql_users_first_approved_batch_fk;
EXECUTE stmt_users_first_approved_batch_fk;
DEALLOCATE PREPARE stmt_users_first_approved_batch_fk;

-- ──────────────────────────────────────
-- Student batch join requests
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS student_batch_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL UNIQUE,
    requested_batch_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by INT NULL,
    reviewed_role ENUM('moderator', 'admin') NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_requests_status (status),
    INDEX idx_student_requests_batch (requested_batch_id),
    CONSTRAINT fk_student_requests_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_requests_batch FOREIGN KEY (requested_batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Subjects (batch-scoped)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subject_code_per_batch (batch_id, code),
    INDEX idx_subjects_batch (batch_id),
    CONSTRAINT fk_subjects_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_subjects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Existing deployments that migrate from the previous schema should remove legacy global subjects
-- before adding the NOT NULL batch_id constraint to avoid orphaned content.
DELETE FROM subjects;

-- ──────────────────────────────────────
-- Seed data (optional, for testing)
-- ──────────────────────────────────────

INSERT INTO universities (name, short_code, is_active) VALUES
('University of Colombo', 'UOC', 1),
('University of Peradeniya', 'UOP', 1),
('University of Sri Jayewardenepura', 'USJ', 1),
('University of Kelaniya', 'UOK', 1),
('University of Moratuwa', 'UOM', 1),
('University of Jaffna', 'UOJ', 1),
('University of Ruhuna', 'UOR', 1),
('Rajarata University of Sri Lanka', 'RUSL', 1),
('Sabaragamuwa University of Sri Lanka', 'SUSL', 1),
('Wayamba University of Sri Lanka', 'WUSL', 1),
('Uva Wellassa University', 'UWU', 1),
('South Eastern University of Sri Lanka', 'SEUSL', 1),
('Eastern University, Sri Lanka', 'EUSL', 1),
('University of Vavuniya', 'UOV', 1),
('The Open University of Sri Lanka', 'OUSL', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    is_active = VALUES(is_active);

-- Seed users (password for all seeded users: 123)
SET @seed_password_123 = '$2y$12$4sVZY2Cz8Lu71OKcmQa2lec45cNXKraA4OQryLa8.hNVQWDTBiOHu';

INSERT INTO users (name, email, password, role, academic_year, university_id, batch_id, first_approved_batch_id) VALUES
('Admin User', 'admin@uninest.com', @seed_password_123, 'admin', NULL, NULL, NULL, NULL),
('Moderator 1', 'm1@uninest.com', @seed_password_123, 'moderator', 3, (SELECT id FROM universities WHERE short_code = 'UOC' LIMIT 1), NULL, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role),
    academic_year = VALUES(academic_year),
    university_id = VALUES(university_id);

SET @seed_admin_id = (SELECT id FROM users WHERE email = 'admin@uninest.com' LIMIT 1);
SET @seed_moderator_id = (SELECT id FROM users WHERE email = 'm1@uninest.com' LIMIT 1);
SET @seed_uoc_id = (SELECT id FROM universities WHERE short_code = 'UOC' LIMIT 1);

-- Approved batch under moderator m1@uninest.com
INSERT INTO batches (
    batch_code,
    name,
    program,
    intake_year,
    university_id,
    moderator_user_id,
    status,
    rejection_reason,
    reviewed_by,
    reviewed_at
) VALUES (
    'BATCH-A1B2C3',
    'CS 24/25',
    'BSc Computer Science',
    2024,
    @seed_uoc_id,
    @seed_moderator_id,
    'approved',
    NULL,
    @seed_admin_id,
    NOW()
)
ON DUPLICATE KEY UPDATE
    batch_code = VALUES(batch_code),
    name = VALUES(name),
    program = VALUES(program),
    intake_year = VALUES(intake_year),
    university_id = VALUES(university_id),
    status = VALUES(status),
    rejection_reason = VALUES(rejection_reason),
    reviewed_by = VALUES(reviewed_by),
    reviewed_at = VALUES(reviewed_at);

SET @seed_batch_id = (SELECT id FROM batches WHERE moderator_user_id = @seed_moderator_id LIMIT 1);

-- Ensure moderator is assigned to his batch
UPDATE users
SET batch_id = @seed_batch_id
WHERE id = @seed_moderator_id;

-- Seed 10 students under moderator's batch
INSERT INTO users (name, email, password, role, academic_year, university_id, batch_id, first_approved_batch_id) VALUES
('Student 1',  'st1@uninest.com',  @seed_password_123, 'student', 1, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 2',  'st2@uninest.com',  @seed_password_123, 'student', 1, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 3',  'st3@uninest.com',  @seed_password_123, 'student', 2, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 4',  'st4@uninest.com',  @seed_password_123, 'student', 2, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 5',  'st5@uninest.com',  @seed_password_123, 'student', 2, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 6',  'st6@uninest.com',  @seed_password_123, 'student', 3, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 7',  'st7@uninest.com',  @seed_password_123, 'student', 3, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 8',  'st8@uninest.com',  @seed_password_123, 'student', 3, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 9',  'st9@uninest.com',  @seed_password_123, 'student', 4, @seed_uoc_id, @seed_batch_id, @seed_batch_id),
('Student 10', 'st10@uninest.com', @seed_password_123, 'student', 4, @seed_uoc_id, @seed_batch_id, @seed_batch_id)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role),
    academic_year = VALUES(academic_year),
    university_id = VALUES(university_id),
    batch_id = VALUES(batch_id),
    first_approved_batch_id = VALUES(first_approved_batch_id);

-- Ensure join-request rows exist/align for seeded students
INSERT INTO student_batch_requests (
    student_user_id,
    requested_batch_id,
    status,
    rejection_reason,
    reviewed_by,
    reviewed_role,
    reviewed_at
)
SELECT
    u.id AS student_user_id,
    @seed_batch_id AS requested_batch_id,
    'approved' AS status,
    NULL AS rejection_reason,
    @seed_admin_id AS reviewed_by,
    'admin' AS reviewed_role,
    NOW() AS reviewed_at
FROM users u
WHERE u.email IN (
    'st1@uninest.com',
    'st2@uninest.com',
    'st3@uninest.com',
    'st4@uninest.com',
    'st5@uninest.com',
    'st6@uninest.com',
    'st7@uninest.com',
    'st8@uninest.com',
    'st9@uninest.com',
    'st10@uninest.com'
)
ON DUPLICATE KEY UPDATE
    requested_batch_id = VALUES(requested_batch_id),
    status = VALUES(status),
    rejection_reason = VALUES(rejection_reason),
    reviewed_by = VALUES(reviewed_by),
    reviewed_role = VALUES(reviewed_role),
    reviewed_at = VALUES(reviewed_at);
