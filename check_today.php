<?php
include 'config/db.php';

echo "=== Today's Punch Records (2026-03-11) ===\n\n";

$query = "SELECT a.id, a.user_id, u.name, u.employee_id, a.punch_in, a.punch_out 
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          WHERE DATE(a.punch_in) = '2026-03-11'
          ORDER BY a.id DESC
          LIMIT 20";

$result = $conn->query($query);
$count = $result->num_rows;

if ($count > 0) {
    echo "Found " . $count . " records today:\n\n";
    while($row = $result->fetch_assoc()) {
        echo "Record ID: " . $row['id'] . " | User ID: " . $row['user_id'] . " | Name: " . $row['name'] . " (Emp#" . $row['employee_id'] . ")\n";
        echo "  Punch IN: " . $row['punch_in'] . "\n";
        echo "  Punch OUT: " . ($row['punch_out'] ? $row['punch_out'] : "EMPTY") . "\n";
        echo "---\n";
    }
} else {
    echo "NO RECORDS FOUND TODAY!\n";
}
?>
