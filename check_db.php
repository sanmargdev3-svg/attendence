<?php
/**
 * Database Status Checker
 */

include('config/db.php');

echo "🔍 DATABASE STATUS CHECK\n";
echo str_repeat("=", 60) . "\n\n";

// Check database exists
$db_check = $conn->query("SELECT DATABASE()");
$current_db = $db_check->fetch_row()[0];
echo "✅ Connected to database: " . ($current_db ?? 'NONE') . "\n\n";

// Check tables
echo "📋 Existing Tables:\n";
$tables = $conn->query("SHOW TABLES");
if ($tables->num_rows > 0) {
    while ($row = $tables->fetch_row()) {
        echo "  - $row[0]\n";
    }
} else {
    echo "  ⚠️ NO TABLES FOUND - Database is empty!\n";
}

// Check users table structure
echo "\n👤 Users Table Structure:\n";
$check = $conn->query("SHOW COLUMNS FROM users");
if ($check) {
    while ($row = $check->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  ⚠️ Users table does not exist!\n";
}

// Count users
echo "\n👥 User Count:\n";
$user_count = $conn->query("SELECT COUNT(*) FROM users");
if ($user_count) {
    $count = $user_count->fetch_row()[0];
    echo "  Total users: " . $count . "\n";
}

?>
