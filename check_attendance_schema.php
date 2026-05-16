<?php
include 'config/db.php';

$result = $conn->query('DESCRIBE attendance');

echo "Attendance Table Structure:\n";
echo "===========================\n\n";

while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . "\n";
}

$conn->close();
?>
