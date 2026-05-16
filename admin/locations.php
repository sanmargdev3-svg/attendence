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
} else {
    $back_dashboard = ($_SESSION['role'] === 'suparadmin') ? 'dashboard.php' : 'admin_dashboard.php';
}

$message = "";
$message_type = "";

// Create locations table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle Add Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $location_name = htmlspecialchars(trim($_POST['location_name']));
    
    if (empty($location_name)) {
        $message = "Location name cannot be empty";
        $message_type = "danger";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM locations WHERE name = ?");
        $stmt_check->bind_param("s", $location_name);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message = "This location already exists";
            $message_type = "warning";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO locations (name) VALUES (?)");
            $stmt_insert->bind_param("s", $location_name);
            
            if ($stmt_insert->execute()) {
                $message = "✓ Location added successfully!";
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

// Handle Delete Location
if (isset($_GET['delete_location'])) {
    $location_id = (int)$_GET['delete_location'];
    
    // Check if location is being used by any employee
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE location = (SELECT name FROM locations WHERE id = ?) LIMIT 1");
    $stmt_check->bind_param("i", $location_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $message = "Cannot delete location that is assigned to employees";
        $message_type = "danger";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM locations WHERE id = ?");
        $stmt_delete->bind_param("i", $location_id);
        
        if ($stmt_delete->execute()) {
            $message = "✓ Location deleted successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Error: " . $stmt_delete->error;
            $message_type = "danger";
        }
        $stmt_delete->close();
    }
    $stmt_check->close();
}

// Fetch all locations
$stmt = $conn->prepare("SELECT id, name, created_at FROM locations ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Management - Attendance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">📍 Location Management</span>
            <div>

                <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Add Location Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">➕ Add New Location</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" class="form-control" placeholder="e.g., Head Office, Branch Office" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="add_location" class="btn btn-success w-100">✓ Add Location</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Locations List -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">All Locations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Location Name</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['id'] . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                                    echo "<td>" . date('d-m-Y H:i', strtotime($row['created_at'])) . "</td>";
                                    echo "<td>";
                                    echo "<button type='button' onclick=\"confirmDeleteLocation(" . $row['id'] . ", '" . htmlspecialchars($row['name']) . "')\" class='btn btn-sm btn-danger'>Delete</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted'>No locations found. Add one to get started!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="<?php echo htmlspecialchars($back_dashboard); ?>" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDeleteLocation(locationId, locationName) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Delete location: ' + locationName,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_location=' + locationId;
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
    </script>
</body>
</html>

<?php
$stmt->close();
?>
