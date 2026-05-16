<?php
session_start();
include('../config/db.php');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'suparadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image provided']);
        exit();
    }
    
    // Save the image for processing
    $image_data = $input['image'];
    if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
        $data = substr($image_data, strpos($image_data, ',') + 1);
        $type = strtolower($type[1]);
        
        if (!in_array($type, ['jpeg', 'jpg', 'png', 'gif'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit();
        }
        
        $data = base64_decode($data);
        $upload_dir = '../uploads/face_captures';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = 'capture_' . time() . '.' . ($type === 'jpg' ? 'jpeg' : $type);
        $filepath = $upload_dir . '/' . $filename;
        file_put_contents($filepath, $data);
        
        // TODO: Implement actual face recognition using face-api.js or TensorFlow.js
        // For now, return a simulated result
        
        // Get all employees with photos
        $employees_query = "SELECT id, name, employee_id, profile_photo FROM users WHERE role = 'employee' AND profile_photo IS NOT NULL ORDER BY name ASC";
        $employees_result = $conn->query($employees_query);
        $employees = [];
        
        if ($employees_result) {
            while ($row = $employees_result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        
        if (count($employees) > 0) {
            // Simulate face matching - in real implementation, use face comparison algorithms
            // For now, just pick first employee (random simulation)
            $matched_employee = $employees[array_rand($employees)];
            
            // Record attendance
            $today = date('Y-m-d');
            $current_time = date('H:i:s');
            
            // Check if employee already has attendance record for today
            $check_stmt = $conn->prepare("SELECT id, punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
            $check_stmt->bind_param("is", $matched_employee['id'], $today);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing record - set punch_out if punch_in already exists
                $record = $check_result->fetch_assoc();
                
                if ($record['punch_in'] && !$record['punch_out']) {
                    // Set punch_out
                    $update_stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, status = 'Present' WHERE user_id = ? AND date = ?");
                    $update_stmt->bind_param("sis", $current_time, $matched_employee['id'], $today);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    $action = 'Punch Out';
                } else {
                    // Already has punch_out, no action needed or update punch_in
                    $action = 'Already Checked Out';
                }
            } else {
                // Create new record with punch_in
                $insert_stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, status) VALUES (?, ?, ?, 'Present')");
                $insert_stmt->bind_param("iss", $matched_employee['id'], $today, $current_time);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                $action = 'Punch In';
            }
            
            $check_stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => $action . ' recorded successfully',
                'employee_name' => $matched_employee['name'],
                'employee_id' => $matched_employee['employee_id'],
                'time' => $current_time,
                'image_path' => $filepath
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No employees with photos found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid image data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
