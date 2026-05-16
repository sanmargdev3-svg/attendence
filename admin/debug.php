<?php
session_start();
include('../config/db.php');

echo "<h2>🔍 Debug Information</h2>";
echo "<hr>";

// Check 1: Is the role ENUM correct?
echo "<h3>1. Check users table structure:</h3>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'role') {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
    }
}

// Check 2: Check the admin user
echo "<h3>2. Check admin user in database:</h3>";
$check = $conn->prepare("SELECT id, name, phone, role FROM users WHERE phone = '9830376202'");
$check->execute();
$admin_result = $check->get_result();
if ($admin_result->num_rows > 0) {
    $admin = $admin_result->fetch_assoc();
    echo "<pre>";
    print_r($admin);
    echo "</pre>";
} else {
    echo "❌ No user found with phone 9830376202";
}
$check->close();

// Check 3: Try updating directly
echo "<h3>3. Attempting to set role directly to suparadmin:</h3>";
$update = $conn->prepare("UPDATE users SET role = 'suparadmin' WHERE phone = '9830376202'");
if ($update->execute()) {
    echo "✅ Update executed. Affected rows: " . $update->affected_rows;
} else {
    echo "❌ Update failed: " . $conn->error;
}
$update->close();

// Check 4: Verify the update
echo "<h3>4. Verify after update:</h3>";
$verify = $conn->prepare("SELECT id, name, phone, role FROM users WHERE phone = '9830376202'");
$verify->execute();
$verify_result = $verify->get_result();
if ($verify_result->num_rows > 0) {
    $user = $verify_result->fetch_assoc();
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    echo "Role value: <strong>" . htmlspecialchars($user['role']) . "</strong>";
}
$verify->close();

// Check 5: Check login_process.php logic
echo "<h3>5. Dashboard.php check (what role does it expect?):</h3>";
echo "Dashboard expects: <strong>role = 'suparadmin'</strong>";

?>
<br><br>
<a href="../auth/login.php" class="btn btn-primary">Back to Login</a>
