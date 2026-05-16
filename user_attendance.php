<?php
session_start();

// Check if user is logged in as employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include('config/db.php');

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

header('Content-Type: application/json');

if ($action === 'punch_in') {
    // Check if already punched in today
    $today = date('Y-m-d');
    $check = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id = '$user_id' AND date = '$today'");
    
    if ($check->num_rows > 0) {
        $record = $check->fetch_assoc();
        if ($record['punch_in']) {
            echo json_encode(['success' => false, 'message' => 'Already punched in today']);
            exit();
        }
    } else {
        // Create new attendance record
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, status) VALUES (?, ?, 'Present')");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
    }
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['photo'])) {
        $upload_dir = 'uploads/selfies/' . date('Y/m/d') . '/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'punch_in_' . $user_id . '_' . time() . '.jpg';
        $photo_path = $upload_dir . $filename;
        
        // Save from base64 or file upload
        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
        }
    }
    
    // Record punch in
    $punch_in = date('H:i:s');
    $stmt = $conn->prepare("UPDATE attendance SET punch_in = ?, selfie_punchin = ? WHERE user_id = ? AND date = ?");
    $stmt->bind_param("ssis", $punch_in, $photo_path, $user_id, $today);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Punched in successfully',
            'punch_in_time' => $punch_in,
            'photo' => $photo_path
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating attendance: ' . $conn->error]);
    }
    
} elseif ($action === 'punch_out') {
    // Record punch out
    $today = date('Y-m-d');
    $punch_out = date('H:i:s');
    
    $photo_path = null;
    if (isset($_FILES['photo'])) {
        $upload_dir = 'uploads/selfies/' . date('Y/m/d') . '/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'punch_out_' . $user_id . '_' . time() . '.jpg';
        $photo_path = $upload_dir . $filename;
        
        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
        }
    }
    
    $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, selfie_punchout = ? WHERE user_id = ? AND date = ?");
    $stmt->bind_param("ssis", $punch_out, $photo_path, $user_id, $today);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Punched out successfully',
            'punch_out_time' => $punch_out,
            'photo' => $photo_path
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating attendance: ' . $conn->error]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
