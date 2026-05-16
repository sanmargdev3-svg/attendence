<?php
include 'config/db.php';

// Delete all today's records
$result = $conn->query("DELETE FROM attendance WHERE DATE(punch_in) = '2026-03-11'");

if ($result) {
    echo "✅ Deleted " . $conn->affected_rows . " records from 2026-03-11\n";
    echo "Database is now clean. You can test fresh.\n";
    
    // Show remaining records
    $check = $conn->query("SELECT COUNT(*) as total FROM attendance");
    $row = $check->fetch_assoc();
    echo "Total remaining records: " . $row['total'] . "\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}
?>
