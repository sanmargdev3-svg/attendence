<?php
include 'config/db.php';

echo "<h3>Attendance Table Structure</h3>";
$result = $conn->query('DESCRIBE attendance');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . "<br>";
}

echo "<h3>Sample Records (Last 5)</h3>";
$records = $conn->query('SELECT * FROM attendance ORDER BY id DESC LIMIT 5');
while($row = $records->fetch_assoc()) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
?>
