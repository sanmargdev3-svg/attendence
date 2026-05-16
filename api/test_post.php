<?php
session_start();
include('../config/db.php');

// Test what the API receives
$debug = [
    'session_user_id' => $_SESSION['user_id'] ?? 'NOT SET',
    'post_employee_id' => $_POST['employee_id'] ?? 'NOT SENT',
    'post_name' => $_POST['name'] ?? 'NOT SENT',
    'post_confidence' => $_POST['confidence'] ?? 'NOT SENT',
    'employee_id_after_intval' => intval($_POST['employee_id'] ?? 0),
    'current_date' => date('Y-m-d'),
    'current_datetime' => date('Y-m-d H:i:s')
];

// Check if we can find existing records
if (intval($_POST['employee_id'] ?? 0) > 0) {
    $emp_id = intval($_POST['employee_id']);
    $date = date('Y-m-d');
    
    $check = $conn->prepare("SELECT id, punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
    $check->bind_param("is", $emp_id, $date);
    $check->execute();
    $result = $check->get_result();
    
    $debug['existing_records_found'] = $result->num_rows;
    $debug['query_user_id'] = $emp_id;
    $debug['query_date'] = $date;
}

header('Content-Type: application/json');
echo json_encode($debug, JSON_PRETTY_PRINT);
?>
