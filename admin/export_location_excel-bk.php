<?php
session_start();
include('../config/db.php');

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Authentication check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Get parameters
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$department = isset($_POST['department']) ? trim($_POST['department']) : '';
$month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

if (!$location) {
    header("Location: export_monthly.php");
    exit();
}

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$month_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));

// Build query to fetch employees (include resigned employees for filtering)
$query = "SELECT id, employee_id, name, department, week_off, status, date_of_exit FROM users WHERE role = 'employee' AND (status = 'Working' OR status = 'Resign') AND location = ?";
$params = [$location];
$types = "s";

// Add department filter if specified
if ($department && $department !== 'All') {
    $query .= " AND department = ?";
    $params[] = $department;
    $types .= "s";
}

$query .= " ORDER BY department, name";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get current month/year for filtering resigned employees
$current_month = intval($month);
$current_year = intval($year);

$employees_by_dept = array();
while ($row = $result->fetch_assoc()) {
    // Filter: Skip resigned employees whose resignation is before the selected month
    if ($row['status'] === 'Resign' && !empty($row['date_of_exit'])) {
        $resign_parts = explode('-', trim($row['date_of_exit']));
        if (count($resign_parts) === 3) {
            $resign_year = intval($resign_parts[0]);
            $resign_month = intval($resign_parts[1]);
            // Skip if resignation is before selected export month
            if ($resign_year < $current_year || ($resign_year === $current_year && $resign_month < $current_month)) {
                continue; // Skip this resigned employee
            }
        }
    }
    
    $dept = !empty($row['department']) ? $row['department'] : 'Unassigned';
    if (!isset($employees_by_dept[$dept])) {
        $employees_by_dept[$dept] = array();
    }
    $employees_by_dept[$dept][] = $row;
}
$stmt->close();

// Helper function to check status for a date
function getStatusForDate($conn, $user_id, $date, $week_off, $month, $year, $resign_date = null) {
    $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $date, $year));
    
    // If employee resigned and current date is after resignation date, mark as absent
    if ($resign_date && strtotime($date_obj) > strtotime($resign_date)) {
        return 'A'; // Absent after resignation
    }
    
    $day_name = date('l', strtotime($date_obj));
    
    if ($week_off === $day_name) {
        return 'WO';
    }
    
    $stmt_od = $conn->prepare("SELECT id FROM od_records WHERE user_id = ? AND od_date = ?");
    $stmt_od->bind_param("is", $user_id, $date_obj);
    $stmt_od->execute();
    $result_od = $stmt_od->get_result();
    if ($result_od->num_rows > 0) {
        $stmt_od->close();
        return 'OD';
    }
    $stmt_od->close();
    
    $stmt_att = $conn->prepare("SELECT status FROM attendance WHERE user_id = ? AND DATE(date) = ?");
    $stmt_att->bind_param("is", $user_id, $date_obj);
    $stmt_att->execute();
    $result_att = $stmt_att->get_result();
    
    if ($result_att->num_rows > 0) {
        $att_row = $result_att->fetch_assoc();
        $stmt_att->close();
        return ($att_row['status'] === 'Present' || $att_row['status'] === 'Late') ? 'P' : 'A';
    }
    
    $stmt_att->close();
    return 'A';
}

function extractTimeFromTimestamp($timestamp) {
    if (!$timestamp) {
        return '-';
    }
    if (strlen($timestamp) === 8 && strpos($timestamp, ':') === 2) {
        return substr($timestamp, 0, 5);
    }
    if (strpos($timestamp, ' ') !== false) {
        return substr($timestamp, 11, 5);
    }
    try {
        $time_obj = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        if ($time_obj) {
            return $time_obj->format('H:i');
        }
    } catch (Exception $e) {
        // Continue
    }
    return (strlen($timestamp) >= 5) ? substr($timestamp, 0, 5) : '-';
}

function getPunchTimes($conn, $user_id, $date, $month, $year) {
    $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $date, $year));
    $stmt = $conn->prepare("
        SELECT 
            MIN(punch_in) as first_punch_in, 
            MAX(punch_out) as last_punch_out
        FROM attendance 
        WHERE user_id = ? AND DATE(date) = ?
    ");
    $stmt->bind_param("is", $user_id, $date_obj);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    $stmt->close();
    return null;
}

function calculateHours($punch_in, $punch_out) {
    if (!$punch_in || !$punch_out) {
        return '-';
    }
    $in = strtotime($punch_in);
    $out = strtotime($punch_out);
    $diff = $out - $in;
    
    // Handle overnight shifts (shift crosses midnight)
    // If punch_out is earlier than punch_in on the same day, add 24 hours
    if ($diff < 0) {
        $diff += 86400; // 24 hours in seconds
    }
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    return sprintf("%02d:%02d", $hours, $minutes);
}

function getCompOffDate($conn, $employee_id, $day, $month, $year) {
    $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $stmt = $conn->prepare("SELECT earned_date FROM comp_off_requests WHERE user_id = ? AND comp_off_date = ?");
    
    if ($stmt === false) {
        return false;
    }
    
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['earned_date'];
    }
    $stmt->close();
    return false;
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance");

// Define styles
$titleFont = new Font(['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']]);
$titleAlignment = new Alignment(['horizontal' => 'left', 'vertical' => 'center', 'wrapText' => true]);

$headerFont = new Font(['bold' => true, 'color' => ['rgb' => 'FFFFFF']]);
$headerAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true]);

$deptFont = new Font(['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']]);
$deptAlignment = new Alignment(['horizontal' => 'left', 'vertical' => 'center']);

$labelFont = new Font(['bold' => true]);
$labelAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center']);

$centerAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center']);

$row = 1;

// Title
$sheet->setCellValue('A' . $row, "Attendance Report - Location: " . $location . " - " . $month_name);
$sheet->getStyle('A' . $row)->setFont($titleFont);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->mergeCells('A' . $row . ':' . Coordinate::stringFromColumnIndex($days_in_month + 1) . $row);
$sheet->getRowDimension($row)->setRowHeight(25);
$row += 2;

// Days header row (shown once, before all departments) - ONLY dates, no label
for ($day = 1; $day <= $days_in_month; $day++) {
    $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
    // Format: "01-Sun"
    $formatted_date = date('d-D', strtotime($date_obj));
    if ($day === 1) {
        $sheet->setCellValue('A' . $row, "");
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '000000']]]);
    }
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $formatted_date);
    $sheet->getStyle($col . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '000000']]]);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

// Process each department
foreach ($employees_by_dept as $dept => $employees) {
    // Department header - left side
    $sheet->setCellValue('A' . $row, "DEPARTMENT: " . $dept);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12]
    ]);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;

    // Process each employee
    foreach ($employees as $emp) {
        // Employee name row
        $sheet->setCellValue('A' . $row, $emp['employee_id'] . " - " . $emp['name']);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11]
        ]);
        $row++;

        // Status row
        $sheet->setCellValue('A' . $row, "Status");
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
        
        for ($day = 1; $day <= $days_in_month; $day++) {
            $status = getStatusForDate($conn, $emp['id'], $day, $emp['week_off'], $month, $year, $emp['date_of_exit'] ?? null);
            $col = Coordinate::stringFromColumnIndex($day + 1);
            $sheet->setCellValue($col . $row, $status);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        $row++;

        // Punch In row
        $sheet->setCellValue('A' . $row, "In");
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
        
        for ($day = 1; $day <= $days_in_month; $day++) {
            // Check if this date is after resignation
            $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
            $is_resigned = false;
            if (!empty($emp['date_of_exit']) && strtotime($date_obj) > strtotime($emp['date_of_exit'])) {
                $is_resigned = true;
            }
            
            $col = Coordinate::stringFromColumnIndex($day + 1);
            if ($is_resigned) {
                $sheet->setCellValue($col . $row, '-');
            } else {
                $times = getPunchTimes($conn, $emp['id'], $day, $month, $year);
                $punch_in = $times && !empty($times['first_punch_in']) ? extractTimeFromTimestamp($times['first_punch_in']) : '-';
                $sheet->setCellValue($col . $row, $punch_in);
            }
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        $row++;

        // Punch Out row
        $sheet->setCellValue('A' . $row, "Out");
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
        
        for ($day = 1; $day <= $days_in_month; $day++) {
            // Check if this date is after resignation
            $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
            $is_resigned = false;
            if (!empty($emp['date_of_exit']) && strtotime($date_obj) > strtotime($emp['date_of_exit'])) {
                $is_resigned = true;
            }
            
            $col = Coordinate::stringFromColumnIndex($day + 1);
            if ($is_resigned) {
                $sheet->setCellValue($col . $row, '-');
            } else {
                $times = getPunchTimes($conn, $emp['id'], $day, $month, $year);
                $punch_out = $times && !empty($times['last_punch_out']) ? extractTimeFromTimestamp($times['last_punch_out']) : '-';
                $sheet->setCellValue($col . $row, $punch_out);
            }
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        $row++;

        // Total Hours row
        $sheet->setCellValue('A' . $row, "Total");
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
        
        for ($day = 1; $day <= $days_in_month; $day++) {
            // Check if this date is after resignation
            $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
            $is_resigned = false;
            if (!empty($emp['date_of_exit']) && strtotime($date_obj) > strtotime($emp['date_of_exit'])) {
                $is_resigned = true;
            }
            
            $col = Coordinate::stringFromColumnIndex($day + 1);
            if ($is_resigned) {
                $sheet->setCellValue($col . $row, '-');
            } else {
                // Check if comp off exists for this date
                $compOffDate = getCompOffDate($conn, $emp['id'], $day, $month, $year);
                if ($compOffDate) {
                    $day_taken = date('d', strtotime($compOffDate));
                    $display = 'CO-' . $day_taken;
                } else {
                    $times = getPunchTimes($conn, $emp['id'], $day, $month, $year);
                    $display = calculateHours($times['first_punch_in'] ?? null, $times['last_punch_out'] ?? null);
                }
                $sheet->setCellValue($col . $row, $display);
            }
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        $row += 2; // Blank row between employees
    }
    
    $row++; // Blank row between departments
}

// Set column widths
$sheet->getColumnDimension('A')->setWidth(25);
for ($day = 1; $day <= $days_in_month; $day++) {
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->getColumnDimension($col)->setWidth(7);
}

// Apply black borders to entire used range
$usedRange = 'A1:' . Coordinate::stringFromColumnIndex($days_in_month + 1) . ($row - 1);
$borders = $sheet->getStyle($usedRange)->getBorders();
$borders->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Set filename
$dept_str = ($department && $department !== 'All') ? '_' . str_replace(' ', '_', $department) : '';
$filename = 'Attendance_Location_' . str_replace(' ', '_', $location) . $dept_str . '_' . str_replace(' ', '_', $month_name) . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

// Write to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

