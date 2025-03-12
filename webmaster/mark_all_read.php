<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a webmaster
if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$webmaster_id = $_SESSION['user_id'];
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : $_SERVER['HTTP_REFERER'];

// Mark all notifications as read
$query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $webmaster_id);
$stmt->execute();

// If there's a qa_feedback table, you can add code to mark those as read too

// Redirect back
header("Location: " . $redirect);
exit();