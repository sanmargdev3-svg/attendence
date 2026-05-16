<?php
session_start();
include('../config/db.php');

// Allow API to work without strict session check (for debugging)
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Not authenticated']);
//     exit;
// }

$photo_dir = '../uploads/employee_photos';

// Create directory if doesn't exist
if (!is_dir($photo_dir)) {
    mkdir($photo_dir, 0755, true);
}

// Fetch all employees
$photos = array();
$stmt = $conn->prepare("SELECT id, name, employee_id FROM users WHERE role = 'employee' ORDER BY name");

if (!$stmt) {
    error_log("Query prepare failed: " . $conn->error);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'debug' => $conn->error
    ]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

$total = 0;
$found = 0;

while ($row = $result->fetch_assoc()) {
    $total++;
    
    // Look for photo file [employee_id].(jpg|jpeg|png)
    $photo_patterns = array(
        $row['id'] . '.jpg',
        $row['id'] . '.jpeg',
        $row['id'] . '.png'
    );
    
    $photo_found = false;
    $photo_data = null;
    $mime_type = 'image/jpeg';
    
    foreach ($photo_patterns as $filename) {
        $full_path = $photo_dir . '/' . $filename;
        
        if (file_exists($full_path)) {
            $photo_data = file_get_contents($full_path);
            $found++;
            $photo_found = true;
            
            if (strpos($filename, '.png') !== false) {
                $mime_type = 'image/png';
            }
            
            error_log("Found photo: " . $filename . " for employee " . $row['name']);
            break;
        }
    }
    
    if ($photo_found && $photo_data) {
        $photos[] = array(
            'id' => intval($row['id']),
            'name' => $row['name'],
            'employee_id' => $row['employee_id'],
            'photo_base64' => 'data:' . $mime_type . ';base64,' . base64_encode($photo_data)
        );
    }
}

$stmt->close();

// Return response
$response = array(
    'success' => true,
    'photos' => $photos,
    'count' => count($photos),
    'total_employees' => $total,
    'employees_with_photos' => $found,
    'debug' => array(
        'photo_dir' => $photo_dir,
        'dir_exists' => is_dir($photo_dir),
        'message' => "Found $found photos out of $total employees"
    )
);

header('Content-Type: application/json');
echo json_encode($response);
?>

