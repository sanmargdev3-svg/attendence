<?php
/**
 * Delete all Face Operators from database
 */

include('config/db.php');

echo "🗑️  DELETE ALL FACE OPERATORS\n";
echo str_repeat("=", 60) . "\n\n";

// Get count before deletion
$count_before = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='face_operator'")->fetch_assoc();
echo "Current operators: " . $count_before['count'] . "\n\n";

// Delete all face operators
$delete = $conn->query("DELETE FROM users WHERE role='face_operator'");
if ($delete) {
    echo "✅ All face operators deleted!\n\n";
    
    // Verify deletion
    $count_after = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='face_operator'")->fetch_assoc();
    echo "Remaining operators: " . $count_after['count'] . "\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

?>
