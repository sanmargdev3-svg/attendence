<?php
/**
 * Migration: Add profile_photo column to users table
 * This script adds the missing profile_photo column required for face recognition
 */

include('db.php');

try {
    // Check if column already exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $alter_query = "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER employee_id";
        
        if ($conn->query($alter_query)) {
            echo "✓ Successfully added 'profile_photo' column to users table";
            exit(0);
        } else {
            throw new Exception("Error adding column: " . $conn->error);
        }
    } else {
        echo "✓ Column 'profile_photo' already exists";
        exit(0);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
    exit(1);
}
?>
