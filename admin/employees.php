    <?php
session_start();
include('../config/db.php');

// Enable authentication check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Determine dashboard to return to based on 'from' parameter or user role
$from = isset($_GET['from']) ? htmlspecialchars($_GET['from']) : '';
if ($from === 'suparadmin') {
    $back_dashboard = 'dashboard.php';
} elseif ($from === 'admin') {
    $back_dashboard = 'admin_dashboard.php';
} elseif ($from === 'employee') {
    $back_dashboard = '../employee/dashboard.php';
} else {
    // Default based on role
    $back_dashboard = ($_SESSION['role'] === 'suparadmin') ? 'dashboard.php' : 'admin_dashboard.php';
}

$message = "";
$message_type = "";

// Add columns if they don't exist
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
if ($check_col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN employee_id VARCHAR(50)");
}
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'company'");
if ($check_col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN company VARCHAR(100)");
}
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($check_col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) UNIQUE");
}
// Add other columns safely
$columns_to_add = [
    'shift_time' => 'VARCHAR(50)',
    'location' => 'VARCHAR(100)',
    'date_of_joining' => 'DATE',
    'date_of_exit' => 'DATE',
    'status' => "ENUM('Working', 'Resign') DEFAULT 'Working'",
    'sex' => "ENUM('Male', 'Female', 'Other')",
    'week_off' => "ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')",
    'password_set' => 'BOOLEAN DEFAULT FALSE'
];

foreach ($columns_to_add as $col_name => $col_type) {
    $check_col = $conn->query("SHOW COLUMNS FROM users LIKE '$col_name'");
    if ($check_col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col_name $col_type");
    }
}

// Create departments table if it doesn't exist
$check_table = $conn->query("SHOW TABLES LIKE 'departments'");
if ($check_table->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS departments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Create companies table if it doesn't exist
$check_table = $conn->query("SHOW TABLES LIKE 'companies'");
if ($check_table->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS companies (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Create shifts table if it doesn't exist
$check_table = $conn->query("SHOW TABLES LIKE 'shifts'");
if ($check_table->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS shifts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Create locations table if it doesn't exist
$check_table = $conn->query("SHOW TABLES LIKE 'locations'");
if ($check_table->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS locations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Fetch all existing departments
$dept_stmt = $conn->prepare("SELECT name FROM departments ORDER BY name");
if (!$dept_stmt) {
    error_log("Department prepare error: " . $conn->error);
    $departments = [];
} else {
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $departments = [];
    while ($dept_row = $dept_result->fetch_assoc()) {
        $departments[] = $dept_row['name'];
    }
    $dept_stmt->close();
}

// Fetch all existing companies
$comp_stmt = $conn->prepare("SELECT id, name FROM companies ORDER BY name");
if (!$comp_stmt) {
    error_log("Company prepare error: " . $conn->error);
    $companies = [];
} else {
    $comp_stmt->execute();
    $comp_result = $comp_stmt->get_result();
    $companies = [];
    while ($comp_row = $comp_result->fetch_assoc()) {
        $companies[] = $comp_row;
    }
    $comp_stmt->close();
}

// Fetch all existing shifts
$shift_stmt = $conn->prepare("SELECT id, start_time, end_time FROM shifts ORDER BY start_time");
if (!$shift_stmt) {
    error_log("Shift prepare error: " . $conn->error);
    $shifts = [];
} else {
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    $shifts = [];
    while ($shift_row = $shift_result->fetch_assoc()) {
        $start_time = date('h:i A', strtotime($shift_row['start_time']));
        $end_time = date('h:i A', strtotime($shift_row['end_time']));
        $shift_row['display_name'] = $start_time . ' - ' . $end_time;
        $shifts[] = $shift_row;
    }
    $shift_stmt->close();
}

// Fetch all existing locations
$loc_stmt = $conn->prepare("SELECT id, name FROM locations ORDER BY name");
if (!$loc_stmt) {
    error_log("Location prepare error: " . $conn->error);
    $locations = [];
} else {
    $loc_stmt->execute();
    $loc_result = $loc_stmt->get_result();
    $locations = [];
    while ($loc_row = $loc_result->fetch_assoc()) {
        $locations[] = $loc_row;
    }
    $loc_stmt->close();
}

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $department = htmlspecialchars($_POST['department']);
    $employee_id = htmlspecialchars($_POST['employee_id']);
    $company = htmlspecialchars($_POST['company']);
    $phone = htmlspecialchars($_POST['phone']);
    $shift_time = htmlspecialchars($_POST['shift_time']);
    $location = htmlspecialchars($_POST['location']);
    $date_of_joining = htmlspecialchars($_POST['date_of_joining']);
    $date_of_exit = htmlspecialchars($_POST['date_of_exit'] ?? '');
    $status = htmlspecialchars($_POST['status']);
    $sex = htmlspecialchars($_POST['sex']);
    $week_off = htmlspecialchars($_POST['week_off']);
    
    // Validation
    if (empty($name) || empty($department) || empty($employee_id) || 
        empty($company) || empty($phone) || empty($shift_time) || empty($location) || 
        empty($date_of_joining) || empty($status) || empty($sex) || empty($week_off)) {
        $message = "All mandatory fields are required (Email is optional and Date of Exit is only required when Status is Resign)";
        $message_type = "danger";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format";
        $message_type = "danger";
    } elseif ($status === 'Resign' && empty($date_of_exit)) {
        $message = "Date of Exit is required when Status is 'Resign'";
        $message_type = "danger";
    } else {
        // Check if phone already exists
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt_check->bind_param("s", $phone);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message = "Phone number already exists";
            $message_type = "danger";
        } else {
            // Create employee WITHOUT password - Super Admin will set it later
            // Use a placeholder password that cannot login (starts with !)
            $placeholder_password = password_hash('!' . uniqid(), PASSWORD_BCRYPT);
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, role, department, employee_id, company, phone, shift_time, location, date_of_joining, date_of_exit, status, sex, week_off, password_set) VALUES (?, ?, ?, 'employee', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)");
            $stmt_insert->bind_param("ssssssssssssss", $name, $email, $placeholder_password, $department, $employee_id, $company, $phone, $shift_time, $location, $date_of_joining, $date_of_exit, $status, $sex, $week_off);
            
            if ($stmt_insert->execute()) {
                $message = "✓ Employee added successfully! Super Admin must set password before employee can login.";
                $message_type = "success";
            } else {
                $message = "Error: " . $stmt_insert->error;
                $message_type = "danger";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// Handle Update Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $emp_id = (int)$_POST['emp_id'];
    $name = htmlspecialchars($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $department = htmlspecialchars($_POST['department']);
    $employee_id = htmlspecialchars($_POST['employee_id']);
    $company = htmlspecialchars($_POST['company']);
    $phone = htmlspecialchars($_POST['phone']);
    $shift_time = htmlspecialchars($_POST['shift_time']);
    $location = htmlspecialchars($_POST['location']);
    $date_of_joining = htmlspecialchars($_POST['date_of_joining']);
    $date_of_exit = htmlspecialchars($_POST['date_of_exit'] ?? '');
    $status = htmlspecialchars($_POST['status']);
    $sex = htmlspecialchars($_POST['sex']);
    $week_off = htmlspecialchars($_POST['week_off']);
    
    // Validation
    if (empty($name) || empty($department) || empty($employee_id) || empty($company) || 
        empty($phone) || empty($shift_time) || empty($location) || 
        empty($date_of_joining) || empty($status) || empty($sex) || empty($week_off)) {
        $message = "All mandatory fields are required (Email is optional and Date of Exit is only required when Status is Resign)";
        $message_type = "danger";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format";
        $message_type = "danger";
    } elseif ($status === 'Resign' && empty($date_of_exit)) {
        $message = "Date of Exit is required when Status is 'Resign'";
        $message_type = "danger";
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET name=?, email=?, department=?, employee_id=?, company=?, phone=?, shift_time=?, location=?, date_of_joining=?, date_of_exit=?, status=?, sex=?, week_off=? WHERE id=?");
        $stmt_update->bind_param("sssssssssssssi", $name, $email, $department, $employee_id, $company, $phone, $shift_time, $location, $date_of_joining, $date_of_exit, $status, $sex, $week_off, $emp_id);
        
        if ($stmt_update->execute()) {
            $message = "✓ Employee updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt_update->error;
            $message_type = "danger";
        }
        $stmt_update->close();
    }
}

// Handle Delete Employee
if (isset($_GET['delete_employee'])) {
    $delete_id = (int)$_GET['delete_employee'];
    
    // Delete the employee (CASCADE will delete related attendance records)
    $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
    $stmt_delete->bind_param("i", $delete_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $message = "✓ Employee deleted successfully!";
            $message_type = "success";
        } else {
            $message = "⚠ Employee not found";
            $message_type = "warning";
        }
    } else {
        $message = "✗ Error: " . $stmt_delete->error;
        $message_type = "danger";
    }
    $stmt_delete->close();
}

// Handle AJAX fetch employee data
if (isset($_GET['fetch_employee'])) {
    $fetch_id = (int)$_GET['fetch_employee'];
    $stmt_fetch = $conn->prepare("SELECT id, name, email, employee_id, company, phone, department, shift_time, location, date_of_joining, date_of_exit, status, sex, week_off FROM users WHERE id = ? AND role = 'employee'");
    $stmt_fetch->bind_param("i", $fetch_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    header('Content-Type: application/json');
    
    if ($result_fetch->num_rows > 0) {
        $emp = $result_fetch->fetch_assoc();
        echo json_encode([
            'success' => true,
            'employee' => $emp
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Employee not found'
        ]);
    }
    
    $stmt_fetch->close();
    exit;
}

// Fetch all employees using prepared statement
$stmt = $conn->prepare("SELECT id, name, email, department, employee_id, company, phone, shift_time, location, date_of_joining, date_of_exit, status, sex, week_off, password_set FROM users WHERE role = 'employee' ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Attendance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-input-group .form-control {
            padding-right: 45px;
        }
        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-btn:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Employees Management</span>
            <div>
                <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="<?php echo htmlspecialchars($back_dashboard); ?>" class="btn btn-secondary btn-sm">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Toggle Button with Import/Export Options -->
        <div class="mb-4">
            <button class="btn btn-primary btn-lg" onclick="toggleForm()" id="toggleBtn">
                ➕ Add New Employee
            </button>
            <a href="import_employees.php" class="btn btn-info btn-lg me-2">📥 Import Excel</a>
            <a href="#" class="btn btn-success btn-lg" onclick="exportEmployees()">📤 Export Excel</a>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label"><strong>🔍 Search Employee</strong></label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search by name, email, employee ID, or phone...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><strong>📋 Employee ID</strong></label>
                        <select class="form-control" id="employeeIdFilter">
                            <option value="">No Sort</option>
                            <option value="asc">🔼 First to Last (1-100)</option>
                            <option value="desc">🔽 Last to First (100-1)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><strong>🏢 Company</strong></label>
                        <select class="form-control" id="companyFilter">
                            <option value="">All Companies</option>
                            <?php 
                            $companies_list = array();
                            foreach ($companies as $comp) {
                                $companies_list[] = $comp['name'];
                            }
                            sort($companies_list);
                            foreach ($companies_list as $comp): ?>
                                <option value="<?php echo htmlspecialchars($comp); ?>"><?php echo htmlspecialchars($comp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><strong>🏭 Department</strong></label>
                        <select class="form-control" id="departmentFilter">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add Employee Form (Hidden by default) -->
        <div class="card mb-4" id="addForm" style="display: none;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">➕ Add New Employee</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" placeholder="e.g., EMP001" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email" >
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="e.g., 9876543210" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="sex" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select name="company" class="form-control" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo htmlspecialchars($comp['name']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shift Time</label>
                        <select name="shift_time" class="form-control" required>
                            <option value="">Select Shift</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo htmlspecialchars($shift['display_name']); ?>"><?php echo htmlspecialchars($shift['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <select name="location" class="form-control" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc['name']); ?>"><?php echo htmlspecialchars($loc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date of Joining</label>
                        <input type="date" name="date_of_joining" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status </label>
                        <select name="status" class="form-control add-status-select" required onchange="toggleExitDateField(this, 'add')">
                            <option value="">Select Status</option>
                            <option value="Working">Working</option>
                            <option value="Resign">Resign</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="addExitDateField" style="display:none;">
                        <label class="form-label">Date of Exit <span class="text-danger"></span></label>
                        <input type="date" name="date_of_exit" class="form-control add-exit-date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Week Off</label>
                        <select name="week_off" class="form-control" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info" role="alert">
                            <strong>ℹ️ Password Management:</strong> Employee password will be set by Super Admin after employee creation. The employee will not be able to login until password is set.
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_employee" class="btn btn-success">✓ Add Employee</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">✕ Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees List -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">All Employees</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="employeesTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Employee ID</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Photo</th>
                                <th>Pass. Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                $pass_status = $row['password_set'] ? '<span class="badge bg-success">✓ Set</span>' : '<span class="badge bg-warning">✗ Not Set</span>';
                                
                                // Check if employee has photo
                                $photoPath = "../uploads/employee_photos/" . $row['id'] . ".*";
                                $photos = glob($photoPath);
                                $hasPhoto = !empty($photos);
                                
                                echo "<tr>";
                                echo "<td>" . $row['id'] . "</td>";
                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td>" . ($row['employee_id'] ?? 'N/A') . "</td>";
                                echo "<td>" . ($row['company'] ?? 'N/A') . "</td>";
                                echo "<td>" . ($row['phone'] ?? 'N/A') . "</td>";
                                echo "<td>" . ($row['department'] ?? 'N/A') . "</td>";
                                
                                // Photo column
                                echo "<td>";
                                if ($hasPhoto) {
                                    $photoFile = $photos[0];
                                    $photoUrl = "../uploads/employee_photos/" . basename($photoFile);
                                    echo "<img src='" . htmlspecialchars($photoUrl) . "' alt='Photo' style='width: 50px; height: 50px; border-radius: 5px; object-fit: cover; cursor: pointer;' onclick=\"showPhotoModal('" . htmlspecialchars($photoUrl) . "', '" . htmlspecialchars($row['name']) . "')\" title='Click to view'>";
                                } else {
                                    echo "<span class='text-muted'>No photo</span>";
                                }
                                echo "</td>";
                                
                                echo "<td>" . $pass_status . "</td>";
                                echo "<td>";
                                echo "<a href='?view_employee=" . $row['id'] . "' class='btn btn-sm btn-info'>View</a> ";
                                echo "<button type='button' class='btn btn-sm btn-warning' onclick=\"openEditModalFromTable(" . $row['id'] . ")\">Edit</button> ";
                                echo "<button type='button' onclick=\"openPhotoUpload(" . $row['id'] . ", '" . htmlspecialchars($row['name']) . "')\" class='btn btn-sm btn-secondary'>📸 Photo</button> ";
                                // if ($hasPhoto) {
                                //     echo "<button type='button' class='btn btn-sm btn-danger' onclick=\"deleteEmployeePhoto(" . $row['id'] . ", '" . htmlspecialchars($row['name']) . "')\">🗑️ Del</button> ";
                                // }
                                echo "<button type='button' onclick=\"confirmDeleteEmployee(" . $row['id'] . ")\" class='btn btn-sm btn-danger'>Delete</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Employee Details Section -->
        <?php
        if (isset($_GET['view_employee'])) {
            $view_id = (int)$_GET['view_employee'];
            $stmt_view = $conn->prepare("SELECT id, name, email, employee_id, company, phone, department, shift_time, location, date_of_joining, date_of_exit, status, sex, week_off FROM users WHERE id = ? AND role = 'employee'");
            $stmt_view->bind_param("i", $view_id);
            $stmt_view->execute();
            $result_view = $stmt_view->get_result();
            
            if ($result_view->num_rows > 0) {
                $emp = $result_view->fetch_assoc();
                $is_edit = isset($_GET['edit']) && $_GET['edit'] == '1';
                ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📋 Employee <?php echo $is_edit ? 'Edit' : 'Details'; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$is_edit): ?>
                            <!-- Employee Photo Section -->
                            <div class="row mb-4">
                                <div class="col-md-3 text-center">
                                    <?php 
                                    $photoPath = "../uploads/employee_photos/" . $emp['id'] . ".*";
                                    $photos = glob($photoPath);
                                    if (!empty($photos)) {
                                        $photoFile = $photos[0];
                                        $photoUrl = "../uploads/employee_photos/" . basename($photoFile);
                                        ?>
                                        <div style="border: 2px solid #007bff; border-radius: 10px; padding: 10px; background: #f8f9fa;">
                                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Employee Photo" style="max-width: 100%; max-height: 300px; border-radius: 8px; object-fit: cover;">
                                            <p class="text-muted mt-2"><small>✅ Face photo available</small></p>
                                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="deleteEmployeePhoto(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete Photo
                                            </button>
                                        </div>
                                        <?php
                                    } else {
                                        ?>
                                        <div style="border: 2px dashed #ccc; border-radius: 10px; padding: 30px; background: #f8f9fa; text-align: center;">
                                            <i class="fas fa-image" style="font-size: 50px; color: #ccc;"></i>
                                            <p class="text-muted mt-3">❌ No photo uploaded</p>
                                            <a href="employees.php" class="btn btn-sm btn-info">← Back to Upload</a>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($emp['name']); ?></p>
                                            <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($emp['employee_id']); ?></p>
                                            <p><strong>Sex:</strong> <?php echo htmlspecialchars($emp['sex']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($emp['email']); ?></p>
                                            <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($emp['phone']); ?></p>
                                            <p><strong>Department:</strong> <?php echo htmlspecialchars($emp['department'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Company:</strong> <?php echo htmlspecialchars($emp['company']); ?></p>
                                            <p><strong>Shift Time:</strong> <?php echo htmlspecialchars($emp['shift_time']); ?></p>
                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($emp['location']); ?></p>
                                            <p><strong>Date of Joining:</strong> <?php echo htmlspecialchars($emp['date_of_joining']); ?></p>
                                            <p><strong>Status:</strong> <span class="badge <?php echo $emp['status'] === 'Working' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($emp['status']); ?></span></p>
                                            <?php if ($emp['status'] === 'Resign' && !empty($emp['date_of_exit'])): ?>
                                            <p><strong>Date of Exit:</strong> <?php echo htmlspecialchars($emp['date_of_exit']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Week Off:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($emp['week_off'] ?? 'N/A'); ?></span></p>
                                            <p><strong>User ID:</strong> <?php echo $emp['id']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <a href="?view_employee=<?php echo $emp['id']; ?>" class="btn btn-info">View Details</a>
                            <button type="button" class="btn btn-warning" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">✎ Edit Employee</button>
                            <a href="employees.php" class="btn btn-secondary">Back to List</a>
                        <?php else: ?>
                            <!-- Edit form shown in modal, not here -->
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            $stmt_view->close();
        }
        ?>

        <!-- <div class="mt-3">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleForm() {
            const form = document.getElementById('addForm');
            const btn = document.getElementById('toggleBtn');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                btn.textContent = '✕ Close Form';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
            } else {
                form.style.display = 'none';
                btn.textContent = '➕ Add New Employee';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
            }
        }
        
        function togglePasswordField(fieldId) {
            const field = document.getElementById(fieldId);
            const btn = event.target.closest('.toggle-password-btn');
            const icon = btn.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function confirmDeleteEmployee(employeeId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This employee and all their attendance records will be deleted permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_employee=' + employeeId;
                }
            });
        }
        
        <?php
        if ($message) {
            $icon = 'success';
            $title = 'Success';
            if ($message_type === 'danger') {
                $icon = 'error';
                $title = 'Error';
            } elseif ($message_type === 'warning') {
                $icon = 'warning';
                $title = 'Warning';
            }
            $message_escaped = addslashes($message);
            echo "Swal.fire({
                icon: '$icon',
                title: '$title',
                text: '$message_escaped',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });";
        }
        ?>

        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('employeeIdFilter').addEventListener('change', filterTable);
        document.getElementById('companyFilter').addEventListener('change', filterTable);
        document.getElementById('departmentFilter').addEventListener('change', filterTable);

        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const employeeIdSort = document.getElementById('employeeIdFilter').value;
            const companyFilter = document.getElementById('companyFilter').value.toLowerCase();
            const departmentFilter = document.getElementById('departmentFilter').value.toLowerCase();
            const table = document.getElementById('employeesTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            let rows = Array.from(tbody.getElementsByTagName('tr'));

            // Filter rows based on search and other filters
            rows = rows.filter(row => {
                const cells = row.getElementsByTagName('td');
                const name = cells[1].textContent.toLowerCase();
                const email = cells[2].textContent.toLowerCase();
                const employeeId = cells[3].textContent.toLowerCase();
                const company = cells[4].textContent.toLowerCase();
                const phone = cells[5].textContent.toLowerCase();
                const department = cells[6].textContent.toLowerCase();

                const matchesSearch = searchInput === '' || 
                    name.includes(searchInput) || 
                    email.includes(searchInput) || 
                    employeeId.includes(searchInput) || 
                    phone.includes(searchInput);

                const matchesCompany = companyFilter === '' || 
                    company === companyFilter;

                const matchesDepartment = departmentFilter === '' || 
                    department === departmentFilter;

                return matchesSearch && matchesCompany && matchesDepartment;
            });

            // Sort by Employee ID if selected, otherwise sort by Name (A-Z)
            if (employeeIdSort === 'asc' || employeeIdSort === 'desc') {
                // Sort by Employee ID
                rows.sort((a, b) => {
                    const cellsA = a.getElementsByTagName('td');
                    const cellsB = b.getElementsByTagName('td');
                    
                    // Extract numeric part from employee ID
                    const idA = parseInt(cellsA[3].textContent.replace(/\D/g, '')) || 0;
                    const idB = parseInt(cellsB[3].textContent.replace(/\D/g, '')) || 0;
                    
                    return employeeIdSort === 'asc' ? idA - idB : idB - idA;
                });
            } else {
                // Default: Sort by Name A-Z
                rows.sort((a, b) => {
                    const cellsA = a.getElementsByTagName('td');
                    const cellsB = b.getElementsByTagName('td');
                    
                    const nameA = cellsA[1].textContent.toLowerCase();
                    const nameB = cellsB[1].textContent.toLowerCase();
                    
                    return nameA.localeCompare(nameB);
                });
            }

            // Clear tbody and re-append sorted rows
            const allRows = Array.from(tbody.getElementsByTagName('tr'));
            allRows.forEach(row => row.style.display = 'none');

            rows.forEach(row => {
                row.style.display = '';
                tbody.appendChild(row);
            });
        }

        // Export employees with optional filters
        function exportEmployees() {
            const department = document.getElementById('departmentFilter').value;
            const company = document.getElementById('companyFilter').value;
            const employeeId = document.getElementById('employeeIdFilter').value;
            
            let url = 'export_employees.php?';
            if (department) url += 'department=' + encodeURIComponent(department) + '&';
            if (company) url += 'company=' + encodeURIComponent(company) + '&';
            if (employeeId) url += 'employee_id=' + encodeURIComponent(employeeId);
            
            window.location.href = url;
        }

        // Photo upload modal functions
        function openPhotoUpload(employeeId, employeeName) {
            document.getElementById('photoEmployeeId').value = employeeId;
            document.getElementById('photoEmployeeName').textContent = employeeName;
            const photoModal = new bootstrap.Modal(document.getElementById('photoUploadModal'));
            photoModal.show();
            // Start camera after modal is shown
            setTimeout(startPhotoCamera, 500);
        }

        function uploadEmployeePhoto() {
            const employeeId = document.getElementById('photoEmployeeId').value;
            const canvas = document.getElementById('photoCanvas');
            const video = document.getElementById('cameraVideo');
            
            if (!canvas || !video.srcObject) {
                Swal.fire('Error', 'Camera not active. Please try again', 'error');
                return;
            }

            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('employee_id', employeeId);
                formData.append('photo', blob, 'camera_capture.jpg');

                Swal.fire({
                    title: 'Uploading...',
                    html: 'Processing captured photo...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('../api/upload_employee_photo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Stop camera
                        if (video.srcObject) {
                            video.srcObject.getTracks().forEach(track => track.stop());
                        }
                        
                        // Close the modal and show success
                        const photoModal = bootstrap.Modal.getInstance(document.getElementById('photoUploadModal'));
                        photoModal.hide();
                        
                        // Clean up backdrop
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        
                        // Show success message
                        Swal.fire('Success!', 'Photo captured successfully! The face descriptor will be extracted when the employee opens the face attendance app.', 'success');
                    } else {
                        // Keep modal open, show error
                        Swal.fire('Error', data.error || data.message || 'Failed to upload photo', 'error');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    Swal.fire('Error', 'Upload failed: ' + error.message, 'error');
                });
            }, 'image/jpeg', 0.95);
        }

        function deleteEmployeePhoto(employeeId, employeeName) {
            Swal.fire({
                title: 'Delete Photo?',
                text: `Are you sure you want to delete ${employeeName}'s photo?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Deleting...',
                        html: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send delete request
                    fetch('../api/delete_employee_photo.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            employee_id: employeeId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', 'Photo deleted successfully.', 'success').then(() => {
                                // Reload the page
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete photo', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Delete error:', error);
                        Swal.fire('Error', 'Delete failed: ' + error.message, 'error');
                    });
                }
            });
        }

        // Show Photo Modal
        function showPhotoModal(photoUrl, employeeName) {
            Swal.fire({
                title: employeeName + "'s Photo",
                imageUrl: photoUrl,
                imageWidth: 400,
                imageHeight: 400,
                imageAlt: 'Employee Photo',
                showCloseButton: true,
                confirmButtonText: 'Close',
                didOpen: () => {
                    const swalImage = document.querySelector('.swal2-image');
                    if (swalImage) {
                        swalImage.style.objectFit = 'cover';
                        swalImage.style.borderRadius = '10px';
                    }
                }
            });
        }

        function startPhotoCamera() {
            const video = document.getElementById('cameraVideo');
            
            if (video.srcObject) {
                // Camera already running
                return;
            }

            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            })
            .then(stream => {
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                };
            })
            .catch(err => {
                Swal.fire('Camera Error', 'Cannot access camera: ' + err.message, 'error');
            });
        }

        function stopPhotoCamera() {
            const video = document.getElementById('cameraVideo');
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
            }
        }

        // Stop camera when modal is closed
        document.getElementById('photoUploadModal')?.addEventListener('hide.bs.modal', stopPhotoCamera);

        // Toggle Date of Exit field based on Status selection
        function toggleExitDateField(selectElement, formType) {
            const exitDateField = document.getElementById(formType + 'ExitDateField');
            const exitDateInput = document.querySelector('.' + formType + '-exit-date');
            const status = selectElement.value;

            if (status === 'Resign') {
                // Show exit date field and make it required
                exitDateField.style.display = 'block';
                exitDateInput.setAttribute('required', 'required');
            } else {
                // Hide exit date field, remove required, and clear value
                exitDateField.style.display = 'none';
                exitDateInput.removeAttribute('required');
                exitDateInput.value = '';
            }
        }

        // Initialize on form load if in edit mode with Resign status
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're on the edit form
            const editStatusSelect = document.querySelector('.edit-status-select');
            if (editStatusSelect && editStatusSelect.value === 'Resign') {
                // Already handled by PHP display, but ensure input is marked required
                const exitDateInput = document.querySelector('.edit-exit-date');
                if (exitDateInput) {
                    exitDateInput.setAttribute('required', 'required');
                }
            }

            // Populate select dropdown values for modal
            populateModalSelects();
        });

        // Populate dropdown options in the modal
        function populateModalSelects() {
            const departments = <?php echo json_encode($departments); ?>;
            const companies = <?php echo json_encode(array_column($companies, 'name')); ?>;
            const shifts = <?php echo json_encode(array_column($shifts, 'display_name')); ?>;
            const locations = <?php echo json_encode(array_column($locations, 'name')); ?>;

            // Fill Department dropdown
            const deptSelect = document.getElementById('modalDepartment');
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                deptSelect.appendChild(option);
            });

            // Fill Company dropdown
            const compSelect = document.getElementById('modalCompany');
            companies.forEach(comp => {
                const option = document.createElement('option');
                option.value = comp;
                option.textContent = comp;
                compSelect.appendChild(option);
            });

            // Fill Shift Time dropdown
            const shiftSelect = document.getElementById('modalShiftTime');
            shifts.forEach(shift => {
                const option = document.createElement('option');
                option.value = shift;
                option.textContent = shift;
                shiftSelect.appendChild(option);
            });

            // Fill Location dropdown
            const locSelect = document.getElementById('modalLocation');
            locations.forEach(loc => {
                const option = document.createElement('option');
                option.value = loc;
                option.textContent = loc;
                locSelect.appendChild(option);
            });
        }

        // Open Edit Modal from table row - fetch employee data via AJAX
        function openEditModalFromTable(empId) {
            Swal.fire({
                title: 'Loading...',
                html: 'Fetching employee details...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fetch employee data
            fetch('employees.php?fetch_employee=' + empId, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success && data.employee) {
                    openEditModal(data.employee);
                } else {
                    Swal.fire('Error', data.error || 'Failed to fetch employee data', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire('Error', 'Failed to fetch employee: ' + error.message, 'error');
            });
        }

        // Open Edit Modal and populate with employee data
        function openEditModal(empData) {
            // Populate form fields
            document.getElementById('modalEmpId').value = empData.id;
            document.getElementById('modalEmployeeId').value = empData.employee_id;
            document.getElementById('modalName').value = empData.name;
            document.getElementById('modalEmail').value = empData.email;
            document.getElementById('modalPhone').value = empData.phone;
            document.getElementById('modalSex').value = empData.sex;
            document.getElementById('modalDepartment').value = empData.department;
            document.getElementById('modalCompany').value = empData.company;
            document.getElementById('modalShiftTime').value = empData.shift_time;
            document.getElementById('modalLocation').value = empData.location;
            document.getElementById('modalDateOfJoining').value = empData.date_of_joining;
            document.getElementById('modalStatus').value = empData.status;
            document.getElementById('modalDateOfExit').value = empData.date_of_exit || '';
            document.getElementById('modalWeekOff').value = empData.week_off;

            // Toggle exit date field based on status
            if (empData.status === 'Resign') {
                document.getElementById('modalExitDateField').style.display = 'block';
                document.getElementById('modalDateOfExit').setAttribute('required', 'required');
            } else {
                document.getElementById('modalExitDateField').style.display = 'none';
                document.getElementById('modalDateOfExit').removeAttribute('required');
            }

            // Open modal
            const modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
            modal.show();
        }

        // Toggle Date of Exit field in modal
        function toggleExitDateFieldModal(selectElement) {
            const exitDateField = document.getElementById('modalExitDateField');
            const exitDateInput = document.getElementById('modalDateOfExit');
            const status = selectElement.value;

            if (status === 'Resign') {
                exitDateField.style.display = 'block';
                exitDateInput.setAttribute('required', 'required');
            } else {
                exitDateField.style.display = 'none';
                exitDateInput.removeAttribute('required');
                exitDateInput.value = '';
            }
        }

        // Submit the edit form
        function submitEditForm() {
            const form = document.getElementById('editEmployeeForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Updating...',
                html: 'Please wait while employee is being updated...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form via AJAX
            const formData = new FormData(form);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Check if update was successful by looking for the success message in response
                if (data.includes('Employee updated successfully')) {
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editEmployeeModal'));
                    modal.hide();

                    // Show success message
                    Swal.fire('Success!', '✓ Employee updated successfully!', 'success').then(() => {
                        // Reload the page to refresh employee details
                        location.reload();
                    });
                } else if (data.includes('All mandatory fields are required') || data.includes('Invalid email format') || data.includes('Date of Exit is required')) {
                    // Extract error message from response
                    const errorMatch = data.match(/<p[^>]*>([\s\S]*?)<\/p>/);
                    const errorMsg = errorMatch ? errorMatch[1] : 'Validation error occurred';
                    Swal.fire('Error', errorMsg, 'error');
                } else {
                    Swal.fire('Error', 'Failed to update employee. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Submit error:', error);
                Swal.fire('Error', 'Submit failed: ' + error.message, 'error');
            });
        }
    </script>

    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoUploadModal" tabindex="-1" aria-labelledby="photoUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="photoUploadModalLabel">📸 Capture Employee Photo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Employee:</strong></label>
                        <p id="photoEmployeeName" class="form-control-plaintext" style="font-weight: bold;"></p>
                    </div>

                    <!-- Camera Capture Section -->
                    <div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Camera Preview</strong></label>
                            <div class="position-relative" style="max-width: 100%; background: #000; border-radius: 8px; overflow: hidden;">
                                <video id="cameraVideo" style="width: 100%; height: 400px; object-fit: cover; display: block;" playsinline></video>
                                <canvas id="photoCanvas" style="display: none;"></canvas>
                            </div>
                            <small class="text-muted d-block mt-2">✅ Look directly at the camera for best results. Your photo will be captured when you click "Capture Photo".</small>
                        </div>
                    </div>

                    <input type="hidden" id="photoEmployeeId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="uploadEmployeePhoto()">
                        <i class="fas fa-camera"></i> Capture Photo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editEmployeeModalLabel">✎ Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editEmployeeForm" method="POST" class="row g-3">
                        <input type="hidden" name="emp_id" id="modalEmpId">
                        <input type="hidden" name="update_employee" value="1">
                        
                        <div class="col-md-4">
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="employee_id" id="modalEmployeeId" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="modalName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="modalEmail" class="form-control">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" id="modalPhone" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="sex" id="modalSex" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <select name="department" id="modalDepartment" class="form-control" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Company</label>
                            <select name="company" id="modalCompany" class="form-control" required>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Shift Time</label>
                            <select name="shift_time" id="modalShiftTime" class="form-control" required>
                                <option value="">Select Shift</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <select name="location" id="modalLocation" class="form-control" required>
                                <option value="">Select Location</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" name="date_of_joining" id="modalDateOfJoining" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="modalStatus" class="form-control modal-status-select" required onchange="toggleExitDateFieldModal(this)">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="Resign">Resign</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="modalExitDateField" style="display:none;">
                            <label class="form-label">Date of Exit <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_exit" id="modalDateOfExit" class="form-control modal-exit-date">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Week Off</label>
                            <select name="week_off" id="modalWeekOff" class="form-control" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitEditForm()">✓ Update Employee</button>
                </div>
            </div>
        </div>
    </div>

<?php
$stmt->close();
?>
?>
