<?php
include 'config/db.php';

echo "=== Checking Latest Record Detail ===\n\n";

// Get the latest record
$query = "SELECT a.id, a.user_id, u.name, a.date, a.punch_in, a.punch_out
          FROM attendance a
          JOIN users u ON a.user_id = u.id
          WHERE a.id = (SELECT MAX(id) FROM attendance)";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    echo "Latest Record:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "User ID: " . $row['user_id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Date Column: '" . $row['date'] . "' (Type: " . gettype($row['date']) . ")\n";
    echo "Punch IN: '" . $row['punch_in'] . "'\n";
    echo "Punch OUT: '" . ($row['punch_out'] ?: 'NULL') . "'\n\n";
}

// Check schema
echo "=== Table Schema ===\n";
$schema = $conn->query("DESCRIBE attendance");
while ($col = $schema->fetch_assoc()) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

// Check if a record with BOTH punch_in and punch_out exists today
echo "\n=== Same-day IN/OUT Records ===\n";
$both = $conn->query("SELECT id, user_id, punch_in, punch_out FROM attendance 
                       WHERE DATE(punch_in) = '2026-03-11' 
                       AND punch_out IS NOT NULL 
                       LIMIT 5");
if ($both->num_rows > 0) {
    echo "Found " . $both->num_rows . " records with both IN and OUT:\n";
    while ($b = $both->fetch_assoc()) {
        echo "ID: " . $b['id'] . " | User: " . $b['user_id'] . " | IN: " . $b['punch_in'] . " | OUT: " . $b['punch_out'] . "\n";
    }
} else {
    echo "NO records have both punch_in and punch_out filled!\n";
}
?>
