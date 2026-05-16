<?php
session_start();
include('../config/db.php');

// Enable login requirement
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <!-- <title class="titel">Employee Dashboard</title> -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Navbar Styling */
        .navbar-custom {
            position: relative;
        }
        
        .navbar-logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            height: 50px;
            z-index: 10;
        }
        
        .navbar-logo img {
            height: 100%;
            width: auto;
            max-width: 200px;
        }
        
        .navbar-welcome {
            position: absolute;
            left: 20px;
            display: flex;
            align-items: center;
            height: 100%;
            color: white;
        }
        
        .navbar-logout {
            margin-left: auto;
            padding-right: 20px;
        }

        .dashboard-card {
            border-left: 5px solid;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .card-punch-in {
            border-left-color: #28a745;
        }
        .card-punch-out {
            border-left-color: #dc3545;
        }
        .card-attendance {
            border-left-color: #17a2b8;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .card-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .card-links a {
            flex: 1;
            min-width: 100px;
        }

                /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar-welcome {
                display: none;
            }
            
            .navbar-logo {
                height: 45px;
            }
            
            .navbar-logo img {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>

<!-- Centered Logo Navbar -->
<nav class="navbar navbar-dark bg-dark navbar-custom" style="min-height: 100px;">
    <div class="container-fluid position-relative">
        <!-- Welcome Text (Left) -->
        <div class="navbar-welcome">
            <span class="text-white">Welcome, <?php echo htmlspecialchars($user['name'] ?? 'Employee'); ?></span>
        </div>

        <!-- Centered Logo -->
        <div class="navbar-logo">
            <img src="../assets/images/logo.png" alt="Company Logo">
        </div>

        <!-- Logout Button (Right) -->
        <div class="navbar-logout">
            <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-5 mb-5">
    <h3 class="mb-4 text-center">Employee Dashboard</h3>
    <div class="row g-4">
        
        <!-- Punch In Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card dashboard-card card-punch-in shadow">
                <div class="card-body">
                    <h5 class="card-title">🔓 Punch In</h5>
                    <p class="card-text text-muted">Mark your attendance by punching in when you arrive at the office.</p>
                    <div class="card-links">
                        <a href="punch_in.php" class="btn btn-success btn-sm">Punch In</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Punch Out Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card dashboard-card card-punch-out shadow">
                <div class="card-body">
                    <h5 class="card-title">🔒 Punch Out</h5>
                    <p class="card-text text-muted">Mark your departure by punching out when you leave the office.</p>
                    <div class="card-links">
                        <a href="punch_out.php" class="btn btn-danger btn-sm">Punch Out</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Attendance Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card dashboard-card card-attendance shadow">
                <div class="card-body">
                    <h5 class="card-title">📋 My Attendance</h5>
                    <p class="card-text text-muted">View your attendance history and check your punch records.</p>
                    <div class="card-links">
                        <a href="my_attendance.php" class="btn btn-info btn-sm">View Attendance</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>