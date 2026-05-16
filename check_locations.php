<?php
/**
 * Check Actual Locations Stored in Database
 */
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    die("Please login first");
}

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

// Get all records with locations for this employee on this date
$sql = "SELECT id, punch_in, punch_out, punch_in_location, punch_out_location 
        FROM attendance 
        WHERE user_id = ? AND date = ? 
        ORDER BY id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Locations for $date</h2>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    table { background: white; border-collapse: collapse; width: 100%; max-width: 900px; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #333; color: white; }
    .location { background: #ffffcc; font-weight: bold; }
</style>";

echo "<table>";
echo "<tr><th>ID</th><th>Punch In</th><th>📍 Punch In Location</th><th>Punch Out</th><th>📍 Punch Out Location</th></tr>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['punch_in'] . "</td>";
        echo "<td class='location'>" . ($row['punch_in_location'] ?? 'NOT SET') . "</td>";
        echo "<td>" . ($row['punch_out'] ?? '-') . "</td>";
        echo "<td class='location'>" . ($row['punch_out_location'] ?? 'NOT SET') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No records found for this date</td></tr>";
}

echo "</table>";

echo "<p><a href='employee/my_attendance.php'>← Back to My Attendance</a></p>";

$stmt->close();
$conn->close();
?>
