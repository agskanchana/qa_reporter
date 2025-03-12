<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a webmaster
if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$webmaster_id = $_SESSION['user_id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : $_SERVER['HTTP_REFERER'];

if ($notification_id > 0) {
    if ($type === 'notification') {
        // Mark notification as read
        $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notification_id, $webmaster_id);
        $stmt->execute();
    }
    // Other types can be added here if needed
}

// Redirect back
header("Location: " . $redirect);
exit();