<?php
/**
 * Fix existing shifts - add name column if missing
 */

include('config/db.php');

echo "🔧 FIXING SHIFTS TABLE\n";
echo str_repeat("=", 60) . "\n\n";

// Check if name column exists
$check = $conn->query("SHOW COLUMNS FROM shifts LIKE 'name'");
if ($check && $check->num_rows === 0) {
    echo "📋 Adding 'name' column to shifts table...\n";
    
    $result = $conn->query("ALTER TABLE shifts ADD COLUMN name VARCHAR(100) UNIQUE");
    if ($result) {
        echo "✅ Column 'name' added successfully\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }
} else {
    echo "✅ Column 'name' already exists\n";
}

// Delete shifts with empty names
echo "\n🧹 Cleaning up empty shifts...\n";
$cleanup = $conn->query("DELETE FROM shifts WHERE name IS NULL OR name = ''");
if ($cleanup) {
    echo "✅ Empty shifts removed\n";
} else {
    echo "⚠️ No empty shifts to remove\n";
}

// Show all shifts
echo "\n📋 Current Shifts:\n";
$shifts = $conn->query("SELECT id, name, start_time, end_time FROM shifts ORDER BY start_time");
if ($shifts && $shifts->num_rows > 0) {
    while ($row = $shifts->fetch_assoc()) {
        echo "  - " . ($row['name'] ?? 'NO NAME') . " (" . $row['start_time'] . " - " . $row['end_time'] . ")\n";
    }
} else {
    echo "  No shifts found\n";
}

echo "\n✅ Shifts table is now clean!\n";
?>
