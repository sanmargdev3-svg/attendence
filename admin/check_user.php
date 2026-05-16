<?php
include('../config/db.php');

$phone = '9830376200';

$stmt = $conn->prepare("SELECT id, name, phone, role, email FROM users WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h2>✅ User Found:</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    echo "<h3>Role: <strong>" . htmlspecialchars($user['role']) . "</strong></h3>";
    
    if ($user['role'] === 'suparadmin') {
        echo "<p style='color: green;'><strong>✅ YES - This is a SUPERADMIN</strong></p>";
    } elseif ($user['role'] === 'admin') {
        echo "<p style='color: blue;'><strong>✅ YES - This is an ADMIN</strong></p>";
    } elseif ($user['role'] === 'employee') {
        echo "<p style='color: orange;'><strong>⚠️ NO - This is an EMPLOYEE</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Unknown role: " . htmlspecialchars($user['role']) . "</strong></p>";
    }
} else {
    echo "<h2>❌ User NOT Found</h2>";
    echo "<p>No user exists with phone: <strong>9830376200</strong></p>";
}

$stmt->close();
?>

<hr>
<a href="javascript:history.back()">← Go Back</a>
