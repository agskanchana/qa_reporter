<?php
// config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bug_reporter_jk_poe');



// For manual override (uncomment and modify if auto-detection doesn't work)
define('BASE_URL', 'http://localhost/bug_reporter_jk_poe/');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function checkPermission($allowedRoles) {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header("Location: " . BASE_URL . "unauthorized.php");
        exit();
    }
}

// URL Helper function
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}