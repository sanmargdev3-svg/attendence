<?php
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    // Get ALL employees (with or without descriptors)
    $query = "SELECT id, name, employee_id, face_descriptor 
              FROM users 
              WHERE role = 'employee' 
              ORDER BY name";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $descriptors = array();
    $employees = array();
    $photosToProcess = array();
    $descriptorCount = 0;
    
    while ($emp = $result->fetch_assoc()) {
        $empId = $emp['id'];
        
        // Store employee info
        $employees[$empId] = array(
            'id' => $empId,
            'name' => $emp['name'],
            'employee_id' => $emp['employee_id']
        );
        
        // Check if descriptor exists in database
        if (!empty($emp['face_descriptor'])) {
            $descriptor = json_decode($emp['face_descriptor'], true);
            
            if (is_array($descriptor) && count($descriptor) === 128) {
                // Valid descriptor found
                $descriptors[$empId] = $descriptor;
                $descriptorCount++;
                error_log("✅ Loaded descriptor for {$emp['name']} (ID: $empId)");
            } else {
                error_log("⚠️ Invalid descriptor for {$emp['name']} (ID: $empId)");
            }
        } else {
            // No descriptor in database - check if photo exists
            $photoPattern = __DIR__ . '/../uploads/employee_photos/' . $empId . '.*';
            $photos = glob($photoPattern);
            
            if (!empty($photos)) {
                $photoPath = $photos[0];
                if (file_exists($photoPath)) {
                    $photoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($photoPath));
                    $photosToProcess[$empId] = array(
                        'name' => $emp['name'],
                        'photo' => $photoBase64,
                        'path' => $photoPath
                    );
                    error_log("📸 Photo found for {$emp['name']} (ID: $empId) - needs descriptor extraction");
                }
            }
        }
    }
    
    echo json_encode(array(
        'success' => true,
        'descriptors' => $descriptors,
        'employees' => $employees,
        'photos_to_process' => $photosToProcess,
        'descriptor_count' => $descriptorCount,
        'pending_count' => count($photosToProcess),
        'total_employees' => count($employees),
        'message' => "Loaded $descriptorCount descriptors, " . count($photosToProcess) . " photos pending extraction"
    ));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>
