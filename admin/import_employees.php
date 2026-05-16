<?php
session_start();
include('../config/db.php');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin')) {
    header("Location: ../auth/login.php");
    exit();
}
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "Error uploading file";
        $message_type = "danger";
    } else {
        $tmp_file = $file['tmp_name'];
        $handle = fopen($tmp_file, 'r');
        if ($handle) {
            $line_no = 0;
            $imported = 0;
            $skipped = 0;
            while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                $line_no++;
                if ($line_no === 1) continue;
                if (count($data) < 6) {
                    $skipped++;
                    continue;
                }
                $employee_id = htmlspecialchars(trim($data[0]));
                $name = htmlspecialchars(trim($data[1]));
                $email = filter_var(trim($data[2]), FILTER_SANITIZE_EMAIL);
                $company = htmlspecialchars(trim($data[3]));
                $phone = htmlspecialchars(trim($data[4]));
                $department = htmlspecialchars(trim($data[5]));
                if (empty($employee_id) || empty($name) || empty($email) || empty($company) || empty($phone) || empty($department)) {
                    $skipped++;
                    continue;
                }
                $password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role, employee_id, company, phone, department) VALUES (?, ?, ?, 'employee', ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssssss", $name, $email, $hashed_password, $employee_id, $company, $phone, $department);
                if ($insert_stmt->execute()) {
                    $imported++;
                }
                $insert_stmt->close();
            }
            fclose($handle);
            if ($imported > 0) {
                $message = "✓ Successfully imported $imported employees!";
                $message_type = "success";
                header("refresh:2;url=employees.php");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Import Employees</span>
            <a href="employees.php" class="btn btn-secondary btn-sm">Back to Employees</a>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">📥 Import Employees from Excel</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Upload file with columns: Employee ID | Full Name | Email | Company | Phone | Department</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <button type="submit" class="btn btn-success">📥 Import</button>
                    <a href="employees.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php
        if ($message) {
            $icon = $message_type === 'success' ? 'success' : 'error';
            $title = $message_type === 'success' ? 'Success' : 'Error';
            $message_escaped = addslashes($message);
            echo "Swal.fire({icon: '$icon', title: '$title', text: '$message_escaped'});";
        }
        ?>
    </script>
</body>
</html>