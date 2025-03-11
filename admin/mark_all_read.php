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

// Only allow admin and qa_manager to mark all notifications as read
if ($user_role !== 'admin' && $user_role !== 'qa_manager') {
    $_SESSION['error_message'] = "You don't have permission to perform this action.";
    header("Location: index.php");
    exit();
}

// Mark all notifications as read for this user
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "All notifications marked as read.";
} else {
    $_SESSION['info_message'] = "No unread notifications to mark.";
}

// Redirect back to the page they came from, or dashboard if not specified
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: $redirect_url");
exit();