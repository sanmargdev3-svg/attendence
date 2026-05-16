<?php
/**
 * Add Location Tracking Columns to Attendance Table
 * Run this once to add the missing location columns
 */

session_start();
include('config/db.php');

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'suparadmin') {
    die("❌ Access Denied - Admin only");
}

echo "<h2>Adding Location Columns to Attendance Table...</h2>";

try {
    // Add location columns for punch_in
    $queries = [
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_location VARCHAR(255)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_lat DECIMAL(10, 8)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_lng DECIMAL(11, 8)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_in_accuracy DECIMAL(10, 2)",
        
        // Add location columns for punch_out
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_location VARCHAR(255)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_lat DECIMAL(10, 8)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_lng DECIMAL(11, 8)",
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS punch_out_accuracy DECIMAL(10, 2)"
    ];
    
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            echo "<p style='color: green;'>✓ " . htmlspecialchars($query) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Already exists or error: " . htmlspecialchars($query) . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✓ All location columns added successfully!</h3>";
    echo "<p><a href='admin/dashboard.php'>← Back to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>
