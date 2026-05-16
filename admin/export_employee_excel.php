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

// Get employee ID and month/year
if (!isset($_POST['employee_id'])) {
    header("Location: export_monthly.php");
    exit();
}

$employee_id = intval($_POST['employee_id']);
$month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Fetch employee details
$stmt = $conn->prepare("SELECT id, employee_id, name, department, week_off FROM users WHERE id = ? AND role = 'employee' AND (status = 'Working' OR status = 'Resign')");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: export_monthly.php");
    exit();
}

$employee = $result->fetch_assoc();
$stmt->close();

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$month_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));

// Helper function to check status for a date
function getStatusForDate($conn, $user_id, $date, $week_off, $month, $year) {
    $date_obj = date('Y-m-d', mktime(0, 0, 0, $month, $date, $year));
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
    
    // Check if there's a comp off for this date
    $stmt_co = $conn->prepare("SELECT id FROM comp_off_requests WHERE user_id = ? AND comp_off_date = ?");
    $stmt_co->bind_param("is", $user_id, $date_obj);
    $stmt_co->execute();
    $result_co = $stmt_co->get_result();
    $has_comp_off = $result_co->num_rows > 0;
    $stmt_co->close();
    
    $stmt_att = $conn->prepare("SELECT status FROM attendance WHERE user_id = ? AND DATE(date) = ?");
    $stmt_att->bind_param("is", $user_id, $date_obj);
    $stmt_att->execute();
    $result_att = $stmt_att->get_result();
    
    if ($result_att->num_rows > 0) {
        $att_row = $result_att->fetch_assoc();
        $stmt_att->close();
        $status = ($att_row['status'] === 'Present' || $att_row['status'] === 'Late') ? 'P' : 'A';
        // If absent but has comp off, show ADJ (adjusted)
        if ($status === 'A' && $has_comp_off) {
            return 'ADJ';
        }
        return $status;
    }
    
    $stmt_att->close();
    // If no attendance record but has comp off, show ADJ
    if ($has_comp_off) {
        return 'ADJ';
    }
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
            MAX(punch_out) as last_punch_out,
            MAX(CASE WHEN punch_in IS NOT NULL THEN punch_in_location ELSE NULL END) as punch_in_location,
            MAX(CASE WHEN punch_out IS NOT NULL THEN punch_out_location ELSE NULL END) as punch_out_location
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

function getFullLocationName($location_text, $conn) {
    if (empty($location_text) || $location_text === '-') {
        return '-';
    }
    
    if (is_numeric($location_text)) {
        $location_id = intval($location_text);
        $stmt = $conn->prepare("SELECT name FROM locations WHERE id = ?");
        $stmt->bind_param("i", $location_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['name'];
        }
        $stmt->close();
    }
    
    return $location_text;
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance");

// Define styles
$titleFont = new Font(['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']]);

$headerFont = new Font(['bold' => true, 'color' => ['rgb' => 'FFFFFF']]);

$deptFont = new Font(['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']]);

$empFont = new Font(['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']]);

$labelFont = new Font(['bold' => true]);

$row = 1;

// Title
$sheet->setCellValue('A' . $row, "Attendance Report - " . $employee['name'] . " - " . $month_name);
$sheet->getStyle('A' . $row)->setFont($titleFont);

$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->mergeCells('A' . $row . ':' . Coordinate::stringFromColumnIndex($days_in_month + 1) . $row);
$sheet->getRowDimension($row)->setRowHeight(25);
$row += 2;

// Department header
$sheet->setCellValue('A' . $row, "DEPARTMENT: " . $employee['department']);
$sheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 12]
]);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

for ($col_idx = 2; $col_idx <= $days_in_month; $col_idx++) {
    $col = Coordinate::stringFromColumnIndex($col_idx);
}

$sheet->getRowDimension($row)->setRowHeight(20);
$row++;

// Employee information
$sheet->setCellValue('A' . $row, $employee['employee_id'] . " - " . $employee['name']);
$sheet->getStyle('A' . $row)->applyFromArray([
    'font' => ['bold' => true, 'size' => 11]
]);

for ($col_idx = 2; $col_idx <= $days_in_month; $col_idx++) {
    $col = Coordinate::stringFromColumnIndex($col_idx);
}

$sheet->getRowDimension($row)->setRowHeight(18);
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

// Status row
$sheet->setCellValue('A' . $row, "Status");
$sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);

for ($day = 1; $day <= $days_in_month; $day++) {
    $status = getStatusForDate($conn, $employee['id'], $day, $employee['week_off'], $month, $year);
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
    $times = getPunchTimes($conn, $employee['id'], $day, $month, $year);
    $punch_in = $times && !empty($times['first_punch_in']) ? extractTimeFromTimestamp($times['first_punch_in']) : '-';
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $punch_in);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$row++;

// Punch Out row
$sheet->setCellValue('A' . $row, "Out");
$sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);

for ($day = 1; $day <= $days_in_month; $day++) {
    $times = getPunchTimes($conn, $employee['id'], $day, $month, $year);
    $punch_out = $times && !empty($times['last_punch_out']) ? extractTimeFromTimestamp($times['last_punch_out']) : '-';
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $punch_out);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$row++;

// Total Hours row
$sheet->setCellValue('A' . $row, "Total");
$sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);

for ($day = 1; $day <= $days_in_month; $day++) {
    // Check if comp off exists for this date
    $compOffDate = getCompOffDate($conn, $employee['id'], $day, $month, $year);
    if ($compOffDate) {
        $day_taken = date('d', strtotime($compOffDate));
        $display = 'CO-' . $day_taken;
    } else {
        $times = getPunchTimes($conn, $employee['id'], $day, $month, $year);
        $display = calculateHours($times['first_punch_in'] ?? null, $times['last_punch_out'] ?? null);
    }
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $display);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$row += 2;

// Punch In Location row
$sheet->setCellValue('A' . $row, "In Location");
$sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);

for ($day = 1; $day <= $days_in_month; $day++) {
    $times = getPunchTimes($conn, $employee['id'], $day, $month, $year);
    $location = ($times && !empty($times['punch_in_location'])) ? getFullLocationName($times['punch_in_location'], $conn) : '-';
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $location);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$row++;

// Punch Out Location row
$sheet->setCellValue('A' . $row, "Out Location");
$sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);

for ($day = 1; $day <= $days_in_month; $day++) {
    $times = getPunchTimes($conn, $employee['id'], $day, $month, $year);
    $location = ($times && !empty($times['punch_out_location'])) ? getFullLocationName($times['punch_out_location'], $conn) : '-';
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->setCellValue($col . $row, $location);
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

// Set column widths
$sheet->getColumnDimension('A')->setWidth(20);
for ($day = 1; $day <= $days_in_month; $day++) {
    $col = Coordinate::stringFromColumnIndex($day + 1);
    $sheet->getColumnDimension($col)->setWidth(7);
}

// Apply black borders to entire used range
$usedRange = 'A1:' . Coordinate::stringFromColumnIndex($days_in_month + 1) . $row;
$borders = $sheet->getStyle($usedRange)->getBorders();
$borders->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Set filename
$filename = 'Attendance_' . str_replace(' ', '_', $employee['name']) . '_' . str_replace(' ', '_', $month_name) . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

// Write to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();



