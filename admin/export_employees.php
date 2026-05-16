<?php
ob_start();
session_start();
include('../config/db.php');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all employees organized by location and department
$stmt = $conn->prepare("SELECT employee_id, name, email, company, phone, department, location FROM users WHERE role = 'employee' AND status = 'Working' ORDER BY location, department, name");
$stmt->execute();
$result = $stmt->get_result();

// Group employees by location and department
$employees_by_location = [];
while ($row = $result->fetch_assoc()) {
    $location = $row['location'] ?: 'Unassigned';
    $department = $row['department'] ?: 'Unassigned';
    
    if (!isset($employees_by_location[$location])) {
        $employees_by_location[$location] = [];
    }
    if (!isset($employees_by_location[$location][$department])) {
        $employees_by_location[$location][$department] = [];
    }
    
    $employees_by_location[$location][$department][] = $row;
}

ksort($employees_by_location);

// Set filename
$filename = 'employees_by_location_' . date('Y-m-d') . '.csv';

ob_clean();
ob_end_clean();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$output = "";

// Generate output organized by location and department
foreach ($employees_by_location as $location => $departments) {
    $output .= "LOCATION: " . strtoupper($location) . "\n";
    $output .= "\n";
    
    foreach ($departments as $department => $employees) {
        $output .= "DEPARTMENT: " . $department . "\n";
        $output .= "Employee ID\tFull Name\tEmail\tCompany\tPhone\n";
        
        foreach ($employees as $emp) {
            // Escape values to prevent Excel formula errors
            $emp_id = "'" . $emp['employee_id'];
            $name = $emp['name'];
            $email = $emp['email'];
            $company = $emp['company'];
            $phone = "'" . $emp['phone'];
            
            $output .= $emp_id . "\t" . $name . "\t" . $email . "\t" . $company . "\t" . $phone . "\n";
        }
        $output .= "\n";
    }
}

echo $output;
$stmt->close();
exit();
?>