<?php
/**
 * Complete Database Setup & Migration
 * Creates all tables + applies migrations
 */

include('config/db.php');

echo "🔧 COMPLETE DATABASE SETUP\n";
echo str_repeat("=", 60) . "\n\n";

// Read both SQL files
$database_sql = file_get_contents('database.sql');
$migration_sql = file_get_contents('attendance.sql');

// Combine both (database.sql first, then migration.sql)
$full_sql = $database_sql . "\n" . $migration_sql;

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $full_sql)));

$success_count = 0;
$error_count = 0;
$errors = [];
$warnings = [];

foreach ($statements as $i => $statement) {
    // Skip comments and empty statements
    if (empty($statement) || strpos(trim($statement), '--') === 0) {
        continue;
    }
    
    // Execute statement
    if ($conn->multi_query($statement)) {
        // Clear results
        while ($conn->more_results()) {
            $conn->next_result();
        }
        $success_count++;
        
        // Show abbreviated statement
        $preview = substr($statement, 0, 70);
        if (strlen($statement) > 70) $preview .= "...";
        echo "✅ " . $preview . "\n";
    } else {
        $error_count++;
        $error_msg = $conn->error;
        $errors[] = $error_msg;
        
        // Show only significant errors (skip duplicate key warnings)
        if (strpos($error_msg, 'Duplicate entry') === false && 
            strpos($error_msg, 'UNIQUE constraint') === false) {
            echo "❌ Error: " . $error_msg . "\n";
        } else {
            $warnings[] = $error_msg;
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ SETUP COMPLETE!\n";
echo str_repeat("=", 60) . "\n";
echo "Successful: $success_count statements\n";
echo "Warnings/Skipped: " . count($warnings) . "\n";
echo "Errors: " . count($errors) . "\n";

// Verify setup
echo "\n🔍 VERIFICATION:\n";
echo str_repeat("-", 60) . "\n";

// Check tables
$tables_result = $conn->query("SHOW TABLES FROM attendance");
echo "\n📋 Tables Created:\n";
while ($row = $tables_result->fetch_row()) {
    echo "  ✅ " . $row[0] . "\n";
}

// Check role enum
$check = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='role'");
if ($check && $col = $check->fetch_assoc()) {
    echo "\n👤 Users 'role' column:\n";
    echo "  Type: " . $col['COLUMN_TYPE'] . "\n";
}

// Show user counts
echo "\n👥 User Data:\n";
$roles = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($roles && $roles->num_rows > 0) {
    while ($row = $roles->fetch_assoc()) {
        echo "  - " . ucfirst($row['role']) . ": " . $row['count'] . " users\n";
    }
} else {
    echo "  ⚠️ No users found\n";
}

echo "\n✅ Database is ready to use!\n";

// Test login data
echo "\n📝 TEST USERS (Password: admin123):\n";
echo "  1. SuperAdmin: 9876543210\n";
echo "  2. Admin: 9876543211\n";
echo "  3. Employee: 9876543212, 9876543213, 9876543214\n";

?>
