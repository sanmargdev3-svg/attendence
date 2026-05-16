<?php
include('config/db.php');

// Check if od_records table exists
$result = $conn->query("SHOW TABLES LIKE 'od_records'");
if ($result->num_rows === 0) {
    echo "Table od_records does not exist. Creating...\n";
    
    $create_table = "CREATE TABLE IF NOT EXISTS od_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        od_date DATE NOT NULL,
        marked_by INT NOT NULL,
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_od (user_id, od_date)
    )";
    
    if ($conn->query($create_table)) {
        echo "✓ od_records table created successfully!\n";
    } else {
        echo "✗ Error creating od_records table: " . $conn->error . "\n";
    }
} else {
    echo "✓ od_records table already exists\n";
}

// Verify table structure
$result = $conn->query("SHOW COLUMNS FROM od_records");
if ($result) {
    echo "\nTable structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

// Check all tables in database
echo "\nAll tables in database:\n";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo "  - " . $row[0] . "\n";
}
?>
