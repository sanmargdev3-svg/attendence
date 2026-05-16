<?php
/**
 * Database Migration Script
 * Apply attendance.sql to database
 */

include('config/db.php');

echo "🔧 Applying Database Migration...\n\n";

// Read SQL file
$sql_file = 'attendance.sql';
$sql_content = file_get_contents($sql_file);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($statements as $statement) {
    // Skip comments and empty statements
    if (empty($statement) || strpos(trim($statement), '--') === 0) {
        continue;
    }
    
    // Execute statement
    if ($conn->query($statement) === TRUE) {
        $success_count++;
        echo "✅ " . substr($statement, 0, 80) . "...\n";
    } else {
        $error_count++;
        $errors[] = $conn->error;
        echo "❌ Error: " . $conn->error . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ MIGRATION COMPLETE!\n";
echo str_repeat("=", 60) . "\n";
echo "Successful: $success_count statements\n";
echo "Errors: $error_count statements\n";

if ($error_count > 0) {
    echo "\n⚠️ Errors encountered:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Verify users table has face_operator role
echo "\n🔍 Verification:\n";
$check = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
$role_col = $check->fetch_assoc();
echo "Users table 'role' column: " . $role_col['Type'] . "\n";

// Show current user counts by role
$roles = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
echo "\nCurrent Users by Role:\n";
while ($row = $roles->fetch_assoc()) {
    echo "  - " . $row['role'] . ": " . $row['count'] . " users\n";
}

echo "\n✅ Database is ready!\n";
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        pre { background: white; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <pre id="output"></pre>
</body>
</html>
