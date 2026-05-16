<?php
/**
 * Complete Setup: Create Tables + Apply Safe Migration
 * PRESERVES ALL EXISTING DATA - No DROP DATABASE
 */

include('config/db.php');

echo "🔧 DATABASE SETUP & MIGRATION\n";
echo str_repeat("=", 60) . "\n\n";

// Step 1: Create all tables if they don't exist
echo "📋 STEP 1: Creating tables if missing...\n";
echo str_repeat("-", 60) . "\n";

$create_tables = [
    // Users Table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        role ENUM('suparadmin', 'admin', 'employee', 'face_operator') NOT NULL DEFAULT 'employee',
        department VARCHAR(100),
        employee_id VARCHAR(50),
        company VARCHAR(100),
        phone VARCHAR(20) NOT NULL UNIQUE,
        profile_photo VARCHAR(255),
        dashboard_role VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_phone (phone),
        INDEX idx_role (role),
        INDEX idx_employee_id (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Attendance Table
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        punch_in TIME,
        punch_out TIME,
        status ENUM('Present', 'Absent', 'Leave', 'Late') DEFAULT 'Absent',
        selfie_punchin VARCHAR(255) DEFAULT NULL,
        selfie_punchout VARCHAR(255) DEFAULT NULL,
        punch_in_lat DECIMAL(10, 8) DEFAULT NULL,
        punch_in_lng DECIMAL(11, 8) DEFAULT NULL,
        punch_in_accuracy FLOAT DEFAULT NULL,
        punch_out_lat DECIMAL(10, 8) DEFAULT NULL,
        punch_out_lng DECIMAL(11, 8) DEFAULT NULL,
        punch_out_accuracy FLOAT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_daily_record (user_id, date),
        INDEX idx_date (date),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Departments Table
    "CREATE TABLE IF NOT EXISTS departments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Locations Table
    "CREATE TABLE IF NOT EXISTS locations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Companies Table
    "CREATE TABLE IF NOT EXISTS companies (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Shifts Table
    "CREATE TABLE IF NOT EXISTS shifts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // OD Records Table
    "CREATE TABLE IF NOT EXISTS od_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        od_date DATE NOT NULL,
        marked_by INT NOT NULL,
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id),
        UNIQUE KEY unique_od (user_id, od_date),
        INDEX idx_user_id (user_id),
        INDEX idx_od_date (od_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($create_tables as $sql) {
    if ($conn->query($sql)) {
        echo "✅ " . substr(trim($sql), 0, 50) . "...\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
}

echo "\n📊 STEP 2: Applying safe migration...\n";
echo str_repeat("-", 60) . "\n";

// Step 2: Apply migration SQL (ALTER TABLE statements)
$migration_sql = file_get_contents('attendance.sql');
$migration_sql = str_replace('USE attendance;', '', $migration_sql);

$statements = array_filter(array_map('trim', explode(';', $migration_sql)));

$success = 0;
$skipped = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos(trim($statement), '--') === 0) {
        continue;
    }
    
    if ($conn->multi_query($statement)) {
        while ($conn->more_results()) {
            $conn->next_result();
        }
        $success++;
        echo "✅ " . substr($statement, 0, 60) . "...\n";
    } else {
        $error = $conn->error;
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'already exists') !== false) {
            $skipped++;
            echo "⚠️  Skipped: " . substr($statement, 0, 60) . "...\n";
        } else {
            echo "❌ Error: $error\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ SETUP COMPLETE!\n";
echo str_repeat("=", 60) . "\n";

// Verification
echo "\n🔍 DATABASE STATUS:\n";

// Tables
$tables = $conn->query("SHOW TABLES FROM attendance");
echo "\n📋 Tables: " . $tables->num_rows . " tables created\n";

// Users
$users = $conn->query("SELECT COUNT(*) as count FROM users");
$u = $users->fetch_assoc();
echo "👥 Total Users: " . $u['count'] . "\n";

// User roles
$roles = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($roles->num_rows > 0) {
    echo "\n   By Role:\n";
    while ($row = $roles->fetch_assoc()) {
        echo "   - " . ucfirst($row['role']) . ": " . $row['count'] . "\n";
    }
}

// Attendance records
$att = $conn->query("SELECT COUNT(*) as count FROM attendance");
$a = $att->fetch_assoc();
echo "\n📊 Attendance Records: " . $a['count'] . "\n";

echo "\n✅ All systems ready!\n";
echo "✅ All your existing data is preserved.\n";
?>
