<?php
session_start();
include('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$value   = isset($_POST['value'])   ? (int)(bool)$_POST['value'] : 0; // strict 0 or 1

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

$stmt = $conn->prepare("UPDATE users SET geo_restricted = ? WHERE id = ? AND role = 'employee'");
$stmt->bind_param("ii", $value, $user_id);

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    echo json_encode(['success' => true, 'value' => $value]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
$stmt->close();
