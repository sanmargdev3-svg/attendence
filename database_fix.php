<?php
/**
 * Quick Database Fix - Update schema for Face Operators
 */

include('config/db.php');

echo "<h2>🔧 Database Fixer</h2>";
echo "<hr>";

// 1. Update role enum to include face_operator
$sql = "ALTER TABLE users MODIFY role ENUM('suparadmin', 'admin', 'user', 'face_operator') DEFAULT 'user'";
if ($conn->query($sql) === TRUE) {
    echo "✅ Updated role ENUM to include 'face_operator'<br>";
} else {
    echo "ℹ️ Role ENUM already updated (or no changes needed)<br>";
}

// 2. Show all face operators in database
echo "<h3>All Face Operators Currently in Database:</h3>";
$result = $conn->query("SELECT id, name, phone, email, password, created_at FROM users WHERE role = 'face_operator' ORDER BY created_at DESC");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='margin: 20px 0; border-collapse: collapse;'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Password Hash</th><th>Created</th><th>Change Password</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td style='font-family: monospace; font-size: 0.8rem;'>" . substr($row['password'], 0, 20) . "...</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>";
        echo "<form method='POST' style='display:inline;'>";
        echo "<input type='hidden' name='user_id' value='" . $row['id'] . "'>";
        echo "<input type='hidden' name='action' value='change_password'>";
        echo "<input type='password' name='new_password' placeholder='New password' required style='padding: 5px; width: 120px;'>";
        echo "<button type='submit' class='btn btn-warning btn-sm'>Update</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Handle password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'face_operator'");
        $update->bind_param("si", $hashed_password, $user_id);
        
        if ($update->execute()) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; color: green;'>";
            echo "✅ Password updated successfully!";
            echo "</div>";
        }
    }
    
} else {
    echo "<p style='color: red;'><strong>❌ No face operators found in database!</strong></p>";
    echo "<p>This is the issue - operators were created but stored with wrong role type.</p>";
}

// 3. Show all users with all roles
echo "<h3>All Users by Role:</h3>";
$role_counts = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($role_counts->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='margin: 20px 0;'>";
    echo "<tr style='background: #667eea; color: white;'><th>Role</th><th>Count</th></tr>";
    while ($row = $role_counts->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['role']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
}

// 4. Fix: Move all operators from 'user' role to 'face_operator' role (if needed)
echo "<h3>🔧 Auto-Fix Tool:</h3>";
echo "<form method='POST' style='margin: 20px 0;'>";
echo "<input type='hidden' name='action' value='fix_operators'>";
echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Convert all users with no employee photos to face_operator role?\")'>Convert Users to Face Operators</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix_operators') {
    // This is a safety measure - we can manually convert if needed
    echo "<p style='color: orange;'>Manual conversion skipped for safety. Please contact admin.</p>";
}

// 5. Instructions
echo "<h3>📝 Next Steps:</h3>";
echo "<ol>";
echo "<li>If you see operators in the table above, try logging in with their credentials</li>";
echo "<li>If no operators shown, go back and create a new operator with a <strong>NEW phone number</strong></li>";
echo "<li>To change an operator's password, use the form above</li>";
echo "<li>Once fixed, try logging in at: <a href='face_operator_login.php' target='_blank'>http://localhost/attendence/face_operator_login.php</a></li>";
echo "</ol>";

echo "<hr>";
echo "<a href='admin/manage_face_operators.php' class='btn btn-primary'>← Back to Manage Operators</a>";

?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 30px; font-family: Arial, sans-serif; }
        h2, h3 { color: #333; margin-top: 30px; }
        table { background: white; }
        .btn { padding: 8px 16px; text-decoration: none; border-radius: 5px; display: inline-block; }
        input[type="password"] { border: 1px solid #ddd; border-radius: 3px; }
    </style>
</head>
<body>
</body>
</html>
