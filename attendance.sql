-- ============================================
-- ATTENDANCE SYSTEM DATABASE - MIGRATION
-- Updated for Face Operator & Multi-Dashboard
-- 4 Roles: SuperAdmin, Admin, Employee, Face Operator
-- ⚠️ PRESERVES ALL EXISTING DATA
-- ============================================

USE attendance;

-- ============================================
-- ALTER USERS TABLE - ADD MISSING COLUMNS & UPDATE ROLE ENUM
-- ============================================

-- Update role ENUM to include 'face_operator'
ALTER TABLE users MODIFY role ENUM('suparadmin', 'admin', 'employee', 'face_operator') NOT NULL DEFAULT 'employee';

-- Add missing columns if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_role VARCHAR(50);
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add indexes if they don't exist
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_phone (phone);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_employee_id (employee_id);

-- ============================================
-- ALTER ATTENDANCE TABLE - ENSURE PROPER STRUCTURE
-- ============================================

ALTER TABLE attendance ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add indexes if they don't exist
ALTER TABLE attendance ADD UNIQUE KEY IF NOT EXISTS unique_daily_record (user_id, date);
ALTER TABLE attendance ADD INDEX IF NOT EXISTS idx_date (date);
ALTER TABLE attendance ADD INDEX IF NOT EXISTS idx_user_id (user_id);

-- ============================================
-- ENSURE ALL REQUIRED TABLES EXIST
-- ============================================

-- Companies Table
CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shifts Table
CREATE TABLE IF NOT EXISTS shifts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT DATA (if not exists)
-- ============================================

-- Insert default departments if empty
INSERT INTO departments (name) VALUES ('HR'), ('IT'), ('Finance'), ('Sales'), ('Operations')
ON DUPLICATE KEY UPDATE name = name;

-- Insert default companies if empty
INSERT INTO companies (name) VALUES ('Company ABC'), ('Company XYZ')
ON DUPLICATE KEY UPDATE name = name;

-- Insert default locations if empty
INSERT INTO locations (name) VALUES ('Head Office'), ('Branch Office 1'), ('Branch Office 2')
ON DUPLICATE KEY UPDATE name = name;

-- Insert default shifts if empty
INSERT INTO shifts (name, start_time, end_time) VALUES 
('Morning', '09:00:00', '17:00:00'), 
('Evening', '14:00:00', '22:00:00'), 
('Night', '22:00:00', '06:00:00')
ON DUPLICATE KEY UPDATE name = name;
