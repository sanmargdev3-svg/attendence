<?php
// Database Migration Script
// This script adds new columns for face recognition multi-user attendance system

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// Array of migrations
$migrations = [
    // Add profile photo column to users table
    "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL COMMENT 'Path to employee profile photo for face recognition'",
    
    // Add source column to attendance table (track which system recorded attendance)
    "ALTER TABLE attendance ADD COLUMN source ENUM('dashboard', 'face_recognition', 'admin') DEFAULT 'dashboard' COMMENT 'Source of attendance record'",
    
    // Add photo_match_confidence for face recognition accuracy tracking
    "ALTER TABLE attendance ADD COLUMN photo_match_confidence INT DEFAULT NULL COMMENT 'Face recognition match confidence percentage'",
    
    // Add is_first_in and is_last_out flags for first-in-last-out logic
    "ALTER TABLE attendance ADD COLUMN is_first_in BOOLEAN DEFAULT FALSE COMMENT 'Whether this is first punch in of the day'",
    "ALTER TABLE attendance ADD COLUMN is_last_out BOOLEAN DEFAULT FALSE COMMENT 'Whether this is last punch out of the day'",
    
    // Create new table for attendance records with more granular tracking
    "CREATE TABLE IF NOT EXISTS attendance_records (
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
    )"
];

// Execute migrations
$success = true;
$messages = [];

foreach ($migrations as $migration) {
    if (!$conn->query($migration)) {
        // Check if error is due to column already existing
        if (strpos($conn->error, "Duplicate column") !== false) {
            $messages[] = "⚠ Column already exists: " . $conn->error;
        } else if (strpos($conn->error, "already exists") !== false) {
            $messages[] = "⚠ Table already exists";
        } else {
            $success = false;
            $messages[] = "✗ Error: " . $conn->error;
        }
    } else {
        $messages[] = "✓ Migration successful";
    }
}

$conn->close();

return [
    'success' => $success,
    'messages' => $messages
];
?>
