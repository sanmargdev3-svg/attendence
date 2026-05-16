-- Database Migration for Face Recognition and Multi-Source Attendance System
-- Run this script to add required columns and tables

USE attendance;

-- Add columns to users table if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL COMMENT 'Path to employee profile photo for face recognition';

-- Add columns to attendance table if they don't exist
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS source ENUM('dashboard', 'face_recognition', 'admin') DEFAULT 'dashboard' COMMENT 'Source of attendance record';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS photo_match_confidence INT DEFAULT NULL COMMENT 'Face recognition match confidence percentage';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS is_first_in BOOLEAN DEFAULT FALSE COMMENT 'Whether this is first punch in of the day';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS is_last_out BOOLEAN DEFAULT FALSE COMMENT 'Whether this is last punch out of the day';

-- Create attendance_records table for granular tracking of multiple attendance entries
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    punch_time DATETIME NOT NULL,
    punch_type ENUM('in', 'out') NOT NULL,
    source ENUM('dashboard', 'face_recognition', 'admin') NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    photo_match_confidence INT DEFAULT NULL,
    gps_latitude DECIMAL(10, 8) DEFAULT NULL,
    gps_longitude DECIMAL(11, 8) DEFAULT NULL,
    gps_accuracy FLOAT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, attendance_date),
    INDEX idx_punch_time (punch_time)
);

-- Create employee_attendance_summary table for first-in-last-out calculation
CREATE TABLE IF NOT EXISTS employee_attendance_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    attendance_date DATE NOT NULL,
    first_punch_in DATETIME DEFAULT NULL,
    last_punch_out DATETIME DEFAULT NULL,
    first_punch_source ENUM('dashboard', 'face_recognition', 'admin') DEFAULT NULL,
    last_punch_source ENUM('dashboard', 'face_recognition', 'admin') DEFAULT NULL,
    first_punch_photo VARCHAR(255) DEFAULT NULL,
    last_punch_photo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, attendance_date)
);

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_source ON attendance(source);
CREATE INDEX IF NOT EXISTS idx_photo_confidence ON attendance(photo_match_confidence);
