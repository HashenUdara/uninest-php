-- Uninest LMS Database Schema
-- Run this file against your MySQL database to set up the tables.

CREATE DATABASE IF NOT EXISTS uninest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE uninest;

-- ──────────────────────────────────────
-- Users
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'coordinator', 'moderator', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Subjects
-- ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────
-- Seed data (optional, for testing)
-- ──────────────────────────────────────

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@uninest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Moderator user (password: mod123)
INSERT INTO users (name, email, password, role) VALUES
('Moderator User', 'mod@uninest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator');

-- Sample subjects
INSERT INTO subjects (code, name, description, credits, created_by) VALUES
('CS101', 'Introduction to Computer Science', 'Fundamentals of computing and programming concepts.', 4, 1),
('CS201', 'Data Structures & Algorithms', 'Study of fundamental data structures and algorithmic techniques.', 3, 1),
('CS301', 'Database Systems', 'Relational databases, SQL, normalization, and database design.', 3, 1),
('MA101', 'Calculus I', 'Limits, derivatives, integrals, and applications.', 4, 1),
('EN101', 'Academic English', 'Academic writing, reading comprehension, and critical thinking.', 2, 1);
