<?php
session_start();
include('../config/db.php');

// Check authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    header("Location: ../auth/login.php");
    exit;
}

// Get parameters
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : date('Y-m-d');
$format = isset($_GET['format']) ? htmlspecialchars($_GET['format']) : 'csv';

// Get all attendance records for the date
$stmt = $conn->prepare("
    SELECT 
        u.employee_id,
        u.name,
        a.date,
        a.punch_in,
        a.punch_out,
        a.punch_in_location,
        a.punch_out_location,
        a.status
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = ? AND u.role = 'employee'
    ORDER BY u.name ASC, a.punch_in ASC
");
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

if ($format === 'csv') {
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.csv"');

    // CSV Headers
    $headers = ['Employee ID', 'Employee Name', 'Date', 'Punch In Time', 'Punch Out Time', 'Punch In Location', 'Punch Out Location', 'Duration (Hours)', 'Status'];
    
    // Create file handle
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    // Write data
    $prev_employee = '';
    $total_hours = 0;

    foreach ($records as $record) {
        // If new employee, add total hours line
        if ($prev_employee && $prev_employee !== $record['name']) {
            fputcsv($output, ['', $prev_employee . ' - TOTAL', '', '', '', '', '', number_format($total_hours, 2), '']);
            $total_hours = 0;
        }

        $punch_in = $record['punch_in'] ? date('H:i:s', strtotime($record['punch_in'])) : '';
        $punch_out = $record['punch_out'] ? date('H:i:s', strtotime($record['punch_out'])) : '';
        
        $duration = '';
        if ($punch_in && $punch_out) {
            $in_time = strtotime($record['punch_in']);
            $out_time = strtotime($record['punch_out']);
            $duration = number_format(($out_time - $in_time) / 3600, 2);
            $total_hours += ($out_time - $in_time) / 3600;
        }

        fputcsv($output, [
            $record['employee_id'],
            $record['name'],
            $record['date'],
            $punch_in,
            $punch_out,
            $record['punch_in_location'] ?? '',
            $record['punch_out_location'] ?? '',
            $duration,
            $record['status']
        ]);

        $prev_employee = $record['name'];
    }

    // Add last employee total
    if ($prev_employee) {
        fputcsv($output, ['', $prev_employee . ' - TOTAL', '', '', '', '', '', number_format($total_hours, 2), '']);
    }

    fclose($output);

} else if ($format === 'json') {
    // Generate JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.json"');

    // Process and calculate durations
    $processed_records = [];
    foreach ($records as $record) {
        $duration = null;
        if ($record['punch_in'] && $record['punch_out']) {
            $in_time = strtotime($record['punch_in']);
            $out_time = strtotime($record['punch_out']);
            $duration = round(($out_time - $in_time) / 3600, 2);
        }

        $processed_records[] = [
            'employee_id' => $record['employee_id'],
            'name' => $record['name'],
            'date' => $record['date'],
            'punch_in' => $record['punch_in'],
            'punch_out' => $record['punch_out'],
            'punch_in_location' => $record['punch_in_location'],
            'punch_out_location' => $record['punch_out_location'],
            'duration_hours' => $duration,
            'status' => $record['status']
        ];
    }

    echo json_encode([
        'date' => $date,
        'total_records' => count($processed_records),
        'records' => $processed_records
    ], JSON_PRETTY_PRINT);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid format']);
}

$conn->close();
?>
