<?php
session_start();
require_once 'config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'suparadmin') {
    die('Access denied. Admin login required.');
}

echo "<h1>Setting up test users with username login...</h1>";

// Test users to create/update
$testUsers = [
    [
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'phone' => '9999999999',
        'password' => 'admin123',
        'role' => 'admin',
        'department' => 'Management'
    ],
    [
        'name' => 'Test Employee',
        'email' => 'employee@test.com',
        'phone' => '9999999998',
        'password' => 'admin123',
        'role' => 'employee',
        'department' => 'Operations'
    ],
    [
        'name' => 'Face Operator',
        'email' => 'operator@test.com',
        'phone' => '9999999997',
        'password' => 'admin123',
        'role' => 'face_operator',
        'department' => 'HR'
    ]
];

foreach ($testUsers as $user) {
    // Check if user exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $check->bind_param('ss', $user['email'], $user['phone']);
    $check->execute();
    $result = $check->get_result();
    
    $hashedPassword = password_hash($user['password'], PASSWORD_BCRYPT);
    
    if ($result->num_rows > 0) {
        // Update existing user
        $row = $result->fetch_assoc();
        $update = $conn->prepare("UPDATE users SET name = ?, password = ?, role = ?, department = ? WHERE id = ?");
        $update->bind_param('ssssi', $user['name'], $hashedPassword, $user['role'], $user['department'], $row['id']);
        if ($update->execute()) {
            echo "✅ Updated: {$user['name']} ({$user['email']})<br>";
        } else {
            echo "❌ Failed to update: {$user['name']}<br>";
        }
    } else {
        // Create new user
        $insert = $conn->prepare("INSERT INTO users (name, email, phone, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param('ssssss', $user['name'], $user['email'], $user['phone'], $hashedPassword, $user['role'], $user['department']);
        if ($insert->execute()) {
            echo "✅ Created: {$user['name']} ({$user['email']})<br>";
        } else {
            echo "❌ Failed to create: {$user['name']} - " . $insert->error . "<br>";
        }
    }
    $check->close();
}

echo "<br><h3>✅ Setup Complete!</h3>";
echo "<p>You can now login using:</p>";
echo "<ul>";
echo "<li><strong>Username:</strong> Admin User | <strong>Email:</strong> admin@test.com | <strong>Pass:</strong> admin123</li>";
echo "<li><strong>Username:</strong> Test Employee | <strong>Email:</strong> employee@test.com | <strong>Pass:</strong> admin123</li>";
echo "<li><strong>Username:</strong> Face Operator | <strong>Email:</strong> operator@test.com | <strong>Pass:</strong> admin123</li>";
echo "</ul>";
echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
?>
