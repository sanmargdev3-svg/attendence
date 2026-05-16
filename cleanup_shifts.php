<?php
/**
 * Clean up shifts table - remove name field, allow duplicates
 */

include('config/db.php');

echo "🧹 CLEANING UP SHIFTS TABLE\n";
echo str_repeat("=", 60) . "\n\n";

// Drop the UNIQUE constraint on name if it exists
echo "📋 Removing UNIQUE constraint on 'name' field...\n";
$result = $conn->query("ALTER TABLE shifts DROP INDEX IF EXISTS name");
if ($result) {
    echo "✅ UNIQUE constraint removed\n";
} else {
    echo "⚠️ No unique constraint found (already removed)\n";
}

// Drop the name column if it exists
echo "\n📋 Dropping 'name' column...\n";
$result = $conn->query("ALTER TABLE shifts DROP COLUMN IF EXISTS name");
if ($result) {
    echo "✅ 'name' column dropped\n";
} else {
    echo "⚠️ Column not found (already removed)\n";
}

// Verify shifts table structure
echo "\n🔍 Shifts Table Structure:\n";
$columns = $conn->query("SHOW COLUMNS FROM shifts");
if ($columns) {
    while ($col = $columns->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}

// Show current shifts
echo "\n📊 Current Shifts:\n";
$shifts = $conn->query("SELECT id, start_time, end_time FROM shifts ORDER BY id");
if ($shifts && $shifts->num_rows > 0) {
    while ($row = $shifts->fetch_assoc()) {
        $start = date('h:i A', strtotime($row['start_time']));
        $end = date('h:i A', strtotime($row['end_time']));
        echo "  [" . $row['id'] . "] " . $start . " - " . $end . "\n";
    }
} else {
    echo "  No shifts found\n";
}

echo "\n✅ Shifts table cleaned! You can now add duplicate shifts freely.\n";
?>
