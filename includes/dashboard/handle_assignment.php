<?php
// This file is included in the admin dashboard when a QA assignment form is submitted

// Get the submitted data
$project_id = (int)$_POST['project_id'];
$qa_user_id = (int)$_POST['qa_user_id'];

// Validate the data
if ($project_id <= 0 || $qa_user_id <= 0) {
    $_SESSION['error_message'] = "Invalid project or QA user ID.";
    return;
}

// Check if the project exists
$project_check_stmt = $conn->prepare("SELECT id, name FROM projects WHERE id = ?");
$project_check_stmt->bind_param("i", $project_id);
$project_check_stmt->execute();
$project_result = $project_check_stmt->get_result();

if ($project_result->num_rows === 0) {
    $_SESSION['error_message'] = "Project not found.";
    return;
}

$project = $project_result->fetch_assoc();

// Check if the QA user exists and has correct role
$user_check_stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ? AND role IN ('qa_reporter', 'qa_manager')");
$user_check_stmt->bind_param("i", $qa_user_id);
$user_check_stmt->execute();
$user_result = $user_check_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['error_message'] = "Selected user is not a valid QA reporter.";
    return;
}

$qa_user = $user_result->fetch_assoc();

// Check if project is already assigned
$check_stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ?");
$check_stmt->bind_param("i", $project_id);
$check_stmt->execute();
$existing = $check_stmt->get_result();

// Begin transaction for data consistency
$conn->begin_transaction();

try {
    if ($existing->num_rows > 0) {
        // Update existing assignment
        $assignment_id = $existing->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE qa_assignments SET qa_user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE id = ?");
        $stmt->bind_param("iii", $qa_user_id, $user_id, $assignment_id);
        $stmt->execute();

        // Add to project history
        $action = "QA Reporter reassigned to " . $qa_user['username'];
        $history_stmt = $conn->prepare("INSERT INTO project_history (project_id, user_id, action, created_at) VALUES (?, ?, ?, NOW())");
        $history_stmt->bind_param("iis", $project_id, $user_id, $action);
        $history_stmt->execute();

        $_SESSION['success_message'] = "QA Reporter reassigned successfully.";
    } else {
        // Create new assignment
        $stmt = $conn->prepare("INSERT INTO qa_assignments (project_id, qa_user_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $project_id, $qa_user_id, $user_id);
        $stmt->execute();

        // Add to project history
        $action = "QA Reporter assigned to " . $qa_user['username'];
        $history_stmt = $conn->prepare("INSERT INTO project_history (project_id, user_id, action, created_at) VALUES (?, ?, ?, NOW())");
        $history_stmt->bind_param("iis", $project_id, $user_id, $action);
        $history_stmt->execute();

        $_SESSION['success_message'] = "QA Reporter assigned successfully.";
    }

    // Commit transaction
    $conn->commit();

    // Create notification for the QA user
    $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, content, created_at, is_read, related_id)
                                         VALUES (?, 'qa_assignment', ?, NOW(), 0, ?)");
    $notification_content = "You have been assigned to QA project: " . $project['name'];
    $notification_stmt->bind_param("isi", $qa_user_id, $notification_content, $project_id);
    $notification_stmt->execute();

} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error assigning QA Reporter: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();