<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Only allow admin and qa_manager to mark notifications as read
if ($user_role !== 'admin' && $user_role !== 'qa_manager') {
    $_SESSION['error_message'] = "You don't have permission to perform this action.";
    header("Location: index.php");
    exit();
}

// Check if notification id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid notification ID.";
    header("Location: index.php");
    exit();
}

$notification_id = (int)$_GET['id'];

// Mark notification as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "Notification marked as read.";
} else {
    $_SESSION['error_message'] = "Could not mark notification as read.";
}

// Redirect back to the page they came from, or dashboard if not specified
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: $redirect_url");
exit();