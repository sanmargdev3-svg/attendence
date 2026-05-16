<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: auth/login.php");
    exit();
}

// Redirect based on user role
if ($_SESSION['role'] === 'admin') {
    header("Location: admin/dashboard.php");
} else {
    header("Location: employee/dashboard.php");
}
exit();
?>
