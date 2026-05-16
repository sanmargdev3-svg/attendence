<?php
/**
 * Remove Unique Constraint to Allow Multiple Punch Records Per Day
 * Run this once to enable multiple punch in/out per day
 */

session_start();
include('config/db.php');

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'suparadmin') {
    die("❌ Access Denied - SuperAdmin only");
}

echo "<h2>Removing Unique Constraint for Multiple Punch In/Out...</h2>";

try {
    // Remove the unique constraint that prevents multiple records per day
    $query = "ALTER TABLE attendance DROP INDEX IF EXISTS unique_daily_record";
    
    if ($conn->query($query)) {
        echo "<p style='color: green;'>✅ Unique constraint removed successfully!</p>";
        echo "<p style='color: green;'>✓ Multiple punch in/out per day is now enabled</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Constraint may not exist or already removed</p>";
    }
    
    // Verify the change
    $check_query = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                   WHERE TABLE_NAME='attendance' AND CONSTRAINT_NAME='unique_daily_record'";
    $result = $conn->query($check_query);
    
    if ($result->num_rows === 0) {
        echo "<hr>";
        echo "<h3 style='color: green;'>✅ Configuration Complete!</h3>";
        echo "<p>Employees can now:</p>";
        echo "<ul>";
        echo "<li>✓ Punch In multiple times per day via Face Detection</li>";
        echo "<li>✓ Punch In multiple times per day via Manual Punch</li>";
        echo "<li>✓ Punch Out multiple times per day</li>";
        echo "<li>✓ Take lunch breaks with multiple punch cycles</li>";
        echo "</ul>";
        echo "<p><strong>System will capture first Punch In and last Punch Out automatically</strong></p>";
    }
    
    echo "<hr>";
    echo "<p><a href='admin/dashboard.php'>← Back to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
