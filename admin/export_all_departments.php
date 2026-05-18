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

// Get month and year from POST, or from_date/to_date for date range
$month = null;
$year = null;
$from_date = null;
$to_date = null;
$date_range = array();
$month_name = '';

// Check if using new date range format
if (!empty($_POST['from_date']) && !empty($_POST['to_date'])) {
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $month_name = date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date));
    
    // Generate array of dates in range
    $current = new DateTime($from_date);
    $end = new DateTime($to_date);
    $end->modify('+1 day');
    while ($current < $end) {
        $date_range[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
} elseif (!empty($_POST['month']) && !empty($_POST['year'])) {
    // Fallback to old format
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $month_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    
    // Generate date range for entire month
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date_range[] = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
    }
} else {
    header("Location: export_monthly.php");
    exit();
}

// Set days_in_month for column calculations
$days_in_month = count($date_range);

// Get selected company filter (if any)
$selected_companies = isset($_POST['companies']) && is_array($_POST['companies']) ? $_POST['companies'] : array();

// Build SQL query to fetch employees, optionally filtering by company
$query = "
    SELECT id, employee_id, name, department, location, week_off, status, date_of_exit 
    FROM users 
    WHERE role = 'employee' AND (status = 'Working' OR status = 'Resign')
";

if (count($selected_companies) > 0) {
    $placeholders = implode(',', array_fill(0, count($selected_companies), '?'));
    $query .= " AND company IN ($placeholders)";
}

$query .= " ORDER BY location, department, name";

$stmt = $conn->prepare($query);

// Bind parameters if companies were selected
if (count($selected_companies) > 0) {
    $types = str_repeat('s', count($selected_companies));
    $stmt->bind_param($types, ...$selected_companies);
}

$stmt->execute();
$result = $stmt->get_result();

// Convert date_range to DateTime objects for comparison
$from_date_obj = new DateTime($date_range[0]);
$to_date_obj = new DateTime($date_range[count($date_range) - 1]);

$employees_by_location = array();
while ($row = $result->fetch_assoc()) {
    $location = !empty($row['location']) ? $row['location'] : 'Unassigned';
    $dept = !empty($row['department']) ? $row['department'] : 'Unassigned';
    
    if (!isset($employees_by_location[$location])) {
        $employees_by_location[$location] = array();
    }
    if (!isset($employees_by_location[$location][$dept])) {
        $employees_by_location[$location][$dept] = array();
    }
    $employees_by_location[$location][$dept][] = $row;
}
$stmt->close();

// Helper function to check status for a date
function getStatusForDate($conn, $user_id, $date_str, $week_off) {
    $day_name = date('l', strtotime($date_str));
    
    if ($week_off === $day_name) {
        return 'WO';
    }
    
    $stmt_od = $conn->prepare("SELECT id FROM od_records WHERE user_id = ? AND od_date = ?");
    $stmt_od->bind_param("is", $user_id, $date_str);
    $stmt_od->execute();
    $result_od = $stmt_od->get_result();
    if ($result_od->num_rows > 0) {
        $stmt_od->close();
        return 'OD';
    }
    $stmt_od->close();
    
    // Check if there's a comp off for this date
    $stmt_co = $conn->prepare("SELECT id FROM comp_off_requests WHERE user_id = ? AND comp_off_date = ?");
    $stmt_co->bind_param("is", $user_id, $date_str);
    $stmt_co->execute();
    $result_co = $stmt_co->get_result();
    $has_comp_off = $result_co->num_rows > 0;
    $stmt_co->close();
    
    $stmt_att = $conn->prepare("SELECT status FROM attendance WHERE user_id = ? AND DATE(date) = ?");
    $stmt_att->bind_param("is", $user_id, $date_str);
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

function getPunchTimes($conn, $user_id, $date_str) {
    $stmt = $conn->prepare("
        SELECT 
            MIN(punch_in) as first_punch_in, 
            MAX(punch_out) as last_punch_out
        FROM attendance 
        WHERE user_id = ? AND DATE(date) = ?
    ");
    $stmt->bind_param("is", $user_id, $date_str);
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

function getCompOffDate($conn, $employee_id, $date_str) {
    $stmt = $conn->prepare("SELECT earned_date FROM comp_off_requests WHERE user_id = ? AND comp_off_date = ?");
    
    if ($stmt === false) {
        return false;
    }
    
    $stmt->bind_param("is", $employee_id, $date_str);
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

// Remove default sheet
$spreadsheet->removeSheetByIndex(0);

// Define styles (reusable)
$titleFont = new Font(['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']]);
$titleAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true]);

$headerFont = new Font(['bold' => true, 'color' => ['rgb' => 'FFFFFF']]);
$headerAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true]);

$deptFont = new Font(['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']]);
$deptAlignment = new Alignment(['horizontal' => 'left', 'vertical' => 'center']);

$labelFont = new Font(['bold' => true]);
$labelAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center']);

$centerAlignment = new Alignment(['horizontal' => 'center', 'vertical' => 'center']);

// Function to create sheet for a location
function createLocationSheet($spreadsheet, $location, $employees_by_dept, $date_range, $days_in_month, $month_name, $titleFont, $titleAlignment, $centerAlignment, $conn) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(substr($location, 0, 31)); // Excel sheet name max 31 chars
    
    // Get date range objects for comparison
    $from_date_obj = new DateTime($date_range[0]);
    $to_date_obj = new DateTime($date_range[count($date_range) - 1]);
    
    $row = 1;
    
    // Title
    $sheet->setCellValue('A' . $row, "Attendance Report - " . $location . " (" . $month_name . ")");
    $sheet->getStyle('A' . $row)->setFont($titleFont);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->mergeCells('A' . $row . ':' . Coordinate::stringFromColumnIndex($days_in_month + 1) . $row);
    $sheet->getRowDimension($row)->setRowHeight(25);
    $row += 2;
    
    // Days header row
    foreach ($date_range as $day_index => $date_str) {
        $formatted_date = date('d-D', strtotime($date_str));
        if ($day_index === 0) {
            $sheet->setCellValue('A' . $row, "");
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '000000']]]);
        }
        $col = Coordinate::stringFromColumnIndex($day_index + 2);
        $sheet->setCellValue($col . $row, $formatted_date);
        $sheet->getStyle($col . $row)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '000000']]]);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;
    
    // Process each department in this location
    foreach ($employees_by_dept as $dept => $employees) {
        $sheet->setCellValue('A' . $row, "DEPARTMENT: " . $dept);
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;
        
        // Process each employee
        foreach ($employees as $emp) {
            // Check if employee has resigned and if report period is after resignation
            $emp_resigned = ($emp['status'] === 'Resign' && !empty($emp['date_of_exit']));
            $resign_month_end = null;
            if ($emp_resigned) {
                // Get the last day of the resignation month
                $resign_date = new DateTime($emp['date_of_exit']);
                $resign_month_end = clone $resign_date;
                $resign_month_end->modify('last day of this month');
                // If employee resigned before the report period, skip them
                if ($resign_month_end < $from_date_obj) {
                    continue;
                }
            }
            
            $emp_start_row = $row;
            
            // Employee name row
            $sheet->setCellValue('A' . $row, $emp['employee_id'] . " - " . $emp['name']);
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 11]]);
            $row++;
            
            // Status row
            $sheet->setCellValue('A' . $row, "Status");
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
            
            foreach ($date_range as $day_index => $date_str) {
                // Check if this date is after resignation month
                if ($emp_resigned && new DateTime($date_str) > $resign_month_end) {
                    $status = '-';
                } else {
                    $status = getStatusForDate($conn, $emp['id'], $date_str, $emp['week_off']);
                }
                $col = Coordinate::stringFromColumnIndex($day_index + 2);
                $sheet->setCellValue($col . $row, $status);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $row++;
            
            // Punch In row
            $sheet->setCellValue('A' . $row, "In");
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
            
            foreach ($date_range as $day_index => $date_str) {
                // Check if this date is after resignation month
                if ($emp_resigned && new DateTime($date_str) > $resign_month_end) {
                    $punch_in = '-';
                } else {
                    $times = getPunchTimes($conn, $emp['id'], $date_str);
                    $punch_in = $times && !empty($times['first_punch_in']) ? extractTimeFromTimestamp($times['first_punch_in']) : '-';
                }
                $col = Coordinate::stringFromColumnIndex($day_index + 2);
                $sheet->setCellValue($col . $row, $punch_in);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $row++;
            
            // Punch Out row
            $sheet->setCellValue('A' . $row, "Out");
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
            
            foreach ($date_range as $day_index => $date_str) {
                // Check if this date is after resignation month
                if ($emp_resigned && new DateTime($date_str) > $resign_month_end) {
                    $punch_out = '-';
                } else {
                    $times = getPunchTimes($conn, $emp['id'], $date_str);
                    $punch_out = $times && !empty($times['last_punch_out']) ? extractTimeFromTimestamp($times['last_punch_out']) : '-';
                }
                $col = Coordinate::stringFromColumnIndex($day_index + 2);
                $sheet->setCellValue($col . $row, $punch_out);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $row++;
            
            // Total Hours row
            $sheet->setCellValue('A' . $row, "Total");
            $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
            
            foreach ($date_range as $day_index => $date_str) {
                // Check if this date is after resignation month
                if ($emp_resigned && new DateTime($date_str) > $resign_month_end) {
                    $display = '-';
                } else {
                    $compOffDate = getCompOffDate($conn, $emp['id'], $date_str);
                    if ($compOffDate) {
                        $day_taken = date('d', strtotime($compOffDate));
                        $display = 'CO-' . $day_taken;
                    } else {
                        $times = getPunchTimes($conn, $emp['id'], $date_str);
                        $display = calculateHours($times['first_punch_in'] ?? null, $times['last_punch_out'] ?? null);
                    }
                }
                $col = Coordinate::stringFromColumnIndex($day_index + 2);
                $sheet->setCellValue($col . $row, $display);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $row += 2; // Blank row between employees
        }
        
        $row++; // Blank row between departments
    }
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(25);
    foreach ($date_range as $day_index => $date_str) {
        $col = Coordinate::stringFromColumnIndex($day_index + 2);
        $sheet->getColumnDimension($col)->setWidth(7);
    }
    
    // Apply borders
    $usedRange = 'A1:' . Coordinate::stringFromColumnIndex($days_in_month + 1) . ($row - 1);
    $borders = $sheet->getStyle($usedRange)->getBorders();
    $borders->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Create a sheet for each location
foreach ($employees_by_location as $location => $employees_by_dept) {
    createLocationSheet($spreadsheet, $location, $employees_by_dept, $date_range, $days_in_month, $month_name, $titleFont, $titleAlignment, $centerAlignment, $conn);
}

// Set filename with date range
$filename = 'Attendance_AllLocation_' . str_replace(' ', '_', str_replace(' to ', '-', $month_name)) . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

// Write to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

