<?php
// install.php
require_once 'config.php';

// Check if admin user already exists
$query = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    die("Installation has already been completed. Please delete this file for security.");
}

// Create initial admin user
$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$role = 'admin';

$query = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $username, $password, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<strong>Please delete this file (install.php) for security reasons.</strong><br>";
    echo "<a href='login.php'>Go to Login Page</a>";
} else {
    echo "Error creating admin user: " . $conn->error;
}