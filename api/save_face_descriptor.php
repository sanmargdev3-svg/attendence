<?php
// API to accept extracted face descriptor and store in database
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['employee_id']) || !isset($input['descriptor'])) {
        throw new Exception('Missing employee_id or descriptor');
    }
    
    $employee_id = intval($input['employee_id']);
    $descriptor = $input['descriptor'];
    
    // Validate descriptor
    if (!is_array($descriptor) || count($descriptor) !== 128) {
        throw new Exception('Invalid descriptor - must be 128 values');
    }
    
    // Get employee
    $query = "SELECT id, name, employee_id FROM users WHERE id = ? AND role = 'employee'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    // Save descriptor to database
    $descriptorJson = json_encode($descriptor);
    $updateQuery = "UPDATE users SET face_descriptor = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('si', $descriptorJson, $employee_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Descriptor saved for {$employee['name']}",
        'employee_id' => $employee_id,
        'employee_name' => $employee['name'],
        'descriptor_values' => 128
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
