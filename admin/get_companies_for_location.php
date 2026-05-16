<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

// Get location from request (can be empty)
$location = isset($_POST['location']) ? trim($_POST['location']) : '';

try {
    if (empty($location)) {
        // If no location specified, fetch ALL companies
        $query = "SELECT DISTINCT company FROM users WHERE status = 'Working' AND company IS NOT NULL AND company != '' ORDER BY company";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error, 'companies' => []]);
            exit();
        }
    } else {
        // Fetch companies for specific location
        $query = "SELECT DISTINCT company FROM users WHERE location = ? AND status = 'Working' AND company IS NOT NULL AND company != '' ORDER BY company";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error, 'companies' => []]);
            exit();
        }
        
        $stmt->bind_param("s", $location);
    }
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Query execute failed: ' . $stmt->error, 'companies' => []]);
        exit();
    }
    
    $result = $stmt->get_result();
    
    $companies = array();
    while ($row = $result->fetch_assoc()) {
        $companies[] = $row['company'];
    }
    
    $stmt->close();
    
    if (count($companies) > 0) {
        echo json_encode(['success' => true, 'companies' => $companies]);
    } else {
        echo json_encode(['success' => true, 'companies' => [], 'message' => 'No companies found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'companies' => []]);
}
?>


