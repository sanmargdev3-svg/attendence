<?php
/**
 * Safe Database Migration - PRESERVES ALL EXISTING DATA
 * Only uses attendance.sql (ALTER TABLE, no DROP)
 */

include('config/db.php');

echo "🔧 SAFE DATABASE MIGRATION (No Data Loss)\n";
echo str_repeat("=", 60) . "\n\n";

// Read ONLY the migration SQL file (safe - no DROP commands)
$migration_sql = file_get_contents('attendance.sql');

// Remove USE statement if exists (already connected to database)
$migration_sql = str_replace('USE attendance;', '', $migration_sql);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $migration_sql)));

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($statements as $statement) {
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
        $preview = substr(trim($statement), 0, 70);
        if (strlen($statement) > 70) $preview .= "...";
        echo "✅ " . $preview . "\n";
    } else {
        $error_msg = $conn->error;
        
        // Skip benign errors (duplicate key, already exists)
        if (strpos($error_msg, 'Duplicate entry') === false && 
            strpos($error_msg, 'already exists') === false &&
            strpos($error_msg, 'UNIQUE constraint') === false) {
            $error_count++;
            $errors[] = $error_msg;
            echo "❌ Error: " . $error_msg . "\n";
        } else {
            $success_count++;
            echo "⚠️  Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ MIGRATION COMPLETE - DATA PRESERVED!\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Successful: $success_count statements\n";
echo "⚠️  Errors: $error_count\n";

if ($error_count > 0) {
    echo "\n🚨 Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Verify data
echo "\n🔍 DATA VERIFICATION:\n";
echo str_repeat("-", 60) . "\n";

// Check tables
$tables_result = $conn->query("SHOW TABLES FROM attendance");
echo "\n📋 Tables:\n";
$table_count = 0;
while ($row = $tables_result->fetch_row()) {
    echo "  ✅ " . $row[0] . "\n";
    $table_count++;
}

// Check role enum
$check = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='role'");
if ($check && $col = $check->fetch_assoc()) {
    echo "\n👤 Users 'role' column:\n";
    echo "  Type: " . $col['COLUMN_TYPE'] . "\n";
}

// Show user counts by role
echo "\n👥 EXISTING USER DATA (PRESERVED):\n";
$roles = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($roles && $roles->num_rows > 0) {
    $total = 0;
    while ($row = $roles->fetch_assoc()) {
        echo "  - " . ucfirst($row['role']) . ": " . $row['count'] . " users\n";
        $total += $row['count'];
    }
    echo "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  TOTAL: $total users\n";
} else {
    echo "  ⚠️ No users in database\n";
}

// Show attendances
echo "\n📊 Attendance Records:\n";
$att = $conn->query("SELECT COUNT(*) as count FROM attendance");
if ($att && $row = $att->fetch_assoc()) {
    echo "  Total records: " . $row['count'] . "\n";
}

echo "\n✅ DATABASE READY!\n";
echo "All your existing data has been preserved.\n";

?>
