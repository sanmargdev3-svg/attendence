<?php
/**
 * Database Migration: Add dashboard_role column
 * Purpose: Enable role-based access to separate dashboards
 * Run this once to add the column
 */

require_once 'db.php';

try {
    // Check if column already exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'dashboard_role'");
    
    if ($checkColumn->num_rows == 0) {
        // Add the column
        $sql = "ALTER TABLE users ADD COLUMN dashboard_role VARCHAR(50) DEFAULT NULL 
                COMMENT 'Role for dashboard access: fr_panel, employee_photos, fr_dashboard'";
        
        if ($conn->query($sql) === TRUE) {
            echo "✓ Column 'dashboard_role' added successfully!<br>";
            echo "Next: Assign roles to phone numbers in phpMyAdmin<br>";
            echo "<br>";
            echo "Update examples:<br>";
            echo "UPDATE users SET dashboard_role = 'fr_panel' WHERE phone = '9830376201';<br>";
            echo "UPDATE users SET dashboard_role = 'employee_photos' WHERE phone = '9876543210';<br>";
            echo "UPDATE users SET dashboard_role = 'fr_dashboard' WHERE phone = '9999999999';<br>";
        } else {
            echo "✗ Error: " . $conn->error;
        }
    } else {
        echo "✓ Column 'dashboard_role' already exists!<br>";
        echo "Next: Assign roles to phone numbers<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}

?>
