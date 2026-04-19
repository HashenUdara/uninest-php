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
    academic_year TINYINT UNSIGNED NOT NULL DEFAULT 1,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subject_code_per_batch (batch_id, code),
    INDEX idx_subjects_batch (batch_id),
    INDEX idx_subjects_status (status),
    CONSTRAINT fk_subjects_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_subjects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @has_subjects_academic_year_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subjects'
      AND COLUMN_NAME = 'academic_year'
);
SET @sql_subjects_academic_year_column = IF(
    @has_subjects_academic_year_column = 0,
    'ALTER TABLE subjects ADD COLUMN academic_year TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER credits',
    'SELECT 1'
);
PREPARE stmt_subjects_academic_year_column FROM @sql_subjects_academic_year_column;
EXECUTE stmt_subjects_academic_year_column;
DEALLOCATE PREPARE stmt_subjects_academic_year_column;

SET @has_subjects_semester_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subjects'
      AND COLUMN_NAME = 'semester'
);
SET @sql_subjects_semester_column = IF(
    @has_subjects_semester_column = 0,
    'ALTER TABLE subjects ADD COLUMN semester TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER academic_year',
    'SELECT 1'
);
PREPARE stmt_subjects_semester_column FROM @sql_subjects_semester_column;
EXECUTE stmt_subjects_semester_column;
DEALLOCATE PREPARE stmt_subjects_semester_column;

SET @has_subjects_status_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subjects'
      AND COLUMN_NAME = 'status'
);
SET @sql_subjects_status_column = IF(
    @has_subjects_status_column = 0,
    'ALTER TABLE subjects ADD COLUMN status ENUM(''upcoming'', ''in_progress'', ''completed'') NOT NULL DEFAULT ''upcoming'' AFTER semester',
    'SELECT 1'
);
PREPARE stmt_subjects_status_column FROM @sql_subjects_status_column;
EXECUTE stmt_subjects_status_column;
DEALLOCATE PREPARE stmt_subjects_status_column;

UPDATE subjects
SET academic_year = 1
WHERE academic_year IS NULL OR academic_year < 1 OR academic_year > 4;

UPDATE subjects
SET semester = 1
WHERE semester IS NULL OR semester < 1 OR semester > 2;

UPDATE subjects
SET status = 'upcoming'
WHERE status IS NULL OR status NOT IN ('upcoming', 'in_progress', 'completed');

CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_topics_subject_sort (subject_id, sort_order, id),
    INDEX idx_topics_created_by (created_by),
    CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_topics_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Subject Quizzes (subject-scoped, approval-gated)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    created_by_user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    mode ENUM('practice', 'exam') NOT NULL,
    status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    rejection_reason TEXT NULL,
    reviewed_by_user_id INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quizzes_review_queue (status, subject_id, created_at, id),
    INDEX idx_quizzes_published_browse (subject_id, status, updated_at, id),
    INDEX idx_quizzes_subject_status_mode (subject_id, status, mode),
    INDEX idx_quizzes_creator (created_by_user_id),
    INDEX idx_quizzes_reviewer (reviewed_by_user_id),
    CONSTRAINT chk_quizzes_duration CHECK (duration_minutes BETWEEN 5 AND 180),
    CONSTRAINT fk_quizzes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_quizzes_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_quizzes_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quiz_questions_quiz_sort (quiz_id, sort_order, id),
    CONSTRAINT fk_quiz_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quiz_options_question_sort (question_id, sort_order, id),
    INDEX idx_quiz_options_question_correct (question_id, is_correct),
    CONSTRAINT fk_quiz_options_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('in_progress', 'submitted', 'auto_submitted') NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    correct_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_questions INT UNSIGNED NOT NULL DEFAULT 0,
    score_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quiz_attempts_quiz_user_submitted (quiz_id, user_id, submitted_at, id),
    INDEX idx_quiz_attempts_quiz_user_status (quiz_id, user_id, status, id),
    INDEX idx_quiz_attempts_user_status (user_id, status, id),
    CONSTRAINT chk_quiz_attempts_score CHECK (score_percent >= 0 AND score_percent <= 100),
    CONSTRAINT fk_quiz_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_attempt_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_quiz_attempt_question (attempt_id, question_id),
    INDEX idx_quiz_attempt_answers_attempt (attempt_id),
    INDEX idx_quiz_attempt_answers_question (question_id),
    CONSTRAINT fk_quiz_attempt_answers_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_attempt_answers_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_attempt_answers_selected_option FOREIGN KEY (selected_option_id) REFERENCES quiz_options(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- GPA Utility (batch-scoped grade scale + personal term records)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS gpa_batch_grade_scales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    letter_grade VARCHAR(12) NOT NULL,
    description VARCHAR(120) NULL,
    grade_point DECIMAL(3,2) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_user_id INT NOT NULL,
    updated_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gpa_grade_scale_letter (batch_id, letter_grade),
    INDEX idx_gpa_grade_scale_batch_sort (batch_id, sort_order, id),
    INDEX idx_gpa_grade_scale_batch_point (batch_id, grade_point),
    CONSTRAINT chk_gpa_grade_point_range CHECK (grade_point >= 0.00 AND grade_point <= 4.00),
    CONSTRAINT fk_gpa_grade_scale_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_gpa_grade_scale_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_gpa_grade_scale_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gpa_term_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    batch_id INT NOT NULL,
    academic_year TINYINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    semester_gpa DECIMAL(4,2) NOT NULL,
    total_credits DECIMAL(6,2) NOT NULL,
    graded_subject_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gpa_term_record (user_id, batch_id, academic_year, semester),
    INDEX idx_gpa_term_user_order (user_id, academic_year, semester, id),
    INDEX idx_gpa_term_batch_order (batch_id, academic_year, semester, id),
    CONSTRAINT chk_gpa_term_gpa_range CHECK (semester_gpa >= 0.00 AND semester_gpa <= 4.00),
    CONSTRAINT chk_gpa_term_credit_non_negative CHECK (total_credits >= 0.00),
    CONSTRAINT fk_gpa_term_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_gpa_term_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gpa_term_subject_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_record_id INT NOT NULL,
    subject_id INT NULL,
    subject_name_snapshot VARCHAR(255) NOT NULL,
    credit_value DECIMAL(5,2) NOT NULL,
    letter_grade VARCHAR(12) NOT NULL,
    grade_point_snapshot DECIMAL(3,2) NOT NULL,
    quality_points DECIMAL(7,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gpa_term_subject (term_record_id, subject_id),
    INDEX idx_gpa_term_entries_record (term_record_id, id),
    INDEX idx_gpa_term_entries_subject (subject_id),
    CONSTRAINT chk_gpa_term_entry_grade_point_range CHECK (grade_point_snapshot >= 0.00 AND grade_point_snapshot <= 4.00),
    CONSTRAINT chk_gpa_term_entry_credit_non_negative CHECK (credit_value >= 0.00),
    CONSTRAINT chk_gpa_term_entry_quality_non_negative CHECK (quality_points >= 0.00),
    CONSTRAINT fk_gpa_term_entries_record FOREIGN KEY (term_record_id) REFERENCES gpa_term_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_gpa_term_entries_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Topic Resources (topic-scoped, approval-based publishing)
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    uploaded_by_user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NOT NULL,
    category_other VARCHAR(120) NULL,
    source_type ENUM('file', 'link') NOT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    file_mime VARCHAR(120) NULL,
    file_size INT UNSIGNED NULL,
    external_url VARCHAR(2048) NULL,
    status ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by_user_id INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resources_topic_status (topic_id, status),
    INDEX idx_resources_uploaded_by (uploaded_by_user_id),
    INDEX idx_resources_status (status),
    CONSTRAINT fk_resources_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    CONSTRAINT fk_resources_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_resources_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resource_update_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    requested_by_user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NOT NULL,
    category_other VARCHAR(120) NULL,
    source_type ENUM('file', 'link') NOT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    file_mime VARCHAR(120) NULL,
    file_size INT UNSIGNED NULL,
    external_url VARCHAR(2048) NULL,
    status ENUM('pending', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    reviewed_by_user_id INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_resource_update_resource (resource_id),
    INDEX idx_resource_updates_status (status),
    INDEX idx_resource_updates_requested_by (requested_by_user_id),
    CONSTRAINT fk_resource_updates_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    CONSTRAINT fk_resource_updates_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_resource_updates_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resource_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    student_user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_resource_student_rating (resource_id, student_user_id),
    INDEX idx_resource_ratings_resource (resource_id),
    INDEX idx_resource_ratings_student (student_user_id),
    CONSTRAINT chk_resource_ratings_value CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_resource_ratings_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    CONSTRAINT fk_resource_ratings_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    subject_id INT NULL,
    author_user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    pinned_by_user_id INT NULL,
    pinned_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_batch_pin_created (batch_id, is_pinned, created_at, id),
    INDEX idx_announcements_batch_subject_created (batch_id, subject_id, created_at, id),
    INDEX idx_announcements_author (author_user_id),
    CONSTRAINT fk_announcements_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcements_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_announcements_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_announcements_pinned_by FOREIGN KEY (pinned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feed_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    subject_id INT NULL,
    author_user_id INT NULL,
    post_type ENUM('general', 'discussion', 'question', 'resource_share') NOT NULL DEFAULT 'general',
    body TEXT NULL,
    image_path VARCHAR(255) NULL,
    image_name VARCHAR(255) NULL,
    image_mime VARCHAR(120) NULL,
    image_size INT UNSIGNED NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    pinned_by_user_id INT NULL,
    pinned_at TIMESTAMP NULL DEFAULT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_by_user_id INT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    edited_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_feed_posts_batch_created (batch_id, created_at, id),
    INDEX idx_feed_posts_batch_pin_created (batch_id, is_pinned, created_at, id),
    INDEX idx_feed_posts_batch_pin_type (batch_id, is_pinned, post_type),
    INDEX idx_feed_posts_subject (subject_id),
    INDEX idx_feed_posts_author (author_user_id),
    INDEX idx_feed_posts_type (post_type),
    CONSTRAINT fk_feed_posts_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_posts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_feed_posts_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_feed_posts_pinned_by FOREIGN KEY (pinned_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_feed_posts_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feed_post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_feed_post_like (post_id, user_id),
    INDEX idx_feed_post_likes_post (post_id),
    INDEX idx_feed_post_likes_user (user_id),
    CONSTRAINT fk_feed_post_likes_post FOREIGN KEY (post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_post_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feed_post_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_feed_post_save (post_id, user_id),
    INDEX idx_feed_post_saves_user_created (user_id, created_at, id),
    INDEX idx_feed_post_saves_post (post_id),
    CONSTRAINT fk_feed_post_saves_post FOREIGN KEY (post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_post_saves_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feed_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    target_type ENUM('post', 'comment') NOT NULL,
    target_id INT NOT NULL,
    reporter_user_id INT NULL,
    reason ENUM('spam', 'harassment', 'misinformation', 'other') NOT NULL,
    details TEXT NULL,
    status ENUM('open', 'dismissed', 'resolved') NOT NULL DEFAULT 'open',
    reviewed_by_user_id INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    action_taken VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_feed_reports_batch_status_created (batch_id, status, created_at, id),
    INDEX idx_feed_reports_target_status (target_type, target_id, status),
    INDEX idx_feed_reports_reporter (reporter_user_id),
    INDEX idx_feed_reports_reviewed_by (reviewed_by_user_id),
    CONSTRAINT fk_feed_reports_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_feed_reports_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    subject_id INT NOT NULL,
    requested_by_user_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    tags_csv VARCHAR(500) NULL,
    status ENUM('open', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kuppi_requests_batch_status_created (batch_id, status, created_at, id),
    INDEX idx_kuppi_requests_batch_subject (batch_id, subject_id),
    INDEX idx_kuppi_requests_subject (subject_id),
    INDEX idx_kuppi_requests_requested_by (requested_by_user_id),
    CONSTRAINT fk_kuppi_requests_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_requests_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_requests_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_request_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('up', 'down') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kuppi_request_vote (request_id, user_id),
    INDEX idx_kuppi_request_votes_request (request_id),
    INDEX idx_kuppi_request_votes_user (user_id),
    INDEX idx_kuppi_request_votes_request_vote (request_id, vote_type),
    CONSTRAINT fk_kuppi_request_votes_request FOREIGN KEY (request_id) REFERENCES kuppi_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_request_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_conductor_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    applicant_user_id INT NOT NULL,
    motivation VARCHAR(300) NOT NULL,
    availability_csv VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kuppi_conductor_application (request_id, applicant_user_id),
    INDEX idx_kuppi_conductor_applications_request (request_id),
    INDEX idx_kuppi_conductor_applications_user (applicant_user_id),
    CONSTRAINT fk_kuppi_conductor_applications_request FOREIGN KEY (request_id) REFERENCES kuppi_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_conductor_applications_user FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_conductor_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    voter_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kuppi_conductor_vote (application_id, voter_user_id),
    INDEX idx_kuppi_conductor_votes_application (application_id),
    INDEX idx_kuppi_conductor_votes_voter (voter_user_id),
    CONSTRAINT fk_kuppi_conductor_votes_application FOREIGN KEY (application_id) REFERENCES kuppi_conductor_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_conductor_votes_voter FOREIGN KEY (voter_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_scheduled_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    subject_id INT NOT NULL,
    request_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    max_attendees SMALLINT UNSIGNED NOT NULL,
    location_type ENUM('physical', 'online') NOT NULL DEFAULT 'physical',
    location_text VARCHAR(255) NULL,
    meeting_link VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_by_user_id INT NOT NULL,
    cancelled_by_user_id INT NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kuppi_scheduled_batch_date (batch_id, session_date, start_time, id),
    INDEX idx_kuppi_scheduled_subject_date (subject_id, session_date, start_time, id),
    INDEX idx_kuppi_scheduled_status_date (status, session_date, start_time, id),
    INDEX idx_kuppi_scheduled_request_status (request_id, status),
    INDEX idx_kuppi_scheduled_created_by (created_by_user_id),
    CONSTRAINT fk_kuppi_scheduled_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_scheduled_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_scheduled_request FOREIGN KEY (request_id) REFERENCES kuppi_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_kuppi_scheduled_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_kuppi_scheduled_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_scheduled_session_hosts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    host_user_id INT NOT NULL,
    source_type ENUM('request_conductor', 'manual') NOT NULL,
    source_application_id INT NULL,
    assigned_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kuppi_scheduled_session_host (session_id, host_user_id),
    INDEX idx_kuppi_scheduled_host_session (session_id),
    INDEX idx_kuppi_scheduled_host_user (host_user_id),
    INDEX idx_kuppi_scheduled_host_application (source_application_id),
    CONSTRAINT fk_kuppi_scheduled_host_session FOREIGN KEY (session_id) REFERENCES kuppi_scheduled_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_scheduled_host_user FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_scheduled_host_application FOREIGN KEY (source_application_id) REFERENCES kuppi_conductor_applications(id) ON DELETE SET NULL,
    CONSTRAINT fk_kuppi_scheduled_host_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kuppi_university_timetable_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    reason VARCHAR(255) NULL,
    created_by_user_id INT NOT NULL,
    updated_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kuppi_timetable_batch_day_time (batch_id, day_of_week, start_time, end_time),
    INDEX idx_kuppi_timetable_batch_created (batch_id, created_at, id),
    CONSTRAINT chk_kuppi_timetable_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_kuppi_timetable_time_range CHECK (start_time < end_time),
    CONSTRAINT fk_kuppi_timetable_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_kuppi_timetable_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_kuppi_timetable_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(50) NOT NULL,
    target_id INT NOT NULL,
    parent_comment_id INT NULL,
    depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
    user_id INT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_comments_target (target_type, target_id, created_at, id),
    INDEX idx_comments_parent (parent_comment_id),
    INDEX idx_comments_user (user_id),
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subject_coordinators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    student_user_id INT NOT NULL,
    assigned_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subject_coordinator (subject_id, student_user_id),
    INDEX idx_subject_coordinators_subject (subject_id),
    INDEX idx_subject_coordinators_student (student_user_id),
    CONSTRAINT fk_subject_coordinators_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_subject_coordinators_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subject_coordinators_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
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
