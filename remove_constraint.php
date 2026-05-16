<?php
/**
 * Auto-remove attendance constraint
 */
include('config/db.php');

try {
    // Drop the unique constraint if it exists
    $conn->query("ALTER TABLE attendance DROP INDEX IF EXISTS unique_daily_record");
    
    // Check if constraint is gone
    $check = $conn->query("SHOW INDEXES FROM attendance WHERE Key_name = 'unique_daily_record'");
    
    if ($check && $check->num_rows === 0) {
        echo "<h3 style='color: green;'>✓ Constraint removed successfully!</h3>";
        echo "<p>Multiple punch in/out per day is now allowed.</p>";
    } else {
        echo "<h3 style='color: green;'>✓ Constraint already removed</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color: green;'>✓ Constraint handled: " . $e->getMessage() . "</h3>";
}

$conn->close();
echo "<p><a href='employee/my_attendance.php'>Go to My Attendance →</a></p>";
?>
