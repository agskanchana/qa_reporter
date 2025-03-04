<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a webmaster
if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    $_SESSION['error'] = "You don't have permission to perform this action.";
    header("Location: dashboard.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request.";
    header("Location: dashboard.php");
    exit();
}

// Get form data
$project_id = (int)$_POST['project_id'];
$deadline_type = $conn->real_escape_string($_POST['deadline_type']);

// Only allow WP conversion deadline extensions
if ($deadline_type !== 'wp_conversion') {
    $_SESSION['error'] = "Only WP conversion deadline extensions are supported.";
    header("Location: view_project.php?id=$project_id");
    exit();
}

$original_deadline = $conn->real_escape_string($_POST['original_deadline']);
$requested_deadline = $conn->real_escape_string($_POST['requested_deadline']);
$reason = $conn->real_escape_string($_POST['reason']);
$user_id = $_SESSION['user_id'];

// Validate the project belongs to this webmaster
$check_query = "SELECT id FROM projects WHERE id = ? AND webmaster_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "You don't have permission to request extensions for this project.";
    header("Location: dashboard.php");
    exit();
}

// Check if there's already a pending request for this deadline type
$check_pending = "SELECT id FROM deadline_extension_requests
                 WHERE project_id = ? AND deadline_type = 'wp_conversion' AND status = 'pending'";
$stmt = $conn->prepare($check_pending);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error'] = "You already have a pending extension request for the WP conversion deadline.";
    header("Location: view_project.php?id=$project_id");
    exit();
}

// Insert the extension request
$insert_query = "INSERT INTO deadline_extension_requests
                (project_id, deadline_type, original_deadline, requested_deadline, reason, requested_by)
                VALUES (?, 'wp_conversion', ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("isssi", $project_id, $original_deadline, $requested_deadline, $reason, $user_id);

if ($stmt->execute()) {
    // Get project details for the notification
    $project_query = "SELECT name FROM projects WHERE id = ?";
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    // Get webmaster name
    $user_query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Create notification for admins
    $notification_message = "WP Conversion deadline extension requested by {$user['username']} for project: {$project['name']}";

    // Add notification for admins
    addNotification(
        $notification_message,
        'warning',
        null,
        'admin'
    );

    $_SESSION['success'] = "WP Conversion deadline extension request submitted successfully.";
} else {
    $_SESSION['error'] = "Failed to submit deadline extension request.";
}

header("Location: view_project.php?id=$project_id");
exit();
?>