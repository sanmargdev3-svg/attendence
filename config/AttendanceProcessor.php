<?php
/**
 * Attendance Processing Library
 * Implements First-In-Last-Out logic across multiple attendance sources
 * Sources: dashboard (manual), face_recognition (automatic), admin (manual)
 */

class AttendanceProcessor {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Calculate First-In-Last-Out for a specific employee on a specific date
     * Takes all attendance records from any source and calculates:
     * - First punch in time (from any source)
     * - Last punch out time (from any source)
     */
    public function calculateFirstInLastOut($user_id, $date) {
        // Get all punch records for this employee on this date
        $query = "SELECT 
                    id,
                    punch_in,
                    punch_out,
                    source,
                    COALESCE(punch_in, punch_out) as punch_time
                  FROM attendance
                  WHERE user_id = ? AND date = ?
                  ORDER BY COALESCE(punch_in, punch_out) ASC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }
        
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
        
        if (empty($records)) {
            return ['success' => false, 'message' => 'No attendance records found'];
        }
        
        // Find first punch in and last punch out
        $first_in = null;
        $first_in_source = null;
        $first_in_id = null;
        $last_out = null;
        $last_out_source = null;
        $last_out_id = null;
        
        foreach ($records as $record) {
            // First punch in time
            if (!empty($record['punch_in']) && $first_in === null) {
                $first_in = $record['punch_in'];
                $first_in_source = $record['source'];
                $first_in_id = $record['id'];
            }
            
            // Last punch out time
            if (!empty($record['punch_out'])) {
                $last_out = $record['punch_out'];
                $last_out_source = $record['source'];
                $last_out_id = $record['id'];
            }
        }
        
        return [
            'success' => true,
            'first_punch_in' => $first_in,
            'first_punch_in_source' => $first_in_source,
            'first_punch_in_id' => $first_in_id,
            'last_punch_out' => $last_out,
            'last_punch_out_source' => $last_out_source,
            'last_punch_out_id' => $last_out_id,
            'total_records' => count($records),
            'all_records' => $records
        ];
    }
    
    /**
     * Finalize attendance for a specific employee on a specific date
     * Sets the official punch-in and punch-out times
     */
    public function finalizeAttendance($user_id, $date) {
        $filo = $this->calculateFirstInLastOut($user_id, $date);
        
        if (!$filo['success']) {
            return $filo;
        }
        
        // Consolidate into single record (keep the first one, update punch times)
        // Get the first record
        $query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? ORDER BY id ASC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();
        
        if (!$record) {
            return ['success' => false, 'error' => 'Could not find attendance record'];
        }
        
        $main_id = $record['id'];
        
        // Update the main record with FILO times
        $query = "UPDATE attendance 
                  SET punch_in = ?, 
                      punch_out = ?,
                      source = 'consolidated',
                      is_first_in = TRUE,
                      is_last_out = IF(? IS NOT NULL, TRUE, FALSE)
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Update error: ' . $this->conn->error];
        }
        
        $stmt->bind_param("sssi", 
            $filo['first_punch_in'],
            $filo['last_punch_out'],
            $filo['last_punch_out'],
            $main_id
        );
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to update: ' . $this->conn->error];
        }
        $stmt->close();
        
        // Delete duplicate records (keep only the main one)
        if ($filo['total_records'] > 1) {
            $query = "DELETE FROM attendance WHERE user_id = ? AND date = ? AND id != ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("isi", $user_id, $date, $main_id);
            $stmt->execute();
            $stmt->close();
        }
        
        return [
            'success' => true,
            'message' => 'Attendance finalized',
            'punch_in' => $filo['first_punch_in'],
            'punch_out' => $filo['last_punch_out'],
            'first_punch_in_source' => $filo['first_punch_in_source'],
            'last_punch_out_source' => $filo['last_punch_out_source'],
            'consolidated_records' => $filo['total_records']
        ];
    }
    
    /**
     * Get consolidated attendance report for a date range
     */
    public function getConsolidatedAttendanceReport($start_date, $end_date, $department = null) {
        $query = "SELECT 
                    u.id,
                    u.name,
                    u.employee_id,
                    u.department,
                    DATE(a.date) as attendance_date,
                    TIME(a.punch_in) as punch_in_time,
                    TIME(a.punch_out) as punch_out_time,
                    a.source,
                    CASE 
                        WHEN a.punch_in IS NOT NULL AND a.punch_out IS NOT NULL THEN 'Present'
                        WHEN a.punch_in IS NOT NULL AND a.punch_out IS NULL THEN 'Present (Half Day)'
                        ELSE 'Absent'
                    END as status,
                    TIMEDIFF(a.punch_out, a.punch_in) as working_hours
                  FROM users u
                  LEFT JOIN attendance a ON u.id = a.user_id AND DATE(a.date) BETWEEN ? AND ?
                  WHERE u.role = 'employee'";
        
        if ($department) {
            $query .= " AND u.department = ?";
        }
        
        $query .= " ORDER BY u.name, a.date DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        if ($department) {
            $stmt->bind_param("sss", $start_date, $end_date, $department);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        $stmt->close();
        
        return ['success' => true, 'data' => $report];
    }
    
    /**
     * Get attendance summary by source
     * Shows how attendance was recorded (from which source)
     */
    public function getAttendanceBySource($start_date, $end_date) {
        $query = "SELECT 
                    source,
                    COUNT(*) as total_records,
                    COUNT(DISTINCT user_id) as unique_employees
                  FROM attendance
                  WHERE date BETWEEN ? AND ?
                  GROUP BY source
                  ORDER BY total_records DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        
        return ['success' => true, 'data' => $data];
    }
    
    /**
     * Get employee's attendance history with all sources
     */
    public function getEmployeeAttendanceHistory($user_id, $start_date, $end_date) {
        $query = "SELECT 
                    id,
                    date,
                    punch_in,
                    punch_out,
                    source,
                    status,
                    photo_match_confidence,
                    created_at
                  FROM attendance
                  WHERE user_id = ? AND date BETWEEN ? AND ?
                  ORDER BY date DESC, punch_in DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => $this->conn->error];
        }
        
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        
        return ['success' => true, 'data' => $history];
    }
    
    /**
     * Generate daily consolidation for all employees
     * Usually run as a daily batch job
     */
    public function consolidateDailyAttendance($date) {
        // Get all employees with attendance on this date
        $query = "SELECT DISTINCT user_id FROM attendance WHERE date = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $result_data = $this->finalizeAttendance($row['user_id'], $date);
            $results[] = $result_data;
        }
        $stmt->close();
        
        return [
            'success' => true,
            'date' => $date,
            'consolidated_count' => count($results),
            'details' => $results
        ];
    }
}
?>
