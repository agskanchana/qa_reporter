<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log to a file
function debug_log($message) {
    $log_file = __DIR__ . '/extension_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log('Request started');

// Check if user is logged in and is an admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    debug_log('Access denied: ' . getUserRole());
    $_SESSION['error'] = "You don't have permission to perform this action.";
    header("Location: dashboard.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error'] = "Invalid request.";
    header("Location: dashboard.php");
    exit();
}

// Get form data
$request_id = (int)$_POST['request_id'];

// Convert 'rejected' to 'denied' to match the database enum
$status = $conn->real_escape_string($_POST['status']);
if ($status === 'rejected') {
    $status = 'denied';
}

debug_log("Original status from form: " . $_POST['status'] . ", Converted to: $status");

$comment = isset($_POST['comment']) ? $conn->real_escape_string($_POST['comment']) : '';
$admin_id = $_SESSION['user_id'];

debug_log("Processing request_id: $request_id, status: $status");

// Fetch the extension request details
$query = "SELECT r.*, p.name as project_name, p.webmaster_id, u.username as webmaster_name,
          p.wp_conversion_deadline, p.project_deadline
          FROM deadline_extension_requests r
          JOIN projects p ON r.project_id = p.id
          JOIN users u ON p.webmaster_id = u.id
          WHERE r.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    debug_log("Request not found: $request_id");
    $_SESSION['error'] = "Extension request not found.";
    header("Location: dashboard.php");
    exit();
}

debug_log("Found request: " . json_encode($request));

// Update the request status
$update_query = "UPDATE deadline_extension_requests
                SET status = ?, reviewed_by = ?, review_comment = ?, reviewed_at = NOW()
                WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("sisi", $status, $admin_id, $comment, $request_id);
$result = $stmt->execute();

debug_log("Status update result: " . ($result ? "success" : "failed") . ", Error: " . $stmt->error);

// If approved, update the project deadline
if ($status === 'approved') {
    $deadline_field = ($request['deadline_type'] === 'wp_conversion') ? 'wp_conversion_deadline' : 'project_deadline';

    $update_project = "UPDATE projects SET $deadline_field = ? WHERE id = ?";
    $stmt = $conn->prepare($update_project);
    $stmt->bind_param("si", $request['requested_deadline'], $request['project_id']);
    $update_result = $stmt->execute();

    debug_log("Project deadline update: " . ($update_result ? "success" : "failed") . ", Error: " . $stmt->error);
}

// Create notification for the webmaster
$notification_message = "Your WP Conversion deadline extension request for project '{$request['project_name']}' has been " .
                      ($status === 'approved' ? 'approved' : 'denied') . ".";

debug_log("Adding notification for webmaster ID: {$request['webmaster_id']}, Status: " .
         ($status === 'approved' ? 'approved' : 'denied'));

// Add notification for the webmaster
$notification_type = $status === 'approved' ? 'success' : 'danger';

// Check if addNotification function exists
if (function_exists('addNotification')) {
    $notif_result = addNotification(
        $notification_message,
        $notification_type,
        $request['webmaster_id'],
        null
    );
    debug_log("Notification added via function: " . ($notif_result ? "success" : "failed"));
} else {
    // Manual notification creation if function doesn't exist
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $request['webmaster_id'], $notification_message, $notification_type);
    $notif_result = $stmt->execute();
    debug_log("Notification added manually: " . ($notif_result ? "success" : "failed") . ", Error: " . $stmt->error);
}

// Double-check the status was set correctly
$check_query = "SELECT status FROM deadline_extension_requests WHERE id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$check_result = $stmt->get_result()->fetch_assoc();
debug_log("Final status check: " . ($check_result ? $check_result['status'] : "not found"));

// Get the actual values in the database after update
$check_status_query = "SELECT id, status FROM deadline_extension_requests";
$status_result = $conn->query($check_status_query);
$status_values = [];
while ($row = $status_result->fetch_assoc()) {
    $status_values[] = $row;
}
debug_log("All status values: " . json_encode($status_values));

$_SESSION['success'] = "Extension request has been " . ($status === 'approved' ? 'approved' : 'denied') . ".";
debug_log("Process completed successfully, redirecting to deadline_requests.php");
header("Location: deadline_requests.php?tab=denied"); // Direct to the denied tab
exit();
?>