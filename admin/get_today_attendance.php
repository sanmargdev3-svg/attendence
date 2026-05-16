<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

$today = date('Y-m-d');

$query = "SELECT u.id, u.name, u.employee_id, a.punch_in, a.punch_out 
          FROM attendance a 
          JOIN users u ON a.user_id = u.id 
          WHERE a.date = ? 
          ORDER BY a.punch_in DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$attendance_log = [];
while ($row = $result->fetch_assoc()) {
    $attendance_log[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $attendance_log
]);
?>
