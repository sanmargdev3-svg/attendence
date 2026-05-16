<?php
include 'config/db.php';

echo "=== Last 5 Records in Database ===\n\n";

$query = "SELECT a.id, a.user_id, u.name, a.date, a.punch_in, a.punch_out 
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          ORDER BY a.punch_in DESC 
          LIMIT 5";

$result = $conn->query($query);
$count = $result->num_rows;

if ($count > 0) {
    echo "Found " . $count . " records:\n\n";
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Employee: " . $row['name'] . " (User ID: " . $row['user_id'] . ")\n";
        echo "Date: " . $row['date'] . "\n";
        echo "Punch IN: " . $row['punch_in'] . "\n";
        echo "Punch OUT: " . $row['punch_out'] . "\n";
        echo "---\n\n";
    }
} else {
    echo "NO RECORDS FOUND in database!\n";
}

// Also check total count
$countResult = $conn->query("SELECT COUNT(*) as total FROM attendance");
$total = $countResult->fetch_assoc()['total'];
echo "Total records in attendance table: " . $total . "\n";
?>
